import { test } from "node:test";
import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";

const css = await readFile(
  new URL("../src/waitlist/waitlist.css", import.meta.url),
  "utf8",
);

test("beta viewport gutters cannot be combined with the generic section width cap", () => {
  const rule = css.match(/\.waitlist-page \.waitlist-beta\s*\{([^}]+)\}/)?.[1];
  assert.ok(rule, "the beta section has its own layout rule");
  assert.match(rule, /max-width:\s*none\s*;/);
  assert.match(rule, /width:\s*100%\s*;/);
  assert.match(
    rule,
    /grid-template-columns:\s*minmax\(0, 1fr\) minmax\(0, 1fr\)/,
  );
  assert.match(
    rule,
    /padding-inline:\s*max\(1\.5rem, calc\(\(100vw - 1184px\) \/ 2\)\)/,
  );
  assert.match(css, /\.waitlist-beta-heading\s*\{\s*min-width:\s*0/);
  const tablet = css.slice(
    css.indexOf("@media (max-width: 820px)"),
    css.indexOf("@media (max-width: 680px)"),
  );
  assert.match(
    tablet,
    /\.waitlist-page \.waitlist-beta\s*\{\s*grid-template-columns:\s*minmax\(0, 1fr\)/,
  );
});
