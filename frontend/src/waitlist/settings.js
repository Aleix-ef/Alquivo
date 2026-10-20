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
  common: { startFree: "Apuntarme a la beta" },
  marketing: {
    assistant: "Ver un ejemplo",
    features: "Qué puedes hacer",
    plans: "Cómo es la beta",
    freeBeta: "Beta gratuita para propietarios",
    assistantSoon: "Con Alquivo AI",
    heroFirst: "Tus alquileres, en orden.",
    heroSecond: "Tus números, claros.",
    heroWithoutAI:
      "Ten claro qué has cobrado, qué te deben y cuánto gastas. Tus inmuebles, contratos y documentos, juntos y con ayuda de IA.",
    tryBeta: "Apuntarme a la beta",
    startBeta: "Apuntarme a la beta",
    startFree: "Apuntarme a la beta",
    learnAssistant: "Ver cómo te ayuda",
    simpleQuestion: "Pregunta por tus alquileres.",
    contextualAnswer: "Sin buscar entre carpetas.",
    inPreparation: "En la próxima beta",
    assistantDetailOff:
      "«¿Qué alquileres tengo pendientes?» Alquivo AI consulta los datos que hayas registrado para ayudarte a encontrar la información. También prepara cambios que tú revisas y confirmas.",
    basedOnRecords: "Consulta la información de tus inmuebles",
    clearAmounts: "Revisa los importes y las fechas",
    noInventing: "Tú confirmas antes de cambiar un dato",
    fullView: "Menos información dispersa",
    madeForInvestors: "Lo que necesitas, en un solo lugar.",
    paymentsControl: "Cobros y gastos, claros.",
    paymentsControlBody:
      "Consulta qué has cobrado, qué falta por pagar y cuánto has gastado.",
    nothingMissed: "Contratos bajo control.",
    nothingMissedBody:
      "Ten a mano tus alquileres y las fechas que necesitas revisar.",
    documentsPlace: "Tus documentos, a mano.",
    documentsPlaceBody:
      "Encuentra contratos, facturas y fotos asociados a cada inmueble.",
    exampleQuestion: "¿Qué alquileres tengo pendientes?",
    exampleAnswerTitle: "En este ejemplo",
    exampleAnswer:
      "En San Nicolás quedan 550 € pendientes del alquiler de septiembre. Puedes revisar la mensualidad en el registro de cobros.",
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
    faqEyebrow: "Antes de apuntarte",
    faqTitle: "Sin letra pequeña.",
    faqTypesAnswer:
      "Podrás registrar viviendas, locales, oficinas, garajes, trasteros, terrenos y edificios. La gestión detallada por habitaciones o unidades de un edificio llegará más adelante.",
    footer: "Tus alquileres en orden · Próxima beta gratuita",
  },
};

export const waitlistPages = [
  {
    path: "/",
    title: "Alquivo | Tus alquileres en orden",
    description:
      "Controla cobros, gastos y contratos de tus alquileres en un solo lugar. Apúntate a la próxima beta gratuita de Alquivo, sin tarjeta ni compromiso de compra.",
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
