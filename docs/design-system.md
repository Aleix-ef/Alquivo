# Sistema visual Nareo

La interfaz utiliza la identidad de `Nareo_diseño.png`: azul noche, esmeralda, salvia y acentos dorados. Los datos financieros reales tienen prioridad sobre los elementos decorativos.

## Apariencia y estilos

- `frontend/src/theme.css`: tokens semánticos de los modos claro y oscuro. Las superficies, bordes, texto y estados deben usar estas variables; los colores literales se reservan para gráficos o imágenes con su propio contraste.
- `frontend/src/stores/appearance.js`: preferencia `light`, `dark` o `system`, persistida con la clave `nareo-appearance`. La primera visita sigue el sistema operativo. Si el navegador bloquea almacenamiento, el cambio sigue funcionando durante la sesión.
- `frontend/index.html`: aplica el tema antes de renderizar para evitar el destello blanco al abrir en oscuro.
- `frontend/src/components/ThemeToggle.vue`: selector accesible compartido entre acceso, recuperación y navegación.
- `frontend/src/style.css`: elementos comunes y estilos base de formularios y pantallas. `shell.css` define navegación, controles compartidos y adaptación móvil. `dashboard.css` y `property-experience.css` contienen las composiciones específicas nuevas; los demás módulos conservan sus hojas temáticas.
- Las fuentes Plus Jakarta Sans y DM Serif Display se sirven localmente desde `frontend/src/assets/fonts`, con sus licencias OFL. La interfaz no necesita solicitudes a Google Fonts.
- Se respetan `prefers-reduced-motion`, el foco visible y los estados de error/carga. El menú móvil y los nuevos diálogos usan `composables/useDialog.js` para gestionar teclado, Escape y retorno del foco.

## Propiedades e imágenes

`PropertyImage.vue` muestra las fotografías privadas a través de Axios y el endpoint autenticado. Cancela solicitudes que quedan obsoletas y libera los object URLs al cambiar de imagen o desmontarse. La lista y el dashboard usan la portada; la ficha permite consultar las fotografías de la galería.

Si no existe foto, se muestra una ilustración arquitectónica según el tipo de inmueble, identificada como ilustración. No se atribuyen fotos de catálogo a las propiedades de un cliente. Un error de descarga conserva la ficha y muestra el marcador de posición.

Las fotografías admiten hasta 6 MB, conforme a Laravel. Los documentos admiten hasta 10 MB. `backend/docker/uploads.ini` alinea PHP con esas validaciones (`upload_max_filesize=10M`, `post_max_size=12M`); Nginx admite peticiones de 12 MB. En despliegues sin Docker hay que configurar también esos límites en PHP.

## Imagen editorial del acceso

- Original: `frontend/src/assets/nareo-architecture.png`.
- Versión utilizada: `frontend/src/assets/nareo-architecture.webp` (119.460 bytes, misma resolución de 1024 × 1536).
- Generada con la herramienta integrada de la habilidad `imagegen`. Es una imagen decorativa de marca y no representa un inmueble del usuario. WebP es una recodificación del original para reducir el tiempo de carga.
- Prompt utilizado:

> Use case: photorealistic-natural. Asset type: editorial architectural background for Nareo, a premium Spanish property management SaaS sign-in screen. Create a single photorealistic editorial architectural photograph, portrait 1024x1536. A beautifully proportioned Mediterranean contemporary apartment building with rounded pale limestone balconies, thin deep midnight blue metal window frames, subtle greenery and warm natural travertine. Perspective from a shaded neighboring terrace with a glimpse of a quiet coastal city and hazy sea. Building mostly in right half, calm blue-grey negative space sky in upper-left suitable for UI overlay. Warm early evening side light, realistic stone texture, quiet affluent but approachable mood, restrained sage/teal greenery, timeless financial brand. Professional architectural photography, precise credible structure. No people, no text, no logo, no watermark, no collage, no UI mockup. This is decorative brand imagery, not a real user's property.

## Comprobaciones y límites

Ejecutar `npm test`, `npm run format:check` y `npm run build` desde `frontend`. Ejecutar las pruebas de Laravel con SQLite en memoria, nunca apuntándolas a la base de datos utilizada por clientes.

La revisión de navegador cubre acceso y restauración de sesión, persistencia del tema, respuesta al tema del sistema, las pantallas principales en escritorio y móvil, búsqueda de propiedades, diálogos y estados de error. Las solicitudes de subida/edición de la comprobación de interfaz se simulan para no modificar los datos de demostración; Laravel comprueba por separado subida privada, aislamiento entre carteras y metadatos del dashboard.

Antes de publicar, mantener la revisión de los recorridos de contratación y webhooks en el entorno de pruebas de Stripe. Este rediseño no añade ni cambia políticas de cobro. Las pruebas descritas reducen regresiones, pero no sustituyen una prueba completa del producto con usuarios y datos de producción.
