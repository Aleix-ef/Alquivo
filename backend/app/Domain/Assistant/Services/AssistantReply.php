<?php

namespace App\Domain\Assistant\Services;

use RuntimeException;

/** Validates provider output before any text is persisted or shown to the user. */
final class AssistantReply
{
    // A closed help catalog prevents the "app_help" route from emitting unverified free text.
    private const APP_HELP = [
        'greeting' => '¡Hola! Soy el asistente de Alquivo. Puedes preguntarme por tu patrimonio, los cobros pendientes o tus próximos vencimientos.',
        'capabilities' => 'Puedo resumir tu patrimonio, comparar meses e inmuebles, desglosar movimientos pagados y consultar cobros o vencimientos pendientes. También tienes guías paso a paso en Cómo usar Alquivo, sin gastar consultas. Soy de solo lectura: no cambio tus datos, no tengo Internet ni leo el contenido de tus archivos. Revisa siempre las cifras importantes.',
        'properties' => 'Puedes consultar las fichas de tus inmuebles en [Inmuebles](/properties).',
        'leases' => 'Puedes consultar tus contratos en [Alquileres](/leases).',
        'finance' => 'Puedes revisar los ingresos y gastos registrados en [Finanzas](/finance).',
        'documents' => 'Puedes consultar tus archivos en [Documentos](/documents). El asistente no puede leer su contenido.',
        'issues' => 'Puedes revisar los problemas registrados en [Incidencias](/issues).',
        'calendar' => 'Puedes consultar los eventos y vencimientos en [Calendario](/calendar).',
        'settings' => 'Puedes revisar tu perfil en [Configuración](/settings).',
        'plans' => 'Puedes revisar las opciones de suscripción en [Planes](/plans). El asistente no puede cambiar tu plan ni comprobar los cobros de Alquivo.',
        'getting_started' => "Para empezar con tu primer alquiler:\n1. Añade el inmueble en [Propiedades](/properties).\n2. Añade al inquilino en [Personas](/contacts).\n3. Crea el contrato en [Alquileres](/leases), con las fechas y la renta.\n4. Registra lo que hayas recibido en [Finanzas](/finance). Una mensualidad pendiente todavía no es dinero cobrado.",
        'record_payment' => "Para registrar un alquiler recibido:\n1. Localiza la mensualidad pendiente en [Finanzas](/finance) y pulsa Cobrar.\n2. Introduce la cantidad recibida y la fecha. Si solo has recibido una parte, registra ese importe.\n3. Revisa el movimiento y el importe que siga pendiente. Para corregirlo, utiliza la opción de corrección del movimiento.",
        'reports' => 'Registra los ingresos y gastos, asígnalos al inmueble correspondiente y revisa el periodo en [Informes](/reports). El flujo de caja se basa en los movimientos pagados registrados: no equivale a tu resultado fiscal ni incluye la revalorización del inmueble.',
        'fiscality' => 'Puedes preparar las fichas e informes del ejercicio cubierto en [Fiscalidad](/fiscality), con el Plan Fundador o durante la prueba. Completa el perfil, la titularidad y los periodos de uso y revisa los avisos de cobertura. La beta no presenta tu declaración ni calcula tu IRPF personal completo. Contrasta el informe con tu asesor.',
        'support' => 'En [Ayuda y soporte](/support) encontrarás guías y el canal de contacto disponible. Describe qué estabas haciendo y el mensaje de error, sin incluir contraseñas ni datos bancarios. Este chat no abre solicitudes de soporte.',
    ];

    public const FALLBACKS = [
        'out_of_scope' => 'No estoy autorizado para responder sobre temas ajenos a Alquivo. Puedo ayudarte a entender los datos de tu patrimonio registrados en la aplicación.',
        'insufficient_data' => 'Ahora mismo no sabría responder con los datos disponibles en Alquivo. Comprueba que la información esté registrada o concreta el inmueble y el periodo que quieres consultar.',
        'support_required' => 'No puedo comprobar ni resolver este problema desde el chat. Si necesitas ayuda con tu cuenta, un cobro de Alquivo o un fallo de la aplicación, escribe a soporte de Alquivo. No envíes contraseñas ni datos completos de tarjetas. Yo no he abierto ninguna solicitud de soporte.',
        'read_only' => 'Por ahora solo puedo consultar información de Alquivo: no puedo crear, modificar, borrar ni realizar pagos. Debes hacer ese cambio desde la aplicación.',
    ];

    public static function format(): array
    {
        return [
            'type' => 'json_schema', 'name' => 'alquivo_assistant_reply', 'strict' => true,
            'schema' => [
                'type' => 'object', 'additionalProperties' => false,
                'properties' => [
                    'kind' => ['type' => 'string', 'enum' => ['answer', ...array_keys(self::FALLBACKS)]],
                    'basis' => ['type' => 'string', 'enum' => ['portfolio_data', 'app_help', 'none']],
                    'content' => ['type' => 'string'],
                ],
                'required' => ['kind', 'basis', 'content'],
            ],
        ];
    }

    public static function parse(array $output, bool $hasEvidence): array
    {
        $parts = collect($output)->where('type', 'message')->flatMap(fn (array $item) => $item['content'] ?? []);
        if ($parts->contains('type', 'refusal')) {
            return self::fallback('out_of_scope');
        }
        $text = $parts->where('type', 'output_text')->pluck('text')->join('');
        $reply = json_decode($text, true);
        if (! is_array($reply)
            || array_diff(array_keys($reply), ['kind', 'basis', 'content'])
            || ! in_array($reply['kind'] ?? null, ['answer', ...array_keys(self::FALLBACKS)], true)
            || ! in_array($reply['basis'] ?? null, ['portfolio_data', 'app_help', 'none'], true)
            || ! is_string($reply['content'] ?? null)
            || mb_strlen($reply['content']) > 12000) {
            throw new RuntimeException('Formato de respuesta no válido.');
        }
        if ($reply['kind'] !== 'answer') {
            return self::fallback($reply['kind']);
        }
        if ($reply['basis'] === 'none' || ($reply['basis'] === 'portfolio_data' && ! $hasEvidence)) {
            return self::fallback('insufficient_data');
        }
        if ($reply['basis'] === 'app_help') {
            $content = self::APP_HELP[trim($reply['content'])] ?? null;

            return $content === null
                ? self::fallback('insufficient_data')
                : ['content' => $content, 'metadata' => ['kind' => 'answer']];
        }
        if (trim($reply['content']) === '') {
            throw new RuntimeException('Respuesta vacía.');
        }

        return ['content' => trim($reply['content']), 'metadata' => ['kind' => 'answer']];
    }

    private static function fallback(string $kind): array
    {
        return ['content' => self::FALLBACKS[$kind], 'metadata' => ['kind' => $kind]];
    }
}
