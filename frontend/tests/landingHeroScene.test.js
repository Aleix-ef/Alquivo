import { test } from "node:test";
import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import { compileScript, parse } from "@vue/compiler-sfc";
import { createRenderer, nextTick } from "vue";
import { demoRentals, demoMoney } from "../src/content/landingDemo.js";

const source = await readFile(
  new URL("../src/components/LandingHeroScene.vue", import.meta.url),
  "utf8",
);
const { descriptor, errors } = parse(source);
assert.deepEqual(errors, []);
const compiled = compileScript(descriptor, {
  id: "hero-scene-test",
  inlineTemplate: true,
  templateOptions: { compilerOptions: { hoistStatic: false } },
});
const moduleSource = compiled.content.replace(
  /from (["'])([^"']+)\1/g,
  (_, quote, specifier) =>
    `from ${JSON.stringify(specifier.startsWith(".") ? new URL(specifier, new URL("../src/components/", import.meta.url)).href : import.meta.resolve(specifier))}`,
);
const { default: Scene } = await import(
  `data:text/javascript;base64,${Buffer.from(moduleSource).toString("base64")}`
);

function mount() {
  const node = (type, text = "") => ({ type, text, props: {}, children: [] });
  const { createApp } = createRenderer({
    createElement: node,
    createText: (text) => node("#text", text),
    createComment: (text) => node("#comment", text),
    setText: (el, text) => {
      el.text = text;
    },
    setElementText: (el, text) => {
      el.text = text;
      el.children = [];
    },
    patchProp: (el, key, previous, value) => {
      el.props[key] = value;
    },
    insert(el, parent, anchor = null) {
      if (el.parent)
        el.parent.children.splice(el.parent.children.indexOf(el), 1);
      const index = anchor ? parent.children.indexOf(anchor) : -1;
      parent.children.splice(index < 0 ? parent.children.length : index, 0, el);
      el.parent = parent;
    },
    remove(el) {
      el.parent.children.splice(el.parent.children.indexOf(el), 1);
    },
    parentNode: (el) => el.parent,
    nextSibling: (el) =>
      el.parent?.children[el.parent.children.indexOf(el) + 1],
  });
  const root = node("root");
  const app = createApp(Scene);
  app.mount(root);
  const nodes = (el = root) => [el, ...el.children.flatMap(nodes)];
  const text = (el = root) =>
    nodes(el)
      .filter((n) => n.type !== "#comment")
      .map((n) => n.text)
      .join(" ");
  return { app, nodes, text };
}

test("architectural hero selects only synthetic properties and keeps amounts in sync", async () => {
  const m = mount();
  try {
    await nextTick();
    const buttons = m.nodes().filter((n) => n.type === "button");
    const choices = buttons.filter(
      (n) => n.props["aria-controls"] === "scene-property",
    );
    assert.equal(choices.length, 3);
    assert.ok(buttons.every((n) => n.props.type === "button"));
    assert.equal(choices[1].props["aria-pressed"], true);
    assert.match(m.text(), /Ejemplo/);
    for (const [i, rental] of demoRentals.entries()) {
      choices[i].props.onClick();
      await nextTick();
      const detail = m.nodes().find((n) => n.props.id === "scene-property");
      assert.equal(detail.props["aria-live"], "polite");
      assert.ok(m.text(detail).includes(rental.name));
      assert.ok(m.text(detail).includes(demoMoney(rental.receivedCents)));
      assert.ok(
        m
          .text(detail)
          .includes(demoMoney(rental.dueCents - rental.receivedCents)),
      );
      assert.equal(
        choices.filter((n) => n.props["aria-pressed"] === true).length,
        1,
      );
      const floors = m
        .nodes()
        .filter((n) => /scene-floor-\d/.test(n.props.class));
      assert.equal(
        floors.filter((n) => n.props.class.includes("is-selected")).length,
        1,
      );
      assert.ok(
        floors
          .find((n) => n.props.class.includes("is-selected"))
          .props.class.includes(`scene-floor-${i}`),
      );
    }
    const before = m.text();
    buttons
      .find((n) => n.props["aria-label"] === "Repetir animación del edificio")
      .props.onClick();
    await nextTick();
    assert.equal(
      m.text(),
      before,
      "Replaying the illustration must not change financial data or selection",
    );
  } finally {
    m.app.unmount();
  }
  assert.doesNotMatch(
    source,
    /fetch\s*\(|axios|useSession|useProduct|localStorage|setInterval|setTimeout/,
  );
});

test("landing keeps the demo anchor below the hero and styles available during prerender", async () => {
  const landing = await readFile(
    new URL("../src/views/BetaLandingView.vue", import.meta.url),
    "utf8",
  );
  const demo = await readFile(
    new URL("../src/components/LandingDemo.vue", import.meta.url),
    "utf8",
  );
  const main = await readFile(
    new URL("../src/main.js", import.meta.url),
    "utf8",
  );
  const css = await readFile(
    new URL("../src/landing-editorial.css", import.meta.url),
    "utf8",
  );
  assert.equal((landing.match(/id="demostracion"/g) || []).length, 1);
  assert.ok(
    landing.indexOf('id="demostracion"') >
      landing.indexOf("<LandingHeroScene />"),
  );
  assert.doesNotMatch(demo, /id="demostracion"/);
  assert.match(main, /import "\.\/landing-editorial\.css"/);
  assert.match(css, /prefers-reduced-motion: reduce/);
  assert.doesNotMatch(css, /\binfinite\b|scroll-snap|cursor:\s*none/);
  assert.match(css, /animation: none !important/);
  const router = await readFile(
    new URL("../src/router.js", import.meta.url),
    "utf8",
  );
  assert.match(router, /top: to\.name === "home" \? 100 : 24/);
});
