<script setup>
import { computed, onMounted, ref } from "vue";
import { ArrowUpRight, RotateCcw } from "@lucide/vue";
import { demoMoney, demoRentals, demoSummary } from "../content/landingDemo.js";

// A local illustration, not a property selector connected to a user's account.
const selected = ref(1);
const entrance = ref(0);
const labels = ["Piso Centro", "Apartamento Mar", "Garaje Norte"];
const rental = computed(() => demoRentals[selected.value]);
const pending = computed(
  () => rental.value.dueCents - rental.value.receivedCents,
);
const collectedPercent =
  (100 * demoSummary.receivedCents) /
  (demoSummary.receivedCents + demoSummary.pendingCents);
onMounted(() => {
  entrance.value = 1;
});
</script>

<template>
  <div class="landing-scene" :class="{ 'scene-enter': entrance > 0 }">
    <div class="scene-topline">
      <span><i aria-hidden="true"></i> Tu patrimonio, conectado</span>
      <button
        type="button"
        class="scene-replay"
        aria-label="Repetir animación del edificio"
        @click="entrance++"
      >
        <RotateCcw :size="17" aria-hidden="true" />
      </button>
    </div>

    <div class="scene-composition">
      <div class="scene-halo" aria-hidden="true"></div>
      <svg
        :key="entrance"
        class="scene-building"
        viewBox="0 0 640 570"
        aria-hidden="true"
        focusable="false"
      >
        <defs>
          <linearGradient id="alquivo-wall" x1="0" y1="0" x2="1" y2="1">
            <stop stop-color="#f3ead6" />
            <stop offset="1" stop-color="#b5b5a6" />
          </linearGradient>
          <linearGradient id="alquivo-side" x1="0" y1="0" x2="1" y2="1">
            <stop stop-color="#acb9ad" />
            <stop offset="1" stop-color="#687f78" />
          </linearGradient>
          <linearGradient id="alquivo-glass" x1="0" y1="0" x2="1" y2="1">
            <stop stop-color="#142f32" />
            <stop offset="0.6" stop-color="#2b5654" />
            <stop offset="1" stop-color="#79a79a" />
          </linearGradient>
          <linearGradient id="alquivo-plinth" x1="0" y1="0" x2="0" y2="1">
            <stop stop-color="#375555" />
            <stop offset="1" stop-color="#172f33" />
          </linearGradient>
          <radialGradient id="alquivo-ground">
            <stop stop-color="#000" stop-opacity=".4" />
            <stop offset="1" stop-color="#000" stop-opacity="0" />
          </radialGradient>
        </defs>

        <ellipse
          cx="345"
          cy="493"
          rx="261"
          ry="65"
          fill="url(#alquivo-ground)"
        />
        <g class="scene-ground">
          <path
            d="M78 433 320 336 566 438 326 540Z"
            fill="url(#alquivo-plinth)"
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
          <path
            class="scene-orbit"
            d="M148 355C42 381 17 449 160 504s345 11 405-29"
            fill="none"
            stroke="#78c6b5"
            stroke-opacity=".55"
            stroke-width="1"
            stroke-dasharray="3 7"
          />
        </g>

        <!-- Each floor assembles once; selecting a property separates its layer. -->
        <g
          v-for="floor in [2, 1, 0]"
          :key="floor"
          :transform="`translate(0 ${142 + floor * 88})`"
          class="scene-floor-anchor"
        >
          <g class="scene-floor-arrival" :class="`scene-arrival-${floor}`">
            <g
              class="scene-floor"
              :class="[
                `scene-floor-${floor}`,
                { 'is-selected': selected === floor },
              ]"
            >
              <path
                d="M186 0 350-66 480-12 314 54Z"
                fill="#d9dfca"
                stroke="#edf0df"
                stroke-width="1"
              />
              <path
                d="M186 0 314 54 314 142 186 88Z"
                fill="url(#alquivo-wall)"
              />
              <path
                d="M314 54 480-12 480 76 314 142Z"
                fill="url(#alquivo-side)"
              />
              <path
                d="M199 17 299 59 299 119 199 77Z"
                fill="url(#alquivo-glass)"
              />
              <path
                d="M329 62 464 8 464 65 329 119Z"
                fill="url(#alquivo-glass)"
              />
              <path
                d="m234 32 0 60m32-47v60m105-60v57m45-75v57"
                stroke="#b9c8b2"
                stroke-opacity=".6"
                stroke-width="2"
              />
              <path
                d="m202 21 94 40v6l-94-40Z"
                fill="#c7e4cc"
                fill-opacity=".2"
              />
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
                class="scene-floor-edge"
                d="M186 0 314 54 480-12M314 54v88"
                fill="none"
                stroke="#e8fff2"
                stroke-width="1.2"
              />
              <g v-if="floor === 0">
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
        <g
          class="scene-connection"
          fill="none"
          stroke="#8fcebd"
          stroke-width="1.2"
        >
          <path d="M191 267 125 239H62" pathLength="1" />
          <path d="m464 310 66 27v44" pathLength="1" />
          <circle cx="191" cy="267" r="4" fill="#b2ead4" stroke="none" />
          <circle cx="464" cy="310" r="4" fill="#b2ead4" stroke="none" />
        </g>
      </svg>

      <div class="scene-total">
        <span class="scene-card-label"
          >Cobrado este mes <ArrowUpRight :size="16" aria-hidden="true"
        /></span>
        <strong>{{ demoMoney(demoSummary.receivedCents) }}</strong>
        <span class="scene-total-note">3 alquileres · Ejemplo</span>
        <div class="scene-collection-progress" aria-hidden="true">
          <span :style="{ width: `${collectedPercent}%` }"></span>
        </div>
      </div>

      <div
        id="scene-property"
        class="scene-property"
        aria-live="polite"
        aria-atomic="true"
      >
        <div :key="selected" class="scene-property-content">
          <span class="scene-property-index"
            >0{{ selected + 1 }} / TU CARTERA</span
          >
          <strong>{{ rental.name }}</strong>
          <div>
            <span>Cobrado</span><b>{{ demoMoney(rental.receivedCents) }}</b>
          </div>
          <div>
            <span>Por cobrar</span
            ><b :class="{ 'scene-pending': pending > 0 }">{{
              demoMoney(pending)
            }}</b>
          </div>
        </div>
      </div>
    </div>
    <div class="scene-explore">
      <span>Elige un inmueble</span>
      <div role="group" aria-label="Explorar inmuebles de ejemplo">
        <button
          v-for="(label, index) in labels"
          :key="label"
          type="button"
          :aria-label="label"
          :aria-pressed="selected === index"
          aria-controls="scene-property"
          @click="selected = index"
        >
          <span aria-hidden="true">0{{ index + 1 }}</span
          >{{ ["Centro", "Mar", "Garaje"][index] }}
        </button>
      </div>
    </div>
  </div>
</template>
