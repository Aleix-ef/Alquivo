# Dominio principal de la beta

Solicitud: sustituir la lista de espera de Pages por la portada de la beta y
la aplicación, ambas en `https://alquivo.com`.

## Alcance

- Inicio público con registro directo y acceso a la beta gratuita (50 inmuebles,
  5 GB, sin tarjeta). No se habilitan pagos ni funciones de IA pendientes.
- `app.alquivo.com` se conserva como alias compatible y no indexable. No hay
  redirecciones globales: las firmas de los enlaces existentes dependen del host.
- Los correos nuevos apuntan al dominio principal. Las cookies siguen siendo
  seguras, cifradas y limitadas al host: puede ser necesario iniciar sesión una
  vez en el nuevo dominio. No se trasladan cookies ni tokens entre dominios.
- `/privacidad` mantiene accesible el aviso histórico de la lista de espera;
  `/privacy` es la política de la aplicación. `/aviso-legal` redirige a `/legal`.
- No se importan solicitudes de D1 como cuentas ni se borran solicitudes.
- Cloudflare sigue gestionando DNS, dominio y los registros del correo. Solo
  se retira Pages como alojamiento de la web principal.

## Configuración de producción

```dotenv
PUBLIC_DOMAIN=alquivo.com
LEGACY_APP_DOMAIN=app.alquivo.com
APP_URL=https://alquivo.com
FRONTEND_URL=https://alquivo.com
FRONTEND_URLS=https://alquivo.com,https://app.alquivo.com
SANCTUM_STATEFUL_DOMAINS=alquivo.com,app.alquivo.com
VITE_PUBLIC_SITE_URL=https://alquivo.com
VITE_SEO_INDEXABLE=true
SESSION_DOMAIN=null
```

Reconstruir `web`: el origen canónico y la indexación son valores de compilación.
Recrear los servicios de aplicación para cargar el entorno; conservar todos los
volúmenes, claves y demás opciones. No hace falta una migración de base de datos.

## Cambio DNS (panel del titular)

Cuando la versión esté preparada en el VPS:

1. Guardar una captura de los registros existentes y del dominio de Pages.
2. En Cloudflare → DNS, sustituir el registro de `alquivo.com` que apunta a
   Pages por un registro **A**, nombre **@**, IPv4 **51.75.142.10**, **DNS only**.
   No dejar registros AAAA apuntando al alojamiento anterior.
3. Conservar `app`, MX, SPF, DKIM, SRV y los demás registros del correo.
4. Activar la versión y comprobar el certificado de ambos hosts, `/register`,
   `/login`, APIs privadas (401 sin sesión), cookies y enlaces de correo.
5. En Workers & Pages → `alquivo-landing` → Custom domains, retirar la asociación
   del dominio principal cuando la nueva web funcione. No borrar D1 ni el
   proyecto, y conservar los avisos y consentimientos anteriores.

El subdominio `*.pages.dev` puede seguir existiendo mientras no se desactive o
se redirija por separado. Retirar el dominio personalizado no elimina el proyecto.
No dar por cerrado ese retiro sin comprobar el panel.

## Comprobaciones y reversión

Antes: copia externa reciente, versión anterior y entorno guardados en una
carpeta privada, imágenes anteriores retenidas y validación del Caddyfile.

Después: `security:check`, avisos operativos, copia programada, HTTPS en ambos
hosts, origen canónico único, páginas privadas no indexables, registro y CSRF.
No efectuar llamadas de IA ni envíos a clientes para esta comprobación.

Si falla el cambio de aplicación, restaurar el entorno y las imágenes anteriores
y conservar el host antiguo disponible. No restaurar la base de datos: este
cambio no altera su esquema ni requiere deshacer datos de usuarios.

## Estado

**Activado el 28/10/2026 a las 05:54:09 UTC (06:54:09 en España peninsular).**
El titular cambió el DNS raíz a la IPv4 del VPS, sin proxy y sin AAAA residuales.
La aplicación y la portada de la beta ya se sirven en `https://alquivo.com`.
`app.alquivo.com` conserva acceso HTTPS compatible sin redirigir URLs firmadas.

- Release: `d4bcf4cde3bba7785cddfd538c1b71298d6c0816`.
- Copia externa previa finalizada a las 05:53:38 UTC; entorno anterior e imágenes
  guardados en `/opt/alquivo/shared/apex-beta-20261027` (privado).
- Antes del despliegue: 601 pruebas backend aprobadas y 2 live omitidas;
  220 frontend aprobadas; compilación y SEO de 11 páginas.
- Despliegue: 28 comprobaciones HTTP locales entre ambos hosts; nginx y
  `security:check` correctos; scheduler, siete servicios y monitor sin incidencias.
- Comprobación externa en ambos hosts: certificados públicos válidos, portada y
  enlaces directos, canonical único, páginas privadas no indexables, CSP estricta,
  plan beta único (50 inmuebles), IA existente activa y pagos/fiscalidad apagados.
- CSRF y cookies seguras host-only verificados. Registro con campos vacíos y CSRF
  válido devuelve 422 de validación; APIs privadas sin sesión devuelven 401.
  No se ha creado una cuenta ni simulado un registro exitoso con estos checks.
- Correos nuevos al origen canónico y enlaces anteriores comprobados mediante
  regresiones sintéticas; no se han enviado nuevos correos a clientes.
- Monitor del host actualizado para vigilar HTTPS y certificado de `alquivo.com`;
  27 pruebas offline. Copias, vigilancia de copias, monitor y retención activos.

No hubo migraciones, llamadas reales a IA, cambios de usuarios, permisos,
contratos o documentos. La revisión legal archivada r1 no cambia. La actualización
del watchdog se instala separadamente del release, como el resto de operaciones
del host. No equivale a repetir la evaluación de IA ni a certificar seguridad
absoluta o cumplimiento contractual de proveedores.

**Limpieza de Pages pendiente en el panel:** no se ha retirado su asociación
personalizada ni borrado el proyecto, D1 o solicitudes. El dominio principal ya
no apunta allí; `alquivo-landing.pages.dev` puede seguir existiendo. La retirada
administrativa debe preservar solicitudes y consentimientos.

Referencias: [dominios de Pages](https://developers.cloudflare.com/pages/configuration/custom-domains/),
[HTTPS automático de Caddy](https://caddyserver.com/docs/automatic-https).
