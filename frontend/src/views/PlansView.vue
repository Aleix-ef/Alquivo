<script setup>
import { onMounted, ref } from "vue";
import { Check } from "@lucide/vue";
import { useRoute, useRouter } from "vue-router";
import api from "../api";
const route = useRoute(),
  router = useRouter(),
  data = ref(null),
  period = ref("monthly"),
  busy = ref(""),
  error = ref(""),
  pendingChange = ref(null);
const notice = ref(
  route.query.checkout === "success"
    ? "Pago completado. Stripe está confirmando tu suscripción."
    : route.query.checkout === "cancelled"
      ? "No se realizó ningún cargo."
      : "",
);
const mb = (bytes) => Number(bytes) / 1024 / 1024;
const formatDate = (value) =>
  new Intl.DateTimeFormat("es-ES", { dateStyle: "long" }).format(
    new Date(value),
  );
const price = (plan) =>
  period.value === "monthly"
    ? `${plan.price_monthly} €`
    : `${plan.price_yearly} €`;
async function load() {
  data.value = (await api.get("/plans")).data;
}
async function checkout(code) {
  busy.value = code;
  error.value = "";
  try {
    location.assign(
      (
        await api.post("/billing/checkout", {
          plan: code,
          period: period.value,
        })
      ).data.url,
    );
  } catch (e) {
    error.value = e.response?.data?.message || "No se pudo iniciar el pago.";
    busy.value = "";
  }
}
async function portal() {
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
function askPlanChange(code, name) {
  busy.value = "";
  error.value = "";
  pendingChange.value = { code, name, period: period.value };
}
async function changePlan() {
  const change = pendingChange.value;
  if (!change) return;
  busy.value = change.code;
  error.value = "";
  try {
    const response = await api.post("/billing/change-plan", {
      plan: change.code,
      period: change.period,
    });
    notice.value = response.data.message;
    pendingChange.value = null;
    await load();
  } catch (e) {
    error.value = e.response?.data?.message || "No se pudo cambiar el plan.";
  } finally {
    busy.value = "";
  }
}
async function cancelPlanChange() {
  busy.value = "cancel-change";
  error.value = "";
  try {
    const response = await api.delete("/billing/change-plan");
    notice.value = response.data.message;
    await load();
  } catch (e) {
    error.value =
      e.response?.data?.message || "No se pudo cancelar el cambio programado.";
  } finally {
    busy.value = "";
  }
}
onMounted(async () => {
  await load();
  if (route.query.checkout) router.replace("/plans");
});
</script>
<template>
  <main class="page">
    <header class="heading">
      <div>
        <p class="eyebrow">Planes</p>
        <h1>Crece a tu ritmo</h1>
        <p>
          Prueba todas las funciones de Inversor durante 14 días. Después puedes
          continuar gratis o elegir un plan.
        </p>
      </div>
      <div class="billing-period">
        <button
          :class="{ active: period === 'monthly' }"
          @click="period = 'monthly'"
        >
          Mensual</button
        ><button
          :class="{ active: period === 'yearly' }"
          @click="period = 'yearly'"
        >
          Anual · ahorra
        </button>
      </div>
    </header>
    <p v-if="notice" class="success">{{ notice }}</p>
    <p v-if="error" class="error">{{ error }}</p>
    <div v-if="!data" class="empty">Cargando planes…</div>
    <template v-else
      ><section class="current-usage">
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
          <p class="eyebrow">Prueba Inversor activa</p>
          <strong
            >Tienes disponibles todos los límites del plan superior</strong
          >
          <small
            >Hasta el {{ formatDate(data.current.trial_ends_at) }}. Después
            pasarás al plan gratuito si no has contratado otro plan.</small
          >
        </div>
      </section>
      <section v-if="data.current.pending_change" class="pending-plan">
        <div>
          <p class="eyebrow">Próximo cambio</p>
          <strong
            >{{ data.current.pending_change.name }} ·
            {{
              data.current.pending_change.period === "yearly"
                ? "facturación anual"
                : "facturación mensual"
            }}</strong
          >
          <small
            >Se aplicará el
            {{ formatDate(data.current.pending_change.effective_at) }}. Hasta
            entonces conservas tu plan y límites actuales.</small
          >
        </div>
        <button
          type="button"
          class="button secondary"
          :disabled="busy === 'cancel-change'"
          @click="cancelPlanChange"
        >
          {{ busy === "cancel-change" ? "Cancelando…" : "Cancelar cambio" }}
        </button>
      </section>
      <button
        v-if="data.current.subscribed"
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
          :class="{ featured: code === 'investor' }"
        >
          <p class="eyebrow">
            {{ code === data.current.code ? "Tu nivel actual" : "Para crecer" }}
          </p>
          <h2>{{ plan.name }}</h2>
          <strong class="plan-price"
            >{{ price(plan)
            }}<small>/{{ period === "monthly" ? "mes" : "año" }}</small></strong
          >
          <p>Hasta {{ plan.property_limit }} inmuebles</p>
          <ul>
            <li v-for="feature in plan.features" :key="feature">
              <Check :size="15" />{{ feature }}
            </li>
          </ul>
          <button
            v-if="data.current.subscribed && code === data.current.code"
            class="button secondary"
            @click="portal"
          >
            Gestionar en Stripe</button
          ><button
            v-else-if="data.current.subscribed"
            class="button primary"
            :disabled="busy || !plan.checkout_available"
            @click="askPlanChange(code, plan.name)"
          >
            {{
              busy === code ? "Cambiando…" : `Cambiar a ${plan.name}`
            }}</button
          ><button
            v-else
            class="button primary"
            :disabled="busy || !plan.checkout_available"
            @click="checkout(code)"
          >
            {{ busy === code ? "Conectando…" : `Elegir ${plan.name}` }}
          </button>
        </article>
      </section>
      <p class="plans-note">
        El pago se realiza en Stripe Checkout. Nareo no recibe ni almacena los
        datos de tu tarjeta.
      </p>
      <Teleport to="body">
        <div
          v-if="pendingChange"
          class="modal-backdrop"
          @click.self="pendingChange = null"
        >
          <section
            class="plan-modal"
            role="dialog"
            aria-modal="true"
            @click.stop
          >
            <p class="eyebrow">Confirmar cambio</p>
            <h2>Cambiar a {{ pendingChange.name }}</h2>
            <p>
              Has elegido facturación
              {{ pendingChange.period === "monthly" ? "mensual" : "anual" }}. No
              se realizará ningún cobro ni se modificarán tus límites ahora. El
              nuevo plan comenzará únicamente cuando Stripe complete tu próxima
              renovación.
            </p>
            <p v-if="error" class="error">{{ error }}</p>
            <div class="plan-modal-actions">
              <button
                class="button secondary"
                type="button"
                @click="pendingChange = null"
              >
                Cancelar
              </button>
              <button
                class="button primary"
                type="button"
                :disabled="busy === pendingChange.code"
                @click="changePlan"
              >
                {{ busy ? "Aplicando…" : "Confirmar cambio" }}
              </button>
            </div>
          </section>
        </div>
      </Teleport></template
    >
  </main>
</template>
