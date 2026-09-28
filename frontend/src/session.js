import { defineStore } from "pinia";
import api, { csrf } from "./api";
import {
  clearSessionStorage,
  persistSession,
  readSessionValue,
  sessionKeys,
} from "./sessionStorage";
export const useSession = defineStore("session", {
  state: () => ({
    user: readSessionValue(sessionKeys.user),
    portfolio: readSessionValue(sessionKeys.portfolio),
    initialized: false,
    twoFactorMethods: [],
    twoFactorEmailHint: null,
    twoFactorEmailUnavailable: false,
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
      this.twoFactorMethods = [];
      this.twoFactorEmailHint = null;
      this.twoFactorEmailUnavailable = false;
      clearSessionStorage();
    },
    async login(p) {
      await csrf();
      const { data } = await api.post("/auth/login", p);
      if (data.two_factor_required) {
        this.clear();
        this.twoFactorMethods = data.methods || ["authenticator"];
        this.twoFactorEmailHint = data.email_hint || null;
        this.twoFactorEmailUnavailable = Boolean(data.email_delivery_failed);
        return false;
      }
      this.twoFactorMethods = [];
      this.twoFactorEmailHint = null;
      this.twoFactorEmailUnavailable = false;
      this.save(data);
      return true;
    },
    async verifyTwoFactor(code, method, rememberDevice = false) {
      await csrf();
      const { data } = await api.post("/auth/two-factor", {
        code,
        method,
        remember_device: rememberDevice,
      });
      if (!data.challenge_complete) {
        this.twoFactorMethods = data.methods_remaining || [];
        return false;
      }
      this.twoFactorMethods = [];
      this.twoFactorEmailHint = null;
      this.twoFactorEmailUnavailable = false;
      this.save(data);
      return true;
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
