import { test } from "node:test";
import assert from "node:assert/strict";
import { validateSupportFiles } from "../src/supportFiles.js";

const image = { name: "captura.png", type: "image/png", size: 1000 };
test("support attachments have strict format, size and count limits", () => {
  assert.equal(validateSupportFiles([]), "");
  assert.equal(validateSupportFiles([image]), "");
  assert.equal(
    validateSupportFiles([
      { name: "factura.pdf", type: "application/pdf", size: 2 * 1024 * 1024 },
    ]),
    "",
  );
  assert.match(validateSupportFiles(Array(4).fill(image)), /3 archivos/);
  assert.match(
    validateSupportFiles([{ ...image, size: 2 * 1024 * 1024 + 1 }]),
    /2 MB/,
  );
  assert.match(
    validateSupportFiles([
      { ...image, name: "script.svg", type: "image/svg+xml" },
    ]),
    /Solo se admiten/,
  );
  assert.match(
    validateSupportFiles([{ ...image, name: "imagen.png.exe" }]),
    /Solo se admiten/,
  );
  assert.match(
    validateSupportFiles([{ ...image, type: "text/html" }]),
    /Solo se admiten/,
  );
});
