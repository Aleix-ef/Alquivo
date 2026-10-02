import { handleWaitlistRequest } from "../../waitlist-server/handler.js";

// Cloudflare Pages owns /api/waitlist only. This is not the Laravel API.
export function onRequest({ request, env }) {
  return handleWaitlistRequest(request, env);
}
