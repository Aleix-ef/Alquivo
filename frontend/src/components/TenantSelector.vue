<script setup>
import { computed, ref } from "vue";

const props = defineProps({
  contacts: { type: Array, default: () => [] },
  selectedIds: { type: Array, default: () => [] },
  newContacts: { type: Array, default: () => [] },
});
const emit = defineEmits(["update:selectedIds", "update:newContacts"]);
const selectedContact = ref("");
const available = computed(() =>
  props.contacts.filter((contact) => !props.selectedIds.includes(contact.id)),
);
const atLimit = computed(() => props.selectedIds.length + props.newContacts.length >= 20);

function addExisting() {
  const id = Number(selectedContact.value);
  if (!id || atLimit.value || props.selectedIds.includes(id)) return;
  emit("update:selectedIds", [...props.selectedIds, id]);
  selectedContact.value = "";
}
function removeExisting(id) {
  emit("update:selectedIds", props.selectedIds.filter((item) => item !== id));
}
function addNew() {
  if (!atLimit.value) emit("update:newContacts", [...props.newContacts, { name: "", email: "", phone: "" }]);
}
function updateNew(index, field, value) {
  emit("update:newContacts", props.newContacts.map((contact, position) =>
    position === index ? { ...contact, [field]: value } : contact,
  ));
}
function removeNew(index) {
  emit("update:newContacts", props.newContacts.filter((_, position) => position !== index));
}
</script>

<template>
  <div class="tenant-selector">
    <div class="tenant-selector-heading">
      <h3>Inquilinos</h3>
      <span>{{ selectedIds.length + newContacts.length }} / 20</span>
    </div>
    <div v-if="selectedIds.length" class="tenant-selected">
      <span v-for="id in selectedIds" :key="id" class="tenant-chip">
        {{ contacts.find((contact) => contact.id === id)?.name || "Inquilino" }}
        <button type="button" :aria-label="`Quitar inquilino ${id}`" @click="removeExisting(id)">×</button>
      </span>
    </div>
    <div v-if="available.length" class="tenant-add-existing">
      <label>Elegir contacto existente
        <select v-model="selectedContact" :disabled="atLimit">
          <option value="">Selecciona un contacto</option>
          <option v-for="contact in available" :key="contact.id" :value="contact.id">
            {{ contact.name }}{{ contact.email ? ` · ${contact.email}` : "" }}
          </option>
        </select>
      </label>
      <button class="button secondary" type="button" :disabled="!selectedContact || atLimit" @click="addExisting">Añadir</button>
    </div>
    <div v-for="(contact, index) in newContacts" :key="index" class="tenant-new">
      <div class="tenant-new-heading">
        <strong>Nuevo inquilino {{ index + 1 }}</strong>
        <button type="button" @click="removeNew(index)">Quitar</button>
      </div>
      <label>Nombre<input :value="contact.name" required maxlength="120" @input="updateNew(index, 'name', $event.target.value)" /></label>
      <label>Correo (opcional)<input :value="contact.email" type="email" @input="updateNew(index, 'email', $event.target.value)" /></label>
      <label>Teléfono (opcional)<input :value="contact.phone" type="tel" maxlength="30" @input="updateNew(index, 'phone', $event.target.value)" /></label>
    </div>
    <button class="button secondary tenant-add-new" type="button" :disabled="atLimit" @click="addNew">+ Crear inquilino nuevo</button>
  </div>
</template>

<style scoped>
.tenant-selector { display: grid; gap: 13px; margin: 18px 0; }
.tenant-selector-heading, .tenant-new-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.tenant-selector-heading h3 { margin: 0; }
.tenant-selector-heading span { color: var(--muted); }
.tenant-selected { display: flex; flex-wrap: wrap; gap: 8px; }
.tenant-chip { display: inline-flex; align-items: center; gap: 8px; border: 1px solid var(--line); border-radius: 999px; padding: 6px 10px; }
.tenant-chip button, .tenant-new-heading button { background: none; border: 0; color: var(--green); cursor: pointer; font: inherit; }
.tenant-add-existing { display: flex; align-items: end; gap: 10px; }
.tenant-add-existing label { flex: 1; min-width: 0; }
.tenant-new { display: grid; gap: 10px; padding: 14px; border: 1px solid var(--line); border-radius: 12px; }
.tenant-add-new { justify-self: start; }
@media (max-width: 540px) { .tenant-add-existing { align-items: stretch; flex-direction: column; } }
</style>
