<script setup>
import { ref, computed, onMounted } from "vue";
import { KeyRound, Plus } from "@lucide/vue";
import { useRoute, useRouter } from "vue-router";
import api from "../api";
const route = useRoute(),
  router = useRouter(),
  leases = ref([]),
  properties = ref([]),
  contacts = ref([]),
  saving = ref(false),
  error = ref(""),
  show = computed(() => route.query.new === "1");
const form = ref({
  property_id: "",
  contact_id: "",
  tenant_name: "",
  tenant_email: "",
  start_date: new Date().toISOString().slice(0, 10),
  end_date: "",
  monthly_rent: "",
  deposit_amount: "",
  payment_day: 1,
  status: "active",
});
const money = (v) =>
  new Intl.NumberFormat("es-ES", { style: "currency", currency: "EUR" }).format(
    v || 0,
  );
async function load() {
  const [l, p, c] = await Promise.all([
    api.get("/leases"),
    api.get("/properties"),
    api.get("/contacts"),
  ]);
  leases.value = l.data.data;
  properties.value = p.data.data;
  contacts.value = c.data.data;
}
async function save() {
  saving.value = true;
  error.value = "";
  try {
    let contactId = form.value.contact_id
      ? Number(form.value.contact_id)
      : null;
    if (!contactId) {
      contactId = (
        await api.post("/contacts", {
          kind: "person",
          name: form.value.tenant_name,
          email: form.value.tenant_email || null,
        })
      ).data.id;
    }
    await api.post("/leases", {
      property_id: Number(form.value.property_id),
      contact_ids: [contactId],
      status: "active",
      start_date: form.value.start_date,
      end_date: form.value.end_date || null,
      monthly_rent: Number(form.value.monthly_rent),
      deposit_amount: Number(form.value.deposit_amount || 0),
      payment_day: Number(form.value.payment_day),
    });
    router.replace("/leases");
    await load();
  } catch (e) {
    error.value = e.response?.data?.message || "No se pudo crear el alquiler.";
  } finally {
    saving.value = false;
  }
}
async function endLease(lease) {
  if (!window.confirm(`¿Finalizar el alquiler de ${lease.property?.name}?`))
    return;
  await api.put(`/leases/${lease.id}`, { status: "ended" });
  await load();
}
onMounted(load);
</script>
<template>
  <main class="page">
    <header class="heading">
      <div>
        <p class="eyebrow">Alquileres</p>
        <h1>Arrendamientos</h1>
        <p>Contratos, inquilinos y cobros esperados en un mismo lugar.</p>
      </div>
      <RouterLink class="button primary" to="?new=1"
        ><Plus :size="16" />Nuevo alquiler</RouterLink
      >
    </header>
    <section v-if="leases.length" class="record-list">
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
    <div v-if="show" class="drawer-bg" @click.self="router.replace('/leases')">
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
        <h3>Inquilino</h3>
        <label
          >Contacto existente<select v-model="form.contact_id">
            <option value="">Crear una persona nueva</option>
            <option
              v-for="contact in contacts"
              :key="contact.id"
              :value="contact.id"
            >
              {{ contact.name }}{{ contact.email ? ` · ${contact.email}` : "" }}
            </option>
          </select></label
        >
        <template v-if="!form.contact_id">
          <label>Nombre<input v-model="form.tenant_name" required /></label
          ><label
            >Email<input v-model="form.tenant_email" type="email"
          /></label>
        </template>
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
  </main>
</template>
