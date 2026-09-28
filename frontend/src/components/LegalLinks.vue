<script setup>
import { legalPages } from "../content/legal.js";
</script>
<template>
  <nav class="legal-links" aria-label="Información legal">
    <RouterLink v-for="page in legalPages" :key="page.name" :to="page.path">{{
      page.label
    }}</RouterLink>
  </nav>
</template>
