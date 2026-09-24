<script setup>
import SupportChat from "../components/SupportChat.vue";
</script>
<template>
  <main class="page support-page">
    <header class="heading">
      <div>
        <p class="eyebrow">Equipo Alquivo</p>
        <h1>Bandeja de soporte</h1>
        <p>
          Lee las consultas y responde en la misma conversación que ve el
          usuario.
        </p>
      </div>
      <RouterLink to="/support" class="button secondary"
        >Volver a ayuda</RouterLink
      >
    </header>
    <section class="panel"><SupportChat mode="team" /></section>
  </main>
</template>
