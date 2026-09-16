<script setup>
import { computed, onMounted, onBeforeUnmount, ref, watch } from "vue";
import { onBeforeRouteLeave, useRoute } from "vue-router";
import {
  Download,
  FileCheck2,
  Building2,
  LockKeyhole,
  RefreshCw,
} from "@lucide/vue";
import { useFiscality } from "../stores/fiscality";
import { useSession } from "../session";
import { fiscalMoney } from "../fiscality";
import FiscalPropertyForm from "../components/FiscalPropertyForm.vue";
import "../fiscality.css";

const store = useFiscality();
const route = useRoute();
const session = useSession();
const year = ref(2025);
const selectedId = ref(Number(route.query.property) || null);
const saving = ref(false);
const dirty = ref(false);
const fieldErrors = ref({});
const notice = ref("");
const error = ref("");
const profile = ref({ name: "", regime: "unknown" });
const dossier = computed(() => store.data?.dossier);
const selected = computed(() =>
  dossier.value?.properties.find(
    (item) => item.property.id === selectedId.value,
  ),
);
const profileDirty = computed(
  () =>
    dossier.value &&
    (profile.value.name !== dossier.value.profile.name ||
      profile.value.regime !== dossier.value.profile.regime),
);
const unsaved = computed(() => dirty.value || profileDirty.value);
watch(
  () => dossier.value?.profile_revision,
  () => {
    if (dossier.value) profile.value = { ...dossier.value.profile };
  },
);
watch(
  () => dossier.value?.properties,
  (items) => {
    if (
      items?.length &&
      !items.some((item) => item.property.id === selectedId.value)
    )
      selectedId.value = items[0].property.id;
  },
);
function selectProperty(id) {
  if (id === selectedId.value) return;
  if (dirty.value) {
    error.value =
      "Guarda o descarta los cambios de la ficha antes de cambiar de inmueble.";
    return;
  }
  selectedId.value = id;
  error.value = "";
  fieldErrors.value = {};
}
function message(exception) {
  return (
    exception.fiscalMessage ||
    exception.response?.data?.message ||
    "No se ha podido completar la acción. Puedes volver a intentarlo."
  );
}
async function load() {
  try {
    await store.load(year.value);
  } catch {
    /* Store shows the error. */
  }
}
async function saveProfile() {
  saving.value = true;
  error.value = "";
  fieldErrors.value = {};
  try {
    await store.saveProfile(year.value, {
      ...profile.value,
      revision: dossier.value.profile_revision,
    });
    notice.value = "Perfil fiscal guardado.";
  } catch (exception) {
    error.value = message(exception);
  } finally {
    saving.value = false;
  }
}
async function saveProperty(inputs) {
  saving.value = true;
  error.value = "";
  fieldErrors.value = {};
  try {
    await store.saveProperty(
      year.value,
      selectedId.value,
      selected.value.revision,
      inputs,
    );
    dirty.value = false;
    notice.value =
      "Ficha fiscal guardada. El resumen ya refleja los datos revisados.";
  } catch (exception) {
    fieldErrors.value = exception.response?.data?.errors || {};
    error.value = message(exception);
  } finally {
    saving.value = false;
  }
}
async function download(format, propertyId = null, snapshotId = null) {
  if (unsaved.value && !snapshotId) {
    error.value = "Guarda o descarta los cambios antes de crear el dossier.";
    return;
  }
  error.value = "";
  try {
    await store.download(year.value, format, propertyId, snapshotId);
  } catch (exception) {
    error.value = message(exception);
  }
}
onBeforeRouteLeave(() => {
  if (!session.ready) return true;
  if (saving.value || unsaved.value) {
    error.value = "Guarda o descarta los cambios antes de salir de Fiscalidad.";
    window.scrollTo({ top: 0, behavior: "smooth" });
    return false;
  }
});
onMounted(load);
onBeforeUnmount(() => store.clear());
</script>

