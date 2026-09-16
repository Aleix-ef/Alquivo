<script setup>
import { onMounted, ref } from "vue";
import { useRouter, useRoute } from "vue-router";
import BrandLogo from "./components/BrandLogo.vue";
import PublicSupportWidget from "./components/PublicSupportWidget.vue";
const route = useRoute();

const router = useRouter();
const starting = ref(true);
const supportOpen = ref(false);
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
  <div v-else :inert="supportOpen || undefined"><RouterView /></div>
  <PublicSupportWidget
    v-if="!starting && route.meta.public"
    @open-change="supportOpen = $event"
  />
</template>
