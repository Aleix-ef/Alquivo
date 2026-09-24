// Development-only component fixture: API calls are mocked in this isolated page.
// This file is not an application entry point and is not included in the build.
import { createApp, h, nextTick } from "vue";
import api from "../../src/api";
import AssistantActionPreview from "../../src/components/AssistantActionPreview.vue";

if (!import.meta.env.DEV)
  throw new Error("This fixture only runs in development.");

const id = "3189756a-e658-42bc-95db-512fe5a1b6a6";
const base = () => ({
  id,
  type: "expense",
  status: "pending",
  revision: 1,
  expires_at: new Date(Date.now() + 600000).toISOString(),
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
const typedBase = (type) => ({
  ...base(),
  type,
  preview: {
    contact_phone: {
      contact: { id: 3, name: "Ana García" },
      previous_phone: "611 111 111",
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
const output = document.querySelector("#results");
const host = document.querySelector("#fixture");
const errors = [],
  completed = [];
let app,
  state,
  calls,
  financeEvents = 0;
window.addEventListener("alquivo:finance-changed", () => financeEvents++);
const refreshEvents = [];
for (const name of [
  "alquivo:contacts-changed",
  "alquivo:leases-changed",
  "alquivo:properties-changed",
])
  window.addEventListener(name, (event) =>
    refreshEvents.push({ name, detail: event.detail }),
  );
window.addEventListener("unhandledrejection", (event) =>
  errors.push(String(event.reason)),
);
function assert(condition, message) {
  if (!condition) throw new Error(message);
}
function button(label) {
  return [...host.querySelectorAll("button")].find(
    (element) => element.textContent.trim() === label,
  );
}
const pause = () => new Promise((resolve) => setTimeout(resolve, 10));
async function until(condition, message) {
  for (let count = 0; count < 150; count++) {
    await nextTick();
    if (errors.length) throw new Error(errors.join("\n"));
    if (condition()) return;
    await pause();
  }
  throw new Error(`Timeout: ${message}`);
}
async function mount({ enabled = true, get, post, initial } = {}) {
  app?.unmount();
  state = initial || base();
  calls = [];
  api.get = async (url, options) => {
    calls.push({ method: "get", url });
    return get
      ? get(url, options)
      : { data: { proposal: structuredClone(state) } };
  };
  api.post = async (url, payload, options) => {
    calls.push({ method: "post", url, payload });
    if (post) return post(url, payload, options);
    if (url.endsWith("/revise")) {
      state.revision++;
      const { revision: _, ...fields } = payload;
      state.preview = { ...state.preview, ...fields };
    } else if (url.endsWith("/confirm")) {
      state.status = "executed";
      state.result = { transaction_id: 19, path: "https://evil.test" };
    } else if (url.endsWith("/cancel")) state.status = "cancelled";
    return { data: { proposal: structuredClone(state) } };
  };
  app = createApp({
    render: () => h(AssistantActionPreview, { proposalId: id, enabled }),
  });
  app.component("RouterLink", {
    props: ["to"],
    setup:
      (props, { slots }) =>
      () =>
        h("a", { href: props.to }, slots.default?.()),
  });
  app.config.errorHandler = (error) =>
    errors.push(error.stack || String(error));
  app.mount(host);
  await until(
    () =>
      host
        .querySelector(".assistant-action-preview")
        ?.getAttribute("aria-busy") === "false",
    "initial canonical request",
  );
}
async function test(label, callback) {
  await callback();
  assert(errors.length === 0, errors.join("\n"));
  completed.push(label);
  output.textContent = completed.map((text) => `✓ ${text}`).join("\n");
}

try {
  await test("Canonical GET before action; editing creates only a new preview; double click creates one mutation", async () => {
    await mount();
    assert(
      calls.length === 1 && calls[0].method === "get",
      "Mount must only verify the proposal",
    );
    assert(
      host.textContent.includes("San Nicolás") &&
        host.textContent.includes("84,00"),
      "Preview must show property and amount",
    );
    button("Editar propuesta").click();
    await nextTick();
    const amount = host.querySelector("form input");
    amount.value = "85,20";
    amount.dispatchEvent(new Event("input", { bubbles: true }));
    button("Guardar cambios de la propuesta").click();
    await until(
      () =>
        !host.querySelector("form") && host.textContent.includes("Revisión 2"),
      "save editable preview",
    );
    assert(
      state.status === "pending" && state.preview.amount === "85.20",
      "Editing must not execute",
    );
    const confirm = button("Confirmar gasto");
    confirm.click();
    confirm.click();
    await until(
      () => host.textContent.includes("Gasto registrado"),
      "explicit confirmation",
    );
    assert(
      calls.filter((call) => call.url.endsWith("/confirm")).length === 1,
      "Double click must not repeat confirmation",
    );
    assert(
      host.querySelector("a").getAttribute("href") === "/finance",
      "Receipt link must not use arbitrary server or model destinations",
    );
  });

  await test("Conflict updates revision without executing or throwing a component error", async () => {
    await mount({
      post: async () => {
        state.revision = 2;
        state.preview.amount = "90.00";
        throw { response: { status: 409 } };
      },
    });
    button("Confirmar gasto").click();
    await until(
      () => host.textContent.includes("La propuesta ha cambiado"),
      "conflict recovery",
    );
    assert(
      host.textContent.includes("Revisión 2") &&
        host.textContent.includes("90,00"),
      "Conflict must display canonical changes",
    );
    assert(
      state.status === "pending" &&
        calls.filter((call) => call.method === "post").length === 1,
      "Conflict must never automatically confirm",
    );
  });

  await test("Lost confirmation response recovers receipt by GET and refreshes Finance once", async () => {
    const before = financeEvents;
    await mount({
      post: async () => {
        state.status = "executed";
        state.result = { transaction_id: 21, path: "/finance" };
        throw new Error("Lost response");
      },
    });
    button("Confirmar gasto").click();
    await until(
      () => host.textContent.includes("Gasto registrado"),
      "lost response receipt",
    );
    assert(
      calls.filter((call) => call.method === "post").length === 1,
      "Network failure must not cause another mutation",
    );
    assert(
      calls.filter((call) => call.method === "get").length === 2,
      "Recover with GET",
    );
    assert(
      financeEvents === before + 1,
      "Recovered execution must refresh Finance once",
    );
    assert(
      !host.querySelector('[role="alert"]'),
      "Recovered success must not show a failure",
    );
  });

  await test("Uncertain state disables confirmation until recovery", async () => {
    let reads = 0;
    await mount({
      get: async () => {
        if (++reads > 1) throw new Error("offline");
        return { data: { proposal: structuredClone(state) } };
      },
      post: async () => {
        throw new Error("offline");
      },
    });
    button("Confirmar gasto").click();
    await until(() => !!button("Comprobar estado"), "unverified mutation");
    assert(
      button("Confirmar gasto").disabled,
      "Unverified proposal must not be actionable",
    );
  });

  await test("Cancelled and expired proposals cannot be confirmed; disabled capabilities are respected", async () => {
    await mount();
    button("Cancelar propuesta").click();
    await until(
      () => host.textContent.includes("Propuesta cancelada"),
      "cancel",
    );
    assert(
      !button("Confirmar gasto"),
      "Cancelled proposal must have no confirm action",
    );
    await mount({ initial: { ...base(), status: "expired" } });
    assert(
      !button("Confirmar gasto"),
      "Expired proposal must have no confirm action",
    );
    await mount({ enabled: false });
    assert(
      button("Confirmar gasto").disabled && button("Editar propuesta").disabled,
      "Missing capability disables writes",
    );
  });

  await test("Phone card displays old and new values, edits only the new phone and refreshes Contacts", async () => {
    const before = refreshEvents.length;
    await mount({ initial: typedBase("contact_phone") });
    assert(
      host.textContent.includes("Ana García") &&
        host.textContent.includes("611 111 111") &&
        host.textContent.includes("+34 612 345 678"),
      "Phone change must identify contact and both values",
    );
    assert(
      !host.textContent.includes("Estado del gasto"),
      "Phone card must not show expense fields",
    );
    button("Editar propuesta").click();
    await nextTick();
    const phone = host.querySelector('input[type="tel"]');
    phone.value = "123";
    phone.dispatchEvent(new Event("input", { bubbles: true }));
    button("Guardar cambios de la propuesta").click();
    await until(
      () => !!host.querySelector('[role="alert"]'),
      "invalid phone feedback",
    );
    assert(
      calls.every((call) => call.method === "get"),
      "Invalid phone must never mutate",
    );
    phone.value = "699 222 333";
    phone.dispatchEvent(new Event("input", { bubbles: true }));
    button("Guardar cambios de la propuesta").click();
    await until(() => !host.querySelector("form"), "phone revision");
    assert(state.status === "pending", "Revision does not apply phone");
    const confirm = button("Confirmar teléfono");
    confirm.click();
    confirm.click();
    await until(
      () => host.textContent.includes("Teléfono actualizado"),
      "phone confirmation",
    );
    assert(
      calls.filter((call) => call.url.endsWith("/confirm")).length === 1,
      "Phone double click must not duplicate",
    );
    assert(
      host.querySelector("a").getAttribute("href") === "/contacts",
      "Phone receipt must stay on Contacts",
    );
    assert(
      refreshEvents.length === before + 1 &&
        refreshEvents.at(-1).name === "alquivo:contacts-changed" &&
        refreshEvents.at(-1).detail.contactId === 3,
      "Refresh correct contact",
    );
  });

  await test("Rent card prevents overpayment, allows partial receipt and refreshes Lease and Finance", async () => {
    const before = refreshEvents.length,
      beforeFinance = financeEvents;
    await mount({ initial: typedBase("rent_payment") });
    assert(
      host.textContent.includes("800,00") &&
        host.textContent.includes("cobro parcial"),
      "Payment must show outstanding amount and partial meaning",
    );
    button("Editar propuesta").click();
    await nextTick();
    const amount = host.querySelector("form input");
    amount.value = "800,01";
    amount.dispatchEvent(new Event("input", { bubbles: true }));
    button("Guardar cambios de la propuesta").click();
    await until(
      () => host.textContent.includes("no puede superar"),
      "overpayment blocked",
    );
    assert(
      calls.every((call) => call.method === "get"),
      "Overpayment must not reach API",
    );
    amount.value = "400,50";
    amount.dispatchEvent(new Event("input", { bubbles: true }));
    button("Guardar cambios de la propuesta").click();
    await until(() => !host.querySelector("form"), "partial payment edit");
    button("Confirmar cobro").click();
    await until(
      () => host.textContent.includes("Cobro registrado"),
      "payment confirmation",
    );
    assert(
      state.preview.amount === "400.50",
      "Preserve exact submitted decimal",
    );
    assert(
      host.querySelector("a").getAttribute("href") === "/leases/8",
      "Payment receipt uses trusted lease ID",
    );
    assert(
      financeEvents === beforeFinance + 1 &&
        refreshEvents.length === before + 1 &&
        refreshEvents.at(-1).name === "alquivo:leases-changed",
      "Payment refreshes both surfaces once",
    );
  });

  await test("Note card appends text, escapes markup, recovers lost response and refreshes the property once", async () => {
    const before = refreshEvents.length;
    await mount({
      initial: typedBase("property_note"),
      post: async (url, payload) => {
        if (url.endsWith("/revise")) {
          state.revision++;
          state.preview.note = payload.note;
          return { data: { proposal: structuredClone(state) } };
        }
        state.status = "executed";
        throw new Error("Lost response");
      },
    });
    assert(
      host.textContent.includes("sin sustituir las notas existentes"),
      "Must make append-only semantics clear",
    );
    button("Editar propuesta").click();
    await nextTick();
    const note = host.querySelector("textarea");
    note.value = 'Revisar caldera. <img src=x onerror="alert(1)">';
    note.dispatchEvent(new Event("input", { bubbles: true }));
    button("Guardar cambios de la propuesta").click();
    await until(() => !host.querySelector("form"), "note revision");
    assert(
      !host.querySelector("img") && host.textContent.includes("<img"),
      "Notes must render as plain text",
    );
    button("Confirmar nota").click();
    await until(
      () => host.textContent.includes("Nota añadida"),
      "note recovered confirmation",
    );
    assert(
      calls.filter((call) => call.url.endsWith("/confirm")).length === 1,
      "Lost response must not repeat note",
    );
    assert(
      host.querySelector("a").getAttribute("href") === "/properties/15",
      "Note receipt uses trusted property ID",
    );
    assert(
      refreshEvents.length === before + 1 &&
        refreshEvents.at(-1).name === "alquivo:properties-changed" &&
        refreshEvents.at(-1).detail.propertyId === 15,
      "Note refreshes property once",
    );
  });

  await test("New action types still require capability and explicit confirmation, and can be cancelled", async () => {
    for (const [type, label] of [
      ["contact_phone", "Confirmar teléfono"],
      ["rent_payment", "Confirmar cobro"],
      ["property_note", "Confirmar nota"],
    ]) {
      await mount({ initial: typedBase(type), enabled: false });
      assert(
        button(label).disabled,
        "Disabled capability must block each action type",
      );
      await mount({ initial: typedBase(type) });
      button("Cancelar propuesta").click();
      await until(
        () => host.textContent.includes("Propuesta cancelada"),
        "cancel typed proposal",
      );
      assert(
        !button(label) && state.status === "cancelled",
        "Cancellation must never execute",
      );
    }
  });

  app?.unmount();
  document.body.dataset.result = "passed";
  output.textContent += `\nPASS: ${completed.length} real-component scenarios`;
} catch (error) {
  document.body.dataset.result = "failed";
  output.textContent += `\nFAIL: ${error.stack || error}`;
}
