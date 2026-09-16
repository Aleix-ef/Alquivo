import test from "node:test";
import assert from "node:assert/strict";
import { fetchAllPages } from "../src/pagination.js";

test("fetchAllPages preserves filters and joins every server page", async () => {
  const calls = [];
  const api = {
    async get(url, options) {
      calls.push([url, options.params]);
      const page = options.params.page;
      return { data: { data: [{ id: page }], last_page: 3 } };
    },
  };

  assert.deepEqual(
    await fetchAllPages(api, "/documents", {
      params: { category: "invoice" },
    }),
    [{ id: 1 }, { id: 2 }, { id: 3 }],
  );
  assert.deepEqual(calls, [
    ["/documents", { category: "invoice", page: 1 }],
    ["/documents", { category: "invoice", page: 2 }],
    ["/documents", { category: "invoice", page: 3 }],
  ]);
});

test("fetchAllPages rejects non-paginated responses", async () => {
  const api = { get: async () => ({ data: { items: [] } }) };
  await assert.rejects(() => fetchAllPages(api, "/broken"), TypeError);
});
