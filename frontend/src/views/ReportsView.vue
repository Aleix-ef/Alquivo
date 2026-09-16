<script setup>
import { computed, onMounted, ref } from "vue";
import { Download, TrendingUp } from "@lucide/vue";
import api from "../api";

const report = ref(null);
const months = ref(12);
const downloading = ref("");
const loading = ref(true);
const error = ref("");
const maxMovement = computed(() =>
  Math.max(
    1,
    ...(report.value?.series || []).flatMap((item) => [
      item.income,
      item.expenses,
    ]),
  ),
);
const money = (value) =>
  new Intl.NumberFormat("es-ES", {
    style: "currency",
    currency: report.value?.currency || "EUR",
    maximumFractionDigits: 0,
  }).format(value || 0);
async function load() {
  loading.value = true;
  error.value = "";
  try {
    report.value = (
      await api.get("/reports/overview", { params: { months: months.value } })
    ).data;
  } catch {
    error.value = "No hemos podido preparar el informe.";
  } finally {
    loading.value = false;
  }
}
async function download(resource) {
  downloading.value = resource;
  try {
    const response = await api.get(`/exports/${resource}`, {
      responseType: "blob",
    });
    const url = URL.createObjectURL(response.data);
    const link = document.createElement("a");
    link.href = url;
    link.download = `alquivo-${resource}.csv`;
    link.click();
    URL.revokeObjectURL(url);
  } catch {
    error.value = "No se pudo descargar la exportación.";
  } finally {
    downloading.value = "";
  }
}
onMounted(load);
</script>

<template>
  <main class="page">
    <header class="heading">
      <div>
        <p class="eyebrow">Informes</p>
        <h1>La evolución de tu patrimonio</h1>
        <p>Una lectura financiera clara, sin convertirlo en contabilidad.</p>
      </div>
      <label class="period-select"
        >Periodo<select v-model="months" @change="load">
          <option :value="3">3 meses</option>
          <option :value="6">6 meses</option>
          <option :value="12">12 meses</option>
          <option :value="24">24 meses</option>
        </select></label
      >
    </header>
    <div v-if="loading" class="empty" role="status">Preparando el informe…</div>
    <div v-else-if="error" class="empty" role="alert">
      <p>{{ error }}</p>
      <button class="button secondary" @click="load">Volver a intentar</button>
    </div>
    <template v-else>
      <section class="finance-summary">
        <article>
          <span>Ingresos confirmados</span
          ><strong>{{ money(report.totals.income) }}</strong>
        </article>
        <article>
          <span>Gastos confirmados</span
          ><strong>{{ money(report.totals.expenses) }}</strong>
        </article>
        <article>
          <span>Resultado neto</span
          ><strong>{{ money(report.totals.net) }}</strong>
        </article>
        <article>
          <span>Periodo</span><strong>{{ months }} meses</strong>
        </article>
      </section>
      <section class="panel report-chart">
        <div class="report-title">
          <div>
            <p class="eyebrow">Flujo mensual</p>
            <h2>Ingresos frente a gastos</h2>
          </div>
          <TrendingUp :size="22" />
        </div>
        <div class="bar-chart">
          <article v-for="item in report.series" :key="item.period">
            <div class="bars">
              <span
                class="income-bar"
                :style="{
                  height: `${Math.max(2, (item.income / maxMovement) * 100)}%`,
                }"
                :title="money(item.income)"
              ></span
              ><span
                class="expense-bar"
                :style="{
                  height: `${Math.max(2, (item.expenses / maxMovement) * 100)}%`,
                }"
                :title="money(item.expenses)"
              ></span>
            </div>
            <small>{{ item.label }}</small>
          </article>
        </div>
        <footer class="chart-legend">
          <span><i class="income-bar"></i>Ingresos</span
          ><span><i class="expense-bar"></i>Gastos</span>
        </footer>
      </section>
      <section class="panel report-properties">
        <p class="eyebrow">Por activo</p>
        <h2>Rendimiento de propiedades</h2>
        <div v-if="report.properties.length" class="performance-list">
          <RouterLink
            v-for="property in report.properties"
            :key="property.id"
            :to="`/properties/${property.id}`"
            ><div>
              <strong>{{ property.name }}</strong
              ><small
                >Valor {{ money(property.value) }} · Rentabilidad
                {{
                  property.gross_yield === null
                    ? "—"
                    : `${property.gross_yield}%`
                }}</small
              >
            </div>
            <div>
              <strong>{{ money(property.net) }}</strong
              ><small>Resultado del periodo</small>
            </div></RouterLink
          >
        </div>
        <div v-else class="empty">
          Añade propiedades para comparar su rendimiento.
        </div>
      </section>
      <section class="export-grid">
        <article>
          <div>
            <strong>Propiedades</strong><small>Valores, compra y deuda</small>
          </div>
          <button class="button secondary" @click="download('properties')">
            <Download :size="15" />{{
              downloading === "properties" ? "Preparando…" : "CSV"
            }}
          </button>
        </article>
        <article>
          <div>
            <strong>Contratos</strong><small>Inquilinos y condiciones</small>
          </div>
          <button class="button secondary" @click="download('leases')">
            <Download :size="15" />{{
              downloading === "leases" ? "Preparando…" : "CSV"
            }}
          </button>
        </article>
        <article>
          <div>
            <strong>Movimientos</strong
            ><small>Ingresos y gastos completos</small>
          </div>
          <button class="button secondary" @click="download('transactions')">
            <Download :size="15" />{{
              downloading === "transactions" ? "Preparando…" : "CSV"
            }}
          </button>
        </article>
      </section>
    </template>
  </main>
</template>
