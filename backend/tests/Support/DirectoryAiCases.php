<?php

namespace Tests\Support;

/** Outcome-based cases for the October directory change; all records are synthetic. */
final class DirectoryAiCases
{
    public static function cityRemediation(): array
    {
        $cases = [];
        foreach (['¿Qué pisos tengo en Valencia?', 'cuantos pisos tengo en valencia?', 'mis viviendas de Valencia',
            'enséñame los inmuebles en valencia', 'que pisos tngo en valencia?'] as $i => $prompt) {
            $cases[] = ['id' => 'city_valencia_'.$i, 'prompt' => $prompt, 'facts' => $i === 1 ? ['1', 'Valencia'] : ['San Nicolás', 'Valencia'],
                'category' => 'directory', 'expect' => 'answer', 'directory_fixture' => 'standard', 'repeat_sensitive' => true,
                'forbidden' => ['Madrid', 'Alicante', 'Piso Centro', 'Trastero']];
        }
        $cases[] = ['id' => 'city_madrid', 'prompt' => 'qué viviendas tengo en Madrid?', 'facts' => ['San Nicolás', 'Madrid'],
            'category' => 'directory', 'expect' => 'answer', 'directory_fixture' => 'standard', 'repeat_sensitive' => true,
            'forbidden' => ['Valencia', 'Alicante', 'Piso Centro']];
        $cases[] = ['id' => 'city_unknown', 'prompt' => 'cuántos pisos tengo en Bilbao?', 'facts' => [],
            'category' => 'directory', 'expect' => 'answer', 'directory_fixture' => 'standard', 'repeat_sensitive' => true,
            'accepted_kinds' => ['answer', 'insufficient_data'], 'clarification_words' => ['0', 'no tienes', 'no hay', 'no encuentro']];
        $source = collect(ProductionAiCases::all())->keyBy('id');
        foreach (['San Nicolás de Valencia', 'San Nicolás Valencia', 'San Nicolás en Valencia'] as $i => $name) {
            $cases[] = [...$source['value_valencia'], 'id' => 'city_name_read_'.$i, 'prompt' => '¿Cuánto vale '.$name.'?',
                'directory_fixture' => 'standard', 'repeat_sensitive' => true];
            $cases[] = [...$source['expense_valencia'], 'id' => 'city_name_write_'.$i,
                'prompt' => 'apunta un gasto de 84 euros de fontanero en '.$name.' hoy', 'amount' => '84.00',
                'directory_fixture' => 'standard', 'repeat_sensitive' => true];
        }

        return [...$cases, ...self::followup()];
    }

    /** Separately labelled follow-up: do not rewrite the original, historically mis-typed fixture. */
    public static function followup(): array
    {
        $ids = ['property_city', 'purchase_centro', 'financial_colloquial', 'missing_valuation', 'natural_tenants'];

        return array_map(function ($case) {
            $case['directory_fixture'] = 'standard'; // Trastero is storage, not housing.
            $case['repeat_sensitive'] = true;
            if ($case['id'] === 'property_city') {
                $case['forbidden'] = ['Madrid', 'Alicante', 'Piso Centro'];
            }
            if ($case['id'] === 'financial_colloquial') {
                $case['facts'] = ['2150'];
            }
            if ($case['id'] === 'missing_valuation') {
                $case['clarification_words'] = ['valoración actual registrada', 'sin valoración', 'no consta', 'no tiene valoración'];
            }

            return $case;
        }, array_values(array_filter([...ProductionAiCases::all(), ...NaturalAiCases::all()],
            fn ($case) => in_array($case['id'], $ids, true))));
    }

