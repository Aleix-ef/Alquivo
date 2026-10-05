# Alquivo

## Espera y rendimiento del asistente — 5 de octubre de 2026

Optimización conservadora: los resúmenes financieros evitan cargar filas que se descartaban y reutilizan los mismos saldos, sin caché de dinero ni cambios de prompt/modelos/seguridad. El chat muestra tiempo de espera real y un aviso neutral; nuevo diagnóstico interno `assistant:latency`, sin contenido de conversaciones ni gasto de IA. **442 pruebas backend correctas, 2.907 aserciones y dos skips live; 171 frontend correctas**, más un subconjunto PostgreSQL aislado. La mejora SQL medida es de milisegundos: no se promete una aceleración de segundos del modelo. **0 USD adicionales**, sin activar beta. [Detalle, medición y comprobaciones pendientes](docs/assistant-latency.md).

## Preparación externa de la beta — 4 de octubre de 2026

**Guía actual de ejecución:** [proveedores, buzón, servidor, copias y decisión de IA](docs/beta-launch-guide.md). La landing está pública y el titular confirma Search Console configurado; la aplicación todavía no tiene servidor. No hay que añadir módulos para cerrar este bloque. La plantilla de producción usa `https://app.alquivo.com`, separada de la landing, con pagos/fiscalidad/IA pública apagados y sin claves privadas.

Revisión legal archivada **`2026-10-04`**, todavía borrador: servicios actuales frente a previstos, objetivos de conservación y límites reales de `store=false`. Gmail gratuito sigue siendo el buzón receptor; el cambio profesional es una propuesta, no una contratación. Faltan contratos efectivos, configuración/retención y recuperación externas, pruebas HTTPS y revisión humana independiente de IA. No se certifica seguridad absoluta ni cumplimiento jurídico.

Comprobación de este bloque: **436 pruebas backend: 434 correctas, 2.835 aserciones y dos skips live previstos; 165 frontend correctas; build/SEO de 10 páginas y preview de landing correctos**. Subconjunto en PostgreSQL 17 vacío: **70 correctas, 523 aserciones y un skip live** (son repeticiones de la suite, no sumar como casos distintos). npm y Composer sin avisos conocidos en los locks consultados. HTTP local respeta MFA y aislamiento de soporte; el recorrido completo de demo se omite sin quitar su segundo factor. Resend aceptó un único correo técnico sintético a soporte; recepción pendiente de confirmación. **Inferencias/coste adicional IA: 0 USD**, sin cambios de `.env`, routing o flags globales. Detalle en [revisión previa a beta](docs/beta-readiness-review.md).

## Landing pública de lista de espera — actualizado el 3 de octubre de 2026

Preparada una landing independiente para Cloudflare Pages, reutilizando el diseño de Alquivo y sin publicar Laravel ni la aplicación. El formulario es propio, no Tally: guarda solicitudes en una base D1 separada, verifica Turnstile en servidor, registra consentimiento versionado y limita abusos sin guardar IP original. Los CTA solicitan acceso a la futura beta; no hay login, creación de cuentas, pagos ni promesas de acceso inmediato. Incluye aviso legal y privacidad de la lista de espera, soporte por email, fuentes locales, SEO y cabeceras de seguridad.

**Publicada en https://alquivo.com.** El titular ha configurado D1 y Turnstile y confirmado el guardado de una solicitud propia; la portada y las páginas legales responden correctamente. La rama conectada a Pages es `release/alquivo-beta-validation`. La landing prioriza móvil: formulario justo después de la presentación, nombre e inmuebles opcionales desplegables, botones «Avísame cuando abra», tres beneficios y menos repetición. El acceso inferior móvil se oculta al usar el formulario, al llegar al pie y tras guardar; todos los enlaces llevan a la misma tarjeta. El estilo de escritorio se conserva con los ajustes de formulario/textos y contraste. `npm run build:landing` exige la clave pública real; `npm run build:landing:preview` permite revisar el formulario con envío desactivado. No publica la app ni activa globalmente la IA. Ver [configuración, mantenimiento y consulta de solicitudes](docs/landing-waitlist.md).

## Revisión previa a beta — 1 de octubre de 2026

**Correcciones de código cerradas; publicación todavía pendiente de comprobaciones externas.** Se han actualizado las dependencias con avisos, limitado notas/descripciones, unificado la protección CSV, normalizado las cuentas sin cartera y añadido evidencia cifrada/versionada de aceptación y retirada de IA sin conservar sus conversaciones. Las regresiones de MFA forman parte del repositorio. Verificación actual: 411 tests backend, 410 correctos, 2.674 aserciones y un skip live previsto; 11 archivos de tests frontend, build y 10 páginas estáticas correctos; npm/Composer sin avisos conocidos en esta auditoría. Los textos y avisos se archivan con huella y control de sobrescritura. Faltan servicios/contratos y conservación definitivos, HTTPS y operación del servidor, copias externas y revisión humana de IA/privacidad. Ver [estado y lista restante](docs/beta-readiness-review.md) y [operación de privacidad](docs/privacy-operations.md). No constituye certificación jurídica ni garantía de seguridad absoluta.

## Correo y publicación — 28 de septiembre de 2026

El dominio está verificado en Resend según el titular y el cliente de Resend ya está integrado. La configuración local usa `MAIL_MAILER=resend` y `soporte@alquivo.com`: se solicitó la recuperación desde la app y el destinatario confirmó haber recibido el correo. No se ha probado aún el enlace de cambio de contraseña ni los demás tipos de mensaje. Ver [activación del correo](docs/email-delivery.md). El buzón de soporte recibe mediante Cloudflare Email Routing hacia Gmail gratuito; esto requiere revisión de privacidad antes de recibir documentos de clientes.

Los datos definitivos comunicados por el titular (autónomo/persona física, NIF, domicilio y correo) ya figuran en las páginas legales. **El aviso continúa en borrador** porque faltan confirmar alojamiento, copias, conservación y garantías de los proveedores, además de la revisión jurídica final. Crear un proyecto en Hetzner no equivale a disponer de un servidor desplegado.


