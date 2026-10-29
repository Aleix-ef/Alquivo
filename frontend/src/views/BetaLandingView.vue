<script setup>
import { computed, onMounted, ref } from "vue";
import {
  ArrowRight,
  Check,
  CircleCheck,
  MessageCircle,
  ShieldCheck,
  Sparkles,
} from "@lucide/vue";
import BrandLogo from "../components/BrandLogo.vue";
import LegalLinks from "../components/LegalLinks.vue";
import LandingDemo from "../components/LandingDemo.vue";
import LandingHeroScene from "../components/LandingHeroScene.vue";
import { useLandingMotion } from "../composables/useLandingMotion.js";
import { useProduct } from "../stores/product";
import { publicAssistantState } from "../content/landingDemo.js";
import { guides } from "../content/guides.js";
import api from "../api";

const product = useProduct();
const landingRoot = ref(null);
useLandingMotion(landingRoot);
const assistant = computed(() => publicAssistantState(product));
const beta = computed(() => product.features.beta_program);
const plan = ref(null);
// Informational fallback, never an entitlement or permission grant.
const propertyLimit = computed(() => plan.value?.property_limit ?? 50);
const storageGb = computed(() =>
  plan.value?.storage_limit_bytes
    ? Math.round(plan.value.storage_limit_bytes / 1024 ** 3)
    : 5,
);
const assistantLimit = computed(() =>
  Array.isArray(plan.value?.features)
    ? plan.value.features.find(
        (feature) =>
          typeof feature === "string" && /asistente.*consultas/i.test(feature),
      )
    : null,
);
const benefits = [
  {
    title: "Ten claro qué queda por cobrar",
    body: "Pagado, parcial o pendiente. Revisa cada alquiler sin repasar hojas de cálculo.",
  },
  {
    title: "Entiende cada inmueble",
    body: "Consulta sus cobros y gastos registrados para saber qué está pasando con tu alquiler.",
  },
  {
    title: "Encuentra lo que necesitas",
    body: "Inquilinos, contratos y documentos junto al inmueble al que pertenecen.",
  },
];
onMounted(async () => {
  if (!beta.value) return;
  try {
    const { data } = await api.get("/public/plans", { timeout: 8000 });
    plan.value = Array.isArray(data.plans)
      ? (data.plans.find((item) => item.code === "beta") ?? null)
      : null;
  } catch {
    // Keep the public explanation readable; permissions always come from Laravel.
  }
});
</script>

