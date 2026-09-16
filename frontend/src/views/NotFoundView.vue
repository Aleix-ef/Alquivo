<script setup>
import { ArrowLeft } from "@lucide/vue";
</script>

<template>
  <main class="page">
    <section class="empty" role="alert">
      <p class="eyebrow">Página no encontrada</p>
      <h1>Este espacio no existe</h1>
      <p>Puede que el enlace esté incompleto o que la sección haya cambiado.</p>
      <RouterLink class="button primary" to="/dashboard">
        <ArrowLeft :size="16" />Volver al resumen
      </RouterLink>
    </section>
  </main>
</template>
