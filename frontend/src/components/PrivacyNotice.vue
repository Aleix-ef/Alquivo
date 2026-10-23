<script setup>
import { operator } from "../content/legal.js";
defineProps({ context: { type: String, default: "register" } });
</script>
<template>
  <details class="privacy-first-layer">
    <summary>{{ $t("privacy.summary") }}</summary>
    <p>
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
  </details>
</template>
