import { createRouter, createWebHistory } from "vue-router";
import { useSession } from "./session";
const publicPage = (n) => ({
  name: n,
  component: () => import("./views/RecoveryView.vue"),
  meta: { public: true },
});
const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: "/login",
      name: "login",
      component: () => import("./views/AuthView.vue"),
      meta: { public: true },
    },
    {
      path: "/register",
      name: "register",
      component: () => import("./views/AuthView.vue"),
      meta: { public: true },
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
router.beforeEach((to) => {
  const s = useSession();
  if (!to.meta.public && !s.ready) return "/login";
  if (to.meta.public && s.ready) return "/dashboard";
});
export default router;
