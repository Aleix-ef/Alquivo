#!/usr/bin/env node
// Archives public legal content only; never reads .env, accounts or credentials.
import { readFile, mkdir, writeFile } from "node:fs/promises";
import { createHash } from "node:crypto";
import { fileURLToPath } from "node:url";
import path from "node:path";
import {
  legalVersion,
  legalUpdatedAt,
  legalDraft,
  legalPages,
  operator,
} from "../frontend/src/content/legal.js";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const paths = [
  "frontend/src/content/legal.js",
  "frontend/src/content/legalOperator.js",
  "frontend/src/components/AssistantWidget.vue",
  "frontend/src/views/DocumentImportView.vue",
  "backend/config/legal.php",
  "backend/config/assistant.php",
  "backend/config/ai_documents.php",
];
const sources = Object.fromEntries(
  await Promise.all(
    paths.map(async (file) => [
      file,
      await readFile(path.join(root, file), "utf8"),
    ]),
  ),
);
if (
  !sources["backend/config/legal.php"].includes(
    `'terms_version' => '${legalVersion}'`,
  )
) {
  throw new Error("Frontend/backend legal versions differ; archive refused.");
}
if (!/^\d{4}-\d{2}-\d{2}(?:-r\d+)?$/.test(legalVersion))
  throw new Error("Invalid archive version.");
// Archive the actual notices, not unrelated UI/limits whose changes should not
// require a new contractual acceptance. Fail closed if the notice is moved.
for (const [file, start, end] of [
  [
    "frontend/src/components/AssistantWidget.vue",
    '<div v-else-if="!enabled" class="assistant-empty">',
    '<div v-else-if="!messages.length"',
  ],
  [
    "frontend/src/views/DocumentImportView.vue",
    '<section v-if="!settings.accepted" class="import-panel">',
    "</section>",
  ],
]) {
  const from = sources[file].indexOf(start);
  const to = sources[file].indexOf(end, from + start.length);
  if (from < 0 || to < 0)
    throw new Error(`Cannot extract consent notice: ${file}`);
  sources[file] = sources[file].slice(from, to);
}
for (const file of [
  "backend/config/assistant.php",
  "backend/config/ai_documents.php",
]) {
  const notice = sources[file].match(/'notice_version' => '[^']+'/)?.[0];
  if (!notice) throw new Error(`Cannot extract notice version: ${file}`);
  sources[file] = notice;
}
const payload = {
  version: legalVersion,
  updatedAt: legalUpdatedAt,
  draft: legalDraft,
  operator,
  pages: legalPages,
  sources,
};
const sha256 = createHash("sha256")
  .update(JSON.stringify(payload))
  .digest("hex");
const content = JSON.stringify({ sha256, ...payload }, null, 2) + "\n";
const destination = path.join(
  root,
  "docs/legal-revisions",
  `${legalVersion}.json`,
);
let existing;
try {
  existing = await readFile(destination, "utf8");
} catch (error) {
  if (error.code !== "ENOENT") throw error;
}
if (existing !== undefined) {
  if (existing !== content)
    throw new Error(
      "Published/archived version changed. Increment the version; existing archive will NOT be overwritten.",
    );
} else if (process.argv.includes("--check")) {
  throw new Error(
    "Current legal revision has no archive. Run node tools/archive-legal.mjs.",
  );
} else {
  await mkdir(path.dirname(destination), { recursive: true });
  await writeFile(destination, content, { flag: "wx", mode: 0o644 });
}
console.log(
  `Legal revision ${legalVersion}: archive verified (${legalDraft ? "DRAFT, not approved for publication" : "publication flag set"}), sha256 ${sha256}`,
);
