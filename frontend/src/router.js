import { createRouter, createWebHistory } from "vue-router";
import { useSession } from "./session";
import { useProduct } from "./stores/product";
import { safeReturnPath } from "./authNavigation";
import { publicRoutes } from "./publicRoutes.js";
import { updateHead } from "./seo.js";
const publicPage = (n) => ({
  name: n,
  component: () => import("./views/RecoveryView.vue"),
  meta: { public: true },
});
const router = createRouter({
  history: createWebHistory(),
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) return savedPosition;
    if (to.hash) return { el: to.hash, top: 24 };
    if (to.path === from.path) return false;
    return { top: 0 };
  },
  routes: [
    ...publicRoutes,
    {
      path: "/login",
      name: "login",
      component: () => import("./views/AuthView.vue"),
      meta: { public: true, guestOnly: true },
    },
    {
      path: "/register",
      name: "register",
      component: () => import("./views/AuthView.vue"),
      meta: { public: true, guestOnly: true },
    },
    { path: "/forgot-password", ...publicPage("forgot") },
    { path: "/reset-password", ...publicPage("reset") },
    {
      path: "/verify-email",
      component: () => import("./views/VerifyEmailView.vue"),
    },
    {
      path: "/",
      component: () => import("./views/ShellView.vue"),
      children: [
        { path: "support", component: () => import("./views/SupportView.vue") },
        {
          path: "support/inbox",
          component: () => import("./views/SupportInboxView.vue"),
        },
        {
          path: "dashboard",
          component: () => import("./views/DashboardView.vue"),
        },
        {
          path: "properties",
          component: () => import("./views/PropertiesView.vue"),
        },
        {
          path: "properties/:id",
          component: () => import("./views/PropertyDetailView.vue"),
        },
        { path: "leases", component: () => import("./views/LeasesView.vue") },
        {
          path: "leases/:id",
          component: () => import("./views/LeaseDetailView.vue"),
        },
        {
          path: "contacts",
          component: () => import("./views/ContactsView.vue"),
        },
        { path: "finance", component: () => import("./views/FinanceView.vue") },
        { path: "reports", component: () => import("./views/ReportsView.vue") },
        {
          path: "fiscality",
          component: () => import("./views/FiscalityView.vue"),
        },
        { path: "issues", component: () => import("./views/IssuesView.vue") },
        {
          path: "calendar",
          component: () => import("./views/CalendarView.vue"),
        },
        {
          path: "documents",
          component: () => import("./views/DocumentsView.vue"),
        },
        {
          path: "documents/import/:id?",
          component: () => import("./views/DocumentImportView.vue"),
        },
        {
          path: "settings",
          component: () => import("./views/SettingsView.vue"),
        },
        { path: "plans", component: () => import("./views/PlansView.vue") },
      ],
    },
    {
      path: "/:pathMatch(.*)*",
      component: () => import("./views/NotFoundView.vue"),
      meta: { public: true },
    },
  ],
});
router.beforeEach(async (to) => {
  const product = useProduct();
  if (!product.loaded && !product.error) await product.load();
  const s = useSession();
  if (!s.initialized && (!to.meta.public || to.meta.guestOnly))
    await s.restore();
  if (!to.meta.public && !s.ready) {
    return { name: "login", query: { redirect: to.fullPath } };
  }
  if (
    !to.meta.public &&
    s.ready &&
    !s.user.email_verified_at &&
    !["/settings", "/plans", "/support", "/verify-email"].includes(to.path)
  ) {
    return "/settings";
  }
  if (to.meta.guestOnly && s.ready) return safeReturnPath(to.query.redirect);
  if (to.path === "/fiscality" && !product.accountFeatures.fiscality)
    return "/reports";
});
router.afterEach((to, from, failure) => {
  if (!failure)
    updateHead(
      to.matched.some((record) => record.path.includes(":pathMatch"))
        ? "/404"
        : to.path,
    );
});
export default router;
