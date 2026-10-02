import { test } from "node:test";
import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import { inflateSync } from "node:zlib";
import { compileTemplate, parse } from "@vue/compiler-sfc";

const file = (relative) => new URL(relative, import.meta.url);
const component = await readFile(
  file("../src/components/BrandLogo.vue"),
  "utf8",
);
const { descriptor, errors } = parse(component);
assert.deepEqual(errors, []);
const compiled = compileTemplate({
  source: descriptor.template.content,
  filename: "BrandLogo.vue",
  id: "brand-test",
  ssr: true,
  ssrCssVars: [],
});
assert.deepEqual(compiled.errors, []);
const moduleCode = compiled.code.replace(
  /from "(vue(?:\/server-renderer)?)"/g,
  (_, module) => `from ${JSON.stringify(import.meta.resolve(module))}`,
);
const { ssrRender } = await import(
  `data:text/javascript;base64,${Buffer.from(moduleCode).toString("base64")}`
);
function render(props) {
  const chunks = [];
  ssrRender(props, (html) => chunks.push(html), null, {});
  return chunks.join("");
}

// Read the checked-in PNGs without adding an image library to the application.
// Dimensions alone did not catch a cropped viewport with transparent bottom rows.
function pngPixel(png, x, y) {
  const width = png.readUInt32BE(16);
  assert.equal(png[24], 8, "8-bit PNG");
  assert.equal(png[28], 0, "non-interlaced PNG");
  const channels = { 2: 3, 6: 4 }[png[25]];
  assert.ok(channels, "RGB/RGBA PNG");
  const compressed = [];
  for (let offset = 8; offset < png.length;) {
    const length = png.readUInt32BE(offset);
    if (png.toString("ascii", offset + 4, offset + 8) === "IDAT")
      compressed.push(png.subarray(offset + 8, offset + 8 + length));
    offset += length + 12;
  }
  const raw = inflateSync(Buffer.concat(compressed));
  const stride = width * channels;
  let previous = Buffer.alloc(stride);
  for (let row = 0; row <= y; row++) {
    const start = row * (stride + 1);
    const filter = raw[start];
    assert.ok(filter <= 4, "supported PNG filter");
    const current = Buffer.alloc(stride);
    for (let index = 0; index < stride; index++) {
      const left = index >= channels ? current[index - channels] : 0;
      const above = previous[index];
      const diagonal = index >= channels ? previous[index - channels] : 0;
      const p = left + above - diagonal;
      const distances = [
        Math.abs(p - left),
        Math.abs(p - above),
        Math.abs(p - diagonal),
      ];
      const paeth =
        distances[0] <= distances[1] && distances[0] <= distances[2]
          ? left
          : distances[1] <= distances[2]
            ? above
            : diagonal;
      const predictor = [0, left, above, Math.floor((left + above) / 2), paeth][
        filter
      ];
      current[index] = (raw[start + 1 + index] + predictor) & 255;
    }
    previous = current;
  }
  const value = [...previous.subarray(x * channels, (x + 1) * channels)];
  return channels === 3 ? [...value, 255] : value;
}

test("the principal logo is the full vector wordmark, not the AL monogram", () => {
  const html = render({ compact: false, light: false });
  assert.match(html, /class="alquivo-wordmark"/);
  assert.match(html, /href="\/brand\/alquivo-wordmark.svg#wordmark"/);
  assert.doesNotMatch(html, /class="alquivo-symbol"/);
  assert.match(html, /role="img" aria-label="Alquivo"/);
  assert.match(html, /aria-hidden="true" focusable="false"/);
});

test("compact contexts explicitly use the shared AL favicon", () => {
  const html = render({ compact: true, light: true });
  assert.match(html, /class="alquivo-brand compact light"/);
  assert.match(html, /class="alquivo-symbol"/);
  assert.match(html, /href="\/favicon.svg\?v=wordmark-1#mark"/);
  assert.doesNotMatch(html, /class="alquivo-wordmark"/);
});

test("the shared artwork is font-independent, local and passive", async () => {
  for (const name of ["brand/alquivo-wordmark.svg", "favicon.svg"]) {
    const svg = await readFile(file(`../public/${name}`), "utf8");
    assert.doesNotMatch(
      svg,
      /<script|<text|<foreignObject|<image|\bon\w+=|https?:\/\/(?!www\.w3\.org)/i,
    );
    assert.match(svg, /<path\s/);
    assert.match(
      svg,
      name.startsWith("brand/") ? /id="wordmark"/ : /id="mark"/,
    );
  }
  const source = await readFile(
    file("../public/brand/alquivo-wordmark.svg"),
    "utf8",
  );
  const exported = await readFile(
    file("../../brand/alquivo-nombre.svg"),
    "utf8",
  );
  assert.equal(exported, source, "social/app wordmarks share the same source");
});

test("social and browser PNGs have the expected dimensions and valid signatures", async () => {
  for (const [relative, size] of [
    ["../../brand/alquivo-logo-redes.png", 1080],
    ["../public/favicon-32.png", 32],
    ["../public/apple-touch-icon.png", 180],
  ]) {
    const png = await readFile(file(relative));
    assert.equal(png.subarray(0, 8).toString("hex"), "89504e470d0a1a0a");
    assert.equal(png.readUInt32BE(16), size);
    assert.equal(png.readUInt32BE(20), size);
    assert.deepEqual(
      pngPixel(png, Math.floor(size / 2), size - 1),
      [8, 47, 64, 255],
      `${relative}: the artwork reaches the bottom, without a cropped viewport`,
    );
  }
});

test("browser icons are linked explicitly and exist locally", async () => {
  const html = await readFile(file("../index.html"), "utf8");
  assert.match(
    html,
    /rel="icon"[^>]*sizes="32x32"[^>]*href="\/favicon-32.png"/,
  );
  assert.match(
    html,
    /rel="icon"[^>]*type="image\/svg\+xml"[^>]*href="\/favicon.svg\?v=wordmark-1"/,
  );
  assert.match(
    html,
    /rel="apple-touch-icon"[^>]*sizes="180x180"[^>]*href="\/apple-touch-icon.png"/,
  );
});
