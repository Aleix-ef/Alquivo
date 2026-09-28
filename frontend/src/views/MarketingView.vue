<script setup>
import { computed, onMounted, ref } from "vue";
import {
  ArrowRight,
  BarChart3,
  BellRing,
  Building2,
  CalendarDays,
  Check,
  ChevronRight,
  CircleCheck,
  FileText,
  FolderLock,
  ReceiptText,
  Sparkles,
  WalletCards,
} from "@lucide/vue";
import api from "../api";
import BrandLogo from "../components/BrandLogo.vue";
import LegalLinks from "../components/LegalLinks.vue";
import { useProduct } from "../stores/product";
import { guides } from "../content/guides.js";

const plans = ref([]);
const product = useProduct();

const fallbackPlans = [
  {
    code: "beta",
    name: "Beta gratuita",
    price_monthly: 0,
    price_yearly: null,
    property_limit: 10,
    features: [
      "Dashboard, alquileres y finanzas",
      "Hasta 10 inmuebles",
      "1 GB de documentos y fotos",
      "Informes y exportación de datos",
    ],
  },
];

const visiblePlans = computed(() =>
  plans.value.length
    ? plans.value
    : product.features.beta_program
      ? fallbackPlans
      : [],
);
const money = (value) =>
  new Intl.NumberFormat("es-ES", {
    style: "currency",
    currency: "EUR",
    maximumFractionDigits: value % 1 ? 2 : 0,
  }).format(value);

onMounted(async () => {
  try {
    const { data } = await api.get("/public/plans");
    if (Array.isArray(data.plans)) plans.value = data.plans;
  } catch {
    // The page remains useful during a temporary API outage.
  }
});
</script>

