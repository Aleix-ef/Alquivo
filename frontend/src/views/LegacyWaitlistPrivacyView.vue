<script setup>
import WaitlistLegalView from "./WaitlistLegalView.vue";
</script>

<template>
  <div class="legacy-waitlist-privacy">
    <aside class="legacy-waitlist-notice" aria-label="Información histórica">
      Esta es la información de privacidad de la antigua lista de espera,
      conservada para quienes solicitaron acceso. La beta ya permite crear una
      cuenta. Consulta la
      <RouterLink to="/privacy">privacidad de la aplicación</RouterLink> o
      <RouterLink to="/register">prueba Alquivo</RouterLink>.
    </aside>
    <WaitlistLegalView privacy />
  </div>
</template>

<style scoped>
.legacy-waitlist-notice {
  padding: 1.25rem;
  margin: 0 auto;
  max-width: 70rem;
  font-size: 1rem;
  line-height: 1.6;
}
/* The historical notice stays verbatim; its obsolete waitlist CTA is not active. */
.legacy-waitlist-privacy :deep(.legal-header .button) {
  display: none;
}
</style>
