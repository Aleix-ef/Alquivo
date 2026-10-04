# Abrir la beta de Alquivo: guía de ejecución

Actualizado el **4 de octubre de 2026**. La landing ya está pública y el titular confirma Search Console configurado. La aplicación **no** está desplegada; esta guía no abre registros ni activa IA. Los pasos de contratación los realiza el titular. Nunca pegar claves, contraseñas, documentos o enlaces de recuperación en el chat ni en Git.

## Estado: qué está comprobado y qué no

- La aplicación dispone de registro, recuperación, verificación, TOTP/código por email/ambos, dispositivos de confianza de 90 días y tickets. Los tests usan notificaciones simuladas. Un correo de recuperación real llegó al titular en la comprobación anterior; no acredita todos los envíos ni enlaces del despliegue público.
- La plantilla `.env.production.example` apunta a **`app.alquivo.com`**, con frontend/API del mismo origen, cookies seguras, indexación de la app apagada, Beta 50 inmuebles/5 GB y pagos/fiscalidad/IA pública apagados. Luna y fallback técnico Sol quedan explicitados y el presupuesto global inicial se limita a 5 USD/mes. No se han modificado archivos privados `.env`.
- La revisión legal **2026-10-04** distingue servicios actuales y previstos; mantiene la identificación obligatoria sin mostrar la forma jurídica. Los objetivos de conservación no se presentan como configuración ya desplegada. Continúa `reviewedForPublication=false`: faltan evidencias de contratos/cuentas y operación real.
- **No existe inicio de sesión con Google en este checkout.** Gmail receptor y Google2FA (biblioteca TOTP local) no son Google OAuth. No crear un cliente OAuth ni anunciar ese acceso por estas pruebas. No es una función necesaria para esta beta.
- Document AI real, inglés y fiscalidad siguen fuera del acceso general. No se amplían funciones ni se consumen llamadas reales de IA en esta preparación.

## 1. Contratos y cuentas: tareas del titular

Los nombres comerciales no bastan para acreditar un encargo: guardar fuera de Git el acuerdo vigente y evidencia de su aplicación a la cuenta. Estas fuentes describen los productos; no permiten comprobar una aceptación dentro de tu cuenta privada.

