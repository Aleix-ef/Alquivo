<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from "vue";
import {
  Building2,
  Plus,
  Search,
  MapPin,
  ArrowUpRight,
  X,
  SlidersHorizontal,
  RefreshCw,
} from "@lucide/vue";
import { useRoute, useRouter } from "vue-router";
import api from "../api";
import { usePlanAccess } from "../stores/planAccess";
const planAccess = usePlanAccess();
import { fetchAllPages } from "../pagination";
import PropertyImage from "../components/PropertyImage.vue";
import { useDialog } from "../composables/useDialog";
import "../property-experience.css";

const route = useRoute();
const router = useRouter();
const items = ref([]);
const loading = ref(true);
const saving = ref(false);
const loadError = ref("");
const error = ref("");
const fieldErrors = ref({});
const search = ref("");
const filter = ref("all");
const drawer = ref(null);
const show = computed(() => route.query.new === "1");
const newForm = () => ({
  name: "",
  type: "housing",
  address_line: "",
  city: "",
  purchase_price: null,
  current_value: null,
});
const form = ref(newForm());
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
const activeLease = (property) =>
  property.leases?.find((lease) => lease.status === "active");
const rented = computed(() => items.value.filter(activeLease).length);
const totalValue = computed(() =>
  items.value.reduce(
    (total, property) => total + Number(property.current_value || 0),
    0,
  ),
);
const normalize = (value) =>
  value
    .toLocaleLowerCase("es")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "");
const visibleItems = computed(() =>
  items.value.filter((property) => {
    const matchesStatus =
      filter.value === "all" ||
      (filter.value === "rented"
        ? activeLease(property)
        : !activeLease(property));
    return (
      matchesStatus &&
      normalize(
        [
          property.name,
          property.address_line,
          property.city,
          types[property.type],
        ]
          .filter(Boolean)
          .join(" "),
      ).includes(normalize(search.value.trim()))
    );
  }),
);
let loadController;

async function load() {
  loadController?.abort();
  const controller = new AbortController();
  loadController = controller;
  loading.value = true;
  loadError.value = "";
  try {
    const result = await fetchAllPages(api, "/properties", {
      signal: controller.signal,
    });
    if (!controller.signal.aborted) items.value = result;
  } catch {
    if (!controller.signal.aborted)
      loadError.value =
        "No hemos podido cargar tus propiedades. Inténtalo de nuevo.";
  } finally {
    if (!controller.signal.aborted) loading.value = false;
  }
}

function close() {
  if (saving.value) return;
  const query = { ...route.query };
  delete query.new;
  router.replace({ path: "/properties", query });
}
useDialog(show, drawer, close);
watch(show, (open) => {
  if (open) {
    form.value = newForm();
    error.value = "";
    fieldErrors.value = {};
  }
});

async function save() {
  if (saving.value) return;
  saving.value = true;
  error.value = "";
  fieldErrors.value = {};
  try {
    const { data } = await api.post("/properties", form.value);
    await router.push(`/properties/${data.id}?tab=alquiler`);
  } catch (exception) {
    fieldErrors.value = exception.response?.data?.errors || {};
    error.value =
      exception.response?.data?.message ||
      "No se pudo guardar la propiedad. Tus datos siguen aquí para volver a intentarlo.";
  } finally {
    saving.value = false;
  }
}
onMounted(load);
onBeforeUnmount(() => loadController?.abort());
</script>

