# Seguridad y preparación del lanzamiento

**Documento histórico (septiembre).** No tomar sus cifras ni decisiones iniciales como estado actual: los propietarios ya disponen de MFA; se permite usar la cartera propia sin verificar el correo; las consultas IA autorizadas pueden incluir nombres de contactos; producción tiene Compose independiente con PostgreSQL privado. Para el checkpoint vigente, correcciones y pendientes consultar [revisión previa a beta](beta-readiness-review.md), [release candidate](release-candidate.md) y [operación de privacidad](privacy-operations.md).

Revisión y correcciones: 6–7 de septiembre de 2026. Este documento recoge controles implementados y requisitos pendientes; no es un certificado de seguridad ni una declaración de cumplimiento legal.

Verificación del código al 7 de septiembre: 63 pruebas del backend y 266 aserciones correctas; pruebas del frontend y compilación correctas; Composer y npm sin avisos de vulnerabilidades conocidas. Se guardó una copia local de PostgreSQL previa a las migraciones en `backups/alquivo-before-security-20260907.dump` (ignorada por Git y con permisos 600). No sustituye a las copias cifradas externas de producción.

Las migraciones se aplicaron en PostgreSQL local y los servicios quedaron en marcha en `http://localhost:8080`, restringido a `127.0.0.1`. La prueba HTTP sobre Docker confirmó login/logout de demo, respuestas privadas no cacheables, rechazo de cookies con origen no autorizado, rechazo de webhook sin firma y activación obligatoria de IA sin llamar al proveedor. Nginx 1.30.4 validó su configuración. Una comprobación de la imagen del backend confirmó que no incluye `.env`, caché de configuración, SQLite ni archivos de `storage/app` del equipo. No hubo navegador conectado para revisión visual. ClamAV está integrado y su indisponibilidad se prueba, pero falta probar el motor real con firmas y archivos de prueba en el alojamiento elegido; el entorno Docker local dispone de aproximadamente 2 GiB, insuficientes para añadir con holgura el scanner a los servicios existentes.

## Controles implementados

| Riesgo | Protección |
| --- | --- |
| Cambiar el correo desde una sesión robada | Contraseña actual obligatoria, normalización del correo, nueva verificación y revocación de otras sesiones de base de datos. |
| Contraseñas cambiadas/restablecidas | Revocación explícita de sesiones y tokens; Sanctum también invalida cookies cuyo hash de contraseña ha quedado obsoleto. |
| Intentos masivos de acceso | Límites por cuenta y por IP; límites separados de registro, recuperación y operaciones sensibles. La huella del correo se usa en las claves del limitador. |
| Acceso antes de verificar el correo | API de datos, documentos, IA y facturación protegida con `verified`; siguen disponibles cuenta, verificación y eliminación. |
| Eventos de Stripe falsificados | Webhook deshabilitado si falta el secreto; Cashier comprueba la firma cuando está configurado. |
| Robo de datos por cachés o contenido activo | Respuestas privadas `no-store`; CSP en el frontend, `nosniff`, bloqueo de iframes y política de referencia restrictiva. Se mantiene escape de contenido en Vue. |
| Credenciales o documentos dentro de las imágenes | Contextos Docker excluyen `.env`, claves, cachés de configuración, bases SQLite y archivos privados. |
| Saltarse los límites de almacenamiento/inmuebles | Comprobación y escritura bajo bloqueo de la cartera en una transacción. |
| Archivos maliciosos | Tipos/tamaños y dimensiones permitidos; scanner ClamAV antes de almacenar. En producción, si está desactivado o no responde, se rechaza la subida. |
| Archivos que no se eliminan tras borrar una cuenta/documento | Registro persistente de borrados y reintento programado. No depende de que la cuenta siga existiendo. |
| Abuso del cupo de IA | Contador mensual separado de conversaciones, reserva atómica antes de llamar al proveedor, bloqueo por usuario; borrar historial no devuelve el cupo. |
| Consumo excesivo del asistente | 2.000 caracteres por pregunta, historial acotado, máximo de mensajes/conversaciones, herramientas y resultados limitados y plazo total de 45 segundos. Las consultas iniciadas cuentan aunque fallen. |
| Filtraciones a través de la IA | Herramientas cerradas y restringidas por cartera; validación de argumentos; exclusión de nombres de inquilinos, dirección completa y contenido de archivos; errores internos genéricos; instrucciones contra contenido malicioso incrustado. |
| Conservación de chats | Activación opcional con versión del aviso, borrado/desactivación, caducidad de mensajes a 30 días; métricas de uso sin texto separadas. |
| Falta de trazabilidad | Registro de accesos, fallos, cambios sensibles, descargas y activación/desactivación de IA, sin cuerpos de solicitudes ni credenciales. |
| Regresiones y dependencias vulnerables | Pruebas de seguridad, workflow de pruebas/auditorías y configuración de Dependabot. Se ejecutarán en GitHub cuando estos cambios se publiquen. |

