<script setup>
import { ref, computed, onMounted } from "vue";
import { CalendarDays, Check, Pencil, Plus, Trash2 } from "@lucide/vue";
import { useRoute, useRouter } from "vue-router";
import api from "../api";
import { fetchAllPages } from "../pagination";
import ConfirmDialog from "../components/ConfirmDialog.vue";
import { useConfirmDialog } from "../composables/useConfirmDialog";
const route = useRoute(),
  router = useRouter(),
  events = ref([]),
  properties = ref([]),
  loading = ref(true),
  loadError = ref(""),
  saving = ref(false),
  error = ref(""),
  editingReminder = ref(null),
  show = computed(
    () => route.query.new === "1" || Boolean(editingReminder.value),
  ),
  form = ref({ title: "", description: "", starts_at: "", property_id: "" }),
  groups = computed(() =>
    Object.entries(
      events.value.reduce((a, e) => {
        (a[e.date] ??= []).push(e);
        return a;
      }, {}),
    ),
  );
const confirmation = useConfirmDialog();
const eventDetail = (event) => {
  const property = event.property?.name || "Cartera general";
  if (event.type !== "reminder" || !event.starts_at) return property;
  const time = new Intl.DateTimeFormat("es-ES", {
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(event.starts_at));
  return `${property} · ${time}`;
};
async function load() {
  loading.value = true;
  loadError.value = "";
  try {
    const [e, p] = await Promise.all([
      api.get("/calendar"),
      fetchAllPages(api, "/properties"),
    ]);
    events.value = e.data.events;
    properties.value = p;
  } catch {
    loadError.value = "No hemos podido cargar tu calendario.";
  } finally {
    loading.value = false;
  }
}
function openNew() {
  editingReminder.value = null;
  form.value = { title: "", description: "", starts_at: "", property_id: "" };
  router.push({ query: { new: "1" } });
}
function editReminder(event) {
  editingReminder.value = event;
  form.value = {
    title: event.title,
    description: event.description || "",
    starts_at: event.starts_at?.slice(0, 16) || `${event.date}T09:00`,
    property_id: event.property_id || "",
  };
}
function closeForm() {
  if (saving.value) return;
  editingReminder.value = null;
  router.replace("/calendar");
}
async function save() {
  saving.value = true;
  error.value = "";
  try {
    const payload = {
      ...form.value,
      property_id: form.value.property_id
        ? Number(form.value.property_id)
        : null,
    };
    if (editingReminder.value)
      await api.put(`/reminders/${editingReminder.value.reminder_id}`, payload);
    else await api.post("/reminders", payload);
    editingReminder.value = null;
    router.replace("/calendar");
    await load();
  } catch (e) {
    error.value =
      e.response?.data?.message || "No se pudo guardar el recordatorio.";
  } finally {
    saving.value = false;
  }
}
async function complete(event) {
  try {
    await api.put(`/reminders/${event.reminder_id}`, { completed: true });
    await load();
  } catch (exception) {
    loadError.value =
      exception.response?.data?.message ||
      "No se pudo completar el recordatorio.";
  }
}
async function removeReminder(event) {
  if (
    !(await confirmation.ask({
      title: `¿Eliminar “${event.title}”?`,
      description:
        "El recordatorio desaparecerá definitivamente del calendario.",
      confirmLabel: "Eliminar recordatorio",
      danger: true,
    }))
  )
    return;
  try {
    await api.delete(`/reminders/${event.reminder_id}`);
    await load();
  } catch (exception) {
    loadError.value =
      exception.response?.data?.message ||
      "No se pudo eliminar el recordatorio.";
  }
}
onMounted(load);
</script>
<template>
  <main class="page">
    <header class="heading">
      <div>
        <p class="eyebrow">Próximos pasos</p>
        <h1>Calendario</h1>
        <p>Cobros, vencimientos y recordatorios en una sola línea temporal.</p>
      </div>
      <button class="button primary" type="button" @click="openNew">
        <Plus :size="16" />Nuevo recordatorio
      </button>
    </header>
    <section v-if="loading" class="empty" role="status">
      Cargando calendario…
    </section>
    <section v-else-if="loadError" class="empty" role="alert">
      <p>{{ loadError }}</p>
      <button class="button secondary" @click="load">Volver a intentar</button>
    </section>
    <section v-else-if="groups.length" class="timeline">
      <div v-for="[date, list] in groups" :key="date" class="timeline-day">
        <time>{{ date }}</time>
        <div>
          <article v-for="e in list" :key="e.id">
            <span :class="e.type"></span>
            <div>
              <strong>{{ e.title }}</strong
              ><small>{{ eventDetail(e) }}</small>
            </div>
            <strong v-if="e.amount">{{ e.amount }} €</strong>
            <button
              v-if="e.type === 'reminder'"
              class="event-check"
              type="button"
              title="Completar"
              @click="complete(e)"
            >
              <Check :size="16" />
            </button>
            <button
              v-if="e.type === 'reminder'"
              class="event-check"
              type="button"
              title="Editar"
              @click="editReminder(e)"
            >
              <Pencil :size="16" />
            </button>
            <button
              v-if="e.type === 'reminder'"
              class="event-check"
              type="button"
              title="Eliminar"
              @click="removeReminder(e)"
            >
              <Trash2 :size="16" />
            </button>
          </article>
        </div>
      </div>
    </section>
    <section v-else class="empty">
      <CalendarDays :size="35" />
      <h2>No hay eventos próximos</h2>
      <p>Los cobros y finales de contrato aparecerán automáticamente.</p>
    </section>
    <div v-if="show" class="drawer-bg" @click.self="closeForm">
      <form class="drawer" @submit.prevent="save">
        <p class="eyebrow">Recordatorio</p>
        <h2>
          {{
            editingReminder
              ? "Edita la fecha importante"
              : "Añade una fecha importante"
          }}
        </h2>
        <p v-if="error" class="error">{{ error }}</p>
        <label>Título<input v-model="form.title" required /></label
        ><label
          >Fecha y hora<input
            v-model="form.starts_at"
            type="datetime-local"
            required /></label
        ><label
          >Propiedad<select v-model="form.property_id">
            <option value="">General</option>
            <option v-for="p in properties" :value="p.id" :key="p.id">
              {{ p.name }}
            </option>
          </select></label
        ><label
          >Notas<textarea v-model="form.description" rows="4"></textarea>
        </label>
        <footer>
          <button class="button primary" :disabled="saving">
            {{ saving ? "Guardando…" : "Guardar recordatorio" }}
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
