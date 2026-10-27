import { test } from "node:test";
import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import { publicRoutes } from "../src/publicRoutes.js";
import { pageSeo } from "../src/seo.js";

const source = (file) => readFile(new URL(file, import.meta.url), "utf8");

test("beta landing sends visitors to registration, not the retired waitlist", async () => {
  const page = await source("../src/views/MarketingView.vue");
  assert.match(page, /waitlistMode \? "\/#solicitud" : "\/register"/);
  assert.match(page, /waitlistMode \? undefined : 'solicitud'/);
  assert.match(page, /to="\/login"/);
  assert.doesNotMatch(page, /WaitlistForm|turnstile|\/api\/waitlist/i);
  const seo = pageSeo("/", { indexable: true, origin: "https://alquivo.com" });
  assert.equal(seo.canonical, "https://alquivo.com/");
  assert.match(seo.description, /50 inmuebles y 5 GB/);
  assert.doesNotMatch(seo.description, /preparación|lista de espera/);
});

test("the historical waitlist notice remains readable but not indexed", async () => {
  assert.ok(publicRoutes.some((route) => route.path === "/privacidad"));
  assert.equal(
    pageSeo("/privacidad", { indexable: true, origin: "https://alquivo.com" })
      .robots,
    "noindex, nofollow",
  );
  const historical = await source("../src/views/LegacyWaitlistPrivacyView.vue");
  assert.match(historical, /WaitlistLegalView privacy/);
  assert.match(historical, /antigua lista de espera/);
  assert.match(historical, /to="\/privacy"/);
  const nginx = await source("../nginx.conf");
  assert.match(
    nginx,
    /location = \/aviso-legal \{ return 308 \/legal\$is_args\$args; \}/,
  );
});

test("the proxy retains the old host without redirecting signed or private URLs", async () => {
  const caddy = await source("../../ops/Caddyfile");
  assert.match(
    caddy,
    /\{\$PUBLIC_DOMAIN\} \{\$LEGACY_APP_DOMAIN:app\.alquivo\.com\}/,
  );
  assert.match(caddy, /header @legacy >X-Robots-Tag "noindex, nofollow"/);
  assert.doesNotMatch(caddy, /\bredir\b|access_log|admin (?!off)/);
  assert.match(caddy, /reverse_proxy web:80/);
});
