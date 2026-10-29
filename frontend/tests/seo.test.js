import { test } from "node:test";
import assert from "node:assert/strict";
import { seoSettings, pageSeo, publicPages, renderHead } from "../src/seo.js";
import { guides } from "../src/content/guides.js";

test("the app home shares the concise public brand title", () => {
  assert.equal(
    pageSeo("/", { origin: "https://alquivo.com", indexable: true }).title,
    "Alquivo | Tus alquileres en orden",
  );
});

test("public sharing uses the existing brand image, never private page or token URLs", () => {
  const settings = { origin: "https://alquivo.com", indexable: true };
  for (const page of publicPages) {
    const seo = pageSeo(page.path, settings);
    assert.equal(seo.image, "https://alquivo.com/brand/alquivo-social.png");
    const head = renderHead(seo);
    assert.match(head, /property="og:image"/);
    assert.match(head, /name="twitter:image"/);
    assert.match(head, /property="og:image:width" content="1080"/);
  }
  for (const path of [
    "/login",
    "/documents",
    "/reset-password?token=secret",
    "/unknown",
  ]) {
    const seo = pageSeo(path, settings);
    assert.equal(seo.image, "");
    assert.doesNotMatch(renderHead(seo), /og:image|twitter:image|token=secret/);
  }
  assert.equal(pageSeo("/", { origin: "", indexable: false }).image, "");
  assert.doesNotMatch(
    pageSeo("/", settings).description,
    /en preparación|en revisión/,
  );
});

test("SEO is opt-in and refuses local, placeholder or unsafe canonical origins", () => {
  assert.deepEqual(seoSettings(), { origin: "", indexable: false });
  assert.throws(() => seoSettings({ VITE_SEO_INDEXABLE: "true" }));
  for (const origin of [
    "http://alquivo.com",
    "https://localhost",
    "https://127.0.0.1",
    "https://alquivo.example",
    "https://example.test",
    "https://alquivo.com/path",
    "https://user:pass@alquivo.com",
    "https://alquivo.com?q=1",
    "https://alquivo.com#home",
    "https://alquivo.com:8080",
  ]) {
    assert.throws(() => seoSettings({ VITE_PUBLIC_SITE_URL: origin }), origin);
  }
  assert.deepEqual(
    seoSettings({
      VITE_PUBLIC_SITE_URL: "https://alquivo.com/",
      VITE_SEO_INDEXABLE: "true",
    }),
    { origin: "https://alquivo.com", indexable: true },
  );
});

test("only the explicit public content allowlist can be indexed or canonicalized", () => {
  const settings = { origin: "https://alquivo.com", indexable: true };
  for (const path of [
    "/login",
    "/register",
    "/dashboard",
    "/properties/1",
    "/documents",
    "/plans",
    "/privacy",
    "/terms",
    "/reset-password",
    "/nonexistent",
    "/?token=secret",
  ]) {
    const seo = pageSeo(path, settings);
    assert.equal(seo.robots, "noindex, nofollow", path);
    assert.equal(seo.canonical, "", path);
  }
  for (const page of publicPages) {
    assert.equal(
      pageSeo(page.path, settings).canonical,
      settings.origin + page.path,
    );
    assert.match(pageSeo(page.path, settings).robots, /^index,/);
    assert.match(
      pageSeo(page.path, { ...settings, indexable: false }).robots,
      /^noindex,/,
    );
  }
});

test("distinct descriptive metadata and guides stay in sync", () => {
  assert.equal(
    new Set(publicPages.map((page) => page.title)).size,
    publicPages.length,
  );
  assert.equal(
    new Set(publicPages.map((page) => page.description)).size,
    publicPages.length,
  );
  for (const guide of guides) {
    assert.ok(
      publicPages.some((page) => page.path === guide.path && page.article),
    );
    assert.ok(guide.sections.length >= 5);
    assert.ok(guide.summary.length > 100);
  }
});

test("head escapes text and never introduces inline scripts or arbitrary attributes", () => {
  const seo = pageSeo("/", { origin: "https://alquivo.com", indexable: true });
  const head = renderHead({
    ...seo,
    title: '</title><script>alert("x")</script>',
    description: '" onload="alert(1)',
  });
  assert.doesNotMatch(head, /<script|\s+onload="/);
  assert.match(head, /&lt;script&gt;/);
  assert.equal((head.match(/rel="canonical"/g) || []).length, 1);
  assert.match(head, /property="og:locale" content="es_ES"/);
});
