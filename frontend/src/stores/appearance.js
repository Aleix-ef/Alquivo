import { defineStore } from "pinia";
import { computed, ref } from "vue";

const storageKey = "nareo-appearance";
const choices = ["light", "dark", "system"];

function readPreference() {
  try {
    const saved = localStorage.getItem(storageKey);
    return choices.includes(saved) ? saved : "system";
  } catch {
    return "system";
  }
}

export const useAppearance = defineStore("appearance", () => {
  const preference = ref(readPreference());
  const media = window.matchMedia("(prefers-color-scheme: dark)");
  const systemDark = ref(media.matches);
  const dark = computed(() =>
    preference.value === "system"
      ? systemDark.value
      : preference.value === "dark",
  );

  function apply() {
    document.documentElement.dataset.theme = dark.value ? "dark" : "light";
    document
      .querySelector('meta[name="theme-color"]')
      ?.setAttribute("content", dark.value ? "#0b1420" : "#f5f7fa");
  }

  function setPreference(value) {
    if (!choices.includes(value)) return;
    preference.value = value;
    try {
      localStorage.setItem(storageKey, value);
    } catch {
      // The theme still works when browser storage is unavailable.
    }
    apply();
  }

  media.addEventListener("change", (event) => {
    systemDark.value = event.matches;
    apply();
  });
  apply();

  return { preference, dark, setPreference };
});
