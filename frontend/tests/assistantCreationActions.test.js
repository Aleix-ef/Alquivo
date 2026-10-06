import { test } from "node:test";
import assert from "node:assert/strict";
import {
  actionProposalReferences,
  checkedActionProposal,
  actionEditFields,
  actionEditProblem,
  actionRevisionPayload,
  actionResultPath,
  actionRefreshEvents,
  mutateActionProposal,
} from "../src/assistantActions.js";

const id = "3189756a-e658-42bc-95db-512fe5a1b6a6";
function proposal(type) {
  return {
    id,
    type,
    status: "pending",
    revision: 1,
    expires_at: "2026-10-06T10:00:00Z",
    result: null,
    preview: {
      property_create: {
        name: "Casa Muro",
        type: "housing",
        address_line: "Invented street 42",
        city: "Valencia",
        purchase_price: "100000.35",
        current_value: null,
        currency: "EUR",
      },
      contact_create: {
        name: "Pedro Prueba",
        kind: "person",
        email: null,
        phone: "+34 612345678",
        currency: "EUR",
      },
      lease_create: {
        property: { id: 4, name: "Piso Centro" },
        property_id: 4,
        contacts: [{ id: 8, name: "Pedro Prueba" }],
        contact_ids: [8],
        status: "draft",
        start_date: "2026-10-01",
        end_date: null,
        monthly_rent: "550.35",
        deposit_amount: "0.00",
        payment_day: 5,
        currency: "EUR",
      },
    }[type],
  };
}

test("three creation cards validate canonical previews and references", () => {
  for (const type of ["property_create", "contact_create", "lease_create"]) {
    const p = proposal(type);
    assert.equal(checkedActionProposal(p, id), p);
    assert.deepEqual(actionProposalReferences({ proposals: [p] }), [{ id }]);
    assert.equal(actionEditProblem(p, actionEditFields(p)), "");
    assert.equal(actionResultPath(p), null);
    assert.deepEqual(actionRefreshEvents(p), []);
  }
});

test("creation schemas reject incomplete fields, invalid dates, money and lease target mismatches", () => {
  for (const [type, changes] of [
    ["property_create", { address_line: "" }],
    ["property_create", { type: "invented" }],
    ["property_create", { current_value: "19.999" }],
    ["property_create", { purchase_price: "-1" }],
    ["contact_create", { name: "" }],
    ["contact_create", { kind: "admin" }],
    ["contact_create", { email: "not-email" }],
    ["lease_create", { start_date: "2026-02-30" }],
    ["lease_create", { monthly_rent: "0" }],
    ["lease_create", { payment_day: 29 }],
    ["lease_create", { end_date: "2025-01-01" }],
    ["lease_create", { status: "active" }],
    ["lease_create", { contacts: [{ id: 9, name: "Other" }] }],
    ["lease_create", { contact_ids: [8, 8] }],
    ["lease_create", { property_id: 999 }],
  ]) {
    const p = proposal(type);
    assert.throws(() =>
      checkedActionProposal(
        { ...p, preview: { ...p.preview, ...changes } },
        id,
      ),
    );
  }
});

test("creation revisions preserve cents and nullable data and cannot activate or retarget leases", () => {
  const p = proposal("property_create");
  const changes = actionRevisionPayload(p, {
    ...actionEditFields(p),
    current_value: "120000,37",
  });
  assert.equal(changes.current_value, "120000.37");
  const contact = proposal("contact_create");
  assert.equal(
    actionRevisionPayload(contact, actionEditFields(contact)).email,
    null,
  );
  const lease = proposal("lease_create");
  const fields = {
    ...actionEditFields(lease),
    status: "active",
    property_id: 999,
    contact_ids: [999],
  };
  const payload = actionRevisionPayload(lease, fields);
  assert.equal(payload.monthly_rent, "550.35");
  assert.equal(payload.end_date, null);
  for (const key of ["status", "property_id", "contact_ids"])
    assert.equal(key in payload, false);
});

test("created destinations and refresh events use validated receipt IDs, never API URLs", () => {
  for (const [type, result, expected] of [
    ["property_create", { property_id: 55 }, "/properties/55"],
    ["contact_create", { contact_id: 55 }, "/contacts"],
    ["lease_create", { lease_id: 55, property_id: 4 }, "/leases/55"],
  ]) {
    const p = {
      ...proposal(type),
      status: "executed",
      result: { ...result, path: "https://evil.test" },
    };
    checkedActionProposal(p, id);
    assert.equal(actionResultPath(p), expected);
    assert.ok(actionRefreshEvents(p).length);
    const key = Object.keys(result)[0];
    assert.throws(() =>
      checkedActionProposal({ ...p, result: { [key]: "../123" } }, id),
    );
  }
});

test("lost creation confirmation recovers receipt without repeating mutation", async () => {
  const p = proposal("property_create");
  const executed = { ...p, status: "executed", result: { property_id: 55 } };
  let posts = 0,
    gets = 0;
  const api = {
    post: async () => {
      posts++;
      throw new Error("Lost response");
    },
    get: async () => {
      gets++;
      return { data: { proposal: executed } };
    },
  };
  const result = await mutateActionProposal(api, "confirm", p, {});
  assert.equal(result.proposal.status, "executed");
  assert.equal(posts, 1);
  assert.equal(gets, 1);
});
