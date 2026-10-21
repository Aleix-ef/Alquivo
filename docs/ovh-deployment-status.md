# Despliegue restringido de Alquivo — 20–21/10/2026

## Estado actual

Alquivo funciona en **https://app.alquivo.com**, con certificado público válido de
Let's Encrypt. **No se ha abierto la beta.** HTTPS solo permite la conexión IPv4
de prueba del titular; otra red, datos móviles o un cambio de IP puede quedar
bloqueado. No publicar AAAA ni retirar la restricción sin revisar la apertura.
`alquivo.com` sigue sirviendo la landing en Pages y responde 200; no se han
cambiado su DNS, correo ni lista de espera.

- VPS-2 OVHcloud, ubicación contratada Gravelines (Francia), Ubuntu 26.04 amd64.
- Versión de aplicación: `b831c76a855f6495f711400f46b7f7f1ac0f33cb`.
- Release operativa actual: `7c471ff9d8349e9938cf88b04c32671a45e2fdd5`, con
  scripts de copias actualizados y las mismas imágenes de aplicación.
- Código: `/opt/alquivo/releases/<commit>`; enlace estable `/opt/alquivo/current`.
- Entorno privado: `/opt/alquivo/shared/.env.production`, propietario `ubuntu`,
  permisos 600; enlaces desde cada versión. Claves nuevas, no importadas del PC.
- Siete servicios: PostgreSQL, ClamAV, PHP/backend, worker, scheduler, Nginx/web y
  Caddy/proxy. Solo el proxy publica HTTP/HTTPS. Sin servicios systemd fallidos.
- Base nueva con las 37 migraciones aplicadas. **Cero usuarios al terminar las
  comprobaciones**, sin seeders, demo, contratos ni documentos importados.
- Beta gratuita visible: **50 inmuebles y 5 GB**. Pagos, fiscalidad e IA pública
  apagados; `ASSISTANT_ENABLED=false` y `ASSISTANT_VALIDATED=false` intactos. No se
  ha hecho ninguna llamada al proveedor de IA ni usado presupuestos anteriores.
- Los borradores legales pendientes del workspace **no** se incluyeron en esta
  versión. Los commits técnicos están guardados localmente, todavía sin push a
  GitHub; no se ha disparado otro despliegue de la landing.

Imágenes de aplicación comprobadas:

| Imagen | Identificador |
| --- | --- |
| Backend (también worker y scheduler) | `sha256:5432036de2ceab32eece5751c85bb28d33ce6fafa48bc13f79e3f925669dcb63` |
| Web | `sha256:6864feeb0447e7b5ffdff61e6b0901e3d5468db98182edcae8775557226bf431` |

## Comprobaciones realizadas

**13 pruebas HTTP sobre el dominio real**, sin crear una cuenta ni desactivar
MFA: login 200 con CSP/HSTS/noindex; `/up` 200; configuración pública preservando
flags; catálogo solo beta y cuotas correctas; `auth/me`, inmuebles y dashboard
anónimos 401; cookies Secure/HttpOnly/SameSite y limitadas al host; login sin CSRF
419 y petición sintética con CSRF correcto 422; dos rutas `.env` inaccesibles;
HTTP redirige a HTTPS. TLS validado con el almacén de confianza, sin `-k`.

**7 comprobaciones de runtime**: PHP no recibe la contraseña de administrador de
PostgreSQL; cifrado y descifrado de contenido sintético; no intercambio de rutas
de un archivo cifrado; antivirus acepta un archivo limpio y rechaza EICAR;
scanner inaccesible falla con 503. Esta última simulación solo alteró el puerto
en el proceso CLI de prueba y lo restauró; no detuvo ClamAV ni cambió FPM. Los
archivos temporales se eliminaron; no se crearon documentos de usuario.

Se encoló **un correo técnico** a `soporte@alquivo.com` usando Mail de Laravel y
el worker real de producción. Trabajo completado; cero trabajos pendientes o
fallidos en esa comprobación. **El titular confirmó recepción en Zimbra**. Esto
no acredita todavía los enlaces de verificación/recuperación ni todos los
proveedores de destinatarios.

Desde la conexión permitida solo responden TCP 22/80/443; 5432, 9000, 3310, 7357,
2019 y 2375/2376 quedan filtrados. La política propia en `DOCKER-USER` conserva el
aislamiento de Docker y descarta nuevas conexiones externas no autorizadas; IPv6
de aplicación cerrado y sin AAAA. Se verificó persistencia en reinicio antes de
arrancar la app. La fuente externa de navegación no accedió al login y el filtro
registra descartes. Esto no equivale a una auditoría completa de red.

