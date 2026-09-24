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
      <p class="eyebrow">Versión 2026-09-22</p>
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
          El chat de soporte guarda tu nombre, correo de contacto si lo indicas,
          asunto, mensajes y adjuntos para atender tu consulta y responderte en
          la misma conversación. Solo puede acceder el titular de la
          conversación y el personal expresamente autorizado para soporte. Este
          chat no utiliza inteligencia artificial ni da al equipo acceso a tu
          cartera: comparte únicamente la información necesaria.
        </p>
        <p>
          Las conversaciones se eliminan del sistema activo tras 180 días sin
          mensajes ni cambios de estado. Si eliminas tu cuenta, también se
          eliminan sus conversaciones y se programa el borrado de sus adjuntos.
          Los archivos que no puedan borrarse de inmediato quedan pendientes de
          reintento. Los plazos de conservación de las copias de seguridad deben
          concretarse antes del lanzamiento.
        </p>
        <p>
          Sin iniciar sesión, el acceso al chat depende de la sesión de este
          navegador: indicar un correo no acredita su titularidad ni permite
          recuperar el historial desde otro dispositivo. Al caducar esa sesión
          puedes perder el acceso, aunque la conversación se conserve durante el
          plazo indicado. Las nuevas consultas realizadas dentro de tu cuenta
          quedan asociadas a ella.
        </p>
        <p>
          Evita enviar contraseñas, datos bancarios, documentos de identidad o
          datos innecesarios de otras personas. Si utilizas el correo o el
          formulario alternativo de contacto, los datos que envíes se remiten al
          buzón de soporte mediante el proveedor de correo configurado. El
          proveedor y la conservación de esas comunicaciones deben concretarse
          antes del lanzamiento.
        </p>
        <h2>Asistente de inteligencia artificial</h2>
        <p>
          El asistente es opcional. Permite consultar datos y, cuando está
          habilitado para tu cuenta, preparar gastos, cobros de alquiler,
          cambios de teléfono y notas de inmuebles. Ninguna propuesta se guarda
          como operación real hasta que la revisas y pulsas el botón de
          confirmación. Tras activarlo, OpenAI recibe tus preguntas, el
          historial reciente y los datos que necesita para responder. Las
          herramientas pueden incluir nombres de contactos y su relación con
          contratos, nombres de inmuebles, títulos de incidencias, importes y
          fechas. No incluyen DNI, correos, teléfonos ya guardados, notas
          anteriores, direcciones completas ni contenido de documentos en el
          chat. Si escribes un teléfono nuevo, una nota o un concepto en el
          chat, ese texto también se envía al proveedor. Evita introducir datos
          personales innecesarios en el chat o en esos títulos.
        </p>
        <p>
          El historial y los borradores del asistente caducan a los 30 días y
          puedes borrarlos o desactivar el asistente. Las operaciones y cambios
          que hayas confirmado permanecen en sus secciones correspondientes. Se
          conservan hasta 12 meses las métricas de uso, coste y trazabilidad,
          sin el contenido de las conversaciones, aunque borres el chat. La API
          se utiliza sin almacenamiento de respuestas por parte del proveedor;
          esto no elimina sus registros de seguridad, que pueden conservarse
          hasta 30 días, salvo excepciones legales. Activar el asistente no
          sustituye las obligaciones del propietario respecto a los datos de
          terceros.
        </p>
        <p>
          La importación documental se está probando exclusivamente en una
          simulación local para administradores: no envía archivos a OpenAI.
          Requiere aceptar un aviso separado y permite revocarlo desde la
          pantalla de importación. Los borradores y fragmentos se guardan
          cifrados y se eliminan a los 30 días o al revocar el permiso; los
          archivos originales y las operaciones confirmadas se conservan en sus
          módulos. Antes de habilitar un análisis real será necesario un nuevo
          aviso y aceptación: el archivo seleccionado puede contener nombres,
          direcciones, identificadores y otros datos de terceros que recibiría
          el proveedor. La aceptación del chat no autoriza ese envío.
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