## Inglés de prueba, solo administración — 29 de septiembre de 2026

El selector 🇪🇸/🇬🇧 se muestra únicamente dentro de la aplicación a una sesión con rol local de administrador confirmado por el servidor, como la vista previa de fiscalidad. Landing, registro, recuperación y páginas legales permanecen en español, aunque el navegador prefiera inglés o exista una preferencia antigua guardada. Al cerrar sesión se vuelve al español. La traducción interior sigue incompleta y no se promociona inglés a usuarios de la beta. Ver [estado de la localización](docs/localization.md).

## Estado de IA para beta (25 de septiembre de 2026)

El asistente conversacional se ha habilitado **solo en el entorno local** para que el propietario lo pruebe antes de publicar. GPT-6 Luna es el modelo normal; GPT-6 Sol solo se usa como respaldo técnico si una petición falla o la respuesta no tiene el formato esperado. Astra sigue apagado. La IA tiene ahora protagonismo en el dashboard y en la navegación.

Cada usuario conserva activación voluntaria con aviso previo; no se envía nada hasta aceptar y escribir al asistente. La plantilla continúa apagada por defecto. En la fecha de esa activación no se habían hecho evaluaciones reales; posteriormente se realizaron las pruebas sintéticas documentadas en [evaluaciones](docs/ai-evaluations.md), todavía con revisión humana independiente pendiente. Document AI sigue simulada y la app no está desplegada. Esta activación **no es autorización para publicar**. Revisar pruebas, costes y requisitos legales en [progreso de implementación IA](docs/ai-implementation-progress.md).
Producto SaaS Alquivo. Este directorio es independiente del proyecto académico anterior.

## Textos legales de la beta — 24 de septiembre de 2026

Disponibles en `/legal`, `/terms`, `/privacy`, `/cookies` y `/data-processing`, con enlaces desde landing, registro, guías e interior, índice y opción de imprimir/guardar PDF. El registro exige aceptar la versión que muestra el frontend; el servidor rechaza versiones antiguas y registra la fecha y versión aceptadas sin alterar aceptaciones anteriores.

**Estado: borrador implementado, pendiente de proveedores y revisión final antes de publicar.** Constan el titular autónomo, NIF, domicilio y correo. Resend, Cloudflare Email Routing y Gmail gratuito figuran por separado, pero faltan alojamiento, copias, plazos de conservación y revisión de las transferencias. Los datos públicos se centralizan en `frontend/src/content/legalOperator.js`; no introducir secretos.

Las condiciones propuestas comprometen un aviso de 30 días antes del fin de la beta o una reducción sustancial, sin conversión automática a pago, y 15 días para cambios de subencargados. Requieren cumplir el procedimiento operativo documentado. Guía de publicación, obligaciones y fuentes: [textos legales de la beta](docs/legal-beta.md).

## Demo administradora local — 17 de septiembre de 2026

Por petición del propietario, `demo@alquivo.test` dispone de un rol explícito de administración **solo en local**, con correo verificado y doble factor obligatorio. Puede revisar fiscalidad, asistente, catálogo completo y bandeja del equipo. Su cartera tiene las prestaciones internas del Plan Fundador y, durante la beta, al menos sus límites públicos de 50 inmuebles y 5 GB; mantiene hasta 50 consultas de IA al mes. El proveedor debe estar configurado y se mantiene el consentimiento y los límites de tokens. No se activan pagos ni se concede acceso a carteras ajenas.

El permiso no funciona en producción/staging ni añade a la demo al equipo permanente de soporte. La beta pública y las demás cuentas no cambian. Comando de activación: `docker compose exec backend php artisan demo:admin`; añadir `--revoke` para retirarlo. Ver [administración local](docs/local-admin.md). Verificación: 182 pruebas de backend y 970 aserciones, pruebas de frontend y compilación correctas.

## Tickets de soporte humano

Cada consulta abre un ticket con asunto e historial de mensajes. El usuario puede tener varios abiertos, adjuntar archivos, consultar respuestas, marcarlo resuelto y reabrirlo. Disponible en Ayuda y soporte y en el botón público; la bandeja se actualiza cada 15 segundos mientras está abierta. Los tickets se guardan aunque el correo no esté configurado.

Cada ticket nuevo y cada respuesta posterior del cliente generan un aviso a `soporte@alquivo.com` cuando hay un proveedor de correo real y un worker activo. El aviso incluye solo una referencia y un enlace a la bandeja; los mensajes y adjuntos permanecen dentro de la app. Con `MAIL_MAILER=log` no se genera correo y la bandeja del equipo muestra esa limitación. Aún no se envían avisos por correo a los clientes cuando responde el equipo.

El equipo responde desde `/support/inbox`, con autorización explícita, correo verificado y doble factor. La cuenta elegida es **soporte@alquivo.com**, pero no existía al comprobarla: debe registrarse, verificarse y activar MFA antes de concederle acceso mediante `support:agent`. La demo puede revisar la bandeja únicamente con el rol local descrito arriba; no se han creado credenciales ni permisos permanentes de soporte para ella. Los visitantes conservan acceso solo mientras siga activa su sesión del navegador; su correo declarado no es prueba de identidad.

La IA de soporte queda **pendiente y sin implementar**. Funcionamiento, activación, límites, privacidad, borrado y operación en [tickets de soporte](docs/support-chat.md).

## SEO y descubrimiento en IA — 17 de septiembre de 2026

La portada y las nuevas guías públicas ya se generan como HTML legible sin JavaScript, manteniendo Vue y la API privada. Se añaden metadatos por página, canonical, datos estructurados, sitemap, robots, respuestas 404 reales, enlaces internos y compresión. Las cuentas, datos privados y pantallas de acceso no se indexan; no se ha relajado la CSP y el asistente solo está activado en el entorno local de pruebas.