Las pruebas con SQLite verifican comportamiento y aislamiento. Los bloqueos `FOR UPDATE` necesitan PostgreSQL/MySQL real en producción; no se ha realizado una prueba de carga distribuida. Un prompt no es una barrera de autorización: el aislamiento se exige en cada herramienta del servidor.

## Comprobaciones de desarrollo

Desde `backend`:

```bash
php artisan test
composer audit --locked
php artisan security:check
```

Desde `frontend`:

```bash
npm audit
npm test
npm run build
```

`security:check` debe fallar en el entorno local HTTP. En producción comprueba configuración de URLs, cookies, cifrado de sesiones, base de datos, correo, caché/cola, CORS, antivirus y proveedores. Las peticiones de producción devuelven 503 si faltan esas protecciones. No verifica automáticamente DNS, entrega real de correo, TLS del proxy, firmas antivirus actualizadas, contratos, copias ni capacidad de recuperación.

## Despliegue público pendiente

`docker-compose.yml` es solo local y publica el puerto en `127.0.0.1`. No lo expongas con túneles a clientes reales. El usuario demo solo se crea en entorno `local`; producción necesita una base de datos nueva, sin credenciales ni documentos de prueba.

Se incluye `docker-compose.production.yml`, independiente del local, y `.env.production.example`. Requiere PostgreSQL externo/gestionado, proxy HTTPS y completar `.env.production` (ignorado por Git). El despliegue público no se ha ejecutado.

Antes de arrancar:

