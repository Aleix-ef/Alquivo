<script setup>
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import BrandLogo from "./components/BrandLogo.vue";

const router = useRouter();
const starting = ref(true);
onMounted(async () => {
  try {
    await router.isReady();
  } finally {
    starting.value = false;
  }
});
</script>

<template>
  <div v-if="starting" class="app-loading" role="status" aria-live="polite">
    <BrandLogo />
    <span>Preparando tu espacio…</span>
  </div>
  <RouterView v-else />
</template>