**La indexación de la aplicación está desactivada en local y en la plantilla de producción.** La landing independiente en `alquivo.com` sí es indexable y el titular confirma Search Console configurado. Mantener `app.alquivo.com` con `VITE_SEO_INDEXABLE=false`: no trasladar la indexación de marketing a las pantallas de cuenta. Manual de contenido y estrategia: [SEO y descubrimiento](docs/seo-and-discovery.md).

Verificación: 150 pruebas de backend / 764 aserciones, 20 pruebas de frontend y comprobaciones SEO de siete páginas generadas correctas. Compilaciones indexable y protegida verificadas; Nginx, estados HTTP, redirecciones y cabeceras comprobados. Se corrigió la respuesta anónima de API sin cabecera JSON (401 en lugar de 500). La cuenta demo tiene doble factor: se ha comprobado que la contraseña sola no da acceso, sin desactivar ni leer sus claves; el recorrido HTTP completo autenticado queda pendiente de completar ese factor. No se ha realizado revisión visual en navegador ni medición de rendimiento público.

## Plan activo: Beta gratuita — actualizado el 2 de octubre de 2026

`BETA_PROGRAM_ENABLED=true` hace que **Beta gratuita sea el único plan visible y accesible** en landing, Planes y API. Incluye **50 inmuebles y 5 GB de documentos/fotos**, gestión de inquilinos, contratos, cobros, gastos, incidencias, calendario, informes, exportación, soporte y doble factor. Incluye las funciones habilitadas para el público; las que siguen en revisión permanecen ocultas y bloqueadas por sus controles actuales. Es gratuito durante toda la beta: **no caduca a los 14 días**. Se aplica a cuentas nuevas y existentes sin modificar su historial de facturación ni borrar datos. Si una cuenta ya supera los límites, los inmuebles excedentes permanecen en consulta; superar el almacenamiento impide nuevas subidas, no descargas. La cuota conjunta de documentos y fotos es de 5 × 1024³ bytes; los límites individuales de subida no cambian.

La beta dispone de **hasta 20 consultas mensuales**, con límites adicionales de 200.000 tokens de entrada y 20.000 de salida. El entorno local ya activa el asistente para las pruebas autorizadas; la plantilla permanece apagada por defecto y cada persona debe aceptar el aviso antes de enviar una consulta al proveedor. Ampliar la beta no modifica `ASSISTANT_ENABLED`, `ASSISTANT_VALIDATED`, los presupuestos ni los modelos. Fiscalidad e inglés conservan su acceso restringido; Document AI real no se habilita y su simulación conserva los controles actuales.

Gratuito y Fundador siguen implementados, pero no aparecen en los catálogos ni pueden contratarse, ni siquiera activando `BILLING_ENABLED` mientras dure la Beta. Se bloquean Checkout y portal tanto por HTTP como dentro del servicio. No hay una ruta pública para activar planes. Los campos históricos de facturación de la cartera ya no se serializan en el perfil: la fuente de límites es `/plans` o `/account/usage`.

**Cierre futuro, manual:** avisar con antelación, preparar la oferta de agradecimiento del Plan Fundador y permitir exportar antes de cambiar condiciones. Solo después cambiar `BETA_PROGRAM_ENABLED=false` y desplegar; `BILLING_ENABLED` debe seguir en `false` hasta validar la comercialización. Las cuentas creadas en Beta tienen Gratuito como base y no se convierten automáticamente en clientes de pago. Revisar por separado las cuentas antiguas que ya tenían una suscripción en Stripe. No se ha implementado ni enviado todavía la campaña de agradecimiento.

**Alta y correo:** las cuentas nuevas pueden usar toda su cartera y las funciones de su plan aunque el correo no esté confirmado. Durante la beta se aplica Beta gratuita; al cerrarla, una cuenta nueva sin pago empieza en Gratuito, sin prueba premium automática. La confirmación se recomienda con un aviso descartable y puede solicitarse de nuevo desde Configuración. Sigue siendo obligatoria para recibir códigos de doble factor por correo y para trabajar en la bandeja interna de soporte, que además requiere doble factor y autorización explícita.

Esta sección sustituye las referencias históricas a cuotas visibles y pruebas de 14 días de los apartados siguientes.

## Beta de validación sin cobros — 15 de septiembre de 2026

**Contratación desactivada por defecto** (`BILLING_ENABLED=false`). Las cuotas siguen visibles, pero ni la interfaz ni la API permiten abrir Checkout o el portal de Stripe. Las altas nuevas quedan en Beta mientras esté activa y después comenzarán en Gratuito; las pruebas antiguas conservan su fecha de fin. No se cancelan automáticamente suscripciones o enlaces externos anteriores: revisar Stripe antes de abrir la beta. Las referencias a contratación más abajo describen el funcionamiento preparado para cuando se reactive, no el estado actual.

Se han incorporado cifrado autenticado de documentos/fotos con una clave independiente, doble factor opcional en Configuración para todos los planes, códigos de recuperación de un solo uso, copias locales cifradas con Restic y un ensayo de restauración aislado. La configuración de producción incluye PostgreSQL privado, proxy HTTPS, worker, scheduler y antivirus; todavía no se ha desplegado contra un dominio real.

**Manual operativo y pendientes:** [preparación de la beta](docs/release-candidate.md). Incluye claves, rotación, recuperación, pagos, despliegue y límites de estas protecciones. No se han contratado servicios ni realizado cargos. Correo real, copia externa, monitorización, documentación legal y revisión visual siguen pendientes antes de recibir usuarios reales.

Comandos locales:

```bash
php tools/check-secrets.php
bash tools/init-backup-secret.sh
docker compose cp backend:/app/storage/app/keys/documents.json secrets/documents.json
bash tools/backup.sh
bash tools/restore-check.sh
```

Conserva las claves en un gestor de contraseñas externo al equipo. La copia en `backups/repository` y las claves en `secrets/` no se suben a Git. El script de copia pausa brevemente la aplicación; el de restauración solo usa contenedores y volúmenes nuevos y desechables.

