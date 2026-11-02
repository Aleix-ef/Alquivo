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

## Publicación

Pendiente de completar con el resultado efectivo. Se sustituirán web y runtimes
de backend/worker/scheduler (misma versión de código); sin migraciones.
El despliegue limita los cambios de producto a 11 archivos frontend y 2 backend,
exige configuración, dependencias, seguridad y páginas públicas conservadas,
guarda las cuatro imágenes anteriores y protege la reversión.
