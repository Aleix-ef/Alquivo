import { test } from "node:test";
import assert from "node:assert/strict";
import { readFile, readdir } from "node:fs/promises";
import { compileScript, parse } from "@vue/compiler-sfc";
import { createRenderer, h, nextTick, reactive } from "vue";

const source = await readFile(
  new URL("../src/components/PasswordInput.vue", import.meta.url),
  "utf8",
);
const { descriptor, errors } = parse(source);
assert.deepEqual(errors, []);
const compiled = compileScript(descriptor, {
  id: "password-test",
  inlineTemplate: true,
});
const moduleCode = compiled.content.replace(
  /from "(vue|@lucide\/vue)"/g,
  (_, name) => `from ${JSON.stringify(import.meta.resolve(name))}`,
);
const { default: PasswordInput } = await import(
  `data:text/javascript;base64,${Buffer.from(moduleCode).toString("base64")}`
);

// A Vue host renderer exercises reactive controls without a browser or new dependencies.
function mounted(initial = {}) {
  const state = reactive({ modelValue: "synthetic password", ...initial });
  const updates = [];
  const container = { children: [] };
  const node = (type, text = "") => ({
    type,
    text,
    props: {},
    children: [],
    selectionStart: 3,
    selectionEnd: 6,
    focus() {
      this.focused = true;
    },
    setSelectionRange(start, end) {
      this.selectionStart = start;
      this.selectionEnd = end;
    },
  });
  const { createApp } = createRenderer({
    createElement: node,
    createText: (text) => node("#text", text),
    createComment: (text) => node("#comment", text),
    setText: (element, text) => (element.text = text),
    setElementText: (element, text) => (element.text = text),
    patchProp: (element, key, previous, value) => (element.props[key] = value),
    insert(element, parent, anchor = null) {
      if (element.parent) {
        element.parent.children.splice(
          element.parent.children.indexOf(element),
          1,
        );
      }
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
  const app = createApp({
    render: () =>
      h(PasswordInput, {
        ...state,
        "onUpdate:modelValue": (value) => {
          updates.push(value);
          state.modelValue = value;
        },
      }),
  });
  app.mount(container);
  const input = container.children[0].children.find((n) => n.type === "input");
  const button = container.children[0].children.find(
    (n) => n.type === "button",
  );
  return { app, state, input, button, updates };
}

test("passwords are hidden initially and native validation/autofill attributes reach the input", () => {
  const m = mounted({
    id: "new-password",
    required: true,
    minlength: 8,
    autocomplete: "new-password",
  });
  assert.equal(m.input.props.type, "password");
  assert.equal(m.input.props.required, true);
  assert.equal(m.input.props.minlength, 8);
  assert.equal(m.input.props.autocomplete, "new-password");
  assert.equal(
    m.button.props.type,
    "button",
    "toggle must not submit the form",
  );
  assert.equal(m.button.props["aria-controls"], "new-password");
  assert.equal(m.button.props["aria-pressed"], false);
  assert.equal(m.button.props["aria-label"], "Mostrar contraseña");
  m.app.unmount();
});

test("show/hide retains the exact password, focus and caret without emitting a value change", async () => {
  const m = mounted();
  await m.button.props.onClick();
  assert.equal(m.input.props.type, "text");
  assert.equal(m.input.props.value, "synthetic password");
  assert.equal(m.input.focused, true);
  assert.equal(m.input.selectionStart, 3);
  assert.equal(m.input.selectionEnd, 6);
  assert.equal(m.button.props["aria-label"], "Ocultar contraseña");
  assert.deepEqual(m.updates, []);
  await m.button.props.onClick();
  assert.equal(m.input.props.type, "password");
  assert.equal(m.button.props["aria-pressed"], false);
  m.app.unmount();
});

test("typing forwards the value without trimming and clearing restores hidden mode", async () => {
  const m = mounted();
  m.input.props.onInput({ target: { value: " with spaces " } });
  await nextTick();
  assert.deepEqual(m.updates, [" with spaces "]);
  await m.button.props.onClick();
  m.state.modelValue = "";
  await nextTick();
  assert.equal(m.input.props.type, "password");
  assert.equal(m.input.props.value, "");
  m.app.unmount();
});

test("disabled fields cannot be revealed and changing the autofill context hides them", async () => {
  const m = mounted({ disabled: true });
  await m.button.props.onClick();
  assert.equal(m.input.props.type, "password");
  assert.equal(m.button.props.disabled, true);
  m.state.disabled = false;
  await nextTick();
  await m.button.props.onClick();
  assert.equal(m.input.props.type, "text");
  m.state.autocomplete = "new-password";
  await nextTick();
  assert.equal(m.input.props.type, "password");
  m.app.unmount();
});

test("all password forms use the shared control instead of a bare password input", async () => {
  let fields = 0;
  for (const directory of ["views", "components"]) {
    const base = new URL(`../src/${directory}/`, import.meta.url);
    for (const filename of await readdir(base)) {
      if (!filename.endsWith(".vue") || filename === "PasswordInput.vue")
        continue;
      const content = await readFile(new URL(filename, base), "utf8");
      assert.doesNotMatch(content, /type=["']password["']/, filename);
      fields += (content.match(/<PasswordInput\b/g) || []).length;
    }
  }
  assert.equal(
    fields,
    11,
    "login/register, recovery, settings and two-factor forms",
  );
});
