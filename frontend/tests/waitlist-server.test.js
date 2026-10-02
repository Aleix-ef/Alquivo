import { beforeEach, afterEach, test } from "node:test";
import assert from "node:assert/strict";
import { createServer } from "node:http";
import { once } from "node:events";
import { handleWaitlistRequest } from "../waitlist-server/handler.js";
import { waitlistAction, waitlistConsent } from "../src/waitlist/contract.js";
import { testWaitlistDb } from "./support/waitlist-db.js";

let db, env, calls, now;
const validInput = () => ({
  email: " TEST+beta@example.com ",
  name: " Ana Pérez ",
  property_count: "2-5",
  website: "",
  consent: true,
  consent_version: waitlistConsent.version,
  turnstile_token: "synthetic-token",
});
const headers = {
  "Content-Type": "application/json",
  Origin: "https://alquivo.com",
  "cf-connecting-ip": "192.0.2.40",
};
const request = (data = validInput(), options = {}) =>
  new Request(options.url || "https://alquivo.com/api/waitlist", {
    method: options.method || "POST",
    headers: { ...headers, ...options.headers },
    ...((options.method || "POST") === "POST"
      ? { body: options.body ?? JSON.stringify(data) }
      : {}),
  });
const verified = (overrides = {}) => ({
  success: true,
  hostname: "alquivo.com",
  action: waitlistAction,
  ...overrides,
});
const transport =
  (result = verified()) =>
  async (url, options) => {
    calls.push({ url, body: JSON.parse(options.body) });
    return Response.json(result);
  };
const send = (req, fetchImpl = transport()) =>
  handleWaitlistRequest(req, env, { fetchImpl, now: () => now });

beforeEach(async () => {
  db = await testWaitlistDb();
  env = {
    WAITLIST_DB: db,
    WAITLIST_ENABLED: "true",
    TURNSTILE_SECRET_KEY: "synthetic-secret-for-offline-tests-only",
  };
  calls = [];
  now = Date.UTC(2026, 9, 2, 12);
});
afterEach(() => db.close());

test("valid signup persists consent evidence and minimum data using real SQLite", async () => {
  const response = await send(request());
  assert.equal(response.status, 200);
  assert.equal((await response.json()).ok, true);
  const [signup] = db.signups();
  assert.equal(signup.email, "test+beta@example.com");
  assert.equal(signup.name, "Ana Pérez");
  assert.equal(signup.property_count, "2-5");
  assert.equal(signup.consent_version, waitlistConsent.version);
  assert.equal(signup.consent_text, waitlistConsent.text);
  assert.equal(signup.created_at, now / 1000);
  assert.equal(signup.expires_at, Date.UTC(2027, 9, 2, 12) / 1000);
  assert.match(signup.id, /^[a-f0-9-]{36}$/);
  assert.deepEqual(calls, [
    {
      url: "https://challenges.cloudflare.com/turnstile/v0/siteverify",
      body: {
        secret: env.TURNSTILE_SECRET_KEY,
        response: "synthetic-token",
      },
    },
  ]);
  assert.doesNotMatch(
    JSON.stringify(db.rates()),
    /192\.0\.2\.40|Ana|example\.com|synthetic-secret/,
  );
});
test("optional fields can be omitted", async () => {
  const data = validInput();
  delete data.name;
  delete data.property_count;
  delete data.website;
  assert.equal((await send(request(data))).status, 200);
  assert.equal(db.signups()[0].name, null);
  assert.equal(db.signups()[0].property_count, null);
});

for (const [label, changes, expected] of [
  ["no consent", { consent: false }, /consentimiento/],
  ["forged string consent", { consent: "true" }, /consentimiento/],
  ["missing consent", { consent: undefined }, /consentimiento/],
  ["stale consent", { consent_version: "old" }, /Actualiza/],
  ["invalid email", { email: "not-an-email" }, /email válido/],
  [
    "header injection email",
    { email: "test@example.com\r\nBCC:evil@example.com" },
    /email válido/,
  ],
  ["invalid domain", { email: "test@-example.com" }, /email válido/],
  ["numeric email", { email: 123 }, /email válido/],
  ["long email", { email: `${"x".repeat(65)}@example.com` }, /email válido/],
  ["long name", { name: "a".repeat(101) }, /nombre/],
  ["HTML in name", { name: "<script>alert(1)</script>" }, /nombre/],
  ["invalid optional name", { name: [] }, /nombre/],
  ["unexpected range", { property_count: "10000" }, /rangos/],
  ["numeric range", { property_count: 1 }, /rangos/],
  ["honeypot", { website: "spam" }, /validar/],
  ["missing token", { turnstile_token: "" }, /antispam/],
  ["oversized token", { turnstile_token: "x".repeat(2049) }, /antispam/],
  ["unexpected field", { is_admin: true }, /Revisa/],
])
  test(`rejects ${label} before database/provider access`, async () => {
    const response = await send(request({ ...validInput(), ...changes }));
    assert.equal(response.status, 422);
    assert.match((await response.json()).message, expected);
    assert.equal(calls.length, 0);
    assert.equal(db.signups().length, 0);
    assert.equal(db.rates().length, 0);
  });

