<?php

namespace App\Domain\Assistant\Services;

use App\Domain\Assistant\Models\AiConversation;
use App\Domain\Assistant\Models\AiRun;
use App\Domain\Finance\Models\RentCharge;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Closed registry. Tools can read or propose, never confirm. */
final class ToolRegistry
{
    public function __construct(private readonly PortfolioAssistantTools $reads, private readonly AiCapabilities $capabilities) {}

    public function definitions(Portfolio $portfolio, User $user): array
    {
        $tools = [...$this->reads->definitions(), ...app(AssistantLeasingQueries::class)->definitions()];
        if (config('ai.resolve_property_references')) {
            foreach ($tools as &$tool) {
                if (in_array($tool['name'], ['get_financial_summary', 'list_rent_charges'], true)) {
                    $tool['description'] .= ' Si el usuario da el nombre del inmueble, usa property_query directamente: Laravel lo resuelve con la misma búsqueda de inmuebles, sin otra llamada. Nunca combines property_query con property_id. Una referencia ambigua pide aclaración.';
                    $properties = (array) $tool['parameters']['properties'];
                    $properties['property_query'] = [
                        'type' => ['string', 'null'], 'maxLength' => 120,
                        'description' => 'Nombre y ciudad del inmueble indicados por el usuario, o null. No inventes referencias; null para toda la cartera o si usas un property_id ya resuelto.',
                    ];
                    $tool['parameters']['properties'] = is_object($tool['parameters']['properties']) ? (object) $properties : $properties;
                    $tool['parameters']['required'][] = 'property_query';
                }
            }
            unset($tool);
        }
        $tools[] = $this->definition('search_properties', 'Busca inmuebles por nombre y ciudad; acepta «de»/«en» como conectores. Obligatorio antes de proponer gasto o nota; varias coincidencias requieren aclaración.', [
            'query' => ['type' => 'string', 'maxLength' => 120],
        ]);
        if ($this->capabilities->allowsActions($portfolio, $user)) {
            $tools[] = $this->definition('propose_expense', 'Prepara una preview SIN crear el gasto. Sólo tras search_properties con una coincidencia. Importe ausente o incierto: amount null para que Alquivo lo pregunte; nunca elijas uno de varios importes posibles. Si no dice pagado usa pending. Sólo el botón del usuario confirma.', [
                'property_id' => ['type' => 'integer', 'minimum' => 1],
                'amount' => ['type' => ['string', 'null'], 'pattern' => '^[0-9]{1,10}(\\.[0-9]{1,2})?$'],
                'category' => ['type' => 'string', 'enum' => ['maintenance', 'tax', 'insurance', 'other']],
                'description' => ['type' => 'string', 'maxLength' => 180],
                'transaction_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD. Fecha omitida: hoy, siempre visible en preview.'],
                'status' => ['type' => 'string', 'enum' => ['paid', 'pending']],
            ]);
            $tools[] = $this->definition('propose_contact_phone', 'Prepara cambio de teléfono SIN guardar. Requiere search_contacts con una sola coincidencia y un teléfono proporcionado por el usuario. phone null si falta el número nuevo: Alquivo lo preguntará. Nunca inventes un teléfono.', [
                'contact_id' => ['type' => 'integer', 'minimum' => 1], 'phone' => ['type' => ['string', 'null'], 'maxLength' => 30],
            ]);
            $tools[] = $this->definition('propose_rent_payment', 'Prepara un cobro SIN guardar. Requiere list_rent_charges filtrado a una única mensualidad pendiente. Sólo dinero recibido, no una promesa de pago. No crea cargos ni inventa el periodo.', [
                'rent_charge_id' => ['type' => 'integer', 'minimum' => 1],
                'amount' => ['type' => 'string', 'pattern' => '^[0-9]{1,10}(\\.[0-9]{1,2})?$'],
                'transaction_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD. Hoy si no indica fecha; visible en la propuesta.'],
                'payment_method' => ['type' => ['string', 'null'], 'maxLength' => 40, 'description' => 'Sólo si lo indicó el usuario; si no, null.'],
            ]);
            $tools[] = $this->definition('propose_property_note', 'Prepara añadir una nota SIN guardar ni sustituir notas existentes. Requiere search_properties con una coincidencia. Conserva el significado del texto del usuario, no inventes hechos.', [
                'property_id' => ['type' => 'integer', 'minimum' => 1], 'note' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 2000],
            ]);
        }

        if ($this->capabilities->allowsCreations($portfolio, $user)) {
            $tools = [...$tools, ...app(CreationProposalTools::class)->definitions()];
        }

        return $tools;
    }

    public function execute(Portfolio $portfolio, User $user, AiRun $run, string $name, array $arguments, ?int $contextPropertyId = null): array
    {
        abort_unless($run->portfolio_id === $portfolio->id && $run->user_id === $user->id
            && $portfolio->members()->whereKey($user->id)->exists(), 404);
        if (! in_array($name, array_column($this->definitions($portfolio, $user), 'name'), true)) {
            throw new InvalidArgumentException('Herramienta no permitida.');
        }
        if (($clarification = app(AssistantMoneyInput::class)->clarification($portfolio, $user, $run, $name, $arguments)) !== null) {
            return $clarification;
        }
        // Direct IDs recalled from history must obey the same current-turn ambiguity policy.
        $targetProperty = $arguments['property_id'] ?? null;
        if ($targetProperty === null && isset($arguments['lease_id'])) {
            $targetProperty = Lease::where('portfolio_id', $portfolio->id)->whereKey($arguments['lease_id'])->value('property_id');
        }
        if ($targetProperty === null && $name === 'propose_rent_payment' && isset($arguments['rent_charge_id'])) {
            $targetProperty = RentCharge::where('portfolio_id', $portfolio->id)->whereKey($arguments['rent_charge_id'])
                ->first()?->lease()->where('portfolio_id', $portfolio->id)->value('property_id');
        }
        if (is_int($targetProperty) && ($ambiguous = $this->currentAmbiguity($portfolio, $user, $run, [$targetProperty])) !== null
            && ! ($contextPropertyId === $targetProperty && $ambiguous->contains('id', $contextPropertyId))) {
            foreach (['search_properties' => 'property_ids', 'list_rent_charges' => 'rent_charge_ids'] as $tool => $field) {
                $run->steps()->create(['kind' => 'resolution', 'tool' => $tool, 'status' => 'completed', 'metadata' => [$field => []]]);
            }

            return ['properties' => $ambiguous->take(20)->toArray(), 'count' => $ambiguous->count(),
                'needs_clarification' => true, 'clarification_domain' => 'property'];
        }
        if (in_array($name, ['propose_property', 'propose_contact', 'propose_lease'], true)) {
            return app(CreationProposalTools::class)->execute($portfolio, $user, $run, $name, $arguments, $contextPropertyId);
        }
        if (config('ai.resolve_property_references') && in_array($name, ['get_financial_summary', 'list_rent_charges'], true)) {
            $query = $arguments['property_query'] ?? null;
            unset($arguments['property_query']);
            if ($query !== null) {
                $data = Validator::make(['query' => $query], ['query' => ['required', 'string', 'max:120']])->validate();
                if (($arguments['property_id'] ?? null) !== null) {
                    throw new InvalidArgumentException('Indica el inmueble por nombre o por ID, no ambos.');
                }
                // Reuse the same authorized resolver and its current-run evidence, not fuzzy matching.
                $found = $this->execute($portfolio, $user, $run, 'search_properties', ['query' => $data['query']], $contextPropertyId);
                if ($found['needs_clarification']) {
                    if ($name === 'list_rent_charges') {
                        $run->steps()->create(['kind' => 'resolution', 'tool' => $name, 'status' => 'completed', 'metadata' => ['rent_charge_ids' => []]]);
                    }

                    return [...$found, 'clarification_domain' => 'property'];
                }
                $arguments['property_id'] = $found['properties'][0]['id'];
            }
        }
        if ($name === 'search_properties') {
            if (array_diff(array_keys($arguments), ['query'])) {
                throw new InvalidArgumentException('Parámetros no permitidos.');
            }
            $data = Validator::make($arguments, ['query' => ['required', 'string', 'max:120']])->validate();
            $needle = mb_strtolower(Str::ascii(trim($data['query'])));
            if ($needle === '') {
                throw new InvalidArgumentException('Indica el nombre del inmueble.');
            }
            // Small portfolios: consistent accent normalization without a DB-specific extension.
            $words = array_values(array_filter(preg_split('/[^a-z0-9]+/i', $needle), fn ($word) => $word !== '' && ! in_array($word, ['de', 'en'], true)));
            if ($words === []) {
                throw new InvalidArgumentException('Indica el nombre del inmueble.');
            }
            $properties = $portfolio->properties()->orderBy('id')->get(['id', 'name', 'city']);
            $candidates = $properties
                ->filter(function ($property) use ($words) {
                    $labelWords = preg_split('/[^a-z0-9]+/i', mb_strtolower(Str::ascii($property->name.' '.$property->city)));

                    return collect($words)->every(fn ($word) => in_array($word, $labelWords, true));
                })->values();
            $exact = $candidates->filter(fn ($property) => mb_strtolower(Str::ascii($property->name)) === $needle)->values();
            if ($exact->count() === 1) {
                $candidates = $exact;
            }
            // A model may silently add the city from history. An explicitly repeated homonym
            // without a current city is not a unique write reference, even after discussing one.
            if (($ambiguous = $this->currentAmbiguity($portfolio, $user, $run, $candidates->pluck('id')->all(), $properties)) !== null) {
                $candidates = $ambiguous;
            }
            if ($contextPropertyId && $candidates->count() > 1 && $candidates->contains('id', $contextPropertyId)) {
                // The context comes from the authorized HTTP request, never from tool arguments.
                $candidates = $candidates->where('id', $contextPropertyId)->values();
            }
            $ids = $candidates->pluck('id')->all();
            $run->steps()->create(['kind' => 'resolution', 'tool' => $name, 'status' => 'completed', 'metadata' => ['property_ids' => $ids]]);

            // A reference such as «piso de Juan» is not a property name. Find possible people only to ask for clarification.
            $contactQuery = preg_match('/^(?:el\\s+)?(?:piso|casa|inmueble)\\s+de\\s+(.+)$/u', $needle, $personMatch)
                ? trim($personMatch[1]) : $data['query'];
            $relatedContacts = $candidates->isEmpty() && mb_strlen($contactQuery) >= 2
                ? app(AssistantLeasingQueries::class)->execute($portfolio, 'search_contacts', ['query' => $contactQuery, 'property_id' => null])['contacts']
                : [];

            return ['properties' => $candidates->take(20)->toArray(), 'count' => count($ids),
                'needs_clarification' => count($ids) !== 1, 'related_contacts' => $relatedContacts];
        }
        if (in_array($name, array_column(app(AssistantLeasingQueries::class)->definitions(), 'name'), true)) {
            $result = app(AssistantLeasingQueries::class)->execute($portfolio, $name, $arguments);
            if ($name === 'search_contacts' || $name === 'list_rent_charges') {
                $field = $name === 'search_contacts' ? 'contact_ids' : 'rent_charge_ids';
                $items = $result[$name === 'search_contacts' ? 'contacts' : 'charges'];
                // Never resolve from an incomplete list or from a paid/cancelled charge.
                $single = $result['count'] === 1 && ! $result['truncated']
                    && ($name === 'search_contacts' || bccomp($items[0]['remaining_amount'], '0', 2) > 0);
                $run->steps()->create(['kind' => 'resolution', 'tool' => $name, 'status' => 'completed', 'metadata' => [$field => $single ? [$items[0]['id']] : []]]);
            }

            return $result;
        }
        if (in_array($name, ['propose_expense', 'propose_property_note', 'propose_contact_phone', 'propose_rent_payment'], true)) {
            [$tool, $key, $idKey, $method] = match ($name) {
                'propose_expense' => ['search_properties', 'property_ids', 'property_id', 'proposeExpense'],
                'propose_property_note' => ['search_properties', 'property_ids', 'property_id', 'proposePropertyNote'],
                'propose_contact_phone' => ['search_contacts', 'contact_ids', 'contact_id', 'proposeContactPhone'],
                'propose_rent_payment' => ['list_rent_charges', 'rent_charge_ids', 'rent_charge_id', 'proposeRentPayment'],
            };
            $resolved = $run->steps()->where('kind', 'resolution')->where('tool', $tool)->latest('id')->first();
            $ids = $resolved?->metadata[$key] ?? [];
            if (count($ids) !== 1 || $ids[0] !== ($arguments[$idKey] ?? null)) {
                throw new InvalidArgumentException('Localiza un único registro antes de preparar la propuesta.');
            }
            $missingField = match ($name) {
                'propose_expense' => 'amount', 'propose_contact_phone' => 'phone', default => null,
            };
            if ($missingField !== null && (($arguments[$missingField] ?? null) === null || trim((string) $arguments[$missingField]) === '')) {
                app(ProposalActionRegistry::class)->assertFields($arguments, array_keys(collect($this->definitions($portfolio, $user))->firstWhere('name', $name)['parameters']['properties']));

                return ['clarification' => AssistantReply::clarification($missingField === 'amount' ? 'missing_amount' : 'missing_phone'),
                    'missing_fields' => [$missingField]];
            }
            $proposal = app(ActionProposalService::class)->{$method}($portfolio, $user, $run, $arguments);

            return ['proposal' => app(ActionProposalService::class)->present($proposal)];
        }

        return $this->reads->execute($portfolio, $name, $arguments);
    }

    private function definition(string $name, string $description, array $properties): array
    {
        return ['type' => 'function', 'name' => $name, 'description' => $description, 'strict' => true,
            'parameters' => ['type' => 'object', 'properties' => $properties, 'required' => array_keys($properties), 'additionalProperties' => false]];
    }

    private function currentAmbiguity(Portfolio $portfolio, User $user, AiRun $run, array $candidateIds, ?Collection $properties = null): ?Collection
    {
        if ($run->user_message_id === null || $candidateIds === []) {
            return null;
        }
        $current = AiConversation::whereKey($run->conversation_id)->where('portfolio_id', $portfolio->id)->where('user_id', $user->id)
            ->first()?->messages()->whereKey($run->user_message_id)->where('role', 'user')->first()?->content;
        if (! is_string($current)) {
            return null;
        }
        $words = preg_split('/[^a-z0-9]+/i', mb_strtolower(Str::ascii($current)));
        $label = ' '.implode(' ', $words).' ';
        $properties ??= $portfolio->properties()->orderBy('id')->get(['id', 'name', 'city']);
        foreach ($properties->groupBy(fn ($property) => mb_strtolower(Str::ascii(trim($property->name)))) as $name => $sameName) {
            if ($sameName->count() < 2 || ! $sameName->whereIn('id', $candidateIds)->count()
                || ! str_contains($label, ' '.implode(' ', preg_split('/[^a-z0-9]+/i', $name)).' ')) {
                continue;
            }
            $explicitCity = $sameName->filter(function ($property) use ($words) {
                $city = array_filter(preg_split('/[^a-z0-9]+/i', mb_strtolower(Str::ascii((string) $property->city))));

                return $city !== [] && collect($city)->every(fn ($word) => in_array($word, $words, true));
            });
            if ($explicitCity->isEmpty() || $explicitCity->whereIn('id', $candidateIds)->isEmpty()) {
                return $sameName->values();
            }
        }

        return null;
    }
}
