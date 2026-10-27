# Conservación operativa de registros — 27/10/2026

## Aplicación efectiva — 27/10/2026, 06:25 UTC

**Instalado y verificado** con la release `050731bc131b7e4a4f2d8f16b7fb86217b79b37a`: siete contenedores con `journald`, política nativa de 30 días/256 MB, rotación diaria y mantenimiento horario habilitado/activo y ejecutado con éxito. Mensaje de prueba exclusivamente sintético. Sin contenedores detenidos del proyecto con logging anterior; Docker mantiene `local` como valor por defecto. Monitor sin incidencias, copia y alertas activas, entorno y firewall sin cambios. La revisión legal publicada `2026-10-27-r1` refleja los límites por bloques y las políticas independientes.

[Registro de entrega y verificaciones](deployment-legal-retention-20261027.md). Los apartados siguientes documentan la comprobación anterior y el procedimiento preparado, no un estado pendiente actual.

## Estado comprobado y alcance

Consulta SSH de solo lectura del 27/10: release activa `ae5309f4cf17142ec0ad675e756f855dbe2428d4`; siete servicios activos, backend/PostgreSQL/ClamAV sanos y HTTPS `/up` 200. Backend/worker/scheduler/web usan `json-file` con tres archivos de 10 MB; PostgreSQL/ClamAV/proxy usan `local` con ese mismo límite. Journal del host: 60,2 MB. Monitor y copia siguen activos. No se han leído mensajes, logs de usuarios ni `.env`.

**Preparado localmente, todavía no instalado:** los siete servicios del Compose de producción usan `journald`, sin copiar variables de entorno ni etiquetas al registro. La configuración del host fija una ventana de 30 días, rotación diaria, límite de disco de 256 MB y reserva de 1 GB. Un timer horario solicita rotación y caducidad mediante `journalctl`, incluida la rotación del archivo activo. Son límites por bloques y ejecuciones, no un borrado individual exacto al segundo. Puede haber menor conservación si se alcanza antes el límite de espacio; una avería de la tarea requiere intervención.

Validación local: **3 pruebas específicas correctas** sobre el Compose resuelto de los siete servicios (sin leer `.env`), límites nativos y mantenimiento que no accede a documentos/base/archivos internos de Docker. `systemd-analyze verify --generators=no` correcto, usando exclusivamente estas unidades y las dependencias suministradas por el sistema. No inicia servicios ni demuestra instalación remota. Regresiones relacionadas: **34 backend/230 aserciones y 218 frontend correctas**. Sin inferencias ni correo real.

Los archivos propios de Laravel y la auditoría de la base conservan sus políticas independientes de 14 y 30 días. La nueva configuración del journal **abarca el journal del VPS**, también los eventos del sistema que almacene, no solo contenedores. No se modifica la política de copias del VPS o de proveedores ni se borran archivos/documentos/carteras. El controlador por defecto de Docker se mantiene `local` para otros contenedores: todos los servicios de Alquivo declaran su controlador explícitamente.

## Aplicación durante la próxima entrega controlada

No es necesario apagar la aplicación para el trabajo editorial. La migración de logging requiere **recrear** los contenedores; no se considera aplicada por instalar los ficheros ni por reiniciar un contenedor existente. Coordinarla con la entrega de beta, tras verificar copia reciente y sin trabajos/backup en curso. Preservar imágenes, entorno, volúmenes y firewall. No usar el árbol de trabajo completo sin seleccionar sus cambios.

1. Comprobar configuración vigente del journal y que no existe otra política incompatible; conservar las configuraciones anteriores para rollback. No copiar el contenido de logs al informe.
2. Instalar `ops/server/60-alquivo-retention.conf` como `/etc/systemd/journald.conf.d/60-alquivo-retention.conf` (root:root, 644), y las unidades `alquivo-log-retention.service` y `.timer` en `/etc/systemd/system/`. Validar las unidades antes de activarlas.
3. Recargar systemd, reiniciar únicamente `systemd-journald`, ejecutar y habilitar el timer de conservación. Esto puede eliminar bloques del journal anteriores a la ventana: no hacerlo durante una investigación que necesite preservarlos; documentar la excepción de conservación si existe.
4. En la release seleccionada, validar el Compose sin imprimir la configuración privada y recrear los siete servicios con las imágenes actuales/probadas. No reiniciar Docker, construir módulos extra, modificar secretos ni ejecutar migraciones por esta tarea. Los datos están en volúmenes y no se eliminan.
5. Comprobar los controladores efectivos de los siete contenedores con `docker inspect` limitado a `HostConfig.LogConfig`, los servicios/healthchecks, HTTPS, acceso privado 401, scheduler y monitor. Revisar contenedores **detenidos del proyecto** que aún conserven logs antiguos; no ejecutar un prune global ni retirar volúmenes o imágenes de rollback.
6. Validar el journal con un mensaje técnico sintético sin datos personales, estado/código de salida del servicio y próxima ejecución del timer. No hace falta generar logs reales de usuarios ni enviar correos/IA.
7. Registrar la aplicación efectiva y actualizar/versionar la información pública de conservación si cambia respecto al texto de la release. Hasta esa comprobación, el estado sigue siendo **rotación por tamaño**, no se da por cerrada la caducidad temporal de contenedores.

Comandos de control posteriores, sin leer mensajes:

```bash
sudo systemctl is-enabled alquivo-log-retention.timer
sudo systemctl is-active alquivo-log-retention.timer
sudo systemctl show alquivo-log-retention.service -p Result -p ExecMainStatus
sudo systemctl list-timers alquivo-log-retention.timer --no-pager
sudo journalctl --disk-usage
```

No ejecutar `truncate`, `copytruncate` o borrados sobre `/var/lib/docker/containers`: son ficheros gestionados exclusivamente por Docker. No desactivar los logs para simular una política de conservación. El rollback debe restaurar la configuración correspondiente sin perder de vista qué controlador y plazo siguen vigentes.

Fuentes: [Docker: journald](https://docs.docker.com/engine/logging/drivers/journald/), [Docker: precauciones con JSON](https://docs.docker.com/engine/logging/drivers/json-file/), [systemd: conservación y rotación](https://www.freedesktop.org/software/systemd/man/latest/journald.conf.html), [journalctl: rotación y caducidad de archivos](https://www.freedesktop.org/software/systemd/man/255/journalctl.html).
