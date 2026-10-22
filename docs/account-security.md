# Cuenta y doble factor — 22 de octubre de 2026

## Experiencia de usuario

- Configuración se divide en **Perfil, Seguridad, Plan y uso, Privacidad**. Las secciones conservan sus formularios al navegar entre ellas. Se admiten enlaces como `/settings?section=security`; los retornos de facturación siguen llevando a Plan y uso.
- La **nueva activación** del doble factor propone un código por correo, sin configurar previamente una aplicación. Requiere contraseña actual, dirección verificada y correo transaccional disponible. No se activa automáticamente en todas las cuentas ni se cambia el método de quienes ya tienen MFA.
- La aplicación de autenticación sigue disponible. «Correo y aplicación» exige ambos códigos al iniciar sesión. Es posible añadir el autenticador a una cuenta que inicialmente solo utiliza correo, sin desactivar su protección durante el proceso.
- «Recordar este dispositivo» es una elección explícita, inicialmente desmarcada, disponible al activar MFA y al completar el acceso. Recuerda **ese navegador durante 90 días**; no es una confianza por IP, ubicación o equipo completo. La contraseña sigue siendo necesaria. Otro navegador, cookies borradas, caducidad o revocación vuelven a exigir el código.
- Desde Seguridad se pueden consultar y revocar los navegadores recordados. Activar/cambiar/desactivar MFA y cambiar contraseña o dirección revoca las autorizaciones anteriores según el flujo existente. Los códigos de recuperación se muestran una sola vez y son de un solo uso.

## Contratos y protecciones

Los códigos de activación y de cambios de seguridad viven en la sesión del navegador, ligados a usuario, configuración actual y operación concreta. Solo se guarda su HMAC; caducan en cinco minutos, tienen límites de envío e intentos y un reenvío invalida el anterior. La configuración pendiente caduca a los diez minutos. La operación no se guarda sin la prueba correspondiente. No se incluyen códigos ni contraseñas en logs.

Cambiar el método, desactivar MFA o cambiar el correo exige contraseña y factor actual. Para cuentas con MFA por correo existe un código específico para la acción; no se exige un autenticador inexistente. El cambio de dirección liga la prueba a la nueva dirección, envía el código a la dirección actual y requiere verificar la nueva. Una cuenta de correo sin verificar conserva MFA: utiliza sus códigos de recuperación para entrar hasta verificar la nueva dirección; su sesión actual se conserva. No se cambia silenciosamente a TOTP ni se desactiva MFA.

La finalización del acceso devuelve explícitamente `challenge_complete: true` junto al usuario. Un paso intermedio de «ambos» devuelve `false` y los métodos restantes, sin usuario. El frontend rechaza respuestas incompatibles en vez de confundir éxito con otro desafío pendiente.

La ruta de recuperación solo acepta un código de recuperación auténtico. No acepta un TOTP etiquetado como recuperación, ni siquiera si se omite `method`; esto evita saltarse el correo en el modo «ambos» o utilizar un secreto TOTP antiguo en una cuenta de correo.

Nuevos endpoints, dentro de la sesión autenticada y del límite de configuración existente:

- `POST /api/v1/account/two-factor/setup/resend`
- `DELETE /api/v1/account/two-factor/setup`
- `POST /api/v1/account/two-factor/security-code`

`setup` acepta `method` opcional (por defecto `email`); `confirm` acepta `remember_device`. Se conservan CSRF, límites, recuperación, cifrado y aislamiento existentes. No hay migración nueva para este bloque: se reutilizan los campos de MFA y dispositivos.

## Verificación y despliegue

Pruebas nuevas con cuentas sintéticas, notificaciones simuladas y sin servicios externos: activación por defecto, error/caducidad/reenvío/cancelación, códigos ligados a la acción, alta de autenticador, ambos factores, recuperación, cambio de dirección y navegador de confianza de 90 días. El contrato de acceso y los enlaces de categorías tienen regresiones frontend.

Suite backend de esta versión aislada: **525 pruebas, 523 correctas, 3.678 aserciones y dos skips de evaluaciones live previstos**. No se han realizado inferencias ni enviado correos reales. Las trazas de coste de los tests pertenecen a transportes simulados, no a consumo externo.

Frontend: **196 pruebas correctas**; compilación cliente/SSR y comprobación SEO de diez páginas correctas. `git diff --check` sin errores. La entrega incluye exclusivamente acceso y configuración; conserva los textos y la versión legal de la aplicación anterior. No incluye los cambios de IA, revisión de conversaciones ni borradores legales pendientes del workspace principal.

**Implementación local, sin publicar en este bloque.** Desplegar frontend y backend juntos para mantener el contrato de finalización del acceso. Antes de dar por validado el entorno público, comprobar en HTTPS el envío real, activación, salida y nuevo acceso con/sin recordar navegador, revocación y cambio de categoría en móvil. No se han alterado las cuentas existentes, secretos ni MFA de producción.
