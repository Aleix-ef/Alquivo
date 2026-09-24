<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from "vue";
import {
  Building2,
  BadgeEuro,
  KeyRound,
  Check,
  ArrowUpRight,
  ArrowDownLeft,
  ArrowRight,
  CalendarDays,
  Wallet,
  TrendingUp,
  CircleAlert,
  RefreshCw,
} from "@lucide/vue";
import api from "../api";
import { useSession } from "../session";
import PropertyImage from "../components/PropertyImage.vue";
import AttentionPanel from "../components/AttentionPanel.vue";
import "../dashboard.css";

const session = useSession();
const data = ref(null);
const error = ref("");
const loading = ref(true);
const metrics = computed(() => data.value?.metrics || {});
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
const completed = computed(
  () => steps.value.filter((step) => step.done).length,
);
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
  {
    label: "Ocupación",
    value: percent(metrics.value.occupancy_rate),
    detail: "Propiedades con alquiler activo",
    icon: KeyRound,
    tone: "neutral",
  },
]);
const cashflowMax = computed(() =>
  Math.max(
    Number(metrics.value.monthly_income || 0),
    Number(metrics.value.monthly_expenses || 0),
    1,
  ),
);
const cashflow = computed(() => [
  {
    label: "Ingresos",
    value: Number(metrics.value.monthly_income || 0),
    type: "income",
  },
  {
    label: "Gastos",
    value: Number(metrics.value.monthly_expenses || 0),
    type: "expense",
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
    <header class="heading dashboard-heading">
      <div>
        <p class="eyebrow">Una mirada a tu patrimonio</p>
        <h1>
          {{ greeting }}, {{ session.user?.name?.split(" ")[0] || "bienvenido"
          }}<span class="greeting-dot">.</span>
        </h1>
        <p>Lo importante de tu cartera, de un vistazo.</p>
      </div>
      <span class="dashboard-period"
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
      <section v-if="completed < steps.length" class="setup-panel">
        <header>
          <div>
            <p class="eyebrow">Empezamos juntos</p>
            <h2>Dale forma a tu cartera</h2>
          </div>
          <span class="setup-count">{{ completed }} de {{ steps.length }}</span>
        </header>
        <div
          class="setup-progress"
          role="progressbar"
          :aria-valuenow="completed"
          :aria-valuemax="steps.length"
          aria-valuemin="0"
          aria-label="Primeros pasos completados"
        >
          <span
            :style="{ width: `${(completed / steps.length) * 100}%` }"
          ></span>
        </div>
        <div class="setup-steps">
          <RouterLink
            v-for="step in steps"
            :key="step.title"
            :to="step.to"
            :class="{ done: step.done }"
            ><span
              ><Check v-if="step.done" :size="17" /><component
                :is="step.icon"
                v-else
                :size="17"
            /></span>
            <div>
              <strong>{{ step.title }}</strong
              ><small>{{ step.detail }}</small>
            </div></RouterLink
          >
        </div>
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
        v-if="data?.attention_summary"
        :items="data.attention"
        :summary="data.attention_summary"
      />

      <section class="dashboard-grid">
        <article class="panel cashflow-panel">
          <header class="dashboard-panel-heading">
            <div>
              <p class="eyebrow">Tus números</p>
              <h2>El balance de este mes</h2>
            </div>
            <RouterLink class="dashboard-text-link" to="/reports"
              >Ver informes <ArrowUpRight :size="15"
            /></RouterLink>
          </header>
          <div class="cashflow-net">
            <strong :class="{ 'negative-value': metrics.net_profit < 0 }">{{
              money(metrics.net_profit)
            }}</strong
            ><span>Resultado neto registrado</span>
          </div>
          <div class="cashflow-bars">
            <div v-for="item in cashflow" :key="item.type" class="cashflow-row">
              <div>
                <span><i :class="item.type"></i>{{ item.label }}</span
                ><strong>{{ money(item.value) }}</strong>
              </div>
              <div class="cashflow-track">
                <span
                  :class="item.type"
                  :style="{ width: `${(item.value / cashflowMax) * 100}%` }"
                ></span>
              </div>
            </div>
          </div>
          <div class="cashflow-foot">
            <span><KeyRound :size="15" />Renta mensual contratada</span
            ><strong>{{ money(metrics.contracted_rent) }}</strong>
          </div>
          <p class="cashflow-note">
            Solo se incluyen ingresos cobrados y gastos pagados de {{ month }}.
          </p>
        </article>
        <article class="panel dashboard-properties">
          <header class="dashboard-panel-heading">
            <div>
              <p class="eyebrow">Tus espacios</p>
              <h2>Tu cartera</h2>
            </div>
            <RouterLink class="dashboard-text-link" to="/properties"
              >Ver todas <ArrowRight :size="15"
            /></RouterLink>
          </header>
          <div v-if="data.properties.length" class="dashboard-property-list">
            <RouterLink
              v-for="property in data.properties.slice(0, 4)"
              :key="property.id"
              :to="`/properties/${property.id}`"
              ><div class="dashboard-property-image">
                <PropertyImage :property="property" :show-label="false" />
              </div>
              <div class="dashboard-property-name">
                <strong>{{ property.name }}</strong
                ><small>{{ property.city || "Ubicación por completar" }}</small>
              </div>
              <div class="dashboard-property-value">
                <strong>{{ money(property.current_value) }}</strong
                ><small :class="{ rented: property.leases?.length }">{{
                  property.leases?.length ? "Alquilada" : "Sin alquiler"
                }}</small>
              </div></RouterLink
            >
          </div>
          <div v-else class="empty">
            <Building2 :size="30" />
            <p>
              El próximo capítulo de tu patrimonio empieza con tu primera
              propiedad.
            </p>
            <RouterLink class="button secondary" to="/properties?new=1"
              >Añadir propiedad <ArrowRight :size="15"
            /></RouterLink>
          </div>
        </article>
      </section>
    </template>
  </main>
</template>
