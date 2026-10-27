<script setup>
import { legalPages } from "../content/legal.js";
defineProps({ expanded: { type: Boolean, default: false } });
</script>
<template>
  <nav class="legal-links" :aria-label="$t('legal.heading')">
    <template v-if="expanded">
      <RouterLink v-for="page in legalPages" :key="page.name" :to="page.path">{{
        $t(
          `legal.${page.name === "data-processing" ? "processing" : page.name}`,
        )
      }}</RouterLink>
    </template>
    <template v-else>
      <RouterLink to="/privacy">{{ $t("legal.privacy") }}</RouterLink>
      <RouterLink to="/legal">{{ $t("legal.heading") }}</RouterLink>
    </template>
  </nav>
</template>
