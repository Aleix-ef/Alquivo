<?php

namespace App\Domain\Assistant\Services;

use App\Domain\Assistant\Models\AiRun;
use App\Domain\Portfolio\Models\Portfolio;
use App\Domain\Properties\Actions\CreateProperty;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class CreationProposalTools
{
    public function definitions(): array
    {
        $text = fn (int $max) => ['type' => ['string', 'null'], 'maxLength' => $max];
        $money = ['type' => ['string', 'null'], 'description' => 'Importe exacto indicado por el usuario, con punto y hasta dos decimales; null si no lo ha indicado.'];

        return [
            $this->definition('propose_property', 'Prepara un inmueble NUEVO. Requiere nombre, tipo y dirección proporcionados por el usuario; null si faltan para pedirlos. Nunca inventes dirección, precio ni valoración. Sólo se crea al confirmar el botón.', [
                'name' => $text(120), 'type' => ['type' => ['string', 'null'], 'enum' => [...CreateProperty::TYPES, null]],
                'address_line' => $text(255), 'city' => $text(100), 'purchase_price' => $money, 'current_value' => $money,
            ]),
            $this->definition('propose_contact', 'Prepara un contacto NUEVO con nombre del usuario. kind null significa persona, visible en preview. Correo/teléfono únicamente si el usuario los escribe; null en otro caso. No vincula inquilinos a un contrato ni modifica contactos existentes.', [
                'name' => $text(120), 'kind' => ['type' => ['string', 'null'], 'enum' => ['person', 'company', null]],
                'email' => $text(255), 'phone' => $text(30),
            ]),
            $this->definition('propose_lease', 'Prepara un contrato EN BORRADOR sin activar alquiler ni emitir mensualidades. Laravel resuelve nombre+ciudad del inmueble y nombres completos de inquilinos EXISTENTES; ambigüedad exige aclaración. Requiere fecha inicial, renta y día de cobro (1-28); no inventes estos datos. fin/deposito null significa sin fin/0, visible en preview. Si falta un contacto, propone crearlo en otra operación y espera al botón antes de usarlo.', [
                'property_query' => $text(120),
                'contact_queries' => ['type' => ['array', 'null'], 'items' => ['type' => 'string', 'maxLength' => 120], 'maxItems' => 20],
                'start_date' => $text(10), 'end_date' => $text(10), 'monthly_rent' => $money,
                'deposit_amount' => $money, 'payment_day' => ['type' => ['integer', 'null'], 'minimum' => 1, 'maximum' => 28],
            ]),
        ];
    }

    public function execute(Portfolio $portfolio, User $user, AiRun $run, string $name, array $input, ?int $contextPropertyId): array
    {
        app(AiCapabilities::class)->assertCreations($portfolio, $user);
        $definition = collect($this->definitions())->firstWhere('name', $name);
        abort_unless($definition, 422);
        app(ProposalActionRegistry::class)->assertFields($input, array_keys($definition['parameters']['properties']));
        $required = match ($name) {
            'propose_property' => ['name' => 'el nombre', 'type' => 'el tipo de inmueble', 'address_line' => 'la dirección'],
            'propose_contact' => ['name' => 'el nombre del contacto'],
            'propose_lease' => ['property_query' => 'el nombre y ciudad del inmueble', 'contact_queries' => 'los nombres completos de los inquilinos',
                'start_date' => 'la fecha de inicio', 'monthly_rent' => 'la renta mensual exacta', 'payment_day' => 'el día de cobro (del 1 al 28)'],
        };
        $missing = [];
        foreach ($required as $field => $label) {
            if (! isset($input[$field]) || (is_string($input[$field]) && trim($input[$field]) === '') || $input[$field] === []) {
                $missing[] = $label;
            }
        }
        if ($missing !== []) {
            return ['clarification' => 'Para preparar esta propuesta necesito '.implode(', ', $missing).'. No he guardado ningún dato.'];
        }
        if ($name === 'propose_contact') {
            $input['kind'] ??= 'person';
        }
        if ($name === 'propose_lease') {
            Validator::make($input, ['property_query' => ['required', 'string', 'max:120'],
                'contact_queries' => ['required', 'array', 'min:1', 'max:20'], 'contact_queries.*' => ['required', 'string', 'max:120', 'distinct']])->validate();
            $found = app(ToolRegistry::class)->execute($portfolio, $user, $run, 'search_properties', ['query' => $input['property_query']], $contextPropertyId);
            if ($found['needs_clarification']) {
                return [...$found, 'clarification_domain' => 'property'];
            }
            $ids = [];
            foreach ($input['contact_queries'] as $query) {
                $people = app(ToolRegistry::class)->execute($portfolio, $user, $run, 'search_contacts', ['query' => $query, 'property_id' => null]);
                if ($people['needs_clarification']) {
                    $names = array_column($people['contacts'], 'name');

                    return ['clarification' => $names !== []
                        ? 'Hay varios contactos posibles: '.implode(', ', $names).'. Indícame el nombre completo; no he elegido ninguno.'
                        : 'No encuentro ese inquilino en Personas. Créalo primero y confirma su propuesta antes de preparar el contrato.'];
                }
                $ids[] = $people['contacts'][0]['id'];
            }
            unset($input['property_query'], $input['contact_queries']);
            $input = [...$input, 'property_id' => $found['properties'][0]['id'], 'contact_ids' => $ids, 'status' => 'draft'];
            $input['deposit_amount'] ??= '0.00';
        }
        $type = match ($name) {
            'propose_property' => 'property_create', 'propose_contact' => 'contact_create', 'propose_lease' => 'lease_create',
        };
        try {
            $proposal = app(ActionProposalService::class)->proposeCreation($type, $portfolio, $user, $run, $input);
        } catch (ValidationException $exception) {
            return ['clarification' => 'Revisa los datos de la propuesta: '.collect($exception->errors())->flatten()->first().' No he guardado ningún dato.'];
        }

        return ['proposal' => app(ActionProposalService::class)->present($proposal)];
    }

    private function definition(string $name, string $description, array $fields): array
    {
        return ['type' => 'function', 'name' => $name, 'description' => $description, 'strict' => true,
            'parameters' => ['type' => 'object', 'properties' => $fields, 'required' => array_keys($fields), 'additionalProperties' => false]];
    }
}
