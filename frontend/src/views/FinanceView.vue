<script setup>
import { ref, computed, onMounted } from "vue";
import { ArrowDownLeft, ArrowUpRight, Pencil, Plus, Trash2 } from "@lucide/vue";
import { useRoute, useRouter } from "vue-router";
import api from "../api";
import { fetchAllPages } from "../pagination";
import ConfirmDialog from "../components/ConfirmDialog.vue";
import { useConfirmDialog } from "../composables/useConfirmDialog";
const route = useRoute(),
  router = useRouter(),
  tx = ref([]),
  leases = ref([]),
  properties = ref([]),
  recurringRules = ref([]),
  loading = ref(true),
  loadError = ref(""),
  actionError = ref(""),
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
const editingTransaction = ref(null);
const paymentTarget = ref(null);
const editForm = ref({});
const paymentForm = ref({
  amount: "",
  transaction_date: "",
  payment_method: "transfer",
  notes: "",
});
const confirmation = useConfirmDialog();
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
  loading.value = true;
  loadError.value = "";
  try {
    const [t, l, p, r] = await Promise.all([
      fetchAllPages(api, "/transactions"),
      fetchAllPages(api, "/leases"),
      fetchAllPages(api, "/properties"),
      fetchAllPages(api, "/recurring-rules"),
    ]);
    tx.value = t;
    leases.value = l;
    properties.value = p;
    recurringRules.value = r;
  } catch {
    loadError.value = "No hemos podido cargar tus movimientos.";
  } finally {
    loading.value = false;
  }
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
function openPayment(charge) {
  const remaining = Number(charge.amount) - Number(charge.paid_amount);
  paymentTarget.value = { charge, transaction: null, maximum: remaining };
  paymentForm.value = {
    amount: remaining,
    transaction_date: new Date().toISOString().slice(0, 10),
    payment_method: "transfer",
    notes: "",
  };
  actionError.value = "";
}
function editPayment(transaction) {
  const charge = leases.value
    .flatMap((lease) => lease.charges || [])
    .find((item) => item.id === transaction.rent_charge_id);
  const remaining = charge
    ? Number(charge.amount) - Number(charge.paid_amount)
    : 0;
  paymentTarget.value = {
    charge,
    transaction,
    maximum: Number(transaction.amount) + remaining,
  };
  paymentForm.value = {
    amount: Number(transaction.amount),
    transaction_date: transaction.transaction_date?.slice(0, 10),
    payment_method: transaction.payment_method || "transfer",
    notes: transaction.notes || "",
  };
  actionError.value = "";
}
async function savePayment() {
  if (!paymentTarget.value || saving.value) return;
  saving.value = true;
  actionError.value = "";
  try {
    const payload = {
      ...paymentForm.value,
      amount: Number(paymentForm.value.amount),
    };
    if (paymentTarget.value.transaction) {
      await api.put(
        `/rent-payments/${paymentTarget.value.transaction.id}`,
        payload,
      );
    } else {
      await api.post(
        `/rent-charges/${paymentTarget.value.charge.id}/payments`,
        payload,
      );
    }
    paymentTarget.value = null;
    await load();
  } catch (exception) {
    actionError.value =
      exception.response?.data?.message || "No se pudo guardar el cobro.";
  } finally {
    saving.value = false;
  }
}
function editTransaction(transaction) {
  editingTransaction.value = transaction;
  editForm.value = {
    property_id: transaction.property_id || "",
    direction: transaction.direction,
    category: transaction.category,
    description: transaction.description,
    amount: Number(transaction.amount),
    transaction_date: transaction.transaction_date?.slice(0, 10),
    due_date: transaction.due_date?.slice(0, 10) || "",
    status: transaction.status,
    payment_method: transaction.payment_method || "",
    notes: transaction.notes || "",
  };
  actionError.value = "";
}
async function saveTransactionEdit() {
  if (!editingTransaction.value || saving.value) return;
  saving.value = true;
  actionError.value = "";
  try {
    await api.put(`/transactions/${editingTransaction.value.id}`, {
      ...editForm.value,
      property_id: editForm.value.property_id
        ? Number(editForm.value.property_id)
        : null,
      amount: Number(editForm.value.amount),
      due_date: editForm.value.due_date || null,
      payment_method: editForm.value.payment_method || null,
      notes: editForm.value.notes || null,
    });
    editingTransaction.value = null;
    await load();
  } catch (exception) {
    actionError.value =
      exception.response?.data?.message || "No se pudo corregir el movimiento.";
  } finally {
    saving.value = false;
  }
}
async function removePayment(transaction) {
  if (
    !(await confirmation.ask({
      title: "¿Eliminar este cobro registrado?",
      description:
        "La mensualidad volverá a calcularse y podrá quedar pendiente de nuevo.",
      confirmLabel: "Eliminar cobro",
      danger: true,
    }))
  )
    return;
  try {
    await api.delete(`/rent-payments/${transaction.id}`);
    await load();
  } catch (exception) {
    actionError.value =
      exception.response?.data?.message || "No se pudo eliminar el cobro.";
  }
}
async function markPaid(transaction) {
  try {
    await api.put(`/transactions/${transaction.id}`, {
      status: "paid",
      transaction_date: new Date().toISOString().slice(0, 10),
    });
    await load();
  } catch (exception) {
    actionError.value =
      exception.response?.data?.message ||
      "No se pudo confirmar el movimiento.";
  }
}
async function cancelTransaction(transaction) {
  if (
    !(await confirmation.ask({
      title: `¿Cancelar “${transaction.description}”?`,
      description:
        "Dejará de contar en tus ingresos o gastos confirmados, pero conservarás el historial.",
      confirmLabel: "Cancelar movimiento",
    }))
  )
    return;
  try {
    await api.put(`/transactions/${transaction.id}`, { status: "cancelled" });
    await load();
  } catch (exception) {
    actionError.value =
      exception.response?.data?.message || "No se pudo cancelar el movimiento.";
  }
}
async function pauseRule(rule) {
  try {
    await api.put(`/recurring-rules/${rule.id}`, { active: !rule.active });
    await load();
  } catch (exception) {
    actionError.value =
      exception.response?.data?.message || "No se pudo actualizar la regla.";
  }
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
    <section v-if="loading" class="empty" role="status">
      Cargando tus finanzas…
    </section>
    <section v-else-if="loadError" class="empty" role="alert">
      <p>{{ loadError }}</p>
      <button class="button secondary" @click="load">Volver a intentar</button>
    </section>
    <p v-if="actionError" class="error" role="alert">{{ actionError }}</p>
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
            @click="openPayment(charge)"
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
          <span class="movement-actions">
            <strong :class="t.direction"
              >{{ t.direction === "expense" ? "-" : "+"
              }}{{ money(t.amount) }}</strong
            >
            <button
              v-if="t.rent_charge_id"
              type="button"
              title="Corregir cobro"
              @click="editPayment(t)"
            >
              <Pencil :size="16" />
            </button>
            <button
              v-else-if="!t.recurring_rule_id"
              type="button"
              title="Editar movimiento"
              @click="editTransaction(t)"
            >
              <Pencil :size="16" />
            </button>
            <button
              v-if="t.rent_charge_id"
              type="button"
              title="Eliminar cobro"
              @click="removePayment(t)"
            >
              <Trash2 :size="16" />
            </button>
          </span>
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
    <div
      v-if="paymentTarget"
      class="drawer-bg"
      @click.self="!saving && (paymentTarget = null)"
    >
      <form class="drawer" @submit.prevent="savePayment">
        <p class="eyebrow">Alquiler</p>
        <h2>
          {{
            paymentTarget.transaction ? "Corrige el cobro" : "Registra el cobro"
          }}
        </h2>
        <p v-if="actionError" class="error" role="alert">{{ actionError }}</p>
        <label
          >Importe<input
            v-model="paymentForm.amount"
            type="number"
            min="0.01"
            :max="paymentTarget.maximum"
            step="0.01"
            required
        /></label>
        <small>Máximo disponible: {{ money(paymentTarget.maximum) }}</small>
        <label
          >Fecha<input
            v-model="paymentForm.transaction_date"
            type="date"
            required
        /></label>
        <label
          >Método<select v-model="paymentForm.payment_method">
            <option value="transfer">Transferencia</option>
            <option value="cash">Efectivo</option>
            <option value="card">Tarjeta</option>
            <option value="direct_debit">Domiciliación</option>
            <option value="other">Otro</option>
          </select></label
        >
        <label
          >Notas<textarea v-model="paymentForm.notes" rows="3"></textarea>
        </label>
        <footer>
          <button
            class="button secondary"
            type="button"
            :disabled="saving"
            @click="paymentTarget = null"
          >
            Cancelar
          </button>
          <button class="button primary" :disabled="saving">
            {{ saving ? "Guardando…" : "Guardar cobro" }}
          </button>
        </footer>
      </form>
    </div>
    <div
      v-if="editingTransaction"
      class="drawer-bg"
      @click.self="!saving && (editingTransaction = null)"
    >
      <form class="drawer" @submit.prevent="saveTransactionEdit">
        <p class="eyebrow">Corrección</p>
        <h2>Edita el movimiento</h2>
        <p v-if="actionError" class="error" role="alert">{{ actionError }}</p>
        <label
          >Tipo<select v-model="editForm.direction">
            <option value="expense">Gasto</option>
            <option value="income">Ingreso</option>
          </select></label
        >
        <label
          >Concepto<input
            v-model="editForm.description"
            required
            maxlength="180"
        /></label>
        <label
          >Categoría<select v-model="editForm.category">
            <option value="rent">Alquiler</option>
            <option value="maintenance">Mantenimiento</option>
            <option value="tax">Impuestos</option>
            <option value="insurance">Seguro</option>
            <option value="other">Otro</option>
          </select></label
        >
        <label
          >Propiedad<select v-model="editForm.property_id">
            <option value="">General</option>
            <option v-for="p in properties" :key="p.id" :value="p.id">
              {{ p.name }}
            </option>
          </select></label
        >
        <label
          >Importe<input
            v-model="editForm.amount"
            type="number"
            min="0.01"
            step="0.01"
            required
        /></label>
        <label
          >Fecha<input v-model="editForm.transaction_date" type="date" required
        /></label>
        <label
          >Estado<select v-model="editForm.status">
            <option value="paid">Confirmado</option>
            <option value="pending">Pendiente</option>
            <option value="cancelled">Cancelado</option>
          </select></label
        >
        <label
          >Vencimiento<input v-model="editForm.due_date" type="date"
        /></label>
        <label
          >Método de pago<input
            v-model="editForm.payment_method"
            maxlength="40"
        /></label>
        <label
          >Notas<textarea v-model="editForm.notes" rows="3"></textarea>
        </label>
        <footer>
          <button
            class="button secondary"
            type="button"
            :disabled="saving"
            @click="editingTransaction = null"
          >
            Cancelar
          </button>
          <button class="button primary" :disabled="saving">
            {{ saving ? "Guardando…" : "Guardar cambios" }}
          </button>
        </footer>
      </form>
    </div>
    <ConfirmDialog
      :dialog="confirmation.dialog.value"
      @confirm="confirmation.confirm"
      @cancel="confirmation.cancel"
    />
  </main>
</template>