for (const method of ["GET", "HEAD", "DELETE", "OPTIONS"])
  test(`does not expose/read the waitlist through ${method}`, async () => {
    const response = await send(request(undefined, { method }));
    assert.equal(response.status, 405);
    assert.equal(response.headers.get("Allow"), "POST");
    assert.equal(calls.length, 0);
  });

for (const options of [
  { headers: { Origin: "https://evil.example" } },
  { headers: { Origin: "null" } },
  {
    url: "https://alquivo.com.evil.example/api/waitlist",
    headers: { Origin: "https://alquivo.com.evil.example" },
  },
  {
    url: "http://alquivo.com/api/waitlist",
    headers: { Origin: "http://alquivo.com" },
  },
  {
    url: "https://arbitrary.pages.dev/api/waitlist",
    headers: { Origin: "https://arbitrary.pages.dev" },
  },
  { headers: { "Sec-Fetch-Site": "cross-site" } },
  { url: "https://alquivo.com/api/waitlist/extra" },
])
  test(`rejects cross-origin/unconfigured host: ${JSON.stringify(options)}`, async () => {
    assert.equal((await send(request(undefined, options))).status, 403);
    assert.equal(calls.length, 0);
    assert.equal(db.signups().length, 0);
  });
test("Origin header is mandatory", async () => {
  const req = request();
  req.headers.delete("Origin");
  assert.equal((await send(req)).status, 403);
});
test("explicit Pages production hostname works without permitting arbitrary previews", async () => {
  env.WAITLIST_PAGES_HOSTNAME = "alquivo-landing.pages.dev";
  const req = request(undefined, {
    url: "https://alquivo-landing.pages.dev/api/waitlist",
    headers: { Origin: "https://alquivo-landing.pages.dev" },
  });
  assert.equal(
    (
      await send(
        req,
        transport(verified({ hostname: "alquivo-landing.pages.dev" })),
      )
    ).status,
    200,
  );
  assert.equal(
    (
      await send(
        request(undefined, {
          url: "https://branch.alquivo-landing.pages.dev/api/waitlist",
          headers: { Origin: "https://branch.alquivo-landing.pages.dev" },
        }),
      )
    ).status,
    403,
  );
});
test("invalid JSON/content type and chunked oversized bodies fail in a controlled way", async () => {
  assert.equal((await send(request(null, { body: "{broken" }))).status, 400);
  assert.equal((await send(request(null, { body: "null" }))).status, 422);
  assert.equal((await send(request(null, { body: "[]" }))).status, 422);
  assert.equal(
    (
      await send(
        request(undefined, {
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
        }),
      )
    ).status,
    415,
  );
  assert.equal(
    (await send(request(undefined, { body: "a".repeat(4097) }))).status,
    413,
  );
  assert.equal(
    (await send(request(undefined, { headers: { "Content-Length": "5000" } })))
      .status,
    413,
  );
  assert.equal(calls.length, 0);
});

for (const changes of [
  { WAITLIST_ENABLED: "false" },
  { WAITLIST_ENABLED: undefined },
  { WAITLIST_DB: undefined },
  { TURNSTILE_SECRET_KEY: undefined },
  { TURNSTILE_SECRET_KEY: "1x0000000000000000000000000000000AA" },
  { TURNSTILE_SECRET_KEY: "2x0000000000000000000000000000000AA" },
  { TURNSTILE_SECRET_KEY: "3x0000000000000000000000000000000AA" },
  { WAITLIST_PAGES_HOSTNAME: "arbitrary.evil.example" },
])
  test(`fails closed for invalid/unconfigured runtime: ${Object.keys(changes).join()}`, async () => {
    Object.assign(env, changes);
    const response = await send(request());
    assert.equal(response.status, 503);
    assert.equal(calls.length, 0);
    assert.equal(db.signups().length, 0);
  });
test("missing trusted IP cannot bypass rate limiting", async () => {
  const req = request();
  req.headers.delete("cf-connecting-ip");
  req.headers.set("X-Forwarded-For", "192.0.2.99");
  assert.equal((await send(req)).status, 503);
  assert.equal(calls.length, 0);
});

for (const validation of [
  { success: false },
  { success: "true" },
  { hostname: "evil.example" },
  { action: "another_form" },
])
  test(`rejects invalid Turnstile result: ${JSON.stringify(validation)}`, async () => {
    assert.equal(
      (await send(request(), transport(verified(validation)))).status,
      422,
    );
    assert.equal(db.signups().length, 0);
  });
