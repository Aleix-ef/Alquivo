import { test } from "node:test";
import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import { parse } from "@vue/compiler-sfc";
import {
  paymentWindowLabel,
  validPaymentWindow,
} from "../src/paymentWindow.js";

test("payment windows validate integer days, ordering and single-day ranges", () => {
  for (const [start, end] of [
    [1, 5],
    [5, 5],
    [30, 31],
    ["1", "5"],
  ])
    assert.equal(validPaymentWindow(start, end), true);
  for (const [start, end] of [
    [0, 5],
    [1, 32],
    [8, 5],
    [1.5, 5],
    [1, 5.5],
    [null, 5],
    [1, null],
    [true, 5],
    [1, ""],
    [undefined, 5],
  ])
    assert.equal(validPaymentWindow(start, end), false);
});

test("legacy single days and new windows have clear labels", () => {
  assert.equal(paymentWindowLabel({ payment_day: 5 }), "el día 5");
  assert.equal(
    paymentWindowLabel({ payment_day: 5, payment_day_start: null }),
    "el día 5",
  );
  assert.equal(
    paymentWindowLabel({ payment_day: 5, payment_day_start: 1 }),
    "del 1 al 5",
  );
  assert.equal(
    paymentWindowLabel({ payment_day: 31, payment_day_start: 30 }),
    "del 30 al 31",
  );
  assert.equal(
    paymentWindowLabel({ payment_day: 5, payment_day_start: 8 }),
    "sin plazo registrado",
  );
});

test("create, edit and renewal reuse the range editor and send both days", async () => {
  for (const [file, forms] of [
    ["LeasesView", ["form"]],
    ["LeaseDetailView", ["editForm", "renewalForm"]],
  ]) {
    const source = await readFile(
      new URL(`../src/views/${file}.vue`, import.meta.url),
      "utf8",
    );
    assert.deepEqual(parse(source).errors, []);
    for (const form of forms) {
      assert.match(
        source,
        new RegExp(`v-model:start="${form}\\.payment_day_start"`),
      );
      assert.match(source, new RegExp(`v-model:end="${form}\\.payment_day"`));
      assert.match(
        source,
        new RegExp(
          `payment_day_start: Number\\(${form}\\.value\\.payment_day_start\\)`,
        ),
      );
      assert.match(
        source,
        new RegExp(
          `validPaymentWindow\\(\\s*${form}\\.value\\.payment_day_start,`,
        ),
      );
    }
    assert.match(source, /paymentWindowLabel\(/);
  }
  const fields = await readFile(
    new URL("../src/components/PaymentWindowFields.vue", import.meta.url),
    "utf8",
  );
  assert.deepEqual(parse(fields).errors, []);
  assert.match(fields, /Ambos días incluidos/);
});
