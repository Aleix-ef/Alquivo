import { createRouter, createWebHistory } from "vue-router";
import { useSession } from "./session";
import { safeReturnPath } from "./authNavigation";
const publicPage = (n) => ({
  name: n,
  component: () => import("./views/RecoveryView.vue"),
  meta: { public: true },
});
const router = createRouter({
  history: createWebHistory(),
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) return savedPosition;
    if (to.path === from.path) return false;
    return { top: 0 };
  },
  routes: [
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
      path: "/terms",
      name: "terms",
      component: () => import("./views/LegalView.vue"),
      meta: { public: true },
    },
    {
      path: "/privacy",
      name: "privacy",
      component: () => import("./views/LegalView.vue"),
      meta: { public: true },
    },
    {
      path: "/",
      component: () => import("./views/ShellView.vue"),
      children: [
        { path: "", redirect: "/dashboard" },
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
          path: "settings",
          component: () => import("./views/SettingsView.vue"),
        },
        { path: "plans", component: () => import("./views/PlansView.vue") },
        { path: ":section", component: () => import("./views/SoonView.vue") },
      ],
    },
  ],
});
router.beforeEach(async (to) => {
  const s = useSession();
  if (!s.initialized && (!to.meta.public || to.meta.guestOnly))
    await s.restore();
  if (!to.meta.public && !s.ready) {
    return { name: "login", query: { redirect: to.fullPath } };
  }
  if (to.meta.guestOnly && s.ready) return safeReturnPath(to.query.redirect);
});
export default router;
