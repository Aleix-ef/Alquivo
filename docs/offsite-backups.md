# Copias externas y recuperación de Alquivo

Estado comprobado el **21/10/2026**. VPS de OVH en Gravelines (Francia) y destino
independiente Hetzner Storage Box de 1 TB en Falkenstein (Alemania). **No es una
autorización para abrir la beta ni una garantía de disponibilidad.**

## Configuración instalada

- Montaje `/mnt/alquivo-backups`: SMB 3.1.1 con `seal`, sin ejecución de archivos,
  dispositivos ni setuid; propietario root y archivos/directorios 600/700.
- Credenciales `/etc/alquivo/storagebox.credentials`, root:root, 600. No están en
  Git, argumentos de procesos ni salidas de las pruebas.
- Repositorio Restic cifrado `/mnt/alquivo-backups/alquivo-restic`.
- Secreto Restic `/opt/alquivo/shared/secrets/backup.password`, root:root, 600.
- Estado y temporales privados `/opt/alquivo/shared/backups`, fuera de las
  releases; bloqueo común entre copia y restauración.
- Copia diaria a las **03:15–03:20, Europe/Madrid**. Temporizador persistente.
- Retención: snapshots dentro de los 30 días respecto al último, conservando al
  menos la última copia disponible. Si dejan de ejecutarse copias, no se promete
  borrar automáticamente esa última copia al cumplir 30 días: debe investigarse
  la alerta y aplicar la política de conservación correspondiente.
- Control horario: falla si falta el montaje, queda una pausa pendiente o la
  última copia verificada supera 30 horas.
- Los fallos generan una notificación técnica a `soporte@alquivo.com`, sin datos
  de usuarios. Envío directo por Resend desde un contenedor efímero, no depende
  del backend, la cola ni PostgreSQL. Usa el propietario del archivo de entorno,
  sin capacidades de root y con sistema de archivos de solo lectura.
- Un diario registra exclusivamente los servicios que estaban activos. Se
  reanudan al terminar la captura consistente, también ante error; systemd tiene
  un paso de recuperación al terminar el servicio y otro al arrancar el host si
  un apagón hubiera interrumpido esa pausa.

Se respaldan `pg_dump` de la base completa, almacenamiento privado, hashes
SHA-256 y una prueba sintética que exige tanto `APP_KEY` como el llavero de
documentos. **APP_KEY y el llavero no van en este repositorio Restic**: requieren
custodia independiente junto con la contraseña Restic.

La app se detiene durante la captura, no durante la subida y la lectura de
integridad. En el primer ensayo hubo unos **17 segundos** desde el inicio de
la pausa hasta recuperar todos los servicios. Crecerá con los datos; no es una
copia sin interrupciones. Con periodicidad diaria puede perderse aproximadamente
un día de cambios, y más si las copias fallan y no se atienden los avisos.

## Evidencia de esta comprobación

- Release operativa `7c471ff9d8349e9938cf88b04c32671a45e2fdd5`; imágenes de la
  aplicación conservadas, construidas previamente desde `b831c76`.
- Snapshot **`502b0768`**, 21/10/2026 07:01 CEST. Cuatro archivos de captura,
  158,410 KiB antes de deduplicación/compresión, 24,905 KiB almacenados.
- `restic check --read-data` correcto, seguido de aplicación de retención y otra
  comprobación estructural. No había snapshots antiguos que eliminar.
- Restauración desde ese snapshot en PostgreSQL y volumen nuevos: hashes,
  `pg_restore --exit-on-error`, 37 migraciones y prueba de ambas claves correctos.
- **Cero archivos privados de usuario referenciados en esta copia.** La prueba
  de cifrado usó un canario sintético; no acredita todavía un recorrido de
  subida/descarga con documentos reales. No se usaron documentos reales ni IA.
- Ensayo repetido con límites de CPU y memoria: PostgreSQL máximo 768 MiB,
  datos de ensayo en tmpfs de 512 MiB y verificador máximo 512 MiB. Si la base
  crece por encima de esa capacidad, ampliar el entorno de recuperación de forma
  controlada, no eliminar los límites ni arriesgar la memoria de producción.
- Se eliminaron únicamente contenedores, red, volumen y temporales del ensayo;
  nunca la base o los volúmenes de producción.
- Alerta técnica aceptada por Resend y **recepción confirmada en Zimbra** por el
  titular. Falta repetirla si cambian proveedor, remitente o clave de correo.
- Validaciones locales: **11 regresiones de copias**, sintaxis Bash/PHP y
  **195 tests frontend** correctos. La suite HTTP necesita permisos para abrir
  localhost; en sandbox falló por EPERM y pasó al repetirla con ese permiso.
- Login de la app y landing: HTTPS 200, certificado verificado. Control
  `security:check` de producción correcto. No se cambiaron firewall, MFA,
  flags de IA, pagos ni DNS. No se importaron usuarios demo.

## Comandos de operación (en el VPS)

```bash
sudo systemctl list-timers --all alquivo-backup.timer alquivo-backup-watch.timer
sudo journalctl -u alquivo-backup.service -n 80 --no-pager
sudo systemctl start alquivo-backup.service
sudo systemctl start alquivo-backup-watch.service
```

El último control verifica antigüedad de una copia que ya pasó integridad; no
sustituye una nueva restauración. No borrar locks Restic a ciegas ni ejecutar
`down -v` sobre producción. No generar otra contraseña Restic ni cambiar las
claves documentales como atajo si algo deja de descifrarse.

Para repetir el ensayo, usar la imagen exacta que corresponda a la copia y el
llavero conservado. Las claves anteriores deben mantenerse mientras haya
snapshots que dependan de ellas. Ejemplo para esta versión:

