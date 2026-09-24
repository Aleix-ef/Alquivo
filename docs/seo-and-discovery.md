# SEO y descubrimiento en buscadores con IA

Implementación local: 16–17 de septiembre de 2026. No implica publicación, indexación ni posiciones conseguidas.

## Qué se ha implementado

- HTML público generado al compilar usando los mismos componentes Vue que ve el usuario. La portada y las guías se pueden leer sin ejecutar JavaScript. No se renderizan cuentas, carteras ni documentos.
- Portada, índice `/guias` y dos guías: `/guias/organizar-alquileres` y `/guias/control-cobros-gastos-alquiler`. Responden a necesidades concretas de propietarios y enlazan entre sí y con el registro, sin reseñas inventadas ni funciones desactivadas anunciadas como disponibles.
- Títulos, descripciones, canonical, Open Graph y metadatos de tarjeta social por página. No hay una imagen social dedicada; se puede preparar una más adelante. Los parámetros de campaña no forman parte del canonical.
- Datos estructurados mediante **microdatos HTML**: SoftwareApplication en portada, Article y BreadcrumbList en guías. No se necesitan scripts inline ni relajar la CSP. No se prometen resultados enriquecidos ni estrellas.
- `robots.txt`, `sitemap.xml` y cabecera `X-Robots-Tag` generados según el entorno. El sitemap incluye únicamente las cuatro páginas editoriales autorizadas, no cuentas ni páginas de acceso. No inventa fechas de actualización.
- Rutas privadas con una plantilla neutra y `noindex`; páginas legales también con `noindex` mientras sus textos sean provisionales. El aislamiento y la autenticación siguen siendo la protección de los datos: **robots.txt y noindex no son controles de acceso**.
- Respuestas HTTP 404 reales para rutas inexistentes; redirecciones 308 desde las barras finales públicas y `/index.html`; enlaces internos descriptivos; fuentes locales precargadas y compresión de contenido estático.
- El HTML permanece visible mientras Vue prepara la primera navegación. Se mantiene la app autenticada como SPA, sin incorporar un servidor Node a producción.

## Activación al publicar, no antes

1. Tener dominio y HTTPS definitivos, textos legales revisados, correo operativo y comprobaciones de lanzamiento realizadas. Elegir un solo host principal. Si se usan variantes `www` o dominios antiguos, configurar sus certificados y redirecciones 301/308 explícitas en el proxy; no duplicar la web.
2. En `.env.production`, configurar `VITE_PUBLIC_SITE_URL` con el origen HTTPS real, por ejemplo `https://alquivo.com` **solo si es el dominio confirmado**, y `VITE_SEO_INDEXABLE=true`.
3. Reconstruir y desplegar el frontend. Son opciones de **compilación**, no basta con reiniciar Nginx:

   ```bash
   docker compose --env-file .env.production -f docker-compose.production.yml up -d --build web
   ```

4. Comprobar en el dominio definitivo que `/` y las guías responden 200, tienen canonical correcto y no tienen `noindex`; que `/robots.txt` permite rastreo y referencia `/sitemap.xml`; que login, API y documentos siguen excluidos y los archivos privados requieren autorización; y que una URL inventada devuelve 404.
5. Verificar la propiedad del dominio en Google Search Console y Bing Webmaster Tools mediante DNS. Enviar `/sitemap.xml` e inspeccionar la portada y una guía. Son acciones externas pendientes: no se han creado cuentas, modificado DNS ni enviado URLs.
6. Revisar el sitio público con PageSpeed Insights, datos de Core Web Vitals y pruebas de resultados enriquecidos. No hay puntuación Lighthouse ni medición real de usuarios en este cambio.

El valor por defecto es `VITE_SEO_INDEXABLE=false`, también en Docker local: cabeceras y metadatos `noindex`, robots bloqueado y sitemap vacío. No publicar ese estado esperando tráfico orgánico. No activar indexación en staging. Un robots bloqueado no elimina por sí solo una URL previamente indexada: para retirarla hay que permitir que el buscador lea su `noindex` y utilizar sus herramientas si es urgente.

La compilación rechaza indexar sin dominio y rechaza orígenes HTTP, locales, IP, dominios de ejemplo, credenciales, rutas y parámetros. Nunca utiliza un Host enviado por un visitante para generar canonicals.

## Mantenimiento

