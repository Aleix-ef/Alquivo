// Reviewed product guidance. Available without activating AI or spending a query.
export const helpGuides = [
  {
    id: "start",
    title: "Empezar con mi primer alquiler",
    to: "/properties",
    action: "Ir a mis inmuebles",
    steps: [
      "Añade el inmueble con su nombre, dirección y tipo. Puedes completar su valoración y fotografías después.",
      "Añade los datos de tu inquilino en Personas.",
      "Crea el contrato en Alquileres: elige el inmueble y el inquilino e indica fechas y renta mensual.",
      "Cuando recibas el alquiler, registra el cobro desde Finanzas. Un cargo pendiente no es dinero cobrado.",
    ],
  },
  {
    id: "payments",
    title: "Registrar o corregir un cobro",
    to: "/finance",
    action: "Abrir Finanzas",
    steps: [
      "Localiza la mensualidad pendiente en Finanzas y pulsa Cobrar.",
      "Indica la cantidad recibida y la fecha. Si has recibido solo una parte, registra ese importe.",
      "Para corregir un cobro, busca su movimiento en la actividad y utiliza la opción de corrección. La mensualidad se recalcula.",
    ],
  },
  {
    id: "expenses",
    title: "Saber cuánto me dejan mis inmuebles",
    to: "/reports",
    action: "Ver mis informes",
    steps: [
      "Registra tus ingresos y gastos en Finanzas y asigna el inmueble correspondiente.",
      "Distingue movimientos confirmados de pendientes. Los informes de caja se basan en el dinero registrado como pagado.",
      "Consulta Informes para revisar el periodo que te interesa. El flujo de caja no es el resultado fiscal ni incluye la revalorización del inmueble.",
    ],
  },
  {
    id: "documents",
    title: "Guardar mis contratos y facturas",
    to: "/documents",
    action: "Abrir Documentos",
    steps: [
      "Abre Documentos y añade el archivo con un nombre fácil de reconocer.",
      "Asócialo al inmueble o contrato que corresponda y completa su vencimiento si lo tiene.",
      "Los archivos son privados. Puedes consultar sus referencias y vencimientos y descargarlos cuando los necesites.",
    ],
  },
  {
    id: "calendar",
    title: "Recordar una gestión pendiente",
    to: "/calendar",
    action: "Abrir Calendario",
    steps: [
      "Crea un recordatorio en Calendario con título, fecha y hora.",
      "Puedes vincularlo a un inmueble y añadir una descripción.",
      "Cuando termines, márcalo como completado. También puedes editarlo o eliminarlo.",
    ],
  },
  {
    id: "fiscality",
    title: "Preparar la información para mi asesor",
    to: "/fiscality",
    action: "Abrir Fiscalidad",
    steps: [
      "Fiscalidad permite preparar fichas e informes del ejercicio cubierto cuando esté habilitada en tu plan.",
      "Completa el perfil fiscal, la titularidad, los periodos de uso y los importes que solicita cada inmueble.",
      "Revisa los avisos de cobertura antes de descargar el informe. La beta no presenta tu declaración ni calcula tu IRPF personal completo.",
    ],
  },
  {
    id: "plans",
    title: "Cambiar mi tarjeta o cancelar",
    to: "/plans",
    action: "Ver mi suscripción",
    steps: [
      "Entra en Planes y abre la gestión de pago, facturas o cancelación si tienes una suscripción.",
      "Stripe muestra las condiciones y la fecha de efecto antes de confirmar la cancelación.",
      "Comprueba después el estado de tu plan. Si un pago necesita revisión, utiliza la gestión de pago antes de intentar otra contratación.",
    ],
  },
];

export function assistantContext(path) {
  const match = /^\/properties\/([1-9]\d*)$/.exec(path);
  if (match)
    return {
      label: "Este inmueble",
      propertyId: Number(match[1]),
      suggestions: [
        "Resume la situación de este inmueble.",
        "¿Qué ingresos y gastos tiene este inmueble este mes?",
        "Compara este mes con el anterior para este inmueble.",
      ],
    };
  if (path === "/finance" || path === "/reports")
    return {
      label: "Mis finanzas",
      suggestions: [
        "Compara mis ingresos y gastos de este mes con el anterior.",
        "¿Cuáles son mis gastos pagados de este mes?",
        "Compara el dinero que me dejan mis inmuebles este mes.",
      ],
    };
  if (["/leases", "/calendar", "/issues", "/documents"].includes(path))
    return {
      label: "Próximas gestiones",
      suggestions: [
        "¿Qué cobros tengo pendientes y cuántos están vencidos?",
        "¿Qué contratos vencen en los próximos 90 días?",
        "¿Qué incidencias y recordatorios tengo pendientes?",
      ],
    };
  return {
    label: "Mi patrimonio",
    suggestions: [
      "¿Cómo va mi patrimonio este mes?",
      "¿Qué cobros tengo pendientes?",
      "Compara mis ingresos y gastos con el mes anterior.",
      "¿Qué datos me faltan para valorar mis inmuebles?",
    ],
  };
}
