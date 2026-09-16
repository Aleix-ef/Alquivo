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
  WalletCards,
} from "@lucide/vue";
import api from "../api";
import BrandLogo from "../components/BrandLogo.vue";
import { useProduct } from "../stores/product";

const plans = ref([]);
const product = useProduct();

const fallbackPlans = [
  {
    code: "free",
    name: "Gratuito",
    price_monthly: 0,
    price_yearly: 0,
    property_limit: 1,
    features: [
      "Dashboard, alquileres y finanzas",
      "Un inmueble",
      "50 MB de documentos",
    ],
  },
  {
    code: "founder",
    name: "Plan Fundador",
    price_monthly: 6.99,
    price_yearly: null,
    property_limit: 20,
    features: [
      "Hasta 20 inmuebles",
      "2 GB de documentos",
      "Informes y exportación de datos",
    ],
  },
];

const visiblePlans = computed(() =>
  plans.value.length ? plans.value : fallbackPlans,
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
        <a href="#funciones">Funciones</a>
        <a href="#planes">Planes</a>
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
          <span></span> Beta abierta · Gestión para propietarios
        </p>
        <h1>Gestiona tus alquileres <em>sin complicaciones.</em></h1>
        <p class="hero-lead">
          Cobros, gastos, contratos y rentabilidad. Todo lo importante de tu
          patrimonio, en un único lugar y fácil de entender.
        </p>
        <div class="hero-actions">
          <RouterLink class="button button-large" to="/register">
            Empieza gratis <ArrowRight :size="18" aria-hidden="true" />
          </RouterLink>
          <a class="text-action" href="#como-funciona"
            >Ver cómo funciona <ChevronRight :size="17"
          /></a>
        </div>
        <div class="hero-reassurance" aria-label="Condiciones de prueba">
          <span><CircleCheck :size="16" /> 14 días de prueba</span>
          <span><CircleCheck :size="16" /> Sin tarjeta</span>
          <span><CircleCheck :size="16" /> Después, plan gratuito</span>
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
        <div class="preview-note">Tu patrimonio, con claridad.</div>
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
            ><small>Actualizado con tus movimientos</small>
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
        <h2>Un plan que acompaña tu cartera.</h2>
        <p>
          Todos los planes comienzan con 14 días para probar Alquivo completo.
          Sin tarjeta ni renovación automática.
        </p>
        <p v-if="!product.features.billing_enabled">
          Estamos validando Alquivo: puedes probarlo gratis. Los precios son
          informativos; todavía no aceptamos pagos.
        </p>
      </div>
      <div class="marketing-plans">
        <article
          v-for="plan in visiblePlans"
          :key="plan.code"
          class="marketing-plan"
          :class="{ featured: plan.code === 'founder' }"
        >
          <span v-if="plan.code === 'founder'" class="plan-label"
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
            :class="{ 'button-quiet': plan.code !== 'founder' }"
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
          <p>
            No. Puedes crear tu cuenta, probar todas las funciones durante 14
            días y decidir después con calma.
          </p>
        </details>
        <details>
          <summary>
            ¿Qué pasa cuando termina la prueba?<ChevronRight :size="19" />
          </summary>
          <p>
            Tu cartera pasa al plan gratuito. Puedes seguir gestionando un
            inmueble y consultar y exportar los demás. No se realiza ningún
            cobro automático.
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
        <RouterLink to="/terms">Condiciones</RouterLink
        ><RouterLink to="/privacy">Privacidad</RouterLink
        ><RouterLink to="/login">Entrar</RouterLink>
      </div>
    </footer>
  </main>
</template>