**Verificación local:** 143 pruebas de backend / 702 aserciones, 5 suites de frontend y compilación correctas. Auditorías Composer/npm sin avisos conocidos en la comprobación del 15 de septiembre. Análisis básico de patrones de secretos sin hallazgos en archivos publicables ni en el historial revisado. Acceso HTTP normal y con doble factor (cuenta temporal eliminada), rechazo de reutilización de código, bloqueo real de Checkout/portal y restauración de PostgreSQL en contenedores aislados correctos. El ensayo recuperó ambas claves mediante una prueba sintética; la base local no contenía documentos ni fotos. La conversión de archivos antiguos y la rotación se prueban con archivos sintéticos en la suite.

## Preparación de la beta — 14 de septiembre de 2026

La beta tendrá **registro abierto**, aunque los primeros usuarios lleguen por invitación personal. No hay listas de correos autorizados ni códigos de acceso. Contacto configurado: **soporte@alquivo.com** (`SUPPORT_EMAIL`). Configurarlo no crea el buzón ni demuestra que reciba correo: hay que verificar su entrega antes de publicar.

- **Acceso:** los correos de recuperación llevan a `/reset-password`. Los de verificación pasan por `/verify-email`, conservan la firma de Laravel y permiten iniciar sesión antes de confirmar; no dependen de que el lector de correo envíe un `Referer` de la app. Se comprueban enlaces caducados, manipulados y de otra cuenta.
- **Funciones pendientes de validar:** Fiscalidad queda oculta y su API deshabilitada con `FISCALITY_ENABLED=false`. La IA conversacional se habilita solo en el entorno local actual; el archivo de plantilla la mantiene desactivada para otros despliegues. No se borran datos ni implementación. Las guías normales siguen en Ayuda y soporte; allí se puede eliminar el historial antiguo de IA.
- **Paso a Gratuito:** se mantiene editable el primer inmueble añadido (orden de ID); los excedentes quedan en consulta, con sus datos, documentos y exportaciones accesibles. Se bloquean modificaciones también por API y cambios de asociación que intenten saltarse el límite. Se permite eliminar archivos y archivar inmuebles vacíos para liberar espacio. No hay borrados automáticos de datos por bajar de plan.
- **Generación al superar el límite:** no se crean nuevas rentas ni movimientos recurrentes de los inmuebles en consulta. Las reglas conservan su próxima fecha; al recuperar el plan, los movimientos recurrentes pendientes se generan con la lógica de recuperación existente, sin duplicados. Las rentas conservan su lógica mensual, sin prometer una reconstrucción automática de todos los meses omitidos. Los estados vencidos de cargos históricos pueden seguir actualizándose.
- **Facturación:** se conserva el acceso al portal con pagos pendientes y se bloquea otra contratación si existe una suscripción local o en Stripe. Checkout usa un intento persistente por usuario, bloqueo compartido e idempotencia; los reintentos reutilizan parámetros y sesión. Se comprueba que el precio remoto sea activo, mensual, en euros y coincida con 6,99 €. Volver por una URL de éxito no se presenta como prueba de pago: los webhooks siguen siendo la fuente de confirmación.
- **Moneda:** los nuevos registros usan EUR. Se impide renombrar importes existentes como otra moneda; las carteras antiguas mantienen su valor almacenado. No se ha implementado conversión multidivisa.
- **Logs web:** el formato de acceso de Nginx omite consultas y referencias para no registrar tokens de recuperación o verificación. Aplicar la misma política al proxy y a los servicios externos de monitorización.

Para verificar el código: `cd backend && php artisan test`; desde `frontend`, `npm test` y `npm run build`. La nueva migración `2026_09_14_000000_create_billing_checkout_attempts` es aditiva; el arranque habitual la aplica sin reiniciar los datos. `node tools/check-session.mjs` comprueba la sesión sobre Docker y respeta las funciones deshabilitadas, sin consultar a IA ni cobrar.

Verificación del 15 de septiembre: **117 pruebas / 591 aserciones de backend**, las 4 suites de frontend y la compilación correctas. Migración aplicada en PostgreSQL local, Nginx validado y comprobación HTTP de login, restauración, datos y logout correcta. La configuración pública confirma IA y fiscalidad desactivadas y el correo de soporte elegido. Aplicación local en `http://localhost:8080`. Sin navegador conectado para revisión visual; sin pruebas reales de correo, IA o pagos en este cierre.

**Pendiente antes de cobrar/publicar:** configurar y verificar el precio mensual de Stripe y sus webhooks en el entorno elegido; probar contratación, renovación, impago y cancelación en sandbox; completar dominio/HTTPS, correo real, alojamiento, antivirus, copias con restauración, monitorización y textos legales. Las pruebas simuladas no sustituyen esas verificaciones. No se han creado productos/precios externos, enviado correos reales ni realizado cargos en este cierre.

## Interfaz Alquivo

### Formulario de soporte anterior

El formulario anterior se conserva solo en los endpoints indicados abajo; la interfaz de **Ayuda y soporte** y el botón flotante **¿Te ayudamos?** usan ahora tickets. El formulario anterior permite nombre, asunto, mensaje y adjuntos, con el correo de la cuenta o del visitante como dirección de respuesta.

Los endpoints `POST /api/v1/support` y `POST /api/v1/public/support` admiten hasta 3 imágenes JPG/PNG/WebP o PDF, máximo 2 MB cada uno. Validan contenido MIME y tamaño, pasan los adjuntos por el scanner existente (obligatorio en producción) y comparten límites por IP y globales, más un campo trampa antispam. El mensaje HTML se escapa, el destinatario siempre procede de `SUPPORT_EMAIL` y el correo del visitante solo se usa como `Reply-To`. No se envía respuesta automática a direcciones introducidas por terceros.

Estos endpoints anteriores envían correo directo y se mantienen por compatibilidad; el flujo visible actual usa tickets persistidos mediante `/support/chat` y `/public/support/chat`. Los adjuntos del formulario anterior usan archivos temporales de la petición y se envían de forma síncrona al buzón; no se crean enlaces públicos ni documentos de cartera. Configura la retención, permisos y proveedor del buzón en la documentación de privacidad antes de publicar.

