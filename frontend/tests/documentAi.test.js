import test from "node:test";
import assert from "node:assert/strict";
import { reviewValues, receiptPath, documentError } from "../src/documentAi.js";
test("review normalizes only explicit fields, never assumes money or a property", () => {
  const original = {
    total: "",
    property_id: "",
    status: "pending",
    contact_ids: [],
  };
  assert.deepEqual(reviewValues(original), {
    total: null,
    property_id: null,
    status: "pending",
    contact_ids: [],
  });
  assert.equal(original.total, "");
  assert.equal(reviewValues({ total: "1.234,56" }).total, "1.234,56"); // Server must reject ambiguity, not guess.
});
test("receipt routes are generated from positive integer identifiers only", () => {
  assert.equal(receiptPath({ lease_id: 12 }), "/leases/12");
  assert.equal(receiptPath({ transaction_id: 1 }), "/finance");
  assert.equal(receiptPath({ lease_id: "https://bad.test" }), null);
  assert.equal(receiptPath({ url: "javascript:alert(1)" }), null);
});
test("validation feedback is readable", () => {
  assert.equal(
    documentError({
      response: {
        data: {
          errors: { total: ["Revisa importe."], date: ["Falta fecha."] },
        },
      },
    }),
    "Revisa importe. Falta fecha.",
  );
});
