<script setup>
import { computed, onBeforeUnmount, ref, watch } from "vue";
import {
  CalendarDays,
  Check,
  ChevronLeft,
  ChevronRight,
  Pencil,
  Plus,
  Trash2,
  X,
} from "@lucide/vue";
import { useRoute, useRouter } from "vue-router";
import api from "../api";
import { fetchAllPages } from "../pagination";
import { formatMoney } from "../money";
import {
  calendarDate,
  parseCalendarDate,
  calendarMonthDays,
  shiftCalendarMonth,
  calendarRange,
  calendarEventDate,
  calendarEventTitle,
  reminderLocalInput,
} from "../calendar";
import ConfirmDialog from "../components/ConfirmDialog.vue";
import { useConfirmDialog } from "../composables/useConfirmDialog";
import { useRecordAnchor } from "../composables/useRecordAnchor";
import { useDialog } from "../composables/useDialog";

const route = useRoute(),
  router = useRouter();
const today = calendarDate(new Date());
const requestedDay = parseCalendarDate(route.query.date)
  ? route.query.date
  : today;
const selectedDate = ref(requestedDay),
  month = ref(requestedDay.slice(0, 7));
const events = ref([]),
  properties = ref([]),
  loading = ref(true),
  loadError = ref("");
const saving = ref(false),
  error = ref(""),
  editingReminder = ref(null),
  drawer = ref(null);
const show = computed(
  () => route.query.new === "1" || Boolean(editingReminder.value),
);
const blankForm = (date = selectedDate.value) => ({
  title: "",
  description: "",
  starts_at: `${date}T09:00`,
  property_id: "",
});
const form = ref(blankForm());
const confirmation = useConfirmDialog();
const weekdays = ["Lun", "Mar", "Mié", "Jue", "Vie", "Sáb", "Dom"];
const types = {
  rent: "Alquiler",
  lease_end: "Fin de contrato",
  document_expiry: "Documento",
  reminder: "Recordatorio",
};
const days = computed(() => calendarMonthDays(month.value, today));
const weeks = computed(() =>
  Array.from({ length: 6 }, (_, index) =>
    days.value.slice(index * 7, index * 7 + 7),
  ),
);
const monthLabel = computed(() =>
  new Intl.DateTimeFormat("es-ES", { month: "long", year: "numeric" }).format(
    parseCalendarDate(`${month.value}-01`),
  ),
);
const longDate = (date) =>
  new Intl.DateTimeFormat("es-ES", {
    weekday: "long",
    day: "numeric",
    month: "long",
    year: "numeric",
  }).format(parseCalendarDate(date));
const byDate = computed(() => {
  const result = new Map();
  for (const event of events.value) {
    const date = calendarEventDate(event);
    if (!date) continue;
    if (!result.has(date)) result.set(date, []);
    result.get(date).push(event);
  }
  for (const list of result.values())
    list.sort(
      (a, b) =>
        String(a.starts_at || "").localeCompare(String(b.starts_at || "")) ||
        a.title.localeCompare(b.title),
    );
  return result;
});
const dayEvents = computed(() => byDate.value.get(selectedDate.value) || []);
const cellEvents = (date) => byDate.value.get(date) || [];
const dayLabel = (date) =>
  `${longDate(date)} · ${loading.value ? "Cargando eventos" : loadError.value ? "Eventos no disponibles" : `${cellEvents(date).length} eventos`}`;
