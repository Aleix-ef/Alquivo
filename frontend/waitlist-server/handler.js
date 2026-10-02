import {
  isTurnstileTestKey,
  waitlistAction,
  waitlistConsent,
  waitlistPropertyCounts,
  waitlistSuccess,
} from "../src/waitlist/contract.js";

const maxBytes = 4096;
const rateWindow = 600;
const maxAttempts = 10;
const unavailable =
  "Ahora no podemos guardar tu solicitud. Inténtalo más tarde o escribe a soporte@alquivo.com.";

function json(status, body, headers = {}) {
  return Response.json(body, {
    status,
    headers: {
      "Cache-Control": "no-store",
      "X-Content-Type-Options": "nosniff",
      "Content-Security-Policy": "default-src 'none'; frame-ancestors 'none'",
      "Referrer-Policy": "no-referrer",
      ...headers,
    },
  });
}

function allowedOrigins(env) {
  const origins = new Set(["https://alquivo.com", "https://www.alquivo.com"]);
  // An exact project hostname is allowed; arbitrary branch previews are not.
  if (env.WAITLIST_PAGES_HOSTNAME) {
    if (!/^[a-z0-9-]+\.pages\.dev$/.test(env.WAITLIST_PAGES_HOSTNAME))
      throw new Error("Invalid configuration");
    origins.add(`https://${env.WAITLIST_PAGES_HOSTNAME}`);
  }
  return origins;
}

async function readPayload(request) {
  const length = Number(request.headers.get("content-length") || 0);
  if (!Number.isFinite(length) || length > maxBytes) return { tooLarge: true };
  if (!request.body) return { invalid: true };
  const reader = request.body.getReader();
  const chunks = [];
  let size = 0;
  try {
    while (true) {
      const { value, done } = await reader.read();
      if (done) break;
      size += value.byteLength;
      if (size > maxBytes) {
        await reader.cancel();
        return { tooLarge: true };
      }
      chunks.push(value);
    }
    const bytes = new Uint8Array(size);
    let offset = 0;
    for (const chunk of chunks) {
      bytes.set(chunk, offset);
      offset += chunk.byteLength;
    }
    return {
      value: JSON.parse(
        new TextDecoder("utf-8", { fatal: true }).decode(bytes),
      ),
    };
  } catch {
    return { invalid: true };
  } finally {
    reader.releaseLock();
  }
}

