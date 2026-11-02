# Fechas, documentos y avisos — entrega del 02/11/2026

## Corrección de alcance

La publicación anterior de la cartera no incluía estos ajustes locales ya
revisados. Esta entrega los incorpora sobre el web activo `0f0d863`, sin copiar
el árbol principal entero ni revertir las correcciones del asistente/landing.

Incluye:

- Finanzas: fechas españolas `05/11/2026`, mensualidades legibles y sin horas
  técnicas; acciones mejor distribuidas en móvil y texto secundario de 14 px.
- Documentos: categorías en español, campos opcionales específicos de cada tipo
  y visibles directamente, sin desplegable. Facturas/impuestos no piden una
  nueva caducidad; una fecha antigua sigue visible y editable, no se borra sola.
- Formulario documental con Cancelar/Escape, foco y reinicio al volver a subir.
  Sin desbordamiento del archivo/formulario en móvil estrecho.
- Validación de fechas al crear/editar: ambos extremos se comparan con el valor
  guardado si la edición es parcial; se admite una sola fecha conocida.
- Avisos de cobro a Finanzas y mensualidad exacta, con registro/control destacado;
  también controles de contrato, incidencia, documento y recordatorio.
  Navegar **no** abre el formulario ni ejecuta la acción. Aviso controlado si el
  cobro no está disponible. Conserva los enlaces anteriores compatibles.
- Ajuste descubierto en QA: al abrir un enlace documental directamente, el
  foco nativo del fragmento podía quedarse en la fila en lugar de Editar.
  Se redirige ese foco a su acción sin pulsarla y se elimina el listener al salir.
- Se conservan la cartera, ficha inicial, patrimonio compacto y mascota flotante
  ya publicados. El asistente sigue siendo solo chat.

No incluye los módulos locales pendientes de captura/revisión de calidad IA,
sus permisos ni sus hooks. No hay cambios de modelos, flags, pagos, legal,
autenticación, esquema, entorno o firewall. No añade funcionalidades nuevas.

## Validación exacta de la entrega

- 265 pruebas frontend aprobadas; build cliente/SSR y SEO de 11 páginas.
- Backend completo: 616 casos, 614 aprobados y 2 pruebas live omitidas,
  4.176 aserciones. Entorno de pruebas sin `.env` real, SQLite en memoria y clave
  de cifrado ficticia. Primera ejecución falló por no haberse definido esa clave
  de prueba; corregida la invocación, sin modificar código ni producción.
- Subconjunto de documentos/archivo privado/mantenimiento/atención/seguridad/
  céntimos: 58 pruebas y 369 aserciones, todas aprobadas.
- Sintaxis PHP y formato de los cuatro archivos PHP modificados correctos.
- Fechas comprobadas en zonas Pacific/Honolulu y Europe/Madrid.
- Chrome con el build exacto: escritorio 1440px, móvil 390px y 320px; navegación
  desde aviso al botón Cobrar, ausencia de apertura/ejecución automática,
  contexto mensual, Escape/foco, destino inexistente, seis categorías de archivo,
  conservación/limpieza explícita de fechas y reinicio del formulario correctos.
- Todas las API del recorrido visual interceptadas con fixtures sintéticas:
  sin sesiones/datos reales, documentos personales, escrituras ni llamadas a IA.
- Fuentes de cartera/resumen/Finanzas/Documentos coinciden con las revisadas
  localmente. No se consideran publicados los archivos experimentales ajenos.

## Publicación verificada

- Release activa `9f1fa59b062aa9f6d04310621356cfb2f1879d25` desde
  **02/11/2026 22:10:09 UTC** (23:10:09 peninsular), en `https://alquivo.com`
  y `https://app.alquivo.com`, rama GitHub `deploy/dashboard-portfolio-20261102`.
- Copia externa cifrada previa completada correctamente a las 22:04:28 UTC.
- Web y runtimes backend/worker/scheduler actualizados, sin migraciones.
  PostgreSQL, antivirus y proxy conservan sus contenedores; entorno y reglas de
  firewall comprobados idénticos. Worker/scheduler detenidos de forma ordenada
  durante el intercambio y reanudados con la nueva versión.
- Alcance comprobado por el procedimiento: 11 archivos frontend y 2 backend de
  producto; dependencias, configuración, autenticación/IA, archivos públicos y
  contenido legal conservados. Claves/env no mostrados ni modificados.
- Nginx y `security:check` correctos. 24 checks HTTP internos y 22 externos;
  documentos, transacciones, cuenta, cartera, resumen y asistente sin sesión
  siguen devolviendo 401. CSP y noindex privados conservados.
- Doce recursos JS/CSS de siete vistas públicas/privadas coinciden por SHA-256
  con los assets reales del servidor y se referencian desde el bundle activo.
- QA de navegador sobre el código servido por HTTPS: cuatro grupos de pruebas
  aprobados, incluidas fechas, seis categorías, cobro contextual, enlace directo
  y móvil 390/320. Todas las API sintéticas interceptadas antes de red.
- Monitor sin incidencias; temporizadores de copia, vigilancia de copia y
  monitor activos. Sin gasto de IA, cuentas nuevas ni documentos de clientes.

Reversión al release `0f0d863c66098f943963942bf84820654c1373ee` conservada en
`/opt/alquivo/shared/interior-dates-alerts-20261102`, con cuatro imágenes previas
etiquetadas `alquivo-interior-rollback:9f1fa59b062a-{backend,worker,scheduler,web}`,
log de build y marcador `ACTIVATED`. No hay migración que deshacer.

Archivo Git SHA-256:
`f627309fa94dd082aa30fb4c146725e547156c4963bdc37ff55d04c5f92cd251`.
Script de rollout SHA-256:
`d5b09b7bcad013d2ba9c738918845912ba05a42ba7f234eda3448d4c2f187a21`.
