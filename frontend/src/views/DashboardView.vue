<script setup>
import { ref, onMounted, onBeforeUnmount, computed, inject } from "vue";
import {
  Building2,
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
import { formatMoney } from "../money";
import AttentionPanel from "../components/AttentionPanel.vue";
import PortfolioOverviewScene from "../components/PortfolioOverviewScene.vue";
import { useProduct } from "../stores/product";
import "../dashboard.css";

const session = useSession();
const product = useProduct();
const openAssistant = inject("open-assistant", () => {});
const startTour = inject("start-tour", () => {});
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
const money = (value) => formatMoney(value, currency.value);
const percent = (value) =>
  value == null
    ? "—"
    : new Intl.NumberFormat("es-ES", { maximumFractionDigits: 2 }).format(
        value,
      ) + " %";
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
        <PortfolioOverviewScene
          :portfolio-id="session.portfolio?.id"
          :currency="currency"
        />

        <div class="dashboard-overview">
          <div class="dashboard-overview-finances">
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
                    >Patrimonio neto
                    <b>{{ money(metrics.net_equity) }}</b></span
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
              <section
                class="wealth-activity"
                aria-label="Actividad de los alquileres"
              >
                <div>
                  <span>Inmuebles alquilados</span>
                  <strong>{{ percent(metrics.occupancy_rate) }}</strong>
                  <progress
                    v-if="metrics.occupancy_rate != null"
                    :value="metrics.occupancy_rate"
                    max="100"
                    aria-label="Porcentaje de inmuebles con contrato activo"
                  ></progress>
                </div>
                <div>
                  <span>Renta contratada / mes</span>
                  <strong>{{
                    metrics.contracted_rent == null
                      ? "—"
                      : money(metrics.contracted_rent)
                  }}</strong>
                  <small>Según contratos activos</small>
                </div>
              </section>
              <section
                class="dashboard-metrics wealth-metrics"
                aria-label="Indicadores del mes"
              >
                <article v-for="metric in metricCards" :key="metric.label">
                  <div class="metric-top">
                    <span>{{ metric.label }}</span>
                    <span class="metric-icon" :class="metric.tone">
                      <component :is="metric.icon" :size="17" />
                    </span>
                  </div>
                  <strong
                    :class="{
                      'negative-value':
                        metric.label === 'Beneficio neto' &&
                        metrics.net_profit < 0,
                    }"
                    >{{ metric.value }}</strong
                  >
                  <small>{{ metric.detail }}</small>
                </article>
              </section>
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
          </div>
          <AttentionPanel
            v-if="data.attention_summary"
            compact
            :items="data.attention"
            :summary="data.attention_summary"
          />
        </div>
      </template>
    </template>
  </main>
</template>
