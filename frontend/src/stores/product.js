import { defineStore } from "pinia";
import api from "../api";

export const useProduct = defineStore("product", {
  state: () => ({
    loaded: false,
    error: false,
    features: {
      assistant: false,
      fiscality: false,
      billing_enabled: false,
      support_email: null,
    },
  }),
  actions: {
    async load() {
      try {
        this.features = (
          await api.get("/public/config", { timeout: 10000 })
        ).data;
        this.loaded = true;
        this.error = false;
      } catch {
        this.error = true;
      }
    },
  },
});
