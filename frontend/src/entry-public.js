import { createSSRApp, h } from "vue";
import { renderToString } from "vue/server-renderer";
import { createPinia } from "pinia";
import { createMemoryHistory, createRouter, RouterView } from "vue-router";
import { publicRoutes } from "./publicRoutes.js";
import { pageSeo, renderHead, seoSettings } from "./seo.js";
import NotFoundView from "./views/NotFoundView.vue";

export const settings = seoSettings(import.meta.env);
export const betaProgram =
  import.meta.env.VITE_BETA_PROGRAM_ENABLED !== "false";
const routes = [...publicRoutes, { path: "/404", component: NotFoundView }];
export const paths = routes.map((route) => route.path);
export async function render(path) {
  if (!paths.includes(path))
    throw new Error("Solo se pueden generar páginas públicas autorizadas.");
  // A new isolated app per page; no session, API calls, cookies or stored user data.
  const router = createRouter({ history: createMemoryHistory(), routes });
  const app = createSSRApp({ render: () => h(RouterView) })
    .use(createPinia())
    .use(router);
  await router.push(path);
  await router.isReady();
  return {
    body: await renderToString(app),
    head: renderHead(pageSeo(path, settings)),
  };
}
