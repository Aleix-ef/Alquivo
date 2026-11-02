<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { useRecordAnchor } from "../composables/useRecordAnchor";
import { FileText, Plus, Download, Pencil, Trash2 } from "@lucide/vue";
import { useRoute, useRouter } from "vue-router";
import api from "../api";
import { useSession } from "../session";
const session = useSession();
import { fetchAllPages } from "../pagination";
import ConfirmDialog from "../components/ConfirmDialog.vue";
import { useConfirmDialog } from "../composables/useConfirmDialog";
import { useDialog } from "../composables/useDialog";
import DocumentDateFields from "../components/DocumentDateFields.vue";
import { documentType, documentTypes } from "../documentTypes.js";
import { shortDate } from "../datePresentation.js";
const confirmation = useConfirmDialog();
const route = useRoute(),
  router = useRouter(),
  docs = ref([]),
  properties = ref([]),
  filters = ref({
    category: "",
    status: route.query.status || "",
    property_id: route.query.property || "",
  }),
  loading = ref(true),
  loadError = ref(""),
  saving = ref(false),
  error = ref(""),
  editing = computed(() =>
    docs.value.find((d) => d.id === Number(route.query.edit)),
  ),
  show = computed(() => route.query.new === "1" || Boolean(editing.value)),
  form = ref(blankForm());
