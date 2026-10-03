import { build, loadEnv } from "vite";
import {
  cp,
  mkdir,
  mkdtemp,
  readFile,
  readdir,
  rename,
  rm,
  stat,
  writeFile,
} from "node:fs/promises";
import { fileURLToPath, pathToFileURL } from "node:url";
import { dirname, join, resolve } from "node:path";
import { createHash } from "node:crypto";
import { escapeHtml, renderHead } from "../src/seo.js";
import { waitlistPages, waitlistSettings } from "../src/waitlist/settings.js";

const root = fileURLToPath(new URL("../", import.meta.url));
export const landingHeaders = {
  "Content-Security-Policy":
    "default-src 'none'; script-src 'self' https://challenges.cloudflare.com; style-src 'self'; img-src 'self' data:; font-src 'self'; frame-src https://challenges.cloudflare.com; connect-src 'self'; object-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'; upgrade-insecure-requests",
  "X-Content-Type-Options": "nosniff",
  "X-Frame-Options": "DENY",
  "Referrer-Policy": "no-referrer",
  "Permissions-Policy": "camera=(), microphone=(), geolocation=(), payment=()",
};

export async function buildWaitlist({ preview = false, env, outputDir } = {}) {
  const settings = waitlistSettings(
    env ?? { ...loadEnv("landing", root, "LANDING_"), ...process.env },
    { preview },
  );
  const output =
    outputDir || join(root, preview ? "dist-landing-preview" : "dist-landing");
  // Use a clean staging directory. Never copy the app's dist or public/ wholesale.
  const staging = await mkdtemp(join(root, ".waitlist-build-"));
  const server = join(staging, "server");
  const publicDir = join(staging, "public");
  try {
    await build({
      root,
      mode: "landing",
      publicDir: "public",
      logLevel: "warn",
      build: {
        ssr: "src/entry-waitlist.js",
        outDir: server,
        emptyOutDir: true,
        ssrEmitAssets: true,
        sourcemap: false,
        copyPublicDir: false,
        rollupOptions: { output: { entryFileNames: "entry-waitlist.js" } },
      },
    });
    const { render } = await import(
      pathToFileURL(join(server, "entry-waitlist.js")).href
    );
    await mkdir(join(publicDir, "assets"), { recursive: true });
    const assets = await readdir(join(server, "assets"));
    const styleFiles = assets.filter((name) => name.endsWith(".css"));
    if (!styleFiles.length)
      throw new Error("La landing debe incluir los estilos de la marca.");
    for (const name of assets) {
      if (/\.(css|woff2)$/.test(name))
        await cp(join(server, "assets", name), join(publicDir, "assets", name));
    }
    await mkdir(join(publicDir, "brand"), { recursive: true });
    for (const name of [
      "favicon.svg",
      "favicon-32.png",
      "apple-touch-icon.png",
      "brand/alquivo-wordmark.svg",
    ])
      await cp(join(root, "public", name), join(publicDir, name));
    let formVersion = "";
    if (!settings.preview) {
      await cp(
        join(root, "src/waitlist/form.js"),
        join(publicDir, "assets/form.js"),
      );
      formVersion = createHash("sha256")
        .update(await readFile(join(publicDir, "assets/form.js")))
        .digest("hex")
        .slice(0, 12);
    }

    for (const page of waitlistPages) {
      const body = await render(page.path, settings);
      const head = renderHead({
        ...page,
        type: "website",
        robots:
          settings.indexable && page.path === "/"
            ? "index, follow"
            : "noindex, follow",
        canonical: page.path === "/404" ? "" : `${settings.origin}${page.path}`,
      });
      const html = `<!doctype html>\n<html lang="es" data-theme="dark"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0b1420">
<link rel="icon" type="image/svg+xml" href="/favicon.svg?v=wordmark-1">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
${head}
${styleFiles.map((name) => `<link rel="stylesheet" href="/assets/${escapeHtml(name)}">`).join("\n")}
</head><body>${body}${page.path === "/" && !settings.preview ? `<script src="/assets/form.js?v=${formVersion}" defer></script>` : ""}</body></html>\n`;
      const path =
        page.path === "/"
          ? join(publicDir, "index.html")
          : page.path === "/404"
            ? join(publicDir, "404.html")
            : join(publicDir, page.path.slice(1), "index.html");
      await mkdir(dirname(path), { recursive: true });
      await writeFile(path, html);
    }
    const headers = [
      "/*",
      ...Object.entries(landingHeaders).map(
        ([name, value]) => `  ${name}: ${value}`,
      ),
    ].join("\n");
    await writeFile(join(publicDir, "_headers"), `${headers}\n`);
    // Only form submissions invoke the paid/quota-limited runtime, not page views.
    await writeFile(
      join(publicDir, "_routes.json"),
      JSON.stringify(
        {
          version: 1,
          include: ["/api/waitlist"],
          exclude: [],
        },
        null,
        2,
      ) + "\n",
    );
    // No SPA rewrite: app/API/login/unknown URLs must return a real 404.
    await writeFile(
      join(publicDir, "robots.txt"),
      settings.indexable
        ? `User-agent: *\nAllow: /\nSitemap: ${settings.origin}/sitemap.xml\n`
        : "User-agent: *\nDisallow: /\n",
    );
    await writeFile(
      join(publicDir, "sitemap.xml"),
      `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">${settings.indexable ? `<url><loc>${escapeHtml(settings.origin)}/</loc></url>` : ""}</urlset>\n`,
    );
    await writeFile(
      join(publicDir, "llms.txt"),
      `# Alquivo\n\nAplicación web en preparación para propietarios y pequeños inversores: inmuebles, alquileres, contratos, cobros, gastos y documentos. Alquivo AI ayudará a consultar los datos registrados y preparar cambios que el usuario revise y confirme. No sustituye asesoramiento legal, fiscal ni financiero.\n\nLa web permite solicitar acceso a una próxima beta gratuita; no crea cuentas ni cobra. No hay fecha de apertura anunciada. Los ejemplos usan datos ficticios.\n\n- [Presentación y solicitud de beta](${settings.origin}/)\n- [Privacidad de la lista de espera](${settings.origin}/privacidad)\n- [Aviso legal](${settings.origin}/aviso-legal)\n`,
    );
    await verifyWaitlistOutput(publicDir, settings);
    await rm(output, { recursive: true, force: true });
    try {
      await rename(publicDir, output);
    } catch (error) {
      if (error.code !== "EXDEV") throw error;
      await cp(publicDir, output, { recursive: true });
    }
    // Dashboard ZIP uploads do not deploy Pages Functions: retire the old ZIP.
    await rm(`${output}.zip`, { force: true });
    return { output, settings, pages: waitlistPages.length };
  } finally {
    await rm(staging, { recursive: true, force: true });
  }
}