**Activación del envío:** configurar un mailer real (`MAIL_MAILER`, credenciales privadas y remitente autorizado) y comprobar entrega a `soporte@alquivo.com`. El mailer local `log` no envía ni registra el contenido de estas consultas; devuelve un aviso de envío no configurado y conserva el formulario. También se rechazan transportes de prueba y failover que puedan terminar escribiendo datos en logs. No se han enviado correos reales en las pruebas. SMTP tiene un timeout de 15 segundos; la interfaz no confirma éxito ante un error del proveedor. Si el navegador pierde la respuesta, podría haberse aceptado el correo antes del corte: comprobar el buzón antes de reintentar repetidamente.

Pruebas: `php artisan test --filter=SupportApiTest` verifica envíos simulados, cuenta/no autenticado, privacidad, adjuntos reales sin depender de GD, rechazo de MIME falso, tamaño, cantidad, scanner, errores y límites. `npm test` incluye validación de adjuntos del formulario. Sigue siendo necesaria la prueba de entrega con el proveedor real y una revisión visual en navegador.

Verificación de esta ampliación: 130 pruebas / 639 aserciones de backend y 5 suites de frontend correctas, compilación y acceso HTTP sobre Docker correctos. El entorno local sigue usando `MAIL_MAILER=log`: el formulario se puede revisar, pero no enviará consultas hasta configurar un proveedor real. El correo recibido identifica expresamente si procede de una cuenta con correo verificado, sin verificar o de un visitante.

Interfaz oscura fija, navegación móvil, dashboard con datos reales y propiedades con búsqueda, filtros, fotografías privadas y galería. Sin foto se utiliza una ilustración identificada como tal. Las fuentes y la imagen editorial del acceso se sirven desde la aplicación.

El [sistema visual](docs/design-system.md) documenta los estilos, componentes, accesibilidad, recursos gráficos y comprobaciones. Para revisar la app en Docker: `docker compose up -d --build` y abrir `http://localhost:8080`.

## Estado actual

- Laravel 13 con API versionada en `/api/v1`.
- Autenticación con Sanctum.
- Sesión web mediante cookie HttpOnly y protección CSRF; no se guardan tokens en el navegador.
- Cartera SaaS y aislamiento por cartera.
- Propiedades, contactos y arrendamientos activos con aislamiento por cartera.
- Cargos mensuales separados de los movimientos reales y soporte para pagos parciales.
- Finanzas con ingresos, gastos, beneficio y alquiler pendiente.
- Documentos privados con descarga autorizada y asociación a propiedades o contratos.
- Incidencias con prioridad, estado y costes opcionales.
- Calendario unificado de rentas, vencimientos y recordatorios.
- Reglas de ingresos y gastos recurrentes mensuales, trimestrales o anuales.
- Confirmación manual de movimientos previstos para no confundir previsión con dinero real.
- Cancelación segura de movimientos previstos y edición protegida de movimientos manuales.
- Edición y eliminación autorizada de documentos junto con su archivo privado.
- Mantenimiento de contactos sin permitir borrar el historial de un alquiler.
- Dashboard financiero agregado desde backend.
- Onboarding contextual de propiedad, valoración y primer alquiler.
- Agenda de personas e inquilinos reutilizable al crear contratos.
- Configuración del perfil, cartera y contraseña del propietario; moneda informativa sin conversión.
- Ficha completa de contratos con condiciones, mensualidades, documentos e inquilinos.
- Renovaciones enlazadas sin alterar el contrato ni los cobros históricos.
- Incidencias con responsable, seguimiento, costes y gasto financiero idempotente.
- Documentos filtrables por propiedad, categoría y vencimiento, integrados en calendario y avisos.
- Acceso desde la ficha a incidencias y documentos del inmueble seleccionado.
- Recordatorios completables junto a cobros, contratos y vencimientos documentales.
- Informes de flujo mensual y rendimiento por inmueble basados en movimientos confirmados.
- Exportaciones CSV seguras de propiedades, contratos y finanzas.
- Consentimiento legal versionado y base completa de verificación de correo.
- Las pruebas históricas conservan su vencimiento; las nuevas cuentas no reciben una prueba premium automática.
- Eliminación protegida de cuenta, cartera y archivos asociados.
- Catálogo de beta simplificado: Gratuito y Plan Fundador por 6,99 €/mes.
- Límites de inmuebles y almacenamiento aplicados siempre desde backend.
- Stripe Checkout y Customer Portal mediante Laravel Cashier.
- Suscripciones sincronizadas por webhooks firmados como fuente de verdad.
- Asistente con consultas y propuestas confirmadas de gastos, cobros, teléfonos y notas, historial cifrado, aislamiento por cartera y cuotas mensuales.
- Consultas asistidas sobre patrimonio, inmuebles, finanzas, cobros, contratos, incidencias, documentos y recordatorios.

## Asistente de Alquivo

Implementado pero **oculto por defecto en la beta**. Solo habilitar `ASSISTANT_VALIDATED=true` después de validar respuestas reales, cuotas y tratamiento de datos; también requiere `ASSISTANT_ENABLED=true` y una clave configurada. El apartado siguiente describe su funcionamiento cuando está habilitado.

El botón **Asistente IA** de la barra superior abre un panel lateral ampliable, a pantalla completa en móvil. Incluye preguntas adaptadas a la sección, contexto del inmueble abierto, comparación de meses e inmuebles, desglose de movimientos, enlaces para contrastar respuestas y copia de texto. **Cómo usar Alquivo** ofrece guías con buscador sin gastar consultas ni necesitar saldo de IA. **Ayuda y soporte** ocupa la parte inferior izquierda del menú y tiene su propia página; configura `SUPPORT_EMAIL` con una dirección real para habilitar el contacto por correo.

