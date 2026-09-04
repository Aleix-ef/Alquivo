import { nextTick, onBeforeUnmount, watch } from "vue";

// Keep keyboard focus inside a modal and return it to its trigger on close.
export function useDialog(isOpen, dialogRef, close) {
  let previousFocus;
  let previousOverflow;
  let active = false;
  const focusable = () =>
    Array.from(
      dialogRef.value?.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
      ) || [],
    ).filter((element) => element.getClientRects().length);

  function onKeydown(event) {
    if (event.key === "Escape") {
      event.preventDefault();
      close();
    } else if (event.key === "Tab") {
      const elements = focusable();
      const first = elements[0];
      const last = elements.at(-1);
      if (!first) {
        event.preventDefault();
        dialogRef.value?.focus();
      } else if (
        event.shiftKey &&
        (document.activeElement === first ||
          !dialogRef.value?.contains(document.activeElement))
      ) {
        event.preventDefault();
        last.focus();
      } else if (
        !event.shiftKey &&
        (document.activeElement === last ||
          !dialogRef.value?.contains(document.activeElement))
      ) {
        event.preventDefault();
        first.focus();
      }
    }
  }

  function release() {
    if (!active) return;
    active = false;
    document.body.style.overflow = previousOverflow;
    document.removeEventListener("keydown", onKeydown);
    // Wait until Vue removes the backdrop/inert state before restoring focus.
    const returnTarget = previousFocus;
    nextTick(() => {
      if (!active && returnTarget?.isConnected) returnTarget.focus();
    });
  }

  watch(
    isOpen,
    async (open) => {
      if (!open) return release();
      previousFocus = document.activeElement;
      previousOverflow = document.body.style.overflow;
      active = true;
      document.body.style.overflow = "hidden";
      document.addEventListener("keydown", onKeydown);
      await nextTick();
      if (active && isOpen.value) (focusable()[0] || dialogRef.value)?.focus();
    },
    { immediate: true },
  );

  onBeforeUnmount(release);
}
