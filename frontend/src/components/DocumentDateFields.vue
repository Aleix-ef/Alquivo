<script setup>
import { computed } from "vue";
import { documentType } from "../documentTypes.js";

const props = defineProps({
  category: { type: String, required: true },
  issuedAt: { type: String, default: "" },
  expiresAt: { type: String, default: "" },
  originalExpiry: { type: Boolean, default: false },
});
const emit = defineEmits(["update:issuedAt", "update:expiresAt"]);
const type = computed(() => documentType(props.category));
const showExpiry = computed(
  () => type.value.hasExpiry || props.originalExpiry || !!props.expiresAt,
);
</script>

<template>
  <section class="document-date-fields" aria-label="Fechas del documento">
    <div class="document-date-inputs" :class="{ 'field-row': showExpiry }">
      <label>
        <span
          >{{ type.issuedLabel }} <span class="muted">(opcional)</span></span
        >
        <input
          type="date"
          data-document-date="issued"
          :value="issuedAt"
          @input="emit('update:issuedAt', $event.target.value)"
        />
      </label>
      <label v-if="showExpiry">
        <span
          >{{ type.expiryLabel || "Fecha de revisión registrada" }}
          <span class="muted">(opcional)</span></span
        >
        <input
          type="date"
          data-document-date="expiry"
          :value="expiresAt"
          :min="issuedAt || undefined"
          @input="emit('update:expiresAt', $event.target.value)"
        />
      </label>
    </div>
    <p>{{ type.hint }}</p>
    <p v-if="!type.hasExpiry && showExpiry">
      Conservamos la fecha de revisión que ya has indicado. No es una fecha
      límite para pagar; puedes borrarla si no la necesitas.
    </p>
  </section>
</template>

<style scoped>
.document-date-fields {
  margin: 18px 0;
}
.document-date-fields p {
  margin: 10px 0 0;
  font-size: 14px;
  line-height: 1.55;
  color: var(--muted);
}
.document-date-inputs label {
  min-width: 0;
}
.document-date-inputs label .muted {
  font-size: 13px;
}
</style>
