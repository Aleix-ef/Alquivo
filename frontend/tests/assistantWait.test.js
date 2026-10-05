import { test } from "node:test";
import assert from "node:assert/strict";
import { effectScope, ref } from "vue";
import { useAssistantWait } from "../src/composables/useAssistantWait.js";

function fixture(initiallySending = false) {
  let time = 1000;
  let sequence = 0;
  const timers = new Map();
  const cancelled = [];
  const sending = ref(initiallySending);
  const scope = effectScope();
  const wait = scope.run(() =>
    useAssistantWait(sending, {
      now: () => time,
      schedule: (callback) => {
        const id = ++sequence;
        timers.set(id, callback);
        return id;
      },
      cancel: (id) => {
        cancelled.push(id);
        timers.delete(id);
      },
    }),
  );
  return {
    sending,
    scope,
    wait,
    timers,
    cancelled,
    advance: (milliseconds) => {
      time += milliseconds;
      for (const callback of timers.values()) callback();
    },
  };
}

test("idle assistant does not schedule any background work", () => {
  const f = fixture();
  try {
    assert.equal(f.timers.size, 0);
    assert.equal(f.wait.elapsedSeconds.value, 0);
    assert.equal(f.wait.slow.value, false);
    assert.equal(f.wait.label.value, "Preparando tu respuesta…");
  } finally {
    f.scope.stop();
  }
});

test("waiting reports elapsed time and a neutral message, not invented tool progress", () => {
  const f = fixture();
  try {
    f.sending.value = true;
    assert.equal(f.timers.size, 1);
    f.advance(3900);
    assert.equal(f.wait.elapsedSeconds.value, 3);
    assert.equal(f.wait.slow.value, false);
    f.advance(6100);
    assert.equal(f.wait.elapsedSeconds.value, 10);
    assert.equal(f.wait.slow.value, true);
    assert.equal(f.wait.label.value, "La respuesta está tardando un poco más.");
  } finally {
    f.scope.stop();
  }
});

test("a completed or failed request clears the timer and resets waiting", () => {
  const f = fixture(true);
  try {
    f.advance(11000);
    f.sending.value = false;
    assert.equal(f.timers.size, 0);
    assert.equal(f.cancelled.length, 1);
    assert.equal(f.wait.elapsedSeconds.value, 0);
    assert.equal(f.wait.slow.value, false);
    f.advance(5000);
    assert.equal(f.wait.elapsedSeconds.value, 0);
  } finally {
    f.scope.stop();
  }
});

test("a new request has a fresh timer without duplicate intervals", () => {
  const f = fixture(true);
  try {
    f.advance(8000);
    f.sending.value = false;
    f.sending.value = true;
    f.sending.value = true;
    assert.equal(f.timers.size, 1);
    assert.equal(f.wait.elapsedSeconds.value, 0);
    f.advance(2000);
    assert.equal(f.wait.elapsedSeconds.value, 2);
    assert.equal(f.cancelled.length, 1);
  } finally {
    f.scope.stop();
  }
});

test("unmounting stops the timer and the watcher even during an active request", () => {
  const f = fixture(true);
  f.scope.stop();
  assert.equal(f.timers.size, 0);
  assert.equal(f.cancelled.length, 1);
  f.sending.value = false;
  f.sending.value = true;
  assert.equal(f.timers.size, 0);
});

test("a backward clock adjustment cannot show negative waiting time", () => {
  const f = fixture(true);
  try {
    f.advance(-5000);
    assert.equal(f.wait.elapsedSeconds.value, 0);
    assert.equal(f.wait.slow.value, false);
  } finally {
    f.scope.stop();
  }
});
