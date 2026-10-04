# Revisión previa a beta — 1 de octubre de 2026

## Checkpoint vigente — 4 de octubre de 2026

Preparación externa sin nuevas funciones ni apertura: [guía paso a paso](beta-launch-guide.md). Los informes de abajo se conservan como histórico; este checkpoint actualiza la verificación, no certifica cumplimiento ni ausencia de vulnerabilidades.

- Privacidad/proveedores: borrador archivado `2026-10-04`; Cloudflare Pages/D1/Turnstile/DNS/Email Routing, Resend y Gmail actual frente a Hetzner/copias previstos. Metas de conservación explícitamente pendientes de operación. OpenAI: `store=false` no elimina logs de prevención de abusos ni acredita residencia UE; contrato/cuenta y entrenamiento voluntario pendientes de verificar por el titular. No se han contratado servicios ni aceptado acuerdos en su nombre.
- Plantilla de producción: `app.alquivo.com`, cookies seguras y sesión nombrada; landing separada. Luna principal/Sol fallback técnico y presupuesto global inicial de 5 USD/mes, sin cambiar el router. IA global, cobros y fiscalidad apagados. `.env` privado intacto.
- Correo: cinco nuevas regresiones HTTPS de recuperación/verificación/tickets/código MFA; Resend aceptó un único correo técnico sintético a soporte desde Laravel local. Recepción pendiente de confirmar; no se prueban con ello enlaces del servidor aún inexistente, cola ni todos los flujos reales.
- Copias: guard `backup-external.sh` reutiliza la herramienta actual y rechaza destino ausente/no montado/tipo local y enlace de repositorio. Regresiones negativas y sintaxis correctas. **No hay montaje externo, cron, caducidad, alerta ni restauración offsite configurados/probados**. La guía exige esos pasos y advierte de la pausa de servicio durante copia.
- Suite ordinaria: **436 pruebas, 434 correctas, 2.835 aserciones, dos skips opt-in live**; 165 frontend correctas; build cliente/SSR y comprobación SEO/CSP de 10 páginas; preview de landing. npm y Composer actual (`composer:2`, el Composer del host es antiguo) sin avisos conocidos al consultar locks. Escaneo publicable, huella legal, Pint, formato y diff correctos.
- PostgreSQL 17 temporal sin puertos: **71 pruebas, 70 correctas, 523 aserciones, un skip live**; MFA, correo, contratos/tools, histórico, propuestas y evidencia legal. Se corrigió un montaje inválido de caché en el contenedor de testing antes del ensayo correcto; no era un hallazgo de la app. Datos exclusivamente sintéticos, sin red hacia proveedores, contenedores temporales retirados. No sumar el subconjunto a la suite como casos distintos ni como evaluaciones reales del modelo.
- HTTP local: MFA de demo conservado; contraseña sola no permite acceso y recorrido autenticado completo omitido. Soporte visitante respeta sesión/CSRF/origen, rutas privadas/no-store/noindex, sin tickets creados. No hay OAuth Google en este checkout.
- **IA: cero inferencias, cero tokens y 0 USD adicionales.** Sin cambio global de flags, confirmación, aislamiento, límites, Astra o Document AI. La revisión humana de los reportes previos sigue pendiente; no hay nuevas clasificaciones PASS/SAFE/DANGEROUS del modelo en este bloque.

Pendientes antes de datos reales: (1) contratos/cuentas, política y revisión final de privacidad, especialmente buzón; (2) VPS HTTPS restringido, firewall, claves, ClamAV/cola/scheduler/monitorización; (3) backup externo recuperado y retención/alertas comprobadas; (4) navegador HTTPS y entrega real de todos los correos/funciones; (5) revisión humana y decisión expresa de IA/apertura. Servidor, copias y pasos del titular en la guía. No hace falta añadir módulos.

## Cierre de las correcciones de código

**Los cinco hallazgos de código del informe inicial están corregidos y verificados en la suite. La publicación sigue pendiente de requisitos externos.** No se garantiza ausencia de vulnerabilidades ni cumplimiento jurídico completo.

