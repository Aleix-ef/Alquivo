import { guides } from "./content/guides.js";
import { legalPages } from "./content/legal.js";

// One allowlist for the browser and the static renderer. No private routes here.
export const publicRoutes = [
  {
    path: "/",
    name: "home",
    component: () => import("./views/BetaLandingView.vue"),
    meta: { public: true },
  },
  {
    path: "/privacidad",
    name: "legacy-waitlist-privacy",
    component: () => import("./views/LegacyWaitlistPrivacyView.vue"),
    meta: { public: true },
  },
  ...["/guias", ...guides.map((guide) => guide.path)].map((path) => ({
    path,
    component: () => import("./views/GuidesView.vue"),
    meta: { public: true },
  })),
  ...legalPages.map(({ name, path }) => ({
    path,
    name,
    component: () => import("./views/LegalView.vue"),
    meta: { public: true },
  })),
];
