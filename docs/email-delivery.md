# Correo transaccional de Alquivo

## Comprobación actual — 4 de octubre de 2026

**Resend aceptó un único correo técnico sintético** con asunto «Prueba tecnica de correo - Alquivo» a `soporte@alquivo.com`, enviado por el servicio Mail de Laravel en el Docker local. No contiene datos de clientes, enlaces, tokens ni un ticket real; no se ha solicitado un reset de una cuenta. La recepción sigue pendiente de confirmación humana: aceptación del transporte no equivale a entrega en bandeja ni a comprobar la cola o los flujos completos de cuenta.

Nuevas regresiones `ProductionMailLinksTest`: cinco pruebas/28 aserciones correctas, incluidas en la suite ordinaria y repetidas en PostgreSQL temporal. Recuperación, verificación firmada con caducidad y host, ticket y código MFA usan el contrato esperado con `https://app.alquivo.com`; notificaciones simuladas, sin envío real en esos tests. La plantilla pública apunta al subdominio de la app, no a la landing. **Todavía no existe servidor/HTTPS de aplicación** y no se editó el `.env` privado.

El titular confirma Cloudflare → Gmail gratuito como recepción actual. El procedimiento para pasar a un buzón profesional conservando dirección y Resend está en [la guía de lanzamiento](beta-launch-guide.md#2-cambiar-el-buzón-sin-perder-soportealquivocom). Ninguna compra o modificación MX/SPF/DKIM se ha realizado. El checklist HTTPS de esa guía sigue pendiente antes de invitar usuarios.

## Comprobación anterior — 28 de septiembre de 2026

Estado (28 de septiembre de 2026): dominio `alquivo.com` verificado en Resend según el titular. La aplicación tiene el cliente Resend y los flujos de verificación, recuperación, códigos de doble factor y avisos de tickets. **El correo saliente local está activado con Resend** y el remitente `soporte@alquivo.com`. Se solicitó la recuperación para una dirección de prueba del titular mediante la API de la aplicación, se generó la solicitud de restablecimiento y el titular confirmó haber recibido el mensaje «Recupera tu acceso a Alquivo». La configuración operativa debe permanecer en archivos privados fuera de Git.

## Revisión previa al envío a GitHub — 2 de octubre de 2026

Se retiró de `backend/.env.example` un valor con formato de clave Resend antes de hacer commit/push. La plantilla vuelve a dejar `RESEND_API_KEY` vacío y `tools/check-secrets.php` detecta ahora también ese formato, sin imprimir sus valores. La búsqueda de esa asignación en el historial del archivo no encontró commits previos. No se han modificado las claves del `.env` privado ni llamado a Resend.

**Acción pendiente del titular:** si el valor era una clave activa, revocarla en Resend y crear otra con permiso de envío limitado al dominio; guardarla únicamente en el entorno privado y recargar los servicios de correo cuando corresponda. No pegarla en el chat. La landing de lista de espera no utiliza Resend y no depende de esa clave.

## Activación sin compartir secretos

En `backend/.env` (Docker local también lo carga) y, más adelante, en el archivo privado `.env.production` del servidor:

```dotenv
MAIL_MAILER=resend
RESEND_API_KEY=re_...             # clave privada con permiso de envío
MAIL_FROM_ADDRESS=soporte@alquivo.com
MAIL_FROM_NAME=Alquivo
SUPPORT_EMAIL=soporte@alquivo.com
```

No pegar la clave en chats, documentación, capturas ni Git. El archivo `.env.production.example` deja las variables sin secretos. La comprobación `php artisan security:check` rechaza producción con Resend sin clave o remitente válido. En el entorno local actual, `MAIL_MAILER=resend`. En otros entornos, configurar la clave y el remitente antes de cambiar el transporte; hacerlo sin ellos rompe los envíos.

Al cambiar el entorno de Docker, reconstruir backend/worker/scheduler para incluir `resend/resend-php` y recrearlos para cargar las variables. Antes de publicar, configurar `APP_URL` y `FRONTEND_URL` al mismo dominio HTTPS real: los enlaces de recuperación y verificación no deben apuntar a localhost.

## Comprobación de extremo a extremo

1. Confirmar en Resend que `alquivo.com` sigue verificado y que SPF, DKIM y DMARC no presentan errores.
2. Con una dirección tuya distinta de soporte, crear una cuenta de prueba y comprobar la recepción, apertura y caducidad del enlace de verificación.
3. Solicitar recuperación de contraseña y comprobar que el enlace lleva a la app pública y deja cambiar la contraseña.
4. Tras verificar esa cuenta, probar el código por correo del doble factor y conservar los códigos de recuperación.
5. Crear un ticket y confirmar que llega a `soporte@alquivo.com` un aviso sin asunto, texto ni adjuntos privados del cliente. Verificar que el worker está funcionando y revisar fallos de cola.
6. Comprobar en Resend los envíos y rebotes. La clave actual tiene permiso de envío, no de lectura de eventos; la recepción humana confirmó solo el mensaje de recuperación probado.

**Pendiente:** probar que el enlace local permite fijar una contraseña nueva, los correos de verificación y segundo factor y el aviso de tickets. Los enlaces de la prueba local apuntan a `http://localhost:8080` y no servirán fuera de esta máquina. La recepción de `soporte@alquivo.com` utiliza Cloudflare Email Routing y llega a Gmail gratuito; Resend solo se usa para enviar desde la aplicación. Si se responde directamente desde Gmail a un mensaje reenviado, comprobar el remitente visible: puede ser la dirección de Gmail y no `soporte@alquivo.com`. El flujo de tickets permite contestar desde la aplicación.

## Privacidad y operación

Resend declara tratamiento principal en EE. UU. y cláusulas contractuales tipo en su [DPA](https://resend.com/legal/dpa). Cloudflare [reenvía el correo al buzón de destino](https://developers.cloudflare.com/email-service/configuration/email-routing-addresses/). El uso de Gmail gratuito como destino de mensajes de soporte, potencialmente con datos o adjuntos personales, sigue pendiente de revisión jurídica y de una política real de conservación; no debe presentarse como si tuviera el acuerdo empresarial de Google Workspace. [Google documenta ese acuerdo para Workspace](https://knowledge.workspace.google.com/admin/compliance/privacy-compliance-and-records-for-google-workspace-and-cloud-identity). Considerar un buzón empresarial con acuerdo de tratamiento antes de abrir soporte a clientes reales.

Los avisos de tickets al equipo no incluyen el contenido del ticket. El formulario de soporte antiguo sí envía directamente el mensaje y adjuntos por correo, aunque la interfaz pública actual usa tickets. Aún no se avisa por email a clientes cuando el equipo responde; deben consultar su ticket.
