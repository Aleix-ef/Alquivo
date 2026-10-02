// Deterministic exports of the existing vector logo, without an image API.
// Usage: node tools/export-brand.mjs (Google Chrome required for PNG exports).
import { spawn } from "node:child_process";
import { readFile, writeFile, mkdir, mkdtemp, rm } from "node:fs/promises";
import { createServer } from "node:http";
import { tmpdir } from "node:os";
import path from "node:path";
import { fileURLToPath } from "node:url";
const root = fileURLToPath(new URL("../", import.meta.url));
const publicRoot = path.join(root, "frontend/public");
const brandRoot = path.join(root, "brand");
const source = await readFile(
  path.join(publicRoot, "brand/alquivo-wordmark.svg"),
  "utf8",
);
const icon = await readFile(path.join(publicRoot, "favicon.svg"), "utf8");
const wordmark = source.match(/<g id="wordmark"[\s\S]*<\/g>/)?.[0];
const symbol = icon.match(/<g id="mark"[\s\S]*<\/g>/)?.[0];
if (!wordmark || !symbol)
  throw new Error("Missing shared vector brand source.");

const social = `<svg xmlns="http://www.w3.org/2000/svg" width="1080" height="1080" viewBox="0 0 1080 1080" color="#fff">
  <title>Alquivo · Nombre completo para redes sociales</title>
  <rect width="1080" height="1080" fill="#082f40" />
  <g transform="translate(135 461) scale(1.234756)">${wordmark}</g>
</svg>\n`;
const light = source.replace('color="#082f40"', 'color="#fff"');

await mkdir(brandRoot, { recursive: true });
await Promise.all([
  writeFile(path.join(brandRoot, "alquivo-nombre.svg"), source),
  writeFile(path.join(brandRoot, "alquivo-nombre-claro.svg"), light),
  writeFile(path.join(brandRoot, "alquivo-logo-redes.svg"), social),
  writeFile(path.join(brandRoot, "alquivo-icono-al.svg"), icon),
]);

const exports = [
  {
    name: "redes",
    size: 1080,
    svg: social,
    file: path.join(brandRoot, "alquivo-logo-redes.png"),
  },
  {
    name: "favicon",
    size: 32,
    svg: symbol,
    file: path.join(publicRoot, "favicon-32.png"),
    icon: true,
  },
  {
    name: "apple",
    size: 180,
    svg: symbol,
    file: path.join(publicRoot, "apple-touch-icon.png"),
    icon: true,
  },
];
if (process.argv.includes("--preview")) {
  const styles = (
    await Promise.all(
      ["theme.css", "style.css", "shell.css"].map((name) =>
        readFile(path.join(root, "frontend/src", name), "utf8"),
      ),
    )
  ).join("\n");
  const logo = (compact = false, light = false) =>
    `<span class="alquivo-brand${light ? " light" : ""}" role="img" aria-label="Alquivo"><svg class="${compact ? "alquivo-symbol" : "alquivo-wordmark"}" viewBox="${compact ? "0 0 100 100" : "0 0 656 128"}"><use href="${compact ? "/favicon.svg?v=wordmark-1#mark" : "/brand/alquivo-wordmark.svg#wordmark"}" /></svg></span>`;
  exports.push({
    name: "preview",
    size: 1080,
    file: path.join(tmpdir(), "alquivo-brand-preview.png"),
    svg: `<style>${styles}\n.brand-proof{padding:64px;min-height:1080px;background:#0b1420;color:#e5edf7}.brand-proof section{padding:48px;margin:28px 0;background:#121f2e;border-radius:16px}.brand-proof p{margin:0 0 28px}.brand-proof .proof-light{--ink:#082f40;background:#fff;color:#082f40}.brand-proof .sidebar{position:static;width:auto;height:auto}</style><main class="brand-proof"><h1>Alquivo · Comprobación de marca</h1><section><p>Nombre completo · Fondo oscuro</p>${logo()}</section><section class="proof-light"><p>Nombre completo · Fondo claro</p>${logo()}</section><section class="sidebar"><p>Nombre completo · Menú de la app</p>${logo(false, true)}</section><section><p>AL · Solo para espacios pequeños</p>${logo(true)}</section></main>`,
  });
}
const server = createServer((request, response) => {
  const pathname = new URL(request.url, "http://localhost").pathname;
  if (
    pathname === "/brand/alquivo-wordmark.svg" ||
    pathname === "/favicon.svg"
  ) {
    response.writeHead(200, { "Content-Type": "image/svg+xml" });
    response.end(pathname === "/favicon.svg" ? icon : source);
    return;
  }
  const item = exports.find(
    (candidate) => request.url === `/${candidate.name}`,
  );
  if (!item) {
    response.writeHead(404);
    response.end();
    return;
  }
  response.writeHead(200, {
    "Content-Type": "text/html; charset=utf-8",
    "Cache-Control": "no-store",
  });
  const artwork = item.icon
    ? `<svg xmlns="http://www.w3.org/2000/svg" width="${item.size}" height="${item.size}" viewBox="0 0 100 100">${item.svg}</svg>`
    : item.svg;
  response.end(
    `<!doctype html><html><head><meta charset="utf-8"><style>html,body{margin:0;padding:0;background:transparent;overflow:hidden}svg{display:block}</style></head><body>${artwork}</body></html>`,
  );
});
await new Promise((resolve, reject) => {
  server.once("error", reject);
  server.listen(0, "127.0.0.1", resolve);
});
const port = server.address().port;
const temporary = await mkdtemp(path.join(tmpdir(), "alquivo-brand-"));
// The screenshot CLI can include browser chrome and crop the actual viewport.
// Use an isolated headless process with an explicit content viewport instead.
const browser = spawn(
  process.env.CHROME_BIN || "google-chrome",
  [
    "--headless=new",
    "--no-sandbox",
    "--disable-dev-shm-usage",
    "--disable-gpu",
    "--disable-background-networking",
    "--disable-extensions",
    "--no-first-run",
    "--no-default-browser-check",
    "--hide-scrollbars",
    "--remote-debugging-pipe",
    `--user-data-dir=${temporary}`,
    "about:blank",
  ],
  { stdio: ["ignore", "ignore", "ignore", "pipe", "pipe"] },
);
let nextId = 0,
  buffer = "",
  failure;