El frontend nunca recibe la clave ni consulta directamente al proveedor. Laravel autoriza la cartera y conserva las ocho consultas iniciales, con búsqueda segura de inmuebles/contactos, mensualidades y detalle de contratos. Cuatro herramientas **sólo preparan** gastos, cobros de mensualidades existentes, cambios de teléfono y notas de inmuebles. Las tarjetas permiten revisar, editar y cancelar; únicamente su botón de confirmación ejecuta la operación. Repetir una confirmación devuelve el mismo resultado, sin duplicar cobros ni notas. El historial y las propuestas se guardan cifrados. El proveedor se utiliza con `store=false`, sin equipararlo a retención cero.

Incluye la mascota de Alquivo con poses de bienvenida, consulta y respuesta. Las consultas ajenas a la aplicación, la falta de datos, las acciones no implementadas y los problemas de soporte tienen respuestas diferenciadas. El [documento del asistente](docs/assistant.md) explica las protecciones y pruebas pendientes con saldo real. El [progreso de Alquivo AI](docs/ai-implementation-progress.md) distingue lo terminado de las siguientes fases; no basta con tests simulados para validar la calidad del modelo.

Configuración de modelos para el entorno privado, **solo después de aprobar la apertura**; las claves no sustituyen la activación voluntaria, validación ni controles globales:

```dotenv
OPENAI_API_KEY=sk-...
OPENAI_ASSISTANT_MODEL=gpt-6-luna
AI_CHAT_PROFILE=fast
AI_FALLBACK_PROFILE=complex
AI_GLOBAL_MONTHLY_BUDGET_USD=5
```

`backend/config/ai.php` centraliza perfiles, tarifas versionadas y presupuestos por ejecución, cartera y proveedor. `fast` selecciona Luna y `complex` selecciona Sol **solo como fallback técnico**, nunca por complejidad de la pregunta. Astra sigue desactivado. Hay replay offline y un [evaluador opt-in del flujo de producción](docs/ai-evaluations.md); su ejecución real exige datos sintéticos y presupuesto adicional autorizado. Las cuotas de `config/assistant.php` son Beta 20, Gratuito 5 y Fundador/prueba 50 consultas mensuales. Borrar chats no restaura cuota. Las acciones requieren cuenta verificada, activación vigente, permisos y plan habilitado; `AI_ACTIONS_ENABLED=false` las desactiva sin romper consultas. El aviso vigente `2026-10-01` informa de los nombres de contactos que se pueden consultar y del teléfono o texto que el usuario envíe al chat. El asistente no procesa documentos ni actúa autónomamente. Las muestras reales sintéticas están documentadas; faltan revisión humana independiente y pruebas del despliegue antes de decidir beta.

## Document AI — laboratorio local

Facturas y contratos cuentan con un flujo de importación asistida **simulado**, accesible sólo al administrador local desde **Documentos → Importación IA · demo**. Incluye dos PDF ficticios descargables, cola, borrador cifrado con fragmentos, edición y confirmación explícita. Facturas registran un gasto; contratos crean un contrato en borrador con inmueble/inquilinos elegidos o adjuntan el documento a uno existente. Nada se crea antes de confirmar.

No consume saldo ni envía archivos a OpenAI. Sólo los ejemplos conocidos tienen datos precargados; otros archivos muestran campos vacíos y aviso de ausencia de análisis. Requiere consentimiento documental separado; la activación del chat no cambia el modo de Document AI, que permanece en simulación y solo es visible al administrador local. Consulta [operación y demostración de Document AI](docs/document-ai.md) y [estado de implementación](docs/ai-implementation-progress.md). La lectura real de documentos sigue pendiente de evaluación y nueva aceptación de privacidad.

## Fiscalidad premium (beta)

Implementada pero **deshabilitada por defecto** con `FISCALITY_ENABLED=false`. No forma parte de las prestaciones anunciadas mientras no se revise y active explícitamente.

El apartado **Fiscalidad** (`/fiscality`) incluye fichas anuales por inmueble, cálculo explicado y dossier PDF/CSV general o individual, con historial privado de versiones. Está incluido en el Plan Fundador y durante la prueba. La primera entrega cubre supuestos residenciales ordinarios del ejercicio **2025** y señala los datos o casos pendientes; está en beta y requiere revisión fiscal profesional antes de su comercialización como cálculo validado.

La [documentación fiscal](docs/fiscalidad.md) detalla cobertura, arquitectura y limitaciones. La simulación personal de IRPF, 2026 y los casos especiales siguen pendientes. Los informes de finanzas siguen mostrando el flujo de caja, separado del criterio fiscal.

## Stripe en desarrollo

`STRIPE_PRICE_FOUNDER_MONTHLY` debe contener el identificador del precio mensual de 6,99 € creado en Stripe (`price_...`), no el importe numérico. Los precios antiguos no se reutilizan: las suscripciones de prueba locales anteriores deben cancelarse y recrearse. Durante la beta no hay cambios entre planes de pago: Checkout activa el Plan Fundador y el portal de Stripe permite actualizar el método de pago, consultar facturas o cancelar; al finalizar la suscripción se aplica Gratuito. Para recibir eventos en local, instala Stripe CLI y ejecuta:

```bash
stripe login
stripe listen --forward-to http://127.0.0.1:8100/stripe/webhook
```

Copia el valor `whsec_...` mostrado por Stripe CLI en `STRIPE_WEBHOOK_SECRET` y reinicia el backend. Las nuevas cuentas comienzan en Beta gratuita durante el programa y en Gratuito después, sin tarjeta. Una prueba heredada puede conservar sus días restantes en el checkout; los límites pasan a Gratuito al vencer si no hay suscripción activa. La contratación se activará por separado cuando termine la beta y se hayan validado los pagos.

## Cierre funcional de beta

