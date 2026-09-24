import axios from "axios";
import { clearSessionStorage } from "./sessionStorage";
import { safeReturnPath } from "./authNavigation";
const baseURL = import.meta.env.VITE_API_URL ?? "http://127.0.0.1:8100/api/v1";
const csrfURL = baseURL.startsWith("http")
  ? new URL("/sanctum/csrf-cookie", baseURL).toString()
  : "/sanctum/csrf-cookie";
const api = axios.create({
  baseURL,
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: "application/json" },
});
export const csrf = (options = {}) =>
  axios.get(csrfURL, {
    ...options,
    withCredentials: true,
  });
api.interceptors.response.use(
  (r) => r,
  (e) => {
    if (
      e.response?.status === 403 &&
      e.response?.data?.message === "Your email address is not verified." &&
      location.pathname !== "/settings"
    ) {
      location.assign("/settings");
    }
    // Auth screens and the initial cookie check handle their own errors.
    if (e.response?.status === 401 && !e.config?.url?.startsWith("/auth/")) {
      clearSessionStorage();
      if (location.pathname !== "/login") {
        const redirect = safeReturnPath(
          location.pathname + location.search + location.hash,
        );
        location.assign(`/login?redirect=${encodeURIComponent(redirect)}`);
      }
    }
    return Promise.reject(e);
  },
);
export default api;
