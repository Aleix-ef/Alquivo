import { createSSRApp, h } from "vue";
import { renderToString } from "vue/server-renderer";
import { createPinia } from "pinia";
import { createI18n } from "vue-i18n";
import { createMemoryHistory, createRouter, RouterView } from "vue-router";
import { i18n } from "./i18n.js";
import { useProduct } from "./stores/product.js";
import WaitlistLandingView from "./views/WaitlistLandingView.vue";
import WaitlistLegalView from "./views/WaitlistLegalView.vue";
import { waitlistCopy, waitlistPages } from "./waitlist/settings.js";
import "./theme.css";
import "./style.css";
import "./marketing.css";
import "./waitlist/waitlist.css";

// Build-time rendering only: no app entry, session, HTTP requests or hydration.
export async function render(path, settings) {
  if (!waitlistPages.some((page) => page.path === path))
    throw new Error("Ruta no autorizada para la landing independiente.");
  const base = i18n.global.getLocaleMessage("es");
  const translation = createI18n({
    legacy: false,
    locale: "es",
    fallbackLocale: "es",
    messages: {
      es: {
        ...base,
        common: { ...base.common, ...waitlistCopy.common },
        marketing: { ...base.marketing, ...waitlistCopy.marketing },
      },
    },
  });
  const pinia = createPinia();
  useProduct(pinia).features = {
    beta_program: true,
    assistant: false,
    billing_enabled: false,
  };
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/", component: WaitlistLandingView, props: { settings } },
      {
        path: "/privacidad",
        component: WaitlistLegalView,
        props: { privacy: true },
      },
      { path: "/aviso-legal", component: WaitlistLegalView },
      {
        path: "/404",
        component: {
          render: () =>
            h("main", { id: "contenido", class: "legal-page legal-content" }, [
              h("h1", "Esta página no existe"),
              h("p", "La aplicación todavía no está abierta al público."),
              h("a", { href: "/", class: "button" }, "Volver a Alquivo"),
            ]),
        },
      },
    ],
  });
  const app = createSSRApp({ render: () => h(RouterView) })
    .use(pinia)
    .use(translation)
    .use(router);
  await router.push(path);
  await router.isReady();
  return renderToString(app);
}
