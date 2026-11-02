import { test } from "node:test";
import assert from "node:assert/strict";
import { shortDate, shortPeriod } from "../src/datePresentation.js";

test("financial dates hide technical timestamps without shifting their recorded calendar day", () => {
  assert.equal(
    shortDate("2026-10-05T00:00:00.000000Z"),
    shortDate("2026-10-05"),
  );
  assert.equal(shortDate("2026-10-05"), "05/10/2026");
  assert.doesNotMatch(
    shortDate("2026-10-05T00:00:00.000000Z"),
    /00:00|000000|T|Z/,
  );
  for (const invalid of [null, undefined, 123, "not-a-date", "2026-02-30"])
    assert.equal(shortDate(invalid), "Sin fecha");
});

test("rental months are readable and invalid periods are never invented", () => {
  assert.equal(shortPeriod("2026-10"), "octubre de 2026");
  for (const invalid of [null, "", "2026-13", "2026-10-05"])
    assert.equal(shortPeriod(invalid), "Sin mensualidad");
});