```bash
sudo env \
  BACKUP_WORK_DIR=/opt/alquivo/shared/backups \
  BACKUP_REPOSITORY_DIR=/mnt/alquivo-backups/alquivo-restic \
  BACKUP_PASSWORD_FILE=/opt/alquivo/shared/secrets/backup.password \
  RECOVERY_KEYRING=/opt/alquivo/shared/recovery/documents.json \
  RECOVERY_ENV_FILE=/opt/alquivo/shared/.env.production \
  RECOVERY_BACKEND_IMAGE=sha256:5432036de2ceab32eece5751c85bb28d33ce6fafa48bc13f79e3f925669dcb63 \
  bash /opt/alquivo/current/tools/restore-check.sh 502b0768
```

Este comando usa el llavero conservado en el propio VPS. **No demuestra por sí
solo que se podría recuperar tras perder completamente ese VPS**: falta el
siguiente paso del titular.

## Pendiente obligatorio: custodiar secretos fuera del VPS

Avance del 21/10: el titular ha creado el paquete cifrado de recuperación. Se ha
descargado a `~/Descargas/Alquivo-recuperacion/` con permisos 600, dentro de una
carpeta 700, fuera del repositorio Git. Sus 10.422 bytes y SHA-256 coinciden con
el VPS (`31723b4ac4b0da0146aa99273d947ec6aca28687c38f6121e762069b356a95b3`).
No quedaron temporales de exportación en `/run`. **Todavía no se ha descifrado
esa copia local: el titular conserva en exclusiva su contraseña.**

Preparado `comprobar-copia.sh` junto al paquete. La herramienta age procede del
paquete autenticado de los repositorios de Ubuntu (`1.0.0-1ubuntu0.1`), extraído
en `herramientas/`, sin instalar paquetes del sistema del ordenador. El
verificador usa age para pedir la contraseña en la terminal, comprueba los
cuatro hashes del contenido, rechaza rutas/entradas inesperadas, duplicados y
enlaces, y elimina sus temporales de memoria. No usa red ni muestra claves.
Solo escribe una evidencia `.verified` con la huella del archivo cifrado si
termina correctamente. Seis regresiones sintéticas verifican éxito, contenido
alterado, entradas extra, enlaces, fallo al descifrar y rechazo de temporales
fuera de memoria. **Estas regresiones no descifran ni validan por sí mismas el
paquete real**.

En la terminal del **ordenador**, no en el VPS:

```bash
bash ~/Descargas/Alquivo-recuperacion/comprobar-copia.sh
```

Debe pedir la contraseña del paquete (no la de SSH/Hetzner) y terminar con
`VERIFICACIÓN CORRECTA`. Después custodiar otra copia fuera del ordenador y la
contraseña en el gestor. Las instrucciones siguientes se conservan para futuras
exportaciones; no hace falta generar otro paquete para completar esta prueba.

En una sesión SSH interactiva, el titular ejecuta:

```bash
sudo /usr/local/sbin/alquivo-export-recovery
```

Elegir una contraseña larga distinta, guardada en un gestor de contraseñas; no
compartirla en chat. El comando prepara los secretos en `/run` (memoria), cifra
un paquete con age y elimina los temporales. El paquete contiene el entorno de
producción, llavero de documentos, contraseña Restic y acceso al almacenamiento,
con sus hashes. No contiene contratos ni base de datos. El comando informa de
la ruta del archivo `.tar.age` y permite que `ubuntu` lo descargue.

**Descargar ese archivo al ordenador y conservar otra copia fuera de él**, en un
gestor con adjuntos o almacenamiento seguro. No dejar la única copia en el VPS,
en este repositorio Git ni en el mismo destino al que solo se accede con las
credenciales contenidas en el propio paquete. Verificar posteriormente que el
archivo descargado se descifra y que coinciden sus SHA-256, sin mostrar los
valores de los secretos ni dejar la extracción sin cifrar en disco. Conservar
el acceso a OVH/Hetzner y los medios de recuperación de sus cuentas por separado.

Hasta confirmar descarga, custodia y descifrado de ese paquete, **la recuperación
independiente de claves sigue pendiente**. Regenerarlo y probarlo después de
cambios de claves, credenciales o configuración importante; no borrar versiones
antiguas necesarias para recuperar snapshots retenidos.

## Límites que no hay que confundir con tareas terminadas

- El montaje y las unidades están habilitados para reinicio, pero no se ha hecho
  otro reinicio real del VPS en este bloque.
- El monitor depende del VPS, Docker, red y Resend. Si el servidor se apaga por
  completo no puede enviar correo: hace falta un control externo de ausencia de
  copias/caída, todavía pendiente dentro de la monitorización de lanzamiento.
- Este acceso SMB es de lectura/escritura, no un almacén inmutable. Considerar
  snapshots automáticos de Storage Box gestionados desde Hetzner (sin guardar
  credenciales de la consola en el VPS) y verificar su recuperación. No se han
  activado desde aquí ni se promete resistencia a una toma completa del host.
- Se ha probado recuperación de datos y claves, no conmutación a otro VPS ni
  recorrido funcional completo tras un desastre. No hay SLA o tiempo de
  recuperación garantizado. Repetir pruebas tras cambios relevantes y, como
  mínimo, antes de admitir datos reales.

Referencias: [SMB cifrado de Hetzner](https://docs.hetzner.com/storage/storage-box/access/access-smb-cifs/),
[retención de Restic](https://restic.readthedocs.io/en/stable/060_forget.html),
[age](https://github.com/FiloSottile/age).
