<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { KeyRound, Plus } from "@lucide/vue";
import { useRoute, useRouter } from "vue-router";
import api from "../api";
import { fetchAllPages } from "../pagination";
import ConfirmDialog from "../components/ConfirmDialog.vue";
import TenantSelector from "../components/TenantSelector.vue";
import { useConfirmDialog } from "../composables/useConfirmDialog";
const route = useRoute(),
  router = useRouter(),
  leases = ref([]),
  properties = ref([]),
  contacts = ref([]),
  loading = ref(true),
  loadError = ref(""),
  saving = ref(false),
  error = ref(""),
  show = computed(() => route.query.new === "1");
const confirmation = useConfirmDialog();
const form = ref({
  property_id: route.query.property || "",
  start_date: new Date().toISOString().slice(0, 10),
  end_date: "",
  monthly_rent: "",
  deposit_amount: "",
  payment_day: 1,
  status: "active",
});
const selectedContactIds = ref([]);
const newContacts = ref([]);
const closeNew = () => router.replace(route.query.from === "property" && route.query.property ? `/properties/${route.query.property}?tab=alquiler` : "/leases");
watch(() => route.query.property, (id) => { if (id) form.value.property_id = id; });
const money = (v) =>
  new Intl.NumberFormat("es-ES", { style: "currency", currency: "EUR" }).format(
    v || 0,
  );
async function load() {
  loading.value = true;
  loadError.value = "";
  try {
    const [l, p, c] = await Promise.all([
      fetchAllPages(api, "/leases"),
      fetchAllPages(api, "/properties"),
      fetchAllPages(api, "/contacts"),
    ]);
    leases.value = l;
    properties.value = p;
    contacts.value = c;
    if (show.value && !c.length && !selectedContactIds.value.length && !newContacts.value.length) {
      newContacts.value = [{ name: "", email: "", phone: "" }];
    }
  } catch {
    loadError.value = "No hemos podido cargar los alquileres.";
  } finally {
    loading.value = false;
  }
}
async function save() {
  saving.value = true;
  error.value = "";
  try {
    if (!selectedContactIds.value.length && !newContacts.value.length) {
      error.value = "Añade al menos un inquilino.";
      return;
    }
    const { data } = await api.post("/leases", {
      property_id: Number(form.value.property_id),
      contact_ids: selectedContactIds.value,
      new_contacts: newContacts.value.map((contact) => ({
        name: contact.name.trim(),
        email: contact.email.trim() || null,
        phone: contact.phone.trim() || null,
      })),
      status: "active",
      start_date: form.value.start_date,
      end_date: form.value.end_date || null,
      monthly_rent: Number(form.value.monthly_rent),
      deposit_amount: Number(form.value.deposit_amount || 0),
      payment_day: Number(form.value.payment_day),
    });
    if (route.query.from === "property" && route.query.property) {
      await router.replace(`/properties/${route.query.property}?tab=alquiler`);
    } else {
      await router.replace(`/leases/${data.id}`);
    }
  } catch (e) {
    error.value = e.response?.data?.message || "No se pudo crear el alquiler.";
  } finally {
    saving.value = false;
  }
}
async function endLease(lease) {
  if (
    !(await confirmation.ask({
      title: `¿Finalizar el alquiler de ${lease.property?.name}?`,
      description:
        "El contrato conservará su historial y dejará de generar nuevas mensualidades.",
      confirmLabel: "Finalizar alquiler",
    }))
  )
    return;
  try {
    await api.put(`/leases/${lease.id}`, { status: "ended" });
    await load();
  } catch (exception) {
    loadError.value =
      exception.response?.data?.message || "No se pudo finalizar el alquiler.";
  }
}
onMounted(load);
</script>
<template>
  <main class="page">
    <header class="heading" data-tour="leases">
      <div>
        <p class="eyebrow">Alquileres</p>
        <h1>Arrendamientos</h1>
        <p>Contratos, inquilinos y cobros esperados en un mismo lugar.</p>
      </div>
      <RouterLink class="button primary" to="?new=1"
        ><Plus :size="16" />Nuevo alquiler</RouterLink
      >
    </header>
    <section v-if="loading" class="empty" role="status">
      Cargando alquileres…
    </section>
    <section v-else-if="loadError" class="empty" role="alert">
      <p>{{ loadError }}</p>
      <button class="button secondary" @click="load">Volver a intentar</button>
    </section>
    <section v-else-if="leases.length" class="record-list">
      <article v-for="l in leases" :key="l.id" class="record">
        <span class="record-icon"><KeyRound :size="20" /></span>
        <div>
          <RouterLink :to="`/leases/${l.id}`"
            ><strong>{{ l.property?.name }}</strong></RouterLink
          ><small>{{ l.participants?.map((p) => p.name).join(", ") }}</small>
        </div>
        <div>
          <strong>{{ money(l.monthly_rent) }}/mes</strong
          ><small>Cobro el día {{ l.payment_day }}</small>
        </div>
        <button
          v-if="l.status === 'active'"
          class="button secondary"
          type="button"
          @click="endLease(l)"
        >
          Finalizar
        </button>
        <RouterLink v-else class="pill" :to="`/leases/${l.id}`">{{
          l.status
        }}</RouterLink>
      </article>
    </section>
    <section v-else class="empty">
      <KeyRound :size="35" />
      <h2>Aún no tienes alquileres</h2>
      <p>
        Vincula una propiedad y un inquilino para controlar la renta cada mes.
      </p>
      <RouterLink v-if="properties.length" class="button primary" to="?new=1"
        >Crear alquiler</RouterLink
      ><RouterLink v-else class="button secondary" to="/properties?new=1"
        >Primero añade una propiedad</RouterLink
      >
    </section>
    <div v-if="show" class="drawer-bg" @click.self="closeNew">
      <form class="drawer" @submit.prevent="save">
        <header>
          <div>
            <p class="eyebrow">Nuevo alquiler</p>
            <h2>Activa el seguimiento</h2>
          </div>
        </header>
        <p v-if="error" class="error">{{ error }}</p>
        <label
          >Propiedad<select v-model="form.property_id" required>
            <option value="" disabled>Selecciona</option>
            <option v-for="p in properties" :key="p.id" :value="p.id">
              {{ p.name }}
            </option>
          </select></label
        >
        <TenantSelector
          :contacts="contacts"
          v-model:selected-ids="selectedContactIds"
          v-model:new-contacts="newContacts"
        />
        <h3>Condiciones</h3>
        <label
          >Inicio<input v-model="form.start_date" type="date" required /></label
        ><label>Fin opcional<input v-model="form.end_date" type="date" /></label
        ><label
          >Renta mensual<input
            v-model="form.monthly_rent"
            type="number"
            min="1"
            required /></label
        ><label
          >Fianza<input
            v-model="form.deposit_amount"
            type="number"
            min="0" /></label
        ><label
          >Día de cobro<input
            v-model="form.payment_day"
            type="number"
            min="1"
            max="28"
            required
        /></label>
        <footer>
          <button class="button primary" :disabled="saving">
            {{ saving ? "Creando…" : "Crear arrendamiento" }}
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
