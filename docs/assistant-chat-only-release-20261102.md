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

## Despliegue

Pendiente de completar con el resultado efectivo. Solo se reemplazará el web,
con copia externa reciente, imagen anterior conservada y reversión ante fallo.
El procedimiento rechaza cualquier otro cambio en `frontend/src` y exige
backend, archivos públicos, configuración y herramientas idénticos al release
`a3dbf0421c4be84f52916b48059ae6fad3586291`.
