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
  navigation = false,
  values = {},
  challengeWidth = 400,
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
          this.disabledAtFocus = elements["waitlist-fields"].disabled;
          context.document.activeElement = this;
        },
      },
    ]),
  );
  elements["waitlist-submit"].textContent = "Avísame cuando abra";
  elements["waitlist-challenge"] = { clientWidth: challengeWidth };
  const form = {
    hidden: false,
    dataset: {
      preview: String(preview),
      siteKey: "synthetic-sitekey",
      consentVersion: waitlistConsent.version,
    },
    addEventListener(event, callback) {
      const previous = listeners[event];
      listeners[event] = previous
        ? (...args) => {
            previous(...args);
            return callback(...args);
          }
        : callback;
    },
    reportValidity() {
      return valid;
    },
    setAttribute() {},
    removeAttribute() {},
    contains(element) {
      return (
        !!element &&
        ["waitlist-email", "waitlist-submit", "waitlist-error"].some(
          (id) => element === elements[id],
        )
      );
    },
  };
  elements["waitlist-form"] = form;
  const activeClasses = new Set();
  let intersect;
  if (navigation) {
    elements["waitlist-mobile-cta"] = { hidden: true };
    elements["solicitud"] = {};
    elements["footer"] = {};
    elements["waitlist-email"] = {};
  }
  const context = {
    document: {
      activeElement: null,
      querySelector() {
        return elements.footer;
      },
      documentElement: {
        classList: {
          toggle(name, enabled) {
            if (enabled) activeClasses.add(name);
            else activeClasses.delete(name);
          },
        },
      },
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
      ...(navigation
        ? {
            IntersectionObserver: class {
              constructor(callback) {
                intersect = callback;
              }
              observe() {}
            },
          }
        : {}),
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
          ...values,
        }[key];
      }
    },
    setTimeout(callback, delay) {
      if (delay === 0) queueMicrotask(callback);
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
    context,
    activeClasses,
    visibility(cardVisible, footerVisible = false) {
      intersect([
        { target: elements.solicitud, isIntersecting: cardVisible },
        { target: elements.footer, isIntersecting: footerVisible },
      ]);
    },
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
test("antispam uses compact size when the phone form has less than 300px available", async () => {
  for (const challengeWidth of [240, 280, 299]) {
    const ui = client({ challengeWidth });
    await ui.listeners.focusin();
    assert.equal(ui.options.size, "compact");
    assert.equal(ui.options.appearance, "interaction-only");
    assert.equal(ui.calls.length, 0);
  }
});
test("antispam remains flexible when its container is wide enough", async () => {
  for (const challengeWidth of [300, 480]) {
    const ui = client({ challengeWidth });
    await ui.listeners.focusin();
    assert.equal(ui.options.size, "flexible");
    assert.equal(ui.calls.length, 0);
  }
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
      "Avísame cuando abra",
    );
    assert.equal(ui.elements["waitlist-error"].disabledAtFocus, false);
    assert.equal(ui.resets, 1);
  });
test("optional details may be omitted without a second request", async () => {
  const ui = client({ values: { name: null, property_count: null } });
  await ui.submit();
  assert.equal(ui.calls.length, 1);
  const body = JSON.parse(ui.calls[0].options.body);
  assert.equal(body.name, null);
  assert.equal(body.property_count, null);
  assert.equal(ui.form.hidden, true);
});
test("mobile shortcut stays hidden until the form was seen and never covers it or the footer", () => {
  const ui = client({ navigation: true });
  const shortcut = ui.elements["waitlist-mobile-cta"];
  ui.visibility(false);
  assert.equal(shortcut.hidden, true);
  ui.visibility(true);
  assert.equal(shortcut.hidden, true);
  ui.visibility(false);
  assert.equal(shortcut.hidden, false);
  assert.ok(ui.activeClasses.has("waitlist-cta-visible"));
  ui.visibility(false, true);
  assert.equal(shortcut.hidden, true);
  assert.equal(ui.activeClasses.size, 0);
  ui.visibility(true);
  assert.equal(shortcut.hidden, true);
});
test("mobile shortcut is hidden during editing and after a confirmed signup", async () => {
  const ui = client({ navigation: true });
  ui.visibility(true);
  ui.visibility(false);
  assert.equal(ui.elements["waitlist-mobile-cta"].hidden, false);
  ui.context.document.activeElement = ui.elements["waitlist-email"];
  ui.listeners.focusin();
  assert.equal(ui.elements["waitlist-mobile-cta"].hidden, true);
  ui.context.document.activeElement = null;
  ui.listeners.focusout();
  await new Promise((resolve) => setImmediate(resolve));
  assert.equal(ui.elements["waitlist-mobile-cta"].hidden, false);
  const sending = ui.submit();
  assert.equal(ui.elements["waitlist-mobile-cta"].hidden, true);
  await sending;
  assert.equal(ui.elements["waitlist-mobile-cta"].hidden, true);
  assert.equal(ui.activeClasses.size, 0);
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
