import { defineStore } from "pinia";
import api from "../api";

export const usePlanAccess = defineStore("planAccess", {
  state: () => ({ usage: null, owner: null, request: 0 }),
  getters: {
    readOnly: (state) => (id) =>
      Boolean(
        state.usage?.properties.read_only_count &&
        !state.usage.properties.editable_ids.includes(Number(id)),
      ),
  },
  actions: {
    async load(owner) {
      const request = ++this.request;
      if (this.owner !== owner) this.usage = null;
      this.owner = owner;
      if (!owner) return;
      try {
        const { data } = await api.get("/account/usage");
        if (request === this.request) this.usage = data;
      } catch {
        // The server always enforces access even if this informational request fails.
      }
    },
  },
});
