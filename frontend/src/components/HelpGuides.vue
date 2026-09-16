<script setup>
import { computed, ref } from "vue";
import { helpGuides } from "../assistantHelp";
import { useProduct } from "../stores/product";
const product = useProduct();
const emit = defineEmits(["navigate"]);
const search = ref("");
const normalize = (text) =>
  text
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase();
const guides = computed(() =>
  helpGuides.filter(
    (guide) =>
      (guide.to !== "/fiscality" || product.features.fiscality) &&
      normalize(guide.title + " " + guide.steps.join(" ")).includes(
        normalize(search.value),
      ),
  ),
);
</script>
<template>
  <section class="assistant-guides" aria-label="Guías de Alquivo">
    <label
      >¿En qué necesitas ayuda?<input
        v-model="search"
        type="search"
        placeholder="Buscar: cobro, contrato, factura…"
    /></label>
    <p>Guías paso a paso. No consumen consultas de IA.</p>
    <details v-for="guide in guides" :key="guide.id">
      <summary>{{ guide.title }}</summary>
      <ol>
        <li v-for="step in guide.steps" :key="step">{{ step }}</li>
      </ol>
      <RouterLink :to="guide.to" @click="emit('navigate')"
        >{{ guide.action }} →</RouterLink
      >
    </details>
    <p v-if="!guides.length" role="status">
      No hay una guía que coincida. Prueba con otra palabra o consulta las
      opciones de soporte.
    </p>
  </section>
</template>
