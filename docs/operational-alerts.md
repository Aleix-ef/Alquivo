# Avisos operativos de Alquivo

Actualización 28/10/2026: el watchdog del host comprueba HTTPS y caducidad del
certificado de `alquivo.com`, el nuevo dominio principal. Se conservan la misma
política de alertas, destinatarios y heartbeat. Validado sin incidencias tras el
cambio de dominio, con 27 pruebas offline; no se simula otra caída ni se envía
otro correo de prueba. Esto sigue sin ser una comprobación entrante externa
continua de DNS/Internet; las comprobaciones externas del despliegue son puntuales.

Preparación del 23/10/2026. Esta página describe el contrato del monitor; el estado de instalación y las recepciones reales se registran al final, sin inferirlas de los tests. No abre la beta ni cambia IA, pagos o permisos.

## Dónde llegan

- **Internos:** `soporte@alquivo.com`, a través de la API de Resend directamente desde Python del host. No necesita Laravel, PostgreSQL, la cola o Docker para enviar el correo. Reutiliza únicamente remitente/clave del entorno privado, sin copiarlos a Git, argumentos de procesos ni a un proveedor nuevo.
- **Externos:** cuenta Healthchecks.io y avisos a `alquivoapp@gmail.com`, autorizados por el titular. El VPS emite una señal de salud cada dos minutos; si cesa, el proveedor puede avisar sin depender del VPS, Resend ni el buzón OVH. Periodo recomendado 2 minutos, gracia 5 minutos: una caída total se detecta aproximadamente tras 7 minutos sin señal, más la entrega del correo, no instantáneamente.
- **Copias:** conservan su notificador y vigilancia existentes. El monitor añade controles de frescura/temporizadores, no los sustituye ni repite una copia cada dos minutos.

El envío a Resend aceptado no acredita entrega en Zimbra. La recepción y el filtro Spam se comprueban con una prueba etiquetada. Configurar notificaciones del móvil para ambos buzones. No se han contratado SMS, WhatsApp ni servicios de pago.

## Qué comprueba

| Control | Umbral/alcance |
| --- | --- |
| Docker y siete servicios | Estado running y healthcheck cuando exista; no se leen sus logs ni variables. |
| Aplicación y acceso | HTTPS local con certificado verificado: `/up` y `/login` 200; API privada sin sesión 401. No es una prueba externa de DNS/ruta de Internet. |
| Certificado | Validez TLS y aviso si resta menos de 14 días. |
| Disco | Aviso bajo 20 % o 10 GiB libres; crítico bajo 10 % o 5 GiB. Inodos críticos bajo 10 %. No borra archivos automáticamente. |
| Base/cola | SELECT y agregados; trabajos disponibles desde hace más de 5 minutos o trabajos fallidos en la última hora. No lee payloads/excepciones, reintenta, cancela ni vacía colas. Los trabajos futuros no cuentan como atasco. |
| Planificador | Evento real cada minuto guarda timestamp en caché compartida de BD; alerta si falta o supera 3 minutos. Ejecutar la sonda no fabrica el timestamp. |
| Antivirus | Servicio/daemon y versión de firmas; aviso si faltan datos, tienen más de 3 días o fecha inválida. No se envían archivos a terceros. |
| Preparación | Booleano del control de seguridad/configuración existente, sin serializar mensajes/configuración. |
| Copias | Temporizadores activos, última copia verificada con menos de 30 horas y pausa de servicios no abandonada más de 5 minutos. No sustituye verificar integridad ni restauración. |

## Política de notificaciones

Timer cada dos minutos. Un aviso normal necesita dos observaciones consecutivas; disco crítico, Docker, base, seguridad y pausa de copia abandonada notifican en la primera. La recuperación necesita dos observaciones buenas. No se manda un correo por cada sondeo: uno al cambiar incidencias, recordatorio cada seis horas mientras sigan y aviso de recuperación de incidencias previamente notificadas.