- Axios actualizado a **1.20.0**; Laravel **13.34.0**, CommonMark **2.10.3** y Flysystem **3.36.0**. Las auditorías repetidas de npm y Composer no encuentran avisos conocidos en los locks actuales.
- Notas/descripciones limitadas a **10.000 caracteres** en creación, edición y renovación; se conserva el límite compartido de las acciones de dominio. Regresiones de rechazo sin escritura y aceptación de texto válido, Unicode y valores nulos.
- `CsvCell` aplica una única defensa a CSV ordinarios y fiscales: reconoce fórmulas tras espacios, tabulaciones, retornos, saltos y caracteres de formato/BOM. Tests de función y de exportación real; no se ha abierto el CSV en Excel ni se presenta como un pentest de hojas de cálculo.
- `RequirePortfolio` devuelve **404** en rutas que requieren cartera; no elige ninguna cartera por defecto. Autenticación, MFA, soporte y cambio de contraseña siguen accesibles. La cuenta sin cartera también puede eliminarse con contraseña/confirmación, sin error 500.
- Evidencia legal cifrada e independiente para registro/chat/simulación documental, con versión, aceptación/retirada, fecha y caducidad. Es transaccional, evita duplicados, conserva versiones previas y **no reconstruye aceptaciones históricas**. Desactivar IA sigue borrando chat/borradores y conserva operaciones confirmadas/cuotas. No hay acceso para soporte/IA ni un nuevo endpoint.
- Política técnica de evidencia: vigente mientras corresponda y **1.095 días** desde sustitución, retirada o cierre de cuenta; `legal:prune` diario. El plazo está en el borrador de privacidad y requiere revisión profesional según bases y responsabilidades: **no se afirma que la ley fije universalmente tres años**.
- Las **12 regresiones de MFA** ya están en `TwoFactorMethodsTest.php`, dentro de la suite y CI; ya no dependen de un archivo temporal.
- Revisión legal nueva **`2026-10-01-r2`** y avisos de chat/documentos renovados. Archivo con huella SHA-256 y control de sobrescritura en `docs/legal-revisions/`; comprobación en CI. Sigue marcado borrador; conservar el artefacto final/commit fuera del servidor al lanzar. Los avisos nuevos requieren aceptación expresa, no se activan cuentas automáticamente.

### Verificación posterior

- Backend ordinario: **411 pruebas, 410 correctas, 2.674 aserciones**, un skip live opt-in previsto; cero llamadas de inferencia. Subconjunto focalizado: 87 pruebas correctas / 672 aserciones (incluido en el total; no sumarlo como casos distintos).
- Frontend: **11 archivos de pruebas**, build cliente/SSR y **10 páginas** estáticas/SEO/CSP correctos. También pasa la comprobación HTTP contra Docker de páginas públicas, redirecciones, noindex y límite de sesión.
- Escaneo publicable, archivo legal y `git diff --check` correctos. El escaneo no cubre de forma exhaustiva todo el historial Git ni todo tipo de secreto.
- Copia local cifrada previa a actualizar Docker: **`da6cb084`**, integridad de todas las piezas verificada. No sustituye destino externo ni sus contratos/retención/alertas.
- Docker local reconstruido, migración de evidencia aplicada en **PostgreSQL, lote 16**, sin reset ni seed. La demo sigue exigiendo MFA: la contraseña sola deja `/auth/me` en 401; recorrido completo de esa cuenta omitido, sin consultar sus claves ni desactivar MFA. Soporte visitante: sesión/CSRF/origen/aislamiento correctos, sin crear tickets.
- HTTP local confirma revisión legal **`2026-10-01-r2`** y salud 200. `/public/config` local devuelve beta gratuita y pagos desactivados; **el asistente local aparece disponible por la configuración preexistente**. No se han editado `.env` ni los interruptores `ASSISTANT_ENABLED`/`ASSISTANT_VALIDATED`, no se ha publicado un servidor ni abierto la beta. No presentar el flag local como validación de calidad o aprobación legal para usuarios.
- **Gasto adicional de IA: 0 USD.** No se ha modificado routing/modelos ni aumentado rondas. Document AI continúa simulada.
- **Verificación adicional bloqueada, no aprobada:** se preparó PostgreSQL 17 vacío (`alquivo-security-audit-pg-20261001`) con datos exclusivamente sintéticos. El PHP del host no tiene `pdo_pgsql`; se construyó un runtime desechable con ese driver. La API de Docker Desktop dejó de responder (incluido `_ping`/`ps`), aunque la web siguió en 200, antes de obtener resultados de las 63 pruebas en ese runtime y del nuevo ensayo de recuperación. Se cancelaron los clientes/scripts bloqueados; la copia previa `da6cb084` sí terminó y fue verificada. No contar pruebas PostgreSQL o restauración como correctas sin resultado. Retomar tras reiniciar Docker Desktop y eliminar el contenedor temporal solo por su nombre/ID comprobados; nunca `down -v` en la app.

