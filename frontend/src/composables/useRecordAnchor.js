import { watch, nextTick, onBeforeUnmount, ref } from "vue";
import { useRoute } from "vue-router";
import { recordAnchorId, revealRecord } from "../recordAnchor";

// Focus only an existing rendered record, after asynchronous loading completes.
export function useRecordAnchor(loading, prefix) {
  const route = useRoute();
  const missing = ref(false);
  let clearHighlight = () => {};
  watch(
    [loading, () => route.hash],
    async ([busy, hash], previous, onCleanup) => {
      clearHighlight();
      missing.value = false;
      if (busy || recordAnchorId(hash, prefix) === null) return;
      let cancelled = false;
      onCleanup(() => {
        cancelled = true;
      });
      await nextTick();
      if (cancelled) return;
      const element = document.getElementById(hash.slice(1));
      missing.value = !element;
      clearHighlight = revealRecord(element);
    },
    { flush: "post", immediate: true },
  );
  onBeforeUnmount(() => clearHighlight());
  return { missing };
}
