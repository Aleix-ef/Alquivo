# Base del VPS (Ubuntu 26.04, amd64)

Estos archivos son configuración de host, **no** configuración de Laravel. No
aplicarlos al ordenador de desarrollo ni copiar encima de una instalación con
ajustes propios sin revisarlos. `docker.sources` es específico de Resolute/amd64.

## Comprobación del 20/10/2026

Aplicado al VPS-2 de OVHcloud contratado por el titular en Gravelines. Después
del reinicio se verificaron kernel `7.0.0-38-generic`, Docker Engine `29.9.1`,
Compose `5.6.0`, UFW y Fail2ban activos, reloj sincronizado, ningún servicio
fallido y ningún reinicio pendiente. Una conexión nueva por clave y sudo pasó;
el intento sin clave fue rechazado con `Permission denied (publickey)` sin
introducir contraseñas. Configuración efectiva: root/contraseña SSH y reenvío del
agente desactivados. El temporizador de reversión se canceló después de probar
el acceso; no queda programado.

La prueba `hello-world` pasó sin red de contenedor ni puertos publicados, y su
contenedor se eliminó automáticamente. Cero contenedores de aplicación. No se
modificaron DNS, correo, landing, secretos de desarrollo ni flags públicos de IA.
Estos resultados son del host vacío, no una validación del despliegue de Alquivo.

## Empaquetado de versiones

Para copiar una versión desde Git sin archivos privados, usar
`git -c tar.umask=022 archive --format=tar COMMIT` y extraerla en su directorio de
release con `tar --extract --preserve-permissions --no-same-owner`. Mantener el
directorio exterior en 750 y el archivo de entorno compartido en 600. Una
extracción con máscara 027 sin preservar permisos deja código 640/directorios
750; al copiarlo como root a una imagen, el usuario no privilegiado del runtime
ya no puede leerlo. No resolverlo ejecutando PHP como root ni con permisos 777.
Antes de inicializar comprobar que ese usuario puede leer `artisan`,
`public/index.php` y `vendor/autoload.php`, y no puede escribir el código.

Al ejecutar comandos Compose `run` desde un script enviado por la entrada de
SSH, añadir `-T` y redirigir su entrada desde `/dev/null`. De lo contrario, el
contenedor puede consumir el resto del script y dejar pasos sin ejecutar. Exigir
éxito explícito del control de producción, migraciones y verificación del vault.

El Caddyfile usa HTTP-01 para emitir/renovar el certificado sin abrir HTTPS al
público durante la prueba. No desactivar la validación TLS ni usar certificado
autofirmado para simular una comprobación de HTTPS de producción.

El healthcheck de PostgreSQL debe autenticar con la cuenta de la aplicación y
ejecutar una consulta real. `pg_isready` por sí solo no acredita que existan el
usuario o la base ni que funcione su contraseña. Si falla el primer init por un
archivo SQL ilegible, corregir el empaquetado y diagnosticar el estado antes de
seguir: un reinicio del contenedor no vuelve a ejecutar los scripts del volumen
ya inicializado. No borrar el volumen ni rotar contraseñas como atajo. El entorno
de PHP y sus workers tampoco recibe la contraseña de administrador de PostgreSQL.

## Orden de preparación

1. Confirmar identidad/huella del servidor y acceso de recuperación en OVHcloud.
2. Probar una **conexión nueva** con clave pública, `BatchMode=yes`, comprobación
   estricta de host y `sudo -n true`. No copiar la clave privada al servidor ni
   activar reenvío del agente. Conservar la sesión actual.
3. Actualizar Ubuntu y kernel sin retirar paquetes. Instalar `ca-certificates`,
   `curl`, `git`, `ufw`, `fail2ban` y `unattended-upgrades` desde Ubuntu.
4. Añadir la clave pública del repositorio oficial de Docker en
   `/etc/apt/keyrings/docker.asc`, permisos 644, descargándola por HTTPS desde
   `https://download.docker.com/linux/ubuntu/gpg`. Instalar `docker.sources` en
   `/etc/apt/sources.list.d/`. Actualizar índices e instalar `docker-ce`,
   `docker-ce-cli`, `containerd.io`, `docker-buildx-plugin` y
   `docker-compose-plugin`. No usar el script de conveniencia ni abrir el socket
   Docker por TCP. Administrar con `sudo docker`, sin añadir usuarios al grupo.
