<script setup>
import { ref, onMounted, computed } from "vue";
import { Building2, BadgeEuro, KeyRound, Check } from "@lucide/vue";
import api from "../api";
import { useSession } from "../session";
const s = useSession(),
  data = ref(null),
  m = computed(() => data.value?.metrics || {}),
  setupSteps = computed(() => [
    {
      done: Boolean(data.value?.properties.length),
      title: "Añade tu primera propiedad",
      detail: "Nombre, dirección y tipo son suficientes para empezar.",
      to: "/properties?new=1",
      icon: Building2,
    },
    {
      done: data.value?.properties.some(
        (property) => Number(property.current_value) > 0,
      ),
      title: "Indica su valor actual",
      detail: "Así verás el patrimonio y su evolución.",
      to: data.value?.properties[0]
        ? `/properties/${data.value.properties[0].id}`
        : "/properties?new=1",
      icon: BadgeEuro,
    },
    {
      done: Number(m.value.contracted_rent) > 0,
      title: "Registra un alquiler",
      detail: "Vincula al inquilino y controla cada cobro.",
      to: data.value?.properties.length ? "/leases?new=1" : "/properties?new=1",
      icon: KeyRound,
    },
  ]),
  setupProgress = computed(
    () => setupSteps.value.filter((step) => step.done).length,
  ),
  money = (v) =>
    new Intl.NumberFormat("es-ES", {
      style: "currency",
      currency: "EUR",
      maximumFractionDigits: 0,
    }).format(v || 0);
onMounted(async () => (data.value = (await api.get("/dashboard")).data));
</script>
<template>
  <main class="page">
    <header class="heading">
      <div>
        <p class="eyebrow">Tu patrimonio</p>
        <h1>Buenos días, {{ s.user?.name?.split(" ")[0] }}</h1>
        <p>Así está tu cartera este mes.</p>
      </div>
    </header>
    <div v-if="!data" class="empty">Preparando tu resumen…</div>
    <template v-else>
      <section v-if="setupProgress < setupSteps.length" class="setup-panel">
        <header>
          <div>
            <p class="eyebrow">Primeros pasos</p>
            <h2>Prepara tu patrimonio</h2>
            <p>{{ setupProgress }} de {{ setupSteps.length }} completados</p>
          </div>
          <strong
            >{{
              Math.round((setupProgress / setupSteps.length) * 100)
            }}%</strong
          >
        </header>
        <div class="setup-progress">
          <span
            :style="{ width: `${(setupProgress / setupSteps.length) * 100}%` }"
          ></span>
        </div>
        <div class="setup-steps">
          <RouterLink
            v-for="step in setupSteps"
            :key="step.title"
            :to="step.to"
            :class="{ done: step.done }"
          >
            <span
              ><Check v-if="step.done" :size="17" /><component
                :is="step.icon"
                v-else
                :size="17"
            /></span>
            <div>
              <strong>{{ step.title }}</strong
              ><small>{{ step.detail }}</small>
            </div>
          </RouterLink>
        </div>
      </section>
      <section class="hero">
        <div>
          <span>Valor estimado del patrimonio</span
          ><strong>{{ money(m.portfolio_value) }}</strong
          ><small>Patrimonio neto {{ money(m.net_equity) }}</small>
        </div>
        <div>
          <span>Rentabilidad bruta</span
          ><strong>{{
            m.gross_yield === null ? "—" : m.gross_yield + "%"
          }}</strong>
        </div>
      </section>
      <section class="metrics">
        <article>
          <span>Ingresos</span><strong>{{ money(m.monthly_income) }}</strong
          ><small>Este mes</small>
        </article>
        <article>
          <span>Gastos</span><strong>{{ money(m.monthly_expenses) }}</strong
          ><small>Este mes</small>
        </article>
        <article>
          <span>Beneficio neto</span><strong>{{ money(m.net_profit) }}</strong
          ><small>Ingresos menos gastos</small>
        </article>
        <article>
          <span>Ocupación</span
          ><strong>{{
            m.occupancy_rate === null ? "—" : m.occupancy_rate + "%"
          }}</strong
          ><small>de la cartera</small>
        </article>
      </section>
      <section class="panels">
        <article class="panel">
          <p class="eyebrow">Atención</p>
          <h2>
            {{
              data.attention.length
                ? "Necesita tu revisión"
                : "Todo bajo control"
            }}
          </h2>
          <div v-if="data.attention.length" class="attention-list">
            <RouterLink
              v-for="a in data.attention"
              :key="a.type + a.detail + a.date"
              :to="
                a.type === 'lease_expiring'
                  ? '/leases'
                  : a.type === 'issue'
                    ? '/issues'
                    : a.type === 'document_expiry'
                      ? '/documents?status=upcoming'
                      : a.type === 'reminder'
                        ? '/calendar'
                        : '/finance'
              "
              class="attention-row"
              ><div>
                <strong>{{ a.title }}</strong
                ><small>{{ a.detail }} · {{ a.date }}</small>
              </div>
              <strong v-if="a.amount">{{ money(a.amount) }}</strong></RouterLink
            >
          </div>
          <div v-else class="empty">
            No tienes cobros atrasados ni vencimientos próximos.
          </div>
        </article>
        <article class="panel">
          <p class="eyebrow">Tu cartera</p>
          <h2>Propiedades</h2>
          <div v-if="data.properties.length" class="mini-properties">
            <RouterLink
              v-for="p in data.properties"
              :key="p.id"
              :to="'/properties/' + p.id"
              ><strong>{{ p.name }}</strong
              ><small>{{ money(p.current_value) }}</small></RouterLink
            >
          </div>
          <div v-else class="empty">
            <p>Añade tu primera propiedad para empezar a ver su rendimiento.</p>
            <RouterLink class="button secondary" to="/properties?new=1"
              >Añadir propiedad</RouterLink
            >
          </div>
        </article>
      </section>
    </template>
  </main>
</template>
