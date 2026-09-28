# Tickets de soporte humano

Implementado localmente y ampliado a tickets con avisos al equipo el 24 de septiembre de 2026. **No se ha activado IA de soporte.**

## Uso

- Clientes: **Ayuda y soporte** (`/support`). Abrir varios tickets con asunto, mensajes y adjuntos; ver las respuestas en cada ticket, filtrarlos por estado, resolverlos y reabrirlos al escribir de nuevo.
- Visitantes: botón flotante de soporte en las páginas públicas. Las conversaciones están ligadas a la sesión segura del navegador; no se recuperan escribiendo el mismo correo en otro navegador. El correo indicado no está verificado. Para conservar nuevas conversaciones entre dispositivos, iniciar sesión primero.
- Equipo: `/support/inbox`, o **Abrir bandeja del equipo** dentro de Ayuda y soporte para cuentas autorizadas. Filtrar por abiertos, por responder, en espera del cliente o resueltos; abrir, responder y cerrar. Es una bandeja compartida, sin reparto automático entre agentes.
- Actualización cada 15 segundos mientras la pantalla/chat esté abierto y la pestaña visible. No se promete atención inmediata ni se muestra una presencia ficticia. La conversación sigue guardada aunque nadie esté conectado.
- Con un transporte real de correo, cada ticket nuevo y cada mensaje posterior del cliente avisa a `SUPPORT_EMAIL`. El aviso solo contiene una referencia corta y el enlace a la bandeja, sin nombre, asunto, texto ni adjuntos. Se encola después de confirmar el mensaje; reintentar el mismo envío no duplica el aviso. Si el envío falla, el ticket permanece guardado y el worker registra el fallo para revisión. Con `MAIL_MAILER=log` no se encola ningún aviso y la bandeja muestra que el correo está desactivado. Todavía no hay avisos por correo a clientes ni notificaciones push; deben consultar su ticket para leer respuestas.
- El correo configurado sigue disponible como alternativa y los endpoints del formulario anterior se conservan por compatibilidad.
- Los borradores y archivos sin enviar solo viven en memoria: se mantienen al cambiar entre conversaciones dentro del componente, pero se pierden al recargar/salir. Los mensajes confirmados sí están guardados en el servidor.

## Activar la cuenta de soporte

Cuenta elegida por el propietario: **soporte@alquivo.com**. No existía al comprobarla; no se ha creado una contraseña ni habilitado ninguna cuenta automáticamente.

1. Registrar la cuenta, verificar su correo mediante el flujo normal y activar doble factor en Configuración. Guardar los códigos de recuperación en un lugar seguro.
2. Un operador autorizado del servidor concede acceso explícitamente:

   ```bash
   docker compose exec backend php artisan support:agent soporte@alquivo.com
   ```

3. Entrar con esa cuenta, completar el doble factor y abrir `/support/inbox`.
4. Para retirar permisos:

   ```bash
   docker compose exec backend php artisan support:agent soporte@alquivo.com --revoke
   ```

En producción utilizar su archivo Compose y entorno correspondientes. El comando no crea cuentas, no verifica correos ni desactiva MFA. Saber o registrar la dirección no concede permisos. Estos permisos se vinculan al ID de la cuenta; perder la verificación de correo o desactivar MFA bloquea la bandeja hasta resolverlo. La demo no recibe pertenencia a `support_agents`: por petición posterior del propietario puede acceder mediante el [rol de administrador local](local-admin.md), que no funciona fuera de local.

## Privacidad y límites

