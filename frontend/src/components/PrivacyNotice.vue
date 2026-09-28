<script setup>
import { operator } from "../content/legal.js";
defineProps({ context: { type: String, default: "register" } });
</script>
<template>
  <p class="privacy-first-layer">
    <strong>Sobre tus datos.</strong>
    Responsable:
    {{ operator.name || "titular de Alquivo (identificación pendiente)" }}.
    {{
      context === "register"
        ? "Usamos tus datos para crear y gestionar tu cuenta, ejecutar el contrato y proteger el servicio."
        : "Usamos tus datos y los archivos que compartas para atender esta consulta: por el contrato o las medidas precontractuales, o por el interés legítimo en gestionar otras consultas."
    }}
    Acceden el personal autorizado y los proveedores necesarios que se
    identifican en la política de privacidad. Puedes ejercer tus derechos en
    <a :href="`mailto:${operator.email}`">{{ operator.email }}</a
    >.
    <RouterLink to="/privacy" target="_blank" rel="noopener"
      >Leer la información completa</RouterLink
    >.
  </p>
</template>
