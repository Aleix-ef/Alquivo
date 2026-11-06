# Administración privada de Alquivo

Primera fase, 6 de noviembre de 2026. Ruta `/admin`; API `/api/v1/admin`.
No introduce analítica externa, cookies de marketing, cambios comerciales ni acceso
administrativo al contenido de contratos, documentos, contactos o conversaciones.

## Acceso

Solo la cuenta existente `soporte@alquivo.com`, con correo verificado y MFA activo,
puede recibir una autorización explícita en `business_admins`:

```bash
php artisan admin:account soporte@alquivo.com
php artisan admin:account soporte@alquivo.com --revoke
```

Ejecutar dentro del backend del entorno correcto. No crea usuarios ni cambia
`users.role`, permisos de soporte, planes o límites. El correo no concede acceso
por sí solo. Un cambio de correo bloquea el acceso; no transfiere la autorización
a la siguiente cuenta que registre esa dirección. Revocación y concesión nuevas
invalidan las pruebas MFA anteriores mediante una versión de autorización.

Requiere sesión Sanctum/CSRF del navegador, nunca Personal Access Tokens. Cada
entrada necesita una prueba administrativa reciente (30 minutos): correo,
autenticador o ambos según el método configurado. Los códigos de recuperación
existentes funcionan explícitamente y una sola vez. Recordar el dispositivo de
login no evita esta comprobación adicional. Cambio de contraseña/MFA, pérdida de
verificación, caducidad o revocación invalidan la prueba. No se modifica el MFA
del resto de la app. Peticiones con límites y respuestas privadas `no-store`.

`can_admin_business` es solo una indicación de navegación devuelta por el servidor:
no se acepta en formularios ni sustituye el middleware. Todo endpoint administrativo
comprueba permiso y prueba MFA. Accesos/grants/revocaciones quedan en la auditoría
existente, sin guardar búsquedas, emails ni contenido. Sin exportación masiva.

## Qué se mide realmente

| Sección | Disponible | Limitación |
| --- | --- | --- |
| Usuarios | Cuentas actuales, verificadas, altas conservadas por fecha; lista paginada de nombre/correo/alta/verificación | No reconstruye bajas ni cuentas ya eliminadas; no llama «activo» a quien se ha autenticado |
| Planes | Carteras por acceso efectivo reutilizando `PlanService` | Beta, gratuito y planes de catálogo diferenciados; un plan no acredita un cobro; cambios/conversiones históricos no disponibles |
| Activación | Propietarios con inmueble, alquiler activo, cobro registrado o gasto pagado | Fotografía de hitos independientes, no embudo secuencial ni actividad del periodo |
| Ingresos | No disponible | No hay libro conciliado de SaaS. Nunca usa rentas, precios de catálogo o suscripciones como ingresos cobrados |
| IA | Ejecuciones/usuarios/fallos, llamadas por modelo, tokens/caché, latencia media y costes estimados por modelo/usuario | Metadatos existentes, no prompts. Coste conocido, incompleto y reservado separados; no equivale a factura |
| Uso | Carteras, inmuebles vigentes, alquileres activos, documentos y almacenamiento de cuota | Reutiliza las sumas de documentos/fotos; no mide todo el disco ni inventa unidades alquilables |
| Retención | No disponible | No hay historial fiable de actividad útil por día |
| Captación | No disponible | No hay atribución de campañas/orígenes ni se añade seguimiento |

El periodo filtra altas y ejecuciones IA; las demás cifras son el estado actual.
Fechas de calendario de Europe/Madrid, ambos extremos inclusivos, convertidas a
UTC con fin exclusivo (incluido el cambio horario). Hasta 366 días por consulta.
Por defecto excluye **solo la propia cuenta administradora**; no presupone qué
otras cuentas son de pruebas. Marcar «Incluir mi cuenta» y aplicar para incluirla.
El listado de usuarios muestra registros conservados dentro del periodo seleccionado.

Las métricas IA siguen la conservación existente (12 meses por defecto). Borrar
cuentas/caducar datos reduce el histórico: se advierte expresamente. Las sumas de
ejecuciones y de llamadas son niveles distintos y no se suman entre sí. Los costes
se transmiten como nano-USD enteros en texto; el frontend solo los presenta en USD.

## Diseño y rendimiento

- Servicios `AdminAccess`, `AdminRange`, `BusinessMetrics`; controladores pequeños.
- Agregaciones SQL/EXISTS, índices temporales, máximo 25 usuarios por página,
  búsquedas parametrizadas con comodines escapados y rangos limitados.
- El reparto de planes recorre solo metadatos y reutiliza la regla de dominio.
  No carga carteras completas ni ejecuta conteos por cliente en producción.
- Solo el resumen agregado tiene caché de 60 segundos. Las tablas identificativas
  no se cachean y el middleware autoriza antes de leer la caché.
- Gráficos con un máximo de 60 barras; tablas con desplazamiento interno en móvil.
- Es un monolito pequeño: sin colas/eventos/resúmenes nuevos antes de necesitarlos.

## Siguientes fases (no incluidas)

1. Instrumentar, tras definir finalidad/conservación, actividad útil mínima agregada
   por día, bajas y cambios de plan; sin copiar contenido ni usar cookies externas.
2. Reconciliar cobros/facturas/reembolsos de Stripe antes de calcular ingresos/MRR.
3. Atribución voluntaria y proporcionada solo si se necesita y puede medirse bien.
4. Si las consultas crecen, snapshots diarios y límites de presupuesto de consulta.
5. Android puede reutilizar contratos JSON, pero necesitará diseño de autenticación
   propio con MFA y revocación. **No se habilitan tokens privilegiados ahora.**

## Entrega y reversión

Migración aditiva `2026_11_06_000000_create_business_admins.php`. Ejecutarla antes
de conceder el permiso; la capacidad falla cerrada si no existe la tabla. No hay
seeders administrativos ni concesiones automáticas a demo/soporte. En producción
se usa una entrega aislada basada en la versión pública, no el árbol local con
trabajo pendiente de IA. Copia externa y rollback de imágenes antes del cambio.
Revertir la aplicación no requiere borrar esta tabla/índices. Revocar la nueva
autorización si se abandona la entrega; no ejecutar migraciones destructivas.

Pruebas: suite ordinaria 624 correctas y 2 pruebas live omitidas; 269 frontend.
Las diez regresiones del módulo comprueban permiso/denegación/MFA/replay/revocación,
ausencia de cartera, filtros/DST, beta efectiva, privacidad, consultas acotadas y
costes sin duplicación. No usan proveedores reales ni consumen saldo de IA.
La revisión de navegador usa APIs interceptadas y datos ficticios: no sustituye
la primera entrada del titular con su segundo factor real.

Publicado el 6 de noviembre: [evidencia y límites de la entrega](admin-business-release-20261106.md).
