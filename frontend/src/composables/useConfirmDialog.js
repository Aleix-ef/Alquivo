import { onBeforeUnmount, ref } from "vue";

export function useConfirmDialog() {
  const dialog = ref(null);
  let resolvePending;

  function ask(options) {
    if (resolvePending) resolvePending(false);
    dialog.value = {
      title: options.title,
      description: options.description,
      confirmLabel: options.confirmLabel || "Confirmar",
      danger: Boolean(options.danger),
    };
    return new Promise((resolve) => {
      resolvePending = resolve;
    });
  }

  function answer(value) {
    const resolve = resolvePending;
    resolvePending = undefined;
    dialog.value = null;
    resolve?.(value);
  }

  onBeforeUnmount(() => answer(false));

  return {
    dialog,
    ask,
    confirm: () => answer(true),
    cancel: () => answer(false),
  };
}
