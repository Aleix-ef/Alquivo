# Alquivo AI — progreso de implementación

Última actualización: 2026-10-02. Documento de reanudación; distingue código implementado, comprobaciones realizadas y tareas pendientes. No representa autorización de lanzamiento.

## Checkpoint de consultas históricas (2026-10-02)

Corregida la confusión entre consultar el total cobrado/neto de un inmueble y preparar un cobro de una mensualidad. `get_financial_summary` incorpora un alcance explícito `period|all_time`: histórico hasta hoy, fechas obtenidas de registros de la cartera, agregación SQL por estado/dirección/categoría, sin cargar todos los movimientos ni mezclar pendientes con dinero recibido. Los intervalos normales mantienen su límite de tres años y sus defaults. El historial permite recuperar el inmueble de la conversación, pero las cifras se consultan de nuevo en cada turno; «en total» cambia el alcance mensual anterior. `missing_period` se reserva en el prompt para preparar cobros sin mensualidad identificada.

Regresiones cubren histórico de más de tres años, importes decimales, pendientes/cancelados/futuros, separación por inmueble/cartera, fechas incompatibles, histórico vacío y continuación de la conversación anterior sin propuestas ni escrituras. **425 pruebas ordinarias correctas, 2.757 aserciones, dos skips opt-in**; PostgreSQL temporal: **33 correctas y 161 aserciones**, un skip live. Cinco ejecuciones reales sintéticas de Luna (15 llamadas), con tres repeticiones de la continuación: **5 PASS en grader y revisión Codex**, sin revisión humana independiente. Coste conservador adicional **0,0068277 USD**, techo persistente **0,02 USD**. Detalles y artefacto en [evaluaciones](ai-evaluations.md).

Sin cambios en modelos/routing, cuatro rondas, flags globales, consentimientos, MFA, aislamiento, confirmación, idempotencia ni Document AI. No se envían datos reales de usuarios ni se modifica su cartera para probar. Esta corrección no declara la IA lista para beta; conservar pendientes de revisión/despliegue de los checkpoints anteriores.

Corrección aplicada a backend/worker/scheduler locales; web reiniciada para resolver el backend nuevo. PostgreSQL de usuarios y almacenamiento privado conservados, sin reset ni seeders. Base temporal de las regresiones retirada. La comprobación del flujo real de Luna usa autenticación de testing (`actingAs`), no suplanta un login/MFA de navegador.

## Checkpoint de seguridad y privacidad (2026-10-01, posterior a evaluaciones)

Corregidos los cinco hallazgos de código de [revisión previa a beta](beta-readiness-review.md): dependencias, límites de texto, CSV, contexto sin cartera y evidencia mínima de aceptación/retirada. El registro/chat/simulación documental conservan prueba cifrada y versionada separada del contenido, con caducidad y limpieza. Revocar sigue borrando chats/borradores sin reiniciar cuotas ni borrar operaciones confirmadas; confirmación separada, cifrado y aislamiento no cambian. No se fabrica evidencia de aceptaciones antiguas.

Aviso de chat nuevo `2026-10-01`; aviso de simulación documental `documents-simulation-2026-10-01`; revisión legal `2026-10-01-r2`. Requieren nueva aceptación expresa cuando se utilicen, no autorización automática. Revisión legal/retención provisional pendientes antes de publicar; archivo de contenido y guardia de sobrescritura documentados en [legal-beta](legal-beta.md).

Suite ordinaria: **411 pruebas, 410 correctas, 2.674 aserciones y un skip live previsto**. Frontend: 11 archivos correctos, build y 10 páginas/HTTP correctos. Migración de evidencia aplicada en PostgreSQL local sin reset/seed. MFA de demo se conserva; el recorrido autenticado completo de esa cuenta se omite. Las pruebas PostgreSQL sintéticas adicionales y un nuevo ensayo de restauración quedaron pendientes por bloqueo de la API de Docker Desktop; no contarlas como PASS. Ver checkpoint de seguridad para reanudar/limpiar solo recursos temporales.

