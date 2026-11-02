import { test } from "node:test";
import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import { parse } from "@vue/compiler-sfc";

const source = await readFile(
  new URL("../src/views/DashboardView.vue", import.meta.url),
  "utf8",
);
const { descriptor } = parse(source);
const entries = [];
function visit(node, parent = null) {
  entries.push({ node, parent });
  for (const child of node.children || []) visit(child, node);
}
visit(descriptor.template.ast);
const hasAttribute = (node, name, value) =>
  node.props?.some(
    (prop) => prop.name === name && (!value || prop.value?.content === value),
  );
const hasClass = (node, name) =>
  node.props?.some(
    (prop) =>
      prop.name === "class" && prop.value?.content.split(/\s+/).includes(name),
  );

test("the visual portfolio appears once before finances and alerts in the real template reading order", () => {
  const scenes = entries.filter(
    ({ node }) => node.tag === "PortfolioOverviewScene",
  );
  assert.equal(scenes.length, 1);
  const overview = entries.find(({ node }) =>
    hasClass(node, "dashboard-overview"),
  );
  assert.ok(overview);
  assert.equal(scenes[0].parent, overview.parent);
  const siblings = overview.parent.children;
  assert.ok(siblings.indexOf(scenes[0].node) < siblings.indexOf(overview.node));
});

test("monthly metrics remain inside wealth and the compact alert panel remains beside it", () => {
  const overview = entries.find(({ node }) =>
    hasClass(node, "dashboard-overview"),
  ).node;
  const alerts = entries.find(({ node }) => node.tag === "AttentionPanel");
  assert.equal(alerts.parent, overview);
  assert.ok(hasAttribute(alerts.node, "compact"));
  const finances = entries.find(({ node }) =>
    hasClass(node, "dashboard-overview-finances"),
  );
  assert.equal(finances.parent, overview);
  const wealth = entries.find(
    ({ node, parent }) =>
      parent === finances.node && hasClass(node, "wealth-card"),
  );
  assert.ok(wealth);
  const monthly = entries.find(({ node }) =>
    hasAttribute(node, "aria-label", "Indicadores del mes"),
  );
  assert.equal(monthly.parent, wealth.node);
});

test("the populated summary has no recurring AI or next-step banners", () => {
  for (const name of ["dashboard-ai-intro", "dashboard-next-step"])
    assert.equal(
      entries.some(({ node }) => hasClass(node, name)),
      false,
    );
});

test("first-property onboarding and the optional guided tour are preserved", () => {
  assert.ok(entries.some(({ node }) => hasClass(node, "dashboard-welcome")));
  assert.ok(
    entries.some(
      ({ node }) =>
        node.tag === "RouterLink" &&
        hasAttribute(node, "to", "/properties?new=1"),
    ),
  );
  assert.ok(
    entries.some(
      ({ node }) =>
        node.tag === "button" &&
        node.props.some(
          (prop) =>
            prop.name === "on" &&
            prop.arg?.content === "click" &&
            prop.exp?.content === "startTour()",
        ),
    ),
  );
});

test("the side panel is dedicated to properties and does not expand the name list", async () => {
  const sceneSource = await readFile(
    new URL("../src/components/PortfolioOverviewScene.vue", import.meta.url),
    "utf8",
  );
  const { descriptor: scene } = parse(sceneSource);
  const sceneEntries = [];
  function walk(node, ancestors = []) {
    sceneEntries.push({ node, ancestors });
    for (const child of node.children || []) walk(child, [...ancestors, node]);
  }
  walk(scene.template.ast);
  assert.equal(
    sceneEntries.some(({ node }) =>
      hasClass(node, "portfolio-scene-chat-host"),
    ),
    false,
  );
  assert.doesNotMatch(sceneSource, /assistant-dock|assistantAvailable/);
  assert.match(
    sceneSource,
    /sceneSelectionId\(visible\.value, selectedId\.value\)/,
  );
  const details = sceneEntries.filter(
    ({ node }) => node.tag === "PortfolioPropertyDetails",
  );
  assert.equal(details.length, 1);
  assert.ok(
    details[0].ancestors.some((node) =>
      hasClass(node, "portfolio-scene-panel"),
    ),
  );
  assert.equal(
    details[0].ancestors.some((node) =>
      hasClass(node, "portfolio-scene-choice"),
    ),
    false,
  );
  assert.equal(
    sceneEntries.some(
      ({ node }) =>
        node.tag === "button" &&
        hasAttribute(node, "aria-label", "Cerrar datos del inmueble"),
    ),
    false,
  );
});

