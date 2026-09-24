import { test } from "node:test";
import assert from "node:assert/strict";
import {
  assistantRequest,
  checkedExpenseProposal,
  expenseEditFields,
  expenseEditProblem,
  expenseProposalReferences,
  expenseRevisionPayload,
  mergeAssistantMessages,
  mutateExpenseProposal,
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
const otherId = "842be821-ea02-4dbe-bcd0-3fb05ecb459b";
const proposal = () => ({
  id,
  type: "expense",
  status: "pending",
  revision: 1,
  expires_at: "2026-09-22T10:00:00Z",
  preview: {
    property: { id: 15, name: "San Nicolás" },
    amount: "84.00",
    currency: "EUR",
    category: "maintenance",
    description: "Fontanería",
    transaction_date: "2026-09-20",
    status: "paid",
  },
  result: null,
});
const typedProposal = (type) => ({
  ...proposal(),
  type,
  preview: {
    contact_phone: {
      contact: { id: 3, name: "Ana García" },
      previous_phone: null,
      phone: "+34 612 345 678",
    },
    rent_payment: {
      property: { id: 15, name: "San Nicolás" },
      lease: { id: 8 },
      rent_charge: {
        id: 17,
        period: "2026-09",
        due_date: "2026-09-05",
        remaining_amount: "800.00",
      },
      amount: "300.00",
      currency: "EUR",
      transaction_date: "2026-09-22",
      payment_method: null,
    },
    property_note: {
      property: { id: 15, name: "San Nicolás" },
      note: "Revisar la caldera en octubre.",
    },
  }[type],
});

test("all four action kinds use canonical cards and reject unregistered types", () => {
  for (const type of ["contact_phone", "rent_payment", "property_note"]) {
    const action = typedProposal(type);
    assert.equal(checkedActionProposal(action, id), action);
    assert.deepEqual(actionProposalReferences({ proposals: [action] }), [
      { id },
    ]);
    assert.equal(actionEditProblem(action, actionEditFields(action)), "");
  }
  assert.throws(() =>
    checkedActionProposal({ ...proposal(), type: "contact_delete" }, id),
  );
  for (const [type, changes] of [
    ["contact_phone", { contact: { id: "../1", name: "Ana" } }],
    ["contact_phone", { phone: null }],
    ["rent_payment", { lease: { id: "https://evil.test" } }],
    ["rent_payment", { amount: "900.00" }],
    ["rent_payment", { transaction_date: "2026-02-30" }],
    ["property_note", { note: "" }],
    ["property_note", { property: { id: -3, name: "Casa" } }],
  ]) {
    const action = typedProposal(type);
    assert.throws(() =>
      checkedActionProposal(
        { ...action, preview: { ...action.preview, ...changes } },
        id,
      ),
    );
  }
});

test("phone updates only allow a valid new phone, never change the contact", () => {
  const action = typedProposal("contact_phone");
  assert.equal(actionEditProblem(action, { phone: "+34 (612) 345-678" }), "");
  for (const phone of [
    "",
    null,
    "123456",
    "1234567890123456",
    "612abc678",
    "612\n345678",
    "+".repeat(31),
  ])
    assert.ok(actionEditProblem(action, { phone }));
  assert.deepEqual(
    actionRevisionPayload(action, {
      phone: " 612345678 ",
      contact_id: 99,
      previous_phone: "000",
    }),
    { revision: 1, phone: "612345678" },
  );
});

test("rent payment edits allow partial payments but forbid exceeding the pending cents", () => {
  const action = typedProposal("rent_payment");
  const fields = actionEditFields(action);
  assert.equal(actionEditProblem(action, { ...fields, amount: "800,00" }), "");
  for (const changes of [
    { amount: "800.01" },
    { amount: "0" },
    { amount: "0.001" },
    { amount: "1e2" },
    { amount: "-3" },
    { payment_method: "a".repeat(41) },
    { transaction_date: "2026-02-29" },
  ])
    assert.ok(actionEditProblem(action, { ...fields, ...changes }));
  assert.deepEqual(
    actionRevisionPayload(action, {
      ...fields,
      amount: "300,20",
      rent_charge_id: 999,
      lease_id: 99,
      payment_method: " transferencia ",
    }),
    {
      revision: 1,
      amount: "300.20",
      transaction_date: "2026-09-22",
      payment_method: "transferencia",
    },
  );
  assert.equal(actionRevisionPayload(action, fields).payment_method, null);
});

test("property notes are bounded additions and cannot overwrite notes or choose targets", () => {
  const action = typedProposal("property_note");
  assert.equal(actionEditProblem(action, { note: "a".repeat(2000) }), "");
  for (const note of [" ", "a".repeat(2001), null])
    assert.ok(actionEditProblem(action, { note }));
  assert.deepEqual(
    actionRevisionPayload(action, {
      note: " Nueva nota ",
      property_id: 99,
      notes: "replacement",
      append: false,
    }),
    { revision: 1, note: "Nueva nota" },
  );
});

test("receipt destinations and refresh events are built from known action kinds and IDs", () => {
  const expected = {
    contact_phone: ["/contacts", ["alquivo:contacts-changed"]],
    rent_payment: [
      "/leases/8",
      ["alquivo:finance-changed", "alquivo:leases-changed"],
    ],
    property_note: ["/properties/15", ["alquivo:properties-changed"]],
  };
  for (const [type, [path, events]] of Object.entries(expected)) {
    const action = typedProposal(type);
    assert.equal(actionResultPath(action), null);
    assert.deepEqual(actionRefreshEvents(action), []);
    action.status = "executed";
    action.result = { path: "https://evil.test", lease_id: "../../delete" };
    assert.equal(actionResultPath(action), path);
    assert.deepEqual(
      actionRefreshEvents(action).map(({ name }) => name),
      events,
    );
  }
});

test("all new action types recover confirmation by GET without retrying mutation", async () => {
  for (const type of ["contact_phone", "rent_payment", "property_note"]) {
    const action = typedProposal(type),
      calls = [];
    const result = await mutateActionProposal(
      {
        post: async (path, payload) => {
          calls.push(["post", path, payload]);
          throw new Error("Lost response");
        },
        get: async () => {
          calls.push(["get"]);
          return { data: { proposal: { ...action, status: "executed" } } };
        },
      },
      "confirm",
      action,
      { phone: "untrusted", note: "ignored" },
    );
    assert.equal(result.proposal.status, "executed");
    assert.deepEqual(
      calls.map(([method]) => method),
      ["post", "get"],
    );
    assert.deepEqual(calls[0][2], { revision: 1 });
  }
});

test("a mutation response cannot swap the reviewed action kind", async () => {
  const phone = typedProposal("contact_phone");
  const substituted = { ...typedProposal("property_note"), status: "executed" };
  const result = await mutateActionProposal(
    {
      post: async () => ({ data: { proposal: substituted } }),
      get: async () => ({ data: { proposal: substituted } }),
    },
    "confirm",
    phone,
  );
  assert.equal(result.proposal, null);
  assert.ok(result.error);
  assert.throws(() => checkedActionProposal(substituted, id, phone.type));
});

test("metadata only references unique expense proposals, never arbitrary endpoints or actions", () => {
  assert.deepEqual(expenseProposalReferences(null), []);
  assert.deepEqual(expenseProposalReferences({ proposals: "unexpected" }), []);
  assert.deepEqual(
    expenseProposalReferences({
      proposals: [
        proposal(),
        proposal(),
        { id: "../../other", type: "expense" },
        { id: otherId, type: "confirm" },
        null,
        { id: otherId, type: "expense", endpoint: "https://evil.test" },
      ],
    }),
    [{ id }, { id: otherId }],
  );
});

test("canonical resources must match requested identity, revision, currency and state", () => {
  assert.equal(checkedExpenseProposal(proposal(), id).id, id);
  for (const changes of [
    { id: otherId },
    { revision: 0 },
    { status: "approved" },
    { type: "delete" },
    { preview: { ...proposal().preview, amount: "NaN" } },
    { preview: { ...proposal().preview, currency: "invalid" } },
  ]) {
    assert.throws(() =>
      checkedExpenseProposal({ ...proposal(), ...changes }, id),
    );
  }
  assert.equal(
    checkedExpenseProposal(
      { ...proposal(), preview: { ...proposal().preview, currency: "USD" } },
      id,
    ).preview.currency,
    "USD",
  );
});

test("expense editing validates cents, known categories, actual dates and status", () => {
  const fields = expenseEditFields(proposal().preview);
  assert.equal(expenseEditProblem(fields), "");
  assert.equal(expenseEditProblem({ ...fields, amount: "84,20" }), "");
  for (const changes of [
    { amount: "0" },
    { amount: "-12" },
    { amount: "8e2" },
    { amount: "84.005" },
    { amount: "10000000000" },
    { description: " " },
    { description: "a".repeat(181) },
    { category: "custom" },
    { transaction_date: "2026-02-30" },
    { transaction_date: "2026-99-01" },
    { transaction_date: "invalid" },
    { status: "cancelled" },
  ]) {
    assert.ok(expenseEditProblem({ ...fields, ...changes }));
  }
});

test("revision payload cannot smuggle property, ownership, execution or result fields", () => {
  const fields = {
    ...expenseEditFields(proposal().preview),
    amount: "84,20",
    description: " Fontanería ",
    property_id: 999,
    user_id: 555,
    confirmed: true,
  };
  assert.deepEqual(expenseRevisionPayload(proposal(), fields), {
    revision: 1,
    amount: "84.20",
    category: "maintenance",
    description: "Fontanería",
    transaction_date: "2026-09-20",
    status: "paid",
  });
});

test("confirmation only submits proposal revision, never data chosen by the model", async () => {
  const calls = [];
  const completed = {
    ...proposal(),
    status: "executed",
    result: { transaction_id: 23, path: "/finance" },
  };
  const api = {
    post: async (...args) => {
      calls.push(args);
      return { data: { proposal: completed } };
    },
  };
  const result = await mutateExpenseProposal(api, "confirm", proposal(), {
    amount: "999",
    confirmed: true,
  });
  assert.deepEqual(calls[0].slice(0, 2), [
    `/assistant/proposals/${id}/confirm`,
    { revision: 1 },
  ]);
  assert.equal(result.proposal.status, "executed");
  assert.equal(calls.length, 1);
});

test("a lost confirmation response recovers its receipt without another mutation", async () => {
  const calls = [];
  const lost = new Error("Network disconnected");
  const api = {
    post: async (...args) => {
      calls.push(["post", ...args]);
      throw lost;
    },
    get: async (...args) => {
      calls.push(["get", ...args]);
      return { data: { proposal: { ...proposal(), status: "executed" } } };
    },
  };
  const result = await mutateExpenseProposal(api, "confirm", proposal());
  assert.equal(result.proposal.status, "executed");
  assert.equal(result.error, lost);
  assert.deepEqual(
    calls.map(([method]) => method),
    ["post", "get"],
  );
  assert.equal(calls[1][1], `/assistant/proposals/${id}`);
});

test("a conflict returns current revision for a new explicit review, never auto-confirms", async () => {
  let posts = 0;
  const conflict = { response: { status: 409 } };
  const result = await mutateExpenseProposal(
    {
      post: async () => {
        posts++;
        throw conflict;
      },
      get: async () => ({
        data: {
          proposal: {
            ...proposal(),
            revision: 2,
            preview: { ...proposal().preview, amount: "85.00" },
          },
        },
      }),
    },
    "confirm",
    proposal(),
  );
  assert.equal(posts, 1);
  assert.equal(result.proposal.revision, 2);
  assert.equal(result.error, conflict);
});

test("failed recovery does not return the stale proposal as if it were verified", async () => {
  const result = await mutateExpenseProposal(
    {
      post: async () => {
        throw new Error("lost");
      },
      get: async () => {
        throw new Error("offline");
      },
    },
    "cancel",
    proposal(),
  );
  assert.equal(result.proposal, null);
  assert.ok(result.error);
});

test("aborted requests do not recover into an unrelated component context", async () => {
  const controller = new AbortController();
  let gets = 0;
  await assert.rejects(() =>
    mutateExpenseProposal(
      {
        post: async () => {
          controller.abort();
          throw new Error("aborted");
        },
        get: async () => {
          gets++;
        },
      },
      "confirm",
      proposal(),
      {},
      { signal: controller.signal },
    ),
  );
  assert.equal(gets, 0);
});

test("unknown actions cannot choose arbitrary mutation routes", async () => {
  let called = false;
  await assert.rejects(() =>
    mutateExpenseProposal(
      {
        post: async () => {
          called = true;
        },
      },
      "../delete",
      proposal(),
    ),
  );
  assert.equal(called, false);
});

test("request IDs survive retries but cannot cross message, property or conversation contexts", () => {
  const context = {
    message: "Registra 84 €",
    propertyId: 15,
    conversationId: "1",
  };
  const initial = assistantRequest(null, context, () => id);
  assert.equal(
    assistantRequest(initial, context, () => {
      throw new Error("must reuse id");
    }),
    initial,
  );
  for (const changes of [
    { message: "Registra 85 €" },
    { propertyId: 16 },
    { conversationId: "2" },
  ]) {
    const changed = assistantRequest(
      initial,
      { ...context, ...changes },
      () => otherId,
    );
    assert.equal(changed.clientRequestId, otherId);
  }
});

test("replaying an envelope replaces messages instead of duplicating the transcript", () => {
  const user = { id: 1, role: "user", content: "Mi gasto" };
  const reply = { id: 2, role: "assistant", content: "Revisa" };
  assert.deepEqual(
    mergeAssistantMessages(
      [user, reply],
      [
        undefined,
        user,
        { ...reply, content: "Actualizado" },
        { id: 3, role: "system", content: "ignore" },
      ],
    ),
    [user, { ...reply, content: "Actualizado" }],
  );
});
