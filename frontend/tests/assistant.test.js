import { test } from "node:test";
import assert from "node:assert/strict";
import {
  contentParts,
  replyPose,
  safeSources,
} from "../src/assistantPresentation.js";
import { assistantContext, helpGuides } from "../src/assistantHelp.js";

test("sources only allow internal destinations and tolerate old metadata", () => {
  assert.deepEqual(safeSources(null), []);
  assert.deepEqual(safeSources({ sources: "unexpected" }), []);
  assert.deepEqual(
    safeSources({
      sources: [
        null,
        { path: "https://evil.test", label: "Externo" },
        { path: "/settings?redirect=//evil", label: "No" },
        { path: "/finance", label: "Finanzas" },
        { path: "/support", label: "Soporte" },
        { path: "/properties/5", label: "Inmueble" },
      ],
    }),
    [
      { path: "/finance", label: "Finanzas" },
      { path: "/support", label: "Soporte" },
      { path: "/properties/5", label: "Inmueble" },
    ],
  );
});

test("context scopes only property detail routes and offers appropriate questions", () => {
  assert.equal(assistantContext("/properties/12").propertyId, 12);
  assert.equal(assistantContext("/properties/new").propertyId, undefined);
  assert.equal(assistantContext("/leases/12").propertyId, undefined);
  assert.equal(assistantContext("/properties/0").propertyId, undefined);
  assert.equal(assistantContext("/finance").label, "Mis finanzas");
  assert.equal(assistantContext("/documents").label, "Próximas gestiones");
  assert.ok(assistantContext("/dashboard").suggestions.length > 0);
});

test("help guides have unique ids, concrete steps and safe navigation", () => {
  assert.equal(
    new Set(helpGuides.map((guide) => guide.id)).size,
    helpGuides.length,
  );
  for (const guide of helpGuides) {
    assert.ok(guide.steps.length >= 3);
    assert.equal(contentParts(`[Abrir](${guide.to})`)[0].to, guide.to);
  }
});

test("assistant links only navigate to known application pages", () => {
  assert.deepEqual(contentParts("Ver [inmueble](/properties/12)."), [
    { text: "Ver " },
    { text: "inmueble", to: "/properties/12" },
    { text: "." },
  ]);
  for (const path of [
    "https://example.com",
    "//evil",
    "/unknown",
    "/properties/../plans",
    "/settings?redirect=//evil",
    "javascript:alert(1)",
    "/logout",
  ]) {
    assert.equal(
      contentParts(`[Enlace](${path})`).some((part) => part.to),
      false,
    );
  }
  assert.deepEqual(contentParts('<img src=x onerror="alert(1)">'), [
    { text: '<img src=x onerror="alert(1)">' },
  ]);
});

test("mascot remains neutral for old messages and thoughtful for limitations", () => {
  assert.equal(replyPose({ content: "Respuesta antigua" }), "idle");
  assert.equal(replyPose({ metadata: { kind: "answer" } }), "idle");
  for (const kind of [
    "insufficient_data",
    "out_of_scope",
    "support_required",
    "read_only",
  ]) {
    assert.equal(replyPose({ metadata: { kind } }), "uncertain");
  }
});
