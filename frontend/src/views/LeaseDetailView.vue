<script setup>
import { computed, onMounted, onBeforeUnmount, watch, ref } from "vue";
import {
  ArrowLeft,
  CalendarDays,
  FileText,
  Pencil,
  RefreshCw,
  UsersRound,
} from "@lucide/vue";
import { useRoute, useRouter } from "vue-router";
import api from "../api";
import { fetchAllPages } from "../pagination";

const route = useRoute();
const router = useRouter();
const lease = ref(null);
const contacts = ref([]);
const saving = ref(false);
const error = ref("");
const loading = ref(true);
const loadError = ref("");
const action = computed(() => route.query.action);
const money = (value) =>
  new Intl.NumberFormat("es-ES", { style: "currency", currency: "EUR" }).format(
    value || 0,
  );
const statusLabel = {
  draft: "Borrador",
  active: "Activo",
  ended: "Finalizado",
  cancelled: "Cancelado",
};
const editForm = ref({});
const renewalForm = ref({});

let loadGeneration = 0;
async function load() {
  const generation = ++loadGeneration;
  loading.value = true;
  loadError.value = "";
  try {
    const [leaseResponse, contactsResponse] = await Promise.all([
      api.get(`/leases/${route.params.id}`),
      fetchAllPages(api, "/contacts"),
    ]);
    if (generation !== loadGeneration) return;
    lease.value = leaseResponse.data;
    contacts.value = contactsResponse;
  } catch (exception) {
    if (generation !== loadGeneration) return;
    loadError.value =
      exception.response?.status === 404
        ? "Este contrato no existe o no pertenece a tu cartera."
        : "No hemos podido cargar el contrato.";
  } finally {
    if (generation === loadGeneration) loading.value = false;
  }
}

function tomorrowAfter(date) {
  const next = date ? new Date(`${date.slice(0, 10)}T12:00:00`) : new Date();
  next.setDate(next.getDate() + 1);
  return next.toISOString().slice(0, 10);
}

function openEdit() {
  editForm.value = {
    contact_ids: lease.value.participants.map((contact) => contact.id),
    status: lease.value.status,
    start_date: lease.value.start_date.slice(0, 10),
    end_date: lease.value.end_date?.slice(0, 10) || "",
    monthly_rent: lease.value.monthly_rent,
    deposit_amount: lease.value.deposit_amount,
    payment_day: lease.value.payment_day,
    notes: lease.value.notes || "",
  };
  error.value = "";
  router.push({ query: { action: "edit" } });
}

function openRenew() {
  renewalForm.value = {
    start_date: tomorrowAfter(lease.value.end_date || new Date().toISOString()),
    end_date: "",
    monthly_rent: lease.value.monthly_rent,
    deposit_amount: lease.value.deposit_amount,
    payment_day: lease.value.payment_day,
    notes: "Renovación del contrato anterior",
  };
  error.value = "";
  router.push({ query: { action: "renew" } });
}

async function saveEdit() {
  saving.value = true;
  error.value = "";
  try {
    await api.put(`/leases/${lease.value.id}`, {
      ...editForm.value,
      end_date: editForm.value.end_date || null,
      monthly_rent: Number(editForm.value.monthly_rent),
      deposit_amount: Number(editForm.value.deposit_amount || 0),
      payment_day: Number(editForm.value.payment_day),
    });
    router.replace(`/leases/${lease.value.id}`);
    await load();
  } catch (e) {
    error.value =
      e.response?.data?.message || "No se pudo actualizar el contrato.";
  } finally {
    saving.value = false;
  }
}

async function saveRenewal() {
  saving.value = true;
  error.value = "";
  try {
    const { data } = await api.post(`/leases/${lease.value.id}/renew`, {
      ...renewalForm.value,
      end_date: renewalForm.value.end_date || null,
      monthly_rent: Number(renewalForm.value.monthly_rent),
      deposit_amount: Number(renewalForm.value.deposit_amount || 0),
      payment_day: Number(renewalForm.value.payment_day),
    });
    router.push(`/leases/${data.id}`);
  } catch (e) {
    error.value =
      e.response?.data?.message || "No se pudo preparar la renovación.";
  } finally {
    saving.value = false;
  }
}

