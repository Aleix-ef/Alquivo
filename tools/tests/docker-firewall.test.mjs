import { test } from "node:test";
import assert from "node:assert/strict";
import { readFile, writeFile, mkdtemp, symlink, rm } from "node:fs/promises";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { execFile } from "node:child_process";
import { promisify } from "node:util";

const exec = promisify(execFile);
const source = await readFile(
  new URL("../../ops/server/alquivo-docker-firewall", import.meta.url),
  "utf8",
);
const rootGuard = String.raw`[[ $EUID -eq 0 ]] || { printf 'Run as root.\n' >&2; exit 1; }`;
assert.ok(source.includes(rootGuard));
// Execute real policy construction unprivileged, removing ONLY the UID check
// from an in-memory copy. Every network command is a fake in a temporary PATH.
// The production file retains its root check and is never executed by this test.
const fixture = source.replace(rootGuard, "");

async function policy(settings = {}, empty = false) {
  const dir = await mkdtemp(join(tmpdir(), "alquivo-firewall-test-"));
  try {
    const log = join(dir, "calls.jsonl");
    const stub = join(dir, "network-stub.cjs");
    await writeFile(
      stub,
      `#!${process.execPath}
const fs=require("node:fs"), path=require("node:path");
const command=path.basename(process.argv[1]), args=process.argv.slice(2);
const input=command.endsWith("-restore") ? fs.readFileSync(0,"utf8") : "";
fs.appendFileSync(process.env.CAPTURE,JSON.stringify({command,args,input})+"\\n");
process.exit(process.env.EMPTY_CHAINS==="1" && ["iptables","ip6tables"].includes(command) && (args.includes("-L") || args.includes("-C")) ? 1 : 0);
`,
      { mode: 0o755 },
    );
    for (const name of [
      "ip",
      "iptables",
      "ip6tables",
      "iptables-restore",
      "ip6tables-restore",
    ])
      await symlink(stub, join(dir, name));
    let code = 0;
    try {
      await exec("/bin/bash", ["-c", fixture], {
        env: {
          PATH: dir,
          CAPTURE: log,
          EMPTY_CHAINS: empty ? "1" : "0",
          ALQUIVO_PUBLIC_INTERFACE: "ens3",
          ALQUIVO_TEST_IPV4: "192.0.2.7",
          ALQUIVO_PROXY_IPV4: "172.30.20.11",
          ...settings,
        },
        timeout: 10000,
      });
    } catch (error) {
      code = error.code;
    }
    let calls = [];
    try {
      calls = (await readFile(log, "utf8"))
        .trim()
        .split("\n")
        .filter(Boolean)
        .map((line) => JSON.parse(line));
    } catch (error) {
      if (error.code !== "ENOENT") throw error;
    }
    return { code, calls };
  } finally {
    await rm(dir, { recursive: true, force: true });
  }
}

function rules(result, family = "iptables") {
  const restore = result.calls.find((c) => c.command === family + "-restore");
  assert.ok(restore);
  assert.deepEqual(restore.args, ["--wait", "5", "--noflush"]);
  return restore.input.split("\n").filter((line) => line.startsWith("-A "));
}

test("missing opt-in preserves the exact restricted proxy-only policy", async () => {
  const result = await policy();
  assert.equal(result.code, 0);
  const lines = rules(result);
  assert.equal(lines.filter((line) => line.includes("--dport 443")).length, 1);
  assert.match(
    lines.find((line) => line.includes("--dport 443")),
    /-s 192\.0\.2\.7\/32/,
  );
  assert.match(
    lines.find((line) => line.includes("--dport 80")),
    /-d 172\.30\.20\.11\/32/,
  );
  assert.ok(lines.includes("-A ALQUIVO-INGRESS -i ens3 -j DROP"));
});

test("public opt-in opens only original IPv4 TCP/443 to the fixed DNAT proxy", async () => {
  const result = await policy({ ALQUIVO_WEB_ACCESS: "public" });
  assert.equal(result.code, 0);
  const lines = rules(result);
  const permitted = lines.filter((line) => line.includes("--dport"));
  assert.equal(permitted.length, 2);
  const https = permitted.find((line) => line.includes("--dport 443"));
  assert.doesNotMatch(https, /-s /);
  assert.match(
    https,
    /-d 172\.30\.20\.11\/32 -p tcp --dport 443 -m conntrack --ctstate DNAT --ctorigdstport 443 -j RETURN$/,
  );
  assert.ok(lines.includes("-A ALQUIVO-INGRESS -i ens3 -j DROP"));
  assert.doesNotMatch(lines.join("\n"), /5432|9000|3310|2375|2376|-p udp/);
});

for (const mode of ["restricted", "public"])
  test("IPv6 ingress stays closed in " + mode + " mode", async () => {
    const result = await policy({ ALQUIVO_WEB_ACCESS: mode });
    assert.equal(result.code, 0);
    assert.deepEqual(rules(result, "ip6tables"), [
      "-A ALQUIVO-INGRESS -m conntrack --ctstate ESTABLISHED,RELATED -j RETURN",
      "-A ALQUIVO-INGRESS -i ens3 -j DROP",
      "-A ALQUIVO-INGRESS -j RETURN",
    ]);
  });

for (const settings of [
  { ALQUIVO_WEB_ACCESS: "PUBLIC" },
  { ALQUIVO_WEB_ACCESS: "true" },
  { ALQUIVO_WEB_ACCESS: "public; echo unsafe" },
  { ALQUIVO_TEST_IPV4: "0.0.0.0" },
  { ALQUIVO_TEST_IPV4: "192.0.2.7/24" },
  { ALQUIVO_TEST_IPV4: "192.0.2.999" },
  { ALQUIVO_PUBLIC_INTERFACE: "ens3; echo unsafe" },
  { ALQUIVO_PROXY_IPV4: "172.30.20.11/24" },
])
  test(
    "invalid configuration fails before any firewall restoration: " +
      JSON.stringify(settings),
    async () => {
      const result = await policy(settings);
      assert.notEqual(result.code, 0);
      assert.equal(
        result.calls.filter((c) => c.command.endsWith("-restore")).length,
        0,
      );
    },
  );

test("new chains install guarded jumps without flushing another chain or changing SSH", async () => {
  const result = await policy({ ALQUIVO_WEB_ACCESS: "public" }, true);
  assert.equal(result.code, 0);
  for (const command of ["iptables", "ip6tables"]) {
    const calls = result.calls.filter((c) => c.command === command);
    for (const chain of ["DOCKER-USER", "FORWARD"])
      assert.ok(
        calls.some(
          (c) =>
            c.args.join(" ") ===
            "-w 5 -I " +
              chain +
              " 1 -j " +
              (chain === "FORWARD" ? "DOCKER-USER" : "ALQUIVO-INGRESS"),
        ),
      );
    assert.equal(
      calls.some((c) =>
        c.args.some((a) =>
          ["-F", "--flush", "-P", "--policy", "INPUT"].includes(a),
        ),
      ),
      false,
    );
  }
  assert.deepEqual(
    result.calls
      .filter((c) => c.command.endsWith("-restore"))
      .map((c) => c.input.match(/^-F (.+)$/m)?.[1]),
    ["ALQUIVO-INGRESS", "ALQUIVO-INGRESS"],
  );
  assert.doesNotMatch(
    result.calls.map((c) => c.input).join("\n"),
    /--dport 22/,
  );
});
