<script setup>
import { computed, onBeforeUnmount, ref, useId, watch } from "vue";
import {
  ArrowUpRight,
  Building2,
  ChevronLeft,
  ChevronRight,
  RefreshCw,
  Search,
  X,
} from "@lucide/vue";
import api from "../api";
import {
  filterSceneProperties,
  loadSceneProperties,
  sceneBuildingFrame,
  sceneGroupSize,
  sceneSelectionId,
} from "../portfolioScene.js";
import PortfolioBuilding from "./PortfolioBuilding.vue";
import PortfolioPropertyDetails from "./PortfolioPropertyDetails.vue";
import "../portfolio-scene.css";

const props = defineProps({
  portfolioId: { type: [Number, String], default: null },
  currency: { type: String, default: "EUR" },
});
const detailId = `portfolio-detail-${useId()}`;
const items = ref([]);
const query = ref("");
const page = ref(0);
const selectedId = ref(null);
const loading = ref(false);
const error = ref("");
let controller;
const filtered = computed(() =>
  filterSceneProperties(items.value, query.value),
);
const pageCount = computed(() =>
  Math.max(1, Math.ceil(filtered.value.length / sceneGroupSize)),
);
const visible = computed(() =>
  filtered.value.slice(
    page.value * sceneGroupSize,
    (page.value + 1) * sceneGroupSize,
  ),
);
const selected = computed(() =>
  visible.value.find((property) => property.id === selectedId.value),
);
const buildingFrame = computed(() => sceneBuildingFrame(visible.value.length));
function selectProperty(id) {
  if (visible.value.some((property) => property.id === id))
    selectedId.value = id;
}
watch(query, () => {
  page.value = 0;
  selectedId.value = null;
});
watch(
  [visible, pageCount],
  () => {
    if (page.value >= pageCount.value) page.value = pageCount.value - 1;
    selectedId.value = sceneSelectionId(visible.value, selectedId.value);
  },
  { immediate: true },
);

async function load() {
  controller?.abort();
  const request = new AbortController();
  controller = request;
  items.value = [];
  selectedId.value = null;
  page.value = 0;
  query.value = "";
  error.value = "";
  if (!props.portfolioId) {
    loading.value = false;
    return;
  }
  loading.value = true;
  try {
    const result = await loadSceneProperties(api, request.signal);
    if (!request.signal.aborted) items.value = result;
  } catch {
    if (!request.signal.aborted)
      error.value = "No hemos podido cargar la vista de tu cartera.";
  } finally {
    if (!request.signal.aborted) loading.value = false;
  }
}
watch(() => props.portfolioId, load, { immediate: true });
onBeforeUnmount(() => controller?.abort());
</script>

<template>
  <section
    class="portfolio-scene"
    aria-labelledby="portfolio-scene-title"
    :aria-busy="loading"
  >
    <header class="portfolio-scene-heading">
      <div>
        <h2 id="portfolio-scene-title">Tu cartera</h2>
      </div>
      <div class="portfolio-scene-actions">
        <RouterLink
          class="portfolio-scene-all"
          to="/properties"
          aria-label="Ver todas las propiedades"
          >Ver todas<ArrowUpRight :size="16" aria-hidden="true"
        /></RouterLink>
      </div>
    </header>
    <div v-if="loading" class="portfolio-scene-message" role="status">
      <Building2 :size="26" aria-hidden="true" />
      <p>Preparando tu cartera…</p>
    </div>
    <div v-else-if="error" class="portfolio-scene-message" role="alert">
      <p>{{ error }}</p>
      <button type="button" class="button secondary" @click="load">
        <RefreshCw :size="16" aria-hidden="true" />Reintentar
      </button>
    </div>
    <div v-else-if="!items.length" class="portfolio-scene-message">
      <p>Tu primer inmueble dará forma a esta vista.</p>
      <RouterLink class="button secondary" to="/properties?new=1"
        >Añadir inmueble</RouterLink
      >
    </div>
    <template v-else>
      <div class="portfolio-scene-toolbar">
        <p>
          <strong>{{ items.length }}</strong>
          {{ items.length === 1 ? "inmueble" : "inmuebles" }}
        </p>
        <label
          v-if="items.length > sceneGroupSize"
          class="portfolio-scene-search"
          ><Search :size="17" aria-hidden="true" /><span
            class="portfolio-sr-only"
            >Buscar inmueble por nombre, dirección o ciudad</span
          ><input
            v-model="query"
            type="search"
            placeholder="Buscar"
            autocomplete="off" /><button
            v-if="query"
            type="button"
            aria-label="Limpiar búsqueda"
            @click="query = ''"
          >
            <X :size="16" aria-hidden="true" /></button
        ></label>
      </div>
      <div
        v-if="visible.length"
        class="portfolio-scene-layout"
        :style="{
          '--scene-building-height': `${buildingFrame.desktopHeight}px`,
          '--scene-building-height-mobile': `${buildingFrame.mobileHeight}px`,
        }"
      >
        <div class="portfolio-scene-illustration">
          <PortfolioBuilding
            :key="`${props.portfolioId}-${page}-${visible.map((item) => item.id).join('-')}`"
            :properties="visible"
            :selected-id="selectedId"
            :detail-id="detailId"
            @select="selectProperty"
          />
        </div>
        <div class="portfolio-scene-inspector">
          <div
            class="portfolio-scene-choices"
            role="group"
            aria-label="Inmuebles del grupo"
          >
            <div
              v-for="property in visible"
              :key="property.id"
              class="portfolio-scene-choice"
              :class="{ 'is-open': selectedId === property.id }"
            >
              <button
                :data-property-id="property.id"
                type="button"
                :aria-expanded="selectedId === property.id"
                :aria-controls="
                  selectedId === property.id ? detailId : undefined
                "
                :title="property.name"
                @click="selectProperty(property.id)"
              >
                <strong class="portfolio-choice-name">{{
                  property.name
                }}</strong>
                <ArrowUpRight :size="15" aria-hidden="true" />
              </button>
            </div>
          </div>
        </div>
        <div class="portfolio-scene-panel">
          <div v-if="selected" :id="detailId" class="portfolio-selected">
            <header class="portfolio-selected-heading">
              <h3>{{ selected.name }}</h3>
            </header>
            <PortfolioPropertyDetails
              :property="selected"
              :currency="currency"
            />
          </div>
        </div>
      </div>
      <div v-else class="portfolio-scene-message" role="status">
        <p>No hay inmuebles que coincidan con «{{ query }}».</p>
        <button type="button" class="button secondary" @click="query = ''">
          Ver todos
        </button>
      </div>
      <nav
        v-if="pageCount > 1"
        class="portfolio-scene-pagination"
        aria-label="Grupos de inmuebles"
      >
        <button
          type="button"
          :disabled="page === 0"
          aria-label="Grupo anterior"
          @click="page--"
        >
          <ChevronLeft :size="18" aria-hidden="true" />Anterior
        </button>
        <span role="status"
          >{{ page * sceneGroupSize + 1 }}–{{
            Math.min((page + 1) * sceneGroupSize, filtered.length)
          }}
          de {{ filtered.length }}</span
        >
        <button
          type="button"
          :disabled="page === pageCount - 1"
          aria-label="Grupo siguiente"
          @click="page++"
        >
          Siguiente<ChevronRight :size="18" aria-hidden="true" />
        </button>
      </nav>
    </template>
  </section>
</template>
