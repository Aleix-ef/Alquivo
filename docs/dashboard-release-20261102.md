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

Pendientes de registrar al completar la publicación: copia externa previa,
imagen de reversión, sustitución exclusiva de web y comprobación HTTPS de ambos
hosts. No confundir las 271 pruebas del árbol de desarrollo con las 254 de esta
versión aislada.
