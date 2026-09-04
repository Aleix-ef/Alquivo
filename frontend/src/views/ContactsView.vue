<script setup>
import { computed, onMounted, ref } from "vue";
import { Archive, Pencil, Plus, UsersRound } from "@lucide/vue";
import { useRoute, useRouter } from "vue-router";
import api from "../api";

const route = useRoute();
const router = useRouter();
const contacts = ref([]);
const saving = ref(false);
const error = ref("");
const editing = computed(() =>
  contacts.value.find((contact) => contact.id === Number(route.query.edit)),
);
const show = computed(() => route.query.new === "1" || Boolean(editing.value));
const emptyForm = () => ({
  kind: "person",
  name: "",
  tax_id: "",
  email: "",
  phone: "",
  notes: "",
});
const form = ref(emptyForm());
const mainLease = (contact) =>
  contact.leases?.find((lease) => lease.status === "active") ||
  contact.leases?.[0];

async function load() {
  contacts.value = (await api.get("/contacts")).data.data;
}

function openNew() {
  form.value = emptyForm();
  error.value = "";
  router.push({ query: { new: "1" } });
}

function edit(contact) {
  form.value = {
    kind: contact.kind,
    name: contact.name,
    tax_id: contact.tax_id || "",
    email: contact.email || "",
    phone: contact.phone || "",
    notes: contact.notes || "",
  };
  error.value = "";
  router.push({ query: { edit: contact.id } });
}

async function save() {
  saving.value = true;
  error.value = "";
  try {
    if (editing.value)
      await api.put(`/contacts/${editing.value.id}`, form.value);
    else await api.post("/contacts", form.value);
    router.replace("/contacts");
    await load();
  } catch (e) {
    error.value =
      e.response?.data?.message || "No se pudo guardar el contacto.";
  } finally {
    saving.value = false;
  }
}

async function archive(contact) {
  if (contact.leases?.length) return;
  if (!window.confirm(`¿Archivar a ${contact.name}?`)) return;
  await api.delete(`/contacts/${contact.id}`);
  await load();
}

onMounted(load);
</script>

<template>
  <main class="page">
    <header class="heading">
      <div>
        <p class="eyebrow">Personas</p>
        <h1>Contactos e inquilinos</h1>
        <p>
          La información esencial de quienes forman parte de tus alquileres.
        </p>
      </div>
      <button class="button primary" type="button" @click="openNew">
        <Plus :size="16" />Nuevo contacto
      </button>
    </header>

    <section v-if="contacts.length" class="record-list">
      <article
        v-for="contact in contacts"
        :key="contact.id"
        class="record contact-record"
      >
        <span class="record-icon"><UsersRound :size="20" /></span>
        <div>
          <strong>{{ contact.name }}</strong>
          <small>{{
            contact.email || contact.phone || "Sin datos de contacto"
          }}</small>
        </div>
        <div>
          <strong>{{
            mainLease(contact)?.property?.name || "Sin alquiler"
          }}</strong>
          <small>{{
            mainLease(contact)?.status === "active"
              ? "Alquiler activo"
              : contact.kind === "company"
                ? "Empresa"
                : "Persona"
          }}</small>
        </div>
        <span class="record-actions">
          <button type="button" title="Editar" @click="edit(contact)">
            <Pencil :size="16" />
          </button>
          <button
            type="button"
            :disabled="Boolean(contact.leases?.length)"
            :title="
              contact.leases?.length
                ? 'Conservado por su historial de alquiler'
                : 'Archivar'
            "
            @click="archive(contact)"
          >
            <Archive :size="16" />
          </button>
        </span>
      </article>
    </section>
    <section v-else class="empty">
      <UsersRound :size="35" />
      <h2>Aún no tienes contactos</h2>
      <p>
        Añade propietarios, proveedores o futuros inquilinos y reutilízalos
        después.
      </p>
      <button class="button primary" type="button" @click="openNew">
        Añadir contacto
      </button>
    </section>

    <div
      v-if="show"
      class="drawer-bg"
      @click.self="router.replace('/contacts')"
    >
      <form class="drawer" @submit.prevent="save">
        <p class="eyebrow">
          {{ editing ? "Editar contacto" : "Nuevo contacto" }}
        </p>
        <h2>{{ editing ? "Actualiza sus datos" : "Añade una persona" }}</h2>
        <p v-if="error" class="error">{{ error }}</p>
        <label
          >Tipo<select v-model="form.kind">
            <option value="person">Persona</option>
            <option value="company">Empresa</option>
          </select></label
        >
        <label
          >Nombre<input v-model="form.name" required maxlength="120"
        /></label>
        <label>Email<input v-model="form.email" type="email" /></label>
        <label>Teléfono<input v-model="form.phone" type="tel" /></label>
        <label>NIF/CIF<input v-model="form.tax_id" /></label>
        <label>Notas<textarea v-model="form.notes" rows="4"></textarea></label>
        <footer>
          <button class="button primary" :disabled="saving">
            {{ saving ? "Guardando…" : "Guardar contacto" }}
          </button>
        </footer>
      </form>
    </div>
  </main>
</template>
