# Publicar la landing de Alquivo con formulario propio

Actualizado el 2 de octubre de 2026. Esta guía **sustituye la anterior con Tally y ZIP**.

## Estado y coste

Implementado: formulario nativo con el diseño de Alquivo; email obligatorio, nombre y rango de inmuebles opcionales; consentimiento específico sin premarcar; confirmación en la misma página solo cuando el servidor confirma el guardado. Sin cuentas, pagos, documentos, anuncios, tracking ni acceso inmediato a la beta.

Cloudflare Pages sirve la landing, Pages Functions recibe únicamente `POST /api/waitlist`, D1 guarda las solicitudes en una base **independiente de Laravel** y Turnstile verifica el antispam en servidor. No requiere Hetzner, Laravel, PostgreSQL, OpenAI, Stripe ni Resend. Los registros no se mandan por email automáticamente: se consultan en Cloudflare.

Se aprovechan las cuotas gratuitas de [Pages Functions](https://developers.cloudflare.com/pages/functions/pricing/), [D1](https://developers.cloudflare.com/d1/platform/pricing/) y [Turnstile](https://developers.cloudflare.com/turnstile/plans/). No son ilimitadas; mantener Workers Free, revisar consumo y no contratar planes adicionales. La renovación del dominio va aparte.

**Landing no publicada ni probada en tu cuenta todavía.** El código se prepara en la rama `release/alquivo-beta-validation`; faltan las claves reales, la base D1, sus vínculos y el dominio en tu cuenta Cloudflare. No se han cambiado DNS, enviado datos a proveedores, abierto la app ni activado globalmente su IA.

## 1. Tener el código en GitHub

Se puede utilizar el repositorio existente `Aleix-ef/Alquivo`, también privado. No hace falta separar frontend/backend ni crear otro repositorio.

La rama preparada para publicación es **`release/alquivo-beta-validation`**. Elegirla en Cloudflare, no `main` por costumbre. Comprobar en GitHub que contiene el script `build:landing` y el formulario propio antes de conectar Pages. Las futuras actualizaciones deben revisar los cambios pendientes y pasar la detección de secretos; no usar `git add .` sin revisión.

Deben existir en esa rama el frontend actualizado y sus recursos de marca/fuentes, `frontend/functions/api/waitlist.js`, `frontend/waitlist-server/` y `frontend/migrations/waitlist/0001_waitlist.sql`. No subir `.env`, `.env.landing.local`, `.dev.vars`, copias de bases, documentos ni secretos. El código se descarga para compilar; **Cloudflare solo publica el resultado de landing**, no todo el repositorio.

## 2. Crear Turnstile gratuito

En tu cuenta Cloudflare: **Turnstile → Add widget**.

- Nombre: `Alquivo · lista de espera`.
- Hostnames: `alquivo.com` y, si lo vas a usar, `www.alquivo.com`.
- Añadir también el hostname exacto de Pages, por ejemplo `alquivo-landing.pages.dev`, para probar antes del dominio. Si ese nombre no está disponible, usar el que te asigne Cloudflare.
- Modo: **Managed**.
- **Pre-clearance: desactivado**. No añadir localhost ni autorizar cualquier hostname.

Guardar las dos claves: **Site key** es pública y se usa al compilar; **Secret key** se configura como secreto del servidor. No pegar la Secret key en el chat, en Git ni en ninguna variable `LANDING_` o `VITE_`. El código rechaza las claves oficiales de prueba en producción.

Guía oficial: [creación del widget](https://developers.cloudflare.com/turnstile/get-started/widget-management/dashboard/).

## 3. Crear la base gratuita D1

En Cloudflare: **Storage & databases → D1 SQL Database → Create database**.

- Nombre: `alquivo-waitlist`.
- **Data location → Specify jurisdiction → European Union (eu)**. Una simple sugerencia “Western Europe” no es la misma restricción. [Ubicación y jurisdicciones de D1](https://developers.cloudflare.com/d1/configuration/data-location/).

Abrir la base → **Console**. Copiar el contenido de `frontend/migrations/waitlist/0001_waitlist.sql` y ejecutarlo. Si la consola solo acepta una sentencia por ejecución, ejecutar por separado cada una de las cuatro sentencias completas (dos tablas y dos índices).

Debe haber dos tablas: `waitlist_signups` y `waitlist_rate_limits`. No cargar datos de la app ni crear estas tablas en PostgreSQL. Esta migración no contiene solicitudes de ejemplo.

## 4. Crear Pages conectado a GitHub

En Cloudflare: **Workers & Pages → Create application → Pages → Connect to Git**. En algunas versiones hay que pulsar primero “Get started” dentro de Pages.

Autorizar GitHub solo para el repositorio Alquivo. Seleccionarlo y configurar:

| Ajuste | Valor |
| --- | --- |
| Project name | `alquivo-landing`, si está disponible |
| Production branch | La rama que contenga la landing actualizada |
| Framework preset | None |
| Root directory | `frontend` |
| Build command | `npm run build:landing` |
| Build output directory | `dist-landing` |

**No usar `npm run build` ni `dist`**: esos valores compilan la aplicación completa. `functions/` se queda en la raíz del proyecto frontend, no dentro de `dist-landing`; Cloudflare compila la función por separado.

En variables de compilación de **Production**:

| Variable | Valor |
| --- | --- |
| `NODE_VERSION` | `22` |
| `LANDING_SITE_URL` | `https://alquivo.com` |
| `LANDING_INDEXABLE` | `true` |
| `LANDING_TURNSTILE_SITE_KEY` | La Site key pública de tu widget |

Guardar y desplegar. El primer despliegue puede servir la web antes de vincular la base: **el formulario todavía no guardará** y mostrará un error honesto hasta completar el siguiente paso. No anunciarlo aún.

Si ya creaste un proyecto por carga directa, no usar su botón de subir ZIP para esta versión. Cloudflare no admite Pages Functions mediante subida de archivos desde el panel; crear un nuevo proyecto conectado a GitHub o usar Wrangler. [Restricción oficial](https://developers.cloudflare.com/pages/functions/get-started/), [integración Git](https://developers.cloudflare.com/pages/get-started/git-integration/).

## 5. Conectar D1 y el secreto al formulario

Abrir el proyecto Pages → **Settings → Bindings → Add → D1 database**:

- Variable name: **`WAITLIST_DB`** (exactamente así).
- Database: **`alquivo-waitlist`**.
- Entorno: **Production**.

En **Settings → Variables and Secrets** del mismo proyecto, para Production:

| Nombre | Tipo | Valor |
| --- | --- | --- |
| `TURNSTILE_SECRET_KEY` | Secret/encrypted | La Secret key del mismo widget Turnstile |
| `WAITLIST_ENABLED` | Texto | `true` |
| `WAITLIST_PAGES_HOSTNAME` | Texto | El hostname exacto asignado, p. ej. `alquivo-landing.pages.dev`, sin https ni rutas |

La clave secreta nunca se copia al HTML ni se lee por el generador estático. No hacen falta API keys de Cloudflare en el frontend.

Volver a **Deployments → último despliegue → Retry deployment** para que las variables/vínculos entren en vigor. [Vínculos D1 de Pages](https://developers.cloudflare.com/pages/functions/bindings/).

**Previews:** desactivar despliegues de ramas no necesarias en Settings → Builds & deployments. No vincular la base real ni el secreto de Production a Preview. Si haces previews, poner `LANDING_INDEXABLE=false` en sus variables; para una prueba funcional independiente necesitarías otra base y widget. No usar previews para recopilar interesados.

En Settings → Runtime → Fail open/closed elegir **Fail closed** para la función. `_routes.json` limita su ejecución a `/api/waitlist`, para que las visitas normales sigan siendo estáticas. [Rutas y límites de Functions](https://developers.cloudflare.com/pages/functions/routing/).

## 6. Prueba real antes del dominio

Abrir el dominio `*.pages.dev` de producción.

1. Comprobar portada, privacidad y aviso legal. Los botones deben llevar a `#solicitud`.
2. Enviar una solicitud con un email de prueba propio; marcar conscientemente el consentimiento.
3. Ver «Solicitud recibida» **sin salir de Alquivo**.
4. En D1 → Console ejecutar:

```sql
SELECT email, name, property_count, consent_version,
       datetime(created_at, 'unixepoch') AS fecha_utc
FROM waitlist_signups
WHERE expires_at > unixepoch()
ORDER BY created_at DESC
LIMIT 50;
```

5. Ver la solicitud y su versión de consentimiento. Un segundo envío del mismo email debe mostrar la misma confirmación y no crear otra fila ni sobrescribir la anterior.
6. Borrar el registro de prueba usando una sentencia parametrizada si usas un cliente, o en la consola con el email propio exacto, escapando comillas si procede. No conservar pruebas con datos de terceros.
7. Probar desde móvil; comprobar letra, checkbox, campos, teclado y confirmación.
8. `/login`, `/register`, `/api/v1/auth/me` deben dar 404. `GET /api/waitlist` da 405: es normal, no permite leer la lista.

Si falla: **403** → dominio/origen no permitido; **422** → campos/consentimiento/desafío caducado o widget equivocado; **429** → esperar diez minutos; **503** → revisar base/esquema, secreto, `WAITLIST_ENABLED`, vínculos y que se haya vuelto a desplegar. No compartir secretos ni pantallazos con emails de interesados.

## 7. Añadir alquivo.com

Pages → **Custom domains → Set up a custom domain → `alquivo.com`**.

Usar el asistente de Cloudflare y esperar a que el dominio figure activo con HTTPS. **No borrar MX, SPF, DKIM ni TXT del correo**; modificar únicamente el registro web que indique el asistente. Comprobar que soporte sigue recibiendo y enviando.

Añadir `www.alquivo.com` solo si lo quieres. Incluirlo también en el widget y redirigirlo al dominio principal para evitar duplicados. El canonical ya apunta a `https://alquivo.com`.

Repetir el envío real desde el dominio definitivo y comprobar D1. Ahora se puede compartir la landing. Crear la landing no abre la beta ni cambia `ASSISTANT_ENABLED`/`ASSISTANT_VALIDATED`.

## Consulta y privacidad de las solicitudes

Las solicitudes están disponibles únicamente desde tu cuenta Cloudflare/D1. No hay listado público ni endpoint de exportación; no se mandan sus datos por correo ni a la IA. Por ahora esta consola evita construir una segunda aplicación de administración.

- Activar MFA en Cloudflare y GitHub y limitar acceso a la base.
- Revisar/guardar las condiciones y [DPA de Cloudflare](https://www.cloudflare.com/cloudflare-customer-dpa/) y la [privacidad de Turnstile](https://www.cloudflare.com/turnstile-privacy-policy/).
- La lista no verifica todavía que el solicitante sea dueño del email: no usarla como prueba de identidad, no crear cuentas automáticamente ni enviar campañas. Su consentimiento se limita al acceso a la beta.
- Atender retiradas de consentimiento, correcciones y supresión mediante soporte. Eliminar de D1 las solicitudes retiradas o ya invitadas/convertidas; no extender su conservación mediante nuevos envíos duplicados.
- Cada solicitud tiene caducidad de 12 meses. El código purga caducadas en nuevos envíos verificados; **esto no es un cron**. Programar también una revisión de conservación, y ejecutar esta limpieza desde la consola aunque no lleguen solicitudes:

```sql
DELETE FROM waitlist_signups WHERE expires_at <= unixepoch();
DELETE FROM waitlist_rate_limits WHERE expires_at <= unixepoch();
```

- D1 Free tiene recuperación temporal de hasta siete días según su [política de recuperación](https://developers.cloudflare.com/d1/reference/time-travel/). Las bajas se deben reaplicar si se restaura una copia; no considerar una restauración como autorización para volver a invitar.
- El rate limit es persistente, diez intentos por IP e intervalo de diez minutos. Guarda un HMAC que cambia por intervalo, no la IP original ni el token. Sus filas caducadas se purgan en peticiones posteriores y en la limpieza anterior.
- Mantener apagados anuncios, píxeles, Zaraz y analítica hasta revisar información/consentimientos/CSP.
- El soporte sigue siendo Cloudflare Email Routing → Gmail gratuito: la revisión del buzón profesional sigue pendiente. No solicitar contratos ni documentos de inquilinos en esta landing.

## Verificación local y límites de la comprobación

Pruebas de formulario: `npm run test:landing`. Incluyen guardado con SQL real en SQLite y adaptador de la API D1, transporte HTTP local, consentimiento/versionado, mínimos de datos, deduplicación, conservación, control de origen, antispam, límites de tamaño y frecuencia, error de base/proveedor, estados de cliente y aislamiento del artefacto.

Verificado: 96 pruebas específicas del formulario y 149 pruebas frontend completas, compilación de la app ordinaria, sus 10 páginas SEO y compilación de la función con Wrangler 4.147.0. Portada/privacidad/aviso devuelven 200 en la vista previa y login/API de la app 404. No se ha hecho una revisión visual en navegador ni se ha medido el rendimiento público.

Turnstile está simulado en los tests; no se han enviado emails o datos a Cloudflare ni consultado OpenAI. **La comprobación real de D1 y Turnstile se hace al configurar tu cuenta** con el envío indicado arriba. No sustituye una revisión jurídica ni promete seguridad absoluta.

Para revisar el diseño sin credenciales desde `frontend`:

```bash
npm run build:landing:preview
npm run preview:landing
```

Abrir `http://127.0.0.1:4174/`. El formulario se ve pero el envío está desactivado; no subir esta versión noindex. Detener con Ctrl+C. Para compilar producción local, copiar `.env.landing.example` a `.env.landing.local` y completar solo la Site key pública. Nunca añadir ahí la Secret key.
