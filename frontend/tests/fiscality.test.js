import test from "node:test";
import assert from "node:assert/strict";
import { fiscalDraft, fiscalPayload, fiscalMoney } from "../src/fiscality.js";
import { reactive } from "vue";

test("missing fiscal amounts remain unknown rather than being defaulted to zero", () => {
  const payload = fiscalPayload(fiscalDraft());
  assert.equal(payload.income, null);
  assert.equal(payload.prior_depreciation, null);
  assert.equal(payload.records_reviewed, false);
  assert.equal(fiscalMoney(null), "Pendiente");
  assert.notEqual(fiscalMoney(0), "Pendiente");
});
test("Spanish decimal input is normalized without modifying the saved record", () => {
  const saved = reactive({
    income: "1200.00",
    expenses: [{ amount: "20.50", document_id: 2 }],
  });
  const draft = fiscalDraft(saved);
  draft.income = "1234,56";
  draft.expenses[0].amount = "30,25";
  const payload = fiscalPayload(draft);
  assert.equal(payload.income, "1234.56");
  assert.equal(payload.expenses[0].amount, "30.25");
  assert.equal(saved.income, "1200.00");
  assert.equal(saved.expenses[0].amount, "20.50");
});
