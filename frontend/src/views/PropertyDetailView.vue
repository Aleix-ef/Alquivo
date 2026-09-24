<script setup>
import { useProduct } from "../stores/product";
import { usePlanAccess } from "../stores/planAccess";
const product = useProduct(),
  planAccess = usePlanAccess();
import { ref, computed, watch, onMounted, onBeforeUnmount } from "vue";
import {
  ArrowLeft,
  ArrowUpRight,
  Camera,
  Home,
  KeyRound,
  WalletCards,
  Wrench,
  FileText,
  Pencil,
  X,
  MapPin,
  ImagePlus,
  RefreshCw,
  Check,
  Trash2,
  Star,
  Archive,
} from "@lucide/vue";
import { useRoute, useRouter } from "vue-router";
import api from "../api";
import PropertyImage from "../components/PropertyImage.vue";
import { useDialog } from "../composables/useDialog";
import ConfirmDialog from "../components/ConfirmDialog.vue";
import { useConfirmDialog } from "../composables/useConfirmDialog";
import "../property-experience.css";

const route = useRoute();
const router = useRouter();
const property = ref(null);
const loading = ref(true);
const loadError = ref("");
const uploading = ref(false);
const uploadError = ref("");
const notice = ref("");
const editing = ref(false);
const saving = ref(false);
const saveError = ref("");
const fieldErrors = ref({});
const editForm = ref({});
const drawer = ref(null);
const photoInput = ref(null);
const selectedPhotoId = ref(null);
const propertyActionBusy = ref(false);
const confirmation = useConfirmDialog();
const types = {
  housing: "Vivienda",
  commercial: "Local",
  office: "Oficina",
  garage: "Garaje",
  storage: "Trastero",
  land: "Terreno",
  building: "Edificio",
  other: "Otro",
};
const money = (value) =>
  new Intl.NumberFormat("es-ES", {
    style: "currency",
    currency: "EUR",
    maximumFractionDigits: 0,
  }).format(value || 0);
const date = (value) =>
  value
    ? new Intl.DateTimeFormat("es-ES", {
        day: "numeric",
        month: "short",
        year: "numeric",
      }).format(new Date(value.slice(0, 10) + "T12:00:00"))
    : "Sin fecha de fin";
const activeLease = computed(() =>
  property.value?.leases?.find((lease) => lease.status === "active"),
);
const selectedPhoto = computed(
  () =>
    property.value?.photos?.find(
      (photo) => photo.id === selectedPhotoId.value,
    ) || property.value?.photos?.[0],
);
const income = computed(
  () =>
    property.value?.transactions
      ?.filter(
        (transaction) =>
          transaction.direction === "income" && transaction.status === "paid",
      )
      .reduce((total, transaction) => total + Number(transaction.amount), 0) ||
    0,
);
const expenses = computed(
  () =>
    property.value?.transactions
      ?.filter(
        (transaction) =>
          transaction.direction === "expense" && transaction.status === "paid",
      )
      .reduce((total, transaction) => total + Number(transaction.amount), 0) ||
    0,
);
const yieldRate = computed(() =>
  Number(property.value?.current_value) > 0 && activeLease.value
    ? new Intl.NumberFormat("es-ES", { maximumFractionDigits: 2 }).format(
        ((Number(activeLease.value.monthly_rent) * 12) /
          Number(property.value.current_value)) *
          100,
      )
    : null,
);
const openIssues = computed(
  () =>
    property.value?.issues?.filter(
      (issue) => !["resolved", "cancelled"].includes(issue.status),
    ).length || 0,
);
let loadController;

async function load() {
  loadController?.abort();
  const controller = new AbortController();
  loadController = controller;
  loading.value = true;
  loadError.value = "";
  property.value = null;
  try {
    const { data } = await api.get("/properties/" + route.params.id, {
      signal: controller.signal,
    });
    if (!controller.signal.aborted) property.value = data;
  } catch (exception) {
    if (!controller.signal.aborted)
      loadError.value =
        exception.response?.status === 404
          ? "Esta propiedad no existe o no pertenece a tu cartera."
          : "No hemos podido cargar la propiedad. Comprueba tu conexión e inténtalo de nuevo.";
  } finally {
    if (!controller.signal.aborted) loading.value = false;
  }
}

