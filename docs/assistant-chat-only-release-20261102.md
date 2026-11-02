# Asistente solo chat — corrección del 02/11/2026

## Motivo y alcance

La entrega anterior conservó el `AssistantWidget` publicado, que todavía incluía
las pestañas «Preguntar por mis datos» y «Cómo usar Alquivo». Se retiran esas
pestañas, el contenido de guías y su botón residual en las respuestas. El aviso
de cuota agotada ya no remite a una pestaña inexistente.

Único archivo de producto modificado: `frontend/src/components/AssistantWidget.vue`.
Soporte y recorrido opcional de la aplicación siguen en sus ubicaciones propias.
No se incluyen los cambios locales pendientes de consentimiento/captura de
calidad. No cambian backend, rutas, modelos, flags, documentos, pagos ni legal.

## Comprobaciones previas

- 256 pruebas frontend aprobadas; dos regresiones nuevas sobre el chat único,
  eliminación de referencias a pestañas y conservación de sus controles.
- Build cliente/SSR, 11 páginas públicas prerenderizadas, SEO y formato correctos.
- El script del componente coincide byte a byte con el anterior salvo eliminar
  la importación de `HelpGuides` y el estado de pestaña. Las funciones de API,
  activación, recuperación y propuestas no se modifican.
- Chrome con el build de esta entrega: escritorio 1600px, móvil 390px y 320px;
  chat único, historial con `insufficient_data`, borrador al cerrar/abrir, Escape,
  foco de retorno, activación y cuota agotada correctos. Sin errores JS.
- Todas las API de la prueba de presentación se interceptan con fixtures
  sintéticas antes de red: sin credenciales, cuentas, lecturas reales, escrituras
  ni gasto de modelos. No se presenta esta prueba como auditoría del backend.

## Despliegue verificado

- Fuente activa `0f0d863c66098f943963942bf84820654c1373ee` desde
  **02/11/2026 21:57:27 UTC** (22:57:27 en España peninsular).
- Publicado en `https://alquivo.com` y `https://app.alquivo.com`; rama de GitHub
  `deploy/dashboard-portfolio-20261102`.
- Copia externa cifrada anterior correcta a las 21:40:02 UTC, dentro del margen
  de 30 minutos requerido. Solo web sustituido; otros seis contenedores,
  entorno y firewall idénticos durante el rollout. Sin migraciones.
- El procedimiento verificó que el único cambio de `frontend/src` es el widget;
  backend, archivos públicos, configuración y herramientas coinciden con
  `a3dbf0421c4be84f52916b48059ae6fad3586291`.
- Nginx, 20 checks HTTP internos y 18 externos correctos; API privadas sin
  sesión devuelven 401. Cuatro assets Dashboard/Shell servidos coinciden por
  SHA-256 con el build efectivo del servidor. Landing, CSP y noindex conservados.
- Chrome contra el código servido por HTTPS: las mismas cuatro comprobaciones
  de escritorio/móvil, historial, cuota y activación aprobadas. Todas las API
  interceptadas con datos sintéticos; ningún dato real o gasto de IA.
- Monitor sin incidencias; temporizadores de copias, vigilancia de copias y
  monitor activos.

Reversión: release anterior `a3dbf0421c4be84f52916b48059ae6fad3586291`, imagen
conservada en `alquivo-assistant-chat-only-rollback:0f0d863c6609`. Checkpoint
privado `/opt/alquivo/shared/assistant-chat-only-20261102` con datos de reversión,
build y marcador `ACTIVATED`. Sin cambios de base de datos que revertir.

Archivo Git SHA-256:
`f8954aa4b149bae4c584fcaba1d212d3bb689828a59dbc356e9eba02645b920b`.
Script de rollout SHA-256:
`ca4c8ef6b4efb543baeb0ac4e3a2fb061fe5bc0bd8536b016ad246857134dcbe`.
