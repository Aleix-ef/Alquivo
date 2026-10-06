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
        'fiscality' => 'Puedes preparar las fichas e informes del ejercicio cubierto en [Fiscalidad](/fiscality), cuando esté habilitada en tu plan. Completa el perfil, la titularidad y los periodos de uso y revisa los avisos de cobertura. La beta no presenta tu declaración ni calcula tu IRPF personal completo. Contrasta el informe con tu asesor.',
        'support' => 'En [Ayuda y soporte](/support) encontrarás guías y el canal de contacto disponible. Describe qué estabas haciendo y el mensaje de error, sin incluir contraseñas ni datos bancarios. Este chat no abre solicitudes de soporte.',
    ];

    private const CLARIFICATIONS = [
        'missing_period' => '¿De qué mensualidad se trata? Indícame el mes y el año del alquiler.',
        'missing_amount' => '¿Qué importe debo apuntar? Indícame la cantidad exacta.',
        'missing_phone' => '¿Cuál es el número de teléfono nuevo? Escríbelo para preparar el cambio.',
        'ambiguous_contact' => 'Hay más de un contacto posible. Indícame el nombre completo y, si ayuda, el inmueble asociado.',
        'ambiguous_property_or_lease' => 'Necesito saber a qué inmueble y contrato te refieres. Indica el nombre y la ciudad del inmueble o abre su ficha.',
        'missing_contract_end' => 'No consta una fecha de fin registrada para ese contrato. Puedes revisarlo en Alquileres.',
        'missing_valuation' => 'Ese inmueble no tiene una valoración actual registrada. Puedes revisarlo en Inmuebles.',
        'document_unavailable' => 'No puedo leer el contenido de ese documento desde este chat. Revísalo en Documentos.',
        'missing_contact' => 'No encuentro ese contacto en Personas. Indícame el nombre con el que lo guardaste.',
        'missing_operation' => '¿Quieres registrar un gasto o añadir una nota? Si es un gasto, dime el importe; si es una nota, dime qué quieres dejar apuntado.',
        'movement_details_unavailable' => 'Desde este chat puedo consultar fechas, importes y categorías de movimientos pagados, pero no sus conceptos o descripciones. No puedo identificar con seguridad ese gasto por el proveedor. Puedes revisar el detalle en [Finanzas](/finance).',
        'missing_property_reference' => '¿De qué inmueble hablamos? Indícame su nombre y, si hay varios con el mismo nombre, su ciudad. Si todavía no lo has añadido, sus datos no estarán disponibles en el chat.',
        'stored_contact_details_unavailable' => 'El chat puede consultar nombres y contratos asociados, pero no teléfonos ni correos guardados. Puedes ver esos datos en [Personas](/contacts). Para cambiar un teléfono, indícame el contacto y el número nuevo.',
        'future_income_unavailable' => 'No puedo saber cuánto cobrarás exactamente en el futuro. Puedo consultar lo cobrado, las rentas registradas y las mensualidades pendientes, pero no garantizar ingresos ni calcular previsiones desde este chat.',
    ];

    private const UNAVAILABLE = [
        'creation_unavailable' => 'Las altas de inmuebles, contactos y contratos con IA todavía están en revisión para esta cuenta. Puedes crearlos en [Inmuebles](/properties), [Personas](/contacts) o [Alquileres](/leases). No he guardado ningún dato.',
        'confirmation_required' => 'Para guardar una propuesta, revisa su tarjeta y pulsa el botón de confirmación. Un «sí» escrito aquí no la confirma. No he guardado ningún dato.',
    ];

    public const FALLBACKS = [
        'out_of_scope' => 'No estoy autorizado para responder sobre temas ajenos a Alquivo. Puedo ayudarte a entender los datos de tu patrimonio registrados en la aplicación.',
        'insufficient_data' => 'Ahora mismo no sabría responder con los datos disponibles en Alquivo. Comprueba que la información esté registrada o concreta el inmueble y el periodo que quieres consultar.',
        'support_required' => 'No puedo comprobar ni resolver este problema desde el chat. Si necesitas ayuda con tu cuenta, un cobro de Alquivo o un fallo de la aplicación, escribe a soporte de Alquivo. No envíes contraseñas ni datos completos de tarjetas. Yo no he abierto ninguna solicitud de soporte.',
        'read_only' => 'Esa operación no está disponible desde el asistente. Puedes revisarla en la sección correspondiente de Alquivo. No he modificado ningún dato ni realizado ningún pago.',
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

    public static function clarification(string $code): string
    {
        return self::CLARIFICATIONS[$code] ?? throw new \InvalidArgumentException('Código de aclaración no permitido.');
    }

    public static function parse(array $output, bool $hasEvidence, bool $canProposeExpenses = false, bool $canCreate = false): array
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
            $unavailable = trim($reply['content']);
            if ($reply['kind'] === 'read_only' && isset(self::UNAVAILABLE[$unavailable])
                && ($unavailable !== 'creation_unavailable' || ! $canCreate)) {
                return ['content' => self::UNAVAILABLE[$unavailable], 'metadata' => ['kind' => 'read_only']];
            }
            if ($reply['kind'] === 'insufficient_data' && isset(self::CLARIFICATIONS[trim($reply['content'])])) {
                $code = trim($reply['content']);
                if (in_array($code, ['missing_contract_end', 'missing_valuation', 'missing_contact'], true) && ! $hasEvidence) {
                    return self::fallback('insufficient_data');
                }

                return ['content' => self::clarification($code), 'metadata' => ['kind' => 'insufficient_data']];
            }

            return self::fallback($reply['kind']);
        }
        if ($reply['basis'] === 'none' || ($reply['basis'] === 'portfolio_data' && ! $hasEvidence)) {
            return self::fallback('insufficient_data');
        }
        if ($reply['basis'] === 'app_help') {
            $content = self::APP_HELP[trim($reply['content'])] ?? null;
            if (trim($reply['content']) === 'capabilities' && $canProposeExpenses) {
                $content = 'Puedo consultar tu patrimonio, contratos y mensualidades, localizar contactos y comparar ingresos y gastos. También puedo preparar gastos, cobros de alquiler, cambios de teléfono y notas de inmuebles. Sólo se guardarán cuando revises la propuesta y pulses su botón de confirmación. No ejecuto acciones por mi cuenta, no tengo Internet ni leo el contenido de archivos. Revisa siempre los datos importantes.';
                if ($canCreate) {
                    $content .= ' En esta vista previa puedo preparar inmuebles, contactos y contratos en borrador. No activo alquileres ni genero mensualidades al crear esos borradores.';
                }
            }

            return $content === null
                ? self::fallback('insufficient_data')
                : ['content' => $content, 'metadata' => ['kind' => 'answer']];
        }
        if (trim($reply['content']) === '') {
            throw new RuntimeException('Respuesta vacía.');
        }

        return ['content' => str_replace(['\\r\\n', '\\n'], "\n", trim($reply['content'])), 'metadata' => ['kind' => 'answer']];
    }

    private static function fallback(string $kind): array
    {
        return ['content' => self::FALLBACKS[$kind], 'metadata' => ['kind' => $kind]];
    }
}