**Cero inferencias/coste adicional**, sin nuevas capacidades ni modificación de routing/modelos/rondas. No se han cambiado flags globales ni `.env`: la disponibilidad local sigue la autorización/configuración anterior del 25 de septiembre; no supone apertura pública. No se repiten evaluaciones semánticas por estos cambios administrativos. Resultados de calidad siguen siendo los del checkpoint dirigido de abajo, con revisión humana independiente pendiente. Document AI real sigue bloqueada; no abrir beta por estas pruebas solamente.

## Checkpoint de corrección y reevaluación dirigida (2026-10-01)

Corregidos los contratos de datos financieros (movimientos pagados frente a saldos de mensualidades), búsqueda de inmueble+ciudad, contexto de inquilinos en cobros, HTTP 404 sin cartera y aclaraciones cerradas. Guarda determinista impide mostrar la antigua negación de cobros pendientes. No se añadieron módulos ni se tocaron límites de rondas, routing, flags globales o Document AI.

Resultado: 23 prompts sintéticos, 51 ejecuciones/99 llamadas Luna, 0,0400958 USD conservadores adicionales (<0,25 USD). El historial guarda un fallo peligroso intermedio detectado y corregido; con el código final, 47 ejecuciones revisadas por Codex: 45 PASS, 2 SAFE FAILURE, 0 DANGEROUS FAILURE. Revisión humana independiente pendiente. Suite backend: 325 pass, 2.014 aserciones, 1 skip live. **No abrir aún beta por este resultado solamente**; detalles en [evaluaciones](ai-evaluations.md).

## Checkpoint de evaluación real sintética (2026-09-30)

El chat/actions de producción se ha evaluado con 84 casos ficticios y 100 ejecuciones HTTP controladas (196 llamadas Luna; coste conservador adicional 0,0737453 USD, debajo del techo de 1 USD). Se ha usado el orquestador, prompt, tools, schemas, parsing, routing, validaciones y límites reales; la autenticación HTTP de test no sustituye una prueba browser/MFA. Las cuatro acciones propuestas pasaron preview, confirmación separada, idempotencia y separación de carteras; un «sí» en chat no confirmó. No hay Document AI real ni cambio de flags globales.

La revisión semántica de Codex identifica 79 PASS, 20 SAFE FAILURE y **1 DANGEROUS FAILURE de lectura** (afirmación de ausencia de pendientes con 2.150 EUR de mensualidades pendientes). Revisión humana independiente: pendiente. Otros hallazgos: resolución de «San Nicolás de Valencia», agotamiento de rondas/502 y HTTP 500 para usuario sin cartera. No declarar lista para beta hasta corregir/investigar, repetir casos afectados y revisar manualmente el informe. Detalle y artefactos: [evaluaciones](ai-evaluations.md).

## Activación de IA para pruebas de la beta local (2026-09-25)

Autorización del propietario: habilitar el chat ahora para que pueda probarse antes de publicar y destacar la IA como parte principal del producto. Solo se han activado `ASSISTANT_ENABLED=true` y `ASSISTANT_VALIDATED=true` en el `backend/.env` local, ignorado por Git; las plantillas mantienen la función apagada por defecto. No es un despliegue público.