1. Elegir alojamiento y región, dominio y titular del servicio. Contratar almacenamiento persistente cifrado y base de datos privada con un usuario exclusivo, sin privilegios de superusuario. Para PostgreSQL remoto, TLS con certificado validado.
2. Crear una clave de aplicación propia y guardar una copia en un gestor de secretos separado de los backups. No regenerarla sobre datos existentes. Añadir claves restringidas de Stripe/OpenAI y SMTP; nunca usar variables `VITE_` para secretos.
3. Configurar el proxy con HTTPS, renovación de certificados, redirección HTTP, HSTS, límites de peticiones y un host permitido. Debe conservar `Host`, reemplazar `X-Forwarded-For`/`X-Forwarded-Proto` y ser el único acceso al puerto local de `web`. Configurar `TRUSTED_PROXIES` solo con sus IP/CIDR reales, nunca `*`.
4. Configurar un proveedor de correo con TLS y verificar registro, recuperación y cambio de correo con buzones reales. El mailer `log` es exclusivamente de desarrollo.
5. Activar ClamAV y comprobar un archivo limpio, uno de prueba EICAR y el comportamiento si el scanner está caído. El servicio no publica el puerto 3310. Vigilar actualización de firmas y memoria; la documentación recomienda reservar alrededor de 4 GiB para el scanner. [ClamAV en Docker](https://docs.clamav.net/manual/Installing/Docker.html).
6. Configurar Stripe en el entorno correspondiente, probar firma, compra, renovación, impago y cancelación. Revisar importes, impuestos y condiciones comerciales antes de cobrar. No se han realizado cobros reales en esta revisión.
7. Activar MFA en GitHub, alojamiento, correo, Stripe y OpenAI; revisar colaboradores, claves SSH y permisos de despliegue. La aplicación aún no incluye MFA para los propietarios.

Comandos previstos, una vez completado el entorno:

```bash
docker compose -p alquivo-production -f docker-compose.production.yml build --pull
docker compose -p alquivo-production -f docker-compose.production.yml run --rm --no-deps backend php artisan security:check
docker compose -p alquivo-production -f docker-compose.production.yml up -d
```

No combines ambos archivos Compose. Los volúmenes de producción contienen documentos y logs y deben conservarse al recrear contenedores. No usar `down -v`. Si el proxy no está en el mismo host, adapta la red privada; no abras el puerto de la aplicación a Internet para resolverlo.

## Copias, borrados y respuesta a incidentes

- Configurar copias cifradas de base de datos **y** documentos, fuera del servidor principal, con credenciales separadas. Propuesta inicial a aprobar: copia diaria, retención de 30 días, pérdida máxima de 24 horas y recuperación en 4 horas. No prometer estos objetivos hasta haber medido una restauración completa.
- Restaurar periódicamente en un entorno aislado. Validar login, documentos y datos financieros sin enviar correos ni eventos reales. Conservar evidencia de fecha, duración y resultado. Incluir recuperación segura de `APP_KEY`.
- Ejecutar el scheduler permanentemente: `assistant:prune` cada día y `storage:prune-private` cada diez minutos. Alertar sobre scheduler parado, borrados pendientes, backups fallidos y falta de espacio.
- Logs de aplicación: 14 días en la plantilla. Logs de seguridad: 30 días. La infraestructura debe aplicar plazos compatibles también a logs de Nginx, proxy, copias y plataformas de monitorización. No recoger cuerpos, cookies, cabeceras de autorización ni consultas del chat en herramientas de errores.
- Al eliminar una cuenta se suprimen los datos activos y se reintentan los archivos pendientes. Las copias deben expirar con su política y no reactivar cuentas eliminadas al restaurarse; documentar y aplicar las supresiones posteriores a la copia restaurada.
- Ante un incidente: contener acceso, revocar credenciales afectadas, preservar evidencias con acceso restringido, determinar datos/personas afectadas y documentar la decisión de notificación. El responsable debe evaluar la notificación a la autoridad en 72 horas; un encargado informa al responsable sin dilación indebida. La obligación depende del riesgo. [AEPD: notificación de brechas](https://www.aepd.es/derechos-y-deberes/cumple-tus-deberes/medidas-de-cumplimiento/brechas-de-datos-personales-notificacion).

## Privacidad y contratos: información todavía necesaria

Los textos públicos siguen marcados como provisionales. No se han inventado una empresa, dirección, NIF, proveedores ni bases jurídicas. Antes de invitar usuarios con datos reales, completar y revisar:

| Documento/decisión | Información pendiente |
| --- | --- |
| Aviso legal | Titular, identificación fiscal/registral que corresponda, domicilio y contacto. [LSSI, artículo 10](https://www.boe.es/buscar/act.php?id=BOE-A-2002-13758). |
| Privacidad de cuentas y facturación | Responsable, finalidades, bases jurídicas, destinatarios, transferencias, plazos, contacto y ejercicio de derechos. |
| Datos de inquilinos | Determinar cuándo Alquivo actúa como encargado del propietario y formalizar el contrato de tratamiento: instrucciones, seguridad, subencargados, asistencia, devolución/supresión y auditoría. [Directrices AEPD](https://www.aepd.es/documento/guia-directrices-contratos.pdf). |
| Proveedores | Identificar hosting, correo, Stripe y OpenAI, sus funciones, regiones, contratos y garantías para transferencias. No asumir que una casilla del chatbot sustituye esos contratos. |
| Retención y derechos | Definir plazos de cuentas, contratos, datos fiscales, logs y backups. Procedimiento de acceso, rectificación, oposición, supresión y portabilidad cuando proceda. Las exportaciones CSV existentes no equivalen a una respuesta completa a cualquier solicitud de derechos. |
| Condiciones de venta | Precio final/impuestos, prueba, renovación, límites, impagos, baja, reembolso/desistimiento aplicable, soporte y condiciones de beta. |
| Evaluación de riesgos | Documentar el tratamiento con IA y valorar si requiere evaluación de impacto; no se presume obligatoria solo por usar un chatbot. |

OpenAI recibe las preguntas y los resultados necesarios. `store:false` evita el almacenamiento de respuestas de ese endpoint, pero no elimina por sí mismo los registros de abuso del proveedor. Pueden retenerse hasta 30 días, salvo excepciones. Verificar las condiciones de la cuenta y la región antes de activar la IA en producción. [OpenAI Docs: controles de datos](https://developers.openai.com/api/docs/guides/your-data).

El asistente arranca desactivado en la plantilla de producción. Para activarlo, completar lo anterior y establecer `ASSISTANT_ENABLED=true`; cada usuario verá el aviso de activación. Configurar alertas y controles de gasto del proyecto del proveedor: los cupos por usuario no impiden por sí solos el abuso mediante múltiples cuentas. No se ha verificado una respuesta real del proveedor con saldo en esta revisión; las pruebas usan respuestas simuladas.

## Criterio de salida

Abrir a usuarios reales después de validar configuración pública, correo, facturación, scanner, restauración de copias y documentación final. Completar una revisión externa de permisos y seguridad sobre el despliegue y analizar sus imágenes de contenedor. Las auditorías Composer/npm solo cubren vulnerabilidades conocidas de esas dependencias, no prueban ausencia de vulnerabilidades en la aplicación o el servidor.
