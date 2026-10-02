import { createServer } from "node:http";
import { readFile, stat } from "node:fs/promises";
import { fileURLToPath } from "node:url";
import { extname, join, resolve, sep } from "node:path";
import { landingHeaders } from "./build-waitlist.mjs";

const root = fileURLToPath(new URL("../", import.meta.url));
const directory = join(root, "dist-landing-preview");
await stat(join(directory, "index.html"));
const types = {
  ".html": "text/html; charset=utf-8",
  ".css": "text/css; charset=utf-8",
  ".js": "text/javascript; charset=utf-8",
  ".svg": "image/svg+xml",
  ".png": "image/png",
  ".woff2": "font/woff2",
  ".txt": "text/plain; charset=utf-8",
  ".xml": "application/xml; charset=utf-8",
};
const server = createServer(async (request, response) => {
  try {
    if (!["GET", "HEAD"].includes(request.method)) {
      response.writeHead(405, { Allow: "GET, HEAD" });
      response.end();
      return;
    }
    const path = decodeURIComponent(
      new URL(request.url, "http://localhost").pathname,
    );
    let file = resolve(directory, `.${path}`);
    if (!file.startsWith(`${directory}${sep}`) && file !== directory)
      throw new Error("Unauthorized path");
    if (
      path
        .split("/")
        .some((part) => part.startsWith(".") || part.startsWith("_"))
    )
      throw new Error("Private file");
    let status = 200;
    try {
      if ((await stat(file)).isDirectory()) file = join(file, "index.html");
      await stat(file);
    } catch {
      status = 404;
      file = join(directory, "404.html");
    }
    const body = await readFile(file);
    response.writeHead(status, {
      ...landingHeaders,
      "Content-Type": types[extname(file)] || "application/octet-stream",
      "Cache-Control": "no-store",
    });
    response.end(request.method === "HEAD" ? undefined : body);
  } catch {
    response.writeHead(404);
    response.end();
  }
});
server.listen(4174, "127.0.0.1", () =>
  console.log("Local: http://127.0.0.1:4174/"),
);
for (const signal of ["SIGINT", "SIGTERM"])
  process.on(signal, () => server.close(() => process.exit(0)));
