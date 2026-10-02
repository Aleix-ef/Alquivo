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
import { useI18n } from "vue-i18n";
import { useSession } from "../session";
import api from "../api";
import { useProduct } from "../stores/product";
import { usePlanAccess } from "../stores/planAccess";
const planAccess = usePlanAccess();
const product = useProduct();
import BrandLogo from "../components/BrandLogo.vue";
import LanguageSwitcher from "../components/LanguageSwitcher.vue";
import AssistantWidget from "../components/AssistantWidget.vue";
import OnboardingTour from "../components/OnboardingTour.vue";
import { useDialog } from "../composables/useDialog";
const { t } = useI18n({ useScope: "global" });
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
        ? t("nav.resendWait")
        : t("nav.resendError");
  } finally {
    sendingVerification.value = false;
  }
}
const s = useSession(),
  router = useRouter(),
  nav = [
    ["nav.dashboard", "/dashboard", LayoutDashboard],
    ["nav.properties", "/properties", Building2],
    ["nav.leases", "/leases", KeyRound],
    ["nav.people", "/contacts", UsersRound],
    ["nav.finance", "/finance", WalletCards],
    ["nav.reports", "/reports", BarChart3],
    ["nav.tax", "/fiscality", Files],
    ["nav.issues", "/issues", Wrench],
    ["nav.calendar", "/calendar", CalendarDays],
    ["nav.documents", "/documents", Files],
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
      ["nav.settings", "/settings"],
      ["nav.plans", "/plans"],
      ["nav.support", "/support"],
    ].find(
      ([, path]) => route.path === path || route.path.startsWith(`${path}/`),
    )?.[0] || "nav.portfolio",
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
    logoutError.value = t("nav.logoutError");
  } finally {
    signingOut.value = false;
  }
}
</script>
<template>
  <div class="shell" :class="{ 'navigation-open': mobileOpen }">
    <a class="skip-link" href="#main-content">{{ $t("nav.skip") }}</a>
    <button
      v-if="mobileOpen"
      class="navigation-backdrop"
      :aria-label="$t('nav.closeNav')"
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
          :aria-label="$t('nav.closeNav')"
          @click="mobileOpen = false"
        >
          <X :size="19" />
        </button>
      </div>
      <div class="portfolio">
        <span class="portfolio-icon"><Layers :size="20" /></span>
        <div>
          <small>{{ $t("nav.personalSpace") }}</small
          ><strong>{{ s.portfolio?.name || $t("nav.portfolio") }}</strong>
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
          <p class="nav-label">{{ $t("nav.portfolio") }}</p>
          <RouterLink
            v-for="[label, to, icon] in primaryNav"
            :key="to"
            :to="to"
            :title="$t(label)"
          >
            <component
              :is="icon"
              :size="18"
              :stroke-width="1.7"
              aria-hidden="true"
            /><span>{{ $t(label) }}</span>
          </RouterLink>
        </div>
        <details
          class="nav-more"
          :open="moreOpen"
          @toggle="moreOpen = $event.target.open"
        >
          <summary>
            <Layers :size="18" aria-hidden="true" /><span>{{
              $t("nav.more")
            }}</span
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
              :title="$t(label)"
            >
              <component
                :is="icon"
                :size="18"
                :stroke-width="1.7"
                aria-hidden="true"
              /><span>{{ $t(label) }}</span>
            </RouterLink>
          </div>
        </details>
      </nav>
      <div class="sidebar-bottom">
        <RouterLink
          v-if="s.serverConfirmedAdmin"
          to="/support/inbox"
          class="sidebar-settings"
          ><LifeBuoy :size="20" /><span>{{
            $t("nav.teamInbox")
          }}</span></RouterLink
        >
        <button
          class="sidebar-settings tour-menu-entry"
          type="button"
          @click="
            mobileOpen = false;
            tour?.start();
          "
        >
          <BookOpen :size="19" /><span>{{ $t("nav.tour") }}</span>
        </button>
        <RouterLink to="/support" class="sidebar-settings support-entry"
          ><LifeBuoy :size="20" /><span>{{
            $t("nav.support")
          }}</span></RouterLink
        >
        <RouterLink to="/settings" class="sidebar-settings"
          ><Settings :size="18" /><span>{{
            $t("nav.settings")
          }}</span></RouterLink
        >
        <div class="user">
          <RouterLink
            to="/settings"
            class="user-profile"
            :aria-label="$t('nav.account')"
          >
            <span class="avatar">{{ initials }}</span>
            <div>
              <strong>{{ s.user?.name }}</strong
              ><small>{{ $t("nav.account") }}</small>
            </div>
          </RouterLink>
          <button
            class="icon-button"
            :disabled="signingOut"
            :aria-label="$t('nav.signOut')"
            :title="$t('nav.signOut')"
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
            :aria-label="$t('nav.openNav')"
            @click="mobileOpen = !mobileOpen"
          >
            <Menu :size="21" />
          </button>
          <span class="breadcrumb-root">{{ $t("nav.breadcrumb") }}</span
          ><ChevronRight class="breadcrumb-chevron" :size="14" /><strong>{{
            $t(currentSection)
          }}</strong>
        </div>
        <div v-if="s.serverConfirmedAdmin" class="topbar-actions">
          <LanguageSwitcher />
        </div>
      </header>
      <div id="main-content" tabindex="-1">
        <p
          v-if="$i18n.locale === 'en'"
          class="plan-access-notice"
          role="status"
        >
          {{ $t("nav.englishPreview") }}
        </p>
        <section
          v-if="!s.user?.email_verified_at && !reminderDismissed"
          class="verification-banner"
          role="status"
        >
          <div>
            <strong>{{ $t("nav.verifyPrompt") }}</strong>
            <small v-if="verificationMessage">{{ verificationMessage }}</small>
          </div>
          <div class="verification-actions">
            <button
              class="button secondary"
              type="button"
              :disabled="sendingVerification"
              @click="resendVerification"
            >
              {{ sendingVerification ? $t("nav.sending") : $t("nav.resend") }}
            </button>
            <button
              class="verification-dismiss"
              type="button"
              @click="dismissReminder"
            >
              {{ $t("nav.notNow") }}
            </button>
          </div>
        </section>
        <p
          v-if="s.serverConfirmedAdmin"
          class="plan-access-notice"
          role="status"
        >
          {{ $t("nav.adminNotice") }}
        </p>
        <p
          v-if="planAccess.usage?.properties.read_only_count"
          class="plan-access-notice"
          role="status"
        >
          {{
            $t("nav.readOnlyNotice", {
              limit: planAccess.usage.properties.limit,
              count: planAccess.usage.properties.read_only_count,
            })
          }}
          <RouterLink to="/plans">{{ $t("nav.viewPlan") }}</RouterLink>
        </p>
        <RouterView />
      </div>
      <footer class="workspace-footer">
        <span>Alquivo</span><span>{{ $t("auth.storyEyebrow") }}</span>
        <RouterLink to="/legal">{{ $t("nav.legalInfo") }}</RouterLink>
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