| Servicio                      | Uso real/previsto                                                   | Qué verificar antes de la beta                                                                                                                                                                                                             |
| ----------------------------- | ------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Cloudflare                    | Landing, D1 de interesados, Turnstile, DNS y reenvío actual a Gmail | Cuenta con los datos del titular; DPA y subencargados; D1 independiente de Laravel; MFA y accesos mínimos. No asumir que todos sus servicios están en la UE.                                                                               |
| Resend / Plus Five Five, Inc. | Envío de correos de cuenta y avisos al equipo                       | DPA y cláusulas contractuales tipo; tratamiento principal en EE. UU.; dominio verificado; clave restringida. Si la antigua clave retirada de la plantilla era activa, revocarla.                                                           |
| Gmail gratuito                | Buzón receptor actual                                               | No atribuirle el acuerdo empresarial de Workspace. Resolver su adecuación o sustituirlo por un buzón profesional, comprobar conservación y evitar documentos de terceros en correos iniciales.                                             |
| OpenAI API                    | Chat voluntario, preguntas e información limitada de tools          | Identificar entidad contractual en contrato/factura, DPA y garantías de transferencia; comprobar proyecto/región y desactivar compartición voluntaria para entrenamiento. No afirmar retención cero o residencia europea sin acreditarlas. |
| Hetzner                       | Proyecto creado; VPS previsto, todavía no contratado                | Crear el servidor en una región UE, formalizar el [DPA en la cuenta](https://accounts.hetzner.com/account/dpa), confirmar región y permisos.                                                                                               |
| Copias externas               | Pendientes; propuesta Storage Box UE                                | Contratar el destino separado, formalizar su acuerdo y probar caducidad, recuperación y acceso independiente. No sustituirlo por un volumen o snapshot del mismo VPS.                                                                      |
| Stripe                        | Contratación de la beta desactivada                                 | No hacen falta claves live. Revisar sesiones, Payment Links y suscripciones externas antiguas si existen; el flag de Alquivo no las cancela. Antes de cobrar se revisará por separado.                                                     |
| GitHub                        | Código y CI, no datos de carteras                                   | MFA, permisos mínimos, clave de despliegue de solo lectura; nada de `.env`, bases ni documentos en repositorios, issues o Actions.                                                                                                         |

Fuentes verificadas: [DPA de Cloudflare](https://www.cloudflare.com/cloudflare-customer-dpa/), [DPA de Resend](https://resend.com/legal/dpa), [protección de datos de Hetzner](https://docs.hetzner.com/general/company-and-policy/data-protection-at-hetzner/), [OpenAI Docs: controles de datos](https://developers.openai.com/api/docs/guides/your-data), [AEPD: responsable y encargado](https://www.aepd.es/preguntas-frecuentes/2-tus-obligaciones-como-responsable-del-tratamiento/8-responsable-y-encargado-del-tratamiento).

Los textos pueden revisarse internamente, pero un contrato no se firma por rellenar una página. La revisión no es certificación jurídica. Mantener un inventario privado de tratamientos (cuentas, cartera, soporte, IA, lista de espera, seguridad y copias), bases, personas afectadas, acceso, proveedores y conservación; completar la evaluación de riesgos y la valoración de si procede una evaluación de impacto. Procedimientos de derechos, incidentes y supresiones: [operación de privacidad](privacy-operations.md). No guardar expedientes reales en estos documentos públicos.

## 2. Cambiar el buzón sin perder `soporte@alquivo.com`

Propuesta, **todavía no contratada**: [OVHcloud Zimbra Starter](https://www.ovhcloud.com/es-es/emails/). Precio publicado al consultar: **0,30 €/mes/cuenta + IVA**, 0,36 € con IVA mostrado por el proveedor. Confirmar importe, periodo, renovación y entidad en el pedido. No hay que trasladar el dominio desde Cloudflare.

1. Crear cuenta OVHcloud con los datos profesionales y MFA; contratar una cuenta Zimbra Starter.
2. Añadir `alquivo.com` como **dominio externo** y validarlo con el registro que muestre OVHcloud. Si es CNAME, usar DNS only para ese registro de validación. No cambiar nameservers, la landing ni el TXT de Search Console.
3. Crear el buzón **`soporte@alquivo.com`**, guardar su contraseña en el gestor y acceder al webmail para comprobarlo antes de cambiar la recepción.
4. Guardar la configuración DNS actual y los mensajes necesarios de Gmail. Cambiar los MX de recepción solo cuando el buzón esté listo: desactivar Email Routing para ese dominio y aplicar los MX exactos del asistente OVHcloud. No mezclar ambos juegos de MX; Gmail deja de ser el destino nuevo, pero no desaparece el historial anterior.
5. Configurar DKIM/SPF según el asistente **manteniendo los registros de Resend**. No crear dos SPF en el mismo hostname: combinar autorizaciones cuando coincidan. Revisar DMARC sin inventar destinos de informes ni borrar registros existentes.
6. Enviar desde una dirección externa a soporte y responder desde el buzón. Comprobar remitente `soporte@alquivo.com`, recepción, Spam y cabeceras SPF/DKIM/DMARC; repetir después de la propagación.
7. Mantener **Resend** como envío automático de la app: no necesita la contraseña del buzón. Después de confirmar la migración, actualizar proveedor/contrato/ubicación/conservación en los textos y borrar de Gmail lo que ya no deba conservarse, incluidas copias locales cuando proceda.

El asistente del proveedor da los registros exactos, no copiarlos de un ejemplo antiguo: [guía oficial Zimbra para dominios externos](https://docs.ovhcloud.com/en/guides/web-cloud/email-and-collaborative-solutions/zimbra/getting-started-zimbra). La redacción actual sigue indicando Gmail, porque aún es el servicio utilizado.

## 3. Crear el servidor en Hetzner

1. Entrar en Console → proyecto **Alquivo** → **Create server**.
2. Elegir una ubicación de la **UE** (por ejemplo, Alemania). Confirmar el producto/región disponibles; no seleccionar EE. UU. o Singapur por error.
3. Imagen **Ubuntu 24.04 LTS x86-64**. Tipo inicial: **4 vCPU, 8 GB RAM y al menos 80 GB SSD**; el CX33 o equivalente si aparece disponible. Revisar precio actual antes de confirmar, incluida IPv4 y extras. No contratar Kubernetes, balanceador o base gestionada para este despliegue inicial.
4. Crear/seleccionar clave SSH y subir **solo la clave pública**, nunca la privada. Conservarla en tu equipo/gestor y preparar otro acceso de recuperación independiente.
5. Activar IPv4. Preparar firewall: SSH 22 solo desde tu IP; HTTP 80 para emisión de certificados; HTTPS 443 **solo desde tus IP de prueba mientras se verifica la aplicación**. No abrir 5432, 9000, 3310 ni puertos Docker. Aplicar también reglas IPv6 si lo habilitas; no publicar AAAA antes de revisarlas.
6. Nombre `alquivo-beta-01`; confirmar servidor únicamente cuando estés listo para continuar. Un servidor apagado puede seguir facturándose: comprobar la política del producto, no tratar «apagar» como «cancelar».
7. Guardar IP pública y región. En Cloudflare → DNS añadir **A**, nombre **`app`**, IP del VPS, **DNS only** inicialmente. No tocar el dominio raíz servido por Pages ni los registros del correo. El proxy de Cloudflare se decidirá después de verificar TLS/orígenes; no activar Flexible.

La memoria se dimensiona también para el antivirus: [ClamAV recomienda 3 GiB como mínimo y 4 GiB preferidos](https://docs.clamav.net/manual/Installing/Docker.html). Recursos y catálogo: [Hetzner Cloud](https://www.hetzner.com/cloud/cost-optimized/), [crear servidor](https://docs.hetzner.com/cloud/servers/getting-started/creating-a-server/). Esta elección no es una prueba de carga. Los 5 GB son cupo por cartera, no disco reservado: vigilar consumo total y ampliar antes de agotarlo.

## 4. Preparación y primer despliegue (con ayuda técnica si hace falta)

No ejecutar estos comandos en el proyecto local pensando que despliegan en Hetzner. Primero conectarse al **VPS nuevo** mediante SSH y confirmar equipo/ruta. Mantener 443 restringido; todavía no invitar usuarios.

1. Actualizar Ubuntu, sincronizar reloj, crear usuario de despliegue con clave y sudo. Comprobar acceso antes de desactivar contraseña/root SSH. Instalar Docker Engine y Compose desde la [guía oficial para Ubuntu](https://docs.docker.com/engine/install/ubuntu/); los puertos publicados por Docker requieren también firewall en Hetzner, no confiar solo en UFW.
2. Configurar una clave de despliegue GitHub **solo lectura**, verificada con la huella oficial del host, y clonar el repositorio en `/opt/alquivo`. Usar la rama `release/alquivo-beta-validation` y fijar el commit revisado de lanzamiento; no un `git pull` automático en producción.
3. En el VPS copiar `.env.production.example` a `.env.production`, permisos 600, y editarlo allí. Completar dos contraseñas DB distintas y largas (al menos 20 caracteres), `APP_KEY` propia válida, clave Resend restringida y el resto de datos privados. No reutilizar claves de desarrollo ni volúmenes de usuarios locales. No crear claves Stripe live; conservar `BILLING_ENABLED=false`, `BETA_PROGRAM_ENABLED=true`, `FISCALITY_ENABLED=false`, `ASSISTANT_ENABLED=false`, `ASSISTANT_VALIDATED=false`. Guardar `APP_KEY` fuera del servidor.
4. Desde `/opt/alquivo`, compilar y preparar servicios:

```bash
docker compose --env-file .env.production -f docker-compose.production.yml build --pull
docker compose --env-file .env.production -f docker-compose.production.yml up -d --wait postgres clamav
docker compose --env-file .env.production -f docker-compose.production.yml run --rm --no-deps backend php artisan vault:key --if-missing
docker compose --env-file .env.production -f docker-compose.production.yml run --rm --no-deps backend php artisan security:check
docker compose --env-file .env.production -f docker-compose.production.yml run --rm --no-deps backend php artisan migrate --force
docker compose --env-file .env.production -f docker-compose.production.yml run --rm --no-deps backend php artisan vault:files
docker compose --env-file .env.production -f docker-compose.production.yml up -d
```

5. Guardar el llavero de documentos fuera del VPS junto al procedimiento de recuperación, pero separado de los datos cifrados. Revisar `docker compose ... ps`, `/up`, HTTPS y certificado, cookies Secure/HttpOnly/SameSite, origen/CSRF, DB no expuesta, firmas de ClamAV, espacio, permisos y reloj. No ocultar errores con `|| true`, no desactivar `security:check` para arrancar.
6. Crear una cuenta sintética desde navegador, **sin seeders de demo**, completar el checklist de correo/funciones de abajo y dar permisos de soporte solo a una cuenta verificada con MFA mediante el procedimiento existente. No asignarlos por coincidencia de email ni usar la demo como administrador de producción.
7. Configurar monitorización externa de disponibilidad sin incluir datos de usuarios; alertas de copia antigua/fallida, espacio, cola fallida, scheduler y antivirus. El alta del monitor/servicio y su proveedor deben documentarse si implica nuevos datos.

El Compose actual es de un VPS con PostgreSQL en red privada, Caddy, backend, web, worker, scheduler y ClamAV; no necesita DB externa para empezar. No combinarlo con el Compose local y **nunca `down -v` en la aplicación**. Revisar versiones/digests de imágenes antes de fijar la entrega. Un servidor UE no cifra por sí solo todo su disco: verificar cifrado de host/discos o añadirlo según el riesgo; los archivos de la app y Restic están cifrados, pero no son cifrado de extremo a extremo ni cifran todas las columnas de PostgreSQL.

## 5. Copias externas paso a paso

1. Contratar una **Storage Box separada, en UE**, con el plan pequeño suficiente para la fase inicial. No contratar «Storage Share» por confusión. Confirmar región, precio, DPA y recuperación propia del producto. Si crece el almacenamiento, revisar tamaño y ventana de mantenimiento.
2. Crear un subusuario limitado al directorio de copias; guardar credenciales fuera de Git. Habilitar solo el protocolo elegido y la conectividad necesaria. Propuesta simple para la herramienta actual: montar mediante **SMB/CIFS** en el VPS en `/mnt/alquivo-backups` siguiendo la [guía Hetzner](https://docs.hetzner.com/storage/storage-box/access/access-smb-cifs/). Usar conexión cifrada (`seal` en SMB3) y fichero de credenciales 600; no poner contraseñas en la línea de comandos. No permitir montajes públicos o accesibles por usuarios de la app.
3. Confirmar que es un montaje de red y no una carpeta vacía local:

```bash
mountpoint /mnt/alquivo-backups
findmnt --mountpoint /mnt/alquivo-backups
```

4. Inicializar el secreto de Restic una sola vez con `bash tools/init-backup-secret.sh`. Guardar fuera del VPS ese secreto, `APP_KEY` y el llavero de documentos. La pérdida de las claves puede hacer irrecuperables las copias.
5. Ejecutar en el VPS, desde el proyecto:

```bash
BACKUP_EXTERNAL_MOUNT=/mnt/alquivo-backups bash tools/backup-external.sh
```

El guard verifica un montaje de red SMB/SSHFS y rechaza carpetas locales/no montadas o repositorios enlazados fuera de él. Luego reutiliza la copia existente sin red directa del contenedor: dump PostgreSQL + archivos privados + comprobación cifrada de claves, integridad Restic y bloqueo de concurrencia. La app se pausa durante esta copia consistente; medir duración antes de fijar el horario y no prometer copia sin interrupciones.

6. Ensayar la restauración **desde ese destino**, en contenedores aislados, con las claves respaldadas. Ejemplo una vez preparado `secrets/documents.json` y la imagen de recuperación de esta entrega:

```bash
BACKUP_REPOSITORY_DIR=/mnt/alquivo-backups/alquivo-restic RECOVERY_ENV_FILE=.env.production RECOVERY_BACKEND_IMAGE=alquivo-production-backend bash tools/restore-check.sh
```

Confirmar el nombre real de la imagen antes de usar el ejemplo. El ensayo no sobrescribe la base activa, no envía correo ni usa IA. Además de su integridad, comprobar el recorrido funcional aislado y medir tiempo. Repetir al cambiar claves/esquema y periódicamente; practicar pérdida completa del VPS.

7. Programar una ejecución diaria de `backup-external.sh` con `BACKUP_EXTERNAL_MOUNT` explícito, directorio `/opt/alquivo`, bloqueo y logs mínimos, y una alerta externa de fallo/ausencia. Un cron sin alerta no cierra este punto. Probar la alerta desmontando el destino de prueba: debe fallar, nunca escribir una «copia externa» en el disco local.
8. Aplicar y verificar el objetivo de conservación **30 días** usando Restic y el proveedor: revisión/dry-run antes de cualquier purga, última copia verificada y claves recuperables. No documentar «máximo 30» como hecho hasta medir su ejecución, incluidas instantáneas del proveedor. Revisar también caducidad si se interrumpe la tarea; las copias no se borran solas por una frase del aviso.
9. Ante restauración real, reaplicar supresiones posteriores, revocar sesiones restauradas y reconciliar eventos externos antes de abrir. Restringir la capacidad de borrar copias y usar instantáneas/protección independiente donde el producto lo permita: una copia separada con las mismas credenciales de borrado no garantiza protección frente a ransomware o compromiso del administrador.

Destino/cron/caducidad/alertas y restauración externa **no están configurados** por añadir estos archivos. La política definitiva se actualizará al probarlos.

## 6. Correo, acceso y aceptación: checklist en HTTPS

Usar cuentas propias de prueba y datos ficticios. Antes de abrir:

- [ ] Crear cuenta con consentimiento sin premarcar y versión legal final. Sin aceptación/versión obsoleta debe rechazarse. Sin verificar email puede gestionar su cartera; no debe quedar bloqueada en Configuración.
- [ ] Recibir y abrir verificación en `https://app.alquivo.com/verify-email`; comprobar cuenta correcta, firma, caducidad, otro navegador y enlace manipulado. La verificación no debe servir de login por sí sola.
- [ ] Recibir recuperación en `/reset-password`, cambiar contraseña y comprobar enlace caducado/reutilizado, cierre de otras sesiones y dispositivos recordados. MFA debe mantenerse.
- [ ] TOTP, correo y ambos: contraseña sola no da datos; código erróneo/caducado/reutilizado falla; ambos exige ambos; recordar dispositivo respeta 90 días y revocación.
- [ ] Ticket nuevo y respuesta del cliente notifican al equipo sin contenido privado; permisos de soporte requieren verificación + MFA. El cliente consulta respuestas en su ticket; no existe todavía aviso por email al cliente de cada respuesta.
- [ ] Confirmar SPF/DKIM/DMARC, recepción y remitente, rebotes, worker y fallos de cola. Los tests no prueban entrega a todos los proveedores de correo.
- [ ] Crear inmueble/contrato, registrar cobro parcial y gasto, subir documento/foto limpia, rechazar EICAR y scanner caído, descargar autorizado y denegar a otra cartera; exportar y eliminar cuenta/archivos.

## 7. IA: decisión de apertura, no activación automática

Los resultados de modelo previos siguen en [ai-evaluations.md](ai-evaluations.md): la reevaluación final de correcciones observó 45 PASS, 2 SAFE FAILURE y 0 DANGEROUS FAILURE en 47 ejecuciones, más 5 PASS de histórico. Son muestras sintéticas revisadas por Codex, **no revisión humana independiente ni garantía de comprensión futura**. No sumar esas fases como una tasa de precisión global.

1. Revisar tú el informe sintético (prompts, tools, argumentos, respuesta y propuesta). Comprobar deuda frente a pagado, San Nicolás+ciudad, Juan ambiguo, periodo/importe/teléfono ausente, histórico, prompt injection y datos ajenos. Marcar PASS / SAFE FAILURE / DANGEROUS FAILURE y motivo; no alterar resultados originales del modelo para aprobarlos.
2. Completar contrato/cuenta y riesgos de IA antes de enviar datos reales. Conservar activación voluntaria y revisión separada de documentos.
3. Sobre el entorno HTTPS aún restringido, verificar sesión/MFA, endpoint, preview, recuperación de run, confirmación separada, efecto exacto y repetición idempotente. «Sí» en chat no ejecuta; un fallo peligroso de escritura exige investigar antes de beta.
4. Si se decide hacer nuevas consultas reales sintéticas, autorizar **un presupuesto explícito adicional** antes. Esta preparación no utiliza proveedor ni da por renovados presupuestos agotados. Mantener Luna principal, Sol solo por error técnico, cuatro rondas, Astra apagado, Document AI simulado y límites/reservas.
5. Solo con decisión expresa y comprobaciones cerradas cambiar los flags públicos. Abrir 443 al público e invitar un grupo pequeño después, no al crear el VPS. Los 5 USD del techo mensual de la app son estimación preventiva, no saldo del proveedor ni garantía de coste cero por errores; revisar gasto/alertas externos también.

## Criterio de salida

No hace falta añadir nuevos módulos para esta beta. Antes de invitar con datos reales, cerrar: servidor HTTPS y controles, copia externa recuperada, correo/MFA/antivirus/cola, contratos/proveedores/plazos reales y revisión humana de IA. Generar la revisión legal final nueva **sin sobrescribir** el borrador archivado y desplegar frontend/backend juntos. Las personas de prueba no se importan desde desarrollo ni reciben aceptaciones inventadas. La revisión reduce riesgos; no certifica que no pueda haber incidentes ni problemas legales.
