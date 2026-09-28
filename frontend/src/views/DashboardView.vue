<script setup>
import { ref, onMounted, onBeforeUnmount, computed, inject } from "vue";
import {
  Building2,
  BadgeEuro,
  KeyRound,
  ArrowUpRight,
  ArrowDownLeft,
  ArrowRight,
  CalendarDays,
  Wallet,
  TrendingUp,
  CircleAlert,
  RefreshCw,
  Sparkles,
} from "@lucide/vue";
import api from "../api";
import { useSession } from "../session";
import AttentionPanel from "../components/AttentionPanel.vue";
import { useProduct } from "../stores/product";
import "../dashboard.css";

const session = useSession();
const product = useProduct();
const openAssistant = inject("open-assistant", () => {});
const startTour = inject("start-tour", () => {});
const assistantNeedsIntroduction = computed(
  () => !product.accountFeatures.assistant,
);
const data = ref(null);
const error = ref("");
const loading = ref(true);
const metrics = computed(() => data.value?.metrics || {});
const hasProperties = computed(() => Boolean(data.value?.properties?.length));
const now = new Date();
const greeting =
  now.getHours() < 12
    ? "Buenos días"
    : now.getHours() < 20
      ? "Buenas tardes"
      : "Buenas noches";
const month = computed(() =>
  new Intl.DateTimeFormat("es-ES", { month: "long", year: "numeric" }).format(
    data.value?.period ? new Date(`${data.value.period}-01T12:00:00`) : now,
  ),
);
const currency = computed(() => session.portfolio?.currency || "EUR");
const money = (value) =>
  new Intl.NumberFormat("es-ES", {
    style: "currency",
    currency: currency.value,
    maximumFractionDigits: 0,
  }).format(value || 0);
const percent = (value) =>
  value == null
    ? "—"
    : new Intl.NumberFormat("es-ES", { maximumFractionDigits: 2 }).format(
        value,
      ) + " %";
const steps = computed(() => [
  {
    done: Boolean(data.value?.properties.length),
    title: "Tu primera propiedad",
    detail: "Un nombre y una dirección para empezar.",
    to: "/properties?new=1",
    icon: Building2,
  },
  {
    done: Number(metrics.value.portfolio_value) > 0,
    title: "Pon valor a tu patrimonio",
    detail: "Añade una valoración estimada.",
    to: data.value?.properties[0]
      ? `/properties/${data.value.properties[0].id}`
      : "/properties?new=1",
    icon: BadgeEuro,
  },
  {
    done: Number(metrics.value.contracted_rent) > 0,
    title: "Tu primer alquiler",
    detail: "Asocia un contrato y su renta.",
    to: data.value?.properties.length ? "/leases?new=1" : "/properties?new=1",
    icon: KeyRound,
  },
]);
const nextStep = computed(() => steps.value.find((step) => !step.done));
const metricCards = computed(() => [
  {
    label: "Ingresos cobrados",
    value: money(metrics.value.monthly_income),
    detail: "Este mes",
    icon: ArrowDownLeft,
    tone: "income",
  },
  {
    label: "Gastos pagados",
    value: money(metrics.value.monthly_expenses),
    detail: "Este mes",
    icon: ArrowUpRight,
    tone: "expense",
  },
  {
    label: "Beneficio neto",
    value: money(metrics.value.net_profit),
    detail: "Ingresos menos gastos",
    icon: Wallet,
    tone: metrics.value.net_profit < 0 ? "expense" : "neutral",
  },
]);
let controller;
async function load() {
  controller?.abort();
  const current = new AbortController();
  controller = current;
  loading.value = true;
  error.value = "";
  try {
    data.value = (await api.get("/dashboard", { signal: current.signal })).data;
  } catch (exception) {
    if (exception.code !== "ERR_CANCELED")
      error.value =
        "No hemos podido preparar tu resumen. Comprueba la conexión y vuelve a intentarlo.";
  } finally {
    if (controller === current) loading.value = false;
  }
}
const changes = [
  "alquivo:finance-changed",
  "alquivo:leases-changed",
  "alquivo:properties-changed",
];
onMounted(() => {
  load();
  changes.forEach((event) => window.addEventListener(event, load));
});
onBeforeUnmount(() => {
  controller?.abort();
  changes.forEach((event) => window.removeEventListener(event, load));
});
</script>

