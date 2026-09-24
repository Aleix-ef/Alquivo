# Qué necesita mi atención — Prioridad 5

Implementado el 24-09-2026. Es una lectura determinista de registros, no un detector de anomalías ni asesoramiento. No necesita OpenAI ni consentimiento del chat para mostrarse en el dashboard. No genera cargos, notificaciones o acciones. No amplía Document AI.

## Fuente de verdad

`PortfolioAttention::snapshot()` produce hechos estructurados. `DashboardController` devuelve sus `items` como `attention`, y el resto como `attention_summary`. `get_attention_items` consume exactamente el mismo servicio, con filtro opcional de inmueble autorizado y páginas de cinco elementos. La herramienta histórica `get_pending_items` es un adaptador del mismo servicio; ya no tiene ventanas ni reglas propias.

`RentChargeBalances` comparte la consulta de cobros entre atención y `AssistantLeasingQueries`. Sólo cuentan movimientos `income/rent/paid` cuyo cargo, contrato, inmueble y cartera coincidan. No se confía en el `paid_amount` ni en el estado pagado almacenados del cargo. Importes y totales se entregan como cadenas decimales; BCMath calcula diferencias y sumas, y la UI formatea sin redondear mediante flotantes.

## Reglas explícitas

Umbrales en `backend/config/attention.php`, por días naturales inclusivos, usando `config('app.timezone')`. Se captura una única fecha de referencia en cada lectura. No hay umbrales elegidos por el modelo ni un motor genérico de reglas.

| Registro | Cuándo aparece | Cuándo deja de aparecer |
| --- | --- | --- |
| Mensualidad | Saldo real positivo y fecha de cobro hasta hoy + 14 días; incluye atrasos anteriores | Cobros válidos cubren el importe, cargo cancelado, contrato borrador/cancelado o inmueble/contrato archivado |
| Contrato | Activo con fecha final registrada hasta hoy + 60 días; incluye fechas superadas | Cambia la fecha fuera de ventana, se elimina la fecha, deja de estar activo o se archiva |
| Incidencia | Abierta/en curso/en espera, con fecha hasta hoy + 14 días o prioridad alta registrada por el usuario | Resuelta/cancelada; o deja de cumplir fecha/marca |
| Documento | Fecha de vencimiento registrada hasta hoy + 30 días, incluidas anteriores | Se elimina/cambia fuera de ventana o se borra el documento |
| Recordatorio | Sin completar, fecha hasta hoy + 14 días, incluidas anteriores | Se completa, elimina o mueve fuera de ventana |

Los contratos terminados pueden conservar mensualidades históricas pendientes: con fecha final, se excluyen periodos posteriores a su mes final; sin ella, se incluyen sólo cargos cuya fecha ya haya llegado. Cancelar un contrato oculta sus avisos, **no cancela movimientos ni declara perdonada una deuda**. Una mensualidad parcial atrasada produce un solo item con el saldo restante y evidencia de pago parcial. Vencer hoy no significa retraso.

Orden: fecha superada → hoy → incidencia marcada para revisar sin fecha próxima → próximo. Dentro de cada grupo: fecha e identificador estable. La prioridad alta de una incidencia es un dato del usuario, no una evaluación del modelo. No se inventa fecha para una incidencia sin ella. Los costes estimados no se suman como deudas. La fecha contractual/documental registrada no determina su efecto o validez legal.

Cada item contiene identificador estable, grupo/tipo, entidad, inmueble mínimo, prioridad y regla, descripción de hechos, fecha, importe cuando corresponda, destino interno y evidencia. No contiene documentos originales, rutas de almacenamiento, DNI, contactos privados ni notas guardadas. Se comprueba la cartera en las relaciones además de en el registro principal; entidades archivadas no generan destinos.

## Interfaz y asistente

- Dashboard: después de las cifras principales, cuatro avisos iniciales y botón para desplegar el resto. Muestra céntimos, motivo, fecha, prioridad y reglas consultables. Vacío: **Todo al día.**, acotado a los registros y ventanas consultados. Un fallo de carga muestra error, no un falso estado tranquilo.
- Cobros/contratos: enlace a su contrato. Incidencias/documentos: foco sobre la tarjeta correspondiente al cargar. Recordatorios: se abre su día, incluso si es antiguo, con opción de volver al calendario completo. Abrir un destino nunca confirma ni guarda una operación.
- Al confirmar acciones del asistente se vuelve a consultar el dashboard mediante los eventos existentes. Volver desde los formularios también carga datos nuevos. No hay polling, cron IA ni ejecución proactiva.
- `get_attention_items(property_id, page)` es una tool cerrada y estricta. `ToolRegistry` comprueba actor, ejecución y pertenencia a cartera; rechaza IDs ajenos y argumentos adicionales. No admite cartera, fecha de referencia, prioridad, umbrales, SQL ni operaciones de escritura elegidas por el modelo.
- Se consultó la skill OpenAI Docs para mantener el [contrato de function calling estricto](https://developers.openai.com/api/docs/guides/function-calling): esquema cerrado, campos declarados y ejecución controlada por la aplicación. No se cambió proveedor, modelo ni routing.
- Totales y `counts` abarcan todo el horizonte, no sólo los cinco elementos de la página. `has_more` indica si hay más. Una página vacía con `total > 0` no significa ausencia de avisos. El total de atención no es el saldo de todas las mensualidades futuras fuera de ventana.
- El prompt sólo permite explicar esos hechos y exige respetar orden, fechas y saldos. Se ha verificado el transporte con respuestas simuladas, **no la fidelidad de un modelo real**. Esta última requiere evaluaciones autorizadas antes de abrir el chat a la beta.

## Verificación y operación

`PortfolioAttentionTest` cubre permisos, carteras cruzadas, cachés desactualizadas, cobros válidos/ajenos/parciales, céntimos, ventanas, cambio de mes, contratos sin fecha, cancelación/archivo, deuda histórica, resolución, duplicados, totales/páginas, dashboard sin proveedor y flujo del chat simulado. `tests/Support/check-attention-postgres.php` exige base temporal explícita y hace rollback; comprobado con PostgreSQL 17.

Frontend: `tests/attention.test.js` y fixture de componentes Vue reales `tests/ui/attention.html` (sólo desarrollo). Comprueban importes grandes exactos, destinos cerrados, expansión, texto no ejecutable, estado vacío, refresco por cobro, error de red y foco en los tres tipos de registro. Capturas inspeccionadas a escritorio y 390 px de contenido.

No hay migraciones en este bloque. Entorno local: `ASSISTANT_VALIDATED=false` y `ASSISTANT_ENABLED=false`; la clave no se ha consultado, mostrado ni eliminado. La IA documental simulada no pasa a live. Las suites configuran explícitamente flags de prueba e interceptan HTTP; no modifican los flags locales. Mantener esos controles hasta autorización para evaluaciones reales.

Límite conocido: para carteras pequeñas se recuperan los registros que cumplen las ventanas y se ordenan en memoria. Las páginas del chat limitan la salida al modelo, no paginan las consultas SQL. Si crece mucho el histórico pendiente, medir y paginar en servidor preservando totales y orden antes de ampliar escala; no presentar esta comprobación como una prueba de carga.
