import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { mkdtemp, mkdir, writeFile, readFile, rm, stat, utimes, symlink, readdir } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const exec = promisify(execFile);
const root = fileURLToPath(new URL('../../', import.meta.url));
async function fixture(run) {
  const directory = await mkdtemp(join(tmpdir(), 'alquivo-backup-test-'));
  const bin = join(directory, 'bin');
  const work = join(directory, 'work');
  const repository = join(directory, 'repository');
  await Promise.all([mkdir(bin), mkdir(work), mkdir(repository)]);
  await writeFile(join(repository, 'config'), 'synthetic repository');
  const password = join(directory, 'backup.password');
  await writeFile(password, 'SYNTHETIC_TEST_SECRET_NOT_FOR_OUTPUT', { mode: 0o600 });
  await writeFile(join(bin, 'docker'), `#!/usr/bin/env bash
set -eu
printf '%s\\n' "$*" >> "$TRACE"
if [[ "$1" == compose ]]; then
  case " $* " in
    *" ps --services --status running "*) printf 'backend\\nworker\\n' ;;
    *" start --wait "*) [[ "$FAIL_START" != 1 ]] || exit 23 ;;
    *" exec -T postgres "*) [[ "$FAIL_DUMP" != 1 ]] || exit 17; printf 'synthetic dump' ;;
    *" tar -C "*) printf 'synthetic archive' ;;
    *" recovery-probe.php "*) printf 'synthetic encrypted probe' ;;
  esac
elif [[ "$1" == run && " $* " == *" backup /data "* ]]; then
  [[ "$FAIL_UPLOAD" != 1 ]] || exit 22
fi
`, { mode: 0o700 });
  await writeFile(join(bin, 'mountpoint'), '#!/usr/bin/env bash\nexit "${MOUNT_RESULT:-0}"\n', { mode: 0o700 });
  const env = {
    ...process.env, PATH: `${bin}:${process.env.PATH}`, TRACE: join(directory, 'trace'),
    COMPOSE_FILE: 'docker-compose.production.yml', BACKUP_WORK_DIR: work,
    BACKUP_REPOSITORY_DIR: repository, BACKUP_PASSWORD_FILE: password,
    BACKUP_EXTERNAL_MOUNT: directory,
    FAIL_START: '0', FAIL_DUMP: '0', FAIL_UPLOAD: '0',
  };
  try { await run({ directory, work, password, env, trace: async () => readFile(env.TRACE, 'utf8') }); }
  finally { await rm(directory, { recursive: true, force: true }); }
}
const script = (name) => resolve(root, 'tools', name);

test('consistent staging resumes only previously running services before remote upload', () => fixture(async ({ work, env, trace }) => {
  await exec('bash', [script('backup.sh')], { env });
  const calls = await trace();
  assert.match(calls, /stop backend worker\n/);
  assert.match(calls, /start --wait --wait-timeout 120 backend worker\n/);
  assert.ok(calls.indexOf('recovery-probe.php') < calls.indexOf('start --wait'));
  assert.ok(calls.indexOf('start --wait') < calls.indexOf(' backup /data '));
  assert.doesNotMatch(calls, /stop.*(?:web|scheduler)|SYNTHETIC_TEST_SECRET/);
  assert.ok((await stat(join(work, 'last-success'))).isFile());
  assert.deepEqual((await readdir(work)).sort(), ['last-success', 'operation.lock']);
}));

test('dump failure resumes the application and does not mark an external backup successful', () => fixture(async ({ work, env, trace }) => {
  await assert.rejects(exec('bash', [script('backup.sh')], { env: { ...env, FAIL_DUMP: '1' } }));
  assert.match(await trace(), /start --wait --wait-timeout 120 backend worker/);
  assert.doesNotMatch(await trace(), / backup \/data /);
  assert.deepEqual(await readdir(work), ['operation.lock']);
}));

test('upload failure occurs after production has resumed and leaves no plaintext staging or success marker', () => fixture(async ({ work, env, trace }) => {
  await assert.rejects(exec('bash', [script('backup.sh')], { env: { ...env, FAIL_UPLOAD: '1' } }));
  const calls = await trace();
  assert.ok(calls.indexOf('start --wait') < calls.indexOf(' backup /data '));
  assert.deepEqual(await readdir(work), ['operation.lock']);
}));

test('failed resume preserves its journal for systemd recovery, including when called from cleanup', () => fixture(async ({ work, env }) => {
  await assert.rejects(exec('bash', [script('backup.sh')], { env: { ...env, FAIL_START: '1' } }));
  assert.equal(await readFile(join(work, 'paused-services'), 'utf8'), 'backend\nworker\n');
  assert.deepEqual((await readdir(work)).sort(), ['operation.lock', 'paused-services']);
  await exec('bash', [script('backup.sh'), '--resume-only'], { env: { ...env, BACKUP_PASSWORD_FILE: '/missing-password' } });
  assert.deepEqual(await readdir(work), ['operation.lock']);
}));

