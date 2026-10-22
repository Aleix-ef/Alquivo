import { test } from "node:test";
import assert from "node:assert/strict";
import { readTwoFactorResult } from "../src/authChallenge.js";
import { accountSection } from "../src/accountSettings.js";

test("only a completed MFA response with an authenticated account completes the login", () => {
  assert.deepEqual(
    readTwoFactorResult({ challenge_complete: true, user: { id: 7 } }),
    { complete: true, remaining: [] },
  );
  for (const data of [
    null,
    {},
    { user: { id: 7 } },
    { challenge_complete: true },
    { challenge_complete: true, user: { id: -1 } },
  ])
    assert.throws(() => readTwoFactorResult(data));
});
test("both-factor challenge keeps the user at verification until the remaining factor passes", () => {
  assert.deepEqual(
    readTwoFactorResult({
      challenge_complete: false,
      methods_remaining: ["email"],
    }),
    { complete: false, remaining: ["email"] },
  );
  assert.throws(() =>
    readTwoFactorResult({ challenge_complete: false, methods_remaining: [] }),
  );
  assert.throws(() =>
    readTwoFactorResult({
      challenge_complete: false,
      methods_remaining: ["unknown"],
    }),
  );
});
test("settings preserve direct links and choose a safe default for unknown sections", () => {
  assert.equal(accountSection({ section: "security" }), "security");
  assert.equal(accountSection({}, "#profile"), "profile");
  assert.equal(accountSection({ checkout: "success" }), "plan");
  assert.equal(accountSection({ verified: "1" }), "profile");
  assert.equal(accountSection({ section: ["security"] }), "profile");
  assert.equal(accountSection({ section: "unknown" }), "profile");
});
