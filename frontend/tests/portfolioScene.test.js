import { test } from "node:test";
import assert from "node:assert/strict";
import {
  filterSceneProperties,
  loadSceneProperties,
  sceneBuildingFrame,
  sceneGroupSize,
  scenePropertyFacts,
  sceneSelectionId,
} from "../src/portfolioScene.js";

test("the first visible property is the default and selecting it again leaves its details open", () => {
  const visible = [{ id: 4 }, { id: 8 }, { id: 12 }];
  assert.equal(sceneSelectionId(visible), 4);
  assert.equal(sceneSelectionId(visible, 4), 4);
  assert.equal(sceneSelectionId(visible, 8), 8);
});

test("a stale selection falls back to the first property of the current group, never a hidden property", () => {
  const firstGroup = Array.from({ length: 6 }, (_, i) => ({ id: i + 1 }));
  const nextGroup = [{ id: 7 }, { id: 8 }];
  assert.equal(sceneSelectionId(nextGroup, 4), 7);
  assert.equal(sceneSelectionId(firstGroup, 7), 1);
  assert.equal(sceneSelectionId([{ id: 49 }, { id: 50 }], 1), 49);
  assert.equal(sceneSelectionId([{ id: 12 }], 49), 12);
  assert.equal(sceneSelectionId([], 12), null);
});

test("building framing enlarges the illustration without cutting the roof, base or selection", () => {
  for (let count = 1; count <= sceneGroupSize; count++) {
    const frame = sceneBuildingFrame(count);
    const [x, y, width, height] = frame.viewBox.split(" ").map(Number);
    assert.ok(
      x <= 78 && x + width >= 606,
      "foundation and shadow fit horizontally",
    );
    assert.ok(
      y <= 54,
      "roof and its selected position fit above the top floor",
    );
    assert.ok(
      y + height >= 294 + count * 88,
      "foundation and shadow fit below the last floor",
    );
    const previousScale = Math.min(
      450 / 640,
      (130 + count * 25) / (306 + count * 88),
    );
    const enlargedScale = Math.min(560 / width, frame.desktopHeight / height);
    assert.ok(
      enlargedScale / previousScale >= 1.3,
      "visible geometry grows, not just its container",
    );
    assert.ok(frame.desktopHeight <= 360 && frame.mobileHeight <= 240);
  }
});

test("building frame stays bounded for large groups or invalid counts", () => {
  assert.deepEqual(sceneBuildingFrame(50), sceneBuildingFrame(6));
  for (const count of [0, -1, null, undefined, "bad-count"])
    assert.deepEqual(sceneBuildingFrame(count), sceneBuildingFrame(1));
  assert.deepEqual(sceneBuildingFrame(2.8), sceneBuildingFrame(2));
});

test("all 50 properties remain reachable in bounded visual groups, including the final two", async () => {
  const items = Array.from({ length: 50 }, (_, i) => ({
    id: i + 1,
    name: `Inmueble ${i + 1}`,
  }));
  const calls = [];
  const api = {
    async get(path, options) {
      calls.push({ path, ...options });
      return {
        data: {
          data: items.slice(
            (options.params.page - 1) * 18,
            options.params.page * 18,
          ),
          last_page: 3,
        },
      };
    },
  };
  const signal = new AbortController().signal;
  const all = await loadSceneProperties(api, signal);
  assert.deepEqual(all, items);
  assert.deepEqual(
    calls.map((call) => call.params.page),
    [1, 2, 3],
  );
  assert.ok(
    calls.every(
      (call) => call.path === "/properties" && call.signal === signal,
    ),
  );
  const groups = Array.from(
    { length: Math.ceil(all.length / sceneGroupSize) },
    (_, i) => all.slice(i * sceneGroupSize, (i + 1) * sceneGroupSize),
  );
  assert.equal(groups.length, 9);
  assert.ok(groups.every((group) => group.length <= 6));
  assert.deepEqual(groups.flat(), items);
  assert.deepEqual(
    groups.at(-1).map((item) => item.id),
    [49, 50],
  );
});

