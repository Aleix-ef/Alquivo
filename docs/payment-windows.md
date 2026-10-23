# Plazo mensual de cobro

## Comportamiento

Crear, editar y renovar un alquiler permite indicar un rango mensual de días enteros del **1 al 31**, ambos incluidos. Valor inicial de un alquiler nuevo: **del 1 al 5**. También se admite un único día, por ejemplo 5–5. Se rechazan rangos invertidos, decimales, nulos o días fuera de ese intervalo.

El último día es el vencimiento de las **nuevas** mensualidades: con 1–5, sigue pendiente durante todo el día 5 y puede marcarse vencida a partir del 6. En febrero y meses de 30 días se limita al último día real del mes (incluidos años bisiestos). Calendario, saldos y avisos siguen leyendo el vencimiento registrado, sin calcular deudas en la IA. No se interpreta como una regla jurídica universal ni se genera asesoramiento contractual.

Los contratos anteriores no se convierten automáticamente a 1–5. Su día existente se representa como un rango de un día. Editar el plazo **no modifica importes, pagos ni fechas de mensualidades ya generadas**, estén pendientes o pagadas; se informa en el formulario. No hay recálculo retroactivo, cambio de periodos ni prorrateo nuevo.

## Contrato de datos y compatibilidad

- `leases.payment_day` conserva su significado de fecha límite, ahora último día inclusivo; admite 1–31.
- Nueva columna nullable `payment_day_start`. Null significa el mismo día que `payment_day`, no un dato perdido ni el día 1. El modelo lo presenta así incluso al serializar registros nuevos sin recarga.
- Migración aditiva sin UPDATE ni borrado de filas. La API antigua que omite el inicio sigue creando un día único; al editar únicamente `payment_day`, pide también un rango de ese único día. Otras ediciones no alteran el plazo.
- Validación común `LeasePaymentWindow`, revalidada contra el contrato bloqueado al editar. Alta y renovación conservan la ruta normal y el aislamiento por cartera.
- Los payloads canónicos antiguos de creación IA/importación no reciben un campo nuevo si no lo proporcionan. No se amplían los schemas de creación IA en revisión ni Document AI. La lectura autorizada de detalles de contrato y el CSV sí incluyen ambos días y explican el vencimiento.
- Formulario reutilizable `PaymentWindowFields` y validador/formateador frontend `paymentWindow.js`. Los días siguen siendo enteros; no se alteran céntimos de importes.

## Verificación

Entrega aislada sobre `cf50e6b`: **590 pruebas backend correctas, 4.045 aserciones, dos skips live previstos; 213 frontend**, build cliente/SSR y diez páginas públicas, archivo legal, formato y control conservador de secretos correctos. Dieciséis regresiones nuevas cubren compatibilidad, migración con filas anteriores, validación/rollback, edición/renovación, vencimiento inclusivo, febrero bisiesto, idempotencia, CSV, lectura IA y otra cartera.

PostgreSQL 17 temporal vacío: **63 pruebas/475 aserciones** de rangos, contratos y propuestas; subconjunto repetido, no sumar como casos diferentes. PHP del host no tenía pdo_pgsql: se usó PHP 8.3.36 en contenedor, sin instalar paquetes ni conectar con bases reales. No se consumen inferencias de IA.

## Despliegue y reversión

Aplicar únicamente `2026_10_23_000000_add_lease_payment_window.php` con copia externa previa. La columna nullable permite seguir ejecutando la imagen anterior durante la migración. Después sustituir backend/worker/scheduler y web, verificar salud/HTTPS, rango publicado y gate IA autorizado ya activo. No arrastrar migraciones de captura de conversaciones del árbol principal ni cambiar cuentas, consentimientos o firewall.

En caso de rollback de imágenes **dejar la columna añadida**; no ejecutar `migrate:rollback` en producción ni borrar comienzos de rango que haya guardado un usuario. La imagen anterior sigue usando el último día como vencimiento, aunque no muestra el inicio hasta recuperar la entrega nueva. El estado efectivo y las huellas se registran en `ovh-deployment-status.md`.
