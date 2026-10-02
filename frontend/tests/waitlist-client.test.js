import { test } from "node:test";
import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import { runInNewContext } from "node:vm";
import { waitlistConsent } from "../src/waitlist/contract.js";

const source = await readFile(
  new URL("../src/waitlist/form.js", import.meta.url),
  "utf8",
);

function client({
  preview = false,
  valid = true,
  result = { ok: true },
  status = 200,
  fetchError = false,
  challengeError = false,
  html = false,
} = {}) {
  const listeners = {},
    scripts = [],
    calls = [];
  let widgetOptions,
    resets = 0;
  const elements = Object.fromEntries(
    [
      "waitlist-fields",
      "waitlist-submit",
      "waitlist-error",
      "waitlist-success",
      "waitlist-success-title",
    ].map((id) => [
      id,
      {
        hidden: ["waitlist-error", "waitlist-success"].includes(id),
        disabled: false,
        focusCount: 0,
        focus() {
          this.focusCount++;
        },
      },
    ]),
  );
  const form = {
    hidden: false,
    dataset: {
      preview: String(preview),
      siteKey: "synthetic-sitekey",
      consentVersion: waitlistConsent.version,
    },
    addEventListener(event, callback) {
      listeners[event] = callback;
    },
    reportValidity() {
      return valid;
    },
    setAttribute() {},
    removeAttribute() {},
  };
  elements["waitlist-form"] = form;
  const context = {
    document: {
      getElementById(id) {
        return elements[id];
      },
      createElement() {
        return { remove() {} };
      },
      head: {
        append(script) {
          scripts.push(script);
          queueMicrotask(() => script.onload());
        },
      },
    },
    window: {
      turnstile: {
        render(selector, options) {
          assert.equal(selector, "#waitlist-challenge");
          widgetOptions = options;
          return "widget-1";
        },
        execute() {
          if (challengeError) widgetOptions["error-callback"]();
          else widgetOptions.callback("synthetic-token");
        },
        reset() {
          resets++;
        },
      },
    },
    FormData: class {
      get(key) {
        return {
          email: "synthetic@example.com",
          name: "Ana",
          property_count: "1",
          website: "",
          consent: "on",
        }[key];
      }
    },
    setTimeout() {
      return 1;
    },
    clearTimeout() {},
    AbortSignal: {
      timeout() {
        return undefined;
      },
    },
    async fetch(url, options) {
      calls.push({ url, options });
      if (fetchError) throw new Error("network failure");
      return {
        ok: status >= 200 && status < 300,
        async json() {
          if (html) throw new SyntaxError("Unexpected token <");
          return result;
        },
      };
    },
  };
  runInNewContext(source, context);
  return {
    form,
    elements,
    listeners,
    scripts,
    calls,
    get options() {
      return widgetOptions;
    },
    get resets() {
      return resets;
    },
    submit: () => listeners.submit({ preventDefault() {} }),
  };
}

test("no third party is loaded until interaction; Turnstile receives no input values", async () => {
  const ui = client();
  assert.equal(ui.scripts.length, 0);
  ui.listeners.focusin();
  await new Promise((resolve) => setImmediate(resolve));
  assert.equal(ui.scripts.length, 1);
  assert.equal(
    ui.scripts[0].src,
    "https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit",
  );
  assert.equal(ui.options.theme, "dark");
  assert.equal(ui.options.execution, "execute");
  assert.equal(ui.options.action, "beta_waitlist");
  assert.doesNotMatch(
    JSON.stringify(ui.options),
    /synthetic@example.com|Ana|email|property_count/,
  );
  assert.equal(ui.calls.length, 0);
});
test("native successful submit sends only known fields to first-party endpoint and shows confirmation", async () => {
  const ui = client();
  const sending = ui.submit();
  assert.equal(ui.elements["waitlist-fields"].disabled, true);
  await sending;
  assert.equal(ui.calls.length, 1);
  assert.equal(ui.calls[0].url, "/api/waitlist");
  assert.equal(ui.calls[0].options.credentials, "omit");
  assert.deepEqual(JSON.parse(ui.calls[0].options.body), {
    email: "synthetic@example.com",
    name: "Ana",
    property_count: "1",
    website: "",
    consent: true,
    consent_version: waitlistConsent.version,
    turnstile_token: "synthetic-token",
  });
  assert.equal(ui.form.hidden, true);
  assert.equal(ui.elements["waitlist-success"].hidden, false);
  assert.equal(ui.elements["waitlist-success-title"].focusCount, 1);
});
test("invalid browser fields do not load third-party script or send", async () => {
  const ui = client({ valid: false });
  await ui.submit();
  assert.equal(ui.calls.length, 0);
  assert.equal(ui.scripts.length, 0);
});
test("preview has no submit listener or provider request", () => {
  const ui = client({ preview: true });
  assert.deepEqual(ui.listeners, {});
  assert.equal(ui.scripts.length, 0);
});
test("double clicks cannot send concurrent duplicates", async () => {
  const ui = client();
  await Promise.all([ui.submit(), ui.submit()]);
  assert.equal(ui.calls.length, 1);
});
for (const options of [
  {
    result: { ok: false, message: "Necesitamos tu consentimiento" },
    status: 422,
  },
  { result: { ok: false, message: "Espera unos minutos" }, status: 429 },
  { result: { ok: false }, status: 503 },
  { html: true, status: 404 },
  { fetchError: true },
  { challengeError: true },
  { result: { ok: "true" } },
])
  test(`failed submit never claims success; values remain and retry is available: ${JSON.stringify(options)}`, async () => {
    const ui = client(options);
    await ui.submit();
    assert.equal(ui.form.hidden, false);
    assert.equal(ui.elements["waitlist-success"].hidden, true);
    assert.equal(ui.elements["waitlist-error"].hidden, false);
    assert.equal(ui.elements["waitlist-error"].focusCount, 1);
    assert.equal(ui.elements["waitlist-fields"].disabled, false);
    assert.equal(
      ui.elements["waitlist-submit"].textContent,
      "Solicitar acceso gratis",
    );
    assert.equal(ui.resets, 1);
  });
test("provider/user strings are displayed as text, never interpreted as HTML", async () => {
  const ui = client({
    status: 422,
    result: { ok: false, message: "<script>injection</script>" },
  });
  await ui.submit();
  assert.equal(
    ui.elements["waitlist-error"].textContent,
    "<script>injection</script>",
  );
  assert.equal(ui.elements["waitlist-error"].innerHTML, undefined);
  assert.doesNotMatch(
    source,
    /innerHTML|localStorage|sessionStorage|document\.cookie/,
  );
});
