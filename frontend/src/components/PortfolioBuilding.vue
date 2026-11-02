<script setup>
import { computed, onMounted, ref, useId } from "vue";
import { sceneBuildingFrame, sceneGroupSize } from "../portfolioScene.js";

const props = defineProps({
  properties: { type: Array, required: true },
  selectedId: { type: [Number, String], default: null },
  detailId: { type: String, required: true },
});
const emit = defineEmits(["select"]);
const id = useId().replaceAll(":", "");
const entered = ref(false);
const floors = computed(() =>
  props.properties
    .slice(0, sceneGroupSize)
    .map((property, index) => ({ property, index }))
    .reverse(),
);
const frame = computed(() => sceneBuildingFrame(floors.value.length));
const shift = computed(() => (floors.value.length - 3) * 88);
const fill = (name) => `url(#${id}-${name})`;
onMounted(() => {
  entered.value = true;
});
</script>

<template>
  <svg
    class="portfolio-building"
    :class="{ 'has-entered': entered }"
    :viewBox="frame.viewBox"
    role="group"
    aria-label="Seleccionar un inmueble en el edificio ilustrado"
  >
    <defs>
      <linearGradient :id="`${id}-wall`" x1="0" y1="0" x2="1" y2="1">
        <stop stop-color="#f3ead6" />
        <stop offset="1" stop-color="#b5b5a6" />
      </linearGradient>
      <linearGradient :id="`${id}-side`" x1="0" y1="0" x2="1" y2="1">
        <stop stop-color="#acb9ad" />
        <stop offset="1" stop-color="#687f78" />
      </linearGradient>
      <linearGradient :id="`${id}-glass`" x1="0" y1="0" x2="1" y2="1">
        <stop stop-color="#142f32" />
        <stop offset=".6" stop-color="#2b5654" />
        <stop offset="1" stop-color="#79a79a" />
      </linearGradient>
      <linearGradient :id="`${id}-base`" x1="0" y1="0" x2="0" y2="1">
        <stop stop-color="#375555" />
        <stop offset="1" stop-color="#172f33" />
      </linearGradient>
      <radialGradient :id="`${id}-shadow`">
        <stop stop-color="#000" stop-opacity=".35" />
        <stop offset="1" stop-color="#000" stop-opacity="0" />
      </radialGradient>
    </defs>
    <g :transform="`translate(0 ${shift})`" aria-hidden="true">
      <ellipse cx="345" cy="493" rx="261" ry="65" :fill="fill('shadow')" />
      <path
        d="M78 433 320 336 566 438 326 540Z"
        :fill="fill('base')"
        stroke="#617e75"
        stroke-width=".8"
      />
      <path
        d="M78 433 326 535 566 438 566 448 326 546 78 444Z"
        fill="#183136"
        stroke="#335451"
        stroke-width=".8"
      />
      <path
        d="m111 430 213-84 212 90M144 445l213-84M180 460l213-84M216 475l213-84M252 490l213-84M288 505l213-84"
        fill="none"
        stroke="#648179"
        stroke-opacity=".2"
      />
    </g>
    <g
      v-for="floor in floors"
      :key="floor.property.id"
      :transform="`translate(0 ${142 + floor.index * 88})`"
    >
      <g
        class="portfolio-floor-arrival"
        :style="{
          '--floor-delay': `${(floors.length - floor.index - 1) * 90}ms`,
        }"
      >
        <g
          class="portfolio-floor"
          :class="{ 'is-selected': floor.property.id === selectedId }"
          role="button"
          tabindex="0"
          :aria-label="floor.property.name"
          :aria-pressed="floor.property.id === selectedId"
          :aria-expanded="floor.property.id === selectedId"
          :aria-controls="
            floor.property.id === selectedId ? detailId : undefined
          "
          @click="emit('select', floor.property.id)"
          @keydown.enter.prevent="emit('select', floor.property.id)"
          @keydown.space.prevent="emit('select', floor.property.id)"
        >
          <title>{{ floor.property.name }}</title>
          <path
            d="M186 0 350-66 480-12 314 54Z"
            fill="#d9dfca"
            stroke="#edf0df"
            stroke-width="1"
          />
          <path d="M186 0 314 54 314 142 186 88Z" :fill="fill('wall')" />
          <path d="M314 54 480-12 480 76 314 142Z" :fill="fill('side')" />
          <path d="M199 17 299 59 299 119 199 77Z" :fill="fill('glass')" />
          <path d="M329 62 464 8 464 65 329 119Z" :fill="fill('glass')" />
          <path
            d="m234 32v60m32-47v60m105-60v57m45-75v57"
            stroke="#b9c8b2"
            stroke-opacity=".6"
            stroke-width="2"
          />
          <path d="m202 21 94 40v6l-94-40Z" fill="#c7e4cc" fill-opacity=".2" />
          <path
            d="M186 78 314 132 480 66 480 77 314 144 186 89Z"
            fill="#e4dfca"
          />
          <path
            d="m186 90 128 54 166-67"
            fill="none"
            stroke="#6a8175"
            stroke-width="2"
          />
          <path
            class="portfolio-floor-edge"
            d="M186 0 314 54 480-12M314 54v88"
            fill="none"
            stroke="#b3f7dc"
            stroke-width="3"
          />
          <g v-if="floor.index === 0" aria-hidden="true">
            <path d="M199-2 350-62 464-15 313 45Z" fill="#6a8a76" />
            <path d="m215-6 135-53 95 40-133 53Z" fill="#537762" />
            <path
              d="M201-1v-13l149-60 113 47v13M350-74v13"
              fill="none"
              stroke="#a8c1ae"
              stroke-width="2"
            />
            <path
              d="M226-24v13m28-25v14m29-26v14m29-26v14m78-19v13m30-1v14m27-2v13"
              stroke="#a8c1ae"
              stroke-width="1.5"
            />
            <path d="m331-21 25-10 25 10-25 10Z" fill="#d9d5ba" />
            <path d="m331-21 25 10 25-10v10l-25 10-25-10Z" fill="#a2ac91" />
            <path d="M355-26v-24" stroke="#4f5c3a" stroke-width="4" />
            <ellipse cx="356" cy="-51" rx="21" ry="27" fill="#90ad7e" />
            <ellipse cx="349" cy="-55" rx="15" ry="23" fill="#afc394" />
          </g>
        </g>
      </g>
    </g>
  </svg>
