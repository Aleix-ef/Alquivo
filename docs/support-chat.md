# Soporte humano en el mismo chat

Implementado localmente el 17 de septiembre de 2026. **No se ha activado IA de soporte.**

## Uso

- Clientes: **Ayuda y soporte** (`/support`). Crear una consulta con asunto y mensaje, adjuntar capturas/documentos y recibir las respuestas en esa misma conversación.
- Visitantes: botón flotante de soporte en las páginas públicas. Las conversaciones están ligadas a la sesión segura del navegador; no se recuperan escribiendo el mismo correo en otro navegador. El correo indicado no está verificado. Para conservar nuevas conversaciones entre dispositivos, iniciar sesión primero.
- Equipo: `/support/inbox`, o **Abrir bandeja del equipo** dentro de Ayuda y soporte para cuentas autorizadas. Filtrar por pendientes de soporte, respondidas o resueltas; abrir, responder y cerrar. Es una bandeja compartida, sin reparto automático entre agentes.
- Actualización cada 15 segundos mientras la pantalla/chat esté abierto y la pestaña visible. No se promete atención inmediata ni se muestra una presencia ficticia. La conversación sigue guardada aunque nadie esté conectado.
- No hay notificaciones push ni avisos por email de nuevas respuestas en esta fase. El usuario debe volver al chat; el equipo debe revisar la bandeja. El correo configurado sigue disponible como alternativa, y los endpoints del formulario anterior se conservan por compatibilidad.
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
- Hasta 100 mensajes por conversación y 20 conversaciones conservadas por usuario/sesión visitante, más límites de frecuencia. El límite de consultas nuevas comparte la protección existente del formulario. El chat no consume la cuota del asistente patrimonial.
- Los envíos llevan una clave de idempotencia para evitar duplicados al reintentar. Las identidades, autores y permisos no se aceptan desde el formulario. Los identificadores de conversaciones y las rutas físicas se generan en el servidor.
- Se eliminan conversaciones tras **180 días de inactividad** mediante `support:prune` diario a las 02:45, con borrado de archivos reintentable. Leer o sondear el chat no reinicia ese plazo. Un mensaje o cambio de estado sí lo reinicia. Borrar una cuenta programa también el borrado de los adjuntos de sus consultas.
- Los términos de privacidad describen este tratamiento, pero siguen sujetos a revisión profesional y a concretar responsable, proveedores y copias de seguridad antes del lanzamiento.

## Arquitectura y operación

- Dominio: `backend/app/Domain/Support` (modelos, autorización y escritura transaccional).
- Controlador/validación: `SupportChatController`, `SupportChatRequest`.
- Tablas nuevas, aditivas: `support_agents`, `support_conversations`, `support_messages`, `support_attachments`. No se convierten correos antiguos en conversaciones ni se inventa historial.
- Un único componente Vue `SupportChat` para cliente, visitante y equipo; la lógica de sesión, borradores, envíos y actualización vive en `useSupportChat`. Reutiliza el diseño, adjuntos y accesibilidad existentes. No hay un servidor adicional de WebSockets.
- La bandeja privada no se indexa y Nginx admite su recarga directa.
- Desplegar backend, frontend y scheduler con la migración. En local, el arranque habitual ejecuta las migraciones sin resetear datos. En producción aplicar el procedimiento normal de migración y reconstrucción.
- Respaldar base de datos, `APP_KEY`, almacén privado y claves de archivos. Las copias existentes incluyen las nuevas tablas y archivos; coordinar también su política de conservación.

Pruebas: `php artisan test --filter=SupportChatTest` desde backend; `npm test` y `npm run build` desde frontend. Cubren aislamiento entre usuarios y visitantes, permisos, MFA/verificación, respuestas, lectura, reapertura, idempotencia, cifrado, adjuntos, límites, caducidad y borrado de cuenta.

Comprobación HTTP local: `node tools/check-support-chat.mjs` desde la raíz, con Docker levantado. Comprueba sesión de visitante, CSRF, aislamiento de origen y protección de rutas sin crear mensajes ni cuentas. La suite completa de backend pasa con **174 pruebas y 904 aserciones**. Queda pendiente la comprobación visual y el recorrido manual con la cuenta real del equipo, una vez registrada y autorizada.

## Pendiente, deliberadamente

- **IA de soporte y base de conocimiento:** solo idea de futuro. No se envían mensajes ni archivos a un proveedor de IA.
- Avisos por correo/push, atención en directo con presencia, asignación de agentes y recuperación verificada de chats anónimos. No son necesarios para contestar dentro de la conversación, pero pueden añadirse si el volumen lo justifica.
