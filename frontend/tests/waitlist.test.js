import { before, after, test } from "node:test";
import assert from "node:assert/strict";
import {
  readFile,
  mkdtemp,
  readdir,
  rm,
  stat,
  writeFile,
} from "node:fs/promises";
import { join } from "node:path";
import { tmpdir } from "node:os";
import { waitlistConsent } from "../src/waitlist/contract.js";
import { waitlistSettings, waitlistPages } from "../src/waitlist/settings.js";
import {
  buildWaitlist,
  landingHeaders,
  verifyWaitlistOutput,
} from "../tools/build-waitlist.mjs";

// Build-only synthetic key. No network/provider calls or deployment.
const siteKey = "synthetic_site_key_build_only_123";
const env = {
  LANDING_TURNSTILE_SITE_KEY: siteKey,
  TURNSTILE_SECRET_KEY: "SECRET_MUST_NEVER_BE_IN_HTML",
};
let temporary, production, preview;
before(async () => {
  temporary = await mkdtemp(join(tmpdir(), "alquivo-landing-test-"));
  production = await buildWaitlist({
    env,
    outputDir: join(temporary, "production"),
  });
  preview = await buildWaitlist({
    env: {},
    preview: true,
    outputDir: join(temporary, "preview"),
  });
});
after(async () => {
  if (temporary) await rm(temporary, { recursive: true, force: true });
});
const html = (dir, path = "index.html") => readFile(join(dir, path), "utf8");

test("release requires a public Turnstile key; preview remains honest", () => {
  assert.throws(() => waitlistSettings({}), /LANDING_TURNSTILE_SITE_KEY/);
  assert.deepEqual(waitlistSettings({}, { preview: true }), {
    siteKey: "",
    origin: "https://alquivo.com",
    preview: true,
    indexable: false,
  });
});
test("settings expose only explicitly allowlisted public values", () => {
  assert.equal(waitlistSettings(env).siteKey, siteKey);
  assert.doesNotMatch(
    JSON.stringify(waitlistSettings(env)),
    /SECRET_MUST_NEVER_BE_IN_HTML|TALLY/,
  );
});
for (const key of [
  "short",
  "<script>injected</script>",
  "https://evil.example/script.js",
  "1x00000000000000000000AA",
  "2x00000000000000000000AB",
  "1x00000000000000000000BB",
  "2x00000000000000000000BB",
  "3x00000000000000000000FF",
])
  test(`rejects an invalid/test site key in production: ${key}`, () => {
    assert.throws(() => waitlistSettings({ LANDING_TURNSTILE_SITE_KEY: key }));
  });
for (const url of [
  "http://alquivo.com",
  "https://alquivo.com/private",
  "https://localhost",
  "https://127.0.0.1",
  "https://user:secret@alquivo.com",
  "https://alquivo.com:4433",
  "https://alquivo.com/?email=private",
])
  test(`rejects an invalid public origin: ${url}`, () => {
    assert.throws(() => waitlistSettings({ ...env, LANDING_SITE_URL: url }));
  });
