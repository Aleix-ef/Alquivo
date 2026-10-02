<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "../api";
import { fetchAllPages } from "../pagination";
import {
  documentStates,
  documentFields,
  reviewValues,
  receiptPath,
  documentError,
} from "../documentAi";

const route = useRoute(),
  router = useRouter();
const settings = ref(null),
  documents = ref([]),
  properties = ref([]),
  contacts = ref([]),
  leases = ref([]);
const extraction = ref(null),
  values = ref({}),
  kind = ref("invoice"),
  documentId = ref(""),
  file = ref(null);
const loading = ref(true),
  busy = ref(false),
  error = ref(""),
  accepted = ref(false),
  reviewed = ref(false),
  unknown = ref(false);
let timer,
  disposed = false,
  refreshVersion = 0;
const working = computed(() =>
  ["queued", "processing"].includes(extraction.value?.status),
);
const editable = computed(() => extraction.value?.status === "needs_review");
const dirty = computed(
  () =>
    editable.value &&
    JSON.stringify(reviewValues(values.value)) !==
      JSON.stringify(reviewValues(extraction.value.draft.values)),
);
const invoice = computed(() => extraction.value?.kind === "invoice");
const destination = computed(() => receiptPath(extraction.value?.receipt));
const numeric = ["subtotal", "vat", "total", "monthly_rent", "deposit_amount"];
const baseFields = computed(() =>
  invoice.value
    ? [
        "issuer",
        "invoice_number",
        "date",
        "description",
        "subtotal",
        "vat",
        "total",
        "currency",
      ]
    : [
        "start_date",
        "end_date",
        "monthly_rent",
        "deposit_amount",
        "currency",
        "payment_day",
      ],
);
const categories = {
  maintenance: "Mantenimiento",
  insurance: "Seguro",
  tax: "Impuestos",
  other: "Otro",
};