<template>
  <main class="page dashboard-page">
    <header class="heading dashboard-heading" data-tour="summary">
      <div>
        <p class="eyebrow">Una mirada a tu patrimonio</p>
        <h1>
          {{ greeting }}, {{ session.user?.name?.split(" ")[0] || "bienvenido"
          }}<span class="greeting-dot">.</span>
        </h1>
        <p v-if="hasProperties">Lo esencial de tu cartera.</p>
      </div>
      <span v-if="hasProperties" class="dashboard-period"
        ><CalendarDays :size="15" />{{ month }}</span
      >
    </header>

    <section v-if="error" class="empty dashboard-error" role="alert">
      <CircleAlert :size="32" />
      <h2>Vamos a intentarlo de nuevo</h2>
      <p>{{ error }}</p>
      <button class="button secondary" @click="load">
        <RefreshCw :size="16" />Recargar resumen
      </button>
    </section>
    <section
      v-else-if="loading"
      class="dashboard-loading"
      aria-busy="true"
      aria-label="Cargando resumen"
    >
      <div></div>
      <div v-for="number in 4" :key="number"></div>
    </section>
    <template v-else-if="data">
      <section
        v-if="!hasProperties"
        class="dashboard-welcome"
        aria-labelledby="welcome-title"
      >
        <div class="welcome-start">
          <span class="welcome-icon"
            ><Building2 :size="26" aria-hidden="true"
          /></span>
          <p class="eyebrow">Tu primer paso</p>
          <h2 id="welcome-title">Empieza por tu primer inmueble</h2>
          <p>
            Con una dirección y un nombre podrás empezar a organizar tu
            patrimonio.
          </p>
          <div class="welcome-actions">
            <RouterLink class="button primary" to="/properties?new=1"
              >Añadir mi inmueble <ArrowRight :size="17"
            /></RouterLink>
            <button
              class="welcome-tour-link"
              type="button"
              @click="startTour()"
            >
              Ver recorrido de Alquivo
            </button>
          </div>
        </div>
        <div class="welcome-ai" data-tour="ai">
          <span class="welcome-ai-icon"
            ><Sparkles :size="22" aria-hidden="true"
          /></span>
          <p class="eyebrow">Alquivo AI</p>
          <h3>Tu cartera, en conversación</h3>
          <p>
            Cuando añadas datos, podrás preguntar por tus inmuebles, cobros y
            gastos.
          </p>
          <button
            v-if="product.accountFeatures.assistant"
            class="welcome-ai-link"
            type="button"
            @click="openAssistant()"
          >
            Conocer el asistente <ArrowRight :size="16" />
          </button>
          <span v-else class="welcome-ai-soon">Disponible próximamente</span>
        </div>
      </section>
      <template v-else>
        <section
          v-if="product.accountFeatures.assistant"
          class="dashboard-ai-intro is-live"
          data-tour="ai"
          aria-labelledby="dashboard-ai-title"
        >
          <span class="dashboard-ai-icon"
            ><Sparkles :size="23" aria-hidden="true"
          /></span>
          <div>
            <h2 id="dashboard-ai-title">Entiende tu patrimonio preguntando</h2>
            <p>
              Pregunta por cobros, gastos o contratos. Por ejemplo: «¿Cuánto he
              cobrado este mes?»
            </p>
          </div>
          <div class="dashboard-ai-action">
            <button
              class="button primary"
              type="button"
              @click="openAssistant()"
            >
              <Sparkles :size="17" aria-hidden="true" />
              Hablar con Alquivo
            </button>
          </div>
        </section>
        <section
          v-if="assistantNeedsIntroduction"
          class="dashboard-ai-intro"
          data-tour="ai"
          aria-labelledby="dashboard-ai-title"
        >
          <span class="dashboard-ai-icon"
            ><Sparkles :size="23" aria-hidden="true"
          /></span>
          <div>
            <p class="eyebrow">El siguiente paso de Alquivo</p>
            <h2 id="dashboard-ai-title">Tu asistente IA está en preparación</h2>
            <p>
              Cuando esté disponible, podrás consultar tu cartera en lenguaje
              sencillo.
            </p>
          </div>
          <span class="dashboard-ai-status">Próximamente</span>
        </section>
        <section
          v-if="nextStep"
          class="dashboard-next-step"
          aria-label="Siguiente paso"
        >
          <span class="next-step-icon"
            ><component :is="nextStep.icon" :size="20" aria-hidden="true"
          /></span>
          <div>
            <strong>Siguiente paso: {{ nextStep.title }}</strong
            ><span>{{ nextStep.detail }}</span>
          </div>
          <RouterLink :to="nextStep.to"
            >Ir ahora <ArrowRight :size="16"
          /></RouterLink>
        </section>

        <section class="wealth-card" aria-label="Valor del patrimonio">
          <div class="wealth-content">
            <span class="wealth-label"
              ><span></span>Tu patrimonio inmobiliario</span
            ><strong class="wealth-value">{{
              money(metrics.portfolio_value)
            }}</strong>
            <p>Valor estimado de tus propiedades</p>
            <div class="wealth-foot">
              <span
                >Patrimonio neto <b>{{ money(metrics.net_equity) }}</b></span
              ><RouterLink to="/properties"
                >Ver mi cartera <ArrowUpRight :size="16"
              /></RouterLink>
            </div>
          </div>
          <div class="wealth-yield">
            <span class="wealth-icon"
              ><TrendingUp :size="24" :stroke-width="1.5" /></span
            ><span>Rentabilidad bruta anual</span
            ><strong>{{ percent(metrics.gross_yield) }}</strong
            ><small>Renta contratada / valor estimado</small>
          </div>
          <svg
            class="wealth-lines"
            viewBox="0 0 440 280"
            fill="none"
            aria-hidden="true"
          >
            <path
              d="M100 290V162L194 125V290M194 125L250 155V290M250 290V67L331 35V290M331 35L389 69V290M28 290V216L100 189"
              stroke="currentColor"
              stroke-width="1.5"
            />
            <path
              d="M124 186L170 168M124 211L170 193M124 236L170 218M275 106L310 92M275 133L310 119M275 160L310 146M275 187L310 173M275 214L310 200"
              stroke="currentColor"
            />
          </svg>
        </section>

        <section
          class="metrics dashboard-metrics"
          aria-label="Indicadores del mes"
        >
          <article v-for="metric in metricCards" :key="metric.label">
            <div class="metric-top">
              <span>{{ metric.label }}</span
              ><span class="metric-icon" :class="metric.tone"
                ><component :is="metric.icon" :size="17"
              /></span>
            </div>
            <strong
              :class="{
                'negative-value':
                  metric.label === 'Beneficio neto' && metrics.net_profit < 0,
              }"
              >{{ metric.value }}</strong
            ><small>{{ metric.detail }}</small>
          </article>
        </section>

        <AttentionPanel
          v-if="data?.attention_summary?.total"
          :items="data.attention"
          :summary="data.attention_summary"
        />
      </template>
    </template>
  </main>
</template>
