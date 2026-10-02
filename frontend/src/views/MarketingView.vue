<script setup>
import { computed, onMounted, ref } from "vue";
import { useI18n } from "vue-i18n";
import {
  ArrowRight,
  BarChart3,
  BellRing,
  Building2,
  CalendarDays,
  Check,
  ChevronRight,
  CircleCheck,
  FileText,
  FolderLock,
  ReceiptText,
  Sparkles,
  WalletCards,
} from "@lucide/vue";
import api from "../api";
import BrandLogo from "../components/BrandLogo.vue";
import LegalLinks from "../components/LegalLinks.vue";
import { useProduct } from "../stores/product";
import { guides } from "../content/guides.js";

// The static prelaunch build reuses this page, without exposing app navigation.
const props = defineProps({ waitlistMode: { type: Boolean, default: false } });
const signupTarget = computed(() =>
  props.waitlistMode ? "/#solicitud" : "/register",
);
const { t } = useI18n({ useScope: "global" });
const plans = ref([]);
const product = useProduct();

const fallbackPlans = [
  {
    code: "beta",
    name: "Beta gratuita",
    price_monthly: 0,
    price_yearly: null,
    property_limit: 50,
    features: [
      "Dashboard, alquileres y finanzas",
      "Hasta 50 inmuebles",
      "5 GB de documentos y fotos",
      "Informes y exportación de datos",
    ],
  },
];

const visiblePlans = computed(() =>
  plans.value.length
    ? plans.value
    : product.features.beta_program
      ? fallbackPlans
      : [],
);
const money = (value) =>
  new Intl.NumberFormat(t("language.numberLocale"), {
    style: "currency",
    currency: "EUR",
    maximumFractionDigits: value % 1 ? 2 : 0,
  }).format(value);

const featureLabels = {
  "Dashboard, alquileres y finanzas": "dashboardFeature",
  "Hasta 50 inmuebles": "propertyLimitFeature",
  "5 GB de documentos y fotos": "storageFeature",
  "Informes y exportación de datos": "reportsFeature",
};
const featureLabel = (value) =>
  featureLabels[value] ? t(`marketing.${featureLabels[value]}`) : value;
const planName = (plan) =>
  plan.code === "beta" ? t("marketing.betaName") : plan.name;
onMounted(async () => {
  if (props.waitlistMode) return;
  try {
    const { data } = await api.get("/public/plans");
    if (Array.isArray(data.plans)) plans.value = data.plans;
  } catch {
    // The page remains useful during a temporary API outage.
  }
});
</script>

