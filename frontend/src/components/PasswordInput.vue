<script setup>
import { computed, nextTick, ref, useAttrs, useId, watch } from "vue";
import { Eye, EyeOff } from "@lucide/vue";

defineOptions({ inheritAttrs: false });
const props = defineProps({
  modelValue: { type: String, default: "" },
  autocomplete: { type: String, default: "current-password" },
});
const emit = defineEmits(["update:modelValue"]);
const attrs = useAttrs();
const generatedId = useId();
const inputId = computed(() => attrs.id || `password-${generatedId}`);
const input = ref(null);
const visible = ref(false);
const disabled = computed(
  () => attrs.disabled !== undefined && attrs.disabled !== false,
);

watch(
  () => props.modelValue,
  (value) => {
    if (!value) visible.value = false;
  },
);
watch(
  () => props.autocomplete,
  () => (visible.value = false),
);

async function toggleVisibility() {
  if (disabled.value) return;
  const start = input.value?.selectionStart;
  const end = input.value?.selectionEnd;
  visible.value = !visible.value;
  await nextTick();
  input.value?.focus({ preventScroll: true });
  if (
    start !== null &&
    start !== undefined &&
    end !== null &&
    end !== undefined
  )
    input.value?.setSelectionRange(start, end);
}
</script>

<template>
  <span class="password-field">
    <input
      v-bind="attrs"
      :id="inputId"
      ref="input"
      :type="visible ? 'text' : 'password'"
      :value="modelValue"
      :autocomplete="autocomplete"
      spellcheck="false"
      autocapitalize="none"
      autocorrect="off"
      @input="emit('update:modelValue', $event.target.value)"
    />
    <button
      type="button"
      class="password-toggle"
      :disabled="disabled"
      :aria-controls="inputId"
      :aria-label="visible ? 'Ocultar contraseña' : 'Mostrar contraseña'"
      :aria-pressed="visible"
      @click="toggleVisibility"
    >
      <EyeOff v-if="visible" :size="18" aria-hidden="true" />
      <Eye v-else :size="18" aria-hidden="true" />
      <span>{{ visible ? "Ocultar" : "Mostrar" }}</span>
    </button>
  </span>
</template>

<style scoped>
.password-field {
  position: relative;
  display: flex;
  min-width: 0;
  width: 100%;
}
.password-field > input {
  min-width: 0;
  width: 100%;
  min-height: 48px;
  padding-inline-end: 7.4rem;
}
.password-toggle {
  position: absolute;
  inset-block: 2px;
  inset-inline-end: 3px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  min-width: 105px;
  min-height: 44px;
  padding: 0 10px;
  border: 0;
  border-radius: 6px;
  background: transparent;
  color: var(--ink);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}
.password-toggle:hover:not(:disabled) {
  background: var(--surface-hover);
}
.password-toggle:focus-visible {
  outline: 2px solid var(--focus);
  outline-offset: -2px;
}
.password-toggle:disabled {
  opacity: 0.5;
  cursor: default;
}
</style>
