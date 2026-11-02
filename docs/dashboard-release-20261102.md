# Resumen de cartera e IA flotante — entrega del 02/11/2026

Entrega autorizada tras la revisión local del titular. Base publicada:
`f7160cd232a6a9aa987700e732b03fd8438966c0`, conservada por la rama de landing.

## Alcance

- Edificio animado en el Resumen, seis inmuebles por grupo y búsqueda/paginación.
- Primer inmueble visible seleccionado, ficha en panel de altura acotada.
- Patrimonio y avisos compactos alineados en escritorio; métricas mensuales
  integradas y actividad con ocupación y renta contratada ya calculadas por API.
- Mascota flotante abajo a la derecha que abre el asistente existente.
- Sin nuevos cálculos financieros de cartera ni cambios en backend, DB, entorno,
  seguridad, permisos, IA, datos legales o landing.

La entrega aislada no incluye las modificaciones locales de captura de calidad
de IA ni su consentimiento/rutas. `AssistantWidget`, routers, páginas legales y
controladores conservan la versión publicada. Solo se añaden estilos del botón
flotante a su CSS y su control en Shell.

## Comprobaciones

- 254 pruebas frontend aprobadas sobre esta entrega exacta.
- Build cliente/SSR y 11 páginas prerenderizadas con indexación activa.
- SEO, formato y diff aprobados; las páginas legales históricas se conservan.
- Backend, Compose, Caddy, rutas, políticas y `AssistantWidget` sin cambios
  respecto a la base desplegada.
- Los componentes de cartera/patrimonio coinciden con los revisados localmente
  en 14 tamaños de pantalla; no se incluyen fixtures ni usuarios de prueba.

## Publicación verificada

- Fuente web activa: `a3dbf0421c4be84f52916b48059ae6fad3586291`.
- Activación: **02/11/2026 21:42:43 UTC**, en `https://alquivo.com` y su alias
  `https://app.alquivo.com`.
- Copia externa cifrada previa completada y verificada a las **21:40:02 UTC**.
- Solo web sustituido; los otros seis contenedores, la huella del entorno y las
  reglas de firewall permanecen idénticos durante el rollout. Sin migraciones.
- Nginx correcto; 20 checks HTTP internos y 18 externos aprobados. Las API de
  cuenta, cartera y asistente sin sesión siguen devolviendo 401.
- El bundle principal referencia los assets nuevos de Dashboard/Shell. Sus cuatro
  recursos JS/CSS servidos por HTTPS coinciden con los SHA-256 del build real del
  servidor. No se supone igualdad de nombres/hash entre builds local y Docker.
- Landing, canonical, noindex privado y CSP conservados. Sin cuentas nuevas,
  mensajes de IA, envíos a clientes ni lectura de registros de clientes.
- Monitor sin incidencias; copias, vigilancia de copias y monitor activos.
- Imagen web: `sha256:0f92631255e0ab0fb40b942d3083de57f654a4ae3164fd43e28906b6fecac702`.

## Reversión

Release anterior `f7160cd232a6a9aa987700e732b03fd8438966c0`, imagen conservada como
`alquivo-dashboard-rollback:a3dbf0421c4b` (ID
`sha256:98ac6460d340260298521f4af165a82ff02c59782f64bcebfb066b427934c124`).
Checkpoint privado `/opt/alquivo/shared/dashboard-portfolio-20261102`, con
imágenes, log de build, datos de reversión y marcador `ACTIVATED`.
El script de entrega restaura el web anterior ante fallo de sus comprobaciones;
no restaura ni modifica la base de datos.

Archivo Git SHA-256:
`74cb98e5725639c15f7c1cb9f600c9edbc61592ba1b04e1f0a63956c70977726`.
Script de rollout SHA-256:
`753a0a6e9fb2f7ee2f2ed9e9f83010136aadb5f6b9fd49efcb6fc8b544a89432`.

No confundir las 271 pruebas del árbol de desarrollo con las 254 de esta versión
aislada. El panel de administración de usuarios no se implementa en esta entrega.