test('invalid service journal cannot start arbitrary services', () => fixture(async ({ work, env, trace }) => {
  await writeFile(join(work, 'paused-services'), 'postgres\n');
  await assert.rejects(exec('bash', [script('backup.sh'), '--resume-only'], { env }));
  await assert.rejects(trace(), { code: 'ENOENT' });
  assert.ok((await stat(join(work, 'paused-services'))).isFile());
}));

test('retention runs after the verified snapshot, keeps 30 days and at least the latest copy', () => fixture(async ({ env, trace }) => {
  await exec('bash', [script('backup.sh')], { env: { ...env, BACKUP_RETENTION_DAYS: '30' } });
  const calls = await trace();
  assert.ok(calls.indexOf('check --read-data') < calls.indexOf('forget --tag'));
  assert.match(calls, /forget --tag alquivo --host alquivo --keep-within 30d --keep-last 1 --prune/);
}));

test('freshness monitor fails closed for missing mount, stale/future timestamps and pending resume', () => fixture(async ({ work, env }) => {
  await assert.rejects(exec('bash', [script('backup-watch.sh')], { env }));
  const marker = join(work, 'last-success');
  await writeFile(marker, '');
  await exec('bash', [script('backup-watch.sh')], { env });
  await assert.rejects(exec('bash', [script('backup-watch.sh')], { env: { ...env, MOUNT_RESULT: '1' } }));
  for (const hours of [-31, 2]) {
    const date = new Date(Date.now() + hours * 3600 * 1000);
    await utimes(marker, date, date);
    await assert.rejects(exec('bash', [script('backup-watch.sh')], { env }));
  }
  const now = new Date();
  await utimes(marker, now, now);
  await writeFile(join(work, 'paused-services'), 'backend\n');
  await assert.rejects(exec('bash', [script('backup-watch.sh')], { env }));
}));

test('backup password creation preserves existing secrets and rejects symbolic links', () => fixture(async ({ directory, password, env }) => {
  await exec('bash', [script('init-backup-secret.sh')], { env });
  assert.equal(await readFile(password, 'utf8'), 'SYNTHETIC_TEST_SECRET_NOT_FOR_OUTPUT');
  const fresh = join(directory, 'secrets', 'new.password');
  const result = await exec('bash', [script('init-backup-secret.sh')], { env: { ...env, BACKUP_PASSWORD_FILE: fresh } });
  assert.equal((await stat(fresh)).mode & 0o777, 0o600);
  assert.doesNotMatch(result.stdout, /[A-Za-z0-9+/]{48}/);
  const link = join(directory, 'password-link');
  await symlink(password, link);
  await assert.rejects(exec('bash', [script('init-backup-secret.sh')], { env: { ...env, BACKUP_PASSWORD_FILE: link } }));
}));

test('recovery Compose isolates data, credentials and providers, with no production volumes or public ports', async () => {
  const compose = await readFile(resolve(root, 'docker-compose.restore.yml'), 'utf8');
  assert.match(compose, /internal: true/);
  assert.match(compose, /DATABASE_ADMIN_PASSWORD: ""/);
  assert.match(compose, /DOCUMENT_ENCRYPTION_KEY: ""/);
  assert.match(compose, /MAIL_MAILER: array/);
  assert.match(compose, /ASSISTANT_ENABLED: "false"/);
  assert.match(compose, /mem_limit: 768m/);
  assert.match(compose, /mem_limit: 512m/);
  assert.match(compose, /data:size=512m/);
  assert.doesNotMatch(compose, /^\s+(ports|external|container_name):/m);
  assert.doesNotMatch(compose, /alquivo-production|postgres_data|private_storage|document_keys/);
});

test('backup failures recover immediately and a power-loss journal is also handled on boot', async () => {
  const service = await readFile(resolve(root, 'ops/server/alquivo-backup.service'), 'utf8');
  const resume = await readFile(resolve(root, 'ops/server/alquivo-backup-resume.service'), 'utf8');
  assert.match(service, /ExecStopPost=.*backup\.sh --resume-only/);
  assert.match(resume, /ConditionPathExists=\/opt\/alquivo\/shared\/backups\/paused-services/);
  assert.match(resume, /ExecStart=.*backup\.sh --resume-only/);
  assert.match(resume, /WantedBy=multi-user.target/);
});

test('notification sidecar does not need app, queue or root capabilities to read the private env', async () => {
  const alert = await readFile(script('backup-alert.sh'), 'utf8');
  assert.match(alert, /owner=\$\(stat -c '%u:%g'/);
  assert.match(alert, /--user "\$owner"/);
  assert.match(alert, /--read-only --cap-drop ALL/);
  assert.doesNotMatch(alert, /--env-file|--user root/);
});
