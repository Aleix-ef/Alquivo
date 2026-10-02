<script setup>
defineProps({
  compact: { type: Boolean, default: false },
  light: { type: Boolean, default: false },
});
</script>

<template>
  <span
    class="alquivo-brand"
    :class="{ compact, light }"
    role="img"
    aria-label="Alquivo"
  >
    <svg
      v-if="compact"
      class="alquivo-symbol"
      viewBox="0 0 100 100"
      aria-hidden="true"
      focusable="false"
    >
      <use href="/favicon.svg?v=wordmark-1#mark" />
    </svg>
    <svg
      v-else
      class="alquivo-wordmark"
      viewBox="0 0 656 128"
      aria-hidden="true"
      focusable="false"
    >
      <use href="/brand/alquivo-wordmark.svg#wordmark" />
    </svg>
  </span>
</template>
