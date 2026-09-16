<script setup>
defineProps({
  compact: { type: Boolean, default: false },
  light: { type: Boolean, default: false },
});
</script>

<template>
  <span class="alquivo-brand" :class="{ compact, light }" aria-label="Alquivo">
    <svg class="alquivo-symbol" viewBox="0 0 80 64" aria-hidden="true">
      <path class="alquivo-mark-main" d="M4 54 27 8l24 46H40L27 27 15 54Z" />
      <path class="alquivo-mark-sage" d="M53 14h10v30H53z" />
      <path class="alquivo-mark-gold" d="M63 44h14v10H63z" />
    </svg>
    <span v-if="!compact" class="alquivo-wordmark">ALQUIVO</span>
  </span>
</template>
