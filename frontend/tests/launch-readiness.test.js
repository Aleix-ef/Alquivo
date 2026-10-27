import { test } from "node:test";
import assert from "node:assert/strict";
import { createHash } from "node:crypto";
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

test("completed public copy has an editorial publication flag and accurate infrastructure", () => {
  assert.equal(legalDraft, false);
  assert.equal(operator.reviewedForPublication, true);
  assert.match(
    operator.providers.find((p) => p.service.startsWith("Alojamiento")).name,
    /OVH HISPANO S.L./,
  );
  assert.match(
    operator.providers.find((p) => p.service === "Copias de seguridad").name,
    /Hetzner Online GmbH/,
  );
  assert.match(
    operator.providers.find((p) => p.service === "Buzón receptor de soporte")
      .name,
    /OVHcloud Zimbra Starter/,
  );
  assert.match(operator.backupRetention, /30 días/);
  assert.match(operator.backupRetention, /última copia válida/);
  assert.match(operator.backupRetention, /restauración en un entorno aislado/);
  assert.match(
    operator.providers.find((p) => p.service.startsWith("Alojamiento"))
      .location,
    /Gravelines, Francia/,
  );
  assert.match(
    operator.providers.find((p) => p.service === "Copias de seguridad")
      .location,
    /Falkenstein, Alemania/,
  );
  const processing = legalPages.find((p) => p.name === "data-processing");
  assert.match(
    processing.sections
      .find((s) => s.id === "proveedores")
      .paragraphs.join(" "),
    /efectivamente contratados/,
  );
});

test("each provider explains its data scope and links to public official conditions without claiming private approval", () => {
  for (const provider of operator.providers) {
    assert.ok(provider.data.length > 30);
    assert.ok(provider.references.length >= 1);
    for (const reference of provider.references) {
      const url = new URL(reference.href);
      assert.equal(url.protocol, "https:");
      assert.equal(url.username, "");
      assert.equal(url.password, "");
    }
  }
  const content = legalPages
    .find((page) => page.name === "data-processing")
    .sections.find((section) => section.id === "proveedores")
    .paragraphs.join(" ");
  assert.match(content, /no acreditan por sí solos/);
  assert.equal(operator.reviewedForPublication, true);
});

test("test-only Gmail is not misrepresented as processing customer support data", () => {
  const cloudflare = operator.providers.find((p) =>
    p.name.startsWith("Cloudflare"),
  );
  assert.doesNotMatch(cloudflare.name, /Email Routing/);
  assert.doesNotMatch(cloudflare.service, /reenvío/);
  const mailbox = operator.providers.find(
    (p) => p.service === "Buzón receptor de soporte",
  );
  assert.match(mailbox.name, /OVH HISPANO S.L./);
  assert.match(mailbox.location, /Francia/);
  assert.match(mailbox.safeguards, /publica un acuerdo de tratamiento/);
  assert.doesNotMatch(mailbox.name, /Gmail/);
  assert.equal(
    operator.providers.some((p) => /Gmail|Google/.test(p.name)),
    false,
  );
  assert.match(operator.mailboxRetention, /revisión y supresión.*manuales/);
  assert.doesNotMatch(operator.mailboxRetention, /Gmail/);
  assert.match(operator.mailboxRetention, /propios plazos/);
  assert.doesNotMatch(
    operator.mailboxRetention,
    /borrado automático al cumplir el año\.$/,
  );
});

test("mailbox legal revision preserves the previous archived terms and captures public waitlist information", async () => {
  assert.notEqual(legalVersion, "2026-10-05");
  const previous = JSON.parse(
    await readFile(
      new URL("../../docs/legal-revisions/2026-10-05.json", import.meta.url),
      "utf8",
    ),
  );
  assert.equal(previous.version, "2026-10-05");
  assert.equal(
    previous.sha256,
    "b91826b3b46099ad11329a0b144eefaac2e695af038e265c22783bba5ab8baac",
  );
  const { sha256, ...previousPayload } = previous;
  assert.equal(
    createHash("sha256").update(JSON.stringify(previousPayload)).digest("hex"),
    sha256,
  );
  assert.match(
    previous.operator.providers.find(
      (p) => p.service === "Buzón receptor de soporte",
    ).name,
    /Gmail/,
  );
  const current = JSON.parse(
    await readFile(
      new URL(
        `../../docs/legal-revisions/${legalVersion}.json`,
        import.meta.url,
      ),
      "utf8",
    ),
  );
  assert.equal(current.draft, false);
  assert.match(
    current.sources["frontend/src/views/WaitlistLegalView.vue"],
    /OVHcloud Zimbra/,
  );
  assert.match(
    current.sources["frontend/src/waitlist/contract.js"],
    /2026-10-02.waitlist.v1/,
  );
  for (const file of [
    "backend/config/assistant.php",
    "backend/config/ai_documents.php",
  ])
    assert.equal(current.sources[file], previous.sources[file]);
});

test("public legal copy describes native log retention without inventing account controls or exact per-message expiry", () => {
  const content = JSON.stringify({ operator, pages: legalPages });
  assert.doesNotMatch(
    content,
    /Pendiente de completar|Borrador previo|antes de publicar|identificación pendiente/,
  );
  assert.match(operator.infrastructureLogRetention, /journal del VPS/);
  assert.match(operator.infrastructureLogRetention, /ventana de 30 días/);
  assert.match(operator.infrastructureLogRetention, /por bloques/);
  const openai = operator.providers.find((p) => p.name.startsWith("OpenAI"));
  assert.match(openai.name, /OpenAI Ireland Ltd/);
  assert.match(openai.name, /DPA publicado/);
  assert.match(openai.safeguards, /no equivale a retención cero/);
  assert.ok(
    openai.references.some(
      (r) => r.href === "https://openai.com/policies/data-processing-addendum/",
    ),
  );
  const hub = legalPages.find((p) => p.name === "legal");
  assert.deepEqual(
    hub.sections.find((s) => s.id === "documentos").links.map((l) => l.to),
    ["/terms", "/privacy", "/cookies", "/data-processing"],
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
