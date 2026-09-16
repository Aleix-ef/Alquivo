<script setup>
import { computed, ref } from "vue";
import { useDialog } from "../composables/useDialog";

const props = defineProps({ dialog: { type: Object, default: null } });
const emit = defineEmits(["confirm", "cancel"]);
const element = ref(null);
useDialog(
  computed(() => Boolean(props.dialog)),
  element,
  () => emit("cancel"),
);
</script>

<template>
  <Teleport to="body">
    <div v-if="dialog" class="modal-backdrop" @click.self="$emit('cancel')">
      <section
        ref="element"
        class="plan-modal confirm-dialog"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="confirm-dialog-title"
        aria-describedby="confirm-dialog-description"
        tabindex="-1"
      >
        <p class="eyebrow">Confirma la acción</p>
        <h2 id="confirm-dialog-title">{{ dialog.title }}</h2>
        <p id="confirm-dialog-description">{{ dialog.description }}</p>
        <div class="plan-modal-actions">
          <button
            class="button secondary"
            type="button"
            @click="$emit('cancel')"
          >
            Cancelar
          </button>
          <button
            class="button"
            :class="dialog.danger ? 'danger' : 'primary'"
            type="button"
            @click="$emit('confirm')"
          >
            {{ dialog.confirmLabel }}
          </button>
        </div>
      </section>
    </div>
  </Teleport>
</template>