const pending = new Map(),
  listeners = new Map();
function fail(error) {
  failure = error;
  for (const request of pending.values()) request.reject(error);
  pending.clear();
  for (const event of listeners.values()) event.reject(error);
  listeners.clear();
}
browser.once("error", fail);
browser.once("exit", () => fail(new Error("Headless Chrome closed.")));
browser.stdio[3].on("error", fail);
browser.stdio[4].setEncoding("utf8");
browser.stdio[4].on("data", (chunk) => {
  buffer += chunk;
  let end;
  while ((end = buffer.indexOf("\0")) >= 0) {
    const message = JSON.parse(buffer.slice(0, end));
    buffer = buffer.slice(end + 1);
    if (message.id) {
      const request = pending.get(message.id);
      pending.delete(message.id);
      if (message.error) request?.reject(new Error(message.error.message));
      else request?.resolve(message.result);
    } else {
      const key = `${message.sessionId}:${message.method}`;
      listeners.get(key)?.resolve(message.params);
      listeners.delete(key);
    }
  }
});
function command(method, params = {}, sessionId) {
  return new Promise((resolve, reject) => {
    if (failure) return reject(failure);
    const id = ++nextId;
    const timer = setTimeout(() => {
      pending.delete(id);
      reject(new Error(`Chrome timeout: ${method}`));
    }, 30000);
    pending.set(id, {
      resolve: (result) => {
        clearTimeout(timer);
        resolve(result);
      },
      reject: (error) => {
        clearTimeout(timer);
        reject(error);
      },
    });
    browser.stdio[3].write(
      JSON.stringify({ id, method, params, sessionId }) + "\0",
    );
  });
}
function nextEvent(method, sessionId) {
  return new Promise((resolve, reject) => {
    const key = `${sessionId}:${method}`;
    const timer = setTimeout(() => {
      listeners.delete(key);
      reject(new Error(`Chrome timeout: ${method}`));
    }, 30000);
    listeners.set(key, {
      resolve: (result) => {
        clearTimeout(timer);
        resolve(result);
      },
      reject: (error) => {
        clearTimeout(timer);
        reject(error);
      },
    });
  });
}
try {
  const { targetId } = await command("Target.createTarget", {
    url: "about:blank",
  });
  const { sessionId } = await command("Target.attachToTarget", {
    targetId,
    flatten: true,
  });
  await command("Page.enable", {}, sessionId);
  await command(
    "Emulation.setDefaultBackgroundColorOverride",
    { color: { r: 0, g: 0, b: 0, a: 0 } },
    sessionId,
  );
  for (const item of exports) {
    await command(
      "Emulation.setDeviceMetricsOverride",
      {
        width: item.size,
        height: item.size,
        deviceScaleFactor: 1,
        mobile: false,
      },
      sessionId,
    );
    const loaded = nextEvent("Page.loadEventFired", sessionId);
    await command(
      "Page.navigate",
      { url: `http://127.0.0.1:${port}/${item.name}` },
      sessionId,
    );
    await loaded;
    const { cssLayoutViewport } = await command(
      "Page.getLayoutMetrics",
      {},
      sessionId,
    );
    if (
      cssLayoutViewport.clientWidth !== item.size ||
      cssLayoutViewport.clientHeight !== item.size
    )
      throw new Error(`Incorrect content viewport: ${item.name}`);
    const { data } = await command(
      "Page.captureScreenshot",
      {
        format: "png",
        fromSurface: true,
        clip: { x: 0, y: 0, width: item.size, height: item.size, scale: 1 },
      },
      sessionId,
    );
    const png = Buffer.from(data, "base64");
    await writeFile(item.file, png);
    if (
      png.readUInt32BE(16) !== item.size ||
      png.readUInt32BE(20) !== item.size
    )
      throw new Error(`Unexpected PNG dimensions: ${item.name}`);
    const relative = path.relative(root, item.file);
    console.log(
      `Exported ${relative.startsWith("..") ? item.file : relative} (${item.size} × ${item.size})`,
    );
  }
} finally {
  const closed = new Promise((resolve) => {
    if (browser.exitCode !== null || browser.signalCode !== null) resolve();
    else browser.once("close", resolve);
  });
  await command("Browser.close").catch(() => {});
  const stop = setTimeout(() => browser.kill("SIGTERM"), 3000);
  const kill = setTimeout(() => browser.kill("SIGKILL"), 8000);
  await closed;
  clearTimeout(stop);
  clearTimeout(kill);
  await new Promise((resolve) => server.close(resolve));
  await rm(temporary, {
    recursive: true,
    force: true,
    maxRetries: 5,
    retryDelay: 100,
  });
}
