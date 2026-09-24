import { guides } from "./content/guides.js";

export const publicPages = [
  {
    path: "/",
    title: "Alquivo | Gestión de alquileres para propietarios",
    description:
      "Organiza inmuebles, contratos, cobros, gastos y documentos con Alquivo. Software de gestión de alquileres para propietarios y pequeños inversores.",
  },
  {
    path: "/guias",
    title: "Guías para gestionar tus alquileres | Alquivo",
    description:
      "Guías prácticas para propietarios: organiza tus inmuebles y lleva el control de contratos, cobros, gastos y documentos del alquiler.",
  },
  ...guides.map((guide) => ({
    path: guide.path,
    title: `${guide.title} | Alquivo`,
    description: guide.description,
    article: true,
  })),
];

export function seoSettings(env = {}) {
  const indexable = env.VITE_SEO_INDEXABLE === "true";
  let origin = "";
  if (env.VITE_PUBLIC_SITE_URL) {
    const url = new URL(env.VITE_PUBLIC_SITE_URL);
    if (
      url.protocol !== "https:" ||
      url.username ||
      url.password ||
      url.pathname !== "/" ||
      url.search ||
      url.hash ||
      url.port ||
      !url.hostname.includes(".") ||
      /(^localhost$|\.(test|local|example|invalid)$|^[\d.]+$|:)/.test(
        url.hostname,
      )
    ) {
      throw new Error(
        "VITE_PUBLIC_SITE_URL debe ser el origen HTTPS público real, sin rutas ni parámetros.",
      );
    }
    origin = url.origin;
  }
  if (indexable && !origin)
    throw new Error(
      "Para indexar configura VITE_PUBLIC_SITE_URL con el dominio definitivo.",
    );
  return { indexable, origin };
}

export function pageSeo(path, settings) {
  // Intentionally do not canonicalize private URLs, token links or unknown pages.
  const page = publicPages.find((candidate) => candidate.path === path);
  const privateTitles = {
    "/404": "Página no encontrada",
    "/login": "Entrar",
    "/register": "Crear cuenta",
    "/terms": "Condiciones de uso",
    "/privacy": "Política de privacidad",
    "/forgot-password": "Recuperar contraseña",
    "/reset-password": "Restablecer contraseña",
  };
  return {
    title: page?.title || `${privateTitles[path] || "Tu espacio"} | Alquivo`,
    description:
      page?.description ||
      "Accede a tu espacio de gestión de alquileres en Alquivo.",
    robots:
      page && settings.indexable
        ? "index, follow, max-image-preview:large"
        : "noindex, nofollow",
    canonical: page && settings.origin ? `${settings.origin}${path}` : "",
    type: page?.article ? "article" : "website",
  };
}

export function escapeHtml(value) {
  return String(value).replace(
    /[&<>"']/g,
    (character) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[
        character
      ],
  );
}

export function headEntries(seo) {
  return [
    { name: "description", content: seo.description },
    { name: "robots", content: seo.robots },
    { property: "og:site_name", content: "Alquivo" },
    { property: "og:locale", content: "es_ES" },
    { property: "og:type", content: seo.type },
    { property: "og:title", content: seo.title },
    { property: "og:description", content: seo.description },
    { name: "twitter:card", content: "summary" },
    { name: "twitter:title", content: seo.title },
    { name: "twitter:description", content: seo.description },
    ...(seo.canonical
      ? [
          { property: "og:url", content: seo.canonical },
          { rel: "canonical", href: seo.canonical },
        ]
      : []),
  ];
}

export function renderHead(seo) {
  return (
    `<title>${escapeHtml(seo.title)}</title>\n` +
    headEntries(seo)
      .map(
        (attributes) =>
          `<${attributes.rel ? "link" : "meta"} data-seo ${Object.entries(
            attributes,
          )
            .map(([key, value]) => `${key}="${escapeHtml(value)}"`)
            .join(" ")}>`,
      )
      .join("\n")
  );
}

export function updateHead(path) {
  const seo = pageSeo(path, seoSettings(import.meta.env));
  document.title = seo.title;
  document.head
    .querySelectorAll("[data-seo]")
    .forEach((element) => element.remove());
  for (const attributes of headEntries(seo)) {
    const element = document.createElement(attributes.rel ? "link" : "meta");
    element.dataset.seo = "";
    for (const [key, value] of Object.entries(attributes))
      element.setAttribute(key, value);
    document.head.append(element);
  }
}