<template>
  <div ref="landingRoot" class="beta-landing">
    <a class="beta-skip" href="#contenido">Saltar al contenido</a>
    <header class="beta-header">
      <RouterLink to="/" aria-label="Alquivo, inicio"
        ><BrandLogo light
      /></RouterLink>
      <nav aria-label="Navegación principal">
        <a href="#demostracion">Cómo funciona</a
        ><a href="#asistente">Alquivo AI</a
        ><a href="#planes">{{ beta ? "La beta" : "Cómo empezar" }}</a>
      </nav>
      <div class="beta-header-actions">
        <RouterLink class="beta-login" to="/login">Entrar</RouterLink
        ><RouterLink class="beta-button beta-button-small" to="/register"
          >Probar gratis<ArrowRight :size="16" aria-hidden="true"
        /></RouterLink>
      </div>
    </header>
    <main id="contenido" tabindex="-1">
      <section class="beta-hero" aria-labelledby="beta-hero-title">
        <div class="beta-hero-copy">
          <p class="beta-eyebrow">
            <span class="beta-eyebrow-line" aria-hidden="true"></span>Tu
            patrimonio. Tu tranquilidad.
          </p>
          <h1 id="beta-hero-title">Tus alquileres,<br /><em>en orden.</em></h1>
          <p class="beta-lead">
            Inmuebles, cobros y documentos. Todo en su sitio, con una IA que te
            ayuda a gestionarlos. Para quienes llevan sus propios alquileres.
          </p>
          <div class="beta-hero-actions">
            <RouterLink id="solicitud" class="beta-button" to="/register"
              >Probar gratis<ArrowRight
                :size="19"
                aria-hidden="true" /></RouterLink
            ><a class="beta-text-link" href="#demostracion"
              >Ver cómo funciona<span aria-hidden="true">↓</span></a
            >
          </div>
          <p class="beta-reassurance">
            <span
              ><CircleCheck :size="17" aria-hidden="true" />{{
                beta ? "Beta gratuita" : "Empieza gratis"
              }}</span
            ><span
              ><CircleCheck :size="17" aria-hidden="true" />Sin tarjeta</span
            >
          </p>
        </div>
        <LandingHeroScene />
      </section>

      <div class="beta-chapter-line" aria-hidden="true">
        <span>Menos Excel.</span><span class="beta-chapter-rule"></span
        ><span>Más perspectiva.</span
        ><span class="beta-chapter-number">01 — TU CARTERA</span>
      </div>

      <section
        id="demostracion"
        class="beta-product beta-section"
        aria-labelledby="beta-benefits-title"
      >
        <div class="beta-product-copy" data-landing-reveal>
          <div class="beta-section-heading">
            <p class="beta-eyebrow">De cada inmueble al conjunto</p>
            <h2 id="beta-benefits-title">
              Tus números,<br /><em>con perspectiva.</em>
            </h2>
            <p class="beta-product-lead">
              Qué has cobrado. Qué falta. Qué has gastado. Sin tener que
              reconstruirlo cada mes.
            </p>
          </div>
          <div class="beta-product-benefits">
            <article v-for="(benefit, index) in benefits" :key="benefit.title">
              <span class="beta-benefit-number" aria-hidden="true"
                >0{{ index + 1 }}</span
              >
              <div>
                <h3>{{ benefit.title }}</h3>
                <p>{{ benefit.body }}</p>
              </div>
            </article>
          </div>
        </div>
        <div class="beta-product-stage" data-landing-reveal>
          <div class="beta-stage-caption">
            <span class="beta-stage-dot" aria-hidden="true"></span>Así se ve
            tenerlo en orden<span aria-hidden="true">↘</span>
          </div>
          <LandingDemo />
          <p class="beta-stage-hint">
            Prueba las dos vistas: tus cobros y una consulta a la IA.
          </p>
        </div>
      </section>

      <section
        id="asistente"
        class="beta-ai beta-section"
        aria-labelledby="beta-ai-title"
      >
        <div class="beta-ai-copy">
          <p class="beta-eyebrow">
            <Sparkles :size="17" aria-hidden="true" />Alquivo AI
          </p>
          <h2 id="beta-ai-title">Pregúntale a tus alquileres.</h2>
          <p>
            No necesitas recordar en qué pantalla estaba cada dato. Pregunta por
            tus inmuebles, cobros, gastos o contratos y revisa la información
            consultada.
          </p>
          <p class="beta-ai-availability" role="status">
            <template v-if="assistant === true"
              >Disponible en la beta, con límites de uso.</template
            ><template v-else-if="assistant === false"
              >Las consultas de IA no están activas ahora. Puedes utilizar el
              resto de Alquivo.</template
            ><template v-else
              >Uso opcional, según disponibilidad y límites de tu
              cuenta.</template
            >
          </p>
          <a class="beta-text-link" href="#demostracion"
            >Explorar el ejemplo<ArrowRight :size="17" aria-hidden="true"
          /></a>
        </div>
        <div class="beta-ai-questions" aria-label="Ejemplos de preguntas">
          <p>«¿Qué alquileres tengo pendientes?»</p>
          <p>«¿Cuánto he cobrado de este inmueble?»</p>
          <p>«¿Qué contratos terminan pronto?»</p>
          <div>
            <ShieldCheck :size="20" aria-hidden="true" /><span
              >Tú mantienes el control. Las propuestas de cambios disponibles
              requieren que las revises y confirmes. Comprueba siempre los datos
              importantes.</span
            >
          </div>
        </div>
      </section>

      <section
        id="planes"
        class="beta-offer beta-section"
        aria-labelledby="beta-offer-title"
      >
        <div>
          <p class="beta-eyebrow">
            {{ beta ? "Empieza con calma" : "Tu espacio para organizarte" }}
          </p>
          <h2 id="beta-offer-title">
            {{
              beta
                ? "Pruébalo con tus alquileres."
                : "Conoce Alquivo a tu ritmo."
            }}
          </h2>
          <p>
            Empieza por un inmueble. Añade su alquiler y ve dando forma a tu
            cartera, sin tener que completarlo todo el primer día.
          </p>
          <ol class="beta-first-steps">
            <li><span>1</span>Crea tu cuenta</li>
            <li><span>2</span>Añade tu primer inmueble</li>
            <li><span>3</span>Organiza su alquiler o completa su ficha</li>
          </ol>
          <p class="beta-support-note">
            <MessageCircle :size="20" aria-hidden="true" /><span
              >¿Te atascas? Puedes escribirnos desde el botón de soporte. No es
              atención inmediata; tu consulta queda guardada.</span
            >
          </p>
        </div>
        <article class="beta-plan-card">
          <p class="beta-plan-label">
            {{ beta ? "Beta gratuita" : "Empieza gratis" }}
          </p>
          <h3>
            {{ beta ? "Gratis durante la beta" : "Sin tarjeta para empezar" }}
          </h3>
          <ul>
            <template v-if="beta"
              ><li>
                <Check :size="18" aria-hidden="true" />Hasta
                {{ propertyLimit }} inmuebles
              </li>
              <li>
                <Check :size="18" aria-hidden="true" />{{ storageGb }} GB de
                documentos y fotos
              </li></template
            >
            <li>
              <Check :size="18" aria-hidden="true" />Inquilinos, contratos,
              cobros y gastos
            </li>
            <li>
              <Check :size="18" aria-hidden="true" />Calendario, incidencias e
              informes
            </li>
            <li>
              <Check :size="18" aria-hidden="true" />Exportación de datos y
              soporte
            </li>
            <li v-if="assistant !== false">
              <Check :size="18" aria-hidden="true" />{{
                assistantLimit || "Alquivo AI opcional, con límites de uso"
              }}
            </li>
          </ul>
          <RouterLink class="beta-button" to="/register"
            >Probar gratis<ArrowRight :size="18" aria-hidden="true"
          /></RouterLink>
          <p v-if="beta">
            Sin tarjeta. Te avisaremos antes de que termine la beta. No habrá
            cobros automáticos.
          </p>
          <p v-else>
            Consulta en tu cuenta las funciones y los límites disponibles.
          </p>
        </article>
      </section>

      <section
        id="preguntas"
        class="beta-faq beta-section"
        aria-labelledby="beta-faq-title"
      >
        <div class="beta-section-heading">
          <p class="beta-eyebrow">Antes de empezar</p>
          <h2 id="beta-faq-title">Lo que quizás te estés preguntando.</h2>
        </div>
        <div class="beta-faq-list">
          <details>
            <summary>¿Me sirve si solo tengo uno o pocos inmuebles?</summary>
            <p>
              Sí. Alquivo está pensado para propietarios particulares y pequeños
              inversores que llevan sus propios alquileres. Puedes empezar por
              un solo inmueble, sin configurar toda tu cartera de golpe.
            </p>
          </details>
          <details>
            <summary>¿Tengo que subir contratos para probarlo?</summary>
            <p>
              No. Puedes empezar añadiendo un inmueble y sus datos básicos,
              registrar un alquiler o un gasto y explorar el resumen. Subir
              documentos es opcional.
            </p>
          </details>
          <details>
            <summary>
              ¿Necesito tarjeta? ¿Qué ocurre después de la beta?
            </summary>
            <p v-if="beta">
              No necesitas tarjeta para participar. Te avisaremos antes de
              cerrar la beta y podrás decidir cómo continuar. No se realizará
              ningún cobro automático y podrás exportar tus datos.
            </p>
            <p v-else>
              Puedes empezar sin tarjeta. Revisa las condiciones y límites del
              plan disponible antes de contratar cualquier opción de pago.
            </p>
          </details>
          <details>
            <summary>¿Qué hace Alquivo AI y qué no hace?</summary>
            <p>
              Te ayuda a consultar la información que hayas registrado:
              inmuebles, cobros, gastos y contratos. Algunas operaciones pueden
              prepararse como propuestas que tú revisas y confirmas. No gestiona
              todo de forma autónoma ni sustituye a tu asesor.
            </p>
            <p v-if="assistant === true">
              Las consultas están disponibles en la beta, con los límites de tu
              cuenta y tras aceptar su aviso de privacidad.
            </p>
            <p v-else-if="assistant === false">
              En este momento las consultas no están activas. El resto de la
              gestión sigue disponible.
            </p>
            <p v-else>
              Puedes comprobar su disponibilidad y el límite de consultas en tu
              cuenta antes de activarlo. Su uso es opcional.
            </p>
          </details>
          <details>
            <summary>¿Dónde encuentro información sobre mis datos?</summary>
            <p>
              En la
              <RouterLink to="/privacy">política de privacidad</RouterLink>
              explicamos el tratamiento de los datos y los proveedores. También
              puedes consultar las
              <RouterLink to="/terms">condiciones de uso</RouterLink>. No
              necesitas activar la IA para utilizar el resto de la aplicación.
            </p>
          </details>
          <details>
            <summary>¿Y si necesito ayuda para empezar?</summary>
            <p>
              Usa el recorrido opcional de la aplicación o escríbenos desde
              soporte. Puedes abrir tickets, seguir la conversación y adjuntar
              información del problema sin enviar contraseñas.
            </p>
          </details>
        </div>
      </section>

      <section
        class="beta-closing beta-section"
        aria-labelledby="beta-closing-title"
        itemscope
        itemtype="https://schema.org/SoftwareApplication"
      >
        <div>
          <p class="beta-eyebrow">
            Software de gestión de alquileres para propietarios
          </p>
          <h2 id="beta-closing-title">
            Empieza por un inmueble.<br />Pon su alquiler en orden.
          </h2>
          <p itemprop="description">
            <span itemprop="name">Alquivo</span> reúne inmuebles, inquilinos,
            contratos, cobros, gastos y documentos para propietarios
            particulares y pequeños inversores.
          </p>
          <meta
            itemprop="applicationCategory"
            content="BusinessApplication"
          /><meta itemprop="operatingSystem" content="Web browser" />
        </div>
        <RouterLink class="beta-button" to="/register"
          >Probar gratis<ArrowRight :size="19" aria-hidden="true"
        /></RouterLink>
      </section>
    </main>
    <footer class="beta-footer">
      <div class="beta-footer-main">
        <RouterLink to="/" aria-label="Alquivo, inicio"
          ><BrandLogo light
        /></RouterLink>
        <p>Tus alquileres, en orden.</p>
        <LegalLinks />
      </div>
      <details class="beta-resources">
        <summary>Recursos para propietarios</summary>
        <nav aria-label="Guías para propietarios">
          <RouterLink to="/guias">Todas las guías</RouterLink
          ><RouterLink
            v-for="guide in guides"
            :key="guide.path"
            :to="guide.path"
            >{{ guide.title }}</RouterLink
          >
        </nav>
      </details>
    </footer>
  </div>
</template>
