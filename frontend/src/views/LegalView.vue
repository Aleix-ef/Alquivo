<script setup>
import { computed } from "vue";
import { useRoute } from "vue-router";
import BrandLogo from "../components/BrandLogo.vue";
const route = useRoute();
const privacy = computed(() => route.name === "privacy");
</script>
<template>
  <main class="legal-page">
    <RouterLink class="brand" to="/"><BrandLogo /></RouterLink>
    <article>
      <p class="eyebrow">Versión 2026-07</p>
      <h1>{{ privacy ? "Política de privacidad" : "Condiciones de uso" }}</h1>
      <template v-if="privacy"
        ><p>
          Alquivo trata los datos necesarios para prestar el servicio de gestión
          patrimonial. Los documentos y datos inmobiliarios pertenecen al
          usuario y no se venden a terceros.
        </p>
        <h2>Datos y conservación</h2>
        <p>
          Los datos de gestión se conservan mientras la cuenta está activa.
          Puedes solicitar la eliminación desde Configuración. Los archivos cuyo
          borrado falle quedan pendientes de reintento. Los plazos de
          conservación de copias de seguridad y de documentación sujeta a
          obligaciones legales deben concretarse antes de abrir el servicio.
        </p>
        <h2>Consultas de soporte</h2>
        <p>
          Si utilizas el formulario de soporte, tu nombre, correo, asunto,
          mensaje y los archivos que adjuntes se envían al buzón de soporte de
          Alquivo mediante el proveedor de correo configurado. Se utilizan para
          atender tu consulta y responderte. No se publican ni se incorporan a
          la cartera. Evita enviar contraseñas, datos bancarios, documentos de
          identidad o datos innecesarios de otras personas. El proveedor y el
          plazo de conservación de estas comunicaciones deben concretarse antes
          del lanzamiento.
        </p>
        <h2>Asistente de inteligencia artificial</h2>
        <p>
          El asistente es opcional y de lectura. Tras activarlo, OpenAI recibe
          tus preguntas, el historial reciente y los datos que necesita para
          responder. Las herramientas excluyen nombres de inquilinos,
          direcciones completas y contenido de documentos. Sí pueden incluir
          nombres de inmuebles, títulos de incidencias, importes y fechas. Evita
          introducir datos personales innecesarios en el chat o en esos títulos.
        </p>
        <p>
          El historial del asistente caduca a los 30 días y puedes borrarlo o
          desactivar el asistente. Los contadores de uso se conservan hasta
          finalizar el mes aunque borres conversaciones. La API se utiliza sin
          almacenamiento de respuestas por parte del proveedor; esto no elimina
          sus registros de seguridad, que pueden conservarse hasta 30 días,
          salvo excepciones legales. Activar el asistente no sustituye las
          obligaciones del propietario respecto a los datos de terceros.
        </p>
        <h2>Cookies y registros de seguridad</h2>
        <p>
          La sesión utiliza cookies de autenticación y protección frente a
          solicitudes fraudulentas. No se han integrado herramientas de
          publicidad o analítica de terceros. Los registros de acceso y cambios
          sensibles guardan identificadores y una huella de la conexión durante
          30 días, sin contraseñas, documentos ni preguntas del chat. Su
          política de conservación se debe coordinar con la del alojamiento.
        </p>
        <h2>Seguridad</h2>
        <p>
          Los archivos se almacenan de forma privada y el acceso está aislado
          por cartera.
        </p></template
      ><template v-else
        ><p>
          Alquivo es una herramienta de organización patrimonial. No presta
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