function startEditing() {
  const today = new Date();
  const localDate = [
    today.getFullYear(),
    String(today.getMonth() + 1).padStart(2, "0"),
    String(today.getDate()).padStart(2, "0"),
  ].join("-");
  editForm.value = {
    name: property.value.name,
    type: property.value.type,
    address_line: property.value.address_line,
    city: property.value.city || "",
    province: property.value.province || "",
    postal_code: property.value.postal_code || "",
    purchase_date: property.value.purchase_date?.slice(0, 10) || "",
    purchase_price: property.value.purchase_price,
    acquisition_costs: property.value.acquisition_costs,
    current_value: property.value.current_value,
    outstanding_debt: property.value.outstanding_debt,
    area: property.value.area,
    bedrooms: property.value.bedrooms,
    bathrooms: property.value.bathrooms,
    notes: property.value.notes || "",
    valuation_date: localDate,
  };
  saveError.value = "";
  fieldErrors.value = {};
  editing.value = true;
}

async function makeCover() {
  if (
    !selectedPhoto.value ||
    selectedPhoto.value.is_cover ||
    propertyActionBusy.value
  )
    return;
  propertyActionBusy.value = true;
  uploadError.value = "";
  try {
    await api.put(`/property-photos/${selectedPhoto.value.id}/cover`);
    await load();
    notice.value = "La portada de la propiedad se ha actualizado.";
  } catch (exception) {
    uploadError.value =
      exception.response?.data?.message || "No se pudo cambiar la portada.";
  } finally {
    propertyActionBusy.value = false;
  }
}

async function removePhoto() {
  const photo = selectedPhoto.value;
  if (!photo || propertyActionBusy.value) return;
  if (
    !(await confirmation.ask({
      title: "¿Eliminar esta fotografía?",
      description:
        "La imagen dejará de estar disponible y no se podrá recuperar.",
      confirmLabel: "Eliminar fotografía",
      danger: true,
    }))
  )
    return;
  propertyActionBusy.value = true;
  try {
    await api.delete(`/property-photos/${photo.id}`);
    property.value.photos = property.value.photos.filter(
      (item) => item.id !== photo.id,
    );
    selectedPhotoId.value = null;
    notice.value = "Fotografía eliminada.";
  } catch (exception) {
    uploadError.value =
      exception.response?.data?.message || "No se pudo eliminar la fotografía.";
  } finally {
    propertyActionBusy.value = false;
  }
}

async function archiveProperty() {
  if (propertyActionBusy.value) return;
  if (
    !(await confirmation.ask({
      title: `¿Archivar “${property.value.name}”?`,
      description:
        "Solo se puede archivar una propiedad sin contratos, movimientos, documentos, incidencias ni recordatorios vinculados.",
      confirmLabel: "Archivar propiedad",
      danger: true,
    }))
  )
    return;
  propertyActionBusy.value = true;
  uploadError.value = "";
  try {
    await api.delete(`/properties/${property.value.id}`);
    await router.push("/properties");
  } catch (exception) {
    uploadError.value =
      exception.response?.data?.message || "No se pudo archivar la propiedad.";
  } finally {
    propertyActionBusy.value = false;
  }
}
function closeEditing() {
  if (!saving.value) editing.value = false;
}
useDialog(editing, drawer, closeEditing);

async function saveProperty() {
  if (saving.value) return;
  saving.value = true;
  saveError.value = "";
  fieldErrors.value = {};
  try {
    const { data } = await api.put(
      "/properties/" + property.value.id,
      editForm.value,
    );
    property.value = { ...property.value, ...data };
    editing.value = false;
    notice.value = "Los datos de la propiedad se han actualizado.";
  } catch (exception) {
    fieldErrors.value = exception.response?.data?.errors || {};
    saveError.value =
      exception.response?.data?.message ||
      "No se han podido guardar los cambios. Tus datos siguen aquí para volver a intentarlo.";
  } finally {
    saving.value = false;
  }
}