Las listas de producto recorren todas las páginas de la API para que contratos, contactos, documentos, incidencias y movimientos antiguos no desaparezcan. Los cobros admiten importes parciales, corrección y eliminación atómica; un movimiento vinculado no puede editarse por la ruta genérica y descuadrar la mensualidad. Las propiedades vacías pueden archivarse, pero se conserva cualquier inmueble con historial. También se puede elegir y eliminar la fotografía de portada, editar movimientos manuales y gestionar recordatorios. Las acciones destructivas usan diálogos propios de Alquivo y las pantallas principales muestran estados de carga, error y reintento.
- Vue 3, Pinia, Vue Router y Axios.
- Registro, login, shell de producto, dashboard, propiedades, alquileres, finanzas, incidencias, calendario y documentos.
- Identidad visual propia sin Bootstrap.
- Landing pública orientada al registro: propuesta de valor, producto, planes, preguntas frecuentes y acceso directo a la prueba.

## Desarrollo

### Arranque rápido con Docker

Abre Docker Desktop y ejecuta `./levantar-alquivo.sh` desde la carpeta superior (`Inmogest`). El script espera a que los servicios estén listos y muestra la dirección local. También funciona invocándolo por su ruta desde otra carpeta.

Después de cambiar el código, utiliza `./levantar-alquivo.sh --actualizar` para reconstruir las imágenes. Los datos existentes se conservan y el backend aplica las migraciones pendientes al arrancar.

Backend:

```bash
cd backend
php artisan migrate
php artisan serve --port=8100
```

Frontend:

```bash
cd frontend
npm install
npm run dev
```

En otro proceso debe ejecutarse el planificador que genera los cargos mensuales y actualiza atrasos:

```bash
cd backend
php artisan schedule:work
```

En desarrollo el frontend consulta `http://127.0.0.1:8100/api/v1`. Puede cambiarse con `VITE_API_URL`.

Para cargar una cartera de demostración en local:

```bash
cd backend
php artisan db:seed
```

Acceso demo: `demo@alquivo.test` / `demo12345`.

## Verificación

Con Docker levantado y la cuenta demo local, ejecutar `node tools/check-session.mjs` desde la raíz comprueba el recorrido de sesión por HTTP. Las peticiones GET utilizan `Referer` sin `Origin`, igual que las del navegador. Es importante mantener `Referrer-Policy: same-origin` en Nginx y Laravel: `no-referrer` impide a Sanctum reconocer estas peticiones de la SPA y provoca una vuelta al login después de aceptar la contraseña. La prueba no consume IA ni modifica la cartera.

```bash
cd backend && php artisan test
cd frontend && npm run build
```

## Contenedores

La topología incluida utiliza PostgreSQL 17, PHP-FPM, un worker, un scheduler y Nginx sirviendo Vue y la API bajo el mismo origen.

```bash
cp .env.docker.example .env.docker
docker run --rm php:8.3-cli php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
```

Copia la clave generada en `APP_KEY` dentro de `.env.docker`, cambia la contraseña de PostgreSQL y arranca:

```bash
APP_ENV_FILE=.env.docker docker compose --env-file .env.docker up --build -d
docker compose ps
```

La aplicación quedará disponible en `http://localhost:8080`, accesible únicamente desde el equipo local. Para un despliegue público utiliza la plantilla independiente `docker-compose.production.yml` y sigue [seguridad y lanzamiento](docs/security-and-launch.md); no reutilices la configuración local.

### Compatibilidad del cambio de marca

Los textos, recursos activos, remitente y cuenta demo usan **Alquivo**. La caché del navegador pasa a `alquivo_user` / `alquivo_portfolio` y migra automáticamente las claves antiguas. El cambio de nombre de la cookie puede requerir iniciar sesión una vez de nuevo.

Las instalaciones nuevas usan `alquivo` como base y usuario de PostgreSQL. **En este entorno ya existente se conservan la base, el usuario y los volúmenes anteriores**: el archivo privado `.env` de la raíz fija la conexión compatible. No lo borres ni cambies esos valores sin migrar PostgreSQL; usa `docker compose up -d --build` para actualizar este entorno, sin sustituirlo por la receta de instalación nueva. La cuenta demo local se ha renombrado sin volver a sembrar ni modificar su cartera o suscripción.

Los nombres del directorio de trabajo, proyecto/volúmenes de Docker, historial/ramas/remoto de Git y las imágenes históricas de referencia se conservan intencionadamente. No son marca visible de la aplicación. El repositorio remoto y la marca alojada en Stripe requieren una revisión separada; este cambio no modifica cuentas externas ni productos/precios de Stripe. Configura un remitente real al preparar producción y comprueba que el buzón de soporte elegido, soporte@alquivo.com, puede enviar y recibir correo.

El despliegue público requiere:

- `APP_URL` y `FRONTEND_URLS` con el dominio HTTPS real.
- `SANCTUM_STATEFUL_DOMAINS` sin protocolo.
- `SESSION_SECURE_COOKIE=true`.
- credenciales SMTP reales.
- copias de seguridad de `postgres_data` y `private_storage`.
- terminación TLS delante del contenedor `web`.

El contenedor `backend` aplica migraciones antes de iniciar PHP-FPM. `scheduler` ejecuta la generación de cargos y movimientos recurrentes; `worker` queda preparado para correo y tareas asíncronas.

## Criterios arquitectónicos

### Seguridad y salida a producción

Se han reforzado autenticación, verificación de correo, firmas de Stripe, límites de IA y almacenamiento, privacidad del chatbot, borrado de archivos, cabeceras web y dependencias. Las migraciones nuevas conservan el cupo mensual de IA separado del historial. Cada usuario debe activar el asistente tras leer el aviso de privacidad.

`php artisan security:check` valida la configuración técnica y debe fallar en el entorno local. En producción la aplicación bloquea solicitudes si faltan protecciones esenciales. El detalle de controles, pruebas, operación, requisitos legales y tareas todavía pendientes está en [docs/security-and-launch.md](docs/security-and-launch.md). Los textos legales siguen siendo provisionales: no presentar el producto como plenamente conforme hasta completar los datos del titular, proveedores, contratos y pruebas del despliegue.

