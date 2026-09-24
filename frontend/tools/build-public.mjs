import { build } from "vite";
import { readFile, writeFile, mkdir } from "node:fs/promises";
import { fileURLToPath, pathToFileURL } from "node:url";
import path from "node:path";
import { escapeHtml, pageSeo, publicPages, renderHead } from "../src/seo.js";

const root = fileURLToPath(new URL("../", import.meta.url));
process.chdir(root);
await build();
await build({ build: { ssr: "src/entry-public.js", outDir: "dist-ssr" } });
const { paths, render, settings } = await import(
  pathToFileURL(path.join(root, "dist-ssr/entry-public.js"))
);
const template = await readFile("dist/index.html", "utf8");
function html(head, body) {
  return template
    .replace(/<!--seo-start-->[\s\S]*?<!--seo-end-->/, () => head)
    .replace("<!--public-content-->", () => body);
}

// Private routes get a neutral shell, never the indexable public landing.
await writeFile(
  "dist/app.html",
  html(
    renderHead(pageSeo("/login", settings)),
    '<div class="app-loading" role="status">Preparando Alquivo…</div><noscript>Activa JavaScript para iniciar sesión. <a href="/">Volver al inicio</a></noscript>',
  ),
);
const locations = [];
const crawlable = new Set(publicPages.map((page) => page.path));
const robotsMap = [
  "map $request_uri $seo_robots {",
  '    default "noindex, nofollow";',
];
for (const route of paths) {
  const result = await render(route);
  const file =
    route === "/"
      ? "index.html"
      : route === "/404"
        ? "404.html"
        : `${route.slice(1)}/index.html`;
  await mkdir(path.dirname(`dist/${file}`), { recursive: true });
  await writeFile(`dist/${file}`, html(result.head, result.body));
  if (route === "/404") continue;
  locations.push(`location = ${route} { try_files /${file} =404; }`);
  if (route !== "/")
    locations.push(
      `location = ${route}/ { return 308 ${route}$is_args$args; }`,
    );
  if (crawlable.has(route) && settings.indexable) {
    const escaped = route.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    robotsMap.push(
      `    ~^${escaped}(?:\\?|$) "index, follow, max-image-preview:large";`,
    );
  }
}
robotsMap.push("}");
await writeFile("dist-ssr/seo-map.conf", robotsMap.join("\n") + "\n");
await writeFile("dist-ssr/seo-locations.conf", locations.join("\n") + "\n");
const urls = settings.indexable
  ? publicPages.map((page) => `${settings.origin}${page.path}`)
  : [];
await writeFile(
  "dist/sitemap.xml",
  `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${urls.map((url) => `  <url><loc>${escapeHtml(url)}</loc></url>`).join("\n")}\n</urlset>\n`,
);
await writeFile(
  "dist/robots.txt",
  settings.indexable
    ? `User-agent: *\nAllow: /\nDisallow: /api/\nDisallow: /sanctum/\nDisallow: /stripe/\n\nSitemap: ${settings.origin}/sitemap.xml\n`
    : "User-agent: *\nDisallow: /\n",
);
console.log(
  `Public HTML generated: ${paths.length} pages. Indexing ${settings.indexable ? "ENABLED" : "DISABLED (local/staging)"}.`,
);
