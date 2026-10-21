import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { mkdtemp, mkdir, writeFile, readFile, rm, readdir, stat, symlink } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createHash } from 'node:crypto';

const exec = promisify(execFile);
const verifier = fileURLToPath(new URL('../verify-recovery-export.sh', import.meta.url));
async function fixture(kind, run) {
  const root = await mkdtemp(join(tmpdir(), 'alquivo-recovery-export-test-'));
  const files = join(root, 'files');
  const bin = join(root, 'bin');
  const runtime = join(root, 'runtime');
  await Promise.all([mkdir(files), mkdir(bin), mkdir(runtime)]);
  const names = ['production.env', 'backup.password', 'storagebox.credentials', 'documents.json'];
  const sentinel = 'SYNTHETIC_ONLY_SECRET_NEVER_PRINT';
  let manifest = '';
  for (const name of names) {
    const value = `${name}: ${sentinel}\n`;
    await writeFile(join(files, name), value);
    manifest += `${createHash('sha256').update(value).digest('hex')}  ${name}\n`;
  }
  await writeFile(join(files, 'SHA256SUMS'), manifest);
  if (kind === 'tamper') await writeFile(join(files, 'production.env'), `${sentinel} tampered`);
  if (kind === 'extra') await writeFile(join(files, 'unexpected.env'), sentinel);
  if (kind === 'link') { await rm(join(files, 'documents.json')); await symlink('/does-not-exist', join(files, 'documents.json')); }
  const archive = join(root, 'synthetic.tar');
  await exec('tar', ['-C', files, '-cf', archive, '.']);
  const bundle = join(root, 'alquivo-recovery-test.tar.age');
  await writeFile(bundle, 'synthetic encrypted transport fixture');
  await writeFile(join(bin, 'age'), '#!/usr/bin/env bash\nset -eu\nif [[ "$FAIL_DECRYPT" == 1 ]]; then exit 17; fi\ncp -- "$SYNTHETIC_TAR" "$3"\n', { mode: 0o700 });
  await writeFile(join(bin, 'findmnt'), '#!/usr/bin/env bash\nprintf "%s\\n" "$TEST_FSTYPE"\n', { mode: 0o700 });
  const env = {
    ...process.env, PATH: `${bin}:${process.env.PATH}`, XDG_RUNTIME_DIR: runtime,
    ALQUIVO_AGE_BIN: join(bin, 'age'), SYNTHETIC_TAR: archive,
    FAIL_DECRYPT: kind === 'decrypt-fail' ? '1' : '0',
    TEST_FSTYPE: kind === 'disk' ? 'ext4' : 'tmpfs',
  };
  const execute = () => exec('script', ['-q', '-e', '-c', `bash ${JSON.stringify(verifier)} ${JSON.stringify(bundle)}`, '/dev/null'], { env, timeout: 5000 });
  try { await run({ bundle, runtime, execute, sentinel }); }
  finally { await rm(root, { recursive: true, force: true }); }
}

test('offline verification checks all hashes, prints no secrets and removes plaintext temporaries', () => fixture('valid', async ({ bundle, runtime, execute, sentinel }) => {
  const result = await execute();
  assert.match(result.stdout, /VERIFICACI.N CORRECTA/);
  assert.ok(!result.stdout.includes(sentinel));
  const marker = await readFile(`${bundle}.verified`, 'utf8');
  assert.ok(marker.startsWith(createHash('sha256').update(await readFile(bundle)).digest('hex')));
  assert.equal((await stat(`${bundle}.verified`)).mode & 0o777, 0o600);
  assert.deepEqual(await readdir(runtime), []);
}));

for (const kind of ['tamper', 'extra', 'link', 'decrypt-fail', 'disk']) {
  test(`offline verification rejects ${kind} without success evidence or plaintext residue`, () => fixture(kind, async ({ bundle, runtime, execute, sentinel }) => {
    await assert.rejects(execute(), (error) => {
      assert.ok(error.code !== 0);
      assert.ok(!(error.stdout ?? '').includes(sentinel));
      assert.doesNotMatch(error.stdout ?? '', /VERIFICACI.N CORRECTA/);
      return true;
    });
    await assert.rejects(readFile(`${bundle}.verified`), { code: 'ENOENT' });
    assert.deepEqual(await readdir(runtime), []);
  }));
}
