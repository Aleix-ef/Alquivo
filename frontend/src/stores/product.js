import { defineStore } from "pinia";
import api from "../api";
import { useSession } from "../session";

export const useProduct = defineStore("product", {
  state: () => ({
    loaded: false,
    error: false,
    features: {
      beta_program: import.meta.env.VITE_BETA_PROGRAM_ENABLED !== "false",
      assistant: false,
      fiscality: false,
      billing_enabled: false,
      support_email: null,
    },
  }),
  getters: {
    accountFeatures(state) {
      const session = useSession();
      return session.initialized && session.user?.local_admin
        ? { ...state.features, assistant: true, fiscality: true }
        : state.features;
    },
  },
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
