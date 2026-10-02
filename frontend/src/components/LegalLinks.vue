<script setup>
import { legalPages } from "../content/legal.js";
</script>
<template>
  <nav class="legal-links" :aria-label="$t('legal.heading')">
    <RouterLink v-for="page in legalPages" :key="page.name" :to="page.path">{{
      $t(`legal.${page.name === "data-processing" ? "processing" : page.name}`)
    }}</RouterLink>
  </nav>
</template>