<template>
  <main class="page properties-page">
    <header class="heading" data-tour="properties">
      <div>
        <p class="eyebrow">Tu patrimonio, en perspectiva</p>
        <h1>Propiedades</h1>
        <p>Cada espacio, su historia. Todos bajo control.</p>
      </div>
      <RouterLink
        class="button primary"
        :to="{ query: { ...route.query, new: '1' } }"
        ><Plus :size="17" />Nueva propiedad</RouterLink
      >
    </header>

    <template v-if="!loading && !loadError && items.length">
      <section class="portfolio-strip" aria-label="Resumen de propiedades">
        <div>
          <span>Valor estimado</span><strong>{{ money(totalValue) }}</strong
          ><small>Según tus valoraciones</small>
        </div>
        <div>
          <span>Propiedades</span><strong>{{ items.length }}</strong
          ><small>En tu cartera</small>
        </div>
        <div>
          <span>Con alquiler activo</span
          ><strong
            >{{ rented }}<em>/ {{ items.length }}</em></strong
          ><small>{{ items.length - rented }} sin alquiler activo</small>
        </div>
      </section>
      <div class="property-toolbar">
        <label class="property-search"
          ><Search :size="18" /><input
            v-model="search"
            type="search"
            placeholder="Busca por nombre, dirección o ciudad"
            aria-label="Buscar propiedades"
        /></label>
        <div
          class="property-filters"
          role="group"
          aria-label="Filtrar propiedades"
        >
          <button
            type="button"
            :class="{ active: filter === 'all' }"
            :aria-pressed="filter === 'all'"
            @click="filter = 'all'"
          >
            Todas <span>{{ items.length }}</span>
          </button>
          <button
            type="button"
            :class="{ active: filter === 'rented' }"
            :aria-pressed="filter === 'rented'"
            @click="filter = 'rented'"
          >
            Alquiladas
          </button>
          <button
            type="button"
            :class="{ active: filter === 'available' }"
            :aria-pressed="filter === 'available'"
            @click="filter = 'available'"
          >
            Sin alquiler
          </button>
        </div>
      </div>
      <p class="property-result-count" aria-live="polite">
        {{ visibleItems.length }}
        {{ visibleItems.length === 1 ? "propiedad" : "propiedades"
        }}{{ search ? " encontradas" : " en tu cartera" }}
      </p>
      <section
        v-if="visibleItems.length"
        class="property-card-grid"
        aria-label="Tus propiedades"
      >
        <RouterLink
          v-for="property in visibleItems"
          :key="property.id"
          :to="'/properties/' + property.id"
          class="property-card"
        >
          <div class="property-card-cover">
            <PropertyImage :property="property" /><span
              class="property-type-tag"
              >{{ types[property.type] || "Propiedad" }}</span
            ><span class="property-card-open" aria-hidden="true"
              ><ArrowUpRight :size="19"
            /></span>
          </div>
          <div class="property-card-body">
            <span
              class="property-status"
              :class="{ rented: activeLease(property) }"
              ><span aria-hidden="true"></span
              >{{
                activeLease(property) ? "Alquilada" : "Sin alquiler activo"
              }}</span
            >
            <h2>{{ property.name }}</h2>
            <p v-if="planAccess.readOnly(property.id)">
              Modo consulta · datos conservados
            </p>
            <p class="property-card-address">
              <MapPin :size="14" /><span>{{
                [property.address_line, property.city]
                  .filter(Boolean)
                  .join(" · ")
              }}</span>
            </p>
            <div class="property-card-numbers">
              <div>
                <span>Valor estimado</span
                ><strong>{{
                  property.current_value !== null
                    ? money(property.current_value)
                    : "Sin valorar"
                }}</strong>
              </div>
              <div>
                <span>Renta mensual</span
                ><strong>{{
                  activeLease(property)
                    ? money(activeLease(property).monthly_rent)
                    : "—"
                }}</strong>
              </div>
            </div>
          </div>
        </RouterLink>
      </section>
      <section v-else class="empty property-empty">
        <SlidersHorizontal :size="32" />
        <h2>No hay propiedades con estos filtros</h2>
        <p>Prueba con otro nombre o amplía la selección.</p>
        <button
          class="button secondary"
          type="button"
          @click="
            search = '';
            filter = 'all';
          "
        >
          Limpiar filtros
        </button>
      </section>
    </template>
    <section
      v-else-if="loading"
      class="property-card-grid"
      aria-label="Cargando propiedades"
      aria-busy="true"
    >
      <div v-for="index in 3" :key="index" class="property-skeleton">
        <div></div>
        <span></span><span></span><span></span>
      </div>
    </section>
    <section v-else-if="loadError" class="empty property-empty" role="alert">
      <Building2 :size="35" />
      <h2>No se han cargado las propiedades</h2>
      <p>{{ loadError }}</p>
      <button class="button secondary" type="button" @click="load">
        <RefreshCw :size="16" />Volver a intentar
      </button>
    </section>
    <section v-else class="empty property-empty">
      <div class="property-empty-art">
        <PropertyImage
          :property="{ name: 'Tu primera propiedad', type: 'housing' }"
          :show-label="false"
        />
      </div>
      <h2>Tu patrimonio empieza aquí</h2>
      <p>
        Añade tu primera propiedad. Solo necesitas un nombre y una dirección;
        podrás completar el resto después.
      </p>
      <RouterLink class="button primary" to="?new=1"
        ><Plus :size="16" />Añadir mi primera propiedad</RouterLink
      >
    </section>

    <Teleport to="body">
      <div v-if="show" class="drawer-bg" @click.self="close">
        <form
          ref="drawer"
          class="drawer property-drawer"
          role="dialog"
          aria-modal="true"
          aria-labelledby="new-property-title"
          tabindex="-1"
          :aria-busy="saving"
          @submit.prevent="save"
        >
          <header class="property-drawer-heading">
            <div>
              <p class="eyebrow">Un nuevo espacio</p>
              <h2 id="new-property-title">Añade una propiedad</h2>
            </div>
            <button
              class="property-icon-button"
              type="button"
              aria-label="Cerrar formulario"
              :disabled="saving"
              @click="close"
            >
              <X :size="20" />
            </button>
          </header>
          <p class="property-form-intro">
            Empieza con lo esencial. Las fotos y los detalles los añadiremos
            después.
          </p>
          <p v-if="error" class="error" role="alert">{{ error }}</p>
          <label
            >Nombre<input
              v-model.trim="form.name"
              required
              maxlength="120"
              placeholder="Piso Gran Vía"
              :aria-invalid="Boolean(fieldErrors.name)"
              :aria-describedby="
                fieldErrors.name ? 'property-name-error' : undefined
              "
            /><small
              v-if="fieldErrors.name"
              id="property-name-error"
              class="property-field-error"
              >{{ fieldErrors.name[0] }}</small
            ></label
          >
          <label
            >Tipo de propiedad<select v-model="form.type">
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
              v-model.trim="form.address_line"
              required
              maxlength="255"
              autocomplete="street-address"
              placeholder="Calle, número y puerta"
              :aria-invalid="Boolean(fieldErrors.address_line)"
            /><small
              v-if="fieldErrors.address_line"
              class="property-field-error"
              >{{ fieldErrors.address_line[0] }}</small
            ></label
          >
          <label
            >Ciudad <span class="property-optional">Opcional</span
            ><input
              v-model.trim="form.city"
              maxlength="100"
              autocomplete="address-level2"
              placeholder="Barcelona"
          /></label>
          <div class="property-form-row">
            <label
              >Precio de compra (€)<input
                v-model="form.purchase_price"
                type="number"
                min="0"
                step="0.01"
                inputmode="decimal"
                placeholder="0" /></label
            ><label
              >Valor estimado (€)<input
                v-model="form.current_value"
                type="number"
                min="0"
                step="0.01"
                inputmode="decimal"
                placeholder="0"
            /></label>
          </div>
          <footer>
            <button
              class="button secondary"
              type="button"
              :disabled="saving"
              @click="close"
            >
              Cancelar</button
            ><button class="button primary" :disabled="saving">
              {{ saving ? "Guardando…" : "Crear propiedad"
              }}<ArrowUpRight :size="16" />
            </button>
          </footer>
        </form>
      </div>
    </Teleport>
  </main>
</template>
