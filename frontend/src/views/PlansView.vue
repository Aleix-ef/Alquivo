<script setup>
import { onMounted, ref } from "vue";
import { Check } from "@lucide/vue";
import { useRoute, useRouter } from "vue-router";
import api from "../api";
const route = useRoute(),
  router = useRouter(),
  data = ref(null),
  busy = ref(""),
  loading = ref(true),
  error = ref("");
const notice = ref(
  route.query.checkout === "success"
    ? "Has vuelto de Stripe. Comprueba aquí el estado de tu suscripción; la confirmación puede tardar unos instantes."
    : route.query.checkout === "cancelled"
      ? "Has vuelto sin completar la contratación. Puedes revisar tu estado aquí."
      : "",
);
const mb = (bytes) => Number(bytes) / 1024 / 1024;
const formatDate = (value) =>
  new Intl.DateTimeFormat("es-ES", { dateStyle: "long" }).format(
    new Date(value),
  );
const price = (plan) =>
  new Intl.NumberFormat("es-ES", { style: "currency", currency: "EUR" }).format(
    plan.price_monthly,
  );
async function load() {
  loading.value = true;
  error.value = "";
  try {
    data.value = (await api.get("/plans")).data;
    return true;
  } catch (e) {
    error.value =
      e.response?.data?.message ||
      "No se pudieron cargar los planes. Inténtalo de nuevo.";
    return false;
  } finally {
    loading.value = false;
  }
}
async function checkout(code) {
  if (busy.value || !data.value?.billing_enabled) return;
  busy.value = code;
  error.value = "";
  try {
    location.assign(
      (
        await api.post("/billing/checkout", {
          plan: code,
          period: "monthly",
        })
      ).data.url,
    );
  } catch (e) {
    error.value = e.response?.data?.message || "No se pudo iniciar el pago.";
    busy.value = "";
  }
}
async function portal() {
  if (busy.value || !data.value?.billing_enabled) return;
  busy.value = "portal";
  error.value = "";
  try {
    location.assign((await api.post("/billing/portal")).data.url);
  } catch (e) {
    error.value =
      e.response?.data?.message || "No se pudo abrir el portal de facturación.";
    busy.value = "";
  }
}
onMounted(async () => {
  if (await load()) {
    if (route.query.checkout) router.replace("/plans");
  }
});
</script>
<template>
  <main class="page">
    <header class="heading">
      <div>
        <p class="eyebrow">Planes</p>
        <h1>Crece a tu ritmo</h1>
        <p>
          Prueba todas las funciones del Plan Fundador durante 14 días. Después
          puedes continuar gratis. Los precios de los planes están disponibles
          para que conozcas las opciones de Alquivo.
        </p>
      </div>
    </header>
    <p v-if="notice" class="success">{{ notice }}</p>
    <button
      v-if="notice"
      class="button secondary"
      :disabled="loading"
      @click="load"
    >
      Actualizar estado
    </button>
    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <div v-if="!data" class="empty" role="status">
      <template v-if="loading">Cargando planes…</template>
      <button v-else class="button secondary" type="button" @click="load">
        Volver a intentar
      </button>
    </div>
    <template v-else>
      <section v-if="!data.billing_enabled" class="pending-plan" role="status">
        <div>
          <p class="eyebrow">Beta de validación · Sin pagos</p>
          <strong>Puedes probar Alquivo sin tarjeta.</strong>
          <p>
            Los precios son informativos. La contratación todavía no está
            abierta y la prueba no se convierte en una suscripción de pago.
          </p>
          <p v-if="data.current.has_billing_history">
            Si ya tenías una suscripción anterior, este cierre no la cancela.
            Contacta con soporte para revisarla o cancelarla.
          </p>
        </div>
      </section>
      <section class="current-usage">
        <div>
          <strong
            >{{ data.current.properties.used }} de
            {{ data.current.properties.limit }} inmuebles</strong
          ><small
            >Plan {{ data.current.name }} · {{ data.current.status }}</small
          >
        </div>
        <div>
          <strong
            >{{ mb(data.current.storage.used).toFixed(1) }} MB usados</strong
          ><small>de {{ mb(data.current.storage.limit).toFixed(0) }} MB</small>
        </div>
      </section>
      <section v-if="data.current.on_trial" class="pending-plan">
        <div>
          <p class="eyebrow">Prueba del Plan Fundador activa</p>
          <strong
            >Tienes disponibles todos los límites del plan superior</strong
          >
          <small
            >Hasta el {{ formatDate(data.current.trial_ends_at) }}. Después
            pasarás al plan gratuito si no has contratado otro plan.</small
          >
        </div>
      </section>
      <p
        v-if="data.billing_enabled && data.current.payment_pending"
        class="error"
        role="alert"
      >
        Hay un pago pendiente o una suscripción que necesita revisión. Abre la
        gestión de pago para resolverlo, sin contratar otra suscripción.
      </p>
      <p v-if="data.current.ends_at" class="plans-note">
        Tu suscripción finaliza el {{ formatDate(data.current.ends_at) }}.
        Después se aplicarán los límites del plan gratuito.
      </p>
      <p
        v-if="data.current.properties.read_only_count"
        class="plans-note"
        role="status"
      >
        {{ data.current.properties.read_only_count }} inmuebles están en modo
        consulta. Puedes consultar y exportar todos tus datos; sigues
        gestionando los primeros {{ data.current.properties.limit }} inmuebles
        que añadiste.
      </p>
      <button
        v-if="data.current.can_manage_billing"
        class="button secondary billing-portal"
        :disabled="busy"
        @click="portal"
      >
        {{
          busy === "portal"
            ? "Abriendo…"
            : "Gestionar pago, facturas o cancelación"
        }}
      </button>
      <section class="plan-grid">
        <article
          v-for="(plan, code) in data.plans"
          :key="code"
          :class="{ featured: code === 'founder' }"
        >
          <p class="eyebrow">
            {{ code === data.current.code ? "Tu nivel actual" : "Para crecer" }}
          </p>
          <h2>{{ plan.name }}</h2>
          <strong class="plan-price"
            >{{ price(plan) }}<small>/mes</small></strong
          >
          <p>Hasta {{ plan.property_limit }} inmuebles</p>
          <ul>
            <li v-for="feature in plan.features" :key="feature">
              <Check :size="15" />{{ feature }}
            </li>
          </ul>
          <button
            v-if="data.current.can_manage_billing && !plan.checkout_available"
            class="button secondary"
            :disabled="!!busy"
            @click="portal"
          >
            Gestionar en Stripe</button
          ><button
            v-else
            class="button primary"
            :disabled="busy || !plan.checkout_available"
            @click="checkout(code)"
          >
            {{
              busy === code
                ? "Conectando…"
                : !plan.checkout_available
                  ? "Próximamente · Contratación cerrada"
                  : `Elegir ${plan.name}`
            }}
          </button>
        </article>
      </section>
      <p class="plans-note">
        Si finaliza la prueba o vuelves al plan gratuito, podrás seguir
        gestionando el primer inmueble que añadiste. Los demás seguirán
        disponibles para consulta y exportación; sus nuevos cargos y movimientos
        recurrentes quedarán en pausa.
      </p>
      <p v-if="data.billing_enabled" class="plans-note">
        El pago se realiza en Stripe Checkout. Alquivo no recibe ni almacena los
        datos de tu tarjeta.
      </p>
    </template>
  </main>
</template>
