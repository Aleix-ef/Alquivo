import { test } from "node:test";
import assert from "node:assert/strict";
import { readFile, readdir } from "node:fs/promises";
import { parse } from "@vue/compiler-sfc";
import { rentPaymentLimits } from "../src/rentPayments.js";

const monetaryFields = new Set([
  "monthly_rent",
  "deposit_amount",
  "amount",
  "estimated_cost",
  "actual_cost",
  "purchase_price",
  "current_value",
  "acquisition_costs",
  "outstanding_debt",
]);
const integerFields = new Set(["payment_day", "bedrooms", "bathrooms"]);
const numericInputs = [];
for (const directory of ["views", "components"]) {
  const base = new URL(`../src/${directory}/`, import.meta.url);
  for (const filename of await readdir(base)) {
    if (!filename.endsWith(".vue")) continue;
    const { descriptor, errors } = parse(
      await readFile(new URL(filename, base), "utf8"),
    );
    assert.deepEqual(errors, []);
    function visit(node) {
      if (node.tag === "input") {
        const attribute = (name) =>
          node.props.find((p) => p.type === 6 && p.name === name)?.value
            ?.content;
        if (attribute("type") === "number") {
          const model = node.props.find(
            (p) => p.type === 7 && p.name === "model",
          )?.exp?.content;
          assert.ok(model, `${filename}: numeric input must declare its model`);
          numericInputs.push({ filename, model, attribute });
        }
      }
      for (const child of node.children || []) visit(child);
    }
    if (descriptor.template) visit(descriptor.template.ast);
  }
}

test("all native amount and surface inputs admit hundredths and a decimal keyboard", () => {
  let checked = 0;
  for (const input of numericInputs) {
    const field = input.model.split(".").at(-1);
    if (!monetaryFields.has(field) && field !== "area") continue;
    const label = `${input.filename}: ${input.model}`;
    assert.equal(input.attribute("step"), "0.01", label);
    assert.equal(input.attribute("inputmode"), "decimal", label);
    assert.ok(["0", "0.01"].includes(input.attribute("min")), label);
    if (field === "monthly_rent") {
      assert.equal(input.attribute("min"), "0.01", label);
    }
    checked++;
  }
  assert.ok(checked >= 18, "covers every existing native money/surface input");
});

test("counts and payment days remain integers, and new number fields require classification", () => {
  const checked = new Set();
  for (const input of numericInputs) {
    const field = input.model.split(".").at(-1);
    assert.ok(
      monetaryFields.has(field) || field === "area" || integerFields.has(field),
      `Classify the numeric field ${input.filename}: ${input.model}`,
    );
    if (!integerFields.has(field)) continue;
    assert.ok(
      [undefined, "1"].includes(input.attribute("step")),
      `${input.filename}: ${field} must keep the native integer step`,
    );
    if (field === "payment_day") {
      assert.equal(input.attribute("min"), "1");
      assert.equal(input.attribute("max"), "28");
    }
    checked.add(field);
  }
  assert.deepEqual(checked, integerFields);
});

test("a valid full payment is not blocked by a fractional binary maximum", () => {
  assert.ok(380.03 - 330.01 < 50.02); // The previous maximum rejected 50.02.
  assert.deepEqual(
    rentPaymentLimits({ amount: "380.03", paid_amount: "330.01" }),
    { remaining: "50.02", maximum: "50.02" },
  );
});

test("remaining amounts preserve cents including one-cent and fully paid balances", () => {
  for (const [amount, paid, remaining] of [
    ["550.50", "300.25", "250.25"],
    ["0.30", "0.20", "0.10"],
    ["1.00", "0.99", "0.01"],
    ["550.50", "550.50", "0.00"],
    ["9999999999.99", "9999999999.98", "0.01"],
  ]) {
    assert.deepEqual(rentPaymentLimits({ amount, paid_amount: paid }), {
      remaining,
      maximum: remaining,
    });
  }
});

test("editing a payment adds back exactly that payment and no more", () => {
  assert.deepEqual(
    rentPaymentLimits({ amount: "380.03", paid_amount: "330.01" }, "30.01"),
    { remaining: "50.02", maximum: "80.03" },
  );
  assert.deepEqual(
    rentPaymentLimits({ amount: "550.50", paid_amount: "550.50" }, "550.50"),
    { remaining: "0.00", maximum: "550.50" },
  );
});

test("integer API amounts and unavailable charge recovery retain their previous limits", () => {
  assert.deepEqual(rentPaymentLimits({ amount: 550, paid_amount: 300 }, 25), {
    remaining: "250.00",
    maximum: "275.00",
  });
  assert.deepEqual(rentPaymentLimits(undefined, "84.35"), {
    remaining: "0.00",
    maximum: "84.35",
  });
});

test("invalid API values cannot become a permissive or silently rounded maximum", () => {
  for (const value of [
    "50.019",
    "1,234",
    "1e2",
    "NaN",
    "-1",
    "10000000000.00",
  ]) {
    assert.throws(() =>
      rentPaymentLimits({ amount: value, paid_amount: "0.00" }),
    );
    assert.throws(() =>
      rentPaymentLimits({ amount: "550.00", paid_amount: "0.00" }, value),
    );
  }
});
