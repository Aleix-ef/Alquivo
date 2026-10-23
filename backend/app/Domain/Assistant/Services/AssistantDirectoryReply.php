<?php

namespace App\Domain\Assistant\Services;

use Illuminate\Support\Str;

/** Presentation of authorized directory facts. Never resolves a write target. */
final class AssistantDirectoryReply
{
    public const PAGE_SIZE = 20;

    public static function parameters(): array
    {
        return [
            'response_mode' => ['type' => 'string', 'enum' => ['data', 'count', 'list'],
                'description' => 'count/list terminan el turno con un recuento/listado verificado por Alquivo. Úsalos SOLO si eso responde toda la petición. data si necesitas analizar, combinar consultas o preparar una propuesta.'],
            'page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10000,
                'description' => 'Página de 20 registros, empieza por 1. El total cuenta todos los registros filtrados.'],
        ];
    }

    public static function rules(): array
    {
        return ['response_mode' => ['sometimes', 'in:data,count,list'], 'page' => ['sometimes', 'integer', 'between:1,10000']];
    }

    public static function pagination(?int $count, int $page, int $shown, bool $scanTruncated = false): array
    {
        $offset = ($page - 1) * self::PAGE_SIZE;

        return ['count' => $count, 'page' => $page, 'page_size' => self::PAGE_SIZE,
            'truncated' => $scanTruncated || $count > $shown,
            'next_page' => $count !== null && $count > $offset + $shown && $page < 10000 ? $page + 1 : null];
    }

    public function render(string $tool, array $arguments, array $result, ?string $request = null): ?array
    {
        $mode = $arguments['response_mode'] ?? 'data';
        if (! in_array($tool, ['list_properties', 'list_contacts'], true)
            || ! in_array($mode, ['count', 'list'], true) || isset($result['error']) || isset($result['clarification_domain'])) {
            return null;
        }
        $properties = $tool === 'list_properties';
        // response_mode is model output, not evidence that every requested filter
        // was applied. Only short-circuit unqualified, whole directory requests.
        // City/mixed/unknown requests keep the normal model round with explicit scope.
        if ($properties && ! $this->plainPropertyRequest($request, $arguments)) {
            return null;
        }
        $rows = $result[$properties ? 'properties' : 'contacts'];
        $count = $result['count'];
        $noun = $properties ? $this->propertyNoun($arguments['type'] ?? null) : 'contactos';
        $scope = ! $properties && ! empty($result['property'])
            ? ' asociados al inmueble '.self::label($result['property']['name']) : '';
        if (! $properties && ! empty($arguments['query'])) {
            $scope .= ' que coinciden con '.self::label($arguments['query']);
        }
        $content = $count === null
            ? 'La búsqueda de '.$noun.$scope.' es parcial; no puedo dar un total completo.'
            : 'Total de '.$noun.$scope.': '.$count.'.';
        if ($properties && ($arguments['type'] ?? null) === 'housing') {
            $content .= ' Se cuentan las fichas de tipo vivienda; no garajes, locales ni edificios completos.';
        }
        if ($count === 0) {
            $content .= ' No hay registros'.($scope === '' ? '' : ' con estos filtros').'.';
        } elseif ($mode === 'list' || ($count !== null && $count <= 5 && $result['page'] === 1)) {
            if ($rows === []) {
                $content .= $count === null
                    ? ' No aparecen coincidencias en la muestra consultada; no puedo descartar que existan otras. Acota la búsqueda por nombre e inmueble.'
                    : ' Esta página está vacía; el total no es cero. Puedes volver a la primera página.';
            } else {
                $content .= "\n\n".implode("\n", array_map(function (array $row) use ($properties) {
                    $label = self::label($row['name']);
                    if ($properties) {
                        $label .= filled($row['city']) ? ' · '.self::label($row['city']) : '';
                    } elseif ($row['properties'] !== []) {
                        $label .= ' · inmuebles asociados: '.implode(', ', array_map(fn ($p) => self::label($p['name'])
                            .(filled($p['city'] ?? null) ? ' ('.self::label($p['city']).')' : ''), array_slice($row['properties'], 0, 2)));
                        if (($row['properties_truncated'] ?? false) || count($row['properties']) > 2) {
                            $label .= ' (muestra)';
                        }
                    }

                    return '- '.$label;
                }, $rows));
                if ($result['truncated']) {
                    $content .= "\n\nPágina ".$result['page'].': '.count($rows).' registros mostrados; no es la lista completa.';
                    if ($result['next_page'] !== null) {
                        $content .= ' Puedes pedirme la página '.$result['next_page'].'.';
                    } elseif ($count === null) {
                        $content .= ' Concreta el nombre para acotar la búsqueda.';
                    }
                }
            }
        }
        $path = $properties ? '/properties' : '/contacts';
        $label = $properties ? 'Inmuebles' : 'Personas';
        $content .= "\n\nPuedes revisarlo en: [".$label.']('.$path.').';

        return ['content' => $content, 'metadata' => ['kind' => 'answer', 'presentation' => 'directory',
            'directory_reference' => ['tool' => $tool, 'page' => $result['page'],
                ...($properties ? ['type' => $arguments['type'] ?? null, 'city' => $result['scope']['city'] ?? null] : ['query' => $arguments['query'] ?? null, 'property_id' => $arguments['property_id'] ?? null])],
            'sources' => [['label' => $label, 'path' => $path]], 'checked_at' => now()->toIso8601String()]];
    }

    private function plainPropertyRequest(?string $request, array $arguments): bool
    {
        if ($request === null || filled($arguments['city'] ?? null) || ($arguments['page'] ?? 1) !== 1) {
            return false;
        }
        $text = trim(Str::lower(Str::ascii(Str::squish($request))), " ¿?¡!.\t\n\r");
        $noun = '(pisos|casas|viviendas|inmuebles|propiedades|garajes|locales|oficinas|trasteros|terrenos|edificios)';
        $patterns = [
            '/^(?:y )?(?:cuantos|cuantas|que|cuales)(?: son)?(?: todos)?(?: mis| los| las)? '.$noun.'(?: tengo)?(?: en total)?$/',
            '/^(?:ensename|muestrame|lista) (?:todos |todas )?(?:mis|los|las) '.$noun.'$/',
            '/^mis '.$noun.'$/',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $expectedType = match ($match[1]) {
                    'pisos', 'casas', 'viviendas' => 'housing', 'garajes' => 'garage', 'locales' => 'commercial',
                    'oficinas' => 'office', 'trasteros' => 'storage', 'terrenos' => 'land', 'edificios' => 'building',
                    default => null,
                };

                return ($arguments['type'] ?? null) === $expectedType;
            }
        }

        return false;
    }

    /** Quote data, flatten controls and neutralize the frontend's Markdown link syntax. */
    private static function label(string $value): string
    {
        $value = preg_replace('/[\p{C}\s]+/u', ' ', $value);

        $value = trim($value);
        $label = mb_strlen($value) > 80 ? mb_substr($value, 0, 79).'…' : $value;

        return '«'.strtr($label, ['[' => '［', ']' => '］', '«' => '‹', '»' => '›']).'»';
    }

    private function propertyNoun(?string $type): string
    {
        return match ($type) {
            'housing' => 'viviendas', 'commercial' => 'locales', 'office' => 'oficinas', 'garage' => 'garajes',
            'storage' => 'trasteros', 'land' => 'terrenos', 'building' => 'edificios', 'other' => 'otros inmuebles',
            default => 'inmuebles',
        };
    }
}
