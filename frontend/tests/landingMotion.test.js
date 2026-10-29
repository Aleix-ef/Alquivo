import { test } from "node:test";
import assert from "node:assert/strict";
import { observeLandingMotion } from "../src/composables/useLandingMotion.js";

function environment({ reduced = false, supported = true } = {}) {
  const listeners = new Set();
  const instances = [];
  const elements = Array.from({ length: 2 }, () => {
    const classes = new Set();
    return {
      classes,
      classList: {
        add: (name) => classes.add(name),
        remove: (name) => classes.delete(name),
      },
    };
  });
  const root = {
    querySelectorAll: (selector) =>
      selector === "[data-landing-reveal]"
        ? elements
        : elements.filter((el) => el.classes.has("is-revealed")),
  };
  const query = {
    matches: reduced,
    addEventListener: (name, fn) => {
      assert.equal(name, "change");
      listeners.add(fn);
    },
    removeEventListener: (name, fn) => listeners.delete(fn),
  };
  class Observer {
    constructor(callback, options) {
      this.callback = callback;
      this.options = options;
      this.observed = new Set();
      this.disconnected = false;
      instances.push(this);
    }
    observe(el) {
      this.observed.add(el);
    }
    unobserve(el) {
      this.observed.delete(el);
    }
    disconnect() {
      this.disconnected = true;
      this.observed.clear();
    }
  }
  return {
    root,
    elements,
    query,
    listeners,
    instances,
    environment: {
      matchMedia: () => query,
      IntersectionObserver: supported ? Observer : undefined,
    },
  };
}

test("landing reveal plays once on intersection and disconnects on teardown", () => {
  const e = environment();
  const cleanup = observeLandingMotion(e.root, e.environment);
  const observer = e.instances[0];
  assert.equal(observer.observed.size, 2);
  observer.callback([{ target: e.elements[0], isIntersecting: false }]);
  assert.equal(e.elements[0].classes.size, 0);
  observer.callback([{ target: e.elements[0], isIntersecting: true }]);
  assert.equal(e.elements[0].classes.has("is-revealed"), true);
  assert.equal(observer.observed.has(e.elements[0]), false);
  cleanup();
  assert.equal(observer.disconnected, true);
  assert.equal(e.listeners.size, 0);
  assert.ok(e.elements.every((el) => el.classes.size === 0));
});

test("reduced motion works on initial load and on preference changes", () => {
  const e = environment({ reduced: true });
  const cleanup = observeLandingMotion(e.root, e.environment);
  assert.equal(e.instances.length, 0);
  e.query.matches = false;
  for (const listener of e.listeners) listener();
  assert.equal(e.instances.length, 1);
  e.instances[0].callback([{ target: e.elements[0], isIntersecting: true }]);
  e.query.matches = true;
  for (const listener of e.listeners) listener();
  assert.equal(e.instances[0].disconnected, true);
  assert.ok(e.elements.every((el) => el.classes.size === 0));
  cleanup();
});

test("unsupported browsers keep all content accessible without a motion observer", () => {
  const e = environment({ supported: false });
  const cleanup = observeLandingMotion(e.root, e.environment);
  assert.equal(e.instances.length, 0);
  assert.ok(e.elements.every((el) => el.classes.size === 0));
  cleanup();
  assert.doesNotThrow(() => observeLandingMotion(e.root, {})());
});
