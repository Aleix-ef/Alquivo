import { defineStore } from "pinia";
import api, { csrf } from "./api";
import {
  clearSessionStorage,
  persistSession,
  readSessionValue,
} from "./sessionStorage";
export const useSession = defineStore("session", {
  state: () => ({
    user: readSessionValue("ig_user"),
    portfolio: readSessionValue("ig_portfolio"),
    initialized: false,
  }),
  getters: { ready: (s) => !!s.user },
  actions: {
    save(d) {
      this.user = d.user;
      this.portfolio = d.portfolio;
      this.initialized = true;
      persistSession(d);
    },
    clear() {
      this.user = null;
      this.portfolio = null;
      this.initialized = true;
      clearSessionStorage();
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
        this.save((await api.get("/auth/me", { timeout: 10000 })).data);
        return true;
      } catch (error) {
        if ([401, 419].includes(error.response?.status)) this.clear();
        this.initialized = true;
        return false;
      }
    },
    async logout() {
      try {
        await api.post("/auth/logout");
      } catch (error) {
        if (![401, 419].includes(error.response?.status)) throw error;
      }
      this.clear();
    },
  },
});