<template>
  <main class="page fiscal-page">
    <header class="heading">
      <div>
        <p class="eyebrow">Fiscalidad · Plan Fundador</p>
        <h1>La renta de tus inmuebles, preparada</h1>
        <p>
          Reúne tus cifras, revisa lo que falta y prepara el dossier para tu
          gestor.
        </p>
      </div>
      <span class="fiscal-year">Ejercicio {{ year }} <small>Beta</small></span>
    </header>
    <div v-if="error || store.error" class="fiscal-error" role="alert">
      {{ error || store.error
      }}<button v-if="store.error" class="button secondary" @click="load">
        <RefreshCw :size="16" />Reintentar
      </button>
    </div>
    <p v-if="notice" class="fiscal-success" role="status">{{ notice }}</p>
    <section v-if="store.loading && !store.data" class="empty" aria-busy="true">
      Preparando tu espacio fiscal…
    </section>
    <section
      v-else-if="store.data && !store.data.access"
      class="panel fiscal-locked"
    >
      <LockKeyhole :size="30" />
      <h2>Tu dossier fiscal, incluido en el Plan Fundador</h2>
      <p>
        Ficha anual por inmueble, cálculo explicado y descarga en PDF o CSV.
        Disponible también durante la prueba.
      </p>
      <RouterLink class="button" to="/plans">Ver planes</RouterLink>
    </section>
    <template v-if="dossier">
      <div class="fiscal-intro">
        <FileCheck2 :size="23" />
        <p>
          <strong>Primera versión: ejercicio 2025.</strong> Cubre los supuestos
          residenciales indicados en la ficha. Los informes son borradores para
          revisión. La simulación de tu IRPF personal llegará en la siguiente
          fase.
        </p>
      </div>
      <form class="panel fiscal-profile" @submit.prevent="saveProfile">
        <div>
          <h2>Tu perfil fiscal</h2>
          <p>
            El dossier se prepara para este contribuyente. No necesitas indicar
            tu sueldo.
          </p>
        </div>
        <label
          >Nombre del contribuyente<input
            v-model="profile.name"
            required
            maxlength="100"
            :disabled="saving"
        /></label>
        <label
          >Régimen fiscal<select v-model="profile.regime" :disabled="saving">
            <option value="unknown">Selecciona tu situación</option>
            <option value="common">
              Persona física residente · IRPF régimen común
            </option>
            <option value="foral">Régimen foral (País Vasco o Navarra)</option>
            <option value="non_resident">No residente en España</option>
            <option value="company">Sociedad</option>
          </select></label
        >
        <div class="fiscal-actions">
          <button class="button secondary" :disabled="saving || !profileDirty">
            Guardar perfil</button
          ><button
            v-if="profileDirty"
            class="button secondary"
            type="button"
            @click="profile = { ...dossier.profile }"
          >
            Descartar
          </button>
        </div>
      </form>
      <section class="fiscal-summary">
        <article>
          <span>Ingresos fiscales atribuibles</span
          ><strong>{{ fiscalMoney(dossier.totals.income) }}</strong>
        </article>
        <article>
          <span>Gastos y amortización</span
          ><strong>{{
            fiscalMoney(
              dossier.totals.expenses == null
                ? null
                : dossier.totals.expenses + dossier.totals.depreciation,
            )
          }}</strong>
        </article>
        <article>
          <span>Rendimiento neto reducido</span
          ><strong>{{ fiscalMoney(dossier.totals.reduced_net) }}</strong>
        </article>
        <article>
          <span>Rentas imputadas</span
          ><strong>{{ fiscalMoney(dossier.totals.imputed) }}</strong>
        </article>
      </section>
      <div class="fiscal-toolbar">
        <p>
          <strong
            >{{ dossier.calculated_count }} de
            {{ dossier.property_count }}</strong
          >
          inmuebles calculados.
          {{
            dossier.partial
              ? "Resumen parcial: quedan datos por completar."
              : "Cálculos preparados para revisión."
          }}
        </p>
        <div class="fiscal-actions">
          <button
            class="button secondary"
            :disabled="store.downloading || unsaved || !dossier.property_count"
            @click="download('csv')"
          >
            CSV de la cartera</button
          ><button
            class="button"
            :disabled="store.downloading || unsaved || !dossier.property_count"
            @click="download('pdf')"
          >
            <Download :size="17" />{{
              store.downloading ? "Preparando…" : "PDF de la cartera"
            }}
          </button>
        </div>
      </div>
      <section v-if="!dossier.properties.length" class="panel empty">
        <Building2 :size="30" />
        <h2>Empieza por tu primer inmueble</h2>
        <p>Su ficha fiscal aparecerá aquí cuando lo añadas a la cartera.</p>
        <RouterLink class="button" to="/properties">Añadir inmueble</RouterLink>
      </section>
      <div v-else class="fiscal-workspace">
        <aside class="fiscal-properties" aria-label="Inmuebles del dossier">
          <button
            v-for="item in dossier.properties"
            :key="item.property.id"
            type="button"
            :class="{ selected: selectedId === item.property.id }"
            :aria-pressed="selectedId === item.property.id"
            @click="selectProperty(item.property.id)"
          >
            <Building2 :size="20" /><strong>{{ item.property.name }}</strong
            ><span>{{
              item.result.status === "calculated"
                ? "Calculado · para revisar"
                : `${item.result.issues.length} puntos pendientes`
            }}</span
            ><b>{{ fiscalMoney(item.result.figures?.reduced_net) }}</b
            ><small>Rendimiento neto reducido</small>
          </button>
        </aside>
        <div v-if="selected" class="fiscal-editor">
          <section class="panel fiscal-result">
            <h2>
              {{
                selected.result.status === "calculated"
                  ? "El resultado de este inmueble"
                  : "Lo que falta para calcular"
              }}
            </h2>
            <ul v-if="selected.result.issues.length">
              <li v-for="issue in selected.result.issues" :key="issue">
                {{ issue }}
              </li>
            </ul>
            <template v-else>
              <div
                v-for="(label, key) in dossier.figure_labels"
                :key="key"
                class="fiscal-detail-row"
              >
                <span>{{ label }}</span
                ><strong>{{
                  fiscalMoney(selected.result.figures[key])
                }}</strong>
              </div>
              <p>
                Importes correspondientes a tu
                {{ selected.inputs.ownership_percent }} % de titularidad.
              </p>
              <details class="fiscal-help">
                <summary>Aplicación de gastos pendientes</summary>
                <p
                  v-for="carry in selected.result.carryforwards"
                  :key="carry.year"
                >
                  Origen {{ carry.year }}: aplicado
                  {{ fiscalMoney(carry.used_cents) }}, pendiente
                  {{ fiscalMoney(carry.pending_cents) }}, caducado
                  {{ fiscalMoney(carry.expired_cents) }}. Último ejercicio:
                  {{ carry.last_year }}.
                </p>
              </details>
            </template>
          </section>
          <FiscalPropertyForm
            :key="`${selected.property.id}-${selected.revision}`"
            :item="selected"
            :categories="store.data.expense_categories"
            :year="year"
            :saving="saving"
            :errors="fieldErrors"
            :downloading="store.downloading"
            @dirty="dirty = $event"
            @save="saveProperty"
            @download="download($event, selectedId)"
          />
        </div>
      </div>
    </template>
    <section v-if="store.data?.history.length" class="panel fiscal-history">
      <h2>Tus informes guardados</h2>
      <p>
        Cada versión conserva los datos utilizados al generarla. Puedes volver a
        descargarla aunque cambies de plan.
      </p>
      <div
        v-for="report in store.data.history"
        :key="report.id"
        class="fiscal-history-row"
      >
        <div>
          <strong>Dossier {{ report.year }} · #{{ report.id }}</strong>
          <p>
            {{ new Date(report.created_at).toLocaleString("es-ES") }} ·
            {{ report.property_count }} inmueble(s) ·
            {{ report.partial ? "Parcial" : "Para revisar" }}
          </p>
        </div>
        <div class="fiscal-actions">
          <button
            class="button secondary"
            :disabled="store.downloading"
            @click="download('csv', null, report.id)"
          >
            CSV</button
          ><button
            class="button secondary"
            :disabled="store.downloading"
            @click="download('pdf', null, report.id)"
          >
            PDF
          </button>
        </div>
      </div>
    </section>
  </main>
</template>