function validate(data) {
  if (!data || typeof data !== "object" || Array.isArray(data))
    return "Revisa los datos del formulario.";
  const allowed = [
    "email",
    "name",
    "property_count",
    "website",
    "consent",
    "consent_version",
    "turnstile_token",
  ];
  if (Object.keys(data).some((key) => !allowed.includes(key)))
    return "Revisa los datos del formulario.";
  if (data.consent !== true)
    return "Necesitamos tu consentimiento para avisarte sobre el acceso a la beta.";
  if (data.consent_version !== waitlistConsent.version)
    return "Actualiza esta página y revisa la información de privacidad antes de enviar.";
  if (typeof data.email !== "string") return "Introduce un email válido.";
  const email = data.email.trim().toLowerCase();
  const emailPattern =
    /^[a-z0-9.!#$%&'*+\/=?^_`{|}~-]+@[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+$/;
  if (
    email.length > 254 ||
    email.split("@")[0].length > 64 ||
    !emailPattern.test(email)
  )
    return "Introduce un email válido.";
  if (
    data.name != null &&
    (typeof data.name !== "string" ||
      data.name.length > 100 ||
      /[<>\u0000-\u001f\u007f]/.test(data.name))
  )
    return "Revisa tu nombre: utiliza un máximo de 100 caracteres, sin código ni saltos de línea.";
  if (
    data.property_count != null &&
    data.property_count !== "" &&
    !waitlistPropertyCounts.some(
      (option) => option.value === data.property_count,
    )
  )
    return "Selecciona uno de los rangos de inmuebles disponibles.";
  if (
    data.website != null &&
    (typeof data.website !== "string" || data.website !== "")
  )
    return "No se ha podido validar la solicitud.";
  if (
    typeof data.turnstile_token !== "string" ||
    !data.turnstile_token ||
    data.turnstile_token.length > 2048
  )
    return "Completa la comprobación antispam antes de enviar.";
  return {
    email,
    name: data.name?.trim() || null,
    propertyCount: data.property_count || null,
  };
}

async function consumeAttempt(db, secret, ip, now) {
  const bucket = Math.floor(now / rateWindow);
  const key = await crypto.subtle.importKey(
    "raw",
    new TextEncoder().encode(secret),
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign"],
  );
  const digest = await crypto.subtle.sign(
    "HMAC",
    key,
    new TextEncoder().encode(`waitlist:${bucket}:${ip}`),
  );
  const identifier = Array.from(new Uint8Array(digest), (byte) =>
    byte.toString(16).padStart(2, "0"),
  ).join("");
  const results = await db.batch([
    db
      .prepare("DELETE FROM waitlist_rate_limits WHERE expires_at <= ?")
      .bind(now),
    db
      .prepare(
        `INSERT INTO waitlist_rate_limits (identifier, attempts, expires_at) VALUES (?, 1, ?)
      ON CONFLICT(identifier) DO UPDATE SET attempts = attempts + 1
      WHERE attempts < ? RETURNING attempts`,
      )
      .bind(identifier, (bucket + 1) * rateWindow, maxAttempts),
  ]);
  return results[1]?.results?.length === 1;
}

// Injectable transport/clock are for offline tests only, never request/env-controlled.
export async function handleWaitlistRequest(
  request,
  env,
  { fetchImpl = fetch, now = Date.now } = {},
) {
  if (request.method !== "POST")
    return json(
      405,
      {
        ok: false,
        message: "Este formulario solo admite solicitudes de acceso.",
      },
      { Allow: "POST" },
    );
  try {
    const url = new URL(request.url);
    if (
      url.pathname !== "/api/waitlist" ||
      !allowedOrigins(env).has(url.origin) ||
      request.headers.get("origin") !== url.origin ||
      request.headers.get("sec-fetch-site") === "cross-site"
    )
      return json(403, {
        ok: false,
        message: "Envía la solicitud desde la web de Alquivo.",
      });
    if (
      request.headers
        .get("content-type")
        ?.split(";")[0]
        .trim()
        .toLowerCase() !== "application/json"
    )
      return json(415, {
        ok: false,
        message: "Revisa el formato de la solicitud.",
      });
    const payload = await readPayload(request);
    if (payload.tooLarge)
      return json(413, {
        ok: false,
        message: "La solicitud es demasiado grande.",
      });
    if (payload.invalid)
      return json(400, {
        ok: false,
        message: "Revisa los datos del formulario.",
      });
    const input = validate(payload.value);
    if (typeof input === "string")
      return json(422, { ok: false, message: input });
    const secret = env.TURNSTILE_SECRET_KEY;
    if (
      env.WAITLIST_ENABLED !== "true" ||
      !env.WAITLIST_DB ||
      typeof secret !== "string" ||
      secret.length < 20 ||
      isTurnstileTestKey(secret)
    )
      return json(503, { ok: false, message: unavailable });
    // Cloudflare sets this header; do not trust X-Forwarded-For supplied by clients.
    const ip = request.headers.get("cf-connecting-ip");
    if (!ip || !/^[a-f0-9:.]{3,64}$/i.test(ip))
      return json(503, { ok: false, message: unavailable });
    const time = Math.floor(now() / 1000);
    if (!(await consumeAttempt(env.WAITLIST_DB, secret, ip, time)))
      return json(
        429,
        {
          ok: false,
          message:
            "Has enviado varias solicitudes. Espera unos minutos antes de intentarlo de nuevo.",
        },
        { "Retry-After": String(rateWindow) },
      );
    let verification;
    try {
      const response = await fetchImpl(
        "https://challenges.cloudflare.com/turnstile/v0/siteverify",
        {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            secret,
            response: payload.value.turnstile_token,
          }),
          signal: AbortSignal.timeout(8000),
        },
      );
      if (!response.ok) return json(503, { ok: false, message: unavailable });
      verification = await response.json();
    } catch {
      return json(503, { ok: false, message: unavailable });
    }
    if (
      verification?.success !== true ||
      verification.hostname !== url.hostname ||
      verification.action !== waitlistAction
    )
      return json(422, {
        ok: false,
        message:
          "La comprobación antispam ha caducado o no es válida. Inténtalo de nuevo.",
      });
    const expiry = new Date(time * 1000);
    expiry.setUTCFullYear(expiry.getUTCFullYear() + 1);
    await env.WAITLIST_DB.batch([
      env.WAITLIST_DB.prepare(
        "DELETE FROM waitlist_signups WHERE expires_at <= ?",
      ).bind(time),
      env.WAITLIST_DB.prepare(
        `INSERT INTO waitlist_signups
        (id, email, name, property_count, consent_version, consent_text, created_at, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON CONFLICT(email) DO NOTHING`,
      ).bind(
        crypto.randomUUID(),
        input.email,
        input.name,
        input.propertyCount,
        waitlistConsent.version,
        waitlistConsent.text,
        time,
        Math.floor(expiry.getTime() / 1000),
      ),
    ]);
    // Same answer for new/existing email: no enumeration or overwrite of an existing signup.
    return json(200, { ok: true, message: waitlistSuccess });
  } catch {
    // Do not return/log SQL, names, addresses, tokens or credentials.
    return json(503, { ok: false, message: unavailable });
  }
}
