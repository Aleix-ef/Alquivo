import { test } from "node:test";
import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import { compileScript, parse } from "@vue/compiler-sfc";
import { baseParse } from "@vue/compiler-dom";

const source = await readFile(
  new URL("../src/views/AuthView.vue", import.meta.url),
  "utf8",
);
const { descriptor, errors } = parse(source);
assert.deepEqual(errors, []);
const compiled = compileScript(descriptor, {
  id: "portfolio-placeholder-test",
});
const form = compiled.scriptSetupAst
  .filter((node) => node.type === "VariableDeclaration")
  .flatMap((node) => node.declarations)
  .find((node) => node.id.name === "form");
const initialName = form.init.arguments[0].properties.find(
  (property) => property.key.name === "portfolio_name",
).value;
const template = baseParse(descriptor.template.content);
function findInput(node) {
  if (
    node.tag === "input" &&
    node.props.some(
      (prop) =>
        prop.name === "model" && prop.exp?.content === "form.portfolio_name",
    )
  )
    return node;
  return node.children?.map(findInput).find(Boolean);
}
const input = findInput(template);

test("the portfolio name starts empty, without a prefilled value to erase", () => {
  assert.equal(initialName.type, "StringLiteral");
  assert.equal(initialName.value, "");
});

test("the example is a translated native placeholder, not the saved field value", () => {
  assert.ok(input);
  const placeholder = input.props.find(
    (prop) => prop.arg?.content === "placeholder",
  );
  assert.equal(placeholder.name, "bind");
  assert.equal(placeholder.exp.content, "$t('nav.portfolio')");
  assert.ok(input.props.some((prop) => prop.name === "required"));
  assert.equal(
    input.props.find((prop) => prop.name === "maxlength").value.content,
    "100",
  );
  assert.ok(!input.props.some((prop) => prop.name === "value"));
});
