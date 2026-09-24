# Document AI — simulación local

Estado: prioridad 4, sin inferencias reales. No hay modo `live` ni variable que permita enviar archivos al proveedor. `ExtractDocument` sólo puede resolver `FakeAIProvider`; la clave configurada para el chat no se utiliza aquí. No cambiar `ASSISTANT_VALIDATED=false` ni publicar esta capacidad.

## Demostración

1. Entra como administrador local con el segundo factor habitual, sin desactivarlo.
2. Abre **Documentos → Importación IA · demo** (`/documents/import`). Acepta el aviso específico de simulación documental.
3. Descarga el ejemplo de factura o contrato de la pantalla y súbelo. También puedes seleccionar un archivo ya guardado.
4. El worker prepara el borrador. Revisa los campos junto a los fragmentos y descarga el original para contrastarlos. Guarda las correcciones; marca la revisión y confirma.
5. Factura: registra un gasto pendiente por defecto, con vínculo al archivo. Contrato: elige un inmueble y contactos existentes; crea un contrato **en borrador**, sin mensualidades. También puedes elegir vincular el documento a un contrato existente sin cambiar sus datos.

Los ejemplos son ficticios. Se reconocen por sus bytes exactos, nunca por el nombre del archivo. Un archivo diferente produce campos vacíos y un aviso explícito: **no se ha analizado**. Estos ejemplos demuestran el flujo, no la precisión de un modelo. Los contactos nuevos se dan de alta en el formulario existente (en otra pestaña), sin creación implícita por similitud de nombres.

## Componentes

- `Assistant/Documents`: acceso, validación del archivo, contrato JSON cerrado, fixtures y ciclo de revisión/confirmación.
- `Assistant/Jobs/ExtractDocument`: cola existente, sin archivos/textos en el payload; sólo el UUID. Reserva/liquida mediante `AiRunLedger`, routing `document`, cuotas existentes y métrica marcada `simulated`.
- `AiDocumentExtraction`: versión del archivo (HMAC), tipo, actor/cartera, estado, revisión, intento, aviso aceptado, ejecución, caducidad, borrador cifrado y recibo mínimo.
- `CreateExpense` y `CreateLease`: límites transaccionales compartidos con los formularios REST. No herramienta de confirmación para el modelo.
- `DocumentImportView`: pantalla independiente del chat. Recuperación mediante GET, nunca reenvío automático de una escritura de resultado desconocido.

El índice único por cartera/huella/tipo/versión evita procesar dos subidas idénticas. Doble confirmación devuelve el mismo recibo. Una extracción de otro actor de la misma cartera no se comparte automáticamente. Cancelar elimina el borrador; la misma versión cancelada no vuelve a procesarse implícitamente. Los fallos permiten hasta tres intentos explícitos, cada uno con su ejecución; una redelivery del job no llama de nuevo al proveedor. Los recibos mínimos se conservan mientras exista el documento para no repetir operaciones tras expirar el contenido.

## Seguridad y privacidad

- Sólo propietario **administrador local**, correo verificado y MFA; ni activar el gate público permite entrar desde producción.
- Consentimiento separado `documents-simulation-2026-09-23`. Revocar cancela el trabajo pendiente y borra borradores; desactivar el asistente también revoca documentos. Se revalida antes del trabajo, al persistir la salida y al confirmar. Eliminar el original elimina la extracción por FK.
- Original cifrado mediante el vault existente; borrador y evidencia cifrados con `APP_KEY`. Mantener copias de ambas claves por el procedimiento de custodia existente; no almacenarlas en Git.
- MIME real PDF/JPG/PNG, inicialmente 5 MiB, 10 páginas, 20 megapíxeles; `config/ai_documents.php`. `pdfinfo` bajo `prlimit` (256 MiB de memoria virtual, CPU/timeout 5 s), archivo temporal privado y limpieza `finally`. Sin OCR ni renderizado remoto. Scanner existente al subir y antes de extraer. El scanner puede estar desactivado en desarrollo: los tests de rechazo simulan su resultado, **no certifican un antivirus activo local**.
- Documento no confiable: request sin tools/URLs externas, salida JSON cerrada, texto escapado en Vue, IDs sólo elegidos/resueltos por Laravel. Fragmentos propuestos no equivalen a evidencia verificada automáticamente. No ejecución de contenido ni interpretación jurídica/fiscal.
- Dinero decimal sin redondear entradas ambiguas, fechas canónicas o `null`; no conversión monetaria ni periodicidad automática. Ningún inmueble se asigna automáticamente, ni siquiera con una sola coincidencia.
- El gasto guarda campos reales del dominio; emisor/número/base/IVA quedan en revisión y original, no se inventa un subsistema contable. Cláusulas/fechas relevantes del contrato son información de revisión, sin tareas o acciones nuevas.
- Retención del contenido: 30 días y limpieza `assistant:prune`. Métricas minimizadas: política existente de 12 meses. Cuenta eliminada: FK/custodia/borrado privado existentes. El documento y las operaciones confirmadas siguen su propia política.

## Coste y operación

El worker debe estar activo y la cola ser asíncrona (configuración local actual: `database`). Timeout del job 70 s, un intento automático; timeout del proveedor preparado 45 s. Reserva configurable inicial de 0,10 USD dentro de los presupuestos existentes; en simulación conocida se liquida a cero. Fallos de consumo desconocido retienen reserva. Esto no demuestra el coste real de un PDF ni garantiza un precio máximo facturado por un proveedor: falta calibración con datos ficticios y autorización de gasto. Sin promesas de documentos mensuales por plan.

Migración aditiva `2026_09_23_000100_create_ai_document_extractions`: tabla nueva, consentimiento en usuario y `documents.transaction_id`. Aplicar tras copia, con versiones de backend/worker/scheduler coherentes. No ejecutar seeders ni `migrate:fresh` sobre la app.

## Verificación reproducible

```sh
cd backend
php artisan test --filter=DocumentAiTest
php tests/Support/render-document-examples.php
```

La prueba standalone `tests/Support/confirm-document-concurrently.php` exige `APP_ENV=testing`, PostgreSQL y `DB_DATABASE=AI_CONCURRENCY_TEST_DATABASE=alquivo_ai_test_…`; migra únicamente esa base aislada, utiliza una cola real y dos procesos de confirmación. Nunca ejecutar contra la base de aplicación. El cambio interno a entorno local es exclusivamente para comprobar el gate de administrador después de verificar el nombre de base.

Frontend: `npm test`, `npm run build`; fixture de componente real de desarrollo `tests/ui/document-import.html` con cinco escenarios y API simulada, no incluida en el build público. El administrador de la app conserva MFA; esta fixture no lo desactiva ni suplanta una sesión real.

## Pendiente antes del proveedor real

Evaluaciones autorizadas con datos ficticios, costes y límites medidos (PDF incluye texto e imágenes), aceptación de un nuevo aviso que cubra el envío del documento y datos de terceros, revisión operativa del tratamiento y retención del proveedor, prueba real del scanner/despliegue y supervisión de errores/reservas. Las peticiones están preparadas conforme a [File inputs](https://developers.openai.com/api/docs/guides/file-inputs) y [Structured Outputs](https://developers.openai.com/api/docs/guides/structured-outputs); consultar esos contratos y tarifas de nuevo al activar. No se han validado con inferencias reales.

No comenzar prioridad 5 en este bloque.
