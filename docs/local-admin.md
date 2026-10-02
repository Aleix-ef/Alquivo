# Administrador de pruebas local

La cuenta `demo@alquivo.test` puede recibir explícitamente el rol `admin`. Sus credenciales son conocidas: **no publicar esta cuenta como administradora ni utilizarla para soporte real**.

## Activación y retirada

Desde la raíz, con la aplicación local actualizada:

```bash
docker compose exec backend php artisan demo:admin
docker compose exec backend php artisan demo:admin --revoke
```

El comando solo funciona en `APP_ENV=local`. No crea la cuenta ni cambia contraseña, verificación, secreto MFA o consentimiento de IA. Exige correo verificado y doble factor activado. Los registros y cambios de perfil no pueden asignar el rol. Tras concederlo, recargar la aplicación para restaurar la sesión con los permisos nuevos.

## Qué permite

- Fiscalidad, aunque esté oculta durante la beta, manteniendo sus avisos de cobertura y límites de cálculo.
- Mostrar el asistente y probarlo con consentimiento explícito, siempre que esté configurado y habilitado el proveedor. No genera claves, saldo ni respuestas simuladas.
- Revisar el catálogo completo en Planes, sin activar cobros ni modificar suscripciones.
- Bandeja del equipo en `/support/inbox`: leer y responder consultas de soporte locales, incluidos sus adjuntos.
- Cartera de pruebas con prestaciones del Plan Fundador, informes fiscales y hasta 50 consultas de IA al mes, con los límites de tokens existentes. Durante la beta sus cuotas de inmuebles y almacenamiento son al menos las públicas: actualmente 50 inmuebles y 5 GB. Fuera de la beta vuelve a las cuotas del Plan Fundador (20 inmuebles y 2 GB). No es consumo ilimitado ni modifica las cuotas comerciales.

No permite acceder a contratos, documentos o inmuebles de otras carteras. Las validaciones, antivirus, cifrado, CSRF, límites de frecuencia y doble factor siguen activos. El permiso del equipo no implica acceso a la cartera de quien consulta: únicamente al contenido que envía a soporte.

## Aislamiento

`users.role` no es un campo asignable desde formularios. La capacidad calculada `local_admin` exige entorno local, rol explícito, correo verificado y MFA; no admite tokens personales como sustituto de la sesión de navegador. En producción/staging no tiene efecto, aunque se copie una base de datos local. No se añade la demo a `support_agents`, evitando trasladar un permiso permanente de soporte a producción.

Las ventajas de la cartera solo aplican en local a carteras cuyo propietario tiene el rol y cumple los requisitos. No se reescriben el plan contratado, el historial de Stripe ni los datos existentes. La configuración y el catálogo públicos siguen mostrando la beta normal; el frontend utiliza capacidades privadas separadas.

Para soporte real, registrar y proteger `soporte@alquivo.com`, y seguir [la activación de soporte](support-chat.md). El rol local no sustituye ese procedimiento.

## Verificación

`php artisan test --filter=LocalAdminTest`: acceso a módulos ocultos, catálogo público intacto, aislamiento entre carteras, bloqueo de pagos, configuración y consentimiento de IA, imposibilidad de autoasignar permisos, revocación y desactivación fuera de local.
