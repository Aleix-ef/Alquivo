<script setup>
import { computed, onMounted, ref } from "vue";
import { Plus, Wrench } from "@lucide/vue";
import { useRoute, useRouter } from "vue-router";
import api from "../api";

const route = useRoute();
const router = useRouter();
const issues = ref([]);
const properties = ref([]);
const contacts = ref([]);
const saving = ref(false);
const error = ref("");
const editing = computed(() =>
  issues.value.find((item) => item.id === Number(route.query.edit)),
);
const show = computed(() => route.query.new === "1" || Boolean(editing.value));
const today = new Date().toISOString().slice(0, 10);
const blank = () => ({
  property_id: "",
  assigned_contact_id: "",
  title: "",
  description: "",
  priority: "medium",
  status: "open",
  reported_at: today,
  due_date: "",
  estimated_cost: "",
  actual_cost: "",
  create_expense: false,
  expense_status: "paid",
  expense_date: today,
});
const form = ref(blank());
const labels = {
  open: "Abierta",
  in_progress: "En curso",
  waiting: "En espera",
  resolved: "Resuelta",
  cancelled: "Cancelada",
};
const money = (value) =>
  new Intl.NumberFormat("es-ES", { style: "currency", currency: "EUR" }).format(
    value || 0,
  );

async function load() {
  const [i, p, c] = await Promise.all([
    api.get("/issues"),
    api.get("/properties"),
    api.get("/contacts"),
  ]);
  issues.value = i.data.data;
  properties.value = p.data.data;
  contacts.value = c.data.data;
}
function openNew() {
  form.value = blank();
  error.value = "";
  router.push({ query: { new: "1" } });
}
function edit(issue) {
  form.value = {
    property_id: issue.property_id,
    assigned_contact_id: issue.assigned_contact_id || "",
    title: issue.title,
    description: issue.description || "",
    priority: issue.priority,
    status: issue.status,
    reported_at: issue.reported_at.slice(0, 10),
    due_date: issue.due_date?.slice(0, 10) || "",
    estimated_cost: issue.estimated_cost || "",
    actual_cost: issue.actual_cost || "",
    create_expense: false,
    expense_status: "paid",
    expense_date: today,
  };
  error.value = "";
  router.push({ query: { edit: issue.id } });
}
async function save() {
  saving.value = true;
  error.value = "";
  const payload = {
    ...form.value,
    property_id: Number(form.value.property_id),
    assigned_contact_id: form.value.assigned_contact_id
      ? Number(form.value.assigned_contact_id)
      : null,
    due_date: form.value.due_date || null,
    estimated_cost:
      form.value.estimated_cost === ""
        ? null
        : Number(form.value.estimated_cost),
    actual_cost:
      form.value.actual_cost === "" ? null : Number(form.value.actual_cost),
  };
  try {
    if (editing.value) await api.put(`/issues/${editing.value.id}`, payload);
    else await api.post("/issues", payload);
    router.replace("/issues");
    await load();
  } catch (e) {
    error.value =
      e.response?.data?.message || "No se pudo guardar la incidencia.";
  } finally {
    saving.value = false;
  }
}
onMounted(load);
</script>

<template>
  <main class="page">
    <header class="heading">
      <div>
        <p class="eyebrow">Atención</p>
        <h1>Incidencias</h1>
        <p>Reparaciones, responsables y costes bajo control.</p>
      </div>
      <button class="button primary" type="button" @click="openNew">
        <Plus :size="16" />Nueva incidencia
      </button>
    </header>
    <section v-if="issues.length" class="record-list">
      <article v-for="issue in issues" :key="issue.id" class="record">
        <span class="record-icon"><Wrench :size="19" /></span>
        <div>
          <strong>{{ issue.title }}</strong
          ><small
            >{{ issue.property?.name }} ·
            {{ issue.assigned_contact?.name || "Sin asignar" }}</small
          >
        </div>
        <div>
          <strong>{{
            issue.actual_cost
              ? money(issue.actual_cost)
              : issue.estimated_cost
                ? `Prev. ${money(issue.estimated_cost)}`
                : "Sin coste"
          }}</strong
          ><small>{{ labels[issue.status] }}</small>
        </div>
        <button class="button secondary" type="button" @click="edit(issue)">
          Gestionar
        </button>
      </article>
    </section>
    <section v-else class="empty">
      <Wrench :size="35" />
      <h2>No hay incidencias</h2>
      <p>Añade cualquier reparación o asunto que necesite seguimiento.</p>
      <button class="button primary" @click="openNew">Crear incidencia</button>
    </section>

    <div v-if="show" class="drawer-bg" @click.self="router.replace('/issues')">
      <form class="drawer" @submit.prevent="save">
        <p class="eyebrow">
          {{ editing ? "Seguimiento" : "Nueva incidencia" }}
        </p>
        <h2>{{ editing ? editing.title : "¿Qué ha ocurrido?" }}</h2>
        <p v-if="error" class="error">{{ error }}</p>
        <label
          >Propiedad<select
            v-model="form.property_id"
            required
            :disabled="Boolean(editing)"
          >
            <option value="" disabled>Selecciona</option>
            <option
              v-for="property in properties"
              :key="property.id"
              :value="property.id"
            >
              {{ property.name }}
            </option>
          </select></label
        >
        <label
          >Responsable<select v-model="form.assigned_contact_id">
            <option value="">Sin asignar</option>
            <option
              v-for="contact in contacts"
              :key="contact.id"
              :value="contact.id"
            >
              {{ contact.name }}
            </option>
          </select></label
        >
        <label>Título<input v-model="form.title" required /></label
        ><label
          >Descripción<textarea v-model="form.description" rows="4"></textarea>
        </label>
        <label
          >Prioridad<select v-model="form.priority">
            <option value="low">Baja</option>
            <option value="medium">Media</option>
            <option value="high">Alta</option>
          </select></label
        >
        <label v-if="editing"
          >Estado<select v-model="form.status">
            <option value="open">Abierta</option>
            <option value="in_progress">En curso</option>
            <option value="waiting">En espera</option>
            <option value="resolved">Resuelta</option>
            <option value="cancelled">Cancelada</option>
          </select></label
        >
        <label>Fecha límite<input v-model="form.due_date" type="date" /></label
        ><label
          >Coste previsto<input
            v-model="form.estimated_cost"
            type="number"
            min="0"
            step="0.01" /></label
        ><label v-if="editing"
          >Coste real<input
            v-model="form.actual_cost"
            type="number"
            min="0"
            step="0.01"
        /></label>
        <template
          v-if="
            editing &&
            form.status === 'resolved' &&
            !editing.expense_transaction
          "
          ><label class="check-label"
            ><input v-model="form.create_expense" type="checkbox" />Registrar
            coste real en Finanzas</label
          ><template v-if="form.create_expense"
            ><label
              >Estado del gasto<select v-model="form.expense_status">
                <option value="paid">Pagado</option>
                <option value="pending">Pendiente</option>
              </select></label
            ><label
              >Fecha del gasto<input
                v-model="form.expense_date"
                type="date"
                required /></label></template
        ></template>
        <p v-if="editing?.expense_transaction" class="success">
          Gasto registrado: {{ money(editing.expense_transaction.amount) }}
        </p>
        <footer>
          <button class="button primary" :disabled="saving">
            {{ saving ? "Guardando…" : "Guardar seguimiento" }}
          </button>
        </footer>
      </form>
    </div>
  </main>
</template>
