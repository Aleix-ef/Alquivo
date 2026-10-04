import { test } from "node:test";
import assert from "node:assert/strict";
import { mkdtemp, readFile, rm } from "node:fs/promises";
import { execFile } from "node:child_process";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { fileURLToPath } from "node:url";
import { promisify } from "node:util";
import {
  legalDraft,
  legalVersion,
  legalPages,
  operator,
} from "../src/content/legal.js";

const execFileAsync = promisify(execFile);

test("external backup refuses missing or unmounted destinations before invoking Docker", async () => {
  const temporaryDirectory = await mkdtemp(
    join(tmpdir(), "alquivo-backup-guard-"),
  );
  try {
    const script = fileURLToPath(
      new URL("../../tools/backup-external.sh", import.meta.url),
    );
    for (const mount of [
      "",
      join(temporaryDirectory, "missing"),
      temporaryDirectory,
    ]) {
      await assert.rejects(
        execFileAsync("bash", [script], {
          env: { ...process.env, BACKUP_EXTERNAL_MOUNT: mount },
          timeout: 5000,
        }),
        (error) => {
          assert.equal(error.code, 1);
          assert.match(error.stderr, /BACKUP_EXTERNAL_MOUNT|NOT mounted/);
          assert.doesNotMatch(error.stderr, /docker|daemon/i);
          return true;
        },
      );
    }
  } finally {
    await rm(temporaryDirectory, { recursive: true, force: true });
  }
});

test("provider preparation cannot be mistaken for legal approval or a contracted server", () => {
  assert.equal(legalDraft, true);
  assert.equal(operator.reviewedForPublication, false);
  assert.match(
    operator.providers.find((p) => p.service.startsWith("Alojamiento")).name,
    /aún no contratado/,
  );
  assert.match(
    operator.providers.find((p) => p.service === "Copias de seguridad").name,
    /pendiente/,
  );
  assert.match(
    operator.providers.find((p) => p.service === "Buzón receptor de soporte")
      .name,
    /Gmail gratuito/,
  );
  assert.match(operator.backupRetention, /no hay todavía copias de producción/);
  const processing = legalPages.find((p) => p.name === "data-processing");
  assert.match(
    processing.sections
      .find((s) => s.id === "proveedores")
      .paragraphs.join(" "),
    /efectivamente contratados/,
  );
});

test("AI privacy distinguishes API storage from abuse retention without promising EU-only processing", () => {
  const text = legalPages
    .find((p) => p.name === "privacy")
    .sections.find((s) => s.id === "ia")
    .paragraphs.join(" ");
  assert.match(text, /store=false/);
  assert.match(text, /excepciones legales o de seguridad/);
  assert.match(
    text,
    /No se promete retención cero ni residencia exclusivamente europea/,
  );
  assert.match(text, /simulación local que no envía archivos/);
});

test("production example isolates the app from the waitlist and preserves launch gates and budget", async () => {
  const text = await readFile(
    new URL("../../.env.production.example", import.meta.url),
    "utf8",
  );
  for (const line of [
    "PUBLIC_DOMAIN=app.alquivo.com",
    "APP_URL=https://app.alquivo.com",
    "FRONTEND_URL=https://app.alquivo.com",
    "SANCTUM_STATEFUL_DOMAINS=app.alquivo.com",
    "VITE_SEO_INDEXABLE=false",
    "SESSION_COOKIE=alquivo-session",
    "SESSION_ENCRYPT=true",
    "SESSION_SECURE_COOKIE=true",
    "BILLING_ENABLED=false",
    "BETA_PROGRAM_ENABLED=true",
    "FISCALITY_ENABLED=false",
    "ASSISTANT_ENABLED=false",
    "ASSISTANT_VALIDATED=false",
    "AI_CHAT_PROFILE=fast",
    "AI_FALLBACK_PROFILE=complex",
    "AI_GLOBAL_MONTHLY_BUDGET_USD=5",
  ])
    assert.ok(text.split("\n").includes(line), line);
  assert.match(text, /^RESEND_API_KEY=$/m);
  assert.match(text, /^OPENAI_API_KEY=$/m);
  const backend = await readFile(
    new URL("../../backend/config/legal.php", import.meta.url),
    "utf8",
  );
  assert.ok(backend.includes(`'terms_version' => '${legalVersion}'`));
});