5. En un host sin `/etc/docker/daemon.json`, instalar `daemon.json` allí con
   propietario root y permisos 644. Validar con `dockerd --validate` antes de
   reiniciar Docker. La rotación predeterminada no sustituye los límites que el
   Compose define por servicio ni la conservación de los logs de la aplicación.
6. Instalar `90-alquivo-updates` en `/etc/apt/apt.conf.d/`: actualizaciones
   automáticas de seguridad con la política de orígenes de Ubuntu y sin reinicio
   automático. Docker e imágenes se actualizan de forma controlada aparte.
7. **Solo en el host inicial con UFW inactivo y sin estos drop-ins**, programar
   primero una reversión con temporizador de diez minutos. Debe retirar
   `/etc/ssh/sshd_config.d/00-alquivo-ssh.conf`, validar/recargar SSH y desactivar
   UFW si no se cancela. No reutilizar esa reversión en un servidor ya protegido.
8. Instalar `00-alquivo-ssh.conf` en `/etc/ssh/sshd_config.d/` (root:root, 644),
   comprobar `sshd -t` y **los valores efectivos** de `sshd -T`. El prefijo `00-`
   evita que el `PasswordAuthentication yes` de cloud-init tenga prioridad.
   Recargar `ssh.service`; no cerrar las sesiones existentes.
9. En este primer estado, UFW deniega entrada y tráfico reenviado, permite salida
   y permite únicamente SSH TCP/22, tanto IPv4 como IPv6. No restringirlo a una IP
   temporal sin disponer de recuperación. Instalar `alquivo-sshd.local` en
   `/etc/fail2ban/jail.d/`, validar con `fail2ban-client -t` y activar su servicio.
10. Desde otra conexión nueva, verificar acceso por clave, sudo, SSH efectivo,
    UFW y Fail2ban. Solo después cancelar el temporizador de reversión.
11. Probar `sudo docker run --rm hello-world`. No publica puertos ni contiene datos
    de usuarios. Comprobar Compose, servicios, reloj y puertos. Reiniciar de forma
    controlada si el kernel lo requiere y repetir la comprobación tras arrancar.

## Antes de publicar contenedores

**UFW no protege por sí solo los puertos publicados por Docker.** Antes de
ejecutar el Compose de producción con `80:80` y `443:443`, preparar y probar las
reglas de entrada del proveedor y/o `DOCKER-USER`, incluida su persistencia y
tratamiento IPv6. Durante la validación, 443 sigue restringido a los probadores;
80 se habilita para ACME cuando corresponda. Nunca publicar PostgreSQL, PHP-FPM,
ClamAV ni la API/socket de Docker. No publicar AAAA sin revisar IPv6.

Esta base **no** despliega la app, configura copias externas, crea secretos,
habilita IA/pagos ni abre la beta. Continuar por la
[guía de lanzamiento](../../docs/beta-launch-guide.md).

## Protección del despliegue de prueba

**Actualización 27/10:** apertura IPv4 web aplicada por autorización expresa, documentada en [apertura pública](../../docs/public-web-opening-20261027.md). El script mantiene `ALQUIVO_WEB_ACCESS=restricted` por defecto; solo `public` abre TCP/443 al proxy DNAT fijo. Los dos modos bloquean ingreso de app por IPv6. La IP de prueba se conserva para poder volver al modo restringido. Cambiar el modo en el archivo privado del host y **recargar**, no reiniciar, `alquivo-docker-firewall.service`; UFW debe concordar con la elección. No quitar la cadena ni la dependencia de Docker. Trece regresiones sin comandos de red reales: `node --test tools/tests/docker-firewall.test.mjs`. Los párrafos siguientes describen la preparación restringida inicial.

