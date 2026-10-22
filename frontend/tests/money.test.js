import { test } from "node:test";
import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import { formatMoney } from "../src/money.js";

const normalized = (value) => value.replace(/[\u00a0\u202f]/g, " ");

test("summaries retain cents instead of rounding to whole euros", () => {
  for (const [amount, expected] of [
    [62.5, "62,50 €"],
    ["62.05", "62,05 €"],
    ["0.01", "0,01 €"],
    [0, "0,00 €"],
    [550, "550,00 €"],
    [-12.75, "-12,75 €"],
    ["125000.35", "125.000,35 €"],
    [null, "0,00 €"],
  ]) {
    assert.equal(normalized(formatMoney(amount)), expected);
  }
});

test("formatting a sum or a net amount preserves its cents and selected currency", () => {
  assert.equal(normalized(formatMoney(62.5 - 12.25)), "50,25 €");
  assert.equal(normalized(formatMoney(0.1 + 0.2)), "0,30 €");
  const usd = new Intl.NumberFormat("es-ES", {
    style: "currency",
    currency: "USD",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
  assert.equal(formatMoney("62.50", "USD"), usd.format(62.5));
});

test("dashboard, reports and both property screens use the cents-preserving formatter", async () => {
  for (const view of [
    "DashboardView",
    "ReportsView",
    "PropertiesView",
    "PropertyDetailView",
  ]) {
    const source = await readFile(
      new URL(`../src/views/${view}.vue`, import.meta.url),
      "utf8",
    );
    assert.match(
      source,
      /import \{ formatMoney(?: as money)? \} from "\.\.\/money";/,
      view,
    );
    assert.doesNotMatch(source, /maximumFractionDigits:\s*0\b/, view);
  }
});
