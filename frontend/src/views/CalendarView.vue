<script setup>
import { ref, computed, onMounted } from "vue";
import { CalendarDays, Check, Plus } from "@lucide/vue";
import { useRoute, useRouter } from "vue-router";
import api from "../api";
const route = useRoute(),
  router = useRouter(),
  events = ref([]),
  properties = ref([]),
  saving = ref(false),
  error = ref(""),
  show = computed(() => route.query.new === "1"),
  form = ref({ title: "", description: "", starts_at: "", property_id: "" }),
  groups = computed(() =>
    Object.entries(
      events.value.reduce((a, e) => {
        (a[e.date] ??= []).push(e);
        return a;
      }, {}),
    ),
  );
async function load() {
  const [e, p] = await Promise.all([
    api.get("/calendar"),
    api.get("/properties"),
  ]);
  events.value = e.data.events;
  properties.value = p.data.data;
}
async function save() {
  saving.value = true;
  error.value = "";
  try {
    await api.post("/reminders", {
      ...form.value,
      property_id: form.value.property_id
        ? Number(form.value.property_id)
        : null,
    });
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
  await api.put(`/reminders/${event.reminder_id}`, { completed: true });
  await load();
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
      <RouterLink class="button primary" to="?new=1"
        ><Plus :size="16" />Nuevo recordatorio</RouterLink
      >
    </header>
    <section v-if="groups.length" class="timeline">
      <div v-for="[date, list] in groups" :key="date" class="timeline-day">
        <time>{{ date }}</time>
        <div>
          <article v-for="e in list" :key="e.id">
            <span :class="e.type"></span>
            <div>
              <strong>{{ e.title }}</strong
              ><small>{{ e.property?.name || "Cartera general" }}</small>
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
          </article>
        </div>
      </div>
    </section>
    <section v-else class="empty">
      <CalendarDays :size="35" />
      <h2>No hay eventos próximos</h2>
      <p>Los cobros y finales de contrato aparecerán automáticamente.</p>
    </section>
    <div
      v-if="show"
      class="drawer-bg"
      @click.self="router.replace('/calendar')"
    >
      <form class="drawer" @submit.prevent="save">
        <p class="eyebrow">Recordatorio</p>
        <h2>Añade una fecha importante</h2>
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
  </main>
</template>