function adopt(data) {
  extraction.value = data;
  values.value = data.draft?.values ? structuredClone(data.draft.values) : {};
  reviewed.value = false;
  unknown.value = false;
}
async function options() {
  [documents.value, properties.value, contacts.value, leases.value] =
    await Promise.all([
      fetchAllPages(api, "/documents"),
      fetchAllPages(api, "/properties"),
      fetchAllPages(api, "/contacts"),
      fetchAllPages(api, "/leases"),
    ]);
}
async function refresh(id = extraction.value?.id || route.params.id) {
  if (!id) return;
  clearTimeout(timer);
  const version = ++refreshVersion;
  try {
    const data = (await api.get(`/document-ai/extractions/${id}`)).data;
    if (disposed || version !== refreshVersion) return;
    adopt(data);
    if (working.value && !disposed) timer = setTimeout(() => refresh(id), 3000);
  } catch (e) {
    if (disposed || version !== refreshVersion) return;
    error.value = documentError(e);
    unknown.value = true;
  }
}
async function load() {
  loading.value = true;
  error.value = "";
  try {
    settings.value = (await api.get("/document-ai")).data;
    await options();
    await refresh();
  } catch (e) {
    error.value = documentError(e);
  } finally {
    loading.value = false;
  }
}
async function consent() {
  busy.value = true;
  error.value = "";
  try {
    await api.post("/document-ai/consent", {
      accepted: true,
      notice_version: settings.value.notice_version,
    });
    settings.value.accepted = true;
  } catch (e) {
    error.value = documentError(e);
  } finally {
    busy.value = false;
  }
}
async function revoke() {
  busy.value = true;
  error.value = "";
  try {
    await api.delete("/document-ai/consent");
    settings.value.accepted = false;
    accepted.value = false;
    await refresh();
  } catch (e) {
    error.value = documentError(e);
  } finally {
    busy.value = false;
  }
}
async function download(id, example = false) {
  error.value = "";
  try {
    const response = await api.get(
      example ? `/document-ai/examples/${id}` : `/documents/${id}/download`,
      { responseType: "blob" },
    );
    const url = URL.createObjectURL(response.data),
      a = document.createElement("a");
    a.href = url;
    a.download = example
      ? `alquivo-demo-${id}.pdf`
      : documents.value.find((d) => d.id === id)?.original_filename ||
        "documento.pdf";
    a.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  } catch (e) {
    error.value = documentError(e);
  }
}
async function start() {
  if (busy.value) return;
  busy.value = true;
  error.value = "";
  try {
    let id = documentId.value;
    if (file.value) {
      if (file.value.size > settings.value.limits.bytes)
        throw new Error("file-size");
      const data = new FormData();
      data.append("file", file.value);
      data.append("category", kind.value);
      id = (await api.post("/documents", data)).data.id;
      documentId.value = id;
      file.value = null; // A lost extraction response must not upload again.
      await options();
    }
    const result = (
      await api.post("/document-ai/extractions", {
        document_id: Number(id),
        kind: kind.value,
      })
    ).data;
    adopt(result);
    await router.replace(`/documents/import/${result.id}`);
    await refresh(result.id);
  } catch (e) {
    error.value =
      e.message === "file-size"
        ? "El archivo supera el tamaño permitido."
        : documentError(e);
  } finally {
    busy.value = false;
  }
}
async function open(id) {
  error.value = "";
  await router.replace(`/documents/import/${id}`);
  await refresh(id);
}
async function mutate(operation) {
  if (busy.value || unknown.value) return;
  busy.value = true;
  error.value = "";
  try {
    const payload = { revision: extraction.value.revision };
    if (operation === "revise") payload.values = reviewValues(values.value);
    adopt(
      (
        await api.post(
          `/document-ai/extractions/${extraction.value.id}/${operation}`,
          payload,
        )
      ).data,
    );
    if (operation === "confirm") {
      for (const event of [
        "finance-changed",
        "leases-changed",
        "documents-changed",
      ])
        window.dispatchEvent(
          new CustomEvent(`alquivo:${event}`, {
            detail: extraction.value.receipt,
          }),
        );
    }
    if (working.value) await refresh();
  } catch (e) {
    error.value = documentError(e);
    // Recover canonical state only; never replay a mutation after a lost response.
    if (e.response?.status !== 422) {
      unknown.value = true;
      await refresh();
    }
  } finally {
    busy.value = false;
  }
}
async function reset() {
  clearTimeout(timer);
  extraction.value = null;
  values.value = {};
  documentId.value = "";
  file.value = null;
  refreshVersion++;
  await router.replace("/documents/import");
  await load();
}
onMounted(load);
onBeforeUnmount(() => {
  disposed = true;
  clearTimeout(timer);
});
</script>

