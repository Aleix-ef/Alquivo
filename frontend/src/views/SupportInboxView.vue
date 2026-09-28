<script setup>
import SupportChat from "../components/SupportChat.vue";
</script>
<template>
  <main class="page support-page">
    <header class="heading">
      <div>
        <p class="eyebrow">Equipo Alquivo</p>
        <h1>Bandeja de tickets</h1>
        <p>Organiza las consultas abiertas y responde desde cada ticket.</p>
      </div>
      <RouterLink to="/support" class="button secondary"
        >Volver a ayuda</RouterLink
      >
    </header>
    <section class="panel"><SupportChat mode="team" /></section>
  </main>
</template>
