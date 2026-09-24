import { watch, nextTick } from "vue";
import { useRoute } from "vue-router";

// Focus only an existing rendered record, after asynchronous loading completes.
export function useRecordAnchor(loading, prefix) {
  const route = useRoute();
  watch(
    [loading, () => route.hash],
    async ([busy, hash]) => {
      if (busy || !new RegExp(`^#${prefix}-[1-9][0-9]*$`).test(hash)) return;
      await nextTick();
      const element = document.getElementById(hash.slice(1));
      element?.focus({ preventScroll: true });
      element?.scrollIntoView({ block: "center" });
    },
    { flush: "post" },
  );
}
