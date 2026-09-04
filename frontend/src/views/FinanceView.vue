<script setup>
import { ref, computed, onMounted } from "vue";
import { ArrowDownLeft, ArrowUpRight, Plus } from "@lucide/vue";
import { useRoute, useRouter } from "vue-router";
import api from "../api";
const route = useRoute(),
  router = useRouter(),
  tx = ref([]),
  leases = ref([]),
  properties = ref([]),
  recurringRules = ref([]),
  saving = ref(false),
  error = ref(""),
  show = computed(() => route.query.new === "1"),
  form = ref({
    direction: "expense",
    category: "maintenance",
    description: "",
    amount: "",
    transaction_date: new Date().toISOString().slice(0, 10),
    status: "paid",
    property_id: "",
    recurring: false,
    frequency: "monthly",
  });
const money = (v) =>
    new Intl.NumberFormat("es-ES", {
      style: "currency",
      currency: "EUR",
    }).format(v || 0),
  income = computed(() =>
    tx.value
      .filter((x) => x.direction === "income" && x.status === "paid")
      .reduce((a, x) => a + Number(x.amount), 0),
  ),
  expenses = computed(() =>
    tx.value
      .filter((x) => x.direction === "expense" && x.status === "paid")
      .reduce((a, x) => a + Number(x.amount), 0),
  ),
  pending = computed(() =>
    leases.value
      .flatMap((l) => l.charges || [])
      .reduce((a, c) => a + Number(c.amount) - Number(c.paid_amount), 0),
  ),
  pendingCharges = computed(() =>
    leases.value.flatMap((lease) =>
      (lease.charges || [])
        .filter((charge) => !["paid", "cancelled"].includes(charge.status))
        .map((charge) => ({ ...charge, lease })),
    ),
  ),
  pendingTransactions = computed(() =>
    tx.value.filter((transaction) => transaction.status === "pending"),
  );
