<script setup>
import { computed, ref, watch } from "vue";
import { ImagePlus, ImageOff } from "@lucide/vue";
import api from "../api";
import "../property-experience.css";

const props = defineProps({
  property: { type: Object, required: true },
  photo: { type: Object, default: null },
  showLabel: { type: Boolean, default: true },
});
const source = ref("");
const loading = ref(false);
const failed = ref(false);
const selectedPhoto = computed(
  () =>
    props.photo ||
    props.property.photos?.find((photo) => photo.is_cover) ||
    props.property.photos?.[0],
);
const buildingType = computed(() => props.property.type || "housing");
const highRise = computed(() =>
  ["building", "office"].includes(buildingType.value),
);
const lowRise = computed(() =>
  ["garage", "storage", "commercial"].includes(buildingType.value),
);

watch(
  () => selectedPhoto.value?.id,
  async (id, _previous, onCleanup) => {
    const controller = new AbortController();
    let objectUrl;
    source.value = "";
    failed.value = false;
    loading.value = Boolean(id);
    onCleanup(() => {
      controller.abort();
      if (objectUrl) URL.revokeObjectURL(objectUrl);
    });
    if (!id) return;
    try {
      const response = await api.get(`/property-photos/${id}`, {
        responseType: "blob",
        signal: controller.signal,
      });
      if (controller.signal.aborted) return;
      objectUrl = URL.createObjectURL(response.data);
      source.value = objectUrl;
    } catch {
      if (!controller.signal.aborted) failed.value = true;
    } finally {
      if (!controller.signal.aborted) loading.value = false;
    }
  },
  { immediate: true },
);
</script>

<template>
  <div
    class="property-image"
    :class="{ 'is-loading': loading, 'has-photo': source && !failed }"
    :aria-busy="loading"
  >
    <svg
      v-if="!source || failed"
      class="property-illustration"
      viewBox="0 0 640 400"
      fill="none"
      aria-hidden="true"
      preserveAspectRatio="xMidYMid slice"
    >
      <rect width="640" height="400" fill="var(--illustration-sky)" />
      <circle cx="514" cy="90" r="48" fill="var(--illustration-sun)" />
      <path
        d="M0 247C139 199 222 272 338 232C445 195 534 218 640 175V400H0Z"
        fill="var(--illustration-horizon)"
      />
      <path
        d="M0 316C174 291 375 314 640 267V400H0Z"
        fill="var(--illustration-ground)"
      />
      <ellipse
        cx="329"
        cy="342"
        rx="201"
        ry="21"
        fill="var(--illustration-shadow)"
      />
      <template v-if="buildingType === 'land'">
        <path
          d="M126 289L348 221L517 275L296 356Z"
          fill="var(--illustration-wall)"
        />
        <path
          d="M152 290L348 233L487 276L295 342Z"
          fill="var(--illustration-leaf)"
        />
        <path
          d="M188 310L383 245M237 327L430 260M236 266L381 307M291 249L439 286"
          stroke="var(--illustration-ground)"
          stroke-width="4"
        />
        <path
          d="M125 290V264M348 223V197M518 276V250M296 357V331"
          stroke="var(--illustration-frame)"
          stroke-width="7"
          stroke-linecap="round"
        />
      </template>
      <template v-else-if="highRise">
        <path
          d="M230 104L370 82L435 119V321L293 346L230 307Z"
          fill="var(--illustration-side)"
        />
        <path
          d="M230 104L370 82V321L230 307Z"
          fill="var(--illustration-wall)"
        />
        <path
          d="M250 121L348 107V303L250 296Z"
          fill="var(--illustration-glass)"
        />
        <path
          d="M282 116V300M315 111V302M250 161L348 153M250 205L348 201M250 250H348"
          stroke="var(--illustration-wall)"
          stroke-width="9"
        />
        <path
          d="M390 126L419 142V299L390 309Z"
          fill="var(--illustration-frame)"
        />
        <path
          d="M385 173L425 180M385 215L425 218M385 260H425"
          stroke="var(--illustration-side)"
          stroke-width="9"
        />
      </template>
      <template v-else-if="lowRise">
        <path
          d="M170 202L358 174L461 221V316L271 349L170 302Z"
          fill="var(--illustration-side)"
        />
        <path
          d="M170 202L358 174V316L170 302Z"
          fill="var(--illustration-wall)"
        />
        <path
          d="M190 226L334 208V302L190 291Z"
          fill="var(--illustration-glass)"
        />
        <path
          v-if="buildingType === 'garage' || buildingType === 'storage'"
          d="M190 244L334 232M190 263L334 255M190 280L334 279"
          stroke="var(--illustration-frame)"
          stroke-width="5"
        />
        <path
          v-else
          d="M261 217V297"
          stroke="var(--illustration-wall)"
          stroke-width="8"
        />
        <path
          d="M158 199L358 168L470 216L455 232L357 187L173 215Z"
          fill="var(--illustration-frame)"
        />
        <path
          d="M382 228L432 252V307L382 316Z"
          fill="var(--illustration-glass)"
        />
      </template>
      <template v-else>
        <path
          d="M183 183L340 146L446 207V308L289 347L183 286Z"
          fill="var(--illustration-side)"
        />
        <path
          d="M183 183L340 146V308L183 286Z"
          fill="var(--illustration-wall)"
        />
        <path
          d="M174 181L269 106L349 141L340 159L267 131L193 193Z"
          fill="var(--illustration-frame)"
        />
        <path
          d="M269 106L375 165L455 201L349 141Z"
          fill="var(--illustration-roof)"
        />
        <path
          d="M210 204L248 196V242L210 245ZM274 190L311 182V241L274 242Z"
          fill="var(--illustration-glass)"
        />
        <path
          d="M271 260L311 257V303L271 297Z"
          fill="var(--illustration-frame)"
        />
        <path
          d="M369 212L417 237V277L369 256Z"
          fill="var(--illustration-glass)"
        />
        <path
          d="M389 222V266"
          stroke="var(--illustration-side)"
          stroke-width="5"
        />
      </template>
      <path
        d="M124 303V260M511 322V279"
        stroke="var(--illustration-frame)"
        stroke-width="8"
        stroke-linecap="round"
      />
      <ellipse
        cx="124"
        cy="244"
        rx="30"
        ry="42"
        fill="var(--illustration-leaf)"
      />
      <ellipse
        cx="512"
        cy="265"
        rx="24"
        ry="36"
        fill="var(--illustration-leaf)"
      />
      <path
        d="M87 330H151M479 346H547"
        stroke="var(--illustration-shadow)"
        stroke-width="7"
        stroke-linecap="round"
      />
    </svg>
    <img
      v-else
      class="property-photograph"
      :src="source"
      :alt="`Fotografía de ${property.name}`"
      loading="lazy"
      decoding="async"
      @error="failed = true"
    />
    <span v-if="showLabel && (!source || failed)" class="property-image-label">
      <ImageOff v-if="failed" :size="13" /><ImagePlus v-else :size="13" />
      {{
        loading
          ? "Cargando fotografía…"
          : failed
            ? "Foto no disponible"
            : "Ilustración · añade tu foto"
      }}
    </span>
  </div>
</template>