export async function verifyWaitlistOutput(directory, settings) {
  const expectedScript = settings.preview
    ? null
    : `<script src="/assets/form.js?v=${createHash("sha256")
        .update(await readFile(join(directory, "assets/form.js")))
        .digest("hex")
        .slice(0, 12)}" defer>`;
  const publicFiles = new Set([
    "index.html",
    "404.html",
    "privacidad/index.html",
    "aviso-legal/index.html",
    "_headers",
    "_routes.json",
    "robots.txt",
    "sitemap.xml",
    "llms.txt",
    "favicon.svg",
    "favicon-32.png",
    "apple-touch-icon.png",
    "brand/alquivo-wordmark.svg",
    ...(!settings.preview ? ["assets/form.js"] : []),
  ]);
  for (const file of await readdir(directory, { recursive: true })) {
    if ((await stat(join(directory, file))).isDirectory()) continue;
    if (
      !publicFiles.has(file) &&
      !/^assets\/[a-zA-Z0-9_-]+\.(css|woff2)$/.test(file)
    )
      throw new Error(`Archivo no publicable en la landing: ${file}`);
    if (!file.endsWith(".html")) continue;
    const html = await readFile(join(directory, file), "utf8");
    if (
      /href="\/(?:login|register|dashboard|finance|properties|guias)|\/api\/v1|\/sanctum\/|localhost|127\.0\.0\.1/.test(
        html,
      )
    )
      throw new Error(
        `La landing no puede enlazar con el backend o cuentas: ${file}`,
      );
    if (
      Array.from(html.matchAll(/<script\b[^>]*>/g)).some(
        ([tag]) => tag !== expectedScript,
      )
    )
      throw new Error(`Script no autorizado en la landing: ${file}`);
    if (settings.preview && !html.includes('content="noindex, follow"'))
      throw new Error("Una vista previa no puede indexarse.");
  }
}

if (
  process.argv[1] &&
  resolve(process.argv[1]) === fileURLToPath(import.meta.url)
) {
  const result = await buildWaitlist({
    preview: process.argv.includes("--preview"),
  });
  console.log(
    `Landing ${result.settings.preview ? "de vista previa (no publicar)" : "publicable"}: ${result.output}`,
  );
  console.log(
    `${result.pages} páginas públicas y formulario propio. Publicar con GitHub + Pages Functions, no mediante ZIP. Sin Laravel, cuentas, pagos ni claves secretas en el HTML.`,
  );
  if (result.settings.preview)
    console.log(
      "Vista previa: envío desactivado. La publicación requiere Turnstile y una base D1 en Cloudflare.",
    );
}
