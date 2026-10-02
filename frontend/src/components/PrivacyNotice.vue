<script setup>
import { operator } from "../content/legal.js";
defineProps({ context: { type: String, default: "register" } });
</script>
<template>
  <p class="privacy-first-layer">
    <strong>{{ $t("privacy.heading") }}</strong>
    {{ $t("privacy.controller") }}
    {{ operator.name || $t("privacy.unknown") }}.
    {{
      context === "register" ? $t("privacy.register") : $t("privacy.support")
    }}
    {{ $t("privacy.access") }}
    <a :href="`mailto:${operator.email}`">{{ operator.email }}</a
    >.
    <RouterLink to="/privacy" target="_blank" rel="noopener">{{
      $t("privacy.full")
    }}</RouterLink
    >.
  </p>
</template>