### Lista actual de pendientes

1. **Servicios y contratos efectivos:** alojamiento/región, copias externas, entidad/condiciones de OpenAI y garantías de Cloudflare/buzón. Completar `legalOperator.js` usando los servicios realmente contratados y sus acuerdos, no suposiciones.
2. **Buzón y conservación:** revisar el contrato/uso del Gmail gratuito para datos de soporte o utilizar un buzón profesional apropiado. Fijar retención de buzón, copias y logs y confirmar la política de evidencia con el asesor.
3. **Alojamiento definitivo:** HTTPS/dominio, cookies, proxy/origen, firewall, DB privada, claves separadas, actualizaciones y monitorización. `security:check` debe pasar allí; el Compose local HTTP no es una configuración de publicación.
4. **Copia externa y recuperación:** configurar copia fuera del servidor, caducidad y alertas; restaurar desde ese destino y reaplicar supresiones. La copia local verificada no cierra este punto.
5. **Operación real:** motor/firmas ClamAV y EICAR/fallo cerrado, scheduler/limpiezas, correo de verificación/reset/MFA/soporte de extremo a extremo y recorrido navegador sobre HTTPS/PostgreSQL.
6. **Aprobación de privacidad y documentación final:** validar bases, responsabilidades, riesgo y necesidad de evaluación adicional; contratos, política de evidencias/bloqueo y procedimientos de derechos/incidentes. Preparación en `privacy-operations.md`; todavía no acredita ejecución operativa. Generar otra revisión final al completar datos y guardar su artefacto/commit. Producción nueva sin datos de desarrollo; si se mantienen cuentas reales previas, resolver sus condiciones sin inventar aceptaciones.
7. **Decisión de IA para beta:** revisión humana independiente del informe sintético y de los dos SAFE FAILURE; pruebas reales de sesión/MFA/confirmación antes de decidir apertura pequeña. No se repiten evaluaciones con proveedor en este bloque ni se autoriza Document AI real.

Comprobación local adicional pendiente de reanudar: PostgreSQL sintético y recuperación tras el bloqueo de Docker Desktop. La migración real de la app ya consta aplicada; el bloqueo no es un fallo demostrado del código de Alquivo.

Se puede empezar con infraestructura. No queda uno de los cinco hallazgos originales de código abierto; todavía no procede publicar con datos reales hasta cerrar/acotar lo anterior.

---

## Informe inicial conservado como histórico

**Decisión: todavía no publicar para usuarios con datos reales.** La revisión de código y las pruebas muestran controles sólidos, pero existen correcciones concretas y requisitos operativos/documentales pendientes. No es un pentest del alojamiento, una certificación jurídica ni una garantía de ausencia de vulnerabilidades. No se ha cambiado el funcionamiento de la aplicación, activado la IA ni realizado inferencia de pago durante esta revisión.

## Evidencia obtenida

