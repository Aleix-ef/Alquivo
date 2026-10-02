import { isTurnstileTestKey } from "./contract.js";

// Only the public site key is read. Never expose the Turnstile SECRET key here.
export function waitlistSettings(env = {}, { preview = false } = {}) {
  const siteKey = (env.LANDING_TURNSTILE_SITE_KEY || "").trim();
  if (siteKey && !/^[a-zA-Z0-9_-]{20,100}$/.test(siteKey))
    throw new Error("LANDING_TURNSTILE_SITE_KEY no tiene un formato válido.");
  if (!preview && (!siteKey || isTurnstileTestKey(siteKey)))
    throw new Error(
      "Falta una LANDING_TURNSTILE_SITE_KEY real. Las claves de prueba no se permiten al publicar. Para revisar el diseño usa build:landing:preview.",
    );
  const site = new URL(env.LANDING_SITE_URL || "https://alquivo.com");
  if (
    site.protocol !== "https:" ||
    site.username ||
    site.password ||
    site.port ||
    site.pathname !== "/" ||
    site.search ||
    site.hash ||
    !site.hostname.includes(".") ||
    /(^localhost$|\.(test|local|example|invalid)$|^[\d.]+$|:)/.test(
      site.hostname,
    )
  )
    throw new Error(
      "LANDING_SITE_URL debe ser un dominio público HTTPS, sin rutas ni parámetros.",
    );
  return {
    siteKey: preview ? "" : siteKey,
    origin: site.origin,
    preview,
    indexable: !preview && env.LANDING_INDEXABLE !== "false",
  };
}

export const waitlistCopy = {
  common: { startFree: "Solicita acceso" },
  marketing: {
    plans: "Beta gratuita",
    freeBeta: "Próxima beta gratuita",
    assistantSoon: "Con Alquivo AI",
    heroFirst: "Tus alquileres, en orden.",
    heroSecond: "Tus dudas, con respuesta.",
    heroWithoutAI:
      "Inmuebles, cobros, gastos y contratos en un mismo lugar. Pregunta a Alquivo AI por tus datos y entiende cómo va tu patrimonio. Apúntate para probarlo en la próxima beta gratuita.",
    tryBeta: "Solicita acceso a la beta",
    startBeta: "Solicita acceso a la beta",
    startFree: "Solicita acceso a la beta",
    inPreparation: "En la próxima beta",
    assistantDetailOff:
      "Pregunta cuánto has cobrado, qué alquileres tienes pendientes o cuándo termina un contrato. Alquivo AI consulta los datos que hayas registrado y te ayuda a preparar cambios que tú revisas y confirmas.",
    noInventing: "Si falta información, te la pedirá",
    exampleAnswerTitle: "Por ejemplo, con estos datos ficticios",
    exampleAnswer:
      "Tienes 550 € pendientes del alquiler de septiembre de San Nicolás. El contrato de Marina termina el 31 de octubre. Puedes revisar ambos registros antes de decidir qué hacer.",
    topics: "Ejemplos de consultas",
    noCardToStart: "Beta gratuita, sin tarjeta",
    betaFree: "Pruébalo gratis durante la beta.",
    betaDetails:
      "Hasta 50 inmuebles, 5 GB de documentos y fotos y las funciones disponibles al abrir la beta. La IA tendrá límites de uso. Deja tu email y te avisaremos cuando puedas entrar.",
    betaAccess: "Próxima apertura",
    faqCard: "¿Apuntarme tiene algún coste?",
    faqCardBeta:
      "No. Solicitar acceso es gratis y no necesitas tarjeta. La beta también será gratuita. No te estás suscribiendo a un plan de pago.",
    faqAI: "¿Cuándo podré entrar?",
    faqAINo:
      "Te escribiremos cuando podamos darte acceso a la beta. Aún no anunciamos una fecha: estamos revisando el producto y sus respuestas de IA. Apuntarte no crea una cuenta ni da acceso inmediato.",
    faqAfterBetaAnswer:
      "Te avisaremos antes de terminar la beta y te explicaremos las opciones para continuar. Tú decidirás; no habrá ningún cobro automático y podrás exportar tus datos.",
    faqTypesAnswer:
      "Podrás registrar viviendas, locales, oficinas, garajes, trasteros, terrenos y edificios. La gestión detallada por habitaciones o unidades de un edificio llegará más adelante.",
    footer: "Gestión de alquileres con IA · Próxima beta gratuita",
  },
};

export const waitlistPages = [
  {
    path: "/",
    title: "Alquivo | Tus alquileres en orden, con ayuda de IA",
    description:
      "Organiza inmuebles, cobros, gastos y contratos. Pregunta a Alquivo AI por tus datos. Solicita acceso a la próxima beta gratuita, sin tarjeta.",
  },
  {
    path: "/privacidad",
    title: "Privacidad de la lista de espera | Alquivo",
    description:
      "Cómo tratamos tus datos cuando solicitas acceso a la beta de Alquivo o contactas con nosotros.",
  },
  {
    path: "/aviso-legal",
    title: "Aviso legal | Alquivo",
    description:
      "Identificación del titular y condiciones de la web informativa de Alquivo.",
  },
  {
    path: "/404",
    title: "Página no encontrada | Alquivo",
    description:
      "Vuelve a la página de Alquivo para conocer la próxima beta gratuita.",
  },
];
