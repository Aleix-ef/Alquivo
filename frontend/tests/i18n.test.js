import assert from "node:assert/strict";
import test from "node:test";
import {
  i18n,
  currentLocale,
  forceSpanish,
  setLocale,
  syncLocaleForSession,
} from "../src/i18n.js";

function keys(value, prefix = "") {
  return Object.entries(value).flatMap(([key, item]) => {
    const path = prefix ? `${prefix}.${key}` : key;
    return typeof item === "string" ? [path] : keys(item, path);
  });
}

test("Spanish and English dictionaries cover the same keys", () => {
  const es = i18n.global.getLocaleMessage("es");
  const en = i18n.global.getLocaleMessage("en");
  assert.deepEqual(keys(en).sort(), keys(es).sort());
  for (const locale of ["es", "en"]) {
    setLocale(locale, true);
    for (const key of keys(es)) {
      const text = i18n.global.t(key, { count: 1, limit: 1, price: "1 €" });
      assert.ok(text.trim() && text !== key, `${locale}: ${key}`);
    }
  }
  setLocale("es");
});

test("English is never enabled for a non-admin or by a saved preference alone", () => {
  const values = new Map([["alquivo:locale", "en"]]);
  globalThis.window = {
    localStorage: {
      getItem: (key) => values.get(key) ?? null,
      setItem: (key, value) => values.set(key, value),
    },
    navigator: { language: "en-GB" },
  };
  try {
    forceSpanish();
    assert.equal(currentLocale(), "es");
    syncLocaleForSession(false);
    assert.equal(currentLocale(), "es");
    setLocale("en");
    assert.equal(currentLocale(), "es");

    syncLocaleForSession(true);
    assert.equal(currentLocale(), "en");
    setLocale("es", true);
    assert.equal(currentLocale(), "es");
    assert.equal(values.get("alquivo:locale"), "es");
    setLocale("en", true);
    assert.equal(i18n.global.t("common.login"), "Log in");
    syncLocaleForSession(false);
    assert.equal(i18n.global.t("common.login"), "Entrar");
    assert.equal(values.get("alquivo:locale"), "en");
    setLocale("fr", true);
    assert.equal(currentLocale(), "es");
  } finally {
    delete globalThis.window;
    forceSpanish();
  }
});