- Backend completo: 326 pruebas, 325 correctas, 2.014 aserciones, una prueba live opt-in omitida de forma prevista. Datos de pruebas aislados; no documentación real de usuarios.
- Frontend: 11 archivos de pruebas correctos, compilación cliente/SSR correcta y comprobación de 10 páginas públicas/estáticas, metadatos y CSP correcta.
- Escaneo de artefactos publicables: sin patrones conocidos de credenciales ni archivos privados. Este escaneo cubre el estado publicable actual, no todo el historial de Git ni todos los formatos posibles de secreto.
- API local sin sesión: `/api/v1/auth/me` responde 401, privada y no cacheable. La página legal devuelve CSP restrictiva y bloqueo de iframes.
- `security:check` en Docker local falla de forma esperada por entorno local/depuración, HTTP, cookies y sesiones de desarrollo, contraseña de DB de desarrollo, CORS HTTP y antivirus desactivado. No presentar el Compose local como configuración pública.
- Las pruebas focalizadas de los auditores están incluidas en la suite ordinaria; no sumar sus resultados para inflar el número de casos distintos.
- Comprobación adicional fuera de la suite ordinaria: 12 pruebas sintéticas de MFA y 227 aserciones correctas en `/tmp/BetaMfaAuditTest.php`. Correo correcto/erróneo/caducado/reutilizado y reenvío, ambos factores en ambos órdenes, cookie segura de 90 días (válida a los 89 y caducada a los 90), cuenta ajena, token manipulado y revocación/reset. SQLite en memoria, notificaciones simuladas y peticiones externas bloqueadas. Este test temporal no está incorporado a la suite del repositorio; conservar estas regresiones al cerrar el bloque de seguridad.
- Auditorías actuales de dependencias: npm detecta avisos en Axios; Composer detecta cuatro avisos en tres paquetes PHP. Consultas realizadas a registros de dependencias, sin enviar datos de cartera ni claves.

## Controles comprobados

| Área | Resultado y alcance |
| --- | --- |
| Aceptación del registro | Casilla requerida y no premarcada; backend exige aceptación y versión vigente, conserva fecha/versión. Privacidad informativa separada de aceptación contractual y de IA. |
| Identificación del titular | Datos facilitados integrados en aviso y privacidad; versión frontend/backend `2026-10-01`. Domicilio y NIF son públicos intencionadamente. |
| Autenticación y autorización | No se encontró un bypass confirmado de MFA/CSRF ni acceso cruzado de cartera/tickets en esta revisión. Los tests no demuestran ausencia de otros fallos. |
| MFA | Secreto TOTP cifrado, códigos temporales y de recuperación de un solo uso, revocación de sesiones/tokens/dispositivos tras cambios sensibles. Soporte requiere permiso explícito, correo verificado y MFA. |
| Dispositivo de confianza | Token aleatorio de 256 bits, hash en servidor y cookie HttpOnly; duración configurada de 90 días. |
| Archivos | Documentos/fotos/adjuntos privados y cifrados con clave independiente, autorización antes de descargar, rechazo de alteración/clave incorrecta/traslado de ruta. |
| Antivirus | Implementación rechaza subidas en producción con scanner desactivado o indisponible; falta probar motor, firmas y EICAR en el servidor definitivo. |
| Borrado | Cuenta y archivos utilizan borrado/reintentos persistentes. Chat/propuestas y tickets tienen limpieza programada. Cumplimiento temporal depende del scheduler. |
| Soporte | Contenido cifrado y acceso por ticket; aviso al equipo sin texto ni adjuntos. El soporte no concede acceso general a carteras. |
| IA | Activación voluntaria y versionada, aislamiento por cartera, presupuesto reservado y confirmación separada por endpoint. Document AI sigue simulada. |
| Datos en reposo | Cifrado de archivos, chat, propuestas y soporte; no todas las columnas de contactos/inmuebles/metadatos están cifradas. No anunciar cifrado de toda la base de datos ni cifrado de extremo a extremo. |

No hay una integración Google OAuth en las rutas, interfaz o dependencias actuales. El histórico de conversación no prueba que esa integración esté en esta versión. La vía pública de alta revisada es correo y contraseña.

## Correcciones de código identificadas

### 1. Dependencias con avisos actuales — prioridad antes de publicar

| Paquete instalado | Resultado actual | Matiz sobre exposición |
| --- | --- | --- |
| Axios 1.18.1 | npm agrupa 12 avisos, con severidad máxima alta. | Algunos afectan adaptadores Node/HTTP2 y otros requieren contaminación previa de prototipos. El frontend usa Axios en navegador; no se ha demostrado explotación de Alquivo por esos avisos. |
| Laravel Framework 13.19.0 | Un aviso bajo: XSS en página de depuración, `GHSA-jh5r-qr3c-85q8`. | Producción exige depuración desactivada. Actualizar igualmente a versión parcheada compatible. |
| League CommonMark 2.10.0 | Avisos medio y alto: filtrado HTML y consumo cuadrático al interpretar tablas Markdown. | No se encontró una ruta pública que renderice Markdown arbitrario en los controladores revisados. No deducir explotación remota solo por la presencia del paquete. |
| League Flysystem 3.35.2 | Un aviso bajo sobre normalización de rutas con UTF-8 mal formado. | Los archivos del dominio usan rutas controladas/generadas; actualizar y conservar las pruebas de almacenamiento. |

