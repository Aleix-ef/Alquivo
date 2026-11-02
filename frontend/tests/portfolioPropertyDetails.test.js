import { test } from "node:test";
import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import { compileScript, parse } from "@vue/compiler-sfc";
import { createSSRApp, h } from "vue";
import { renderToString } from "vue/server-renderer";

const source = await readFile(
  new URL("../src/components/PortfolioPropertyDetails.vue", import.meta.url),
  "utf8",
);
const { descriptor } = parse(source);
const { content } = compileScript(descriptor, {
  id: "PortfolioPropertyDetails",
  inlineTemplate: true,
});
const moduleSource = content.replace(
  /from (["'])([^"']+)\1/g,
  (match, quote, specifier) => {
    const url =
      specifier === "vue"
        ? import.meta.resolve("vue")
        : new URL(specifier, new URL("../src/components/", import.meta.url))
            .href;
    return `from ${JSON.stringify(url)}`;
  },
);
const { default: component } = await import(
  `data:text/javascript;base64,${Buffer.from(moduleSource).toString("base64")}`
);
async function render(property) {
  const app = createSSRApp(component, { property });
  app.component("RouterLink", {
    props: ["to"],
    setup:
      (props, { slots }) =>
      () =>
        h("a", { href: props.to }, slots.default?.()),
  });
  return renderToString(app);
}

test("property details show contracted rent, defined gross yield, dates and the payment window without claiming payment", async () => {
  const html = await render({
    id: 1,
    name: "San Nicolás",
    city: "Valencia",
    current_value: "100000.00",
    outstanding_debt: "9999.00",
    leases: [
      {
        id: 5,
        status: "active",
        monthly_rent: "550.50",
        start_date: "2026-01-01T00:00:00.000000Z",
        end_date: "2027-12-31T00:00:00.000000Z",
        payment_day_start: 1,
        payment_day: 5,
        participants: [
          { name: "Juan", pivot: { role: "tenant" } },
          { name: "Avalista", pivot: { role: "guarantor" } },
        ],
      },
    ],
  });
  for (const text of [
    "Valencia",
    "550,50",
    "100.000,00",
    "6,61 %",
    "del 1 al 5",
    "01/01/2026",
    "31/12/2027",
    "Juan",
    "antes de gastos e impuestos",
  ])
    assert.ok(html.includes(text), text);
  assert.doesNotMatch(html, /Avalista|9999|Saldo pendiente|cobrado|000000Z/);
  assert.match(html, /role="region" aria-label="Información de San Nicolás"/);
  assert.match(html, /href="\/properties\/1"/);
});

test("unknown valuation or rent stays explicit and a missing contract end is not invented", async () => {
  const html = await render({
    id: 2,
    name: "Local",
    current_value: null,
    leases: [
      {
        id: 2,
        status: "active",
        monthly_rent: null,
        start_date: null,
        end_date: null,
      },
    ],
  });
  for (const text of [
    "Sin valoración",
    "Sin importe registrado",
    "No disponible",
    "Inicio no registrado",
    "Sin fecha de fin registrada",
    "sin plazo registrado",
  ])
    assert.ok(html.includes(text), text);
  assert.doesNotMatch(html, /0,00|0 %|antes de gastos/);
});

test("a property without an active contract does not display ended contracts or zero rent as a fact", async () => {
  const html = await render({
    id: 3,
    name: "Casa",
    leases: [
      {
        status: "ended",
        monthly_rent: "999.00",
        start_date: "2020-01-01",
        end_date: "2021-01-01",
      },
    ],
  });
  assert.match(html, /Sin contrato activo/);
  assert.doesNotMatch(html, /999|Contratos activos|01\/01\/2020|Días de cobro/);
});

test("several contracts stay distinct, rent includes all active leases and the compact preview is bounded", async () => {
  const leases = Array.from({ length: 4 }, (_, i) => ({
    id: i + 1,
    status: "active",
    monthly_rent: "100.25",
    start_date: `2026-0${i + 1}-01`,
    end_date: null,
    participants: [{ name: `Inquilino ${i + 1}`, pivot: { role: "tenant" } }],
  }));
  const html = await render({
    id: 4,
    name: "Edificio",
    current_value: "100000.00",
    leases,
  });
  assert.ok(html.includes("401,00"));
  assert.match(html, /Inquilino 1/);
  assert.match(html, /Inquilino 2/);
  assert.doesNotMatch(html, /Inquilino 3|Inquilino 4|Días de cobro/);
  assert.match(html, /Y 2 más en la ficha/);
  assert.ok(html.includes("01/01/2026") && html.includes("01/02/2026"));
});
