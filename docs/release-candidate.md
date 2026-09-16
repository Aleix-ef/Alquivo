# Preparación local de la beta de validación

Este documento es el punto de entrada operativo. No es un certificado de seguridad ni acredita el cumplimiento legal. La beta aún requiere configurar y comprobar servicios externos antes de recibir datos reales.

## Contratación cerrada

`BILLING_ENABLED=false` es el valor predeterminado en todos los entornos. La landing conserva las cuotas y permite registrarse, pero indica que son informativas. Planes no abre pagos. La API y `CheckoutService` rechazan nuevas contrataciones, incluso con claves y precios Stripe configurados. El portal también queda cerrado para evitar cobros iniciados desde él. El registro mantiene 14 días de prueba de Fundador, después Gratuito; no concede Fundador indefinidamente ni borra excedentes.

**No es un interruptor de Stripe:** no cancela suscripciones existentes, no expira sesiones Checkout antiguas ni desactiva Payment Links externos. Antes de invitar usuarios, revisar esos tres elementos en Stripe. La inspección local encontró una suscripción `trialing` y configuración no-live; no se consultó ni modificó el estado remoto. Se siguen validando y procesando webhooks firmados para mantener la coherencia de suscripciones anteriores. No introducir claves live durante la validación.

No activar `BILLING_ENABLED=true` hasta tener autorizada la comercialización, configuración fiscal/contractual correspondiente y pruebas sandbox de cobro, renovación, impago y cancelación. Desactivar pagos no determina por sí solo las obligaciones legales del titular.

## Documentos y fotos cifrados

- `PrivateFileVault` centraliza el cifrado autenticado AES-256-GCM mediante `Illuminate\Encryption\Encrypter`; no hay criptografía propia. El contenido queda vinculado a su ruta lógica para rechazar un archivo trasladado a otra cartera.
- Cifrado obligatorio para nuevas subidas. Se valida el tipo, se analiza con antivirus cuando está activo y se aplica la cuota sobre el tamaño original. Descargas y fotos mantienen autorización y `private, no-store`.
- Clave distinta de `APP_KEY`. El llavero JSON `storage/app/keys/documents.json` contiene la clave activa primero y las anteriores después. Se excluye de Git y de las copias de datos. Producción lo monta en un volumen separado `document_keys`.
- `vault:key --if-missing` inicializa sin sobrescribir claves existentes. El arranque local lo hace automáticamente; en producción es una operación explícita de preparación.
- `DOCUMENT_ENCRYPTION_KEY` permite usar un gestor de secretos en vez del llavero (en ese modo la rotación debe gestionarse allí). No usar la clave pública de tests en un entorno con datos.
- `php artisan vault:files` descifra y comprueba el tamaño de cada documento/foto referenciado. Falla si encuentra texto plano, clave incorrecta, manipulación o archivos ausentes.
- Para convertir archivos antiguos o rotar: copia verificada, detener workers/scheduler, `php artisan down`, `php artisan vault:key --rotate` (solo rotación), `php artisan vault:files --encrypt --backup-confirmed`, verificar otra vez, respaldar llavero y reabrir. La sustitución es atómica por archivo, no por todo el lote; conserva claves anteriores y permite reintentar si se corta. Nunca eliminar claves antiguas mientras haya copias que dependan de ellas.

Esto protege el contenido almacenado si se obtiene una copia de los archivos sin sus claves. **No es cifrado de extremo a extremo**: el servidor necesita descifrar para un usuario autorizado. No cifra automáticamente todas las columnas de la base de datos, los correos de soporte ni los discos del VPS. Configurar cifrado del almacenamiento del host/proveedor y sus accesos por separado.

## Verificación en dos pasos

Configuración → Verificación en dos pasos, disponible en todos los planes. Alta manual en cualquier aplicación TOTP compatible. La clave no sale hacia un proveedor de QR externo. Se exige contraseña y confirmar un código antes de activarla; el secreto pendiente caduca a los 10 minutos.

Al iniciar sesión, una cuenta protegida no obtiene acceso a sus datos hasta verificar el segundo factor. El desafío caduca a los 5 minutos y se invalida si cambia la contraseña o el secreto. Hay límites por IP y cuenta, rechazo de reutilización del código temporal y 8 códigos de recuperación de un solo uso; solo se guardan sus hashes. El secreto TOTP se cifra con `APP_KEY` y los campos sensibles no se serializan en la API.

Activar/desactivar revoca otras sesiones y tokens. Desactivar exige contraseña y segundo factor. Restablecer la contraseña por correo **no desactiva** el doble factor. Si se pierden móvil y todos los códigos, no hay un desbloqueo automático por soporte; definir un procedimiento de verificación de identidad antes de ofrecer recuperaciones manuales.

## Copias y ensayo de recuperación

Desde la raíz del proyecto:

```bash
bash tools/init-backup-secret.sh
docker compose cp backend:/app/storage/app/keys/documents.json secrets/documents.json
bash tools/backup.sh
bash tools/restore-check.sh
```

Guardar `secrets/backup.password`, el llavero y `APP_KEY` en un gestor de contraseñas **fuera del equipo/VPS**. No enviarlos por chat, correo o Git. `secrets/` y `backups/` están ignorados. La copia local no sustituye una copia externa.