Chat primario GPT-6 Luna; GPT-6 Sol como fallback ante fallos reintentables o respuestas estructuradas inválidas. El router actual **no** eleva a Sol por complejidad semántica, y ambas llamadas siguen sujetas al límite de presupuesto por turno. GPT-6 Astra permanece desactivado. Precios estándar, coste por tokens y versión guardados en `config/ai.php`; basados en [modelos](https://developers.openai.com/api/docs/models) y [precios](https://developers.openai.com/api/docs/pricing) oficiales consultados el 25-09-2026. El techo global local se baja a 5 USD/mes; se conserva el máximo de 0,10 USD por turno y el de 3 USD por cartera.

Dashboard ahora destaca el asistente con CTA, y la navegación lateral/titular permiten abrirlo. Se conservan consentimiento informado individual, posibilidad de revocarlo y límites de cuota/tokens/coste. El consentimiento avisa que los prompts y datos de cartera usados por herramientas se envían a OpenAI, que puede mantener registros de seguridad hasta 30 días. No se realizó ninguna inferencia durante esta configuración ni se enviaron datos a OpenAI. Document AI continúa en simulación y no se conecta a GPT-6.


## Prioridad 5 implementada y comprobada — Qué necesita mi atención (2026-09-24)

El propietario ha revisado manualmente Document AI y autoriza este bloque. Fuente determinista compartida por dashboard y tool de lectura: cobros, saldos exactos y fechas registradas; reglas y umbrales explícitos. Mantener `ASSISTANT_VALIDATED=false`, cero inferencias, consentimiento y routing actuales. No ampliar Document AI ni tocar planes/pagos, RAG, notificaciones o automatizaciones. Detenerse tras pruebas e inspección visual.

`PortfolioAttention` comparte hechos entre dashboard y tool cerrada `get_attention_items`; `get_pending_items` queda como adaptador. `RentChargeBalances` reutiliza la consulta de cobros reales, sin fiarse de saldos/estados almacenados. Importes decimales exactos, mensualidad parcial/atrasada sin duplicados, vencimientos inclusivos y contratos sin fecha final sin estimaciones. Incidencias, documentos y recordatorios usan únicamente campos existentes y reglas explícitas. Umbrales en `config/attention.php`; sin motor genérico.

Dashboard con «Qué necesita mi atención», importes con céntimos, prioridad explicada, destinos a registros concretos, expansión de todos los avisos y «Todo al día.» acotado a datos/ventanas. Funciona sin IA, no fabrica recomendaciones; un error de carga no se presenta como ausencia de asuntos. Se refresca con los eventos existentes de cobros/contratos. El prompt del chat exige explicar sólo la fuente, no calcular prioridades/saldos ni detectar anomalías.

Verificación: **311 pruebas backend / 1.872 aserciones**, diez archivos de tests frontend (**46 pruebas**), build cliente/SSR y siete páginas públicas correctos. Harness offline **9/9** (replay, no eval real). Cinco escenarios de componentes Vue reales correctos en Chrome a escritorio y 390 px, incluyendo navegación/foco en incidencias, documentos y recordatorios antiguos, refresco, vacío, escape de texto y fallo de red. Capturas inspeccionadas visualmente.

PostgreSQL 17 aislado: suma decimal exacta, consulta correlacionada de pagos, cartera ajena, igualdad de la tool con la fuente, cobro completo y cancelación: correcto, cero proveedor. Base temporal `alquivo_ai_test_attention_20260924` eliminada; no se han reiniciado ni sembrado datos de la aplicación. Sin migraciones nuevas.

Imágenes locales reconstruidas y servicios actualizados en `http://localhost:8080`. Se conserva `ASSISTANT_VALIDATED=false`; se ha desactivado también `ASSISTANT_ENABLED` en `backend/.env` porque antes heredaba `true` y un administrador podía iniciar consultas reales. No se ha tocado la clave, el consentimiento, routing ni planes/pagos. Document AI sigue siendo simulación. Detenerse aquí: el siguiente checkpoint requiere autorización para evaluaciones reales limitadas, todavía **sin permiso de gasto**. Reglas, archivos y limitaciones: [atención determinista](attention.md).

Arranque final: cinco servicios activos, web y PostgreSQL saludables, portada y `/up` con HTTP 200. Checks HTTP de sesión y soporte correctos: MFA de la demo conservado; el recorrido autenticado completo contra datos locales se omite por ese segundo factor, sin consultar claves ni desactivarlo. La inspección de pantallas descrita arriba usa componentes reales y API simulada. Pint, Prettier de este bloque y `git diff --check` correctos.

## Prioridad 4 implementada localmente — Document AI (2026-09-23)

Autorizado entonces: facturas primero; contratos después de validar el pipeline con FakeAIProvider. Sin inferencias reales ni gasto. Sólo administrador local, `ASSISTANT_VALIDATED=false` intacto. El cierre histórico de este bloque precede a la autorización de prioridad 5 documentada arriba.

Decisiones iniciales: documentos privados existentes, cola existente, borradores cifrados separados del chat, confirmación explícita e idempotente. Demo identificada como simulación: sólo fixtures conocidas tendrán datos precargados; un documento cualquiera no se presentará como analizado. Facturas crean gastos mediante `CreateExpense`; contratos vinculan inmuebles/contactos existentes y crean un contrato del dominio tras revisión. No interpretación legal ni fiscal. Consentimiento documental separado, revocable; límites técnicos sin promesas comerciales.

Backend y pantalla de revisión de facturas/contratos implementados. Facturas se validaron primero (9 pruebas / 79 aserciones), antes de extender a contratos. Suite completa final: **300 pruebas / 1.763 aserciones**. Frontend: nueve archivos de tests y build cliente/SSR correctos. Pantalla Vue real en Chrome con API simulada: cinco escenarios correctos (edición/confirmación, doble clic, respuesta perdida, estado desconocido, contrato, consentimiento/cancelación/recarga); vista estrecha de 390 px sin desbordamiento. PDF ficticios renderizados e inspeccionados visualmente.

PostgreSQL 17 aislado: cola `database` real, worker ejecutando ambas extracciones y dos procesos confirmando simultáneamente cada tipo: una operación, una auditoría y mismo recibo. Se corrigió en esta comprobación la respuesta inicial (hidratar estado/revisión por defecto antes de responder). Base temporal eliminada al finalizar. Sin llamadas HTTP al proveedor.

Desplegado en `http://localhost:8080`: migración aditiva `2026_09_23_000100` aplicada en lote **13**, imágenes de backend/web/worker/scheduler reconstruidas, cinco servicios activos. Sin reset ni seeders. Copia previa validada `/tmp/alquivo_before_document_ai_20260923.dump`, modo 600; copia temporal de PostgreSQL, no sustituye las copias de claves/archivos de producción. Checks de sesión y soporte correctos; MFA conservado y recorrido HTTP autenticado completo omitido por ese segundo factor, sin consultar sus claves.

`ASSISTANT_VALIDATED=false`, `queue=database`, aviso separado `documents-simulation-2026-09-23`. Sólo administrador local; no depende de la clave del proveedor del chat. El job **no tiene modo live** y usa exclusivamente FakeAIProvider. Se preparan peticiones cerradas sin tools, pero no se evalúa la precisión ni el coste reales. Pendientes proveedor real, antivirus/despliegue de producción y nuevo consentimiento de envío documental. Se detiene aquí: **prioridad 5 no iniciada**. Guía de uso, límites, seguridad y archivos: [Document AI](document-ai.md).

Detalles del bloque: borradores cifrados y evidencia limitada, revisión persistente, confirmación explícita/recuperable, deduplicación por cartera/huella/tipo/versión, revocación y caducidad a 30 días, recibos mínimos para idempotencia. Factura utiliza `CreateExpense`; contrato comparte `CreateLease` con el formulario manual y crea sólo un contrato en borrador o vincula uno existente. Los inquilinos/inmuebles se eligen explícitamente; los nuevos se registran en sus formularios existentes. No se crean mensualidades, obligaciones fiscales, interpretaciones jurídicas, recordatorios ni comunicaciones.

## Prioridad 3 implementada (2026-09-22)

- `AssistantLeasingQueries` añade `search_contacts`, `list_rent_charges`, `list_leases` y `get_lease_details`. Proyecciones limitadas por cartera, contactos archivados excluidos, coincidencias múltiples/truncadas sin resolver escrituras. Las consultas financieras y rentabilidad determinista anteriores se conservan.
- Los saldos se calculan sobre los cobros realmente registrados; `get_pending_items` reutiliza esa misma consulta para no discrepar si el saldo almacenado está desactualizado. Los totales no se limitan a la muestra de 20. Vencimientos inclusivos por fecha y fines de contrato ausentes sin estimar.
- Propuestas `contact_phone`, `rent_payment` y `property_note`, con edición, cancelación, confirmación independiente, recibo idempotente y snapshots cifrados. `ProposalActionRegistry` separa los contratos de cuatro tipos del ciclo común `ActionProposalService`; la columna cifrada existente `property_snapshot` conserva su nombre por compatibilidad. Sin migración adicional.
- Acciones de dominio `UpdateContactPhone`, `RecordRentPayment` y `AppendPropertyNote`. El cobro reutiliza `RentCharge`/`Transaction`, admite pagos parciales, calcula dinero exacto y no genera mensualidades. La nota usa `Property.notes`, añade hasta 2.000 caracteres sin sustituir anteriores y limita el total a 10.000, sin crear un dominio artificial de notas.
- Los formularios REST de teléfono y cobros reutilizan las acciones. El mantenimiento de cobros históricos permite corregir/eliminar tras cancelar el contrato; esto no permite crear cobros nuevos en contratos cancelados ni reactiva mensualidades canceladas. Se mantienen permisos, límites y vínculos.
- Las tarjetas Vue muestran teléfono anterior/nuevo, mensualidad/saldo y texto de nota. Una confirmación refresca Contactos, Finanzas, Contratos o la ficha del inmueble. Los destinos se construyen a partir de IDs validados; respuesta perdida se recupera por GET, sin repetir escrituras automáticamente.
- Aviso de activación `2026-09-22`: las herramientas pueden compartir nombres de contactos/participantes, pero no teléfonos guardados, DNI, correos, notas anteriores ni archivos. Un teléfono nuevo o una nota escritos por el usuario sí pasan al proveedor. Se exige nueva aceptación; el gate público de IA sigue desactivado.

Verificación de este bloque: **281 pruebas backend / 1.602 aserciones**, ocho archivos de tests frontend, build cliente/SSR y nueve escenarios de componente real en Chrome correctos. PostgreSQL 17 aislado comprueba dos confirmaciones simultáneas por cada uno de los cuatro tipos (una sola escritura/recibo/auditoría), dos propuestas distintas por el mismo saldo (un éxito, un conflicto 409, un único pago), y las cuatro consultas nuevas. Cero llamadas al proveedor. Base temporal eliminada tras la prueba. Pint, Prettier y `git diff --check` correctos.

Despliegue local completado: imágenes reconstruidas y contenedores actualizados de backend, web, worker y scheduler, sin resetear ni sembrar la base. Cinco servicios activos; web y PostgreSQL saludables. Portada y `/up` responden 200 en `http://localhost:8080`. Checks de sesión/soporte correctos; el recorrido autenticado completo se omite porque la demo exige MFA, sin desactivarlo. Migraciones existentes aplicadas hasta lote 12, ninguna nueva en este bloque. Confirmados aviso `2026-09-22`, acciones habilitadas y `ASSISTANT_VALIDATED=false` conservado. El harness continúa siendo replay de nueve casos, no una comparación real de modelos ni validación de comprensión natural. No se ha consumido saldo de la API de Alquivo.

## Objetivo aprobado

Evolucionar el asistente actual incrementalmente. Primer bloque: una petición natural prepara un gasto persistente, el usuario revisa/edita/cancela y confirma mediante botón, Laravel ejecuta la operación de forma transaccional e idempotente y Finanzas se actualiza. El modelo nunca confirma acciones ni accede a SQL. Este patrón precede a contactos, cobros, documentos e inteligencia.

## Completado antes de este bloque

- Auditoría del dominio real y propuesta aprobada.
- Línea base: 182 tests backend / 970 assertions y 7 archivos de tests frontend correctos.
- El asistente actual tiene ocho consultas cerradas, consentimiento, cuota y cálculos deterministas.

## Implementado en este bloque: prioridades 1 y 2

### Fundamento mínimo

- `AIProviderInterface`, `OpenAIProvider` y `FakeAIProvider`, `AIModelRouter`, `AssistantOrchestrator` y `ToolRegistry`. El orquestador reutiliza las consultas existentes; `OpenAiPortfolioAssistant` queda como adaptador de compatibilidad. No hay segundo proveedor ni infraestructura genérica de agentes.
- Configuración centralizada en `backend/config/ai.php`: perfiles, tarifas versionadas, presupuestos, capacidades de acciones y caducidad. Máximo cuatro rondas; fallback opcional de una llamada, desactivado por defecto. Se conserva el perfil `legacy` y el modelo ya configurado, sin migrarlo automáticamente.
- `ai_runs` y `ai_run_steps` registran actor, cartera, estado, versión de routing, tokens, costes estimados y auditoría sanitizada. Cada llamada reserva presupuesto antes de consultar al proveedor y liquida con la tarifa capturada en ese momento, usando nano-USD enteros.
- Presupuestos por ejecución, cartera y proveedor; `ai_provider_usage` conserva un agregado mensual anónimo que no se reinicia al borrar una cuenta. Si falta el consumo fiable, la reserva permanece y se marca el coste incompleto; no se presupone que la llamada fue gratuita.
- Idempotencia del turno mediante `client_request_id`, ligado al actor, cartera y contenido; reutilizarlo con otro contenido se rechaza. La interfaz siempre envía UUID. Se puede recuperar el resultado por `GET /assistant/runs/{run}` sin otra inferencia. Los clientes antiguos que omiten UUID siguen admitidos, pero no tienen esa deduplicación por clave de cliente.
- Mensajes y metadatos del historial, carga de propuestas y snapshot del inmueble cifrados en reposo. Acceso limitado al propietario del turno/propuesta y a su cartera. Consentimiento actualizado a `2026-09-21`; se requiere aceptar la nueva versión.

### Primer flujo: gasto revisado y confirmado

- «Registra 84 € de fontanería en San Nicolás» usa `search_properties` dentro de la cartera. Varias coincidencias producen aclaración con enlaces seguros; el contexto de una ficha autorizada permite distinguir inmuebles con el mismo nombre.
- `propose_expense` sólo puede preparar una propuesta después de resolver un único inmueble en ese turno. No existe herramienta de confirmación: escribir «sí» no ejecuta el gasto.
- Propuesta persistente, versionada, cifrada y con caducidad de 30 minutos. La tarjeta Vue permite revisar importe, inmueble, categoría, concepto, fecha y estado; editar campos admitidos, cancelar o confirmar explícitamente. Cambiar el inmueble requiere una nueva propuesta.
- `CreateExpense` es la acción de dominio compartida por el formulario manual y la confirmación IA. Se validan importes decimales, fechas y campos; se revalidan propietario, cartera, acceso al inmueble, plan, consentimiento, integridad, revisión y caducidad al confirmar.
- Bloqueos de filas y transacción agrupan gasto, recibo y auditoría. Repetir la confirmación devuelve el recibo anterior; una revisión obsoleta no ejecuta nada. Además de las regresiones secuenciales, la prueba con dos procesos simultáneos en PostgreSQL 17 ha producido un único gasto y un mismo recibo.
- Ante pérdida de respuesta, Vue recupera el estado canónico sin repetir automáticamente la escritura. Tras ejecutar, emite la actualización que recarga Finanzas. La edición/confirmación no invoca al modelo ni exige saldo para una propuesta ya preparada.
- Borrar una conversación o desactivar el asistente elimina sus propuestas, no los gastos ya confirmados. Limpieza programada: contenido/propuestas a 30 días y métricas minimizadas a 12 meses.

### Evaluaciones tempranas

- Harness `php artisan ai:eval` con nueve casos sintéticos, proveedor fake, grader determinista y comparación contra una variante regresiva.
- Comprueba herramientas, argumentos, estructura, aclaraciones y cifras de las respuestas grabadas; informa tokens/costes sintéticos y tiempo local. Cambiar de perfil recalcula tarifas sobre las mismas fixtures: **no compara la calidad real de esos modelos**.
- `--live` está bloqueado expresamente. Falta reutilizar el flujo/prompt de producción e integrar presupuestos y autorización explícita antes de evaluar modelos con red. No se ha realizado ninguna llamada de inferencia real ni generado gasto de API en este bloque. Detalles en [evaluaciones](ai-evaluations.md).

## Pendiente: siguientes prioridades aprobadas

1. **Evaluaciones reales controladas, antes de abrir la función:** mismos casos, datos ficticios, activación explícita de la prueba con gasto limitado y comparación de herramientas, argumentos, exactitud, invenciones, aclaraciones, latencia, tokens y coste. Ampliar las fixtures del harness con el flujo de propuestas. No cambiar routing por intuición ni dar por validada la comprensión natural por usar un mock.
2. **Revisión con proveedor real de prioridad 3:** el código de consultas y acciones ya está implementado y probado con datos sintéticos. Falta evaluar elección semántica de herramientas, homónimos y periodos ambiguos, importes y claridad de respuestas antes de activar la función para usuarios de la beta.
3. **Evaluación real de prioridad 4:** el pipeline local de facturas/contratos ya está implementado y verificado con fixtures (ver bloque superior). Falta evaluar extracción real, datos ilegibles/ambiguos y costes; requiere autorización de gasto y nuevo aviso de privacidad antes de enviar archivos. Sin RAG, embeddings ni base vectorial.
4. **Prioridad 5 implementada:** fuente determinista, dashboard y herramienta de lectura comprobados. Queda evaluar fidelidad de explicaciones con un modelo real, no añadir proactividad ni automatización autónoma.

## Decisiones

- Mantener monolito Laravel y dominio Assistant; no otro proveedor, RAG, embeddings ni microservicio.
- No activar pagos ni cambiar visibilidad de planes. Se conservan los controles `ASSISTANT_VALIDATED`, `ASSISTANT_ENABLED`, proveedor y consentimiento para nuevas consultas. Las capacidades de acciones se separan de la disponibilidad de la clave del proveedor, manteniendo autorización y consentimiento al confirmar.
- Mantener el modelo de lectura configurado como referencia inicial; perfiles nuevos configurables, sin afirmar calidad sin evals reales.
- Gasto = operación económica confirmada. Una tool sólo crea preview; no existe tool de confirmación.
- Preservar los numerosos cambios anteriores del worktree, incluidos soporte, administración y beta. No se ha creado un commit que mezcle trabajo previo: este archivo actúa como checkpoint persistente. Revisar y separar cambios antes de preparar commits coherentes.
- No llamar a un proveedor real durante tests ordinarios ni exponer secretos.

## Migraciones de este bloque

- `2026_09_20_000100_create_ai_runs`: `ai_runs` y `ai_run_steps`.
- `2026_09_20_000150_encrypt_ai_message_content`: columnas cifradas largas y backfill reanudable del historial/metadatos; vacía el contenido antiguo en claro al completar cada fila.
- `2026_09_20_000180_create_ai_provider_usage`: agregado anónimo de presupuesto por proveedor y mes; inicializa sus totales con ejecuciones existentes.
- `2026_09_20_000200_create_ai_action_proposals`: propuestas persistentes.

**Estado local:** las cuatro migraciones se han aplicado en PostgreSQL de la aplicación, lote 12, sin reinicializar datos ni ejecutar seeders. Antes se creó y validó un `pg_dump` en formato custom: `/tmp/alquivo_before_ai_20260921_01.dump`, permisos `600`. Es una copia local temporal, no una estrategia de copias de producción; no incluye una copia separada de `APP_KEY`.

Se pararon web, backend, worker y scheduler durante la actualización; se reconstruyeron sus imágenes y se levantaron los cinco servicios. El historial de esta base tenía **cero mensajes**: no se presentó una migración de datos históricos reales como prueba del backfill. La conversión/reanudación sí está cubierta por las fixtures, incluidas 206 filas en varios lotes.

Aplicar con copia de seguridad y mantenimiento: parar peticiones/trabajadores antiguos durante el backfill, conservar `APP_KEY` y reconstruir también worker/scheduler. No ejecutar `migrate:fresh` ni sembrar de nuevo la base existente. El rollback del cifrado restaura texto en claro para compatibilidad; no es una operación de mantenimiento rutinaria. Ver [operación del asistente](assistant.md).

## Tests de este bloque

Resultados confirmados el 2026-09-21:

- Suite completa backend: **241 pruebas / 1.259 aserciones** correctas, incluidas las regresiones de presupuesto global tras borrar cuentas y resolución de inmuebles homónimos. El subconjunto `AiInfrastructureTest|AssistantExpenseFlowTest` pasa **24 pruebas**.
- Pruebas de propuestas: permisos y aislamiento, revisiones, caducidad, cambios de inmueble/plan/consentimiento, payload inválido, cifrado, doble confirmación secuencial, rollback conjunto de gasto/recibo/auditoría y reutilización de validación por gastos manuales.
- Pruebas de flujo: búsqueda/aclaración, propuesta sin escritura, edición y confirmación, recuperación del turno, ausencia de herramienta de confirmación, errores/fallback y ninguna inferencia adicional al confirmar.
- Infraestructura/cifrado: tarifas capturadas, consumo inválido o desconocido, liquidación idempotente, reservas y límites, red simulada, backfill reanudable, corrupción que falla de forma cerrada y autorización al leer mensajes.
- Frontend: `npm test`, **8 archivos de pruebas** correctos; `npm run build` correcto, incluidas compilación cliente/SSR y siete páginas públicas.
- Componente Vue real con API simulada en Chrome: **5 escenarios correctos**, incluyendo edición y doble clic, revisión en conflicto, recuperación tras respuesta perdida, bloqueo ante estado desconocido y cancelación/caducidad/capacidades. Fixture: `frontend/tests/ui/assistant-expense.html`, sólo desarrollo.
- Harness offline: **9/9 casos**; la variante regresiva detecta una cifra inventada y sale con error deliberadamente. No es una prueba con OpenAI real.
- PostgreSQL 17 aislado: todas las migraciones correctas y `backend/tests/Support/confirm-proposal-concurrently.php` **correcto con dos procesos simultáneos**: un único gasto, una auditoría de ejecución y el mismo recibo para ambas confirmaciones. La base temporal de prueba se eliminó después; no se ejecutó sobre datos de la aplicación.

Verificación local de despliegue: aplicación disponible en `http://localhost:8080`, portada y `/up` devuelven **200**, `/api/v1/auth/me` devuelve el **401 esperado sin sesión**. Cinco servicios levantados; web y base de datos saludables. El componente Chrome se ha vuelto a comprobar después de la integración: cinco escenarios correctos. `git diff --check` global y formato Pint/Prettier de los archivos de este bloque correctos.

Los checks HTTP de sesión y soporte también pasan: la contraseña de la demo exige segundo factor, sin desactivarlo ni consultar su secreto; el recorrido autenticado completo se omite por ese requisito. Verificados sesión de visitante, CSRF, aislamiento de origen, rutas privadas y cabeceras de soporte, sin crear consultas de soporte.

Se preserva `ASSISTANT_VALIDATED=false` en la beta: **la IA continúa oculta para usuarios normales**, aunque el asistente y el proveedor estén configurados. El administrador local puede acceder para validación. No se ha activado la función públicamente ni efectuado inferencia real durante la comprobación del arranque.

## Riesgos / límites

- La concurrencia se apoya en transacciones/bloqueos de base de datos, no sólo en el lock temporal del chat. Mantener la prueba simultánea PostgreSQL al modificar confirmaciones; una comprobación con dos procesos no equivale a una prueba de carga de producción.
- Los costes son estimaciones con tarifas versionadas, no una factura del proveedor. Queda pendiente conciliación operativa de las reservas de consumo desconocido; mientras tanto retienen presupuesto conservadoramente y pueden bloquear nuevas consultas. Mantener alertas y controles externos de gasto.
- Los tests con mocks y replay no evalúan precisión, latencia ni coste real del modelo. `store=false` no equivale por sí mismo a retención cero del proveedor.
- Las propuestas expiran en 30 minutos, pero su retención de contenido es de 30 días; son plazos distintos. Los gastos confirmados siguen la política normal de datos financieros.
- La recuperación idempotente del turno exige conservar `client_request_id`; un cliente antiguo que no lo envíe no obtiene esa garantía. No reintentar automáticamente una escritura de resultado desconocido.
- El cifrado depende de la custodia y copia segura de `APP_KEY`. El despliegue del backfill no debe mezclar código antiguo y nuevo sobre el historial.
- La importación documental tiene aviso propio de simulación; no habilitar envío al proveedor con ese aviso ni con el consentimiento del chat. La futura extracción real cambia el alcance de privacidad.
- La validación final de producción, proveedor real y despliegue público sigue fuera de lo comprobado. Prioridades 3 y 4 implementadas localmente con proveedor simulado; prioridad 5 implementada con hechos deterministas y flujo de chat simulado. El escaneo local puede seguir desactivado según la configuración existente: los rechazos de antivirus se han probado con un doble de prueba.

## Siguiente paso

Prioridad 5 terminada: detenerse sin nuevas features. Las evaluaciones reales siguen pendientes, sin autorización de saldo: requerir datos ficticios, presupuesto, decisión explícita y privacidad actualizada antes de usar proveedor o abrir la IA a la beta. Incluir las explicaciones de atención en esas evaluaciones. No implementar RAG ni automatización autónoma; no cambiar routing por intuición.
