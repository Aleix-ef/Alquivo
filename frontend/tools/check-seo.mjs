// npm run build && npm run test:seo
// SEO_BASE_URL=http://localhost:8080 npm run test:seo additionally checks nginx.
import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import { paths, settings, betaProgram } from "../dist-ssr/entry-public.js";
import { publicPages } from "../src/seo.js";
import { legalPages, legalVersion } from "../src/content/legal.js";

const backendLegal = await readFile(
  new URL("../../backend/config/legal.php", import.meta.url),
  "utf8",
);
assert.ok(
  backendLegal.includes(`'terms_version' => '${legalVersion}'`),
  "Frontend and backend accept the same legal version",
);
const publicPaths = new Set(publicPages.map((page) => page.path));
for (const path of paths) {
  const file =
    path === "/"
      ? "index.html"
      : path === "/404"
        ? "404.html"
        : `${path.slice(1)}/index.html`;
  const html = await readFile(
    new URL(`../dist/${file}`, import.meta.url),
    "utf8",
  );
  assert.equal(
    (html.match(/<h1[\s>]/g) || []).length,
    1,
    `${path}: one visible main heading without JS`,
  );
  assert.match(html, /<html lang="es"/);
  assert.doesNotMatch(html, /<!--public-content-->|<!--seo-start-->/);
  assert.equal((html.match(/<title>/g) || []).length, 1);
  assert.equal((html.match(/name="description"/g) || []).length, 1);
  assert.equal((html.match(/name="robots"/g) || []).length, 1);
  const indexable = settings.indexable && publicPaths.has(path);
  assert.ok(
    html.includes(
      `name="robots" content="${indexable ? "index, follow" : "noindex, nofollow"}`,
    ),
    `${path}: correct robots`,
  );
  const canonicals = [...html.matchAll(/rel="canonical" href="([^"]+)"/g)];
  assert.equal(
    canonicals.length,
    publicPaths.has(path) && settings.origin ? 1 : 0,
  );
  if (canonicals.length) assert.equal(canonicals[0][1], settings.origin + path);
  for (const script of html.matchAll(
    /<script\b([^>]*)>([\s\S]*?)<\/script>/gi,
  )) {
    assert.ok(
      /\bsrc=/.test(script[1]) || !script[2].trim(),
      "No inline script; preserve strict CSP",
    );
  }
  for (const asset of html.matchAll(/(?:src|href)="(\/assets\/[^"]+)"/g)) {
    await readFile(new URL(`../dist${asset[1]}`, import.meta.url));
  }
  const legalPage = legalPages.find((page) => page.path === path);
  if (legalPage) {
    assert.ok(html.includes(legalPage.title), `${path}: legal title rendered`);
    assert.ok(html.includes(legalVersion), `${path}: legal version rendered`);
    for (const section of legalPage.sections)
      assert.ok(
        html.includes(`id="${section.id}"`),
        `${path}: section anchor exists`,
      );
    for (const page of legalPages)
      assert.ok(
        html.includes(`href="${page.path}"`),
        `${path}: legal navigation`,
      );
  }
  if (path.startsWith("/guias/")) {
    assert.match(html, /itemtype="https:\/\/schema.org\/Article"/);
    assert.match(html, /itemprop="articleBody"/);
  }
  if (path === "/") {
    assert.match(html, /schema.org\/SoftwareApplication/);
    if (betaProgram) assert.match(html, /Beta gratuita/);
    else assert.doesNotMatch(html, /Beta gratuita/);
    assert.doesNotMatch(html, /Pack fundador|6,99|Creator Founder/);
    for (const page of publicPages.filter((page) => page.article))
      assert.ok(html.includes(`href="${page.path}"`));
  }
}
const sitemap = await readFile(
  new URL("../dist/sitemap.xml", import.meta.url),
  "utf8",
);
assert.equal(
  (sitemap.match(/<loc>/g) || []).length,
  settings.indexable ? publicPaths.size : 0,
);
assert.doesNotMatch(
  sitemap,
  /localhost|example|login|properties|documents|plans|privacy|terms/,
);
const robots = await readFile(
  new URL("../dist/robots.txt", import.meta.url),
  "utf8",
);
assert.ok(
  settings.indexable
    ? robots.includes(`Sitemap: ${settings.origin}/sitemap.xml`)
    : robots.includes("Disallow: /\n"),
);
const shell = await readFile(
  new URL("../dist/app.html", import.meta.url),
  "utf8",
);
assert.match(shell, /noindex, nofollow/);
assert.doesNotMatch(shell, /rel="canonical"|Beta gratuita|itemtype=/);
console.log(
  `PASS: ${paths.length} public/static pages, metadata, real content, schema, CSP, private shell and sitemap.`,
);

if (process.env.SEO_BASE_URL) {
  const base = new URL(process.env.SEO_BASE_URL).origin;
  async function request(path) {
    return fetch(base + path, {
      redirect: "manual",
      signal: AbortSignal.timeout(10000),
    });
  }
  for (const path of [
    ...publicPaths,
    "/login",
    "/register",
    "/dashboard",
    "/support/inbox",
    "/properties/1",
    ...legalPages.map((page) => page.path),
    "/robots.txt",
    "/sitemap.xml",
  ]) {
    const response = await request(path);
    assert.equal(response.status, 200, path);
    assert.equal(response.headers.get("referrer-policy"), "same-origin");
    assert.match(
      response.headers.get("content-security-policy"),
      /script-src 'self';/,
    );
    assert.ok(
      response.headers
        .get("x-robots-tag")
        ?.startsWith(
          settings.indexable && publicPaths.has(path) ? "index," : "noindex,",
        ),
      `${path}: HTTP indexing header`,
    );
    if (publicPaths.has(path)) assert.match(await response.text(), /<h1[\s>]/);
  }
  for (const path of [
    "/pagina-inexistente",
    "/guias/inexistente",
    "/app.html",
    "/guias/index.html",
  ]) {
    const response = await request(path);
    assert.equal(response.status, 404, path);
    assert.match(await response.text(), /Este espacio no existe/);
    assert.match(response.headers.get("x-robots-tag"), /^noindex,/);
  }
  for (const path of [...publicPaths].filter((path) => path !== "/")) {
    const response = await request(`${path}/?utm_source=test`);
    assert.equal(response.status, 308, path);
    assert.equal(response.headers.get("location"), `${path}?utm_source=test`);
  }
  const withQuery = await request("/?utm_source=test");
  assert.equal(withQuery.status, 200);
  assert.ok(
    withQuery.headers
      .get("x-robots-tag")
      ?.startsWith(settings.indexable ? "index," : "noindex,"),
  );
  assert.equal((await request("/api/v1/auth/me")).status, 401);
  console.log(
    "PASS: HTTP statuses, redirects, private routes, crawler headers and session boundary.",
  );
}
