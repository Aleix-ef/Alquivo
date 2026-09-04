import { defineStore } from "pinia";
import api, { csrf } from "./api";
const keys = ["ig_user", "ig_portfolio"];
export const useSession = defineStore("session", {
  state: () => ({
    user: JSON.parse(localStorage.getItem("ig_user") || "null"),
    portfolio: JSON.parse(localStorage.getItem("ig_portfolio") || "null"),
  }),
  getters: { ready: (s) => !!s.user },
  actions: {
    save(d) {
      this.user = d.user;
      this.portfolio = d.portfolio;
      localStorage.setItem("ig_user", JSON.stringify(d.user));
      localStorage.setItem("ig_portfolio", JSON.stringify(d.portfolio));
    },
    clear() {
      this.user = null;
      this.portfolio = null;
      keys.forEach((k) => localStorage.removeItem(k));
    },
    async login(p) {
      await csrf();
      this.save((await api.post("/auth/login", p)).data);
    },
    async register(p) {
      await csrf();
      this.save((await api.post("/auth/register", p)).data);
    },
    async restore() {
      try {
        this.save((await api.get("/auth/me")).data);
        return true;
      } catch {
        this.clear();
        return false;
      }
    },
    async logout() {
      try {
        await api.post("/auth/logout");
      } finally {
        this.clear();
      }
    },
  },
});
