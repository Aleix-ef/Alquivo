<script setup>
import { computed, ref, watch, onBeforeUnmount, provide } from "vue";
import {
  LayoutDashboard,
  Building2,
  KeyRound,
  WalletCards,
  Wrench,
  CalendarDays,
  Files,
  LogOut,
  Settings,
  UsersRound,
  BarChart3,
  Menu,
  X,
  ChevronRight,
  Layers,
  Sparkles,
  LifeBuoy,
  BookOpen,
} from "@lucide/vue";
import { useRoute, useRouter } from "vue-router";
import { useSession } from "../session";
import api from "../api";
import { useProduct } from "../stores/product";
import { usePlanAccess } from "../stores/planAccess";
const planAccess = usePlanAccess();
const product = useProduct();
import BrandLogo from "../components/BrandLogo.vue";
import AssistantWidget from "../components/AssistantWidget.vue";
import OnboardingTour from "../components/OnboardingTour.vue";
import { useDialog } from "../composables/useDialog";
const route = useRoute();
const assistant = ref(null);
const tour = ref(null);
const assistantOpen = ref(false);
provide("open-assistant", () => assistant.value?.toggle());
provide("start-tour", () => tour.value?.start());
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
const verificationMessage = ref("");
const sendingVerification = ref(false);
async function resendVerification() {
  if (sendingVerification.value) return;
  sendingVerification.value = true;
  verificationMessage.value = "";
  try {
    verificationMessage.value = (
      await api.post("/auth/email/resend")
    ).data.message;
  } catch (error) {
    verificationMessage.value =
      error.response?.status === 429
        ? "Espera un minuto antes de volver a solicitar el enlace."
        : "No se pudo solicitar el enlace. Inténtalo de nuevo más tarde.";
  } finally {
    sendingVerification.value = false;
  }
}
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
const reminderDismissed = ref(false);
const reminderKey = computed(
  () => `alquivo:email-reminder:v1:${s.user?.id || ""}`,
);
watch(
  reminderKey,
  (key) => {
    try {
      reminderDismissed.value = localStorage.getItem(key) === "dismissed";
    } catch {
      reminderDismissed.value = false;
    }
  },
  { immediate: true },
);
function dismissReminder() {
  reminderDismissed.value = true;
  try {
    localStorage.setItem(reminderKey.value, "dismissed");
  } catch {
    /* La elección vale para esta sesión. */
  }
}
const primaryPaths = ["/dashboard", "/properties", "/leases", "/finance"];
const primaryNav = computed(() =>
  nav.filter(([, path]) => primaryPaths.includes(path)),
);
const secondaryNav = computed(() =>
  nav.filter(
    ([, path]) =>
      !primaryPaths.includes(path) &&
      (path !== "/fiscality" || product.accountFeatures.fiscality),
  ),
);
const moreOpen = ref(false);
watch(
  () => route.path,
  (path) => {
    moreOpen.value = secondaryNav.value.some(
      ([, to]) => path === to || path.startsWith(`${to}/`),
    );
  },
  { immediate: true },
);
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
        <button
          v-if="product.accountFeatures.assistant"
          class="nav-assistant-entry"
          type="button"
          @click="assistant?.toggle()"
        >
          <Sparkles :size="18" :stroke-width="1.7" aria-hidden="true" />
          <span>Alquivo AI</span>
          <span class="nav-assistant-badge">Beta</span>
        </button>
        <div class="nav-group">
          <p class="nav-label">Tu patrimonio</p>
          <RouterLink
            v-for="[label, to, icon] in primaryNav"
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
        <details
          class="nav-more"
          :open="moreOpen"
          @toggle="moreOpen = $event.target.open"
        >
          <summary>
            <Layers :size="18" aria-hidden="true" /><span>Más herramientas</span
            ><ChevronRight
              class="nav-more-chevron"
              :size="16"
              aria-hidden="true"
            />
          </summary>
          <div class="nav-group">
            <RouterLink
              v-for="[label, to, icon] in secondaryNav"
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
        </details>
      </nav>
      <div class="sidebar-bottom">
        <RouterLink
          v-if="s.user?.local_admin"
          to="/support/inbox"
          class="sidebar-settings"
          ><LifeBuoy :size="20" /><span>Bandeja del equipo</span></RouterLink
        >
        <button
          class="sidebar-settings tour-menu-entry"
          type="button"
          @click="
            mobileOpen = false;
            tour?.start();
          "
        >
          <BookOpen :size="19" /><span>Ver recorrido</span>
        </button>
        <RouterLink to="/support" class="sidebar-settings support-entry"
          ><LifeBuoy :size="20" /><span>Ayuda y soporte</span></RouterLink
        >
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
      </header>
      <div id="main-content" tabindex="-1">
        <section
          v-if="!s.user?.email_verified_at && !reminderDismissed"
          class="verification-banner"
          role="status"
        >
          <div>
            <strong>Confirma tu correo para proteger tu cuenta.</strong>
            <small v-if="verificationMessage">{{ verificationMessage }}</small>
          </div>
          <div class="verification-actions">
            <button
              class="button secondary"
              type="button"
              :disabled="sendingVerification"
              @click="resendVerification"
            >
              {{ sendingVerification ? "Enviando…" : "Reenviar" }}
            </button>
            <button
              class="verification-dismiss"
              type="button"
              @click="dismissReminder"
            >
              Ahora no
            </button>
          </div>
        </section>
        <p v-if="s.user?.local_admin" class="plan-access-notice" role="status">
          Administrador local · Funciones en pruebas visibles solo para esta
          cuenta. Los pagos siguen sujetos al bloqueo de la beta y la IA
          mantiene sus límites de consumo.
        </p>
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
        <RouterLink to="/legal">Información legal</RouterLink>
      </footer>
    </section>
    <OnboardingTour
      ref="tour"
      :assistant-available="product.accountFeatures.assistant"
      @open-assistant="assistant?.toggle()"
    />
    <AssistantWidget
      v-if="product.accountFeatures.assistant"
      ref="assistant"
      @open-change="assistantOpen = $event"
    />
  </div>
</template>
