<script setup>
import { Monitor, Moon, Sun } from "@lucide/vue";
import { useAppearance } from "../stores/appearance";

const appearance = useAppearance();
const options = [
  { value: "light", label: "Modo claro", icon: Sun },
  { value: "dark", label: "Modo oscuro", icon: Moon },
  { value: "system", label: "Usar el tema del sistema", icon: Monitor },
];
</script>

<template>
  <div class="theme-toggle" role="group" aria-label="Apariencia">
    <button
      v-for="option in options"
      :key="option.value"
      type="button"
      :aria-label="option.label"
      :title="option.label"
      :aria-pressed="appearance.preference === option.value"
      @click="appearance.setPreference(option.value)"
    >
      <component :is="option.icon" :size="16" aria-hidden="true" />
    </button>
  </div>
</template>