`backup.sh` usa Restic (contenedor sin red), pausa los servicios que estaban activos, genera un `pg_dump` y un archivo de almacenamiento privado en el mismo punto de parada, añade SHA-256 y una prueba sintética cifrada con las dos claves. Crea una instantánea cifrada y ejecuta `restic check --read-data`; después restablece solo los servicios que estaban encendidos, también ante error. La pausa incluye la comprobación de integridad y crecerá con los datos. **Es una primera solución con mantenimiento**, no una copia sin interrupciones.

El staging temporal contiene datos sin el cifrado de Restic y tiene permisos privados. Debe residir sobre un disco cifrado. Se limpia al salir normalmente o con error; un apagado abrupto puede dejar `.stage-*`/`.recovery-*`, que deben revisarse y eliminarse de forma controlada. El borrado de archivos no garantiza borrado físico seguro en SSD.

`restore-check.sh [snapshot]` restaura en una base PostgreSQL y volumen nuevos, sin puertos públicos ni acceso de red externo. Comprueba hashes, importa con `pg_restore --exit-on-error`, verifica migraciones, las dos claves y todos los archivos referenciados; elimina solo ese entorno de ensayo. Nunca sobrescribe la base actual. La prueba sintética cubre recuperación de claves aunque la cartera no tenga archivos. No promete tiempo de recuperación ni comprueba automáticamente toda la lógica de negocio tras un desastre.

Para producción usar `COMPOSE_FILE=docker-compose.production.yml bash tools/backup.sh`. En el ensayo indicar `RECOVERY_ENV_FILE=.env.production` y `RECOVERY_BACKEND_IMAGE` con la imagen local de esa versión; las variables de base de datos/correo/pagos se sustituyen por las de aislamiento. `RECOVERY_KEYRING`, `BACKUP_PASSWORD_FILE` y `BACKUP_REPOSITORY_DIR` aceptan rutas absolutas.

Pendiente externo: ubicación fuera del servidor, ejecución diaria, alerta por copia fallida/antigua, retención aprobada y ensayos periódicos. No se ha configurado todavía ninguna transferencia externa ni se borran copias antiguas automáticamente. Ante una restauración real: aplicar las supresiones posteriores a la copia antes de reabrir, revocar sesiones restauradas y reconciliar eventos externos; no basta con importar el dump.

## Despliegue preparado, sin contratar servidor

`docker-compose.production.yml` es independiente del local: PostgreSQL privado con usuario de aplicación no superusuario, Caddy para HTTPS, Nginx, PHP, worker, scheduler y ClamAV. Solo Caddy publica 80/443. La subred `172.30.20.0/24` debe estar libre; el proxy tiene `.11` y es el único origen confiado por Laravel. Mantener el acceso directo a Nginx cerrado. No se monta el socket Docker.

1. Completar `.env.production` desde la plantilla, con dominio real y DNS, credenciales distintas de administrador/usuario de PostgreSQL, correo real, `APP_KEY` única, `BILLING_ENABLED=false` e IA/fiscalidad ocultas.
2. Preparar firewall (SSH restringido, 80/443), actualizaciones, reloj sincronizado para TOTP, almacenamiento cifrado y recursos suficientes para ClamAV. Ajustar rotación de logs y monitorización externa. No publicar PostgreSQL ni ClamAV.
3. Compilar imágenes y levantar PostgreSQL/ClamAV; inicializar explícitamente el llavero con `docker compose --env-file .env.production -f docker-compose.production.yml run --rm backend php artisan vault:key --if-missing`. Guardarlo fuera del servidor antes de aceptar archivos.
4. Ejecutar `security:check`, aplicar `migrate --force` una vez y verificar `vault:files`; arrancar backend/web/worker/scheduler/proxy. Las migraciones NO se ejecutan automáticamente al reiniciar producción. No ejecutar seeders de demo.
5. Comprobar HTTPS, sesión/CSRF, verificación y recuperación por correo, autorización cruzada de documentos, antivirus con EICAR, envío/adjuntos de soporte, recuperación de copias y alertas. Revisar imágenes de contenedor y fijar versiones/digests de la entrega. Una validación de YAML no sustituye estas pruebas.

El Compose de producción no se ha levantado contra un dominio real. La plantilla PostgreSQL inicializa el rol solo sobre un volumen nuevo; no cambia credenciales de volúmenes existentes. Para una base externa: sustituir el servicio y usar TLS `verify-full` con la CA correspondiente. La configuración `disable` solo corresponde al tráfico del host por la red privada Docker de esta plantilla.

## Antes de abrir la beta

- Completar titular, privacidad, condiciones, subencargados, conservación y contratos con proveedores; revisar el tratamiento de documentos de inquilinos y adjuntos de soporte.
- Correo real: registro/verificación, recuperación y soporte entregados y probados. SPF, DKIM y DMARC configurados.
- Ensayo con un propietario externo y revisión visual móvil/escritorio del registro, 2FA, alta de inmueble, contrato, pago registrado, documento y soporte. No hay navegador conectado en esta sesión para acreditar esa revisión visual.
- Revisar Stripe remoto aunque el código esté bloqueado; nunca anunciar que se han cancelado cobros antiguos por cambiar una variable local.
- Copia externa y monitorización reales, con responsable y procedimiento de recuperación.

Referencias técnicas: [cifrado Laravel](https://laravel.com/framework/docs/13.x/encryption), [Google2FA](https://github.com/antonioribeiro/google2fa), [copias Restic](https://restic.readthedocs.io/en/stable/040_backup.html).