async function upload(event) {
  const input = event.target;
  const file = input.files?.[0];
  if (!file || uploading.value) return;
  uploadError.value = "";
  notice.value = "";
  if (
    ![
      "image/jpeg",
      "image/png",
      "image/webp",
      "image/gif",
      "image/bmp",
    ].includes(file.type)
  ) {
    uploadError.value = "Elige una imagen JPG, PNG, WebP, GIF o BMP.";
    input.value = "";
    return;
  }
  if (file.size > 6 * 1024 * 1024) {
    uploadError.value =
      "La imagen supera los 6 MB. Prueba con una fotografía más pequeña.";
    input.value = "";
    return;
  }
  uploading.value = true;
  const propertyId = property.value.id;
  const payload = new FormData();
  payload.append("photo", file);
  try {
    const { data } = await api.post(
      "/properties/" + propertyId + "/photos",
      payload,
    );
    if (property.value?.id === propertyId) {
      property.value.photos = [...(property.value.photos || []), data];
      selectedPhotoId.value = data.id;
      notice.value = "Fotografía añadida a tu propiedad.";
    }
  } catch (exception) {
    if (property.value?.id === propertyId)
      uploadError.value =
        exception.response?.data?.errors?.photo?.[0] ||
        exception.response?.data?.message ||
        "No hemos podido subir la fotografía. Puedes volver a intentarlo.";
  } finally {
    uploading.value = false;
    input.value = "";
  }
}

watch(
  () => route.params.id,
  () => {
    selectedPhotoId.value = null;
    notice.value = "";
    uploadError.value = "";
    editing.value = false;
    load();
  },
  { immediate: true },
);
function refreshProperty(event) {
  if (
    !event.detail?.propertyId ||
    String(event.detail.propertyId) === String(route.params.id)
  )
    load();
}
onMounted(() => {
  window.addEventListener("alquivo:properties-changed", refreshProperty);
  window.addEventListener("alquivo:leases-changed", refreshProperty);
});
onBeforeUnmount(() => {
  loadController?.abort();
  window.removeEventListener("alquivo:properties-changed", refreshProperty);
  window.removeEventListener("alquivo:leases-changed", refreshProperty);
});
</script>

