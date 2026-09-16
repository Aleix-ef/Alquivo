<script setup>
import { computed, ref, watch, onBeforeUnmount } from "vue";
import {
  LayoutDashboard,
  Building2,
  KeyRound,
  WalletCards,
  Wrench,
  CalendarDays,
  Files,
  Plus,
  LogOut,
  Settings,
  UsersRound,
  BarChart3,
  Menu,
  X,
  ChevronRight,
  ArrowUpRight,
  Layers,
  Sparkles,
  LifeBuoy,
} from "@lucide/vue";
import { useRoute, useRouter } from "vue-router";
import { useSession } from "../session";
import { useProduct } from "../stores/product";
import { usePlanAccess } from "../stores/planAccess";
const planAccess = usePlanAccess();
const product = useProduct();
import BrandLogo from "../components/BrandLogo.vue";
import AssistantWidget from "../components/AssistantWidget.vue";
import { useDialog } from "../composables/useDialog";
const route = useRoute();
const assistant = ref(null);
const assistantOpen = ref(false);
const mobileOpen = ref(false);
const mobileQuery = window.matchMedia("(max-width: 850px)");
const isMobile = ref(mobileQuery.matches);
function updateViewport(event) {
  isMobile.value = event.matches;
  if (!event.matches) mobileOpen.value = false;
}
mobileQuery.addEventListener("change", updateViewport);
onBeforeUnmount(() =>
  mobileQuery.removeEventListener("change", updateViewport),
);
const sidebarElement = ref(null);
useDialog(mobileOpen, sidebarElement, () => {
  mobileOpen.value = false;
});
const signingOut = ref(false);
const logoutError = ref("");
const s = useSession(),
  router = useRouter(),
  nav = [
    ["Resumen", "/dashboard", LayoutDashboard],
    ["Propiedades", "/properties", Building2],
    ["Alquileres", "/leases", KeyRound],
    ["Personas", "/contacts", UsersRound],
    ["Finanzas", "/finance", WalletCards],
    ["Informes", "/reports", BarChart3],
    ["Fiscalidad", "/fiscality", Files],
    ["Incidencias", "/issues", Wrench],
    ["Calendario", "/calendar", CalendarDays],
    ["Documentos", "/documents", Files],
  ];
