import { test } from "node:test";
import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import { compileScript, parse } from "@vue/compiler-sfc";
import { createRenderer, h, nextTick } from "vue";
import {
  demoRentals,
  demoExpenses,
  demoSummary,
  demoPending,
  demoMoney,
  publicAssistantState,
} from "../src/content/landingDemo.js";

test("the public illustration reconciles paid, partial, outstanding and expenses in integer cents", () => {
  assert.deepEqual(demoSummary, {
    receivedCents: 105000,
    pendingCents: 35000,
    expenseCents: 13000,
  });
  assert.equal(
    demoRentals.reduce((sum, rental) => sum + rental.dueCents, 0),
    demoSummary.receivedCents + demoSummary.pendingCents,
  );
  assert.equal(
    demoPending.reduce((sum, rental) => sum + rental.cents, 0),
    demoSummary.pendingCents,
  );
  assert.deepEqual(
    demoPending.map((rental) => rental.cents),
    [25000, 10000],
  );
  for (const row of [...demoRentals, ...demoExpenses, ...demoPending])
    assert.ok(Object.isFrozen(row));
  assert.ok(Object.isFrozen(demoSummary));
  assert.match(demoMoney(6250), /62,50/);
});

test("SSR, loading and API errors must never be marketed as assistant unavailability", () => {
  assert.equal(publicAssistantState(), null);
  assert.equal(
    publicAssistantState({
      loaded: false,
      error: false,
      features: { assistant: false },
    }),
    null,
  );
  assert.equal(
    publicAssistantState({
      loaded: true,
      error: true,
      features: { assistant: false },
    }),
    null,
  );
  assert.equal(
    publicAssistantState({ loaded: true, error: false, features: {} }),
    null,
  );
  assert.equal(
    publicAssistantState({
      loaded: true,
      error: false,
      features: { assistant: true },
    }),
    true,
  );
  assert.equal(
    publicAssistantState({
      loaded: true,
      error: false,
      features: { assistant: false },
    }),
    false,
  );
});

const source = await readFile(
  new URL("../src/components/LandingDemo.vue", import.meta.url),
  "utf8",
);
const { descriptor, errors } = parse(source);
assert.deepEqual(errors, []);
const compiled = compileScript(descriptor, {
  id: "landing-demo-test",
  inlineTemplate: true,
});
const moduleSource = compiled.content.replace(
  /from (["'])([^"']+)\1/g,
  (_, quote, specifier) =>
    `from ${JSON.stringify(specifier.startsWith(".") ? new URL(specifier, new URL("../src/components/", import.meta.url)).href : import.meta.resolve(specifier))}`,
);
const { default: LandingDemo } = await import(
  `data:text/javascript;base64,${Buffer.from(moduleSource).toString("base64")}`
);

// Exercise the real reactive component with Vue's host renderer, without network or account data.
function mountedDemo() {
  const node = (type, text = "") => ({ type, text, props: {}, children: [] });
  const { createApp } = createRenderer({
    createElement: node,
    createText: (text) => node("#text", text),
    createComment: (text) => node("#comment", text),
    setText: (element, text) => (element.text = text),
    setElementText: (element, text) => {
      element.text = text;
      element.children = [];
    },
    patchProp: (element, key, previous, value) => (element.props[key] = value),
    insert(element, parent, anchor = null) {
      if (element.parent)
        element.parent.children.splice(
          element.parent.children.indexOf(element),
          1,
        );
      const index = anchor ? parent.children.indexOf(anchor) : -1;
      parent.children.splice(
        index < 0 ? parent.children.length : index,
        0,
        element,
      );
      element.parent = parent;
    },
    remove(element) {
      element.parent.children.splice(
        element.parent.children.indexOf(element),
        1,
      );
    },
    parentNode: (element) => element.parent,
    nextSibling: (element) =>
      element.parent?.children[element.parent.children.indexOf(element) + 1],
  });
  const container = node("root");
  const app = createApp({ render: () => h(LandingDemo) });
  app.mount(container);
  const nodes = (element = container) => [
    element,
    ...element.children.flatMap((child) => nodes(child)),
  ];
  const text = () =>
    nodes()
      .filter((element) => element.type !== "#comment")
      .map((element) => element.text)
      .join(" ");
  return { app, nodes, text };
}

test("the real demo switches with native non-submit buttons and never simulates a live AI call", async () => {
  const m = mountedDemo();
  try {
    const buttons = m.nodes().filter((element) => element.type === "button");
    assert.equal(buttons.length, 2);
    assert.ok(buttons.every((button) => button.props.type === "button"));
    assert.equal(buttons[0].props["aria-pressed"], true);
    assert.match(m.text(), /Pagado/);
    assert.match(m.text(), /Parcial/);
    assert.match(m.text(), /Pendiente/);
    buttons[1].props.onClick();
    await nextTick();
    assert.equal(buttons[0].props["aria-pressed"], false);
    assert.equal(buttons[1].props["aria-pressed"], true);
    assert.match(m.text(), /Respuesta de ejemplo/);
    for (const cents of [35000, 25000, 10000])
      assert.ok(m.text().includes(demoMoney(cents)));
    assert.match(m.text(), /no consulta tus datos ni ejecuta la IA/);
    assert.equal(
      m
        .nodes()
        .find(
          (element) => element.props.id === buttons[1].props["aria-controls"],
        ).props["aria-live"],
      "polite",
    );
    buttons[0].props.onClick();
    await nextTick();
    assert.match(m.text(), /Parcial/);
    assert.doesNotMatch(m.text(), /Respuesta de ejemplo/);
  } finally {
    m.app.unmount();
  }
  assert.doesNotMatch(
    source,
    /fetch\s*\(|axios|useSession|useProduct|localStorage/,
  );
});