<template>
  <main class="page detail-page property-detail-page">
    <RouterLink class="back" to="/properties"
      ><ArrowLeft :size="16" />Todas las propiedades</RouterLink
    >
    <section
      v-if="loading"
      class="property-detail-loading"
      aria-busy="true"
      aria-label="Cargando propiedad"
    >
      <div class="property-skeleton">
        <div></div>
        <span></span><span></span>
      </div>
    </section>
    <section v-else-if="loadError" class="empty property-empty" role="alert">
      <Home :size="35" />
      <h2>No hemos podido abrir esta propiedad</h2>
      <p>{{ loadError }}</p>
      <button type="button" class="button secondary" @click="load">
        <RefreshCw :size="16" />Volver a intentar
      </button>
    </section>
    <template v-else-if="property">
      <p
        v-if="planAccess.readOnly(property.id)"
        class="property-notice"
        role="status"
      >
        Este inmueble está en modo consulta por el límite de tu plan. Sus datos
        y documentos se conservan.
        <RouterLink to="/plans">Ver opciones</RouterLink>
      </p>
      <p v-if="notice" class="property-notice" role="status">
        <Check :size="16" />{{ notice }}
      </p>
      <p v-if="uploadError" class="error" role="alert">{{ uploadError }}</p>
      <section class="property-hero property-showcase">
        <PropertyImage
          class="property-showcase-image"
          :property="property"
          :photo="selectedPhoto"
          :show-label="false"
        />
        <div class="property-showcase-shade"></div>
        <span class="property-showcase-caption">{{
          selectedPhoto
            ? "Tu propiedad"
            : "Imagen ilustrativa · añade tu fotografía"
        }}</span>
        <div class="property-showcase-content">
          <div>
            <div class="property-showcase-tags">
              <span class="pill">{{ types[property.type] || "Propiedad" }}</span
              ><span class="pill">{{
                activeLease ? "Alquilada" : "Sin alquiler activo"
              }}</span>
            </div>
            <h1>{{ property.name }}</h1>
            <p>
              <MapPin :size="16" />{{
                [property.address_line, property.city]
                  .filter(Boolean)
                  .join(" · ")
              }}
            </p>
          </div>
          <div class="hero-actions">
            <RouterLink
              class="button photo-button"
              :to="`/fiscality?property=${property.id}`"
              v-if="product.accountFeatures.fiscality"
              ><FileText :size="16" />Fiscalidad</RouterLink
            >
            <button
              class="button photo-button"
              type="button"
              @click="startEditing"
              :disabled="planAccess.readOnly(property.id)"
            >
              <Pencil :size="16" />Editar</button
            ><button
              class="button photo-button"
              type="button"
              :disabled="uploading || planAccess.readOnly(property.id)"
              @click="photoInput?.click()"
            >
              <Camera :size="16" />{{ uploading ? "Subiendo…" : "Añadir foto" }}
            </button>
            <button
              class="button photo-button"
              type="button"
              :disabled="propertyActionBusy || planAccess.readOnly(property.id)"
              @click="archiveProperty"
            >
              <Archive :size="16" />Archivar
            </button>
          </div>
        </div>
      </section>
      <input
        ref="photoInput"
        type="file"
        accept="image/jpeg,image/png,image/webp,image/gif,image/bmp"
        hidden
        :disabled="uploading"
        @change="upload"
      />
      <div
        v-if="property.photos?.length"
        class="property-gallery"
        aria-label="Fotografías de la propiedad"
      >
        <button
          v-for="(photo, index) in property.photos"
          :key="photo.id"
          type="button"
          :class="{ selected: selectedPhoto?.id === photo.id }"
          :aria-pressed="selectedPhoto?.id === photo.id"
          :aria-label="'Ver fotografía ' + (index + 1)"
          @click="selectedPhotoId = photo.id"
        >
          <PropertyImage
            :property="property"
            :photo="photo"
            :show-label="false"
          />
        </button>
        <button
          type="button"
          class="property-gallery-add"
          :disabled="uploading"
          aria-label="Añadir otra fotografía"
          @click="photoInput?.click()"
        >
          <ImagePlus :size="22" />
        </button>
      </div>
      <p class="property-photo-help">
        {{
          property.photos?.length
            ? property.photos.length +
              (property.photos.length === 1 ? " fotografía" : " fotografías") +
              " · La primera es la portada de tu cartera."
            : "Hazla tuya con una fotografía del inmueble."
        }}
        JPG, PNG o WebP, entre otros · máximo 6 MB.
      </p>
      <div v-if="selectedPhoto" class="property-photo-actions">
        <button
          v-if="!selectedPhoto.is_cover"
          class="button secondary"
          type="button"
          :disabled="propertyActionBusy"
          @click="makeCover"
        >
          <Star :size="15" />Usar como portada
        </button>
        <span v-else class="pill"><Star :size="14" />Foto de portada</span>
        <button
          class="button secondary"
          type="button"
          :disabled="propertyActionBusy"
          @click="removePhoto"
        >
          <Trash2 :size="15" />Eliminar foto
        </button>
      </div>

      <section class="asset-metrics" aria-label="Resumen de la propiedad">
        <article>
          <span>Valor estimado</span
          ><strong>{{
            property.current_value !== null
              ? money(property.current_value)
              : "Sin valorar"
          }}</strong
          ><small>Tu última valoración</small>
        </article>
        <article>
          <span>Renta mensual</span
          ><strong>{{
            activeLease ? money(activeLease.monthly_rent) : "—"
          }}</strong
          ><small>{{
            activeLease ? "Según el contrato activo" : "Sin contrato activo"
          }}</small>
        </article>
        <article>
          <span>Rentabilidad bruta</span
          ><strong>{{ yieldRate !== null ? yieldRate + " %" : "—" }}</strong
          ><small>Renta anual / valor estimado</small>
        </article>
        <article>
          <span>Resultado registrado</span
          ><strong :class="{ 'is-negative': income - expenses < 0 }">{{
            money(income - expenses)
          }}</strong
          ><small>Ingresos cobrados − gastos pagados</small>
        </article>
      </section>
      <section class="detail-grid property-detail-grid">
        <article class="panel detail-panel">
          <header>
            <span class="property-panel-icon"><Home :size="20" /></span>
            <div>
              <p class="eyebrow">El inmueble</p>
              <h2>Información patrimonial</h2>
            </div>
          </header>
          <dl>
            <div>
              <dt>Precio de compra</dt>
              <dd>
                {{
                  property.purchase_price !== null
                    ? money(property.purchase_price)
                    : "Sin indicar"
                }}
              </dd>
            </div>
            <div>
              <dt>Fecha de compra</dt>
              <dd>
                {{
                  property.purchase_date
                    ? date(property.purchase_date)
                    : "Sin indicar"
                }}
              </dd>
            </div>
            <div>
              <dt>Gastos de adquisición</dt>
              <dd>{{ money(property.acquisition_costs) }}</dd>
            </div>
            <div>
              <dt>Deuda pendiente</dt>
              <dd>{{ money(property.outstanding_debt) }}</dd>
            </div>
            <div>
              <dt>Superficie</dt>
              <dd>
                {{
                  Number(property.area) > 0
                    ? property.area + " m²"
                    : "Sin indicar"
                }}
              </dd>
            </div>
            <div>
              <dt>Tipo de propiedad</dt>
              <dd>{{ types[property.type] || "Otro" }}</dd>
            </div>
            <div v-if="property.bedrooms !== null">
              <dt>Dormitorios</dt>
              <dd>{{ property.bedrooms }}</dd>
            </div>
            <div v-if="property.bathrooms !== null">
              <dt>Baños</dt>
              <dd>{{ property.bathrooms }}</dd>
            </div>
          </dl>
          <p
            v-if="property.notes"
            class="property-panel-description property-notes"
          >
            {{ property.notes }}
          </p>
          <button
            type="button"
            class="property-text-button"
            @click="startEditing"
          >
            Completar información<ArrowUpRight :size="15" />
          </button>
        </article>
        <article class="panel detail-panel">
          <header>
            <span class="property-panel-icon"><KeyRound :size="20" /></span>
            <div>
              <p class="eyebrow">Alquiler</p>
              <h2>
                {{
                  activeLease
                    ? "Tu contrato activo"
                    : "Listo para un nuevo alquiler"
                }}
              </h2>
            </div>
          </header>
          <template v-if="activeLease"
            ><strong class="property-tenant-name">{{
              activeLease.participants
                ?.map((participant) => participant.name)
                .join(", ") || "Contrato activo"
            }}</strong>
            <p class="property-panel-description">
              {{ date(activeLease.start_date) }} ·
              {{ date(activeLease.end_date) }}
            </p>
            <RouterLink
              class="property-panel-link"
              :to="'/leases/' + activeLease.id"
              >Ver contrato<ArrowUpRight :size="15" /></RouterLink></template
          ><template v-else
            ><p class="property-panel-description">
              Asocia un inquilino y un contrato para empezar a controlar la
              renta.
            </p>
            <RouterLink
              class="button secondary"
              :to="`/leases?new=1&property=${property.id}`"
              >Crear alquiler<ArrowUpRight :size="15" /></RouterLink
          ></template>
        </article>
        <article class="panel detail-panel">
          <header>
            <span class="property-panel-icon"><WalletCards :size="20" /></span>
            <div>
              <p class="eyebrow">Finanzas</p>
              <h2>El balance de tu propiedad</h2>
            </div>
          </header>
          <dl>
            <div>
              <dt>Ingresos cobrados</dt>
              <dd>{{ money(income) }}</dd>
            </div>
            <div>
              <dt>Gastos pagados</dt>
              <dd>{{ money(expenses) }}</dd>
            </div>
          </dl>
          <p class="property-panel-description">
            Todos los movimientos registrados, sin límite de fecha.
          </p>
          <RouterLink class="property-panel-link" to="/finance"
            >Ver movimientos<ArrowUpRight :size="15"
          /></RouterLink>
        </article>
        <article class="panel detail-panel">
          <header>
            <span
              class="property-panel-icon"
              :class="{ 'needs-attention': openIssues }"
              ><Wrench :size="20"
            /></span>
            <div>
              <p class="eyebrow">Al día</p>
              <h2>
                {{
                  openIssues
                    ? openIssues +
                      (openIssues === 1
                        ? " incidencia abierta"
                        : " incidencias abiertas")
                    : "Sin incidencias abiertas"
                }}
              </h2>
            </div>
          </header>
          <p class="property-panel-description">
            {{
              openIssues
                ? "Consulta los detalles y sigue la evolución de cada incidencia."
                : "Aquí tendrás a mano cualquier reparación o asunto pendiente."
            }}
          </p>
          <RouterLink
            class="property-panel-link"
            :to="'/issues?property=' + property.id"
            >Gestionar incidencias<ArrowUpRight :size="15"
          /></RouterLink>
        </article>
        <article class="panel detail-panel property-documents-panel">
          <header>
            <span class="property-panel-icon"><FileText :size="20" /></span>
            <div>
              <p class="eyebrow">Todo en su sitio</p>
              <h2>
                {{ property.documents?.length || 0 }}
                {{
                  property.documents?.length === 1
                    ? "documento guardado"
                    : "documentos guardados"
                }}
              </h2>
            </div>
          </header>
          <p class="property-panel-description">
            Contratos, facturas y documentos del inmueble, juntos y accesibles.
          </p>
          <RouterLink
            class="property-panel-link"
            :to="'/documents?property=' + property.id"
            >Abrir documentos<ArrowUpRight :size="15"
          /></RouterLink>
        </article>
      </section>
    </template>

    <Teleport to="body">
      <div v-if="editing" class="drawer-bg" @click.self="closeEditing">
        <form
          ref="drawer"
          class="drawer property-drawer"
          role="dialog"
          aria-modal="true"
          aria-labelledby="edit-property-title"
          tabindex="-1"
          :aria-busy="saving"
          @submit.prevent="saveProperty"
        >
          <header class="property-drawer-heading">
            <div>
              <p class="eyebrow">Los detalles importan</p>
              <h2 id="edit-property-title">Editar propiedad</h2>
            </div>
            <button
              type="button"
              class="property-icon-button"
              aria-label="Cerrar edición"
              :disabled="saving"
              @click="closeEditing"
            >
              <X :size="20" />
            </button>
          </header>
          <p v-if="saveError" class="error" role="alert">{{ saveError }}</p>
          <label
            >Nombre<input
              v-model.trim="editForm.name"
              required
              maxlength="120"
              :aria-invalid="Boolean(fieldErrors.name)"
          /></label>
          <label
            >Tipo<select v-model="editForm.type">
              <option
                v-for="(label, value) in types"
                :key="value"
                :value="value"
              >
                {{ label }}
              </option>
            </select></label
          >
          <label
            >Dirección<input
              v-model.trim="editForm.address_line"
              required
              maxlength="255"
              autocomplete="street-address"
              :aria-invalid="Boolean(fieldErrors.address_line)"
          /></label>
          <label
            >Ciudad<input
              v-model.trim="editForm.city"
              maxlength="100"
              autocomplete="address-level2"
          /></label>
          <div class="property-form-row">
            <label
              >Provincia<input
                v-model.trim="editForm.province"
                maxlength="100" /></label
            ><label
              >Código postal<input
                v-model.trim="editForm.postal_code"
                maxlength="12"
            /></label>
          </div>
          <label
            >Fecha de compra<input v-model="editForm.purchase_date" type="date"
          /></label>
          <div class="property-form-row">
            <label
              >Precio de compra (€)<input
                v-model="editForm.purchase_price"
                type="number"
                min="0"
                step="0.01"
                inputmode="decimal" /></label
            ><label
              >Valor estimado (€)<input
                v-model="editForm.current_value"
                type="number"
                min="0"
                step="0.01"
                inputmode="decimal"
            /></label>
          </div>
          <label
            >Gastos de adquisición (€)<input
              v-model="editForm.acquisition_costs"
              type="number"
              min="0"
              step="0.01"
              inputmode="decimal"
          /></label>
          <label
            >Fecha de valoración<input
              v-model="editForm.valuation_date"
              type="date"
          /></label>
          <div class="property-form-row">
            <label
              >Deuda pendiente (€)<input
                v-model="editForm.outstanding_debt"
                type="number"
                min="0"
                step="0.01"
                inputmode="decimal" /></label
            ><label
              >Superficie (m²)<input
                v-model="editForm.area"
                type="number"
                min="0"
                step="0.01"
                inputmode="decimal"
            /></label>
          </div>
          <div class="property-form-row">
            <label
              >Dormitorios<input
                v-model="editForm.bedrooms"
                type="number"
                min="0" /></label
            ><label
              >Baños<input v-model="editForm.bathrooms" type="number" min="0"
            /></label>
          </div>
          <label
            >Notas<textarea v-model="editForm.notes" rows="4"></textarea>
          </label>
          <ul
            v-if="Object.keys(fieldErrors).length"
            class="property-validation-errors"
          >
            <li v-for="(messages, field) in fieldErrors" :key="field">
              {{ messages[0] }}
            </li>
          </ul>
          <footer>
            <button
              class="button secondary"
              type="button"
              :disabled="saving"
              @click="closeEditing"
            >
              Cancelar</button
            ><button class="button primary" :disabled="saving">
              {{ saving ? "Guardando…" : "Guardar cambios" }}
            </button>
          </footer>
        </form>
      </div>
    </Teleport>
    <ConfirmDialog
      :dialog="confirmation.dialog.value"
      @confirm="confirmation.confirm"
      @cancel="confirmation.cancel"
    />
  </main>
</template>