const drawer = ref(null);
const selectedType = computed(() => documentType(form.value.category));
function blankForm() {
  return {
    name: "",
    category: documentTypes.some(
      (type) => type.value === filters.value.category,
    )
      ? filters.value.category
      : "contract",
    property_id: route.query.property || "",
    lease_id: route.query.lease || "",
    issued_at: "",
    expires_at: "",
    file: null,
  };
}
function closeForm() {
  if (saving.value) return;
  if (route.query.from === "property" && route.query.property) {
    router.replace(`/properties/${route.query.property}?tab=documentos`);
    return;
  }
  const query = { ...route.query };
  delete query.new;
  delete query.edit;
  router.replace({ query, hash: route.hash });
}
useDialog(show, drawer, closeForm);
watch(
  () => route.query.new,
  (value) => {
    if (value === "1") {
      form.value = blankForm();
      error.value = "";
    }
  },
);
async function load() {
  loading.value = true;
  loadError.value = "";
  try {
    const [d, p] = await Promise.all([
      fetchAllPages(api, "/documents", {
        params: Object.fromEntries(
          Object.entries(filters.value).filter(([, value]) => value),
        ),
      }),
      fetchAllPages(api, "/properties"),
    ]);
    docs.value = d;
    properties.value = p;
  } catch {
    loadError.value =
      "No hemos podido cargar los documentos. Vuelve a intentarlo.";
  } finally {
    loading.value = false;
  }
}
async function save() {
  saving.value = true;
  error.value = "";
  try {
    if (editing.value) {
      await api.put(`/documents/${editing.value.id}`, {
        name: form.value.name,
        category: form.value.category,
        property_id: form.value.property_id
          ? Number(form.value.property_id)
          : null,
        issued_at: form.value.issued_at || null,
        expires_at: form.value.expires_at || null,
      });
    } else {
      const data = new FormData();
      Object.entries(form.value).forEach(([k, v]) => {
        if (v) data.append(k, v);
      });
      await api.post("/documents", data);
    }
    if (route.query.from === "property" && route.query.property) {
      await router.replace(
        `/properties/${route.query.property}?tab=documentos`,
      );
    } else {
      await router.replace("/documents");
      await load();
    }
  } catch (e) {
    error.value =
      e.response?.data?.message || "No se pudo guardar el documento.";
  } finally {
    saving.value = false;
  }
}
function edit(d) {
  form.value = {
    name: d.name,
    category: d.category,
    property_id: d.property_id || "",
    lease_id: d.lease_id || "",
    issued_at: d.issued_at?.slice(0, 10) || "",
    expires_at: d.expires_at?.slice(0, 10) || "",
    file: null,
  };
  router.push({ query: { edit: d.id } });
}
async function remove(d) {
  if (
    !(await confirmation.ask({
      title: `¿Eliminar “${d.name}”?`,
      description:
        "El archivo dejará de estar disponible y esta acción no se puede deshacer.",
      confirmLabel: "Eliminar documento",
      danger: true,
    }))
  )
    return;
  try {
    await api.delete(`/documents/${d.id}`);
    await load();
  } catch (exception) {
    loadError.value =
      exception.response?.data?.message || "No se pudo eliminar el documento.";
  }
}
async function download(d) {
  try {
    const r = await api.get(`/documents/${d.id}/download`, {
      responseType: "blob",
    });
    const url = URL.createObjectURL(r.data),
      a = document.createElement("a");
    a.href = url;
    a.download = d.original_filename;
    a.click();
    URL.revokeObjectURL(url);
  } catch {
    loadError.value = "No se pudo descargar el documento.";
  }
}
useRecordAnchor(loading, "document");
onMounted(load);
</script>
<template>
  <main class="page">
    <header class="heading">
      <div>
        <p class="eyebrow">Archivo</p>
        <h1>Documentos</h1>
        <p>Contratos, facturas y documentación vinculada a tu cartera.</p>
      </div>
      <RouterLink
        v-if="session.user?.local_admin"
        class="button secondary"
        to="/documents/import"
        >Importación IA · demo</RouterLink
      >
      <RouterLink
        class="button primary"
        :to="{ query: { ...route.query, new: '1', edit: undefined } }"
        ><Plus :size="16" />Subir documento</RouterLink
      >
    </header>
    <section class="filters">
      <select
        v-model="filters.property_id"
        aria-label="Filtrar documentos por propiedad"
        @change="load"
      >
        <option value="">Todas las propiedades</option>
        <option
          v-for="property in properties"
          :key="property.id"
          :value="property.id"
        >
          {{ property.name }}
        </option>
      </select>
      <select
        v-model="filters.category"
        aria-label="Categoría de documentos"
        @change="load"
      >
        <option value="">Todas las categorías</option>
        <option
          v-for="type in documentTypes"
          :key="type.value"
          :value="type.value"
        >
          {{ type.plural }}
        </option></select
      ><select
        v-model="filters.status"
        aria-label="Vencimiento de documentos"
        @change="load"
      >
        <option value="">Cualquier vencimiento</option>
        <option value="upcoming">Próximos 60 días</option>
        <option value="expired">Vencidos</option>
      </select>
    </section>
    <section v-if="loading" class="empty" role="status">
      Cargando documentos…
    </section>
    <section v-else-if="loadError" class="empty" role="alert">
      <p>{{ loadError }}</p>
      <button class="button secondary" @click="load">Volver a intentar</button>
    </section>
    <section v-else-if="docs.length" class="document-grid">
      <article
        v-for="d in docs"
        :key="d.id"
        :id="`document-${d.id}`"
        tabindex="-1"
        class="document-card"
      >
        <FileText :size="24" />
        <div>
          <strong>{{ d.name }}</strong
          ><small
            >{{
              d.property?.name || d.lease?.property?.name || "Cartera general"
            }}
            · {{ documentType(d.category).label }}</small
          >
          <small v-if="d.transaction_id"
            ><RouterLink to="/finance"
              >Vinculado al gasto #{{ d.transaction_id }}</RouterLink
            ></small
          >
          <small
            v-if="d.expires_at"
            :class="{ 'expiry-alert': new Date(d.expires_at) < new Date() }"
            >Revisar: {{ shortDate(d.expires_at) }}</small
          >
        </div>
        <span class="document-actions">
          <button @click="download(d)" title="Descargar">
            <Download :size="17" />
          </button>
          <button
            @click="edit(d)"
            title="Editar"
            :aria-label="`Editar documento ${d.name}`"
            data-record-action
          >
            <Pencil :size="17" />
          </button>
          <button @click="remove(d)" title="Eliminar">
            <Trash2 :size="17" />
          </button>
        </span>
      </article>
    </section>
    <section v-else class="empty">
      <FileText :size="35" />
      <h2>
        {{
          Object.values(filters).some(Boolean)
            ? "No hay documentos con estos filtros"
            : "Tu archivo está vacío"
        }}
      </h2>
      <p>
        Guarda aquí contratos y facturas para encontrarlos junto al inmueble
        correcto.
      </p>
    </section>
    <div v-if="show" class="drawer-bg" @click.self="closeForm">
      <form
        ref="drawer"
        class="drawer document-drawer"
        role="dialog"
        aria-modal="true"
        aria-labelledby="document-form-title"
        tabindex="-1"
        @submit.prevent="save"
      >
        <p class="eyebrow">
          {{ editing ? "Editar documento" : "Nuevo documento" }}
        </p>
        <h2 id="document-form-title">
          {{ editing ? "Editar" : "Subir" }}
          {{ selectedType.label.toLowerCase() }}
        </h2>
        <p v-if="error" class="error">{{ error }}</p>
        <label v-if="!editing"
          >Archivo<input
            type="file"
            required
            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
            @change="form.file = $event.target.files[0]" /></label
        ><label
          >Nombre<input
            v-model="form.name"
            :placeholder="selectedType.placeholder"
            :required="!!editing"
            maxlength="150" /></label
        ><label
          >Categoría<select v-model="form.category">
            <option
              v-for="type in documentTypes"
              :key="type.value"
              :value="type.value"
            >
              {{ type.label }}
            </option>
          </select></label
        ><label
          >Propiedad<select v-model="form.property_id">
            <option value="">General</option>
            <option v-for="p in properties" :value="p.id" :key="p.id">
              {{ p.name }}
            </option>
          </select></label
        >
        <DocumentDateFields
          :category="form.category"
          v-model:issued-at="form.issued_at"
          v-model:expires-at="form.expires_at"
          :original-expiry="!!editing?.expires_at"
        />
        <footer>
          <button
            class="button secondary"
            type="button"
            :disabled="saving"
            @click="closeForm"
          >
            Cancelar
          </button>
          <button class="button primary" :disabled="saving">
            {{ saving ? "Guardando…" : "Guardar documento" }}
          </button>
        </footer>
      </form>
    </div>
    <ConfirmDialog
      :dialog="confirmation.dialog.value"
      @confirm="confirmation.confirm"
      @cancel="confirmation.cancel"
    />
  </main>
</template>

<style scoped>
.document-drawer input[type="file"] {
  width: 100%;
  min-width: 0;
}
</style>