test("search combines ordinary Spanish name, city and address references without accents", () => {
  const properties = [
    {
      id: 1,
      name: "San Nicolás",
      city: "Valencia",
      address_line: "Calle Río 8",
    },
    { id: 2, name: "San Nicolás", city: "Alcoy", address_line: "Calle Río 9" },
    { id: 3, name: "Garaje Norte", city: null },
  ];
  assert.deepEqual(filterSceneProperties(properties, "nicolas VALENCIA"), [
    properties[0],
  ]);
  assert.deepEqual(filterSceneProperties(properties, "rio 9"), [properties[1]]);
  assert.deepEqual(
    filterSceneProperties(properties, "San Nicolas"),
    properties.slice(0, 2),
  );
  assert.deepEqual(filterSceneProperties(properties, "inexistente"), []);
  assert.deepEqual(filterSceneProperties(properties, ""), properties);
});

test("unknown valuation and rent never become zero; actual zero values remain visible", () => {
  assert.equal(scenePropertyFacts({ current_value: null }).value, null);
  assert.equal(scenePropertyFacts({ current_value: "" }).value, null);
  assert.equal(scenePropertyFacts({ current_value: "0.00" }).value, 0);
  assert.equal(scenePropertyFacts({ leases: [] }).monthlyRent, null);
  assert.equal(
    scenePropertyFacts({ leases: [{ status: "active", monthly_rent: null }] })
      .monthlyRent,
    null,
  );
  assert.equal(
    scenePropertyFacts({ leases: [{ status: "active", monthly_rent: "0.00" }] })
      .monthlyRent,
    0,
  );
});

test("only active contracts contribute to contracted rent, preserving cents without claiming payment", () => {
  const facts = scenePropertyFacts({
    current_value: "35000.50",
    outstanding_debt: "1500.25",
    leases: [
      { status: "active", monthly_rent: "62.50" },
      { status: "active", monthly_rent: "0.10" },
      { status: "active", monthly_rent: "0.20" },
      { status: "ended", monthly_rent: "1000.00" },
    ],
  });
  assert.deepEqual(facts, {
    rented: true,
    leaseCount: 3,
    value: 35000.5,
    debt: 1500.25,
    monthlyRent: 62.8,
    grossYield: 2.15,
  });
  assert.ok(!("paid" in facts) && !("pending" in facts));
});

test("gross yield uses annual contracted rent over current value, not purchase price or payments", () => {
  const property = {
    current_value: "100000.00",
    purchase_price: "22000.00",
    leases: [{ status: "active", monthly_rent: "550.50" }],
    transactions: [{ direction: "income", amount: "1.00", status: "paid" }],
  };
  assert.equal(scenePropertyFacts(property).grossYield, 6.61);
  for (const current_value of [null, "", "0.00", "-1", "no-value"])
    assert.equal(
      scenePropertyFacts({ ...property, current_value }).grossYield,
      null,
    );
  assert.equal(
    scenePropertyFacts({ ...property, leases: [] }).grossYield,
    null,
  );
  assert.equal(
    scenePropertyFacts({
      ...property,
      leases: [{ status: "active", monthly_rent: null }],
    }).grossYield,
    null,
  );
  assert.equal(
    scenePropertyFacts({
      ...property,
      leases: [{ status: "active", monthly_rent: "0.00" }],
    }).grossYield,
    0,
  );
});

test("failed pages do not produce a partial portfolio and the request can be cancelled", async () => {
  const controller = new AbortController();
  const api = {
    async get(path, options) {
      if (options.params.page === 1)
        return { data: { data: [{ id: 1 }], last_page: 2 } };
      controller.abort();
      options.signal.throwIfAborted();
    },
  };
  await assert.rejects(loadSceneProperties(api, controller.signal), {
    name: "AbortError",
  });
});