<template>
  <main class="page document-import">
    <header class="heading">
      <div>
        <RouterLink to="/documents">← Volver a documentos</RouterLink>
        <p class="eyebrow">Alquivo AI · laboratorio local</p>
        <h1>Del documento a tu cartera</h1>
        <p>Revisa los datos con calma. Tú decides qué se guarda.</p>
      </div>
      <button
        v-if="extraction"
        class="button secondary"
        @click="reset"
        :disabled="busy"
      >
        Otro documento
      </button>
    </header>
    <p class="import-notice">
      Simulación sin gasto: no se envía ningún archivo a OpenAI. Los ejemplos
      incluyen datos ficticios precargados; otros archivos se abren con campos
      vacíos, sin análisis automático.
    </p>
    <p v-if="error" role="alert" class="error">
      {{ error }}
      <button type="button" @click="extraction ? refresh() : load()">
        Recargar estado
      </button>
    </p>
    <p v-if="loading" role="status">Cargando importación…</p>
    <template v-else-if="settings">
      <section v-if="!settings.accepted" class="import-panel">
        <h2>Un permiso separado para tus documentos</h2>
        <p>
          Esta prueba guarda borradores y fragmentos cifrados durante un máximo
          de 30 días. Puedes revocar el permiso y eliminarlos aquí; no borra
          documentos ni operaciones ya confirmadas.
        </p>
        <p>
          Un análisis real podría enviar el archivo completo y datos de terceros
          al proveedor. Está bloqueado y requerirá un nuevo aviso y aceptación;
          este permiso sólo cubre la simulación local.
        </p>
        <p>
          Conservamos evidencia mínima del permiso y su retirada, sin archivos
          ni borradores, según la
          <RouterLink to="/privacy#conservacion"
            >política de privacidad</RouterLink
          >.
        </p>
        <label class="import-check"
          ><input v-model="accepted" type="checkbox" />Entiendo el alcance de
          esta prueba documental.</label
        >
        <button
          class="button primary"
          :disabled="!accepted || busy"
          @click="consent"
        >
          Permitir simulación local
        </button>
      </section>
      <form
        v-else-if="!extraction"
        class="import-panel"
        @submit.prevent="start"
      >
        <h2>1. Elige el documento</h2>
        <div class="import-grid">
          <label
            >Qué vas a importar<select v-model="kind">
              <option value="invoice">Factura → gasto</option>
              <option value="contract">Contrato de alquiler</option>
            </select></label
          >
          <label
            >Archivo guardado<select v-model="documentId" :disabled="!!file">
              <option value="">Selecciona un documento</option>
              <option
                v-for="d in documents.filter(
                  (d) => !d.transaction_id && !d.lease_id,
                )"
                :key="d.id"
                :value="d.id"
              >
                {{ d.name }}
              </option>
            </select></label
          >
        </div>
        <label class="import-upload"
          >O sube un archivo<input
            type="file"
            accept=".pdf,.jpg,.jpeg,.png"
            @change="file = $event.target.files[0] || null"
          /><span
            >PDF, JPG o PNG · hasta
            {{ Math.round(settings.limits.bytes / 1024 / 1024) }} MB ·
            {{ settings.limits.pages }} páginas</span
          ></label
        >
        <div class="import-buttons">
          <button
            class="button primary"
            :disabled="busy || (!documentId && !file)"
          >
            {{ busy ? "Preparando…" : "Preparar borrador simulado" }}</button
          ><button
            type="button"
            class="button secondary"
            @click="download(kind, true)"
          >
            Descargar {{ kind === "invoice" ? "factura" : "contrato" }} de
            ejemplo
          </button>
        </div>
        <p>
          Descarga el ejemplo y súbelo para recorrer la demostración completa.
          No se registra ninguna operación al preparar el borrador.
        </p>
      </form>
      <section v-if="extraction" class="import-panel" aria-live="polite">
        <div class="import-buttons">
          <span class="eyebrow"
            >{{ documentStates[extraction.status] || "Estado desconocido" }} ·
            revisión {{ extraction.revision }}</span
          ><button
            class="button secondary"
            @click="download(extraction.document_id)"
          >
            Descargar original
          </button>
        </div>
        <template v-if="working"
          ><h2>Preparando tu borrador</h2>
          <p>
            El trabajo se ejecuta en segundo plano. Puedes salir y volver a esta
            misma dirección; no se repetirá el análisis.
          </p></template
        >
        <template v-else-if="extraction.status === 'confirmed'"
          ><h2>
            {{
              extraction.kind === "invoice"
                ? "Gasto registrado"
                : extraction.receipt?.created
                  ? "Contrato creado en borrador"
                  : "Documento vinculado al contrato"
            }}
          </h2>
          <p>
            El documento está vinculado. No se han generado pagos ni
            interpretaciones fiscales o jurídicas.
          </p>
          <RouterLink
            v-if="destination"
            class="button primary"
            :to="destination"
            >Ver {{ invoice ? "Finanzas" : "contrato" }}</RouterLink
          ></template
        >
        <template v-else-if="extraction.status === 'failed'"
          ><h2>No hemos podido preparar el borrador</h2>
          <p>
            Comprueba formato, tamaño, páginas y permisos. No se ha creado
            ningún gasto ni contrato. Código: {{ extraction.error_code }}.
          </p>
          <button
            class="button primary"
            :disabled="
              busy || unknown || !settings.accepted || extraction.attempts >= 3
            "
            @click="mutate('retry')"
          >
            Reintentar expresamente
          </button></template
        >
        <p v-else-if="['cancelled', 'expired'].includes(extraction.status)">
          Este borrador ya no se puede confirmar. El documento original sigue en
          tu archivo.
        </p>
      </section>
      <div
        v-if="editable && extraction.draft && settings.accepted"
        class="import-review"
      >
        <form class="import-panel" @submit.prevent="mutate('revise')">
          <h2>2. Revisa y corrige</h2>
          <p>
            Un campo vacío significa que no hay un dato fiable. No equivale a
            cero.
          </p>
          <fieldset :disabled="busy || unknown">
            <template v-if="!invoice"
              ><label
                >Qué quieres hacer<select v-model="values.mode">
                  <option value="create">Crear contrato en borrador</option>
                  <option value="link">Vincular a un contrato existente</option>
                </select></label
              >
              <label v-if="values.mode === 'link'"
                >Contrato existente<select v-model="values.lease_id">
                  <option :value="null">Selecciona el contrato</option>
                  <option v-for="l in leases" :key="l.id" :value="l.id">
                    {{ l.property?.name }} · {{ l.start_date?.slice(0, 10) }} ·
                    {{ l.status }}
                  </option></select
                ><span
                  >Sólo adjunta el archivo. No cambia fechas, importes ni
                  participantes del contrato existente.</span
                ></label
              >
            </template>
            <template v-if="invoice || values.mode === 'create'">
              <label
                >Inmueble<select v-model="values.property_id">
                  <option :value="null">
                    {{
                      invoice
                        ? "Gasto general de la cartera"
                        : "Selecciona un inmueble"
                    }}
                  </option>
                  <option v-for="p in properties" :key="p.id" :value="p.id">
                    {{ p.name }} · {{ p.address_line }}
                  </option>
                </select></label
              >
              <p v-if="extraction.draft.property_candidates.length">
                Coincidencias por referencia documental:
                {{
                  extraction.draft.property_candidates
                    .map((p) => p.name + " · " + p.address)
                    .join("; ")
                }}. Elige tú; ninguna se asigna automáticamente.
              </p>
              <div class="import-grid">
                <label v-for="key in baseFields" :key="key"
                  >{{ documentFields[key]
                  }}<input
                    v-model="values[key]"
                    :type="
                      key === 'date' || key.endsWith('_date') ? 'date' : 'text'
                    "
                    :inputmode="numeric.includes(key) ? 'decimal' : undefined"
                    :maxlength="key === 'description' ? 180 : 1000"
                    :placeholder="
                      numeric.includes(key)
                        ? 'Ej. 121.00; vacío si no consta'
                        : 'No consta: revisar'
                    "
                /></label>
              </div>
              <template v-if="invoice"
                ><div class="import-grid">
                  <label
                    >Categoría<select v-model="values.category">
                      <option :value="null">Revisar categoría</option>
                      <option
                        v-for="(label, value) in categories"
                        :key="value"
                        :value="value"
                      >
                        {{ label }}
                      </option>
                    </select></label
                  ><label
                    >Estado del gasto<select v-model="values.status">
                      <option value="pending">
                        Pendiente (no acredita pago)
                      </option>
                      <option value="paid">Pagado — lo he comprobado</option>
                    </select></label
                  >
                </div>
                <p>
                  No se establece deducibilidad fiscal. Emisor, número, base e
                  IVA quedan en esta revisión y en el original; el gasto guarda
                  concepto, total, fecha, categoría, inmueble y estado.
                </p></template
              >
              <template v-else>
                <label
                  >Periodicidad<select v-model="values.periodicity">
                    <option :value="null">No consta</option>
                    <option value="monthly">Mensual</option>
                    <option value="yearly">Anual (no compatible)</option>
                    <option value="weekly">Semanal (no compatible)</option>
                    <option value="other">Otra (no compatible)</option>
                  </select></label
                >
                <h3>Inquilinos que vincularás</h3>
                <p>
                  Compara con las partes detectadas a la derecha. No se asignan
                  por similitud de nombre.
                </p>
                <label v-for="c in contacts" :key="c.id" class="import-check"
                  ><input
                    type="checkbox"
                    :value="c.id"
                    v-model="values.contact_ids"
                  />{{ c.name }}</label
                >
                <p v-if="!contacts.length">
                  Aún no hay contactos en tu cartera.
                </p>
                <RouterLink to="/contacts?new=1" target="_blank"
                  >Crear un contacto en otra pestaña</RouterLink
                >
                ·
                <button type="button" @click="options">
                  Actualizar opciones
                </button>
                <p>
                  Se creará un contrato en estado borrador. No se activan cobros
                  ni se trasladan cláusulas a automatizaciones. Para fianza
                  ausente, introduce 0 sólo si lo has comprobado.
                </p>
              </template>
            </template>
            <button class="button secondary" type="submit" :disabled="!dirty">
              Guardar correcciones
            </button>
          </fieldset>
          <hr />
          <h2>3. Confirma lo que se guardará</h2>
          <p v-if="dirty">
            Guarda primero las correcciones para confirmar la versión revisada.
          </p>
          <label class="import-check"
            ><input
              type="checkbox"
              v-model="reviewed"
              :disabled="dirty || busy"
            />He contrastado los datos y el destino con el documento
            original.</label
          >
          <button
            type="button"
            class="button primary"
            :disabled="busy || unknown || dirty || !reviewed"
            @click="mutate('confirm')"
          >
            {{
              invoice
                ? "Confirmar y registrar gasto"
                : values.mode === "link"
                  ? "Confirmar vínculo"
                  : "Confirmar y crear contrato en borrador"
            }}
          </button>
        </form>
        <aside class="import-panel import-evidence">
          <h2>Lo que propone la extracción</h2>
          <p>
            Datos simulados, no una lectura real. Los fragmentos deben
            contrastarse con el original.
          </p>
          <ul>
            <li
              v-for="(warning, i) in extraction.draft.detected.warnings"
              :key="i"
            >
              {{ warning }}
            </li>
          </ul>
          <template v-if="!invoice"
            ><h3>Participantes detectados</h3>
            <p
              v-for="(p, i) in extraction.draft.detected.participants"
              :key="i"
            >
              {{ p.name || "Nombre no legible" }} ·
              {{
                {
                  tenant: "Inquilino",
                  landlord: "Arrendador",
                  guarantor: "Avalista",
                  other: "Otro",
                }[p.role] || "Papel sin determinar"
              }}
            </p></template
          >
          <details open>
            <summary>Fragmentos y páginas</summary>
            <p v-if="!extraction.draft.detected.evidence.length">
              No hay evidencia disponible. Revisa manualmente el archivo.
            </p>
            <blockquote
              v-for="(e, i) in extraction.draft.detected.evidence"
              :key="i"
            >
              <strong>{{ documentFields[e.field] || e.field }}</strong>
              <p>{{ e.quote }}</p>
              <span>{{
                e.page ? `Página ${e.page}` : "Página sin verificar"
              }}</span>
            </blockquote>
          </details>
          <template v-if="!invoice"
            ><h3>Información sólo para revisión</h3>
            <dl>
              <template
                v-for="key in [
                  'rent_update_clause',
                  'other_clauses',
                  'relevant_dates',
                ]"
                :key="key"
                ><dt>{{ documentFields[key] }}</dt>
                <dd>
                  {{ extraction.draft.detected[key] || "No consta" }}
                </dd></template
              >
            </dl>
            <p>
              Sin interpretación jurídica, plazos calculados ni efectos
              automáticos. Consulta a un profesional si necesitas interpretar el
              contrato.
            </p></template
          >
        </aside>
      </div>
      <div
        v-if="
          extraction &&
          !['confirmed', 'cancelled', 'expired'].includes(extraction.status)
        "
        class="import-buttons"
      >
        <button
          class="button secondary"
          :disabled="busy || unknown"
          @click="mutate('cancel')"
        >
          Cancelar este borrador
        </button>
      </div>
      <section
        v-if="!extraction && settings.extractions.length"
        class="import-panel"
      >
        <h2>Continúa una revisión</h2>
        <button
          v-for="e in settings.extractions"
          :key="e.id"
          class="import-history"
          @click="open(e.id)"
        >
          <strong
            >{{ e.kind === "invoice" ? "Factura" : "Contrato" }} · documento
            {{ e.document_id }}</strong
          ><span
            >{{ documentStates[e.status] }} ·
            {{ e.created_at.slice(0, 10) }}</span
          >
        </button>
      </section>
      <footer v-if="settings.accepted" class="import-footer">
        <button :disabled="busy" @click="revoke">
          Revocar permiso y eliminar borradores
        </button>
        <p>No elimina los archivos ni las operaciones confirmadas.</p>
      </footer>
    </template>
  </main>