function refreshLease(event) {
  if (
    !event.detail?.leaseId ||
    String(event.detail.leaseId) === String(route.params.id)
  )
    load();
}
onMounted(() => {
  window.addEventListener("alquivo:leases-changed", refreshLease);
  window.addEventListener("alquivo:contacts-changed", load);
  load();
});
watch(
  () => route.params.id,
  () => {
    lease.value = null;
    load();
  },
);
onBeforeUnmount(() => {
  loadGeneration++;
  window.removeEventListener("alquivo:leases-changed", refreshLease);
  window.removeEventListener("alquivo:contacts-changed", load);
});
</script>

<template>
  <main v-if="lease" class="page">
    <RouterLink class="back" to="/leases"
      ><ArrowLeft :size="15" />Volver a alquileres</RouterLink
    >
    <header class="lease-heading">
      <div>
        <p class="eyebrow">Contrato · {{ statusLabel[lease.status] }}</p>
        <h1>{{ lease.property.name }}</h1>
        <p>
          {{ lease.participants.map((contact) => contact.name).join(", ") }}
        </p>
      </div>
      <div class="hero-actions">
        <button class="button secondary" type="button" @click="openEdit">
          <Pencil :size="16" />Editar</button
        ><button
          v-if="!lease.renewal"
          class="button primary"
          type="button"
          @click="openRenew"
        >
          <RefreshCw :size="16" />Renovar
        </button>
      </div>
    </header>

    <section class="asset-metrics">
      <article>
        <span>Renta mensual</span
        ><strong>{{ money(lease.monthly_rent) }}</strong>
      </article>
      <article>
        <span>Fianza</span><strong>{{ money(lease.deposit_amount) }}</strong>
      </article>
      <article>
        <span>Inicio</span><strong>{{ lease.start_date.slice(0, 10) }}</strong>
      </article>
      <article>
        <span>Finalización</span
        ><strong>{{ lease.end_date?.slice(0, 10) || "Indefinido" }}</strong>
      </article>
    </section>

    <section class="detail-grid">
      <article class="panel detail-panel">
        <header>
          <UsersRound :size="19" />
          <h2>Inquilinos</h2>
        </header>
        <div class="tenant-list">
          <div v-for="contact in lease.participants" :key="contact.id">
            <strong>{{ contact.name }}</strong
            ><small>{{
              contact.email || contact.phone || "Sin contacto"
            }}</small>
          </div>
        </div>
      </article>
      <article class="panel detail-panel">
        <header>
          <CalendarDays :size="19" />
          <h2>Historial de cobros</h2>
        </header>
        <div v-if="lease.charges.length" class="charge-history">
          <div v-for="charge in lease.charges" :key="charge.id">
            <span
              ><strong>{{ charge.period }}</strong
              ><small>Vence {{ charge.due_date }}</small></span
            ><span
              ><strong
                >{{ money(charge.paid_amount) }} /
                {{ money(charge.amount) }}</strong
              ><small>{{ charge.status }}</small></span
            >
          </div>
        </div>
        <p v-else class="muted">Todavía no hay mensualidades generadas.</p>
      </article>
      <article class="panel detail-panel">
        <header>
          <FileText :size="19" />
          <h2>Documentos</h2>
        </header>
        <div v-if="lease.documents.length" class="tenant-list">
          <div v-for="document in lease.documents" :key="document.id">
            <strong>{{ document.name }}</strong
            ><small>{{ document.category }}</small>
          </div>
        </div>
        <RouterLink :to="`/documents?new=1&lease=${lease.id}`"
          >Subir documento del contrato</RouterLink
        >
      </article>
      <article class="panel detail-panel">
        <p class="eyebrow">Seguimiento</p>
        <h2>
          {{
            lease.renewal
              ? "Renovación preparada"
              : lease.renewed_from
                ? "Procede de una renovación"
                : "Contrato original"
          }}
        </h2>
        <p class="muted">
          Los cambios de renta se aplican a nuevas mensualidades sin modificar
          cobros anteriores.
        </p>
        <RouterLink v-if="lease.renewal" :to="`/leases/${lease.renewal.id}`"
          >Ver renovación</RouterLink
        ><RouterLink
          v-if="lease.renewed_from"
          :to="`/leases/${lease.renewed_from.id}`"
          >Ver contrato anterior</RouterLink
        >
      </article>
    </section>

    <div
      v-if="action"
      class="drawer-bg"
      @click.self="router.replace(`/leases/${lease.id}`)"
    >
      <form v-if="action === 'edit'" class="drawer" @submit.prevent="saveEdit">
        <p class="eyebrow">Editar contrato</p>
        <h2>Condiciones y estado</h2>
        <p v-if="error" class="error">{{ error }}</p>
        <label
          >Estado<select v-model="editForm.status">
            <option value="draft">Borrador</option>
            <option value="active">Activo</option>
            <option value="ended">Finalizado</option>
            <option value="cancelled">Cancelado</option>
          </select></label
        ><label
          >Inquilinos<select v-model="editForm.contact_ids" multiple required>
            <option
              v-for="contact in contacts"
              :key="contact.id"
              :value="contact.id"
            >
              {{ contact.name }}
            </option>
          </select></label
        ><label
          >Inicio<input
            v-model="editForm.start_date"
            type="date"
            required /></label
        ><label>Fin<input v-model="editForm.end_date" type="date" /></label
        ><label
          >Renta mensual<input
            v-model="editForm.monthly_rent"
            type="number"
            min="1"
            step="0.01"
            required /></label
        ><label
          >Fianza<input
            v-model="editForm.deposit_amount"
            type="number"
            min="0"
            step="0.01" /></label
        ><label
          >Día de cobro<input
            v-model="editForm.payment_day"
            type="number"
            min="1"
            max="28"
            required /></label
        ><label
          >Notas<textarea v-model="editForm.notes" rows="4"></textarea>
        </label>
        <footer>
          <button class="button primary" :disabled="saving">
            {{ saving ? "Guardando…" : "Guardar cambios" }}
          </button>
        </footer>
      </form>
      <form
        v-else-if="action === 'renew'"
        class="drawer"
        @submit.prevent="saveRenewal"
      >
        <p class="eyebrow">Renovación</p>
        <h2>Prepara el siguiente periodo</h2>
        <p class="muted">
          Se creará como borrador para no interferir con el contrato vigente.
        </p>
        <p v-if="error" class="error">{{ error }}</p>
        <label
          >Nuevo inicio<input
            v-model="renewalForm.start_date"
            type="date"
            required /></label
        ><label
          >Nueva finalización<input
            v-model="renewalForm.end_date"
            type="date" /></label
        ><label
          >Renta mensual<input
            v-model="renewalForm.monthly_rent"
            type="number"
            min="1"
            step="0.01"
            required /></label
        ><label
          >Fianza<input
            v-model="renewalForm.deposit_amount"
            type="number"
            min="0"
            step="0.01" /></label
        ><label
          >Día de cobro<input
            v-model="renewalForm.payment_day"
            type="number"
            min="1"
            max="28"
            required /></label
        ><label
          >Notas<textarea v-model="renewalForm.notes" rows="4"></textarea>
        </label>
        <footer>
          <button class="button primary" :disabled="saving">
            {{ saving ? "Preparando…" : "Crear renovación" }}
          </button>
        </footer>
      </form>
    </div>
  </main>
  <main v-else class="page">
    <div v-if="loading" class="empty" role="status">Cargando contrato…</div>
    <div v-else class="empty" role="alert">
      <p>{{ loadError }}</p>
      <button class="button secondary" @click="load">Volver a intentar</button>
    </div>
  </main>
</template>
