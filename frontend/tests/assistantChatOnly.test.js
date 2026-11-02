import { test } from "node:test";
import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import { parse } from "@vue/compiler-sfc";

const source = await readFile(
  new URL("../src/components/AssistantWidget.vue", import.meta.url),
  "utf8",
);
const { descriptor, errors } = parse(source);
const entries = [];
function visit(node) {
  entries.push(node);
  for (const child of node.children || []) visit(child);
}
visit(descriptor.template.ast);
const hasClass = (node, name) =>
  node.props?.some(
    (prop) =>
      prop.name === "class" && prop.value?.content.split(/\s+/).includes(name),
  );

test("the assistant contains only its chat, without guide tabs or dead help controls", () => {
  assert.deepEqual(errors, []);
  const chats = entries.filter((node) => hasClass(node, "assistant-chat"));
  assert.equal(chats.length, 1);
  assert.equal(
    chats[0].props.some((prop) => ["if", "show"].includes(prop.name)),
    false,
  );
  for (const name of [
    "assistant-tabs",
    "assistant-help-scroll",
    "assistant-help-action",
  ])
    assert.equal(
      entries.some((node) => hasClass(node, name)),
      false,
    );
  assert.doesNotMatch(source, /HelpGuides|\btab\b|Cómo usar Alquivo/);
  assert.equal((source.match(/v-model="input"/g) || []).length, 1);
});

test("removing guides preserves privacy, guarded sending, recovery and separate action previews", () => {
  assert.match(source, /useDialog\(open, panel, close\)/);
  assert.ok(entries.some((node) => node.tag === "AssistantActionPreview"));
  assert.ok(entries.some((node) => hasClass(node, "assistant-run-recovery")));
  const composer = entries.find((node) => node.tag === "textarea");
  const disabled = composer.props.find(
    (prop) => prop.name === "bind" && prop.arg?.content === "disabled",
  ).exp.content;
  for (const guard of [
    "sending",
    "loading",
    "historyFailed",
    "!available",
    "!enabled",
    "recoverableTurn",
    "quotaExhausted",
  ])
    assert.ok(disabled.includes(guard), guard);
  assert.ok(
    entries.some(
      (node) =>
        node.tag === "RouterLink" &&
        node.props.some(
          (prop) => prop.name === "to" && prop.value?.content === "/privacy",
        ),
    ),
  );
  assert.match(source, /message\.metadata\?\.kind === 'support_required'/);
  assert.doesNotMatch(source, /\/proposals\/.*\/confirm/);
});