Antes de enviar, se persiste una clave de idempotencia y contenido estable para reintentos. [Resend conserva sus claves durante 24 horas](https://resend.com/changelog/idempotency-keys); no se promete deduplicación indefinida si falla el disco/estado o se reinicializa manualmente. Un fallo de notificación también provoca señal externa de fallo, cuando el destino está configurado.

Se agrupan códigos predefinidos; nunca se incluyen nombres de propietarios/inquilinos, correos de clientes, documentos, conversaciones, SQL, trazas, credenciales, URL privada de heartbeat o contenido de trabajos. No hay recuperación automática que pueda modificar datos o esconder una incidencia.

Este control no detecta todos los errores de interfaz o respuestas incorrectas de IA, ni reproduce cada flujo de usuario. El heartbeat externo tampoco comprueba por sí solo DNS/conectividad entrante de Internet. Al abrir HTTPS al público se debe añadir una comprobación externa del dominio, sin retirar ahora la restricción de pruebas ni entregar credenciales de usuarios al proveedor.

## Archivos y operación

- `/usr/local/lib/alquivo/monitor.py`: copia root-owned del script versionado en `ops/server`, independiente del symlink de release.
- `/etc/alquivo/monitor.json`: destinatario interno; root:root, 600.
- `/opt/alquivo/shared/.monitor-heartbeat-url`: única línea con URL `https://hc-ping.com/<uuid>`; privada, 600, editable por el titular. No añadir query, `/log`, credenciales o contenido. No pegarla en tickets, chat, Git o comandos con argumentos visibles.
- `/var/lib/alquivo-monitor`: estado/lock, root:root 700; JSON 600, solo códigos/contadores/fechas y claves de deduplicación, sin URL ni datos de clientes.
- `alquivo-monitor.timer/service`: systemd; estado protegido y ejecución acotada. No depende de `docker.service`: puede notificar si Docker está caído. Timeouts también dentro de los contenedores para no dejar sondas PHP colgadas.
- `php artisan ops:health`: CLI privada en el contenedor, sin endpoint HTTP. Emite únicamente métricas agregadas. Requiere cola/caché compartida de base de datos, los controladores del VPS actual; un cambio de controlador debe actualizar y probar el monitor.

Comprobación **sin envíos, cambios de estado o señales externas**:

```bash
sudo /usr/bin/python3 /usr/local/lib/alquivo/monitor.py --check
sudo systemctl status alquivo-monitor.timer --no-pager
sudo journalctl -u alquivo-monitor.service --since '30 minutes ago' --no-pager
```

Una prueba real, claramente etiquetada, sin simular averías:

```bash
sudo /usr/bin/python3 /usr/local/lib/alquivo/monitor.py --test-email
```

Revisar solo el journal de este monitor: no copiar logs generales de aplicación con información de clientes. Para una incidencia, comprobar primero código/servicio, salud/disco y última copia. No ejecutar `down -v`, limpiar volúmenes o reintentar trabajos indiscriminadamente.

Para mantenimiento se puede detener temporalmente `alquivo-monitor.timer` y pausar conscientemente el check externo; dejar anotada la reanudación. No dejarlo pausado tras una entrega. Si se revierte la aplicación a una release sin `ops:health`/heartbeat, revertir conscientemente esta integración para evitar falsa cobertura.

## Alta externa del titular

1. [Crear cuenta gratuita](https://healthchecks.io/pricing/) con `alquivoapp@gmail.com`; comprobar acceso al correo.
2. Crear check `Alquivo-servidor`, periodo 2 minutos, gracia 5 minutos; confirmar que el canal Email apunta al Gmail y está asociado al check.
3. Guardar su Ping URL, sin mostrarla, en el archivo privado anterior con `nano` desde el VPS.
4. Confirmar recepción de prueba del propio proveedor y llegada de señales. Después hacer una simulación aislada de ausencia de señal para validar el aviso externo, sin detener la aplicación. No darlo por completado por obtener simplemente HTTP 200: la API puede devolver 200 con `not found`; el monitor exige cuerpo `OK`.

Se envían únicamente pings GET sin cuerpo, con TLS y sin redirecciones; el proveedor observa IP de origen, check y tiempos/estado. No se envían métricas ni logs. Registrar proveedor/condiciones/acceso de esta cuenta operativa en el inventario privado. No se acredita aceptación contractual por leer su web. [API oficial](https://healthchecks.io/docs/http_api/).

## Validación y entrega

Pruebas sintéticas offline del estado/deduplicación/recuperación, payload mínimo, destinos externos, aislamiento de modos de prueba, disco y contrato de métricas en `tools/tests/test_operational_monitor.py`. Regresiones de la CLI y ejecución/caducidad real del evento de scheduler en `OperationalHealthTest` con SQLite aislado. Estas pruebas no simulan una caída de producción ni acreditan recepción de email.

Estado inicial de preparación: sin instalación ni apertura, pendiente de completar entrega controlada y comprobaciones reales. El titular ha autorizado Healthchecks.io y Gmail como canal externo; no se presupone que ya haya creado la cuenta o guardado el destino.
