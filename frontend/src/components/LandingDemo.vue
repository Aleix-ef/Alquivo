<script setup>
import { ref } from "vue";
import { ArrowDownLeft, Check, CircleHelp, Sparkles } from "@lucide/vue";
import {
  demoMoney,
  demoPeriod,
  demoRentals,
  demoPending,
  demoSummary,
} from "../content/landingDemo.js";

const mode = ref("payments");
</script>

<template>
  <section
    id="landing-demo"
    class="landing-demo"
    aria-labelledby="landing-demo-title"
  >
    <header class="landing-demo-heading">
      <div>
        <span class="landing-demo-dot" aria-hidden="true"></span
        ><strong id="landing-demo-title">Tus alquileres, de un vistazo</strong>
      </div>
      <span class="landing-demo-period">{{ demoPeriod }}</span>
    </header>
    <div class="landing-demo-metrics">
      <div>
        <span>Cobrado</span
        ><strong>{{ demoMoney(demoSummary.receivedCents) }}</strong>
      </div>
      <div class="is-pending">
        <span>Por cobrar</span
        ><strong>{{ demoMoney(demoSummary.pendingCents) }}</strong>
      </div>
      <div>
        <span>Gastos pagados</span
        ><strong>{{ demoMoney(demoSummary.expenseCents) }}</strong>
      </div>
    </div>
    <div
      class="landing-demo-switch"
      role="group"
      aria-label="Explorar el ejemplo"
    >
      <button
        type="button"
        :aria-pressed="mode === 'payments'"
        aria-controls="landing-demo-content"
        @click="mode = 'payments'"
      >
        <ArrowDownLeft :size="17" aria-hidden="true" />Ver cobros
      </button>
      <button
        type="button"
        :aria-pressed="mode === 'assistant'"
        aria-controls="landing-demo-content"
        @click="mode = 'assistant'"
      >
        <Sparkles :size="17" aria-hidden="true" />Consulta IA
      </button>
    </div>
    <div
      id="landing-demo-content"
      class="landing-demo-content"
      aria-live="polite"
      aria-atomic="true"
    >
      <div :key="mode" class="landing-demo-panel">
        <ul
          v-if="mode === 'payments'"
          class="landing-demo-rentals"
          aria-label="Alquileres del ejemplo"
        >
          <li v-for="rental in demoRentals" :key="rental.name">
            <div>
              <strong>{{ rental.name }}</strong
              ><span
                >{{ demoMoney(rental.receivedCents) }} de
                {{ demoMoney(rental.dueCents) }} cobrados</span
              >
            </div>
            <span
              class="landing-demo-status"
              :class="{ complete: rental.receivedCents === rental.dueCents }"
              ><Check
                v-if="rental.receivedCents === rental.dueCents"
                :size="14"
                aria-hidden="true"
              />{{
                rental.receivedCents === rental.dueCents
                  ? "Pagado"
                  : rental.receivedCents
                    ? "Parcial"
                    : "Pendiente"
              }}</span
            >
          </li>
        </ul>
        <div v-else class="landing-demo-answer">
          <p class="landing-demo-question">¿Qué alquileres tengo pendientes?</p>
          <div class="landing-demo-answer-body">
            <span class="landing-demo-ai-name"
              ><Sparkles :size="17" aria-hidden="true" />Alquivo AI · Respuesta
              de ejemplo</span
            >
            <p>
              En este octubre quedan
              <strong>{{ demoMoney(demoSummary.pendingCents) }}</strong> por
              cobrar:
            </p>
            <ul>
              <li v-for="item in demoPending" :key="item.name">
                {{ item.name }}: <strong>{{ demoMoney(item.cents) }}</strong>
              </li>
            </ul>
            <small>Fuente del ejemplo: mensualidades de octubre.</small>
          </div>
        </div>
      </div>
    </div>
    <footer>
      <CircleHelp :size="15" aria-hidden="true" /><span
        >Datos de ejemplo. Esta vista no consulta tus datos ni ejecuta la
        IA.</span
      >
    </footer>
  </section>
</template>
