# Apertura técnica de acceso web — 27/10/2026

## Resultado y autorización

El titular pidió abrir la app mientras revisa los proveedores. **HTTPS IPv4 público habilitado a las 17:25:45 UTC** en https://app.alquivo.com. El titular confirmó que la pantalla de acceso carga desde el móvil **con Wi-Fi apagado**. Reversión automática cancelada después de esa confirmación y de las comprobaciones técnicas; timer y servicio transitorios inactivos a las 17:27:58 UTC.

La landing https://alquivo.com conserva **lista de espera**, no se impulsa todavía el registro desde sus CTA. El acceso directo público permite llegar al registro de la app: no es una beta privada ni hay un control de invitaciones añadido. Se indicó usar datos ficticios mientras se cierra la revisión contractual/privacidad pendiente. La accesibilidad técnica no acredita esos acuerdos ni autoriza afirmar cumplimiento jurídico.

## Qué se cambió

- Ejecutable de host `alquivo-docker-firewall`: modo explícito `ALQUIVO_WEB_ACCESS=public` en la configuración privada de red. Sin esa elección sigue restringido a la IPv4 de pruebas. Valores no reconocidos fallan antes de aplicar reglas.
- HTTPS IPv4 TCP/443 permitido únicamente para el proxy fijo, en tráfico DNAT originalmente dirigido al puerto 443. HTTP/80 para certificados/redirección ya estaba permitido.
- Se conserva el DROP del resto del tráfico reenviado desde la interfaz pública. IPv6 sigue cerrado para la app en ambos modos y no se encontró DNS IPv6 nativo.
- UFW mantiene SSH por clave, reglas de prueba y bloqueos existentes; añade solo TCP/443 IPv4 hacia el host. No se abrieron servicios de datos, administración de Docker, FPM o antivirus.
- Unidad persistente al arranque y dependencia de Docker conservadas. **Solo recarga** de la política: no reinicio de Docker, recreación de contenedores ni despliegue de la app.

El código de la app continúa en `050731bc131b7e4a4f2d8f16b7fb86217b79b37a`, revisión legal `2026-10-27-r1`. Entorno de aplicación y configuración SSH idénticos por huella antes/después; siete IDs de contenedor idénticos. Sin cambios de usuarios, base, documentos, secretos, flags, modelos, cuotas o creación de datos por IA. Sin llamadas facturadas a modelos.

## Verificación

- **13 pruebas específicas** del firewall correctas: default restringido, apertura exclusiva del proxy, IPv6 cerrado, configuraciones inválidas y creación de saltos sin vaciar cadenas ajenas ni tocar SSH. Comandos de red simulados; no ejecutan reglas reales.
- Sintaxis Bash correcta; unidad validada en el host instalado. La comprobación local de esa unidad no era aplicable porque el ejecutable absoluto no está instalado en el PC. Advertencias heredadas de CPUAccounting en unidades XFS del sistema no impidieron la validación remota.
- Nueva política primero recargada en modo restringido: reglas IPv4/IPv6 idénticas a las anteriores; después se aplicó la elección pública.
- **10 comprobaciones HTTPS locales** sobre el dominio real: salud/login/registro/privacidad/condiciones/configuración/planes con 200 y cuenta/documentos/conversaciones sin sesión con 401.
- **7 comprobaciones HTTPS por DNS público desde la conexión de trabajo**, con TLS validado y los estados esperados, sin leer contenidos privados.
- Conexiones TCP a 5432, 9000, 3310, 2375 y 2376 no accesibles desde esa conexión. No es un escáner completo ni prueba de ausencia de vulnerabilidades.
- Prueba **independiente del titular desde datos móviles** correcta para login. El lector web automatizado no pudo acceder a estas URL: no se usa ese fallo como evidencia de caída ni como validación externa.
- Monitor sin incidencias observadas; ejecución real posterior `Result=success`, `ExecMainStatus=0`. Copias, vigilancia y retención siguen activas.

No se repitieron las 819 pruebas de aplicación del despliegue legal: no cambió su código. No se enviaron correos de prueba ni formularios ni se crearon cuentas sintéticas para este cambio. La prueba móvil confirma carga, no todos los recorridos funcionales.

## Recuperación y mantenimiento

Se conserva un checkpoint privado root:root/700 en `/opt/alquivo/shared/public-opening-20261027` con ejecutable, unidad y configuración de red anteriores y `rollback.sh`. El script restaura únicamente la política restringida y retira el nuevo permiso web de UFW; no modifica volúmenes o datos. Ejecutarlo conscientemente cerraría el acceso de los nuevos usuarios.

El despliegue se hizo con bloqueo, verificación de hashes y timer independiente de reversión de cinco minutos. No se activó esa reversión. Huella del nuevo ejecutable: `444ff98a5302710a490675374f19dcb96e68a0e684e55ba003816d96887ba5d6`; instalador `a233365929675ae63ec5c2d47ba16ec054c8357bd9b863dbe4ad55fd506cf127`.

La vigilancia existente sigue combinando controles locales del servidor y heartbeat externo Healthchecks; **no se ha configurado un monitor externo HTTPS continuo** ni contratado otro proveedor por esta tarea. El ensayo desde otra red es una verificación puntual.

## Pendientes reales

Acuerdos/opciones privadas de proveedores, registro y procedimientos organizativos continúan pendientes de confirmación donde no haya evidencia. [Guía breve de cierre](provider-closeout-quick-guide.md). No confundir publicación de páginas o archivo de documentos con revisión de su aplicabilidad, riesgos o cumplimiento. Mantener la revisión manual del buzón y la custodia independiente de recuperación. La captación pública directa desde la landing se decide tras este cierre.
