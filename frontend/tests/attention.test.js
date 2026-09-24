import assert from "node:assert/strict";
import {
  attentionMoney,
  attentionDestination,
  attentionDate,
} from "../src/attention.js";
assert.equal(
  attentionMoney("9007199254740993.01"),
  "9.007.199.254.740.993,01 €",
);
assert.equal(attentionMoney("399.99"), "399,99 €");
assert.equal(attentionMoney("0.00"), "0,00 €");
assert.equal(attentionMoney("invalid"), "—");
assert.equal(attentionDate(null), "Sin fecha límite");
assert.match(attentionDate("2026-09-24"), /24.*sept.*2026/);
const item = {
  entity: { type: "rent_charge", id: 1 },
  evidence: { lease_id: 8 },
  action: { mode: "navigate", path: "/leases/8" },
};
assert.equal(attentionDestination(item), "/leases/8");
for (const path of [
  "https://evil.test",
  "javascript:alert(1)",
  "/leases/9",
  "/admin",
  "//evil.test",
]) {
  assert.equal(
    attentionDestination({ ...item, action: { ...item.action, path } }),
    null,
  );
}
assert.equal(
  attentionDestination({
    entity: { type: "issue", id: 1 },
    action: { mode: "navigate", path: "/issues#issue-1" },
  }),
  "/issues#issue-1",
);
assert.equal(
  attentionDestination({ action: { mode: "confirm", path: "/issues" } }),
  null,
);
