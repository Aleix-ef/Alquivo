import test from "node:test";
import assert from "node:assert/strict";
import {
  adminSection,
  chartBuckets,
  count,
  nanoUsd,
  recentRange,
  storage,
} from "../src/adminMetrics.js";
test("admin does not disguise missing financial or usage data as zero", () => {
  assert.equal(count(null), "—");
  assert.equal(nanoUsd(null), "—");
  assert.equal(storage(null), "—");
  assert.equal(count(0), "0");
  assert.equal(storage(1024), "1 KB");
  assert.match(nanoUsd("1000000000"), /1,0000/);
});
test("admin limits section names and calendar range uses Madrid", () => {
  assert.equal(adminSection("ai"), "ai");
  assert.equal(adminSection("__proto__"), "users");
  assert.deepEqual(recentRange(7, new Date("2026-11-05T23:30:00Z")), {
    from: "2026-10-31",
    to: "2026-11-06",
  });
});
test("admin charts keep totals and remain bounded for a year", () => {
  const series = Array.from({ length: 366 }, (_, i) => ({
    date: `date-${i}`,
    registrations: i % 4,
  }));
  const buckets = chartBuckets(series);
  assert.ok(buckets.length <= 60);
  assert.equal(
    buckets.reduce((n, item) => n + item.count, 0),
    series.reduce((n, item) => n + item.registrations, 0),
  );
  assert.deepEqual(chartBuckets([]), []);
});
