<script setup>
import { ref } from "vue";
import { useRoute } from "vue-router";
import PublicSupportWidget from "./components/PublicSupportWidget.vue";
const route = useRoute();

const supportOpen = ref(false);
</script>

<template>
  <div :inert="supportOpen || undefined"><RouterView /></div>
  <PublicSupportWidget
    v-if="route.meta.public"
    @open-change="supportOpen = $event"
  />
</template>