test("production includes the beta request, but no account or immediate access", async () => {
  const page = await html(production.output);
  assert.match(page, /Tus alquileres, en orden/);
  assert.match(page, /Solicita acceso a la beta/);
  assert.match(page, /id="solicitud"/);
  assert.match(page, /href="\/#solicitud"/);
  assert.match(page, /Apuntarte no crea una cuenta ni da acceso inmediato/);
  assert.match(page, /Habitaciones|habitaciones/);
  assert.doesNotMatch(
    page,
    /href="\/(login|register|guias|finance|properties)|\/api\/v1|\/sanctum/,
  );
  assert.doesNotMatch(
    page,
    /Empieza gratis|Probar la beta gratis|Asistente IA disponible/,
  );
});
test("every signup CTA targets the form card, not its preceding introduction", async () => {
  for (const output of [production.output, preview.output]) {
    const page = await html(output);
    const target = page.match(/<[^>]+\bid="solicitud"[^>]*>/g);
    assert.equal(target?.length, 1);
    assert.match(target[0], /^<div\b/);
    assert.match(target[0], /class="waitlist-card"/);
    assert.match(target[0], /tabindex="-1"/);
    assert.match(
      page,
      /<h2 id="waitlist-form-title">Solicita acceso a la beta<\/h2>/,
    );
    const card = page.slice(page.indexOf(target[0]));
    assert.ok(card.indexOf('id="waitlist-form"') > 0);
    assert.ok(card.indexOf('id="waitlist-email"') > 0);
    assert.doesNotMatch(card, /Menos Excel/);
    assert.ok(Array.from(page.matchAll(/href="\/#solicitud"/g)).length >= 3);
  }
});
test("mobile compaction is scoped to the waitlist and preserves the form and core content", async () => {
  const source = await readFile(
    new URL("../src/waitlist/waitlist.css", import.meta.url),
    "utf8",
  );
  const mobile = source.slice(
    source.indexOf("@media (max-width: 680px)"),
    source.indexOf("@media (max-width: 400px)"),
  );
  assert.ok(mobile.length > 0);
  assert.match(
    mobile,
    /\.waitlist-page \.product-preview,[\s\S]*?\.waitlist-intro\s*\{\s*display: none/,
  );
  assert.doesNotMatch(
    mobile,
    /\.(ai-preview-section|feature-section|plans-section|faq-section|waitlist-card|waitlist-form-area)\s*\{\s*display: none/,
  );
  assert.match(
    mobile,
    /\.waitlist-optional-fields\s*\{\s*grid-template-columns: minmax\(0, 1fr\)/,
  );
  assert.match(mobile, /\.waitlist-support\s*\{\s*position: static/);
  assert.match(source, /\.waitlist-field input,[\s\S]*?font-size: 1rem/);
});
test("the existing logo, dark theme and local fonts are reused", async () => {
  const page = await html(production.output);
  assert.match(page, /data-theme="dark"/);
  assert.match(page, /\/brand\/alquivo-wordmark.svg#wordmark/);
  assert.match(page, /\/favicon.svg\?v=wordmark-1#mark/);
  const assets = await readdir(join(production.output, "assets"));
  assert.equal(assets.filter((name) => name.endsWith(".woff2")).length, 2);
  const css = await readFile(
    join(
      production.output,
      "assets",
      assets.find((name) => name.endsWith(".css")),
    ),
    "utf8",
  );
  assert.doesNotMatch(css, /https?:\/\//);
  assert.match(css, /prefers-reduced-motion/);
});
test("native form matches the brand, requires consent and has a no-JavaScript contact fallback", async () => {
  const page = await html(production.output);
  assert.match(page, /<form id="waitlist-form"/);
  assert.match(page, /action="\/api\/waitlist" method="post"/);
  assert.match(page, /name="email" type="email"[^>]* required/);
  const checkbox = page.match(/<input[^>]*id="waitlist-consent"[^>]*>/)[0];
  assert.match(checkbox, /required/);
  assert.doesNotMatch(checkbox, /checked/);
  assert.match(
    page,
    new RegExp(waitlistConsent.version.replaceAll(".", "\\.")),
  );
  assert.match(page, /name="property_count"/);
  assert.match(page, /<noscript>/);
  assert.match(page, /mailto:soporte@alquivo.com/);
  assert.match(page, /<script src="\/assets\/form.js" defer><\/script>/);
  assert.doesNotMatch(
    page,
    /Tally|tally\.so|<iframe|<script src="https:|SECRET_MUST_NEVER_BE_IN_HTML/,
  );
  assert.match(page, /id="waitlist-success"[^>]* hidden/);
});
test("preview shows the native form but cannot send or claim success", async () => {
  const page = await html(preview.output);
  assert.match(page, /Vista previa: el envío no está activado/);
  assert.match(page, /id="waitlist-submit"[^>]* disabled/);
  assert.match(page, /data-preview="true"/);
  assert.doesNotMatch(page, /<script/);
  assert.match(page, /content="noindex, follow"/);
});
test("all local links and anchors resolve to public output", async () => {
  for (const page of waitlistPages) {
    const relative =
      page.path === "/"
        ? "index.html"
        : page.path === "/404"
          ? "404.html"
          : `${page.path.slice(1)}/index.html`;
    const body = await html(production.output, relative);
    for (const [, href] of body.matchAll(/href="([^"]+)"/g)) {
      if (href.startsWith("mailto:") || href.startsWith("https:")) continue;
      const url = new URL(
        href.replaceAll("&amp;", "&"),
        `https://alquivo.com${page.path}`,
      );
      let target = join(production.output, url.pathname);
      if ((await stat(target)).isDirectory())
        target = join(target, "index.html");
      assert.ok((await stat(target)).isFile(), `${relative} -> ${href}`);
      if (url.hash) {
        const content = await readFile(target, "utf8");
        assert.ok(
          content.includes(`id="${url.hash.slice(1)}"`),
          `${relative} -> ${href}`,
        );
      }
    }
  }
});
test("the artifact cannot contain app code, SSR sources, databases or secrets", async () => {
  const files = await readdir(production.output, { recursive: true });
  assert.equal(files.filter((name) => name.endsWith(".js")).length, 1);
  assert.ok(files.includes("assets/form.js"));
  assert.doesNotMatch(
    files.join("\n"),
    /\.env|\.map|\.php|\.sqlite|node_modules|app.html|entry-waitlist.js|_worker.js|_redirects/,
  );
  await verifyWaitlistOutput(production.output, production.settings);
  const unsafe = join(production.output, "app.html");
  await writeFile(unsafe, "private app");
  await assert.rejects(
    verifyWaitlistOutput(production.output, production.settings),
    /Archivo no publicable/,
  );
  await rm(unsafe);
  const dump = join(production.output, "private.json");
  await writeFile(dump, '{"synthetic_private_data":true}');
  await assert.rejects(
    verifyWaitlistOutput(production.output, production.settings),
    /Archivo no publicable/,
  );
  await rm(dump);
});
test("security headers allow first-party submissions and only Turnstile as external script/frame", async () => {
  const headers = await html(production.output, "_headers");
  assert.match(headers, /frame-src https:\/\/challenges.cloudflare.com/);
  assert.match(headers, /connect-src 'self'/);
  assert.match(headers, /frame-ancestors 'none'/);
  assert.match(headers, /Referrer-Policy: no-referrer/);
  assert.match(headers, /X-Content-Type-Options: nosniff/);
  assert.doesNotMatch(
    landingHeaders["Content-Security-Policy"],
    /unsafe-inline|unsafe-eval/,
  );
});
test("SEO is public only in the release, with the correct domain and no app URLs", async () => {
  const page = await html(production.output);
  assert.match(page, /content="index, follow"/);
  assert.match(page, /rel="canonical" href="https:\/\/alquivo.com\/"/);
  assert.match(page, /Próxima|próxima/);
  const sitemap = await html(production.output, "sitemap.xml");
  assert.equal((sitemap.match(/<loc>/g) || []).length, 1);
  assert.match(sitemap, /<loc>https:\/\/alquivo.com\/<\/loc>/);
  assert.match(await html(preview.output, "robots.txt"), /Disallow: \//);
  for (const path of [
    "privacidad/index.html",
    "aviso-legal/index.html",
    "404.html",
  ])
    assert.match(
      await html(production.output, path),
      /content="noindex, follow"/,
    );
});
test("legal copy describes the waitlist, consent, retention and actual providers", async () => {
  const privacy = await html(production.output, "privacidad/index.html");
  assert.match(privacy, /Aleix Escañuela Fresneda/);
  assert.match(privacy, /21807679E/);
  assert.match(privacy, /soporte@alquivo.com/);
  assert.match(privacy, /artículo 6.1.a/);
  assert.match(privacy, /12 meses desde la solicitud/);
  assert.match(privacy, /Pages Functions/);
  assert.match(privacy, /D1/);
  assert.match(privacy, /Turnstile/);
  assert.match(privacy, /Gmail/);
  assert.match(
    privacy,
    /No enviamos los datos del formulario a OpenAI, Stripe/,
  );
  assert.doesNotMatch(
    privacy,
    /Borrador previo|identificación pendiente|Aceptar todas|Tally|tally.so/,
  );
});
test("only the form endpoint invokes Functions; source/migrations stay outside public output", async () => {
  assert.deepEqual(JSON.parse(await html(production.output, "_routes.json")), {
    version: 1,
    include: ["/api/waitlist"],
    exclude: [],
  });
  const files = await readdir(production.output, { recursive: true });
  assert.doesNotMatch(
    files.join("\n"),
    /functions|migrations|handler|\.sql|\.zip/,
  );
  const source = await readFile(
    new URL("../functions/api/waitlist.js", import.meta.url),
    "utf8",
  );
  assert.match(source, /export function onRequest/);
  assert.match(source, /handleWaitlistRequest\(request, env\)/);
});
