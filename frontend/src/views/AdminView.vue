<script setup>
import { computed, ref, watch, onBeforeUnmount } from "vue";
import { useRoute, useRouter } from "vue-router";
import {
  ShieldCheck,
  LockKeyhole,
  RefreshCw,
  ArrowRight,
  Info,
} from "@lucide/vue";
import api from "../api";
import {
  adminSections,
  adminSection,
  count,
  nanoUsd,
  storage,
  adminDate,
  recentRange,
  chartBuckets,
} from "../adminMetrics";
import "../admin.css";
const route = useRoute(),
  router = useRouter();
const section = computed(() => adminSection(route.query.section));
const access = ref(null),
  denied = ref(false),
  busy = ref(false),
  error = ref(""),
  notice = ref("");
const metrics = ref(null),
  rows = ref(null),
  tableBusy = ref(false),
  search = ref("");
const range = ref(recentRange()),
  includeSelf = ref(false);
const code = ref(""),
  emailCode = ref(""),
  recovery = ref(false);
let controller, tableController, expiryTimer;
const verified = computed(() => access.value?.verified === true);
const buckets = computed(() => chartBuckets(metrics.value?.users.timeline));
const peak = computed(() =>
  Math.max(1, ...buckets.value.map((bucket) => bucket.count)),
);
function params() {
  return { ...range.value, include_self: includeSelf.value ? 1 : 0 };
}
function resetData() {
  controller?.abort();
  tableController?.abort();
  clearTimeout(expiryTimer);
  metrics.value = null;
  rows.value = null;
}
function handleError(e) {
  if (e.code === "ERR_CANCELED") return;
  if (e.response?.status === 401 || e.response?.status === 403) {
    resetData();
    if (e.response?.data?.code === "admin_verification_required")
      access.value = { ...access.value, verified: false };
    else {
      denied.value = true;
      access.value = null;
    }
  }
  error.value =
    e.response?.status === 429
      ? "Demasiadas peticiones. Espera un momento antes de reintentarlo."
      : Object.values(e.response?.data?.errors || {}).flat()[0] ||
        e.response?.data?.message ||
        "No se ha podido cargar el panel. Vuelve a intentarlo.";
}
async function loadAccess() {
  busy.value = true;
  error.value = "";
  controller = new AbortController();
  try {
    access.value = (
      await api.get("/admin/access", { signal: controller.signal })
    ).data;
    if (verified.value) await loadMetrics();
  } catch (e) {
    handleError(e);
  } finally {
    busy.value = false;
  }
}
async function sendCode() {
  busy.value = true;
  error.value = "";
  notice.value = "";
  try {
    notice.value = (await api.post("/admin/access/code")).data.message;
  } catch (e) {
    handleError(e);
  } finally {
    busy.value = false;
  }
}
async function unlock() {
  busy.value = true;
  error.value = "";
  notice.value = "";
  try {
    await api.post("/admin/access/verify", {
      code: code.value.trim(),
      ...(emailCode.value ? { email_code: emailCode.value.trim() } : {}),
      recovery: recovery.value,
    });
    code.value = "";
    emailCode.value = "";
    await loadAccess();
  } catch (e) {
    handleError(e);
  } finally {
    busy.value = false;
  }
}
async function lock() {
  if (busy.value) return;
  busy.value = true;
  error.value = "";
  try {
    await api.delete("/admin/access");
    resetData();
    access.value = { ...access.value, verified: false };
  } catch (e) {
    handleError(e);
  } finally {
    busy.value = false;
  }
}
async function loadMetrics() {
  if (!verified.value) return;
  controller?.abort();
  tableController?.abort();
  controller = new AbortController();
  const current = controller;
  busy.value = true;
  error.value = "";
  metrics.value = null;
  rows.value = null;
  try {
    const response = await api.get("/admin/overview", {
      params: params(),
      signal: current.signal,
    });
    if (current.signal.aborted) return;
    metrics.value = response.data;
    // Use the server's actual window; filters never silently label another range.
    range.value = {
      from: response.data.range.from,
      to: response.data.range.to,
    };
    clearTimeout(expiryTimer);
    expiryTimer = setTimeout(
      () => {
        resetData();
        access.value = { ...access.value, verified: false };
      },
      Math.max(0, access.value.expires_at * 1000 - Date.now()),
    );
    await loadRows();
  } catch (e) {
    handleError(e);
  } finally {
    if (controller === current) busy.value = false;
  }
}
async function loadRows(page = 1) {
  tableController?.abort();
  rows.value = null;
  if (
    !verified.value ||
    !metrics.value ||
    !["users", "ai"].includes(section.value)
  )
    return;
  tableController = new AbortController();
  const current = tableController;
  tableBusy.value = true;
  try {
    const response = await api.get(
      section.value === "ai" ? "/admin/ai/users" : "/admin/users",
      {
        params: {
          ...metrics.value.range,
          include_self: metrics.value.range.include_self ? 1 : 0,
          page,
          ...(section.value === "users" ? { q: search.value } : {}),
        },
        signal: current.signal,
      },
    );
    if (!current.signal.aborted) rows.value = response.data;
  } catch (e) {
    handleError(e);
  } finally {
    if (tableController === current) tableBusy.value = false;
  }
}
function preset(days) {
  range.value = recentRange(days);
  loadMetrics();
}
function choose(key) {
  router.replace({ query: { section: key } });
}
watch(section, () => {
  error.value = "";
  loadRows();
});
watch(recovery, () => {
  code.value = "";
  emailCode.value = "";
  error.value = "";
  notice.value = "";
});
onBeforeUnmount(resetData);
loadAccess();
</script>

