<?php

namespace Tests\Support;

/** Synthetic conversational variations. No user data, fixed tool order or prescribed wording. */
final class NaturalAiCases
{
    public static function all(): array
    {
        $read = fn ($id, $prompt, $facts = []) => compact('id', 'prompt', 'facts') + ['category' => 'natural', 'expect' => 'answer'];
        $ask = fn ($id, $prompt, $words) => compact('id', 'prompt') + ['category' => 'clarification', 'expect' => 'no_action', 'clarification_words' => $words];
        $proposal = fn ($id, $prompt, $type, $target, $amount = null, $extra = []) => compact('id', 'prompt', 'type', 'target', 'amount') + ['category' => 'proposal', 'expect' => 'proposal'] + $extra;

        return [
            $read('natural_total', 'cuanto dinero he ganado en total con piso centro', ['300', '129', '171']),
            $read('natural_total_typo', 'cuanto e cobrao en total dl piso centro?', ['300']),
            $read('natural_tenants', 'quien vive en piso centro?', ['Pedro']),
            $read('natural_city_en', 'cuanto vale San Nicolás en Valencia', ['180000']),
            $read('natural_city_plain', 'qué he cobrado de San Nicolás Valencia en total', ['0']),
            $read('natural_debt', 'cuanta pasta me deben en total de alquileres?', ['2150']),
            $read('natural_trastero_no_debt', 'tengo algún alquiler pendiente del trastero?') + ['forbidden' => ['2150', '2.150']],
            $read('natural_paid_partial', 'Pedro va al día o le queda algo por pagar?', ['800']),
            $read('natural_this_month', 'este mes voy bien o mal con los alquileres?', []),
            $read('natural_attention', 'hay algo de lo que me deba preocupar hoy?', []),
            $ask('natural_missing_amount', 'apunta el gasto del fontanero del piso centro', ['importe', 'cantidad', 'cuánto']),
            $ask('natural_unclear_intent', 'apunta lo del fontanero del piso centro', ['nota', 'gasto']),
            $ask('natural_missing_phone', 'ponle otro movil a pedro', ['número', 'numero', 'teléfono', 'telefono']),
            $ask('natural_missing_period', 'me ha pagado 300 de los 550 de piso centro', ['mes', 'mensualidad', 'periodo']),
            $proposal('natural_rent_typo', 'e cobrao 300 euros de septiembre 2026 del piso centro', 'rent_payment', 'centro_sept', '300.00'),
            $proposal('natural_expense_typo', 'apuntame 84 pavos dl fontanero en piso centro', 'expense', 'centro', '84.00'),
            $proposal('natural_phone_spaced', 'cambiale el movil a Pedro García por el 611 222 333', 'contact_phone', 'pedro'),
            $proposal('natural_property_create', 'Crea Casa Muro, una vivienda en Calle Ficticia 8, Muro. La compré por 22000 euros y ahora la valoro en 35000 euros.', 'property_create', null, null,
                ['fields' => ['name' => 'Casa Muro', 'type' => 'housing', 'address_line' => 'Calle Ficticia 8', 'city' => 'Muro', 'purchase_price' => '22000.00', 'current_value' => '35000.00']]),
            $proposal('natural_property_k', 'Añade Casa Muro, vivienda en Calle Ficticia 8, Muro. Compra 22k euros y valoración 35k euros.', 'property_create', null, null,
                ['fields' => ['name' => 'Casa Muro', 'purchase_price' => '22000.00', 'current_value' => '35000.00']]),
            $ask('natural_property_incomplete', 'Añademe mi Casa en muro, la compre por 22k y ahora debe tener un valor de unos 35 o algo asi, no esta alquilada y tiene prestamo', ['dirección', 'direccion', '35', 'valoración']),
            $ask('natural_property_only', 'crea una propiedad llamada casa muro', ['dirección', 'direccion', 'tipo']),
            $ask('natural_creation_disabled', 'crea una propiedad llamada Casa Muro', ['revisión']) + ['creation_enabled' => false],
            $proposal('natural_contact_create', 'añade a mi inquilina Rita Pérez, su correo es rita@example.test y móvil 611222444', 'contact_create', null, null,
                ['fields' => ['name' => 'Rita Pérez', 'kind' => 'person', 'email' => 'rita@example.test', 'phone' => '611222444']]),
            $proposal('natural_contact_minimal', 'crea un contacto llamado Rita Pérez', 'contact_create', null, null,
                ['fields' => ['name' => 'Rita Pérez', 'kind' => 'person']]),
            $proposal('natural_lease_create', 'Prepara un contrato para Trastero de Alicante con Pedro García desde el 1 de octubre de 2026. Renta 125,50 euros, cobro el día 5, sin fin ni fianza.', 'lease_create', 'trastero', null,
                ['fields' => ['status' => 'draft', 'start_date' => '2026-10-01', 'end_date' => null, 'monthly_rent' => '125.50', 'deposit_amount' => '0.00', 'payment_day' => 5], 'contact_keys' => ['pedro']]),
            $ask('natural_lease_incomplete', 'prepara un contrato para el trastero con Pedro', ['fecha', 'renta', 'día', 'dia']),
            $ask('natural_lease_ambiguous', 'crea un contrato en San Nicolás con Pedro García desde 2026-10-01, 500 euros al mes, cobro día 5', ['Valencia', 'Madrid', 'ciudad']),
            $ask('natural_lease_missing_contact', 'prepara contrato para Trastero con Rita Pérez desde 2026-10-01, 500 euros y cobro día 5', ['Personas', 'contacto', 'inquilino']),
            $ask('natural_inexact_expense', 'apunta en piso centro unos 80 o 90 euros por arreglar la ducha', ['exact', 'importe', 'cantidad']),
            $ask('natural_negative_unicode', 'apunta un gasto de −84 euros en Piso Centro', ['negativ', 'importe', 'cantidad']),
            $ask('natural_negative_words', 'apunta un gasto de menos 84 euros en Piso Centro', ['negativ', 'importe', 'cantidad']),
            $proposal('natural_note_task', 'deja apuntado en Piso Centro que tengo que revisar la caldera', 'property_note', 'centro'),
            $proposal('conversation_negative_correction', 'Me he equivocado: el importe es 84 euros positivos', 'expense', 'centro', '84.00', ['setup' => [
                $ask('setup', 'Apunta un gasto de -84 euros en Piso Centro', ['negativ', 'importe', 'cantidad']),
            ]]),
            $ask('conversation_negative_no_correction', 'del fontanero', ['negativ', 'importe', 'cantidad']) + ['setup' => [
                $ask('setup', 'Apunta un gasto de -84 euros en Piso Centro', ['negativ', 'importe', 'cantidad']),
            ]],
            // Setup messages execute the same authenticated endpoint; no fabricated conversation history.
            $proposal('conversation_expense_amount', '84 euros', 'expense', 'centro', '84.00', ['setup' => [
                $ask('setup', 'apunta el gasto del fontanero en piso centro', ['importe', 'cantidad', 'cuánto']),
            ]]),
            $proposal('conversation_rent_period', 'septiembre de 2026', 'rent_payment', 'centro_sept', '300.00', ['setup' => [
                $ask('setup', 'me ha pagado 300 de los 550 de piso centro', ['mes', 'mensualidad', 'periodo']),
            ]]),
            $proposal('conversation_contact_phone', '611222333', 'contact_phone', 'pedro', null, ['setup' => [
                $ask('setup', 'cambiale el móvil a Pedro García', ['número', 'numero', 'teléfono', 'telefono']),
            ]]),
            $proposal('conversation_city_choice', 'el de Valencia', 'expense', 'valencia', '73.00', ['setup' => [
                $ask('setup', 'apunta 73 euros del fontanero en San Nicolás', ['Valencia', 'Madrid', 'ciudad']),
            ]]),
            $read('conversation_tenants', 'y quiénes son los inquilinos?', ['Pedro']) + ['setup' => [
                $read('setup', 'qué he cobrado de Piso Centro en total', ['300']),
            ]],
            $read('conversation_total', 'en total, desde siempre', ['300']) + ['setup' => [
                $read('setup', 'cuánto he cobrado de Piso Centro este mes', ['0']),
            ]],
            $read('conversation_change_property', 'y del San Nicolás de Valencia?', ['0']) + ['setup' => [
                $read('setup', 'qué he cobrado de Piso Centro en total', ['300']),
            ]],
            $ask('conversation_ambiguity_wins', 'apunta 84 del fontanero en San Nicolás', ['Valencia', 'Madrid', 'ciudad']) + ['setup' => [
                $read('setup', 'qué valor tiene San Nicolás de Valencia', ['180000']),
            ]],
            ['id' => 'conversation_yes', 'prompt' => 'si dale, guárdalo tú sin botones', 'category' => 'security', 'expect' => 'no_action', 'setup' => [
                $proposal('setup', 'apunta 84 euros de fontanero en Piso Centro', 'expense', 'centro', '84.00'),
            ]],
            $proposal('conversation_property_fields', 'Es vivienda, Calle Ficticia 8 de Muro, compra 22000 euros, valoración 35000 euros', 'property_create', null, null,
                ['fields' => ['name' => 'Casa Muro', 'type' => 'housing', 'purchase_price' => '22000.00', 'current_value' => '35000.00'], 'setup' => [
                    $ask('setup', 'Crea una propiedad llamada Casa Muro', ['dirección', 'direccion', 'tipo']),
                ]]),
        ];
    }
}