</template>

<style scoped>
.portfolio-building {
  display: block;
  width: 100%;
  height: 100%;
  overflow: visible;
}
.portfolio-floor {
  cursor: pointer;
  outline: none;
  transition: transform 0.5s cubic-bezier(0.2, 0.8, 0.2, 1);
}
.portfolio-floor.is-selected {
  transform: translate(-18px, -10px);
}
.portfolio-floor-edge {
  opacity: 0;
  transition: opacity 0.3s ease;
}
.portfolio-floor.is-selected .portfolio-floor-edge,
.portfolio-floor:focus-visible .portfolio-floor-edge {
  opacity: 1;
}
.portfolio-floor:hover {
  transform: translate(-12px, -6px);
}
.has-entered .portfolio-floor-arrival {
  animation: portfolio-build 0.75s var(--floor-delay)
    cubic-bezier(0.18, 0.7, 0.2, 1) backwards;
}
@keyframes portfolio-build {
  from {
    opacity: 0;
    transform: translate(20px, -40px);
  }
  to {
    opacity: 1;
    transform: translate(0, 0);
  }
}
@media (prefers-reduced-motion: reduce) {
  .portfolio-floor,
  .portfolio-floor-edge {
    transition: none;
  }
  .has-entered .portfolio-floor-arrival {
    animation: none;
  }
  .portfolio-floor.is-selected,
  .portfolio-floor:hover {
    transform: none;
  }
}
</style>