`alquivo-docker-firewall` y su unidad systemd añaden una cadena propia en
`DOCKER-USER` para ambas familias, sin vaciar reglas ajenas. Copiar el ejecutable
a `/usr/local/sbin/`, la unidad a `/etc/systemd/system/` y la dependencia de Docker
a `/etc/systemd/system/docker.service.d/10-alquivo-firewall.conf`. Completar
`network.env.example` **fuera de Git**, instalarlo como
`/etc/alquivo/network.env` (root:root, 600) y confirmar interfaz pública e IP fija
del proxy en el Compose. La IP de prueba es la conexión pública del titular,
no una IP de documentación ni una red completa.

Activar primero la política con el host todavía sin contenedores de aplicación.
La dependencia hace que un error de la política impida arrancar Docker; preparar
y comprobar las reglas **antes** de una compilación, no mientras está en curso.
Validar los valores y las reglas efectivas de `iptables`/`ip6tables`, los saltos de
`FORWARD`/`DOCKER-USER`, la conectividad saliente y su persistencia después de
reiniciar. HTTP/80 publicado solo se permite hacia el proxy para ACME/redirección;
HTTPS/443 hacia el mismo proxy solo desde la IPv4 de prueba. El resto de nuevas
conexiones reenviadas desde la interfaz pública se descarta. UFW sigue protegiendo
el host, incluido IPv6; todavía no se publica AAAA. No ejecutar otro servidor
HTTP público en paralelo.

Para cambiar de conexión del probador, actualizar el archivo privado y ejecutar
`sudo systemctl reload alquivo-docker-firewall.service`. La recarga vuelve a
aplicar la política sin detener Docker; reiniciar esa unidad sí puede detenerlo
por la dependencia y debe reservarse para mantenimiento. No retirar el requisito
ni borrar la cadena como atajo. La apertura pública
posterior requiere una decisión y reglas nuevas explícitas, no quitar el control.

`init-production-env.php` genera `APP_KEY` y las dos contraseñas de base de datos
con aleatoriedad criptográfica y permisos 600. Se ejecuta sobre una plantilla en
un directorio privado, no imprime claves y nunca sobrescribe un archivo existente.
Mantiene proveedor de correo sin clave, IA y pagos apagados: **no** elude
`security:check`. Completar después la clave de Resend de producción directamente
en el VPS; conservar las claves de recuperación fuera del servidor antes de usar
datos reales. La plantilla no sustituye el inventario o la copia de secretos.

Fuentes: [Docker en Ubuntu](https://docs.docker.com/engine/install/ubuntu/),
[SSH en Ubuntu](https://documentation.ubuntu.com/server/how-to/security/openssh-server/),
[actualizaciones automáticas](https://documentation.ubuntu.com/server/how-to/software/automatic-updates/).

## Preparar el acceso a una Storage Box externa

Instalar `alquivo-storagebox-credentials` como
`/usr/local/sbin/alquivo-storagebox-credentials`, propietario root y permisos 700.
El directorio `/etc/alquivo` debe pertenecer a root y no permitir escritura a
grupo/otros. El titular lo ejecuta en una sesión SSH interactiva:

```bash
sudo /usr/local/sbin/alquivo-storagebox-credentials u123456
```

Pide la contraseña dos veces sin eco y crea
`/etc/alquivo/storagebox.credentials` con permisos 600. No pasar contraseñas como
argumentos, variables de entorno, mensajes del chat ni archivos versionados. El
comando rechaza sobrescribir credenciales existentes; no monta almacenamiento,
programa copias ni acredita que la autenticación funcione.

Antes de configurar el montaje, confirmar ubicación europea y activar SMB y
External Reachability en Hetzner. La conexión desde OVH necesita salida TCP/445,
**no** abrir ese puerto de entrada en el VPS. Exigir cifrado de transporte SMB3
(`seal`), credenciales privadas y comprobación de montaje real, sin caer en un
directorio local si el almacenamiento externo no está disponible. Continuar con
copia cifrada, recuperación independiente de claves, alertas y restauración
aislada antes de dar este bloque por completado.

La configuración aplicada, las unidades de copia/alerta, los límites y las tareas
del titular están recogidos en [copias externas y recuperación](../../docs/offsite-backups.md).
Los scripts y el estado usan rutas persistentes en `/opt/alquivo/shared`, no
secretos ni temporales dentro de cada release. El montaje de ejemplo necesita
nombre escapado con `systemd-escape` y el hostname real; no contiene contraseñas.
