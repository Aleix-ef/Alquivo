import { test } from "node:test";
import assert from "node:assert/strict";
import { recordAnchorId, revealRecord } from "../src/recordAnchor.js";

test("record links require exact safe positive IDs and the requested record type", () => {
  assert.equal(recordAnchorId("#rent-charge-123", "rent-charge"), 123);
  assert.equal(recordAnchorId("#issue-2", "issue"), 2);
  for (const hash of [
    null,
    [],
    "#rent-charge-0",
    "#rent-charge--1",
    "#rent-charge-01",
    "#rent-charge-1.2",
    "#rent-charge-9007199254740992",
    "#issue-123",
    "#rent-charge-123?confirm=1",
  ])
    assert.equal(recordAnchorId(hash, "rent-charge"), null);
  assert.equal(recordAnchorId("#anything-1", "[anything]"), null);
});

test("highlight locates the actionable button but never clicks it or submits anything", () => {
  const calls = [];
  const action = {
    focus: (options) => calls.push(["focus", options]),
    click: () => {
      throw new Error("Navigation must not execute an action");
    },
  };
  const row = {
    addEventListener() {},
    removeEventListener() {},
    classList: {
      add: (name) => calls.push(["add", name]),
      remove: (name) => calls.push(["remove", name]),
    },
    querySelector: (selector) => {
      assert.equal(selector, "[data-record-action]:not(:disabled)");
      return action;
    },
    scrollIntoView: (options) => calls.push(["scroll", options]),
  };
  const clear = revealRecord(row);
  assert.deepEqual(calls, [
    ["add", "record-anchor-active"],
    ["focus", { preventScroll: true }],
    ["scroll", { block: "center" }],
  ]);
  clear();
  assert.deepEqual(calls.at(-1), ["remove", "record-anchor-active"]);
  assert.doesNotThrow(() => revealRecord(null)());
});

test("a record without an available action gets focus itself", () => {
  let focused = false;
  revealRecord({
    removeEventListener() {},
    classList: { add() {}, remove() {} },
    querySelector: () => null,
    focus() {
      focused = true;
    },
    scrollIntoView() {},
  });
  assert.equal(focused, true);
});

test("late native fragment focus returns to the highlighted action and cleanup removes the listener", () => {
  let listener,
    focusCount = 0;
  const row = {
    classList: { add() {}, remove() {} },
    querySelector: () => ({ focus: () => focusCount++ }),
    addEventListener(type, callback) {
      assert.equal(type, "focus");
      listener = callback;
    },
    removeEventListener(type, callback) {
      assert.equal(type, "focus");
      assert.equal(callback, listener);
      listener = null;
    },
    scrollIntoView() {},
  };
  const clear = revealRecord(row);
  assert.equal(focusCount, 1);
  listener();
  assert.equal(focusCount, 2);
  clear();
  assert.equal(listener, null);
});