Actualizar a revisiones corregidas, ejecutar las auditorías de nuevo y verificar comportamiento/compilación. Las comprobaciones antiguas de «sin avisos» en otros documentos son históricas.

Referencias del mantenedor: [Axios](https://github.com/axios/axios/security/advisories/GHSA-x97p-jq2g-jp4f), [Laravel](https://github.com/laravel/framework/security/advisories/GHSA-jh5r-qr3c-85q8), [CommonMark](https://github.com/thephpleague/commonmark/security/advisories/GHSA-3q6v-r5mr-hxv8). El informe de Composer es la evidencia de los avisos instalados de PHP; el de npm es la evidencia de Axios.

### 2. Texto libre sin longitud máxima — severidad media

Notas/descripciones en `ContactController`, `PropertyController`, `LeaseController`, `TransactionController`, `IssueController` y `CalendarController` admiten `string` sin `max`. Un validador aislado con las reglas actuales acepta 100 KiB en notas de contacto y descripción de incidencia. Los contactos no tienen un cupo comparable al de inmuebles y sus textos no computan en la cuota de archivos. El transporte permite cuerpos de hasta 12 MB y el limitador ordinario 120 solicitudes por minuto.

Un usuario registrado puede acumular textos excesivos. No se ha explotado ni medido agotamiento real del servidor. Corregir con límites explícitos coherentes en creación y edición y regresiones de rechazo de contenido sobredimensionado. No imponer verificación de correo como sustituto del límite.

Referencias: `backend/app/Http/Controllers/Api/V1/ContactController.php:27,42`, `backend/app/Domain/Portfolio/Services/StorageUsageService.php:21`, `frontend/nginx.conf:22`, `ops/Caddyfile:9`.

### 3. Defensa incompleta en CSV — severidad media

`ReportController::sanitizeCsvCell` solo comprueba el primer carácter. Una comprobación por reflexión aislada confirma que una fórmula precedida de tabulación o retorno de carro sale intacta. `TaxReportRenderer` ya reconoce espacios/controles iniciales.

Reutilizar una protección coherente en ambos exportadores y comprobar prefijos de control. No se ha probado ejecución en Excel ni se afirma una ejecución remota demostrada. Referencias: `backend/app/Http/Controllers/Api/V1/ReportController.php:78`, `backend/app/Domain/Fiscality/Services/TaxReportRenderer.php:37`.

### 4. Contexto sin cartera — severidad baja

`AccountController`, `PropertyController` y otros endpoints ordinarios desreferencian una cartera que puede ser nula. Una cuenta interna preparada sin cartera puede obtener 500; no se observó lectura de datos ajenos. Propuestas de IA ya responden 404 para ese contexto. Normalizar rutas que necesitan cartera a un rechazo controlado, preservando autenticación y soporte para cuentas sin cartera.

### 5. Evidencia mínima de consentimiento IA — prioridad de privacidad

`AssistantController::enable` sobrescribe la última fecha/version y `disable` las borra. `SecurityAudit` registra el evento y usuario, pero no la versión del aviso; los logs caducan. La retirada del permiso debe seguir borrando el chat y sus borradores, pero hace falta decidir y conservar evidencia mínima de la aceptación/retirada que permita demostrar la base del tratamiento realizado, con plazo definido y sin conservar conversaciones por ese motivo.

Referencias: `backend/app/Http/Controllers/Api/V1/AssistantController.php:198–221`, `backend/app/Support/SecurityAudit.php:9–14`. El [RGPD, artículo 7.1](https://www.boe.es/doue/2016/119/L00001-00088.pdf) exige poder demostrar el consentimiento cuando esa es la base jurídica. La evidencia y su retención deben ajustarse con el asesor de privacidad.

## Pendientes documentales y operativos que bloquean la publicación

1. **Proveedores y conservación:** `frontend/src/content/legalOperator.js` conserva vacíos alojamiento/copia externa/entidad OpenAI, ubicaciones o garantías de Cloudflare y Gmail y plazos de copias/buzón/logs. El acuerdo de encargo no autoriza proveedores sin identificar. Completar con servicios y contratos efectivos, no por suposiciones.
2. **Buzón de soporte:** Gmail gratuito figura como receptor y no consta un acuerdo empresarial adecuado para tratar por encargo los datos que puedan recibir sus mensajes/adjuntos. Revisar el uso efectivo y su contrato, o elegir un buzón profesional con acuerdo apropiado. No afirmar que el nombre Gmail demuestra cumplimiento ni incumplimiento por sí solo.
3. **Revisiones publicadas:** guardar una copia inmutable de la revisión legal que realmente se publique. Para cuentas reales previas que se conserven, resolver su incorporación bajo condiciones vigentes; no sobrescribir aceptaciones. Si producción empieza con cuentas nuevas y datos sintéticos excluidos, no es necesario inventar un flujo de reaceptación para esos datos de prueba.
4. **Despliegue real:** dominio HTTPS, sesiones/cookies, proxy/origen, DB privada, claves independientes y copias de claves deben verificarse en el alojamiento definitivo. `security:check` es un diagnóstico de ajustes, no comprueba firewall, contratos, actualizaciones ni entrega de correos.
5. **Copias externas y recuperación:** faltan configurar destino externo, frecuencia, retención y alerta; ensayo de restauración desde ese destino y reaplicación de supresiones. Una copia local no cubre la pérdida del servidor.
6. **Antivirus, scheduler y correo:** comprobar firmas y EICAR, fallo cerrado del scanner, tareas de limpieza/borrado, verificación y recuperación completas y notificaciones de soporte. La entrega previa de un correo de recuperación no acredita todos los recorridos.
7. **IA:** revisión humana independiente del informe sintético y recorrido navegador/MFA/PostgreSQL. Último subconjunto con código final: 47 ejecuciones, 45 PASS, 2 SAFE FAILURE, 0 DANGEROUS FAILURE revisados por Codex; no equivale a ausencia de riesgo. Ver `ai-evaluations.md`. No abrir Document AI real con el permiso del chat.
8. **Procedimientos de privacidad:** ejecutar lo que ya prometen los textos: solicitudes de derechos, incidencias, supresión tras restauración y gestión de subencargados, con responsable y registros restringidos. Revisar el conjunto con un profesional usando el despliegue y contratos reales antes de darlo por definitivo.

El [artículo 28 RGPD y la guía de la AEPD](https://www.aepd.es/preguntas-frecuentes/2-tus-obligaciones-como-responsable-del-tratamiento/8-responsable-y-encargado-del-tratamiento/FAQ-0238-cual-seria-el-contenido-del-contrato-de-encargo-de-tratamiento) regulan la relación responsable/encargado y los subencargados. La casilla del formulario no sustituye esos acuerdos. Según [OpenAI Docs](https://developers.openai.com/api/docs/guides/your-data), `store=false` no garantiza retención cero de registros de seguridad del proveedor; comprobar las condiciones concretas de la cuenta.

## Coherencia de la entrega local

La página servida por Docker en `http://localhost:8080/legal` sigue mostrando `2026-09-28`; la compilación actual muestra `2026-10-01`. Reconstruir y desplegar frontend/backend juntos al preparar la entrega. Esta revisión no actualizó contenedores ni abrió la beta.

Los documentos antiguos de seguridad contienen referencias ya superadas, incluida la ausencia de MFA para propietarios. Utilizar este checkpoint junto con `legal-beta.md`, `release-candidate.md` y `ai-evaluations.md`, no tomar una frase histórica como estado actual.

## Orden de cierre

1. Corregir dependencias, límites de texto, CSV, contexto sin cartera y evidencia mínima de consentimiento; verificar las regresiones relacionadas.
2. Preparar alojamiento/servicios reales y completar proveedores y conservación conforme a esos contratos.
3. Verificar desde navegador la entrega actual sobre HTTPS, PostgreSQL, MFA, archivos, correo, antivirus, borrado y restauración externa.
4. Revisión humana de IA y revisión final de privacidad/contratación; decisión explícita de apertura y despliegue del artefacto revisado.

La preparación de infraestructura puede empezar mientras se cierran las correcciones. El criterio de publicación es haber resuelto o acotado estos hallazgos y disponer de evidencia del entorno definitivo, no una promesa de riesgo cero.
