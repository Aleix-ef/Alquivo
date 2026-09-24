<?php

namespace App\Domain\Assistant\Services;

use App\Domain\Assistant\Models\AiRun;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
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
        $tools[] = $this->definition('search_properties', 'Busca inmuebles por nombre. Obligatorio antes de proponer un gasto; varias coincidencias requieren aclaración.', [
            'query' => ['type' => 'string', 'maxLength' => 120],
        ]);
        if ($this->capabilities->allowsActions($portfolio, $user)) {
            $tools[] = $this->definition('propose_expense', 'Prepara una preview SIN crear el gasto. Sólo tras search_properties con una coincidencia. Si no dice pagado usa pending. Sólo el botón del usuario confirma.', [
                'property_id' => ['type' => 'integer', 'minimum' => 1],
                'amount' => ['type' => 'string', 'pattern' => '^[0-9]{1,10}(\\.[0-9]{1,2})?$'],
                'category' => ['type' => 'string', 'enum' => ['maintenance', 'tax', 'insurance', 'other']],
                'description' => ['type' => 'string', 'maxLength' => 180],
                'transaction_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD. Fecha omitida: hoy, siempre visible en preview.'],
                'status' => ['type' => 'string', 'enum' => ['paid', 'pending']],
            ]);
            $tools[] = $this->definition('propose_contact_phone', 'Prepara cambio de teléfono SIN guardar. Requiere search_contacts con una sola coincidencia y un teléfono proporcionado por el usuario. Nunca inventes un teléfono.', [
                'contact_id' => ['type' => 'integer', 'minimum' => 1], 'phone' => ['type' => 'string', 'maxLength' => 30],
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

        return $tools;
    }

    public function execute(Portfolio $portfolio, User $user, AiRun $run, string $name, array $arguments, ?int $contextPropertyId = null): array
    {
        abort_unless($run->portfolio_id === $portfolio->id && $run->user_id === $user->id
            && $portfolio->members()->whereKey($user->id)->exists(), 404);
        if (! in_array($name, array_column($this->definitions($portfolio, $user), 'name'), true)) {
            throw new InvalidArgumentException('Herramienta no permitida.');
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
            $words = preg_split('/\s+/', $needle);
            $candidates = $portfolio->properties()->orderBy('id')->get(['id', 'name', 'city'])
                ->filter(function ($property) use ($words) {
                    $label = mb_strtolower(Str::ascii($property->name.' '.$property->city));

                    return collect($words)->every(fn ($word) => str_contains($label, $word));
                })->values();
            $exact = $candidates->filter(fn ($property) => mb_strtolower(Str::ascii($property->name)) === $needle)->values();
            if ($exact->count() === 1) {
                $candidates = $exact;
            }
            if ($contextPropertyId && $candidates->count() > 1 && $candidates->contains('id', $contextPropertyId)) {
                // The context comes from the authorized HTTP request, never from tool arguments.
                $candidates = $candidates->where('id', $contextPropertyId)->values();
            }
            $ids = $candidates->pluck('id')->all();
            $run->steps()->create(['kind' => 'resolution', 'tool' => $name, 'status' => 'completed', 'metadata' => ['property_ids' => $ids]]);

            return ['properties' => $candidates->take(20)->toArray(), 'count' => count($ids), 'needs_clarification' => count($ids) !== 1];
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
}
