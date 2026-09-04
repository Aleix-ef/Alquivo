<script setup>
import { computed } from "vue";
import { useRoute } from "vue-router";
const route = useRoute();
const privacy = computed(() => route.name === "privacy");
</script>
<template>
  <main class="legal-page">
    <RouterLink class="brand" to="/login"
      ><b class="mark">I</b><span>InmoGest</span></RouterLink
    >
    <article>
      <p class="eyebrow">Versión 2026-07</p>
      <h1>{{ privacy ? "Política de privacidad" : "Condiciones de uso" }}</h1>
      <template v-if="privacy"
        ><p>
          InmoGest trata los datos necesarios para prestar el servicio de
          gestión patrimonial. Los documentos y datos inmobiliarios pertenecen
          al usuario y no se venden a terceros.
        </p>
        <h2>Datos y conservación</h2>
        <p>
          La información se conserva mientras la cuenta esté activa. El
          propietario puede eliminar su cuenta y los datos vinculados desde
          Configuración.
        </p>
        <h2>Seguridad</h2>
        <p>
          Los archivos se almacenan de forma privada y el acceso está aislado
          por cartera.
        </p></template
      ><template v-else
        ><p>
          InmoGest es una herramienta de organización patrimonial. No presta
          asesoramiento fiscal, jurídico ni financiero.
        </p>
        <h2>Responsabilidad del usuario</h2>
        <p>
          El usuario es responsable de la exactitud de la información y de
          contar con autorización para almacenar datos personales y documentos.
        </p>
        <h2>Disponibilidad</h2>
        <p>
          El servicio puede evolucionar para mejorar sus funciones, seguridad y
          planes comerciales.
        </p></template
      >
      <p class="legal-note">
        Texto provisional para validación profesional antes del lanzamiento
        comercial.
      </p>
    </article>
  </main>
</template>
