import { test } from "node:test";
import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import { compileScript, parse } from "@vue/compiler-sfc";
import { createSSRApp } from "vue";
import { renderToString } from "vue/server-renderer";
import { documentType, documentTypes } from "../src/documentTypes.js";

// Exercise the real component without a session, document upload or API calls.
const source = await readFile(
  new URL("../src/components/DocumentDateFields.vue", import.meta.url),
  "utf8",
);
const { descriptor } = parse(source);
const { content } = compileScript(descriptor, {
  id: "DocumentDateFields",
  inlineTemplate: true,
});
const moduleSource = content.replace(
  /from (["'])([^"']+)\1/g,
  (match, quote, specifier) => {
    const url =
      specifier === "vue"
        ? import.meta.resolve("vue")
        : new URL(specifier, new URL("../src/components/", import.meta.url))
            .href;
    return `from ${JSON.stringify(url)}`;
  },
);
const { default: component } = await import(
  `data:text/javascript;base64,${Buffer.from(moduleSource).toString("base64")}`
);
const render = (props) => renderToString(createSSRApp(component, props));

test("document categories have shared Spanish labels and a safe fallback", () => {
  assert.deepEqual(
    documentTypes.map(({ value }) => value),
    ["contract", "invoice", "insurance", "tax", "certificate", "other"],
  );
  assert.equal(documentType("invoice").label, "Factura");
  assert.equal(documentType("certificate").plural, "Certificados");
  assert.equal(documentType("unknown").value, "other");
});

test("invoices and tax receipts expose their date directly without a disclosure or an expiry field", async () => {
  for (const category of ["invoice", "tax"]) {
    const html = await render({ category });
    assert.match(html, /^<section\b/);
    assert.doesNotMatch(html, /<details|<summary>/);
    assert.ok(html.includes(documentType(category).issuedLabel));
    assert.match(html, /data-document-date="issued"/);
    assert.doesNotMatch(
      html,
      /data-document-date="expiry"|\brequired(?:[\s=>])/,
    );
    assert.match(html, /Finanzas/);
  }
});

test("contracts, policies and certificates offer their relevant dates without making them mandatory", async () => {
  for (const category of ["contract", "insurance", "certificate"]) {
    const html = await render({ category });
    assert.match(html, /^<section\b/);
    assert.doesNotMatch(html, /<summary>|\brequired(?:[\s=>])/);
    assert.match(html, /data-document-date="issued"/);
    assert.match(html, /data-document-date="expiry"/);
    assert.ok(html.includes(documentType(category).expiryLabel));
  }
});

test("other documents have optional organization and review dates", async () => {
  const html = await render({ category: "other" });
  assert.match(html, /^<section\b/);
  assert.doesNotMatch(html, /<details|<summary>/);
  assert.match(html, /Revisar el documento el/);
});

test("previous invoice dates stay visible and editable, not silently removed by the category", async () => {
  const props = { issuedAt: "2026-11-01", expiresAt: "2026-12-05" };
  for (const category of ["contract", "invoice", "tax"]) {
    const html = await render({ category, ...props });
    assert.match(html, /value="2026-11-01"/);
    assert.match(html, /value="2026-12-05"/);
    assert.match(html, /min="2026-11-01"/);
    if (category !== "contract") {
      assert.doesNotMatch(html, /<details|<summary>/);
      assert.match(html, /Fecha de revisión registrada/);
      assert.match(html, /No es una fecha\s+límite para pagar/);
    }
  }
  // After deliberately clearing an old expiry, the field stays present during editing.
  assert.match(
    await render({ category: "invoice", originalExpiry: true }),
    /data-document-date="expiry"/,
  );
});