async function load() {
  const [t, l, p, r] = await Promise.all([
    api.get("/transactions"),
    api.get("/leases"),
    api.get("/properties"),
    api.get("/recurring-rules"),
  ]);
  tx.value = t.data.data;
  leases.value = l.data.data;
  properties.value = p.data.data;
  recurringRules.value = r.data.data;
}
async function save() {
  saving.value = true;
  error.value = "";
  try {
    const payload = {
      property_id: form.value.property_id
        ? Number(form.value.property_id)
        : null,
      direction: form.value.direction,
      category: form.value.category,
      description: form.value.description,
      amount: Number(form.value.amount),
    };
    if (form.value.recurring) {
      await api.post("/recurring-rules", {
        ...payload,
        frequency: form.value.frequency,
        starts_on: form.value.transaction_date,
      });
    } else {
      await api.post("/transactions", {
        ...payload,
        transaction_date: form.value.transaction_date,
        status: form.value.status,
      });
    }
    router.replace("/finance");
    await load();
  } catch (e) {
    error.value =
      e.response?.data?.message || "No se pudo guardar el movimiento.";
  } finally {
    saving.value = false;
  }
}
async function collect(charge) {
  const remaining = Number(charge.amount) - Number(charge.paid_amount);
  await api.post(`/rent-charges/${charge.id}/payments`, {
    amount: remaining,
    transaction_date: new Date().toISOString().slice(0, 10),
    payment_method: "transfer",
  });
  await load();
}
async function markPaid(transaction) {
  await api.put(`/transactions/${transaction.id}`, {
    status: "paid",
    transaction_date: new Date().toISOString().slice(0, 10),
  });
  await load();
}
async function cancelTransaction(transaction) {
  if (!window.confirm(`¿Cancelar “${transaction.description}”?`)) return;
  await api.put(`/transactions/${transaction.id}`, { status: "cancelled" });
  await load();
}
async function pauseRule(rule) {
  await api.put(`/recurring-rules/${rule.id}`, { active: !rule.active });
  await load();
}
onMounted(load);
</script>
<template>
  <main class="page">
    <header class="heading">
      <div>
        <p class="eyebrow">Finanzas</p>
        <h1>Tu dinero, sin ruido</h1>
        <p>Ingresos, gastos y alquileres pendientes.</p>
      </div>
      <RouterLink class="button primary" to="?new=1"
        ><Plus :size="16" />Añadir movimiento</RouterLink
      >
    </header>
    <section class="finance-summary">
      <article>
        <span>Ingresos cobrados</span><strong>{{ money(income) }}</strong>
      </article>
      <article>
        <span>Gastos pagados</span><strong>{{ money(expenses) }}</strong>
      </article>
      <article>
        <span>Beneficio</span><strong>{{ money(income - expenses) }}</strong>
      </article>
      <article>
        <span>Alquiler pendiente</span><strong>{{ money(pending) }}</strong>
      </article>
    </section>
    <section
      v-if="pendingCharges.length"
      class="panel finance-panel charges-panel"
    >
      <p class="eyebrow">Por cobrar</p>
      <h2>Alquileres pendientes</h2>
      <div class="movement-list">
        <div v-for="charge in pendingCharges" :key="charge.id" class="movement">
          <span class="pending-dot"></span>
          <div>
            <strong>{{ charge.lease.property?.name }}</strong>
            <small
              >Vencimiento {{ charge.due_date }} · {{ charge.period }}</small
            >
          </div>
          <button
            class="button secondary"
            type="button"
            @click="collect(charge)"
          >
            Cobrar
            {{ money(Number(charge.amount) - Number(charge.paid_amount)) }}
          </button>
        </div>
      </div>
    </section>
    <section
      v-if="pendingTransactions.length"
      class="panel finance-panel charges-panel"
    >
      <p class="eyebrow">Pendiente de confirmar</p>
      <h2>Movimientos previstos</h2>
      <div class="movement-list">
        <div
          v-for="transaction in pendingTransactions"
          :key="transaction.id"
          class="movement"
        >
          <span class="pending-dot"></span>
          <div>
            <strong>{{ transaction.description }}</strong>
            <small
              >{{ transaction.property?.name || "General" }} ·
              {{ transaction.due_date }}</small
            >
          </div>
          <span class="movement-actions">
            <button
              class="button secondary"
              type="button"
              @click="markPaid(transaction)"
            >
              Marcar pagado · {{ money(transaction.amount) }}
            </button>
            <button
              class="button secondary"
              type="button"
              @click="cancelTransaction(transaction)"
            >
              Cancelar
            </button>
          </span>
        </div>
      </div>
    </section>
    <section
      v-if="recurringRules.length"
      class="panel finance-panel charges-panel"
    >
      <p class="eyebrow">Automático</p>
      <h2>Movimientos recurrentes</h2>
      <div class="rule-grid">
        <article
          v-for="rule in recurringRules"
          :key="rule.id"
          class="rule-card"
        >
          <div>
            <strong>{{ rule.description }}</strong
            ><small
              >{{ rule.property?.name || "General" }} ·
              {{ rule.frequency }}</small
            >
          </div>
          <strong>{{ money(rule.amount) }}</strong>
          <button
            class="button secondary"
            type="button"
            @click="pauseRule(rule)"
          >
            {{ rule.active ? "Pausar" : "Activar" }}
          </button>
        </article>
      </div>
    </section>
    <section class="panel finance-panel">
      <p class="eyebrow">Actividad</p>
      <h2>Últimos movimientos</h2>
      <div v-if="tx.length" class="movement-list">
        <div v-for="t in tx" :key="t.id" class="movement">
          <span :class="t.direction"
            ><ArrowUpRight v-if="t.direction === 'income'" /><ArrowDownLeft
              v-else
          /></span>
          <div>
            <strong>{{ t.description }}</strong
            ><small
              >{{ t.property?.name || "General" }} ·
              {{ t.transaction_date }}</small
            >
          </div>
          <strong :class="t.direction"
            >{{ t.direction === "expense" ? "-" : "+"
            }}{{ money(t.amount) }}</strong
          >
        </div>
      </div>
      <div v-else class="empty"><p>Aún no hay movimientos.</p></div>
    </section>
    <div v-if="show" class="drawer-bg" @click.self="router.replace('/finance')">
      <form class="drawer" @submit.prevent="save">
        <header>
          <div>
            <p class="eyebrow">Nuevo movimiento</p>
            <h2>Registra una operación</h2>
          </div>
        </header>
        <p v-if="error" class="error">{{ error }}</p>
        <label
          >Tipo<select v-model="form.direction">
            <option value="expense">Gasto</option>
            <option value="income">Ingreso</option>
          </select></label
        ><label>Concepto<input v-model="form.description" required /></label
        ><label
          >Categoría<select v-model="form.category">
            <option value="rent">Alquiler</option>
            <option value="maintenance">Mantenimiento</option>
            <option value="tax">Impuestos</option>
            <option value="insurance">Seguro</option>
            <option value="other">Otro</option>
          </select></label
        ><label
          >Propiedad<select v-model="form.property_id">
            <option value="">General</option>
            <option v-for="p in properties" :key="p.id" :value="p.id">
              {{ p.name }}
            </option>
          </select></label
        ><label
          >Importe<input
            v-model="form.amount"
            type="number"
            min="0.01"
            step="0.01"
            required /></label
        ><label
          >Fecha<input v-model="form.transaction_date" type="date" required
        /></label>
        <label class="check-label">
          <input v-model="form.recurring" type="checkbox" />
          Repetir automáticamente
        </label>
        <label v-if="form.recurring"
          >Frecuencia<select v-model="form.frequency">
            <option value="monthly">Mensual</option>
            <option value="quarterly">Trimestral</option>
            <option value="yearly">Anual</option>
          </select></label
        >
        <footer>
          <button class="button primary" :disabled="saving">
            {{ saving ? "Guardando…" : "Guardar movimiento" }}
          </button>
        </footer>
      </form>
    </div>
  </main>
</template>