<template>
  <main class="marketing-page">
    <header class="marketing-header">
      <RouterLink class="marketing-logo" to="/" aria-label="Alquivo, inicio">
        <BrandLogo />
      </RouterLink>
      <nav aria-label="Navegación principal">
        <a href="#como-funciona">Cómo funciona</a>
        <a href="#asistente">Asistente IA</a>
        <a href="#funciones">Funciones</a>
        <a href="#planes">Planes</a>
        <RouterLink to="/guias">Guías</RouterLink>
      </nav>
      <div class="marketing-header-actions">
        <RouterLink class="button button-quiet marketing-login" to="/login"
          >Entrar</RouterLink
        >
        <RouterLink class="button button-small" to="/register"
          >Empieza gratis</RouterLink
        >
      </div>
    </header>

    <section class="hero-section">
      <div class="hero-copy">
        <p class="marketing-eyebrow">
          <span></span>
          {{
            product.features.beta_program ? "Beta gratuita" : "Plan gratuito"
          }}
          ·
          {{
            product.features.assistant
              ? "Asistente IA disponible"
              : "Asistente IA en preparación"
          }}
        </p>
        <h1>Gestiona tu cartera hoy. <em>Entiéndela mejor con IA.</em></h1>
        <p class="hero-lead">
          {{
            product.features.assistant
              ? "Organiza inmuebles, cobros y contratos desde un mismo lugar. Pregunta al asistente por la información que hayas registrado y decide si quieres activarlo tras leer su aviso de privacidad."
              : "Organiza inmuebles, cobros y contratos desde un mismo lugar. Estamos preparando un asistente para consultar los datos que registres en Alquivo."
          }}
        </p>
        <div class="hero-actions">
          <RouterLink class="button button-large" to="/register">
            {{
              product.features.beta_program
                ? "Probar la beta gratis"
                : "Crear cuenta gratis"
            }}
            <ArrowRight :size="18" aria-hidden="true" />
          </RouterLink>
          <a class="text-action" href="#asistente"
            >Conocer el asistente <ChevronRight :size="17"
          /></a>
        </div>
        <div class="hero-reassurance" aria-label="Condiciones de acceso">
          <span
            ><CircleCheck :size="16" />
            {{
              product.features.beta_program ? "Beta gratuita" : "Plan gratuito"
            }}</span
          >
          <span><CircleCheck :size="16" /> Sin tarjeta</span>
          <span
            ><CircleCheck :size="16" />
            {{
              product.features.beta_program
                ? "Hasta 10 inmuebles"
                : "Sin renovación automática"
            }}</span
          >
        </div>
      </div>

      <div
        class="product-preview"
        aria-label="Vista previa del panel de Alquivo"
      >
        <div class="preview-window">
          <div class="preview-sidebar">
            <div class="preview-mini-logo"><i></i><b></b></div>
            <span class="preview-nav active"></span
            ><span class="preview-nav"></span> <span class="preview-nav"></span
            ><span class="preview-nav"></span>
            <span class="preview-nav"></span>
          </div>
          <div class="preview-main">
            <div class="preview-top"><span></span><i></i></div>
            <p>Tu patrimonio</p>
            <div class="preview-value">
              248.500 € <small>+ 4,2% este año</small>
            </div>
            <div class="preview-cards">
              <div>
                <small>Ingresos del mes</small><strong>1.940 €</strong
                ><span class="up">+ 3,1%</span>
              </div>
              <div>
                <small>Gastos previstos</small><strong>382 €</strong
                ><span>Este mes</span>
              </div>
              <div>
                <small>Rentabilidad neta</small><strong>5,8%</strong
                ><span class="up">Estable</span>
              </div>
            </div>
            <div class="preview-lower">
              <div class="preview-chart">
                <div class="chart-heading">
                  <strong>Evolución de ingresos</strong><span>2026</span>
                </div>
                <div class="chart-bars">
                  <i></i><i></i><i></i><i></i><i></i><i></i><i></i>
                </div>
              </div>
              <div class="preview-events">
                <strong>Próximamente</strong>
                <p><i></i> Cobro alquiler · 3 sep.</p>
                <p><i></i> Renovación contrato · 12 sep.</p>
              </div>
            </div>
          </div>
        </div>
        <div class="preview-note">Ejemplo ilustrativo con datos ficticios.</div>
      </div>
    </section>

    <section
      id="asistente"
      class="ai-preview-section"
      aria-labelledby="ai-preview-title"
    >
      <div class="ai-preview-inner">
        <div class="ai-preview-copy">
          <p class="marketing-eyebrow">
            <Sparkles :size="17" />Alquivo AI ·
            {{ product.features.assistant ? "Disponible" : "En preparación" }}
          </p>
          <h2 id="ai-preview-title">
            Una pregunta sencilla.<br />Una respuesta con contexto.
          </h2>
          <p>
            {{
              product.features.assistant
                ? "Consulta con el asistente la información que tengas registrada: alquileres, cobros, contratos y avisos. Si falta un dato, te lo dirá. Tú decides si lo activas antes de usarlo."
                : "Estamos preparando un asistente que consultará la información que registres: alquileres, cobros, contratos y avisos. Te avisaremos cuando esté disponible."
            }}
          </p>
          <ul class="ai-preview-points">
            <li><Check :size="18" />Respuestas basadas en tus registros</li>
            <li><Check :size="18" />Importes y fechas fáciles de revisar</li>
            <li><Check :size="18" />Sin inventar información que falta</li>
          </ul>
          <RouterLink class="button button-large" to="/register">
            {{
              product.features.beta_program
                ? "Empezar con la beta gratuita"
                : "Empezar gratis"
            }}
            <ArrowRight :size="18" aria-hidden="true" />
          </RouterLink>
        </div>
        <div
          class="ai-preview-card"
          aria-label="Ejemplo ilustrativo de una conversación futura con Alquivo AI"
        >
          <header class="ai-preview-card-header">
            <span class="ai-preview-brand"
              ><Sparkles :size="19" />Alquivo AI</span
            >
            <span class="ai-preview-status">Vista previa</span>
          </header>
          <p class="ai-preview-caption">
            Ejemplo ilustrativo · Sin datos reales
          </p>
          <div class="ai-question">
            ¿Qué alquileres y contratos debería revisar?
          </div>
          <div class="ai-answer">
            <strong>Así responderá Alquivo</strong>
            <p>
              Consultaré los cobros y las fechas que hayas registrado. Te
              mostraré los datos y de dónde salen; si falta información, te lo
              indicaré.
            </p>
          </div>
          <div
            class="ai-preview-topics"
            aria-label="Temas de consulta previstos"
          >
            <span>Cobros</span><span>Contratos</span><span>Avisos</span>
          </div>
        </div>
      </div>
    </section>

    <section id="como-funciona" class="simple-section process-section">
      <div class="section-heading">
        <p class="marketing-eyebrow">
          Menos hojas de cálculo. Más perspectiva.
        </p>
        <h2>Lo esencial, donde debe estar.</h2>
        <p>
          No necesitas aprender un ERP. Añade tu cartera y deja que Alquivo
          ordene el día a día.
        </p>
      </div>
      <div class="process-grid">
        <article>
          <span>01</span><Building2 :size="25" />
          <h3>Añade tu cartera</h3>
          <p>
            Registra cada inmueble y su información clave sin formularios
            interminables.
          </p>
        </article>
        <article>
          <span>02</span><WalletCards :size="25" />
          <h3>Ordena tus movimientos</h3>
          <p>
            Ten a mano cobros, gastos y vencimientos para saber qué ocurre este
            mes.
          </p>
        </article>
        <article>
          <span>03</span><BarChart3 :size="25" />
          <h3>Decide con claridad</h3>
          <p>
            Consulta el valor, el resultado y la rentabilidad de tu patrimonio
            de un vistazo.
          </p>
        </article>
      </div>
    </section>

    <section id="funciones" class="feature-section">
      <div class="section-heading">
        <p class="marketing-eyebrow">Una visión completa, sin ruido.</p>
        <h2>Hecho para quien invierte.</h2>
      </div>
      <div class="feature-grid">
        <article class="feature-card feature-card-large">
          <div class="feature-icon"><BarChart3 /></div>
          <h3>Tu patrimonio, en una sola vista</h3>
          <p>
            Valor estimado, ingresos, gastos y rentabilidad en un resumen que
            puedes entender en segundos.
          </p>
          <div class="feature-stat">
            <span>Resultado neto anual</span><strong>+ 14.860 €</strong
            ><small>Ejemplo ilustrativo, no una previsión de resultados</small>
          </div>
        </article>
        <article class="feature-card">
          <div class="feature-icon"><ReceiptText /></div>
          <h3>Cobros bajo control</h3>
          <p>
            Registra alquileres e incidencias de pago y detecta rápidamente lo
            pendiente.
          </p>
        </article>
        <article class="feature-card">
          <div class="feature-icon"><CalendarDays /></div>
          <h3>Nada se te pasa</h3>
          <p>
            Contratos, renovaciones y recordatorios en una agenda pensada para
            propietarios.
          </p>
        </article>
        <article class="feature-card">
          <div class="feature-icon"><FolderLock /></div>
          <h3>Documentos, en su sitio</h3>
          <p>
            Guarda contratos y facturas junto al inmueble al que pertenecen.
          </p>
        </article>
        <article class="feature-card">
          <div class="feature-icon"><BellRing /></div>
          <h3>Atiende lo importante</h3>
          <p>
            Centraliza incidencias para no depender de mensajes y notas
            dispersas.
          </p>
        </article>
      </div>
    </section>

    <section class="calm-section">
      <div>
        <p class="marketing-eyebrow">Pensado para tu tranquilidad.</p>
        <h2>La información de tus inmuebles es tuya.</h2>
      </div>
      <div class="calm-points">
        <p><Check :size="18" /> Cada cartera opera de forma aislada.</p>
        <p><Check :size="18" /> Tus documentos se guardan de forma privada.</p>
        <p><Check :size="18" /> Puedes empezar sin compartir una tarjeta.</p>
      </div>
    </section>

    <section id="planes" class="plans-section">
      <div class="section-heading centered">
        <p class="marketing-eyebrow">Empieza con calma.</p>
        <h2>
          {{
            product.features.beta_program
              ? "La beta es gratis. Tu opinión nos ayuda a crecer."
              : "Un plan que acompaña tu cartera."
          }}
        </h2>
        <p v-if="product.features.beta_program">
          Gestiona hasta 10 inmuebles durante toda la beta, sin tarjeta ni
          pagos. No es una prueba de 14 días.
        </p>
        <p v-else>
          Empieza con el plan gratuito y amplía cuando lo necesites. Sin tarjeta
          ni pagos automáticos.
        </p>
        <p
          v-if="
            !product.features.beta_program && !product.features.billing_enabled
          "
        >
          Estamos validando Alquivo: puedes probarlo gratis. Los precios son
          informativos; todavía no aceptamos pagos.
        </p>
      </div>
      <div
        class="marketing-plans"
        :class="{ 'beta-only': product.features.beta_program }"
      >
        <article
          v-for="plan in visiblePlans"
          :key="plan.code"
          class="marketing-plan"
          :class="{ featured: plan.code === 'founder' || plan.code === 'beta' }"
        >
          <span v-if="plan.code === 'beta'" class="plan-label"
            >Acceso durante toda la beta</span
          >
          <span v-else-if="plan.code === 'founder'" class="plan-label"
            >Precio fundador</span
          >
          <p class="plan-name">{{ plan.name }}</p>
          <div class="plan-price">
            <template v-if="plan.price_monthly"
              ><strong>{{ money(plan.price_monthly) }}</strong
              ><span>/ mes</span></template
            ><strong v-else>Gratis</strong>
          </div>
          <p class="plan-description">
            {{
              plan.property_limit === 1
                ? "Para empezar con un inmueble."
                : `Para gestionar hasta ${plan.property_limit} inmuebles.`
            }}
          </p>
          <RouterLink
            class="button"
            :class="{ 'button-quiet': plan.code === 'free' }"
            to="/register"
            >Empezar gratis</RouterLink
          >
          <ul>
            <li v-for="feature in plan.features" :key="feature">
              <Check :size="16" />{{ feature }}
            </li>
          </ul>
          <small v-if="plan.price_yearly"
            >{{ money(plan.price_yearly) }} al año si prefieres pagar
            anualmente.</small
          >
        </article>
      </div>
    </section>

    <section class="faq-section">
      <div class="section-heading">
        <p class="marketing-eyebrow">Dudas habituales.</p>
        <h2>Claro desde el principio.</h2>
      </div>
      <div class="faq-list">
        <details>
          <summary>
            ¿Necesito tarjeta para probar Alquivo?<ChevronRight :size="19" />
          </summary>
          <p v-if="product.features.beta_program">
            No. La Beta es gratuita y no te pediremos datos de pago.
          </p>
          <p v-else>
            No. Puedes empezar con el plan gratuito sin tarjeta. Solo pagarás si
            decides contratar un plan.
          </p>
        </details>
        <details>
          <summary>
            {{
              product.features.beta_program
                ? "¿Qué pasará al terminar la beta?"
                : "¿Puedo seguir en el plan gratuito?"
            }}<ChevronRight :size="19" />
          </summary>
          <p v-if="product.features.beta_program">
            Te avisaremos con antelación y te ofreceremos condiciones especiales
            de agradecimiento por haber participado. Tú decidirás si quieres
            continuar: no habrá ningún cobro automático. Podrás exportar tus
            datos antes del cambio.
          </p>
          <p v-else>
            Sí. Puedes gestionar un inmueble con el plan gratuito y decidir
            cuándo quieres ampliar. No se realiza ningún cobro automático.
          </p>
        </details>
        <details>
          <summary>
            ¿Ya puedo usar el asistente de IA?<ChevronRight :size="19" />
          </summary>
          <p v-if="product.features.assistant">
            Sí. Si tu plan incluye consultas, puedes activar el asistente tras
            revisar el aviso de privacidad. Responde usando los datos que hayas
            registrado.
          </p>
          <p v-else>
            Aún estamos revisando sus respuestas. Te avisaremos cuando esté
            disponible; ya puedes organizar tus inmuebles, alquileres y
            finanzas.
          </p>
        </details>
        <details>
          <summary>
            ¿Puedo gestionar distintos tipos de inmueble?<ChevronRight
              :size="19"
            />
          </summary>
          <p>
            Sí. Alquivo está preparado para viviendas, locales, oficinas,
            garajes, trasteros, terrenos y edificios.
          </p>
        </details>
        <details>
          <summary>
            ¿Alquivo sustituye a mi asesor?<ChevronRight :size="19" />
          </summary>
          <p>
            No. Es tu espacio para organizar el patrimonio y tener una visión
            clara. No ofrece asesoramiento fiscal, legal ni financiero.
          </p>
        </details>
      </div>
    </section>

    <section
      class="simple-section"
      aria-labelledby="about-alquivo"
      itemscope
      itemtype="https://schema.org/SoftwareApplication"
    >
      <div class="section-heading">
        <p class="marketing-eyebrow">Qué es Alquivo</p>
        <h2 id="about-alquivo">
          <span itemprop="name">Alquivo</span>: gestión de alquileres para
          propietarios.
        </h2>
        <p itemprop="description">
          Alquivo es una aplicación web para organizar inmuebles, inquilinos,
          contratos, cobros, gastos, documentos e incidencias. Está pensada para
          propietarios particulares y pequeños inversores que quieren tener una
          visión clara de su cartera.
        </p>
        <p>
          <span itemprop="applicationCategory"
            >Software de gestión inmobiliaria</span
          >
          · <span itemprop="operatingSystem">Navegador web</span>
        </p>
        <p>
          No es una inmobiliaria, no cobra automáticamente a tus inquilinos y no
          sustituye a tu asesor. Los resúmenes dependen de los datos que
          registres.
        </p>
      </div>
    </section>

    <section class="simple-section" aria-labelledby="guides-heading">
      <div class="section-heading">
        <p class="marketing-eyebrow">Recursos para propietarios</p>
        <h2 id="guides-heading">Empieza por tenerlo claro.</h2>
      </div>
      <div class="guide-grid">
        <article v-for="guide in guides" :key="guide.path" class="guide-card">
          <h3>
            <RouterLink :to="guide.path">{{ guide.title }}</RouterLink>
          </h3>
          <p>{{ guide.description }}</p>
        </article>
      </div>
    </section>

    <section class="closing-section">
      <p class="marketing-eyebrow"><span></span> Empieza hoy</p>
      <h2>Deja de buscar tus números.<br />Empieza a entenderlos.</h2>
      <p>Tu cartera merece un espacio propio.</p>
      <RouterLink class="button button-large" to="/register"
        >Crear mi cuenta gratis <ArrowRight :size="18"
      /></RouterLink>
    </section>

    <footer class="marketing-footer">
      <BrandLogo compact />
      <p>Gestiona tus alquileres sin complicaciones.</p>
      <div>
        <RouterLink to="/guias">Guías para propietarios</RouterLink
        ><RouterLink to="/login">Entrar</RouterLink>
      </div>
      <LegalLinks />
    </footer>
  </main>
</template>