<template>
  <main
    id="contenido"
    class="marketing-page"
    :class="{ 'waitlist-page': waitlistMode }"
  >
    <header class="marketing-header">
      <RouterLink class="marketing-logo" to="/" aria-label="Alquivo, inicio">
        <BrandLogo />
      </RouterLink>
      <nav :aria-label="$t('marketing.mainNav')">
        <a href="#como-funciona">{{ $t("marketing.how") }}</a>
        <a href="#asistente">{{ $t("marketing.assistant") }}</a>
        <a href="#funciones">{{ $t("marketing.features") }}</a>
        <a href="#planes">{{ $t("marketing.plans") }}</a>
        <RouterLink v-if="!waitlistMode" to="/guias">{{
          $t("marketing.guides")
        }}</RouterLink>
      </nav>
      <div class="marketing-header-actions">
        <RouterLink
          v-if="!waitlistMode"
          class="button button-quiet marketing-login"
          to="/login"
          >{{ $t("common.login") }}</RouterLink
        >
        <RouterLink class="button button-small" :to="signupTarget">{{
          $t("common.startFree")
        }}</RouterLink>
      </div>
    </header>

    <section class="hero-section">
      <div class="hero-copy">
        <p class="marketing-eyebrow">
          <span></span>
          {{
            product.features.beta_program
              ? $t("marketing.freeBeta")
              : $t("marketing.freePlan")
          }}
          ·
          {{
            product.features.assistant
              ? $t("marketing.assistantAvailable")
              : $t("marketing.assistantSoon")
          }}
        </p>
        <h1>
          {{ $t("marketing.heroFirst") }}
          <em>{{ $t("marketing.heroSecond") }}</em>
        </h1>
        <p class="hero-lead">
          {{
            product.features.assistant
              ? $t("marketing.heroWithAI")
              : $t("marketing.heroWithoutAI")
          }}
        </p>
        <div class="hero-actions">
          <RouterLink class="button button-large" :to="signupTarget">
            {{
              product.features.beta_program
                ? $t("marketing.tryBeta")
                : $t("marketing.createFree")
            }}
            <ArrowRight :size="18" aria-hidden="true" />
          </RouterLink>
          <a class="text-action" href="#asistente"
            >{{ $t("marketing.learnAssistant") }} <ChevronRight :size="17"
          /></a>
        </div>
        <div
          class="hero-reassurance"
          :aria-label="$t('marketing.accessConditions')"
        >
          <span
            ><CircleCheck :size="16" />
            {{
              product.features.beta_program
                ? $t("marketing.freeBeta")
                : $t("marketing.freePlan")
            }}</span
          >
          <span><CircleCheck :size="16" /> {{ $t("marketing.noCard") }}</span>
          <span
            ><CircleCheck :size="16" />
            {{
              product.features.beta_program
                ? $t("marketing.betaPropertyLimit")
                : $t("marketing.noAutoRenew")
            }}</span
          >
        </div>
      </div>

      <div class="product-preview" :aria-label="$t('marketing.productPreview')">
        <div class="preview-window">
          <div class="preview-sidebar">
            <div class="preview-mini-logo"><BrandLogo compact /></div>
            <span class="preview-nav active"></span
            ><span class="preview-nav"></span> <span class="preview-nav"></span
            ><span class="preview-nav"></span>
            <span class="preview-nav"></span>
          </div>
          <div class="preview-main">
            <div class="preview-top"><span></span><i></i></div>
            <p>{{ $t("marketing.previewPortfolio") }}</p>
            <div class="preview-value">
              248.500 € <small>{{ $t("marketing.thisYear") }}</small>
            </div>
            <div class="preview-cards">
              <div>
                <small>{{ $t("marketing.monthlyIncome") }}</small
                ><strong>1.940 €</strong><span class="up">+ 3,1%</span>
              </div>
              <div>
                <small>{{ $t("marketing.plannedExpenses") }}</small
                ><strong>382 €</strong
                ><span>{{ $t("marketing.thisMonth") }}</span>
              </div>
              <div>
                <small>{{ $t("marketing.netYield") }}</small
                ><strong>5,8%</strong
                ><span class="up">{{ $t("marketing.stable") }}</span>
              </div>
            </div>
            <div class="preview-lower">
              <div class="preview-chart">
                <div class="chart-heading">
                  <strong>{{ $t("marketing.incomeTrend") }}</strong
                  ><span>2026</span>
                </div>
                <div class="chart-bars">
                  <i></i><i></i><i></i><i></i><i></i><i></i><i></i>
                </div>
              </div>
              <div class="preview-events">
                <strong>{{ $t("marketing.comingUp") }}</strong>
                <p><i></i> {{ $t("marketing.rentPayment") }}</p>
                <p><i></i> {{ $t("marketing.contractRenewal") }}</p>
              </div>
            </div>
          </div>
        </div>
        <div class="preview-note">{{ $t("marketing.sampleDisclaimer") }}</div>
      </div>
    </section>

    <section
      id="asistente"
      class="ai-preview-section"
      aria-labelledby="ai-preview-title"
    >
      <div class="ai-preview-inner">
        <div class="ai-preview-copy">
          <p class="marketing-eyebrow">
            <Sparkles :size="17" />Alquivo AI ·
            {{
              product.features.assistant
                ? $t("marketing.available")
                : $t("marketing.inPreparation")
            }}
          </p>
          <h2 id="ai-preview-title">
            {{ $t("marketing.simpleQuestion") }}<br />{{
              $t("marketing.contextualAnswer")
            }}
          </h2>
          <p>
            {{
              product.features.assistant
                ? $t("marketing.assistantDetailOn")
                : $t("marketing.assistantDetailOff")
            }}
          </p>
          <ul class="ai-preview-points">
            <li><Check :size="18" />{{ $t("marketing.basedOnRecords") }}</li>
            <li><Check :size="18" />{{ $t("marketing.clearAmounts") }}</li>
            <li><Check :size="18" />{{ $t("marketing.noInventing") }}</li>
          </ul>
          <RouterLink class="button button-large" :to="signupTarget">
            {{
              product.features.beta_program
                ? $t("marketing.startBeta")
                : $t("marketing.startFree")
            }}
            <ArrowRight :size="18" aria-hidden="true" />
          </RouterLink>
        </div>
        <div
          class="ai-preview-card"
          :aria-label="$t('marketing.futureConversation')"
        >
          <header class="ai-preview-card-header">
            <span class="ai-preview-brand"
              ><Sparkles :size="19" />Alquivo AI</span
            >
            <span class="ai-preview-status">{{ $t("marketing.preview") }}</span>
          </header>
          <p class="ai-preview-caption">
            {{ $t("marketing.fictionalExample") }}
          </p>
          <div class="ai-question">
            {{ $t("marketing.exampleQuestion") }}
          </div>
          <div class="ai-answer">
            <strong>{{ $t("marketing.exampleAnswerTitle") }}</strong>
            <p>
              {{ $t("marketing.exampleAnswer") }}
            </p>
          </div>
          <div class="ai-preview-topics" :aria-label="$t('marketing.topics')">
            <span>{{ $t("marketing.payments") }}</span
            ><span>{{ $t("marketing.contracts") }}</span
            ><span>{{ $t("marketing.alerts") }}</span>
          </div>
        </div>
      </div>
    </section>

    <section id="como-funciona" class="simple-section process-section">
      <div class="section-heading">
        <p class="marketing-eyebrow">
          {{ $t("marketing.lessSpreadsheets") }}
        </p>
        <h2>{{ $t("marketing.essentials") }}</h2>
        <p>
          {{ $t("marketing.notERP") }}
        </p>
      </div>
      <div class="process-grid">
        <article>
          <span>01</span><Building2 :size="25" />
          <h3>{{ $t("marketing.addPortfolio") }}</h3>
          <p>
            {{ $t("marketing.addPortfolioBody") }}
          </p>
        </article>
        <article>
          <span>02</span><WalletCards :size="25" />
          <h3>{{ $t("marketing.organiseMovements") }}</h3>
          <p>
            {{ $t("marketing.organiseMovementsBody") }}
          </p>
        </article>
        <article>
          <span>03</span><BarChart3 :size="25" />
          <h3>{{ $t("marketing.decideClearly") }}</h3>
          <p>
            {{ $t("marketing.decideClearlyBody") }}
          </p>
        </article>
      </div>
    </section>

    <section id="funciones" class="feature-section">
      <div class="section-heading">
        <p class="marketing-eyebrow">{{ $t("marketing.fullView") }}</p>
        <h2>{{ $t("marketing.madeForInvestors") }}</h2>
      </div>
      <div class="feature-grid">
        <article class="feature-card feature-card-large">
          <div class="feature-icon"><BarChart3 /></div>
          <h3>{{ $t("marketing.oneView") }}</h3>
          <p>
            {{ $t("marketing.oneViewBody") }}
          </p>
          <div class="feature-stat">
            <span>{{ $t("marketing.annualNet") }}</span
            ><strong>+ 14.860 €</strong
            ><small>{{ $t("marketing.exampleNotForecast") }}</small>
          </div>
        </article>
        <article class="feature-card">
          <div class="feature-icon"><ReceiptText /></div>
          <h3>{{ $t("marketing.paymentsControl") }}</h3>
          <p>
            {{ $t("marketing.paymentsControlBody") }}
          </p>
        </article>
        <article class="feature-card">
          <div class="feature-icon"><CalendarDays /></div>
          <h3>{{ $t("marketing.nothingMissed") }}</h3>
          <p>
            {{ $t("marketing.nothingMissedBody") }}
          </p>
        </article>
        <article class="feature-card">
          <div class="feature-icon"><FolderLock /></div>
          <h3>{{ $t("marketing.documentsPlace") }}</h3>
          <p>
            {{ $t("marketing.documentsPlaceBody") }}
          </p>
        </article>
        <article class="feature-card">
          <div class="feature-icon"><BellRing /></div>
          <h3>{{ $t("marketing.attendImportant") }}</h3>
          <p>
            {{ $t("marketing.attendImportantBody") }}
          </p>
        </article>
      </div>
    </section>

    <section class="calm-section">
      <div>
        <p class="marketing-eyebrow">{{ $t("marketing.peaceOfMind") }}</p>
        <h2>{{ $t("marketing.yourData") }}</h2>
      </div>
      <div class="calm-points">
        <p><Check :size="18" /> {{ $t("marketing.isolatedPortfolio") }}</p>
        <p><Check :size="18" /> {{ $t("marketing.privateDocuments") }}</p>
        <p><Check :size="18" /> {{ $t("marketing.noCardToStart") }}</p>
      </div>
    </section>

    <section id="planes" class="plans-section">
      <div class="section-heading centered">
        <p class="marketing-eyebrow">{{ $t("marketing.startGently") }}</p>
        <h2>
          {{
            product.features.beta_program
              ? $t("marketing.betaFree")
              : $t("marketing.planGrows")
          }}
        </h2>
        <p v-if="product.features.beta_program">
          {{ $t("marketing.betaDetails") }}
        </p>
        <p v-else>
          {{ $t("marketing.freeDetails") }}
        </p>
        <p
          v-if="
            !product.features.beta_program && !product.features.billing_enabled
          "
        >
          {{ $t("marketing.priceInformative") }}
        </p>
      </div>
      <div
        class="marketing-plans"
        :class="{ 'beta-only': product.features.beta_program }"
      >
        <article
          v-for="plan in visiblePlans"
          :key="plan.code"
          class="marketing-plan"
          :class="{ featured: plan.code === 'founder' || plan.code === 'beta' }"
        >
          <span v-if="plan.code === 'beta'" class="plan-label">{{
            $t("marketing.betaAccess")
          }}</span>
          <span v-else-if="plan.code === 'founder'" class="plan-label">{{
            $t("marketing.founderPrice")
          }}</span>
          <p class="plan-name">{{ planName(plan) }}</p>
          <div class="plan-price">
            <template v-if="plan.price_monthly"
              ><strong>{{ money(plan.price_monthly) }}</strong
              ><span>{{ $t("marketing.perMonth") }}</span></template
            ><strong v-else>{{ $t("marketing.free") }}</strong>
          </div>
          <p class="plan-description">
            {{
              plan.property_limit === 1
                ? $t("marketing.forOne")
                : $t("marketing.forUpTo", { count: plan.property_limit })
            }}
          </p>
          <RouterLink
            class="button"
            :class="{ 'button-quiet': plan.code === 'free' }"
            :to="signupTarget"
            >{{ $t("marketing.startFree") }}</RouterLink
          >
          <ul>
            <li v-for="feature in plan.features" :key="feature">
              <Check :size="16" />{{ featureLabel(feature) }}
            </li>
          </ul>
          <small v-if="plan.price_yearly">{{
            $t("marketing.yearlyPrice", { price: money(plan.price_yearly) })
          }}</small>
        </article>
      </div>
    </section>

    <section class="faq-section">
      <div class="section-heading">
        <p class="marketing-eyebrow">{{ $t("marketing.faqEyebrow") }}</p>
        <h2>{{ $t("marketing.faqTitle") }}</h2>
      </div>
      <div class="faq-list">
        <details>
          <summary>
            {{ $t("marketing.faqCard") }}<ChevronRight :size="19" />
          </summary>
          <p v-if="product.features.beta_program">
            {{ $t("marketing.faqCardBeta") }}
          </p>
          <p v-else>
            {{ $t("marketing.faqCardFree") }}
          </p>
        </details>
        <details>
          <summary>
            {{
              product.features.beta_program
                ? $t("marketing.faqAfterBeta")
                : $t("marketing.faqFreePlan")
            }}<ChevronRight :size="19" />
          </summary>
          <p v-if="product.features.beta_program">
            {{ $t("marketing.faqAfterBetaAnswer") }}
          </p>
          <p v-else>
            {{ $t("marketing.faqFreePlanAnswer") }}
          </p>
        </details>
        <details>
          <summary>
            {{ $t("marketing.faqAI") }}<ChevronRight :size="19" />
          </summary>
          <p v-if="product.features.assistant">
            {{ $t("marketing.faqAIYes") }}
          </p>
          <p v-else>
            {{ $t("marketing.faqAINo") }}
          </p>
        </details>
        <details>
          <summary>
            {{ $t("marketing.faqTypes") }}<ChevronRight :size="19" />
          </summary>
          <p>
            {{ $t("marketing.faqTypesAnswer") }}
          </p>
        </details>
        <details>
          <summary>
            {{ $t("marketing.faqAdvisor") }}<ChevronRight :size="19" />
          </summary>
          <p>
            {{ $t("marketing.faqAdvisorAnswer") }}
          </p>
        </details>
      </div>
    </section>

    <section
      class="simple-section"
      aria-labelledby="about-alquivo"
      itemscope
      itemtype="https://schema.org/SoftwareApplication"
    >
      <div class="section-heading">
        <p class="marketing-eyebrow">{{ $t("marketing.whatIs") }}</p>
        <h2 id="about-alquivo">
          <span itemprop="name">Alquivo</span>:
          {{ $t("marketing.whatIsTitle") }}
        </h2>
        <p itemprop="description">
          {{ $t("marketing.whatIsBody") }}
        </p>
        <p>
          <span itemprop="applicationCategory">{{
            $t("marketing.softwareCategory")
          }}</span>
          ·
          <span itemprop="operatingSystem">{{
            $t("marketing.webBrowser")
          }}</span>
        </p>
        <p>
          {{ $t("marketing.notAgency") }}
        </p>
      </div>
    </section>

    <section
      v-if="!waitlistMode"
      class="simple-section"
      aria-labelledby="guides-heading"
    >
      <div class="section-heading">
        <p class="marketing-eyebrow">{{ $t("marketing.resources") }}</p>
        <h2 id="guides-heading">{{ $t("marketing.resourcesTitle") }}</h2>
      </div>
      <p v-if="$i18n.locale === 'en'" class="guide-summary">
        {{ $t("marketing.guidesSpanish") }}
      </p>
      <div v-else class="guide-grid">
        <article v-for="guide in guides" :key="guide.path" class="guide-card">
          <h3>
            <RouterLink :to="guide.path">{{ guide.title }}</RouterLink>
          </h3>
          <p>{{ guide.description }}</p>
        </article>
      </div>
    </section>

    <slot name="closing">
      <section class="closing-section">
        <p class="marketing-eyebrow">
          <span></span> {{ $t("marketing.startToday") }}
        </p>
        <h2>
          {{ $t("marketing.closingFirst") }}<br />{{
            $t("marketing.closingSecond")
          }}
        </h2>
        <p>{{ $t("marketing.ownSpace") }}</p>
        <RouterLink class="button button-large" :to="signupTarget"
          >{{ $t("marketing.createAccount") }} <ArrowRight :size="18"
        /></RouterLink>
      </section>
    </slot>

    <footer class="marketing-footer">
      <BrandLogo />
      <p>{{ $t("marketing.footer") }}</p>
      <div v-if="!waitlistMode">
        <RouterLink to="/guias">{{ $t("marketing.ownerGuides") }}</RouterLink
        ><RouterLink to="/login">{{ $t("common.login") }}</RouterLink>
      </div>
      <slot name="legal"><LegalLinks /></slot>
    </footer>
  </main>
</template>