<template>
  <div class="admin-page">
    <header class="admin-heading">
      <div>
        <p class="admin-eyebrow"><ShieldCheck :size="16" /> Espacio privado</p>
        <h1>Administración</h1>
        <p>Una visión clara de cómo crece Alquivo.</p>
      </div>
      <button
        v-if="verified"
        class="admin-button"
        :disabled="busy"
        @click="lock"
      >
        <LockKeyhole :size="16" /> Bloquear panel
      </button>
    </header>
    <p v-if="error" class="error admin-message" role="alert">{{ error }}</p>
    <section v-if="denied" class="admin-panel admin-gate">
      <LockKeyhole :size="28" />
      <h2>Acceso restringido</h2>
      <p>
        Esta cuenta no tiene autorización administrativa. El acceso no se
        solicita ni se concede desde la aplicación.
      </p>
      <RouterLink to="/dashboard" class="admin-button"
        >Volver al resumen</RouterLink
      >
    </section>
    <section v-else-if="access && !verified" class="admin-panel admin-gate">
      <ShieldCheck :size="28" />
      <h2>Verifica tu acceso</h2>
      <p>
        Los datos del negocio requieren una verificación reciente, incluso en un
        dispositivo recordado. El panel se bloqueará tras
        {{ access.verification_minutes }} minutos.
      </p>
      <form @submit.prevent="unlock" class="admin-unlock">
        <template v-if="!recovery && ['email', 'both'].includes(access.method)">
          <button
            type="button"
            class="admin-button"
            :disabled="busy || !access.email_available"
            @click="sendCode"
          >
            Enviar código al correo
          </button>
          <p v-if="!access.email_available" class="error">
            El correo no está disponible. Puedes utilizar un código de
            recuperación.
          </p>
        </template>
        <p v-if="notice" role="status">{{ notice }}</p>
        <label for="admin-code"
          >{{
            recovery
              ? "Código de recuperación"
              : access.method === "email"
                ? "Código del correo"
                : "Código de la aplicación de autenticación"
          }}<input
            id="admin-code"
            v-model="code"
            :inputmode="recovery ? 'text' : 'numeric'"
            :maxlength="recovery ? 40 : 6"
            autocomplete="one-time-code"
            required
            :disabled="busy"
        /></label>
        <label
          v-if="!recovery && access.method === 'both'"
          for="admin-email-code"
          >Código del correo<input
            id="admin-email-code"
            v-model="emailCode"
            inputmode="numeric"
            maxlength="6"
            autocomplete="off"
            required
            :disabled="busy"
        /></label>
        <button class="admin-button primary" :disabled="busy">
          {{ busy ? "Verificando…" : "Entrar al panel"
          }}<ArrowRight :size="16" />
        </button>
        <button
          type="button"
          class="admin-text-button"
          :disabled="busy"
          @click="recovery = !recovery"
        >
          {{
            recovery
              ? "Volver al segundo factor"
              : "Usar un código de recuperación"
          }}
        </button>
      </form>
    </section>
    <template v-else-if="verified">
      <form class="admin-panel admin-filters" @submit.prevent="loadMetrics">
        <div class="admin-presets">
          <button
            v-for="days in [7, 30, 90]"
            :key="days"
            type="button"
            class="admin-button"
            :disabled="busy"
            @click="preset(days)"
          >
            {{ days }} días
          </button>
        </div>
        <label
          >Desde<input
            v-model="range.from"
            type="date"
            required
            :max="range.to"
            :disabled="busy"
        /></label>
        <label
          >Hasta<input
            v-model="range.to"
            type="date"
            required
            :min="range.from"
            :max="recentRange().to"
            :disabled="busy"
        /></label>
        <label class="admin-check"
          ><input v-model="includeSelf" type="checkbox" :disabled="busy" />
          Incluir mi cuenta</label
        >
        <button class="admin-button primary" :disabled="busy">
          <RefreshCw :size="15" />{{ busy ? "Cargando…" : "Aplicar" }}
        </button>
      </form>
      <nav class="admin-tabs" aria-label="Secciones de administración">
        <button
          v-for="[key, label] in adminSections"
          :key="key"
          :aria-current="section === key ? 'page' : undefined"
          @click="choose(key)"
        >
          {{ label }}
        </button>
      </nav>
      <p v-if="busy && !metrics" role="status">Cargando métricas…</p>
      <template v-if="metrics">
        <div class="admin-context">
          <span>{{ metrics.population_note }}</span
          ><span
            >Actualizado {{ adminDate(metrics.generated_at) }} · caché de hasta
            60 s</span
          >
        </div>
        <template v-if="section === 'users'">
          <div class="admin-kpis">
            <article>
              <span>Cuentas actuales</span
              ><strong>{{ count(metrics.users.total_existing) }}</strong
              ><small>Fotografía de hoy</small>
            </article>
            <article>
              <span>Registros en el periodo</span
              ><strong>{{ count(metrics.users.registered_in_range) }}</strong
              ><small>Cuentas que siguen existiendo</small>
            </article>
            <article>
              <span>Correo verificado</span
              ><strong>{{ count(metrics.users.verified_existing) }}</strong
              ><small>Cuentas actuales</small>
            </article>
            <article>
              <span>Activos / bajas</span><strong>—</strong
              ><small>Sin instrumentación fiable</small>
            </article>
          </div>
          <section class="admin-panel">
            <h2>Nuevos registros</h2>
            <p>{{ metrics.users.note }}</p>
            <div
              class="admin-chart"
              role="img"
              :aria-label="`${count(metrics.users.registered_in_range)} registros conservados, del ${adminDate(range.from)} al ${adminDate(range.to)}`"
            >
              <div
                v-for="bucket in buckets"
                :key="bucket.from"
                :style="{
                  height: `${Math.max(2, (bucket.count / peak) * 100)}%`,
                }"
                :class="{ 'is-zero': !bucket.count }"
                :title="`${adminDate(bucket.from)}${bucket.to !== bucket.from ? ' – ' + adminDate(bucket.to) : ''}: ${count(bucket.count)}`"
              ></div>
            </div>
            <div class="admin-chart-axis">
              <span>{{ adminDate(metrics.range.from) }}</span
              ><span>{{ adminDate(metrics.range.to) }}</span>
            </div>
          </section>
          <section class="admin-panel">
            <div class="admin-section-heading">
              <h2>Usuarios registrados en el periodo</h2>
              <form @submit.prevent="loadRows(1)" class="admin-search">
                <label class="sr-only" for="admin-search"
                  >Buscar por nombre o correo</label
                ><input
                  id="admin-search"
                  v-model="search"
                  type="search"
                  placeholder="Nombre o correo"
                  maxlength="100"
                /><button class="admin-button" :disabled="tableBusy">
                  Buscar
                </button>
              </form>
            </div>
            <p>
              Solo identificación básica y verificación. Sin contratos,
              documentos ni conversaciones.
            </p>
            <p v-if="tableBusy" role="status">Cargando usuarios…</p>
            <div v-else-if="rows" class="admin-table-wrap">
              <table>
                <caption class="sr-only">
                  Usuarios registrados en el periodo
                </caption>
                <thead>
                  <tr>
                    <th>Usuario</th>
                    <th>Correo</th>
                    <th>Alta</th>
                    <th>Verificación</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="user in rows.data" :key="user.id">
                    <td>{{ user.name }}</td>
                    <td>{{ user.email }}</td>
                    <td>{{ adminDate(user.registered_at) }}</td>
                    <td>
                      <span class="admin-badge">{{
                        user.verified ? "Verificado" : "Pendiente"
                      }}</span>
                    </td>
                  </tr>
                  <tr v-if="!rows.data.length">
                    <td colspan="4">
                      No hay usuarios que coincidan con estos filtros.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </template>
        <template v-if="section === 'plans'"
          ><section class="admin-panel">
            <h2>Acceso efectivo por plan</h2>
            <p>{{ metrics.plans.note }}</p>
            <div class="admin-plan-grid">
              <article
                v-for="plan in metrics.plans.distribution"
                :key="plan.code"
              >
                <span class="admin-badge">{{
                  plan.code === "beta"
                    ? "Beta gratuita"
                    : plan.code === "free"
                      ? "Gratuito"
                      : "Plan de catálogo"
                }}</span>
                <h3>{{ plan.name }}</h3>
                <strong>{{ count(plan.portfolios) }}</strong
                ><span> carteras</span>
              </article>
            </div>
            <p v-if="!metrics.plans.distribution.length">
              No hay carteras en esta población.
            </p>
            <p class="admin-note">
              <Info :size="16" />{{
                metrics.plans.billing_enabled
                  ? "Facturación habilitada. Tener un plan de catálogo no demuestra un pago recibido."
                  : "Beta sin pagos: no se contabilizan planes de catálogo como clientes de pago."
              }}
            </p>
            <div class="admin-unavailable">
              <h3>Conversiones y cambios históricos</h3>
              <p>
                No disponibles. No existe un historial completo y no se deducen
                de la configuración actual.
              </p>
            </div>
          </section></template
        >
        <template v-if="section === 'activation'"
          ><section class="admin-panel">
            <h2>Activación actual</h2>
            <p>{{ metrics.activation.note }}</p>
            <div class="admin-activation">
              <article
                v-for="[key, label] in [
                  ['with_property', 'Con algún inmueble'],
                  ['with_active_lease', 'Con alquiler activo'],
                  ['with_recorded_payment', 'Con cobro registrado'],
                  ['with_expense', 'Con gasto pagado'],
                ]"
                :key="key"
              >
                <div>
                  <span>{{ label }}</span
                  ><strong
                    >{{ count(metrics.activation[key]) }} /
                    {{ count(metrics.activation.eligible_users) }}</strong
                  >
                </div>
                <meter
                  :value="metrics.activation[key]"
                  :max="Math.max(1, metrics.activation.eligible_users)"
                  :aria-label="label"
                  min="0"
                ></meter>
              </article>
            </div>
            <p>
              Son hitos independientes; no implican pasos consecutivos ni
              actividad reciente.
            </p>
          </section></template
        >
        <template v-if="section === 'ai'">
          <div class="admin-kpis">
            <article>
              <span>Consultas / ejecuciones</span
              ><strong>{{ count(metrics.ai.runs) }}</strong
              ><small>{{ count(metrics.ai.users) }} usuarios</small>
            </article>
            <article>
              <span>Coste conocido estimado</span
              ><strong class="admin-money">{{
                nanoUsd(metrics.ai.known_cost_nano_usd)
              }}</strong
              ><small>USD · no factura del proveedor</small>
            </article>
            <article>
              <span>Tokens entrada / salida</span
              ><strong class="admin-money"
                >{{ count(metrics.ai.input_tokens) }} /
                {{ count(metrics.ai.output_tokens) }}</strong
              ><small>Solo ejecuciones conservadas</small>
            </article>
            <article>
              <span>Ejecuciones fallidas</span
              ><strong>{{ count(metrics.ai.failed) }}</strong
              ><small>{{ count(metrics.ai.processing) }} en proceso</small>
            </article>
          </div>
          <section class="admin-panel">
            <h2>Consumo por modelo</h2>
            <p>{{ metrics.ai.note }}</p>
            <p class="admin-note">
              <Info :size="16" />{{
                count(metrics.ai.incomplete_cost_runs)
              }}
              ejecuciones con coste incompleto ·
              {{ nanoUsd(metrics.ai.reserved_cost_nano_usd) }} reservados, no
              gasto confirmado ·
              {{ count(metrics.ai.failed_provider_calls) }} llamadas al
              proveedor fallidas.
            </p>
            <div class="admin-table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Modelo</th>
                    <th>Llamadas</th>
                    <th>Tokens entrada / salida</th>
                    <th>Entrada en caché</th>
                    <th>Coste conocido (USD)</th>
                    <th>Costes sin conocer</th>
                    <th>Latencia media</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="model in metrics.ai.by_model" :key="model.model">
                    <td>{{ model.model }}</td>
                    <td>{{ count(model.calls) }}</td>
                    <td>
                      {{ count(model.input_tokens) }} /
                      {{ count(model.output_tokens) }}
                    </td>
                    <td>{{ count(model.cached_input_tokens) }}</td>
                    <td>{{ nanoUsd(model.known_cost_nano_usd) }}</td>
                    <td>{{ count(model.unknown_cost_calls) }} llamadas</td>
                    <td>
                      {{
                        model.latency_ms == null
                          ? "—"
                          : count(model.latency_ms) + " ms"
                      }}
                    </td>
                  </tr>
                  <tr v-if="!metrics.ai.by_model.length">
                    <td colspan="7">
                      No hay llamadas al proveedor registradas en este periodo.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p>
              Conservación de métricas:
              {{ metrics.ai.coverage.retention_months }} meses. Primera
              ejecución conservada:
              {{ adminDate(metrics.ai.coverage.earliest_retained_run) }}.
            </p>
            <p
              v-if="metrics.ai.coverage.requested_before_retention"
              class="admin-note"
            >
              Parte del periodo seleccionado precede a la ventana de
              conservación.
            </p>
          </section>
          <section class="admin-panel">
            <h2>Consumo por usuario</h2>
            <p>
              Solo metadatos agregados de uso. No se muestran preguntas ni
              respuestas.
            </p>
            <p v-if="tableBusy" role="status">Cargando consumo…</p>
            <div v-else-if="rows" class="admin-table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Usuario</th>
                    <th>Consultas</th>
                    <th>Tokens entrada / salida</th>
                    <th>Coste conocido (USD)</th>
                    <th>Costes incompletos</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="user in rows.data" :key="user.id">
                    <td>
                      {{ user.name
                      }}<small class="admin-cell-email">{{ user.email }}</small>
                    </td>
                    <td>{{ count(user.runs) }}</td>
                    <td>
                      {{ count(user.input_tokens) }} /
                      {{ count(user.output_tokens) }}
                    </td>
                    <td>{{ nanoUsd(user.known_cost_nano_usd) }}</td>
                    <td>{{ count(user.incomplete_cost_runs) }}</td>
                  </tr>
                  <tr v-if="!rows.data.length">
                    <td colspan="5">
                      No hay consumo conservado en este periodo.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </template>
        <template v-if="section === 'usage'"
          ><section class="admin-panel">
            <h2>Uso actual del producto</h2>
            <p>{{ metrics.usage.note }}</p>
            <div class="admin-kpis">
              <article
                v-for="[key, label] in [
                  ['portfolios', 'Carteras'],
                  ['properties', 'Inmuebles'],
                  ['active_leases', 'Alquileres activos'],
                  ['documents', 'Documentos'],
                ]"
                :key="key"
              >
                <span>{{ label }}</span
                ><strong>{{ count(metrics.usage[key]) }}</strong>
              </article>
              <article>
                <span>Almacenamiento de cuota</span
                ><strong>{{ storage(metrics.usage.storage_bytes) }}</strong>
              </article>
              <article>
                <span>Unidades alquilables</span><strong>—</strong
                ><small>Modelo aún no disponible</small>
              </article>
            </div>
          </section></template
        >
        <section
          v-if="['revenue', 'retention', 'acquisition'].includes(section)"
          class="admin-panel admin-unavailable"
        >
          <Info :size="26" />
          <h2>
            {{
              {
                revenue: "Ingresos de Alquivo",
                retention: "Retención",
                acquisition: "Origen de los registros",
              }[section]
            }}
          </h2>
          <span class="admin-badge">Métrica no disponible</span>
          <p>{{ metrics[section].note }}</p>
          <p v-if="section === 'revenue'">
            MRR, ingresos cobrados y cancelaciones: sin datos suficientes. No se
            muestran como cero.
          </p>
        </section>
        <nav
          v-if="rows && ['users', 'ai'].includes(section)"
          class="admin-pagination"
          aria-label="Paginación"
        >
          <button
            class="admin-button"
            :disabled="tableBusy || rows.page <= 1"
            @click="loadRows(rows.page - 1)"
          >
            Anterior</button
          ><span
            >{{ count(rows.total) }} resultados · {{ rows.page }} /
            {{ rows.last_page }}</span
          ><button
            class="admin-button"
            :disabled="tableBusy || rows.page >= rows.last_page"
            @click="loadRows(rows.page + 1)"
          >
            Siguiente
          </button>
        </nav>
        <p class="admin-footnote">
          El periodo filtra registros y uso de IA. Planes, activación y uso son
          fotografías actuales. Acceso de solo lectura, registrado en la
          auditoría de seguridad.
        </p>
      </template>
    </template>
    <p v-else-if="busy" role="status">Comprobando autorización…</p>
    <button v-else-if="!denied" class="admin-button" @click="loadAccess">
      Reintentar
    </button>
  </div>
</template>