- Monolito modular: un despliegue sencillo con dominios separados.
- Todas las entidades de negocio pertenecen a una cartera.
- El backend es la fuente de verdad de métricas y reglas financieras.
- Los cargos mensuales se generan mediante un comando idempotente programado diariamente.
- Los movimientos recurrentes se generan como pendientes: el propietario confirma cuándo se han pagado o cobrado.
- Las funciones futuras no se exponen hasta aportar valor al MVP; la IA consulta datos y puede preparar propuestas de gasto, que sólo se ejecutan tras revisión y confirmación explícita del usuario.

## Mantenibilidad y deuda técnica conocida

La base actual es adecuada para continuar el MVP y puede ser entendida por un desarrollador con experiencia en Laravel y Vue sin necesidad de rehacerla. Antes de ampliar el equipo o acelerar el desarrollo conviene consolidar estos puntos, por orden de impacto:

1. **Centralizar planes y derechos de uso.** Crear un servicio único de `Entitlements` que determine prueba activa, plan contratado, límites efectivos, renovación, cancelación y pagos fallidos. Stripe debe seguir siendo la fuente de verdad del cobro, pero el resto de la aplicación no debería interpretar directamente sus estados.
2. **Centralizar el acceso a la cartera.** Sustituir progresivamente las comprobaciones manuales de `portfolio_id` por Policies, route model binding limitado y un contexto de cartera autenticada. Así un módulo nuevo no podrá olvidar accidentalmente el aislamiento entre clientes.
3. **Extraer lógica de los controladores.** Mover validaciones repetidas a `FormRequest`, respuestas a API Resources y reglas comerciales complejas a servicios o acciones de dominio.
4. **Dividir las vistas grandes de Vue.** Extraer componentes reutilizables para modales, formularios, estados vacíos, errores y carga; utilizar composables y stores por dominio cuando exista estado compartido. Las vistas deben coordinar la pantalla, no contener toda su lógica.
5. **Reforzar facturación.** Cubrir con pruebas de integración Checkout, portal, webhooks, fin de prueba, pagos fallidos, periodos de gracia y cancelaciones. Evitar estados contradictorios entre Cashier, Stripe y `Portfolio`.
6. **Automatizar calidad.** Añadir PHPStan/Larastan, ESLint e integración continua para ejecutar pruebas, formato, análisis estático y build en cada cambio.
7. **Documentar la incorporación de desarrolladores.** Mantener un mapa de arquitectura, glosario de negocio, ciclo de vida de suscripción, guía para crear módulos, variables de entorno y contrato OpenAPI de la API.

Esta deuda no impide probar el producto con usuarios. Sí debe resolverse antes de crecer rápidamente con varios desarrolladores o una base relevante de clientes de pago.

## Estrategia de salida al mercado

El MVP no necesita cubrir toda la gestión inmobiliaria. Su promesa inicial es que un pequeño propietario pueda saber qué tiene, cuánto gana, qué está pendiente y qué requiere atención sin mantener varias hojas de cálculo.

### Beta abierta con incorporación acompañada

Antes de invitar clientes reales deben quedar resueltos estos mínimos:

- Recorrido fiable de registro, prueba, contratación y cancelación.
- Correo transaccional real para verificación, recuperación y avisos esenciales.
- Almacenamiento persistente de documentos preparado para producción.
- Copias de seguridad verificadas de base de datos y archivos.
- Dominio, HTTPS, configuración segura de producción y secretos fuera del repositorio.
- Monitorización de errores, logs consultables y una forma visible de contactar con soporte.
- Textos legales y política de privacidad revisados para el mercado donde se venda.
- Analítica básica de producto: registro, primera propiedad, primer contrato, primer cobro y conversión a pago.
- Pruebas end-to-end de los recorridos críticos y webhooks de Stripe.

Con esos puntos se puede empezar invitando personalmente a unos 5-15 propietarios y acompañando su alta, manteniendo el registro abierto a otras personas. No se aplican listas privadas de acceso. La contratación y sus condiciones deben estar verificadas antes de cobrar.

### Funcionalidad a validar antes de construir

Los primeros usuarios deben decidir qué se desarrolla después. Las hipótesis con más valor para investigar son:

- Importación sencilla desde Excel/CSV para reducir la fricción de abandonar las hojas de cálculo.
- Avisos por correo de rentas pendientes, contratos próximos a vencer, documentos e incidencias.
- Mejor gestión de fianzas, revisiones de renta y periodos sin inquilino.
- Resumen fiscal orientativo y exportaciones para entregar a una gestoría.
- Gestión básica de préstamos e hipotecas para calcular patrimonio neto y flujo real.
- Onboarding guiado que lleve al usuario hasta su primer inmueble y contrato en pocos minutos.

No deben incorporarse al MVP sin validación integraciones bancarias, marketplace, mensajería entre propietarios e inquilinos, seguros, automatizaciones avanzadas o gestión para grandes inmobiliarias. El chat de soporte sí está disponible. Las consultas y propuestas del asistente deben medirse antes de ampliar sus capacidades o abrirlas a todos los usuarios de la beta.

Como hipótesis posterior a la beta, queda registrada **Alquivo Enterprise**: una edición para inmobiliarias y gestores que administren carteras de varios propietarios. La idea es aprovechar el mismo núcleo de Alquivo y añadir una experiencia profesional solo después de validar necesidades, permisos, separación de datos y modelo de precios con gestores reales. No forma parte del MVP ni se anuncia todavía. Ver [visión futura de Alquivo Enterprise](docs/future-enterprise.md).

### Señales para ampliar el producto

- Los usuarios completan la carga inicial y vuelven semanal o mensualmente.
- Consultan el dashboard para tomar decisiones, no solo para almacenar datos.
- Registran cobros y gastos de manera recurrente.
- Al menos algunos usuarios pagan sin necesitar descuentos permanentes.
- Varias personas solicitan espontáneamente la misma mejora.

Las peticiones aisladas se registran; las repetidas que refuercen la promesa principal se priorizan. El objetivo de la beta es aprender qué hace imprescindible Alquivo, no cerrar anticipadamente una lista infinita de funciones.
