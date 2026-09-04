<script setup>
defineProps({
  compact: { type: Boolean, default: false },
  light: { type: Boolean, default: false },
});
</script>

<template>
  <span class="nareo-brand" :class="{ compact, light }" aria-label="Nareo">
    <svg class="nareo-symbol" viewBox="0 0 64 64" aria-hidden="true">
      <path
        class="symbol-back"
        d="M5 24 22 15v34L5 40a6 6 0 0 1-3-5V29a6 6 0 0 1 3-5Z"
      />
      <path
        class="symbol-main"
        d="M25 10 42 19v34L25 44a6 6 0 0 1-3-5V15a6 6 0 0 1 3-5Z"
      />
      <path
        class="symbol-front"
        d="m46 23 12 6a6 6 0 0 1 3 5v6a6 6 0 0 1-3 5l-12 6Z"
      />
    </svg>
    <span v-if="!compact" class="nareo-wordmark">NAREO</span>
  </span>
</template>
