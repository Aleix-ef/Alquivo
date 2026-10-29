import { onMounted, onUnmounted } from "vue";

// Progressive enhancement: prerendered content is always visible without JS,
// IntersectionObserver, or motion permission. No scroll handlers or timers.
export function observeLandingMotion(root, environment = globalThis) {
  const query = environment.matchMedia?.("(prefers-reduced-motion: reduce)");
  let observer;
  const clear = () => {
    observer?.disconnect();
    observer = undefined;
    root.querySelectorAll(".is-revealed").forEach((element) => {
      element.classList.remove("is-revealed");
    });
  };
  const configure = () => {
    clear();
    if (query?.matches || !environment.IntersectionObserver) return;
    observer = new environment.IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (!entry.isIntersecting) continue;
          entry.target.classList.add("is-revealed");
          observer?.unobserve(entry.target);
        }
      },
      { threshold: 0.12 },
    );
    root
      .querySelectorAll("[data-landing-reveal]")
      .forEach((element) => observer.observe(element));
  };
  configure();
  query?.addEventListener?.("change", configure);
  return () => {
    query?.removeEventListener?.("change", configure);
    clear();
  };
}

export function useLandingMotion(root) {
  let cleanup;
  onMounted(() => {
    if (root.value) cleanup = observeLandingMotion(root.value);
  });
  onUnmounted(() => cleanup?.());
}
