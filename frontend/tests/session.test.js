import { afterEach, test } from "node:test";
import assert from "node:assert/strict";
import { safeReturnPath } from "../src/authNavigation.js";
import {
  clearSessionStorage,
  persistSession,
  readSessionValue,
} from "../src/sessionStorage.js";

afterEach(() => {
  delete globalThis.localStorage;
});

test("expired access returns to the requested app page including its query", () => {
  assert.equal(
    safeReturnPath("/settings?verified=1#profile"),
    "/settings?verified=1#profile",
  );
  assert.equal(safeReturnPath("/properties/12"), "/properties/12");
});

test("untrusted return paths cannot leave the app or return to authentication", () => {
  for (const value of [
    undefined,
    ["/plans"],
    "https://example.com",
    "//example.com",
    "/\\example.com",
    "/\n/example.com",
    "/login?redirect=/plans",
    "/login/",
    "/LOGIN?redirect=/plans",
    "/register",
    "/reset-password?token=x",
  ]) {
    assert.equal(safeReturnPath(value), "/dashboard");
  }
});

test("corrupt or unexpected cached session values cannot crash startup", () => {
  for (const value of [
    "{unfinished",
    "undefined",
    "42",
    "true",
    "[]",
    "null",
  ]) {
    globalThis.localStorage = { getItem: () => value };
    assert.equal(readSessionValue("ig_user"), null);
  }
});

test("storage restrictions do not prevent login, restore or logout", () => {
  const denied = () => {
    throw new Error("Storage access denied");
  };
  globalThis.localStorage = {
    getItem: denied,
    setItem: denied,
    removeItem: denied,
  };
  assert.equal(readSessionValue("ig_user"), null);
  assert.doesNotThrow(() =>
    persistSession({ user: { id: 1 }, portfolio: { id: 2 } }),
  );
  assert.doesNotThrow(clearSessionStorage);
});

test("session cache persists both display values and leaves preferences when cleared", () => {
  const values = new Map([["nareo-appearance", "dark"]]);
  globalThis.localStorage = {
    getItem: (key) => values.get(key) ?? null,
    setItem: (key, value) => values.set(key, value),
    removeItem: (key) => values.delete(key),
  };
  const session = {
    user: { id: 1, name: "Propietario" },
    portfolio: { id: 2, name: "Cartera" },
  };
  persistSession(session);
  assert.deepEqual(readSessionValue("ig_user"), session.user);
  assert.deepEqual(readSessionValue("ig_portfolio"), session.portfolio);
  clearSessionStorage();
  assert.equal(values.size, 1);
  assert.equal(values.get("nareo-appearance"), "dark");
});