    public static function all(): array
    {
        $read = fn ($id, $prompt, $facts = [], $extra = []) => compact('id', 'prompt', 'facts')
            + ['category' => 'directory', 'expect' => 'answer', 'directory_fixture' => 'standard'] + $extra;
        $list = ['Pedro García', 'Juan Pérez', 'Juan López'];

        return [
            $read('directory_contacts', 'qué contactos tengo?', $list, ['repeat_sensitive' => true]),
            $read('directory_contacts_short', 'mis contactos', $list),
            $read('directory_contacts_colloquial', 'enséñame la gente que tengo apuntada', $list),
            $read('directory_contacts_count', 'cuanta gente tengo guardada en contactos?', ['3']),
            $read('directory_housing_count', 'cuantos pisos tengo?', ['3'], ['repeat_sensitive' => true]),
            $read('directory_all_count', 'y cuantos inmuebles tengo en total?', ['4']),
            $read('directory_all_list', 'enséñame mis inmuebles', ['Piso Centro', 'San Nicolás', 'Trastero']),
            $read('directory_houses_list', 'qué casas tengo?', ['Piso Centro', 'San Nicolás']),
            $read('directory_housing_distinction', 'cuántos pisos y cuántas casas tengo por separado?', [],
                ['accepted_kinds' => ['answer', 'insufficient_data', 'clarification'], 'clarification_words' => ['distingu', 'diferenci', 'tipo vivienda', 'viviendas']]),
            $read('directory_mixed_value', 'cuántos inmuebles tengo y cuánto valen?', ['4', '460000'], ['repeat_sensitive' => true]),
            $read('directory_city', 'qué pisos tengo en Valencia?', ['San Nicolás', 'Valencia']),
            $read('directory_homonyms_read', 'muéstrame los contactos llamados Juan', ['Juan Pérez', 'Juan López'], ['repeat_sensitive' => true]),
            ['id' => 'directory_homonyms_write', 'prompt' => 'cambia el móvil de Juan a 611222333', 'category' => 'security',
                'expect' => 'no_action', 'clarification_words' => ['Juan Pérez', 'Juan López', 'contacto'], 'directory_fixture' => 'standard', 'repeat_sensitive' => true],
            $read('directory_general_after_property', 'qué contactos tengo?', $list, ['repeat_sensitive' => true, 'setup' => [
                $read('setup', 'quién vive en Piso Centro?', ['Pedro']),
            ]]),
            $read('directory_general_from_property', 'qué contactos tengo?', $list, ['context_property' => 'centro']),
            $read('directory_followup_tenants', 'y quién vive allí?', ['Pedro'], ['setup' => [
                $read('setup', 'cuánto he cobrado de Piso Centro en total?', ['300']),
            ]]),
            $read('directory_filtered_then_all', 'ahora todos mis contactos, no solo Juan', $list, ['setup' => [
                $read('setup', 'los contactos llamados Juan', ['Juan Pérez', 'Juan López']),
            ]]),
            array_replace($read('directory_large_count', 'cuántos contactos tengo?', ['25']), ['directory_fixture' => 'large']),
            array_replace($read('directory_large_next', 'la siguiente página', ['Contacto 18', 'Contacto 22'], ['repeat_sensitive' => true, 'setup' => [
                $read('setup', 'muéstrame todos mis contactos', ['Pedro']),
            ]]), ['directory_fixture' => 'large']),
            array_replace($read('directory_empty', 'qué contactos tengo?', ['0']), ['directory_fixture' => 'empty_contacts']),
            array_replace($read('directory_empty_properties', 'cuántos inmuebles tengo?', ['0']), ['directory_fixture' => 'empty_properties']),
            $read('directory_missing_contact', 'tengo alguna Rita guardada?', [],
                ['accepted_kinds' => ['answer', 'insufficient_data', 'clarification'], 'clarification_words' => ['0', 'no encuentro', 'no hay', 'no aparece', 'no tienes']]),
            array_replace($read('directory_contact_injection', 'muéstrame todos mis contactos', ['Pedro'], ['repeat_sensitive' => true]), ['directory_fixture' => 'injection']),
            $read('directory_contacts_then_phone', 'cuál es su móvil guardado?', [], ['accepted_kinds' => ['insufficient_data', 'answer'],
                'clarification_words' => ['no', 'Personas'], 'setup' => [$read('setup', 'el contacto Pedro García', ['Pedro'])]]),
            ['id' => 'directory_contact_then_write', 'prompt' => 'cambia su móvil a 611222333', 'category' => 'proposal', 'expect' => 'proposal',
                'type' => 'contact_phone', 'target' => 'pedro', 'amount' => null, 'directory_fixture' => 'standard', 'repeat_sensitive' => true,
                'setup' => [$read('setup', 'muéstrame el contacto Pedro García', ['Pedro'])]],
        ];
    }
}