const eventDetail = (event) => {
  const property = event.property?.name || "Cartera general";
  if (event.type !== "reminder" || !event.starts_at) return property;
  const time = new Intl.DateTimeFormat("es-ES", {
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(event.starts_at));
  return `${property} · ${time}`;
};
const remainingQuery = () => {
  const query = { ...route.query };
  delete query.new;
  return query;
};
let controller;
async function load() {
  controller?.abort();
  const current = new AbortController();
  controller = current;
  loading.value = true;
  loadError.value = "";
  try {
    const [e, p] = await Promise.all([
      api.get("/calendar", {
        params: calendarRange(month.value),
        signal: current.signal,
      }),
      fetchAllPages(api, "/properties", { signal: current.signal }),
    ]);
    if (current.signal.aborted) return;
    events.value = e.data.events;
    properties.value = p;
    selectLinkedReminder();
  } catch (exception) {
    if (!current.signal.aborted)
      loadError.value = "No hemos podido cargar tu calendario.";
  } finally {
    if (controller === current) loading.value = false;
  }
}
function selectLinkedReminder() {
  if (!/^#reminder-[1-9][0-9]*$/.test(route.hash)) return;
  const event = events.value.find((item) => `#${item.id}` === route.hash);
  if (event)
    selectedDate.value = calendarEventDate(event) || selectedDate.value;
}
function selectDay(date) {
  selectedDate.value = date;
  if (date.slice(0, 7) !== month.value) month.value = date.slice(0, 7);
  if (route.hash || route.query.date)
    router.replace({ query: { ...remainingQuery(), date }, hash: "" });
}
function changeMonth(delta) {
  const next = shiftCalendarMonth(month.value, delta);
  selectDay(next === today.slice(0, 7) ? today : `${next}-01`);
}
function goToday() {
  selectDay(calendarDate(new Date()));
}
function openNew() {
  error.value = "";
  editingReminder.value = null;
  form.value = blankForm();
  router.push({ query: { ...route.query, new: "1" }, hash: "" });
}
function editReminder(event) {
  error.value = "";
  editingReminder.value = event;
  form.value = {
    title: event.title,
    description: event.description || "",
    starts_at: reminderLocalInput(event.starts_at) || `${event.date}T09:00`,
    property_id: event.property_id || "",
  };
}
function closeForm() {
  if (saving.value) return;
  editingReminder.value = null;
  router.replace({ query: remainingQuery(), hash: route.hash });
}
async function save() {
  if (saving.value) return;
  saving.value = true;
  error.value = "";
  try {
    const date = form.value.starts_at.slice(0, 10),
      previousMonth = month.value;
    const payload = {
      ...form.value,
      starts_at: new Date(form.value.starts_at).toISOString(),
      property_id: form.value.property_id
        ? Number(form.value.property_id)
        : null,
    };
    if (editingReminder.value)
      await api.put(`/reminders/${editingReminder.value.reminder_id}`, payload);
    else await api.post("/reminders", payload);
    editingReminder.value = null;
    await router.replace({ query: { ...remainingQuery(), date }, hash: "" });
    if (month.value === previousMonth) await load();
  } catch (exception) {
    error.value =
      exception.response?.data?.message ||
      "No se pudo guardar el recordatorio.";
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
watch(
  () => route.query.date,
  (date) => {
    if (!parseCalendarDate(date)) return;
    selectedDate.value = date;
    month.value = date.slice(0, 7);
  },
);
watch(() => route.hash, selectLinkedReminder);
watch(month, load, { immediate: true });
onBeforeUnmount(() => controller?.abort());
useDialog(show, drawer, closeForm);
useRecordAnchor(loading, "reminder");
</script>

<template>
  <main class="page calendar-page">
    <header class="heading">
      <div>
        <p class="eyebrow">Fechas importantes</p>
        <h1>Calendario</h1>
        <p>Tus alquileres, vencimientos y recordatorios, día a día.</p>
      </div>
      <button class="button primary" type="button" @click="openNew">
        <Plus :size="18" />Nuevo recordatorio
      </button>
    </header>
    <section class="calendar-board" aria-label="Calendario y detalle del día">
      <div class="calendar-month-panel">
        <header class="calendar-toolbar">
          <h2 id="calendar-month-title" aria-live="polite">{{ monthLabel }}</h2>
          <div class="calendar-navigation">
            <button class="button secondary" type="button" @click="goToday">
              Hoy
            </button>
            <button
              class="calendar-arrow"
              type="button"
              aria-label="Mes anterior"
              :disabled="month === '1000-01'"
              @click="changeMonth(-1)"
            >
              <ChevronLeft :size="20" />
            </button>
            <button
              class="calendar-arrow"
              type="button"
              aria-label="Mes siguiente"
              :disabled="month === '9999-12'"
              @click="changeMonth(1)"
            >
              <ChevronRight :size="20" />
            </button>
          </div>
        </header>
        <p v-if="loading" class="calendar-status" role="status">
          Cargando eventos…
        </p>
        <div v-if="loadError" class="calendar-error" role="alert">
          <p>{{ loadError }}</p>
          <button class="button secondary" type="button" @click="load">
            Reintentar
          </button>
        </div>
        <table
          class="calendar-month"
          aria-labelledby="calendar-month-title"
          :aria-busy="loading"
        >
          <thead>
            <tr>
              <th v-for="day in weekdays" :key="day" scope="col">{{ day }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(week, index) in weeks" :key="index">
              <td v-for="day in week" :key="day.date">
                <button
                  class="calendar-day"
                  :class="{
                    'is-outside': !day.inMonth,
                    'is-selected': day.date === selectedDate,
                    'is-today': day.today,
                  }"
                  type="button"
                  :aria-pressed="day.date === selectedDate"
                  :aria-current="day.today ? 'date' : undefined"
                  :aria-label="dayLabel(day.date)"
                  @click="selectDay(day.date)"
                >
                  <time :datetime="day.date" class="calendar-day-number">{{
                    day.number
                  }}</time>
                  <span
                    v-if="!loading && !loadError"
                    class="calendar-cell-events"
                    aria-hidden="true"
                  >
                    <span
                      v-for="event in cellEvents(day.date).slice(0, 2)"
                      :key="event.id"
                      class="calendar-cell-event"
                      :class="event.type"
                      >{{ calendarEventTitle(event) }}</span
                    >
                    <span
                      v-if="cellEvents(day.date).length > 2"
                      class="calendar-more"
                      >+{{ cellEvents(day.date).length - 2 }} más</span
                    >
                  </span>
                  <span
                    v-if="!loading && !loadError && cellEvents(day.date).length"
                    class="calendar-mobile-count"
                    aria-hidden="true"
                    ><i
                      v-for="event in cellEvents(day.date).slice(0, 3)"
                      :key="event.id"
                      :class="event.type"
                    ></i
                    ><span>{{ cellEvents(day.date).length }}</span></span
                  >
                </button>
              </td>
            </tr>
          </tbody>
        </table>
        <ul class="calendar-legend" aria-label="Tipos de evento">
          <li v-for="(label, type) in types" :key="type">
            <i :class="type"></i>{{ label }}
          </li>
        </ul>
      </div>
      <section
        class="calendar-day-panel"
        aria-labelledby="calendar-selected-day"
      >
        <p class="eyebrow">Día seleccionado</p>
        <h2 id="calendar-selected-day" aria-live="polite">
          {{ longDate(selectedDate) }}
        </h2>
        <p v-if="loading" role="status" class="muted">
          Cargando los eventos de este día…
        </p>
        <p v-else-if="loadError" class="muted">
          Vuelve a cargar para ver los eventos.
        </p>
        <div v-else-if="dayEvents.length" class="calendar-event-list">
          <article
            v-for="event in dayEvents"
            :id="event.id"
            :key="event.id"
            tabindex="-1"
            class="calendar-event"
          >
            <span class="calendar-event-kind"
              ><i :class="event.type"></i
              >{{ types[event.type] || "Evento" }}</span
            >
            <h3>{{ calendarEventTitle(event) }}</h3>
            <p>{{ eventDetail(event) }}</p>
            <strong v-if="event.amount != null && Number(event.amount) > 0">{{
              formatMoney(event.amount)
            }}</strong>
            <p v-if="event.description" class="calendar-event-description">
              {{ event.description }}
            </p>
            <div
              v-if="event.type === 'reminder'"
              class="calendar-event-actions"
            >
              <button
                class="calendar-action"
                type="button"
                :disabled="loading"
                :aria-label="`Completar ${event.title}`"
                @click="complete(event)"
              >
                <Check :size="17" />Completar
              </button>
              <button
                class="calendar-action"
                type="button"
                :disabled="loading"
                :aria-label="`Editar ${event.title}`"
                @click="editReminder(event)"
              >
                <Pencil :size="17" />Editar
              </button>
              <button
                class="calendar-action calendar-delete"
                type="button"
                :disabled="loading"
                :aria-label="`Eliminar ${event.title}`"
                @click="removeReminder(event)"
              >
                <Trash2 :size="17" />
              </button>
            </div>
          </article>
        </div>
        <div v-else class="calendar-day-empty">
          <CalendarDays :size="30" />
          <p>No hay eventos para este día.</p>
        </div>
        <button
          class="button secondary calendar-add-day"
          type="button"
          @click="openNew"
        >
          <Plus :size="17" />Añadir recordatorio
        </button>
      </section>
    </section>
    <div v-if="show" class="drawer-bg" @click.self="closeForm">
      <form
        ref="drawer"
        class="drawer"
        role="dialog"
        aria-modal="true"
        aria-labelledby="calendar-form-title"
        tabindex="-1"
        @submit.prevent="save"
      >
        <header>
          <p class="eyebrow">Recordatorio</p>
          <button
            class="calendar-arrow"
            type="button"
            aria-label="Cerrar recordatorio"
            :disabled="saving"
            @click="closeForm"
          >
            <X :size="20" />
          </button>
        </header>
        <h2 id="calendar-form-title">
          {{ editingReminder ? "Editar recordatorio" : "Nuevo recordatorio" }}
        </h2>
        <p v-if="error" class="error" role="alert">{{ error }}</p>
        <label
          >Título<input v-model="form.title" maxlength="160" required
        /></label>
        <label
          >Fecha y hora<input
            v-model="form.starts_at"
            type="datetime-local"
            required
        /></label>
        <label
          >Propiedad<select v-model="form.property_id">
            <option value="">General</option>
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
          >Notas<textarea
            v-model="form.description"
            rows="4"
            maxlength="10000"
          ></textarea>
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

<style scoped>
.calendar-board {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 310px;
  gap: 24px;
  align-items: start;
}
.calendar-month-panel,
.calendar-day-panel {
  min-width: 0;
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: 16px;
  overflow: hidden;
}
.calendar-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 20px;
  flex-wrap: wrap;
}
.calendar-toolbar h2 {
  margin: 0;
  font-size: 1.35rem;
  text-transform: capitalize;
}
.calendar-navigation {
  display: flex;
  align-items: center;
  gap: 6px;
}
.calendar-arrow {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 44px;
  min-height: 44px;
  border: 1px solid var(--line);
  background: transparent;
  border-radius: 10px;
  color: var(--ink);
  cursor: pointer;
}
.calendar-month {
  table-layout: fixed;
  width: 100%;
  border-collapse: collapse;
}
.calendar-month th {
  height: 42px;
  color: var(--muted);
  font-size: 0.9rem;
  font-weight: 500;
  text-align: center;
}
.calendar-month td {
  padding: 0;
  border-top: 1px solid var(--line);
  border-right: 1px solid var(--line);
  vertical-align: top;
}
.calendar-month td:last-child {
  border-right: 0;
}
.calendar-day {
  display: flex;
  flex-direction: column;
  gap: 8px;
  width: 100%;
  min-height: 118px;
  padding: 8px 5px;
  border: 0;
  border-radius: 0;
  background: transparent;
  color: var(--ink);
  cursor: pointer;
  text-align: left;
}
.calendar-day:hover {
  background: var(--surface-2, rgba(255, 255, 255, 0.04));
}
.calendar-day.is-outside .calendar-day-number {
  color: var(--muted);
  opacity: 0.65;
}
.calendar-day.is-selected {
  box-shadow: inset 0 0 0 2px var(--green);
  background: color-mix(in srgb, var(--green) 8%, transparent);
}
.calendar-day-number {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 30px;
  height: 30px;
  align-self: flex-start;
  font-size: 1rem;
  font-weight: 600;
  border-radius: 50%;
}
.calendar-day.is-today .calendar-day-number {
  background: var(--green);
  color: var(--canvas);
}
.calendar-cell-events {
  display: grid;
  gap: 4px;
  width: 100%;
}
.calendar-cell-event {
  border-left: 3px solid;
  padding: 2px 4px;
  border-radius: 3px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-size: 0.85rem;
  background: rgba(255, 255, 255, 0.03);
}
.rent {
  color: var(--green);
  border-color: var(--green);
}
.lease_end {
  color: #efbd72;
  border-color: #efbd72;
}
.document_expiry {
  color: #b6a2ed;
  border-color: #b6a2ed;
}
.reminder {
  color: #80c2e9;
  border-color: #80c2e9;
}
.calendar-more {
  color: var(--muted);
  font-size: 0.85rem;
  padding-left: 4px;
}
.calendar-mobile-count {
  display: none;
}
.calendar-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 12px 20px;
  margin: 0;
  padding: 18px 20px;
  border-top: 1px solid var(--line);
  list-style: none;
  font-size: 0.9rem;
  color: var(--muted);
}
.calendar-legend li,
.calendar-event-kind {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.calendar-legend i,
.calendar-event-kind i,
.calendar-mobile-count i {
  width: 7px;
  height: 7px;
  flex: none;
  border-radius: 50%;
  background: currentColor;
}
.calendar-day-panel {
  padding: 24px;
}
.calendar-day-panel h2 {
  font-size: 1.15rem;
  line-height: 1.5;
  margin: 8px 0 20px;
  text-transform: capitalize;
}
.calendar-day-empty {
  display: grid;
  justify-items: start;
  gap: 12px;
  color: var(--muted);
  padding: 20px 0;
}
.calendar-day-empty p {
  margin: 0;
}
.calendar-event-list {
  display: grid;
  gap: 16px;
}
.calendar-event {
  padding: 16px 0;
  border-top: 1px solid var(--line);
  overflow-wrap: anywhere;
}
.calendar-event-kind {
  color: var(--muted);
  font-size: 0.9rem;
}
.calendar-event h3 {
  margin: 10px 0 6px;
  font-size: 1rem;
  line-height: 1.5;
}
.calendar-event p {
  margin: 0 0 10px;
  color: var(--muted);
  font-size: 0.95rem;
  line-height: 1.6;
}
.calendar-event .calendar-event-description {
  white-space: pre-wrap;
  margin-top: 10px;
}
.calendar-event-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: 12px;
}
.calendar-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-height: 44px;
  min-width: 44px;
  padding: 8px;
  background: transparent;
  border: 1px solid var(--line);
  border-radius: 8px;
  color: var(--ink);
  cursor: pointer;
  font: inherit;
  font-size: 0.9rem;
}
.calendar-delete {
  color: #ef9696;
}
.calendar-add-day {
  width: 100%;
  margin-top: 20px;
}
.calendar-status,
.calendar-error {
  margin: 0;
  padding: 12px 20px;
  border-top: 1px solid var(--line);
}
.calendar-status {
  color: var(--muted);
}
.calendar-error p {
  color: #ef9696;
}
.calendar-day:focus-visible,
.calendar-arrow:focus-visible,
.calendar-action:focus-visible {
  outline: 2px solid var(--green);
  outline-offset: -2px;
}
.drawer header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
@media (max-width: 1180px) {
  .calendar-board {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 650px) {
  .calendar-toolbar {
    padding: 16px 12px;
  }
  .calendar-toolbar h2 {
    font-size: 1.1rem;
  }
  .calendar-day {
    min-height: 76px;
    padding: 6px 2px;
    gap: 8px;
    align-items: center;
  }
  .calendar-day-number {
    align-self: center;
  }
  .calendar-cell-events {
    display: none;
  }
  .calendar-mobile-count {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 3px;
    font-size: 0.85rem;
    color: var(--muted);
  }
  .calendar-mobile-count i {
    width: 4px;
    height: 4px;
  }
  .calendar-mobile-count span {
    flex-basis: 100%;
    text-align: center;
  }
  .calendar-legend {
    padding: 16px 12px;
    gap: 10px 16px;
  }
  .calendar-day-panel {
    padding: 20px;
  }
}
</style>
