import { computed, onScopeDispose, ref, watch } from "vue";

// Measures waiting only. It never polls, retries or claims to know a tool's phase.
export function useAssistantWait(
  sending,
  {
    now = () => Date.now(),
    schedule = (callback) => setInterval(callback, 1000),
    cancel = (timer) => clearInterval(timer),
  } = {},
) {
  const elapsedSeconds = ref(0);
  let timer;
  const stop = () => {
    if (timer !== undefined) cancel(timer);
    timer = undefined;
  };
  const unwatch = watch(
    sending,
    (active) => {
      stop();
      elapsedSeconds.value = 0;
      if (!active) return;
      const started = now();
      timer = schedule(() => {
        elapsedSeconds.value = Math.max(
          0,
          Math.floor((now() - started) / 1000),
        );
      });
    },
    { immediate: true, flush: "sync" },
  );
  onScopeDispose(() => {
    unwatch();
    stop();
  });
  const slow = computed(() => elapsedSeconds.value >= 10);
  const label = computed(() =>
    slow.value
      ? "La respuesta está tardando un poco más."
      : "Preparando tu respuesta…",
  );
  return { elapsedSeconds, slow, label };
}
