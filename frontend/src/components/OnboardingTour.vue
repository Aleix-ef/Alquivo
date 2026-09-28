<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { ArrowLeft, ArrowRight, Sparkles, X } from "@lucide/vue";
import "../onboarding.css";

const props = defineProps({
  assistantAvailable: { type: Boolean, default: false },
});
const emit = defineEmits(["open-assistant"]);
const route = useRoute();
const router = useRouter();
const active = ref(false);
const stepIndex = ref(0);
const steps = [
  {
    path: "/dashboard",
    target: "summary",
    section: "Resumen",
    title: "Todo tu patrimonio en un lugar",
    text: "Aquí verás el valor de tus inmuebles, los cobros, los gastos y lo que requiere atención. Las cifras se completan a medida que añades datos.",
  },
  {
    path: "/properties",
    target: "properties",
    section: "Propiedades",
    title: "Empieza por un inmueble",
    text: "Pulsa «Añadir propiedad» para guardar su nombre, dirección y valor estimado. Puedes completar la ficha y subir fotos más adelante.",
  },
  {
    path: "/leases",
    target: "leases",
    section: "Alquileres",
    title: "Relaciona cada alquiler con su inmueble",
    text: "Cuando tengas una propiedad, crea aquí el alquiler con la persona inquilina, las fechas y la renta mensual. Así podrás seguir los cobros esperados.",
  },
  {
    path: "/finance",
    target: "finance",
    section: "Finanzas",
    title: "Entiende lo que ganas y gastas",
    text: "Registra ingresos y gastos, y asígnalos a un inmueble si corresponde. Alquivo calculará el balance con los movimientos que hayas guardado.",
  },
  {
    path: "/dashboard",
    target: "ai",
    section: "Alquivo AI",
    title: "Pregunta a tu patrimonio",
    text: "Haz preguntas sobre tus inmuebles, contratos, cobros y gastos. Alquivo AI responde con la información de tu espacio e indica sus fuentes. Si faltan datos, te lo dirá.",
    detail:
      "Si la lectura de documentos está disponible en tu cuenta, podrás probarla desde Documentos. Las propuestas de cambios requieren tu confirmación y solo aparecen cuando esa función está disponible.",
  },
];
const current = computed(() => steps[stepIndex.value]);
let highlighted = null;
let focusTimer = null;

function clearHighlight() {
  if (highlighted) highlighted.classList.remove("tour-highlight");
  highlighted = null;
  if (focusTimer) clearTimeout(focusTimer);
}
async function focusStep() {
  clearHighlight();
  if (!active.value || route.path !== current.value.path) return;
  await nextTick();
  let attempts = 0;
  const locate = () => {
    if (!active.value || route.path !== current.value.path) return;
    const element = document.querySelector(
      `[data-tour="${current.value.target}"]`,
    );
    if (!element) {
      if (attempts++ < 24) focusTimer = setTimeout(locate, 120);
      return;
    }
    highlighted = element;
    element.classList.add("tour-highlight");
    element.scrollIntoView({
      behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches
        ? "auto"
        : "smooth",
      block: "center",
    });
  };
  focusTimer = setTimeout(locate, 80);
}
async function navigateToStep() {
  if (route.path !== current.value.path) await router.push(current.value.path);
  await focusStep();
}
async function start() {
  active.value = true;
  stepIndex.value = 0;
  await navigateToStep();
}
function finish() {
  active.value = false;
  clearHighlight();
}
async function next() {
  if (stepIndex.value === steps.length - 1) return finish();
  stepIndex.value += 1;
  await navigateToStep();
}
async function previous() {
  if (stepIndex.value === 0) return;
  stepIndex.value -= 1;
  await navigateToStep();
}
function tryAssistant() {
  finish();
  emit("open-assistant");
}
watch(() => route.path, focusStep);
onBeforeUnmount(clearHighlight);
defineExpose({ start });
</script>

<template>
  <section
    v-if="active"
    class="guided-tour"
    aria-labelledby="guided-tour-title"
    aria-live="polite"
  >
    <div class="guided-tour-top">
      <span
        >{{ stepIndex + 1 }} de {{ steps.length }} · {{ current.section }}</span
      >
      <button type="button" aria-label="Salir del recorrido" @click="finish()">
        <X :size="18" />
      </button>
    </div>
    <div class="guided-tour-progress" aria-hidden="true">
      <span
        :style="{ width: `${((stepIndex + 1) / steps.length) * 100}%` }"
      ></span>
    </div>
    <h2 id="guided-tour-title">{{ current.title }}</h2>
    <p>
      {{
        stepIndex === steps.length - 1 && !assistantAvailable
          ? "Alquivo AI todavía no está activo en tu cuenta. Cuando esté disponible, podrás preguntarle por tus inmuebles, contratos, cobros y gastos."
          : current.text
      }}
    </p>
    <p v-if="current.detail" class="guided-tour-detail">{{ current.detail }}</p>
    <button
      v-if="route.path !== current.path"
      class="guided-tour-link"
      type="button"
      @click="navigateToStep"
    >
      Abrir {{ current.section }}
    </button>
    <div class="guided-tour-actions">
      <button
        v-if="stepIndex"
        class="button secondary"
        type="button"
        @click="previous"
      >
        <ArrowLeft :size="16" />Anterior
      </button>
      <button v-else class="button secondary" type="button" @click="finish()">
        Salir
      </button>
      <button
        v-if="stepIndex === steps.length - 1 && assistantAvailable"
        class="button primary"
        type="button"
        @click="tryAssistant"
      >
        <Sparkles :size="16" />Probar Alquivo AI
      </button>
      <button v-else class="button primary" type="button" @click="next">
        {{ stepIndex === steps.length - 1 ? "Terminar" : "Siguiente"
        }}<ArrowRight v-if="stepIndex < steps.length - 1" :size="16" />
      </button>
    </div>
  </section>
</template>
