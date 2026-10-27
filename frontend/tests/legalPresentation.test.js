import { test } from "node:test";
import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import { compileScript, parse } from "@vue/compiler-sfc";
import { createSSRApp, h } from "vue";
import { renderToString } from "vue/server-renderer";
import { i18n } from "../src/i18n.js";

// Render the real Vue components, without a browser, session or provider calls.
async function renderComponent(name, props = {}) {
  const source = await readFile(
    new URL(`../src/components/${name}.vue`, import.meta.url),
    "utf8",
  );
  const { descriptor } = parse(source);
  const { content } = compileScript(descriptor, {
    id: name,
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
  const app = createSSRApp(component, props);
  app.component("RouterLink", {
    props: ["to"],
    setup:
      (props, { slots }) =>
      () =>
        h("a", { href: props.to }, slots.default?.()),
  });
  app.config.globalProperties.$t = (key) => i18n.global.t(key);
  return renderToString(app);
}

test("normal footers expose exactly two clear links and the legal page exposes all documents", async () => {
  const compact = await renderComponent("LegalLinks");
  assert.equal((compact.match(/<a\b/g) || []).length, 2);
  assert.match(compact, /href="\/privacy"[^>]*>Privacidad/);
  assert.match(compact, /href="\/legal"[^>]*>Información legal/);
  const expanded = await renderComponent("LegalLinks", { expanded: true });
  assert.equal((expanded.match(/<a\b/g) || []).length, 5);
  for (const path of [
    "legal",
    "terms",
    "privacy",
    "cookies",
    "data-processing",
  ])
    assert.ok(expanded.includes(`href="/${path}"`));
});

test("registration and support privacy information is available behind a closed disclosure", async () => {
  for (const context of ["register", "support"]) {
    const html = await renderComponent("PrivacyNotice", { context });
    assert.match(html, /<details class="privacy-first-layer">/);
    assert.doesNotMatch(html, /<details[^>]*\bopen\b|type="checkbox"/);
    assert.match(html, /<summary>Información de privacidad<\/summary>/);
    assert.match(html, /Responsable:.*Aleix Escañuela Fresneda/s);
    assert.match(html, /href="\/privacy"/);
    assert.match(
      html,
      context === "register" ? /ejecutar el contrato/ : /atender esta consulta/,
    );
  }
});

test("forms keep explicit terms acceptance but do not require a blanket support privacy checkbox", async () => {
  const read = (path) =>
    readFile(new URL(`../src/${path}`, import.meta.url), "utf8");
  const auth = await read("views/AuthView.vue");
  assert.match(
    auth,
    /v-model="form\.terms_accepted"\s+type="checkbox"\s+required/,
  );
  assert.match(auth, /terms_version: legalVersion/);
  for (const view of ["views/AuthView.vue", "views/RecoveryView.vue"])
    assert.match(await read(view), /<LegalLinks class="auth-legal-links"/);
  for (const component of ["SupportChat", "SupportForm"]) {
    const source = await read(`components/${component}.vue`);
    assert.match(source, /<PrivacyNotice[^>]*context="support"/);
    assert.doesNotMatch(source, /privacy_acknowledged|support-consent/);
  }
  // Optional purposes retain their own unselected choice.
  const quality = await read("components/AssistantQualityConsent.vue");
  assert.match(quality, /accepted = ref\(false\)/);
  const waitlist = await read("views/WaitlistLandingView.vue");
  assert.match(waitlist, /name="consent"\s+type="checkbox"\s+required/);
});
