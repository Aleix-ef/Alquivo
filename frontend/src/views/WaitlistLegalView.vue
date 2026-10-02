<script setup>
import BrandLogo from "../components/BrandLogo.vue";
import { legalOperator as operator } from "../content/legalOperator.js";
defineProps({ privacy: { type: Boolean, default: false } });
</script>

<template>
  <a class="waitlist-skip" href="#contenido">Saltar al contenido</a>
  <div class="legal-page waitlist-legal">
    <header class="legal-header">
      <a href="/" aria-label="Alquivo, volver al inicio"><BrandLogo /></a
      ><a href="/#solicitud" class="button">Solicita acceso</a>
    </header>
    <main id="contenido" class="legal-content">
      <p class="marketing-eyebrow">
        Web informativa · Actualizado el 2 de octubre de 2026
      </p>
      <h1>
        {{ privacy ? "Privacidad de la lista de espera" : "Aviso legal" }}
      </h1>
      <p class="legal-summary">
        Esta información se aplica a la web pública de Alquivo, las solicitudes
        para su próxima beta y las consultas de contacto. No regula todavía las
        cuentas de la aplicación.
      </p>
      <section id="titular" class="legal-section">
        <h2>{{ privacy ? "Quién trata tus datos" : "Titular de esta web" }}</h2>
        <dl class="legal-identity">
          <div>
            <dt>Titular</dt>
            <dd>{{ operator.name }}</dd>
          </div>
          <div>
            <dt>Forma jurídica</dt>
            <dd>{{ operator.legalForm }}</dd>
          </div>
          <div>
            <dt>NIF</dt>
            <dd>{{ operator.taxId }}</dd>
          </div>
          <div>
            <dt>Domicilio</dt>
            <dd>{{ operator.address }}</dd>
          </div>
          <div>
            <dt>Contacto</dt>
            <dd>
              <a :href="`mailto:${operator.email}`">{{ operator.email }}</a>
            </dd>
          </div>
        </dl>
      </section>
      <template v-if="privacy">
        <section id="lista-espera" class="legal-section">
          <h2>Solicitud de acceso a la beta</h2>
          <p>
            Recogemos tu email y, si los facilitas, tu nombre y un rango
            aproximado de inmuebles. No necesitamos direcciones de inmuebles,
            datos de inquilinos, contratos, facturas ni documentación personal
            en este formulario.
          </p>
          <p>
            La finalidad es gestionar tu solicitud y enviarte la invitación o
            las comunicaciones necesarias sobre tu acceso a la beta. La base
            jurídica es tu consentimiento (artículo 6.1.a del RGPD), solicitado
            de forma específica en el formulario. Puedes retirarlo en cualquier
            momento escribiendo al correo de contacto, sin afectar al
            tratamiento realizado previamente.
          </p>
          <p>
            No usaremos la solicitud para enviarte campañas publicitarias ni la
            cederemos para publicidad. Solicitar acceso no crea una cuenta, no
            inicia una suscripción y no garantiza una fecha de apertura.
          </p>
          <p>
            Conservaremos la solicitud hasta que recibas acceso, retires tu
            consentimiento o transcurran 12 meses desde la solicitud, lo que
            ocurra antes. Si creas una cuenta, su tratamiento se explicará por
            separado. Las solicitudes canceladas se eliminarán de los sistemas
            activos. Las copias de recuperación del proveedor pueden conservar
            temporalmente versiones anteriores según sus plazos; una
            restauración deberá reaplicar las bajas y eliminaciones.
          </p>
        </section>
        <section class="legal-section">
          <h2>Consultas de contacto y seguridad de la web</h2>
          <p>
            Si nos escribes, tratamos tu email y el contenido que envíes para
            responder a tu consulta. Cuando pides información sobre el servicio,
            la base es atender medidas precontractuales solicitadas por ti
            (artículo 6.1.b del RGPD); para otras consultas y la prevención de
            abusos, nuestro interés legítimo en atender y proteger el servicio
            (artículo 6.1.f).
          </p>
          <p>
            Guardaremos las consultas durante su tramitación y hasta 12 meses
            después de su cierre. Los datos que deban conservarse por obligación
            legal o para atender responsabilidades se mantendrán restringidos
            durante el plazo legal aplicable.
          </p>
          <p>
            El proveedor de alojamiento puede tratar datos técnicos, como la
            dirección IP y la información de la petición, para servir y proteger
            la web, conforme a sus condiciones de tratamiento. No realizamos
            decisiones automatizadas con efectos jurídicos ni perfiles
            comerciales.
          </p>
        </section>
        <section id="proveedores" class="legal-section">
          <h2>Proveedores y transferencias</h2>
          <p>
            El formulario pertenece a Alquivo. Cloudflare presta el alojamiento,
            ejecuta el guardado de solicitudes mediante Pages Functions,
            almacena las respuestas en D1 y ofrece la comprobación antispam
            Turnstile, conforme a sus condiciones de tratamiento. No enviamos
            los datos del formulario a OpenAI, Stripe ni proveedores de
            formularios externos.
          </p>
          <p>
            Los correos enviados a soporte se reenvían mediante Cloudflare Email
            Routing al buzón de Google (Gmail) del titular. No publiques datos
            sensibles ni documentación de terceros en consultas iniciales.
          </p>
          <p>
            Algunos proveedores o subproveedores pueden tratar datos fuera del
            Espacio Económico Europeo. Cloudflare prevé las cláusulas
            contractuales tipo para las transferencias a las que se aplican;
            consulta también las condiciones de Google y los subencargados
            publicados por Cloudflare. No hay pagos, subida de archivos,
            integraciones publicitarias ni envío automático de las respuestas
            del formulario por email.
          </p>
          <ul class="legal-related">
            <li>
              <a
                href="https://www.cloudflare.com/cloudflare-customer-dpa/"
                rel="noopener noreferrer"
                target="_blank"
                >Acuerdo de tratamiento de Cloudflare ↗</a
              >
            </li>
            <li>
              <a
                href="https://policies.google.com/privacy?hl=es"
                rel="noopener noreferrer"
                target="_blank"
                >Política de privacidad de Google ↗</a
              >
            </li>
          </ul>
        </section>
        <section class="legal-section">
          <h2>Tus derechos</h2>
          <p>
            Puedes solicitar acceso, rectificación, supresión, limitación o
            portabilidad, y oponerte al tratamiento cuando proceda, escribiendo
            al correo indicado. No es necesario enviar una copia del DNI de
            forma preventiva: solo pediremos información adicional si es
            necesaria para verificar tu identidad. Puedes retirar el
            consentimiento y salir de la lista de espera por el mismo medio.
          </p>
          <p>
            Responderemos en el plazo previsto por el RGPD, normalmente un mes.
            Si consideras que no se han respetado tus derechos, puedes reclamar
            ante la
            <a
              href="https://www.aepd.es/"
              rel="noopener noreferrer"
              target="_blank"
              >Agencia Española de Protección de Datos ↗</a
            >.
          </p>
        </section>
        <section id="cookies" class="legal-section">
          <h2>Cookies y servicios externos</h2>
          <p>
            Esta landing no incorpora analítica de visitantes, píxeles
            publicitarios, fuentes remotas ni cookies propias de seguimiento.
            Cloudflare puede utilizar mecanismos técnicos de seguridad en
            función de la configuración del alojamiento.
          </p>
          <p>
            Turnstile se carga al interactuar con el formulario para comprobar
            que la solicitud no es automatizada. Cloudflare puede tratar datos
            técnicos del navegador para esta comprobación; no le enviamos los
            campos de nombre, email o inmuebles al verificar el desafío. Alquivo
            no guarda la IP original ni el token en la lista de espera. Para
            limitar abusos usa un identificador seudonimizado por intervalos de
            diez minutos, que se limpia en solicitudes posteriores.
          </p>
          <p>
            No habilitamos el modo de autorización previa de Turnstile ni sus
            integraciones publicitarias. Si añadimos cookies no necesarias o
            nuevas integraciones, revisaremos esta información y solicitaremos
            el consentimiento que corresponda antes de activarlas.
          </p>
          <p>
            <a
              href="https://www.cloudflare.com/turnstile-privacy-policy/"
              rel="noopener noreferrer"
              target="_blank"
              >Privacidad de Turnstile ↗</a
            >
          </p>
        </section>
      </template>
      <template v-else>
        <section class="legal-section">
          <h2>Objeto de la web</h2>
          <p>
            Esta web presenta Alquivo, una aplicación de gestión de alquileres
            para propietarios y pequeños inversores, y permite solicitar
            información o acceso a una futura beta gratuita. No es una
            plataforma de contratación ni permite realizar pagos.
          </p>
          <p>
            Los ejemplos mostrados son ilustrativos y utilizan datos ficticios.
            El producto está en pruebas y puede cambiar. No sustituye el
            asesoramiento legal, fiscal ni financiero.
          </p>
        </section>
        <section class="legal-section">
          <h2>Uso y contenidos</h2>
          <p>
            Utiliza la web de forma lícita, sin intentar dañar su funcionamiento
            ni enviar datos de terceros sin autorización. Los contenidos, la
            marca y los elementos gráficos pertenecen a sus titulares o se
            utilizan conforme a sus licencias; su publicación no concede una
            licencia de explotación comercial.
          </p>
          <p>
            Procuramos mantener la información actualizada, sin excluir los
            derechos ni las responsabilidades que reconoce la normativa
            aplicable. Los enlaces externos se facilitan como información y
            tienen sus propias condiciones.
          </p>
        </section>
        <section class="legal-section">
          <h2>Solicitar acceso no es una compra</h2>
          <p>
            No se cobra por apuntarse y no se pide tarjeta. La invitación
            llegará cuando podamos dar acceso; no se promete una fecha ni una
            plaza inmediata. Antes de crear una cuenta podrás consultar las
            condiciones de uso y la privacidad de la aplicación.
          </p>
        </section>
        <section class="legal-section">
          <h2>Privacidad y contacto</h2>
          <p>
            Consulta la <a href="/privacidad">información de privacidad</a> para
            conocer el tratamiento de solicitudes y consultas. Para cualquier
            duda o reclamación puedes escribir al correo de contacto indicado.
          </p>
          <p>
            Se aplica la legislación española, respetando en todo caso los
            derechos y las normas imperativas que correspondan a las personas
            consumidoras.
          </p>
        </section>
      </template>
      <footer class="legal-footer">
        <nav class="legal-links" aria-label="Información legal">
          <a href="/privacidad">Privacidad</a
          ><a href="/aviso-legal">Aviso legal</a>
        </nav>
        <a href="/">Volver a Alquivo</a>
      </footer>
    </main>
  </div>
</template>