- `frontend/src/publicRoutes.js`: única lista de páginas públicas prerenderizadas, compartida con el router.
- `frontend/src/seo.js`: metadatos y lista explícita de páginas indexables. Añadir aquí solo contenido público aprobado.
- `frontend/src/content/guides.js`: contenido editorial compartido, sin datos de clientes. Revisarlo cuando cambie el producto, con lenguaje comprensible y sin publicar recomendaciones fiscales no verificadas.
- `frontend/src/entry-public.js` y `frontend/tools/build-public.mjs`: render aislado al compilar, sin peticiones a API ni acceso a datos personales. Generan también las reglas de Nginx en `dist-ssr`; el contenedor solo copia las reglas y los archivos públicos, no el renderizador.
- `frontend/nginx.conf`: mantener la lista de rutas privadas recargables al añadir módulos. Las rutas desconocidas ya no reciben la portada con estado 200.
- Al cambiar `BETA_PROGRAM_ENABLED`, reconstruir **también web**: Compose de producción lo transmite como `VITE_BETA_PROGRAM_ENABLED`. En una compilación manual, establecerlo explícitamente. Revisar portada y condiciones en el cierre de Beta. Si cambian límites o prestaciones, actualizar los textos públicos y el fallback de marketing junto al backend.
- Mantener guías y páginas distintas: no generar decenas de páginas iguales por ciudad o palabra clave, no insertar instrucciones ocultas para asistentes y no comprar enlaces.

## Verificación local

Desde `frontend`:

```bash
npm test
npm run build
npm run test:seo
SEO_BASE_URL=http://localhost:8080 npm run test:seo
```

La última comprobación requiere Docker actualizado y el mismo modo de indexación que la compilación local. Comprueba HTML inicial, metadatos únicos, rutas, esquema, referencias a assets, cabeceras de seguridad, canonical, sitemap, 404, redirecciones y acceso anónimo rechazado por la API. Desde la raíz, `node tools/check-session.mjs` comprueba además login, restauración y logout. Si la cuenta demo tiene doble factor, comprueba que la contraseña sola no da acceso e indica explícitamente que el recorrido autenticado queda pendiente; nunca lee ni desactiva sus claves. `vite preview` no reproduce todas las reglas 404/cabeceras de Nginx: no utilizarlo para dar esas reglas por verificadas.

Para comprobar una compilación indexable sin desplegarla:

```bash
VITE_PUBLIC_SITE_URL=https://alquivo.com VITE_SEO_INDEXABLE=true npm run build
npm run test:seo
# Devolver los artefactos locales al modo protegido después de la prueba:
npm run build
```

## GEO y crecimiento después de publicar

GEO aquí significa facilitar que los buscadores y asistentes comprendan y puedan citar el contenido público: respuestas claras, identidad coherente de Alquivo, enlaces rastreables, contenido en HTML y datos estructurados acordes a lo visible. **No requiere abrir el chatbot ni exponer información de usuarios**.

Google indica que sus funciones de búsqueda con IA utilizan los fundamentos habituales de SEO; no exige un archivo especial para IA ni garantiza inclusión. No se añade `llms.txt` como supuesto atajo de posicionamiento. Las reglas genéricas de rastreo no constituyen una política contractual sobre entrenamiento: revisar las políticas de los proveedores si se quiere restringir usos concretos.

Medir consultas, páginas indexadas, impresiones, clics y registros reales antes de ampliar contenido. Tras recoger preguntas de propietarios, mejorar estas guías o publicar respuestas específicas revisadas. Buscar menciones y enlaces genuinos en comunidades, asociaciones o colaboradores relevantes, con autorización y sin spam. No se ha instalado analítica de terceros; definir privacidad y consentimiento antes de añadirla.

Referencias oficiales revisadas:

- [Google: funciones de IA y tu sitio](https://developers.google.com/search/docs/appearance/ai-features).
- [Google: conceptos básicos de SEO para JavaScript](https://developers.google.com/search/docs/crawling-indexing/javascript/javascript-seo-basics).
- [Bing: directrices para webmasters](https://www.bing.com/webmasters/help/bing-webmaster-guidelines-30fba23a).
- [Vue: renderizado del lado del servidor](https://vuejs.org/guide/scaling-up/ssr.html).

El código prepara la base técnica. Autoridad, competencia, utilidad del contenido y tiempo de rastreo influyen en los resultados; no hay promesa de primera posición ni de menciones en asistentes.
