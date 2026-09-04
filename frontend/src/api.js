import axios from "axios";
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
export const csrf = () =>
  axios.get(csrfURL, {
    withCredentials: true,
  });
api.interceptors.response.use(
  (r) => r,
  (e) => {
    if (e.response?.status === 401) {
      ["ig_user", "ig_portfolio"].forEach((k) => localStorage.removeItem(k));
      if (!location.pathname.startsWith("/login")) location.assign("/login");
    }
    return Promise.reject(e);
  },
);
export default api;