El código del backend es legible pero no escribible por su usuario de ejecución
sin privilegios. La cuenta SQL `alquivo` no es superusuario, no crea bases ni
roles. Control `security:check` correcto. Snapshot de recursos, no prueba de
carga: 1,8 GiB RAM usados de 7,6 y unos 65 GiB de disco libres.

## Problemas corregidos durante el primer despliegue

1. La máscara de extracción dejaba código/SQL ilegibles por usuarios de
   contenedores. Se preservaron permisos del archivo Git y se normalizó el
   empaquetado con `tar.umask=022`, sin root PHP ni permisos 777.
2. La primera inicialización SQL no se ejecutó por esos permisos. Se comprobó
   ausencia de rol/base y coincidencia de credenciales, y se ejecutó el script
   inicial con parada ante error. **No se borraron volúmenes ni datos**.
3. `pg_isready` daba salud aparente aunque faltaba la base. El healthcheck ahora
   autentica con la cuenta de la app y ejecuta `SELECT 1`.
4. Se ocultó la contraseña administrativa de PostgreSQL del entorno de PHP,
   worker y scheduler; sigue disponible únicamente donde la necesita Compose.
5. Los comandos `compose run` enviados por SSH podían consumir el resto del
   script. Se ejecutan con `-T` y entrada `/dev/null`, verificando cada resultado.
6. HTTP-01 permite emitir/renovar certificados sin abrir 443 a todo Internet.

Antes del despliegue se corrigieron dependencias de Vue/source-map-js, con cero
avisos en el audit de npm y 193 tests del candidato frontend correctos. Los
cambios posteriores de este bloque son infraestructura, no funciones nuevas.

## Pendiente antes de admitir datos reales

Actualización del 21/10/2026: Storage Box de 1 TB en Falkenstein (Alemania),
autenticada y montada con SMB 3.1.1 cifrado. Snapshot externo `502b0768` verificado
con lectura de integridad y restaurado en un entorno aislado, incluidas las 37
migraciones y un canario cifrado con las dos claves. Se repitió el ensayo con
límites de recursos. Cero archivos privados de usuario referenciados en la copia;
no se usaron documentos reales. Copia diaria 03:15–03:20 Europe/Madrid, retención
de 30 días/última copia y control horario activos. Alerta recibida en Zimbra,
confirmada por el titular. App/landing HTTPS 200 y control de seguridad correctos.
**Falta custodiar y probar las claves fuera del VPS** y la monitorización externa
de ausencia de copias/caída total. Detalles y límites en
[copias externas y recuperación](offsite-backups.md).

- Copia cifrada externa de base **y archivos**, recuperación independiente de
  `APP_KEY`/llavero de documentos/secreto Restic y restauración de prueba. El
  backup Standard del VPS no cierra este punto. **Copia, programación y ensayo
  aislado correctos; pendiente custodia/descifrado independiente de secretos y
  recorrido con documentos sintéticos subidos desde la app.**
- Registro con consentimiento y una cuenta propia de prueba: verificar correo,
  recuperación, MFA y dispositivos, onboarding, documentos/aislamiento entre
  cuentas, contratos/cobros/gastos, tickets, exportación y eliminación.
- Alertas de disponibilidad, espacio, copias, cola/scheduler y antivirus. No
  tratar logs puntuales como monitorización continua.
- Cierre de revisión legal/proveedores y archivo nuevo de la versión definitiva,
  sin sobrescribir el borrador legal de 19/10. Desplegar textos coherentes juntos.
- Decisión expresa y prueba separada de IA en producción antes de activar flags.
  Sin Document AI real, pagos, fiscalidad ni inglés para acceso general.
- Publicar commits técnicos y definir entrega revisada; abrir HTTPS e invitar
  usuarios solo después de comprobar los puntos anteriores.

Para consultar el estado sin mostrar secretos, entrar por SSH y ejecutar:

```bash
cd /opt/alquivo/current
sudo docker compose --env-file .env.production -f docker-compose.production.yml ps
sudo docker compose --env-file .env.production -f docker-compose.production.yml exec -T backend php artisan security:check
```

No ejecutar `down -v`, seeders ni comandos que impriman `.env`/configuración
expandida. Procedimiento de host en [ops/server](../ops/server/README.md) y cierre
de lanzamiento en la [guía de beta](beta-launch-guide.md).