- Conversaciones de clientes aisladas por **usuario**, no por cartera. Los permisos del equipo se validan en cada endpoint (lista, historial, respuesta, lectura, cierre y descarga).
- Los agentes solo tienen acceso a consultas de soporte mediante estas rutas, no a la cartera del cliente.
- Los visitantes utilizan la sesión existente con cookie HttpOnly y CSRF; no se entregan enlaces públicos ni tokens de conversación en URLs o localStorage. Perder la sesión no convierte el correo declarado en una credencial de recuperación.
- Texto de mensajes, asunto, nombre y correo cifrados en base de datos con `APP_KEY`. Adjuntos privados cifrados con la clave independiente del almacén de archivos. No es cifrado de extremo a extremo: el servidor debe descifrar para mostrar el contenido a participantes autorizados.
- Hasta 3 adjuntos de 2 MB por mensaje, JPG/PNG/WebP/PDF; 20 MB por conversación. Validación de tipo/contenido y escáner antivirus existente, obligatorio en producción. Descarga autenticada/ligada a sesión, sin rutas públicas ni nombres originales potencialmente sensibles. El mantenimiento `vault:files` incluye estos adjuntos.
- Hasta 100 mensajes y 20 MB en adjuntos por ticket; hasta 20 tickets abiertos y 100 conservados por usuario/sesión visitante, más límites de frecuencia. Los tickets resueltos no consumen la cuota de abiertos. El límite de tickets nuevos comparte la protección existente del formulario. Soporte no consume la cuota del asistente patrimonial.
- Los envíos llevan una clave de idempotencia para evitar duplicados al reintentar. Las identidades, autores y permisos no se aceptan desde el formulario. Los identificadores de conversaciones y las rutas físicas se generan en el servidor.
- Se eliminan conversaciones tras **180 días de inactividad** mediante `support:prune` diario a las 02:45, con borrado de archivos reintentable. Leer o sondear el chat no reinicia ese plazo. Un mensaje o cambio de estado sí lo reinicia. Borrar una cuenta programa también el borrado de los adjuntos de sus consultas.
- Los términos de privacidad describen este tratamiento, pero siguen sujetos a revisión profesional y a concretar responsable, proveedores y copias de seguridad antes del lanzamiento.

## Arquitectura y operación

- Dominio: `backend/app/Domain/Support` (modelos, autorización y escritura transaccional).
- Controlador/validación: `SupportChatController`, `SupportChatRequest`.
- Tablas nuevas, aditivas: `support_agents`, `support_conversations`, `support_messages`, `support_attachments`. No se convierten correos antiguos en conversaciones ni se inventa historial.
- Un único componente Vue `SupportChat` para cliente, visitante y equipo; la lógica de sesión, borradores, envíos y actualización vive en `useSupportChat`. Reutiliza el diseño, adjuntos y accesibilidad existentes. No hay un servidor adicional de WebSockets.
- La bandeja privada no se indexa y Nginx admite su recarga directa.
- Desplegar backend, frontend, worker y scheduler. En local, el arranque habitual ejecuta las migraciones sin resetear datos. En producción aplicar el procedimiento normal de migración y reconstrucción. Para activar avisos, configurar `MAIL_MAILER` y un remitente reales, comprobar SPF/DKIM/DMARC, vigilar `queue:failed` y probar la entrega al buzón de soporte; no se ha enviado correo real con la configuración local.
- Respaldar base de datos, `APP_KEY`, almacén privado y claves de archivos. Las copias existentes incluyen las nuevas tablas y archivos; coordinar también su política de conservación.

Pruebas: `php artisan test tests/Feature/SupportChatTest.php tests/Feature/SupportTicketNotificationTest.php` desde backend; `npm test` y `npm run build` desde frontend. Cubren aislamiento, permisos, MFA/verificación, respuestas, lectura, reapertura, idempotencia, cifrado, adjuntos, límites, caducidad, borrado y avisos sin contenido privado.

Comprobación HTTP local: `node tools/check-support-chat.mjs` desde la raíz, con Docker levantado. Comprueba sesión de visitante, CSRF, aislamiento de origen y protección de rutas sin crear mensajes ni cuentas. Queda pendiente la comprobación visual, la entrega real de correo y el recorrido manual con la cuenta del equipo, una vez registrada y autorizada.

## Pendiente, deliberadamente

- **IA de soporte y base de conocimiento:** solo idea de futuro. No se envían mensajes ni archivos a un proveedor de IA.
- Avisos al cliente cuando responde soporte, notificaciones push, atención en directo con presencia, asignación de agentes y recuperación verificada de tickets anónimos. Pueden añadirse si el volumen lo justifica.
