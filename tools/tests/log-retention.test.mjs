import { test } from "node:test";
import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import { execFile } from "node:child_process";
import { promisify } from "node:util";
import { fileURLToPath } from "node:url";

const read = (path) =>
  readFile(new URL(`../../${path}`, import.meta.url), "utf8");
const exec = promisify(execFile);

test("all seven production services use native time-bounded host logging without exporting environment fields", async () => {
  // No Docker daemon, real .env file, provider or production connection required.
  const { stdout } = await exec(
    "docker",
    [
      "compose",
      "--env-file",
      "/dev/null",
      "-f",
      "docker-compose.production.yml",
      "config",
      "--format",
      "json",
    ],
    {
      cwd: fileURLToPath(new URL("../../", import.meta.url)),
      env: {
        ...process.env,
        APP_ENV_FILE: "/dev/null",
        DATABASE_ADMIN_PASSWORD: "synthetic-admin-only",
        DB_PASSWORD: "synthetic-app-only",
        PUBLIC_DOMAIN: "app.alquivo.com",
        ACME_EMAIL: "synthetic@example.test",
      },
      timeout: 10000,
    },
  );
  const { services } = JSON.parse(stdout);
  assert.deepEqual(Object.keys(services).sort(), [
    "backend",
    "clamav",
    "postgres",
    "proxy",
    "scheduler",
    "web",
    "worker",
  ]);
  for (const service of Object.values(services)) {
    assert.deepEqual(service.logging, {
      driver: "journald",
      options: { tag: "alquivo-{{.Name}}" },
    });
    assert.doesNotMatch(
      JSON.stringify(service.logging),
      /env|labels|json-file|syslog-address/,
    );
  }
});

test("journal retention has both time and space limits with bounded block rotation", async () => {
  const conf = await read("ops/server/60-alquivo-retention.conf");
  for (const line of [
    "[Journal]",
    "Storage=persistent",
    "MaxRetentionSec=30day",
    "MaxFileSec=1day",
    "SystemMaxUse=256M",
    "SystemKeepFree=1G",
    "RuntimeMaxUse=64M",
  ])
    assert.ok(conf.split("\n").includes(line), line);
});

test("hourly maintenance uses the native journal API and cannot modify documents, databases or Docker files", async () => {
  const service = await read("ops/server/alquivo-log-retention.service");
  assert.match(
    service,
    /^ExecStart=\/usr\/bin\/journalctl --rotate --vacuum-time=30d --vacuum-size=256M$/m,
  );
  assert.match(
    service,
    /^ReadWritePaths=-\/var\/log\/journal -\/run\/log\/journal$/m,
  );
  assert.match(service, /^ProtectSystem=strict$/m);
  assert.match(service, /^ProtectHome=true$/m);
  assert.doesNotMatch(
    service,
    /rm |truncate|copytruncate|\/var\/lib\/docker|\/opt\/alquivo|sh -c|docker /,
  );
  const timer = await read("ops/server/alquivo-log-retention.timer");
  assert.match(timer, /^OnUnitActiveSec=1h$/m);
  assert.match(timer, /^OnBootSec=10min$/m);
  assert.match(timer, /^WantedBy=timers.target$/m);
});
