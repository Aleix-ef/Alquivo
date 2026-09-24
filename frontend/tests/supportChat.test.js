import { test } from "node:test";
import assert from "node:assert/strict";
import {
  mergeSupportMessages,
  supportEndpoint,
  supportStatuses,
} from "../src/supportChat.js";

test("chat endpoints are fixed internal paths and never accept arbitrary URLs", () => {
  assert.equal(supportEndpoint("team"), "/support/team/conversations");
  assert.equal(supportEndpoint("public"), "/public/support/chat/conversations");
  assert.equal(
    supportEndpoint("https://attacker.test"),
    "/support/chat/conversations",
  );
});

test("polling merges messages without duplicates and preserves older loaded history", () => {
  const old = [
    { id: 1, body: "primero" },
    { id: 4, body: "respuesta" },
  ];
  const result = mergeSupportMessages(old, [
    { id: 4, body: "respuesta" },
    { id: 6, body: "nuevo" },
  ]);
  assert.deepEqual(
    result.map((item) => item.id),
    [1, 4, 6],
  );
  assert.equal(old.length, 2);
});

test("chat statuses distinguish pending support, a reply and a resolved query", () => {
  assert.equal(supportStatuses.waiting_support, "Pendiente de soporte");
  assert.equal(supportStatuses.waiting_customer, "Respuesta del equipo");
  assert.equal(supportStatuses.closed, "Resuelta");
});