</template>

<style scoped>
.document-import {
  max-width: 1240px;
  width: 100%;
  min-width: 0;
  overflow-wrap: anywhere;
}
.document-import p,
.document-import label,
.document-import li,
.document-import dd {
  font-size: 1rem;
  line-height: 1.65;
}
.import-notice,
.import-panel {
  border: 1px solid var(--border, #343940);
  border-radius: 18px;
  padding: 24px;
  margin-bottom: 22px;
  background: var(--surface, #171c24);
}
.import-notice {
  border-left: 4px solid #92b7ce;
}
.import-grid,
.import-review {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 22px;
}
.import-review {
  grid-template-columns: minmax(0, 1.6fr) minmax(280px, 1fr);
  align-items: start;
}
.import-panel label {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 20px;
}
.import-panel input:not([type="checkbox"]),
.import-panel select {
  width: 100%;
  min-width: 0;
  max-width: 100%;
  min-height: 46px;
  font-size: 1rem;
  padding: 11px 13px;
  border: 1px solid var(--line);
  border-radius: 10px;
  background: var(--surface-soft);
  color: var(--ink);
}
.import-panel .import-check {
  flex-direction: row;
  align-items: start;
  gap: 12px;
}
.import-check input {
  width: 20px;
  height: 20px;
  flex: 0 0 20px;
  margin-top: 4px;
}
.import-upload {
  padding: 22px;
  border: 1px dashed var(--border, #535f72);
  border-radius: 12px;
}
.import-buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
  margin: 16px 0;
}
fieldset {
  border: 0;
  padding: 0;
  margin: 0;
  min-width: 0;
}
hr {
  border: 0;
  border-top: 1px solid var(--border, #343940);
  margin: 28px 0;
}
blockquote {
  margin: 18px 0;
  padding: 16px;
  border-left: 3px solid #92b7ce;
  background: #ffffff05;
  overflow-wrap: anywhere;
}
blockquote p {
  white-space: pre-wrap;
}
dt {
  font-weight: 600;
  margin-top: 16px;
}
dd {
  margin: 4px 0 20px;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
.import-history {
  display: flex;
  flex-direction: column;
  text-align: left;
  width: 100%;
  padding: 14px;
  border-bottom: 1px solid var(--border, #343940);
  gap: 6px;
}
.import-footer {
  margin: 30px 0;
}
summary {
  cursor: pointer;
  font-weight: 600;
}
@media (max-width: 800px) {
  .import-review,
  .import-grid {
    grid-template-columns: minmax(0, 1fr);
  }
  .heading {
    flex-direction: column;
    align-items: stretch;
  }
  .import-review > *,
  .import-grid > * {
    min-width: 0;
  }
  .import-panel {
    padding: 18px;
  }
}
</style>
