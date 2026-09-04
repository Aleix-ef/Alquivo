<script setup>
import { ref, computed, onMounted } from "vue";
import { FileText, Plus, Download, Pencil, Trash2 } from "@lucide/vue";
import { useRoute, useRouter } from "vue-router";
import api from "../api";
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
  form = ref({
    name: "",
    category: "contract",
    property_id: route.query.property || "",
    lease_id: route.query.lease || "",
    issued_at: "",
    expires_at: "",
    file: null,
  });
async function load() {
  loading.value = true;
  loadError.value = "";
  try {
    const [d, p] = await Promise.all([
      api.get("/documents", {
        params: Object.fromEntries(
          Object.entries(filters.value).filter(([, value]) => value),
        ),
      }),
      api.get("/properties"),
    ]);
    docs.value = d.data.data;
    properties.value = p.data.data;
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
    router.replace("/documents");
    await load();
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
    !window.confirm(
      `¿Eliminar “${d.name}”? El archivo dejará de estar disponible.`,
    )
  )
    return;
  await api.delete(`/documents/${d.id}`);
  await load();
}
async function download(d) {
  const r = await api.get(`/documents/${d.id}/download`, {
    responseType: "blob",
  });
  const url = URL.createObjectURL(r.data),
    a = document.createElement("a");
  a.href = url;
  a.download = d.original_filename;
  a.click();
  URL.revokeObjectURL(url);
}
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
      <RouterLink class="button primary" to="?new=1"
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
        <option value="contract">Contratos</option>
        <option value="invoice">Facturas</option>
        <option value="insurance">Seguros</option>
        <option value="tax">Impuestos</option>
        <option value="certificate">Certificados</option>
        <option value="other">Otros</option></select
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
      <article v-for="d in docs" :key="d.id" class="document-card">
        <FileText :size="24" />
        <div>
          <strong>{{ d.name }}</strong
          ><small
            >{{
              d.property?.name || d.lease?.property?.name || "Cartera general"
            }}
            · {{ d.category }}</small
          >
          <small
            v-if="d.expires_at"
            :class="{ 'expiry-alert': new Date(d.expires_at) < new Date() }"
            >Vence {{ d.expires_at.slice(0, 10) }}</small
          >
        </div>
        <span class="document-actions">
          <button @click="download(d)" title="Descargar">
            <Download :size="17" />
          </button>
          <button @click="edit(d)" title="Editar"><Pencil :size="17" /></button>
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
    <div
      v-if="show"
      class="drawer-bg"
      @click.self="router.replace('/documents')"
    >
      <form class="drawer" @submit.prevent="save">
        <p class="eyebrow">
          {{ editing ? "Editar documento" : "Nuevo documento" }}
        </p>
        <h2>{{ editing ? "Corrige sus datos" : "Sube un archivo" }}</h2>
        <p v-if="error" class="error">{{ error }}</p>
        <label v-if="!editing"
          >Archivo<input
            type="file"
            required
            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
            @change="form.file = $event.target.files[0]" /></label
        ><label
          >Nombre<input v-model="form.name" placeholder="Opcional" /></label
        ><label
          >Categoría<select v-model="form.category">
            <option value="contract">Contrato</option>
            <option value="invoice">Factura</option>
            <option value="insurance">Seguro</option>
            <option value="tax">Impuestos</option>
            <option value="certificate">Certificado</option>
            <option value="other">Otro</option>
          </select></label
        ><label
          >Propiedad<select v-model="form.property_id">
            <option value="">General</option>
            <option v-for="p in properties" :value="p.id" :key="p.id">
              {{ p.name }}
            </option>
          </select></label
        >
        <label
          >Fecha de emisión<input v-model="form.issued_at" type="date" /></label
        ><label
          >Fecha de vencimiento<input v-model="form.expires_at" type="date"
        /></label>
        <footer>
          <button class="button primary" :disabled="saving">
            {{ saving ? "Guardando…" : "Guardar documento" }}
          </button>
        </footer>
      </form>
    </div>
  </main>
</template>