test("the mascot launcher opens the only guarded assistant and preserves the modal confirmation flow", async () => {
  const shell = await readFile(
    new URL("../src/views/ShellView.vue", import.meta.url),
    "utf8",
  );
  const widget = await readFile(
    new URL("../src/components/AssistantWidget.vue", import.meta.url),
    "utf8",
  );
  const shellEntries = [];
  function walk(node) {
    shellEntries.push(node);
    for (const child of node.children || []) walk(child);
  }
  walk(parse(shell).descriptor.template.ast);
  const widgets = shellEntries.filter((node) => node.tag === "AssistantWidget");
  assert.equal(widgets.length, 1);
  const launchers = shellEntries.filter((node) =>
    hasClass(node, "assistant-launcher"),
  );
  assert.equal(launchers.length, 1);
  const launcher = launchers[0];
  assert.equal(launcher.tag, "button");
  assert.ok(hasAttribute(launcher, "type", "button"));
  assert.ok(hasAttribute(launcher, "aria-label", "Abrir Alquivo AI"));
  assert.ok(hasAttribute(launcher, "data-tour", "ai"));
  assert.ok(launcher.children.some((node) => node.tag === "AssistantMascot"));
  assert.ok(
    launcher.props.some(
      (prop) => prop.name === "show" && prop.exp?.content === "!assistantOpen",
    ),
  );
  assert.ok(
    launcher.props.some(
      (prop) =>
        prop.name === "on" &&
        prop.arg?.content === "click" &&
        prop.exp?.content === "assistant?.toggle()",
    ),
  );
  assert.ok(
    launcher.props.some(
      (prop) =>
        prop.name === "if" &&
        prop.exp?.content === "product.accountFeatures.assistant",
    ),
  );
  assert.doesNotMatch(widget, /docked|dockTarget|assistant-dock/);
  assert.match(widget, /<Teleport to="body">/);
  assert.match(widget, /role="dialog"\s+aria-modal="true"/);
  assert.match(widget, /<AssistantActionPreview/);
  assert.match(widget, /useDialog\(open, panel, close\)/);
  assert.equal((widget.match(/v-model="input"/g) || []).length, 1);
  assert.ok(
    widgets[0].props.some(
      (prop) =>
        prop.name === "if" &&
        prop.exp?.content === "product.accountFeatures.assistant",
    ),
  );
});

test("wealth shows existing contracted-rent and occupancy facts separately from paid monthly metrics", () => {
  const wealth = entries.find(({ node }) => hasClass(node, "wealth-card")).node;
  const activity = entries.find(({ node }) =>
    hasClass(node, "wealth-activity"),
  );
  assert.equal(activity.parent, wealth);
  assert.ok(
    hasAttribute(activity.node, "aria-label", "Actividad de los alquileres"),
  );
  assert.match(source, /percent\(metrics\.occupancy_rate\)/);
  assert.match(
    source,
    /metrics\.contracted_rent == null\s*\? "—"\s*:\s*money\(metrics\.contracted_rent\)/,
  );
  assert.match(source, /Ingresos cobrados/);
  assert.match(source, /Gastos pagados/);
  assert.match(source, /value: money\(metrics\.value\.monthly_income\)/);
});