const navGroups = computed(() => [
  { label: "Tu patrimonio", items: nav.slice(0, 4) },
  {
    label: "Gestión",
    items: nav
      .slice(4)
      .filter(
        ([, path]) => path !== "/fiscality" || product.features.fiscality,
      ),
  },
]);
const currentSection = computed(
  () =>
    [
      ...nav,
      ["Configuración", "/settings"],
      ["Planes", "/plans"],
      ["Ayuda y soporte", "/support"],
    ].find(
      ([, path]) => route.path === path || route.path.startsWith(`${path}/`),
    )?.[0] || "Tu cartera",
);
const initials = computed(() =>
  (s.user?.name || "Mi cuenta")
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join("")
    .toUpperCase(),
);
watch(
  () => [route.fullPath, s.user?.id],
  () => planAccess.load(s.user?.id),
  { immediate: true },
);
watch(
  () => route.fullPath,
  () => {
    mobileOpen.value = false;
  },
);
async function logout() {
  if (signingOut.value) return;
  signingOut.value = true;
  logoutError.value = "";
  try {
    await s.logout();
    await router.push("/login");
  } catch {
    logoutError.value = "No se pudo cerrar la sesión. Inténtalo de nuevo.";
  } finally {
    signingOut.value = false;
  }
}
</script>
<template>
  <div class="shell" :class="{ 'navigation-open': mobileOpen }">
    <a class="skip-link" href="#main-content">Saltar al contenido</a>
    <button
      v-if="mobileOpen"
      class="navigation-backdrop"
      aria-label="Cerrar navegación"
      @click="mobileOpen = false"
    ></button>
    <aside
      ref="sidebarElement"
      id="main-navigation"
      :inert="assistantOpen || (isMobile && !mobileOpen)"
      class="sidebar"
      :class="{ 'is-open': mobileOpen }"
      :role="mobileOpen ? 'dialog' : undefined"
      :aria-modal="mobileOpen || undefined"
      aria-label="Navegación"
      tabindex="-1"
      @keydown.esc="mobileOpen = false"
    >
      <div class="sidebar-brand-row">
        <RouterLink
          class="brand"
          to="/dashboard"
          aria-label="Alquivo, ir al resumen"
          ><BrandLogo
        /></RouterLink>
        <button
          class="icon-button mobile-close"
          aria-label="Cerrar navegación"
          @click="mobileOpen = false"
        >
          <X :size="19" />
        </button>
      </div>
      <div class="portfolio">
        <span class="portfolio-icon"><Layers :size="20" /></span>
        <div>
          <small>Espacio personal</small
          ><strong>{{ s.portfolio?.name || "Mi patrimonio" }}</strong>
        </div>
      </div>
      <nav aria-label="Navegación principal">
        <div v-for="group in navGroups" :key="group.label" class="nav-group">
          <p class="nav-label">{{ group.label }}</p>
          <RouterLink
            v-for="[label, to, icon] in group.items"
            :key="to"
            :to="to"
            :title="label"
          >
            <component
              :is="icon"
              :size="18"
              :stroke-width="1.7"
              aria-hidden="true"
            /><span>{{ label }}</span>
          </RouterLink>
        </div>
      </nav>
      <div class="sidebar-bottom">
        <RouterLink to="/support" class="sidebar-settings support-entry"
          ><LifeBuoy :size="20" /><span>Ayuda y soporte</span></RouterLink
        >
        <RouterLink to="/plans" class="sidebar-plan"
          ><span><span class="plan-spark">✦</span> Un espacio para crecer</span
          ><ArrowUpRight :size="16"
        /></RouterLink>
        <RouterLink to="/settings" class="sidebar-settings"
          ><Settings :size="18" /><span>Configuración</span></RouterLink
        >
        <div class="user">
          <RouterLink
            to="/settings"
            class="user-profile"
            aria-label="Ver mi cuenta"
          >
            <span class="avatar">{{ initials }}</span>
            <div>
              <strong>{{ s.user?.name }}</strong
              ><small>Mi cuenta personal</small>
            </div>
          </RouterLink>
          <button
            class="icon-button"
            :disabled="signingOut"
            aria-label="Cerrar sesión"
            title="Cerrar sesión"
            @click="logout"
          >
            <LogOut :size="17" />
          </button>
        </div>
        <p v-if="logoutError" class="error" role="alert">{{ logoutError }}</p>
      </div>
    </aside>
    <section class="workspace" :inert="mobileOpen || assistantOpen">
      <header class="topbar">
        <div class="topbar-location">
          <button
            class="icon-button mobile-menu"
            :aria-expanded="mobileOpen"
            aria-controls="main-navigation"
            aria-label="Abrir navegación"
            @click="mobileOpen = !mobileOpen"
          >
            <Menu :size="21" />
          </button>
          <span class="breadcrumb-root">Mi espacio</span
          ><ChevronRight class="breadcrumb-chevron" :size="14" /><strong>{{
            currentSection
          }}</strong>
        </div>
        <div class="topbar-actions">
          <button
            v-if="s.user?.email_verified_at && product.features.assistant"
            class="button secondary assistant-entry"
            aria-haspopup="dialog"
            aria-controls="alquivo-assistant-panel"
            :aria-expanded="assistantOpen"
            @click="assistant?.toggle()"
          >
            <Sparkles :size="18" /><span>Asistente IA</span>
          </button>
          <RouterLink
            class="button primary quick-add"
            to="/properties?new=1"
            aria-label="Añadir propiedad"
            ><Plus :size="17" /><span>Añadir propiedad</span></RouterLink
          >
        </div>
      </header>
      <div id="main-content" tabindex="-1">
        <p
          v-if="planAccess.usage?.properties.read_only_count"
          class="plan-access-notice"
          role="status"
        >
          Tu plan permite gestionar
          {{ planAccess.usage.properties.limit }} inmueble(s). Los
          {{ planAccess.usage.properties.read_only_count }} restantes están en
          modo consulta, sin borrar ningún dato.
          <RouterLink to="/plans">Ver mi plan</RouterLink>
        </p>
        <RouterView />
      </div>
      <footer class="workspace-footer">
        <span>Alquivo</span><span>Tu patrimonio, con claridad.</span>
      </footer>
    </section>
    <AssistantWidget
      v-if="s.user?.email_verified_at && product.features.assistant"
      ref="assistant"
      @open-change="assistantOpen = $event"
    />
  </div>
</template>
