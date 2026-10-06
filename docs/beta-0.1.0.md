# Primera entrega beta: v0.1.0-beta.1

Fecha: 6 de octubre de 2026. Rama: `release/alquivo-beta-validation`. Etiqueta de Git: `v0.1.0-beta.1`; corresponde al commit que contiene este documento. Guardar una versión en GitHub no despliega Laravel ni abre la beta.

## Cambios incluidos

- Botón de ojo + «Mostrar/Ocultar» en todos los campos de contraseña: acceso/registro, recuperación, cambio de correo/contraseña, eliminación de cuenta y configuración de doble factor. Ocultos inicialmente; conserva validación, autocompletado, valor y selección. Sin almacenamiento nuevo ni cambios de autenticación.
- Cambios pendientes de IA de la entrega anterior: resolución autorizada de inmueble + ciudad, totales históricos, referencias breves dentro del mismo chat, propuestas de altas en revisión y mejoras de aclaraciones. No se cambia el modelo principal ni se habilitan permisos públicos nuevos.
- Guardas deterministas contra reutilizar un homónimo ambiguo del historial, invertir un importe negativo y mezclar deuda de otros inmuebles con el saldo consultado.
- Evaluador de producción con conversaciones sintéticas, repeticiones y presupuesto persistente; resultados intermedios y limitaciones documentados en [evaluaciones](ai-evaluations.md).

## Verificación local de esta entrega

- Backend: 514 pruebas, 512 correctas, 3.528 aserciones y dos pruebas live omitidas deliberadamente. Sin llamadas de pago durante esta comprobación.
- Frontend: 188 pruebas correctas, incluidas cinco regresiones del control de contraseña; compilación de cliente y SSR de diez páginas públicas correcta.
- Detector de secretos, comprobación del archivo legal y revisión de whitespace correctos. Las claves, datos y reportes privados no se incluyen en Git.
- La revisión interactiva en un navegador y las comprobaciones del futuro despliegue siguen pendientes; no se deducen de los tests locales.

## Límites y comprobaciones pendientes

Esta es una versión de código recuperable, no una certificación de seguridad, calidad de IA o cumplimiento legal. La validación manual que confirma el titular no sustituye las comprobaciones del despliegue HTTPS.

- La revisión legal archivada `2026-10-05` sigue en borrador; se actualizará posteriormente con proveedores y operación reales, sin sobrescribir el archivo ni fabricar aceptaciones.
- No se despliega servidor, no se activan pagos, no se crean usuarios ni se cambian roles. Las altas de IA siguen restringidas por su gate actual; Document AI sigue simulada.
- La reevaluación final de IA fue parcial por errores técnicos/reservas de coste. No se presenta como completa; faltan algunos recorridos finales y revisión humana independiente. No hay más gasto automático autorizado por crear esta etiqueta.
- Antes de abrir a usuarios, completar correo/HTTPS, proveedores, copias externas/restauración y operación según [guía de lanzamiento](beta-launch-guide.md).

## Cómo retomar o recuperar la entrega

Consultar `git show v0.1.0-beta.1` y desplegar un commit fijado tras verificarlo. Para trabajar sobre esta versión sin descartar cambios: `git worktree add ../alquivo-beta-0.1.0 v0.1.0-beta.1`. Los próximos cambios tendrán nuevos commits/etiquetas: no mover ni sobrescribir esta etiqueta.

Un rollback de código no revierte la base de datos, archivos o proveedores. No restaurar datos ni ejecutar migraciones inversas sin un procedimiento probado y copia independiente. Claves, `.env`, conversaciones y bases de datos no forman parte de esta entrega.