test("Turnstile timeout/non-JSON/HTTP failure never creates a signup", async () => {
  for (const fetchImpl of [
    async () => {
      throw new Error("secret stack");
    },
    async () => new Response("upstream error", { status: 503 }),
    async () => new Response("<html>bad</html>"),
  ]) {
    const response = await send(request(), fetchImpl);
    assert.equal(response.status, 503);
    assert.doesNotMatch(
      await response.text(),
      /secret stack|upstream error|<html>/,
    );
  }
  assert.equal(db.signups().length, 0);
});
test("duplicates return the same response and never overwrite or extend the original consent", async () => {
  const first = await send(request());
  const original = { ...db.signups()[0] };
  now += 601000;
  const repeat = await send(
    request({
      ...validInput(),
      email: "test+beta@EXAMPLE.com",
      name: "Attacker",
      property_count: "11+",
    }),
  );
  assert.equal(repeat.status, 200);
  assert.deepEqual(await repeat.json(), await first.json());
  assert.equal(db.signups().length, 1);
  assert.deepEqual({ ...db.signups()[0] }, original);
});
test("rate cap is atomic/persistent, rotates by window and does not call Siteverify after the cap", async () => {
  for (let i = 0; i < 10; i++)
    assert.equal((await send(request())).status, 200);
  const over = await send(request());
  assert.equal(over.status, 429);
  assert.equal(over.headers.get("Retry-After"), "600");
  assert.equal(calls.length, 10);
  assert.equal(db.rates()[0].attempts, 10);
  const oldIdentifier = db.rates()[0].identifier;
  now += 601000;
  assert.equal((await send(request())).status, 200);
  assert.equal(db.rates().length, 1);
  assert.notEqual(db.rates()[0].identifier, oldIdentifier);
});
test("SQL injection cannot alter schema and names are parameter-bound", async () => {
  assert.equal(
    (
      await send(
        request({
          ...validInput(),
          name: "Ana'); DROP TABLE waitlist_signups; --",
        }),
      )
    ).status,
    200,
  );
  assert.equal(db.signups()[0].name, "Ana'); DROP TABLE waitlist_signups; --");
  assert.equal(
    (await send(request({ ...validInput(), email: "other@example.com" })))
      .status,
    200,
  );
  assert.equal(db.signups().length, 2);
});
test("expired signups are purged before storing a new verified request", async () => {
  assert.equal((await send(request())).status, 200);
  now = Date.UTC(2027, 9, 3, 12);
  assert.equal(
    (await send(request({ ...validInput(), email: "new@example.com" }))).status,
    200,
  );
  assert.equal(db.signups().length, 1);
  assert.equal(db.signups()[0].email, "new@example.com");
});
test("database failures are safe, uncached, no CORS and never report success", async () => {
  env.WAITLIST_DB = {
    prepare() {
      throw new Error("SQL secret customer data");
    },
  };
  const response = await send(request());
  assert.equal(response.status, 503);
  assert.equal((await response.json()).ok, false);
  assert.equal(response.headers.get("Cache-Control"), "no-store");
  assert.equal(response.headers.get("Access-Control-Allow-Origin"), null);
});
test("write failure after successful anti-spam validation never claims receipt", async () => {
  let batches = 0;
  env.WAITLIST_DB = {
    prepare: (sql) => db.prepare(sql),
    async batch(statements) {
      if (++batches === 2) throw new Error("private SQL details");
      return db.batch(statements);
    },
  };
  const response = await send(request());
  assert.equal(response.status, 503);
  assert.equal(calls.length, 1);
  assert.equal(db.signups().length, 0);
  assert.doesNotMatch(await response.text(), /private SQL details/);
});

test("expiry cleanup uses indexes rather than scanning the complete waitlist", () => {
  const plan = db.sqlite
    .prepare(
      "EXPLAIN QUERY PLAN DELETE FROM waitlist_signups WHERE expires_at <= ?",
    )
    .all(now / 1000);
  assert.match(JSON.stringify(plan), /idx_waitlist_signups_expires_at/);
  const ratePlan = db.sqlite
    .prepare(
      "EXPLAIN QUERY PLAN DELETE FROM waitlist_rate_limits WHERE expires_at <= ?",
    )
    .all(now / 1000);
  assert.match(JSON.stringify(ratePlan), /idx_waitlist_rate_limits_expires_at/);
});

test("HTTP round-trip uses the same production handler and real SQLite, with synthetic anti-spam transport", async () => {
  const server = createServer(async (incoming, outgoing) => {
    const req = new Request(`https://alquivo.com${incoming.url}`, {
      method: incoming.method,
      headers: incoming.headers,
      ...(incoming.method === "POST" ? { body: incoming, duplex: "half" } : {}),
    });
    const response = await send(req);
    outgoing.writeHead(response.status, Object.fromEntries(response.headers));
    outgoing.end(await response.text());
  });
  server.listen(0, "127.0.0.1");
  await once(server, "listening");
  try {
    const url = `http://127.0.0.1:${server.address().port}/api/waitlist`;
    const post = await fetch(url, {
      method: "POST",
      headers,
      body: JSON.stringify(validInput()),
    });
    assert.equal(post.status, 200);
    assert.equal((await post.json()).ok, true);
    assert.equal(db.signups().length, 1);
    assert.equal((await fetch(url)).status, 405);
    assert.equal(
      (
        await fetch(url, {
          method: "POST",
          headers: { ...headers, Origin: "https://evil.example" },
          body: JSON.stringify(validInput()),
        })
      ).status,
      403,
    );
  } finally {
    server.closeAllConnections();
    await new Promise((resolve) => server.close(resolve));
  }
});
