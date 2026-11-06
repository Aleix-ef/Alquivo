# Panel privado de negocio — entrega del 6/11/2026

## Resultado

Publicado en **https://alquivo.com/admin** a las **20:13:36 UTC**.
Release de código `8957e3a55461e209e578ed0f66e9f94db88f0e0a`, basada en la versión
pública anterior `9f1fa59b062aa9f6d04310621356cfb2f1879d25`, mediante la rama
aislada `deploy/admin-business-20261106`. Los commits posteriores de evidencias
no cambian el código en ejecución. El árbol local principal conserva sus cambios
pendientes; no se desplegó entero.

La cuenta de Alquivo **soporte@alquivo.com**, ya existente/verificada/con MFA,
tiene la única autorización nueva en `business_admins`. Se ejecutó
`admin:account` sin crear usuario, contraseña ni cambiar `users.role`, soporte,
planes o cuotas. El permiso no permite leer contratos ni conversaciones ajenas.
Los endpoints requieren sesión de navegador y una prueba MFA administrativa
reciente. El acceso positivo con el segundo factor real lo realiza el titular:
no se han obtenido sus códigos ni simulado su autenticación en producción.

Usuarios, acceso por plan, activación actual, uso y metadatos IA disponibles.
MRR/cobros/cancelaciones SaaS, cambios históricos de plan, bajas, actividad útil,
retención y atribución no se inventan: se indican como no disponibles. No se
han añadido eventos de seguimiento, cookies de analítica ni proveedores.

## Validación

- Suite backend de la entrega: **624 correctas**, **4.291 aserciones**, dos pruebas
  de proveedor real omitidas. SQLite en memoria y transportes simulados.
- Diez regresiones del módulo repetidas en **PostgreSQL 17 aislado** con PHP
  8.3.36: **10 correctas, 115 aserciones**. Base efímera eliminada al terminar.
- **269 pruebas frontend** correctas; compilación cliente/SSR y comprobación SEO
  de 11 páginas públicas correctas. Prueba específica de ruta Nginx y noindex.
- Navegador en **1440, 390 y 320 px**, ocho secciones, verificación/bloqueo,
  denegación, ausencia de desbordamiento general y errores JavaScript. Repetido
  con los assets servidos por HTTPS. Todas las APIs se interceptaron con datos
  ficticios; esto prueba la interfaz, no sustituye la MFA humana real.
- Nginx candidato: `/admin`, `/admin/`, `/login` 200; ruta privada desconocida 404.
- HTTPS real en ambos dominios: portada/admin/login/salud 200; cuatro endpoints
  administrativos, cuenta e inmuebles **401 sin sesión**. Panel con CSP, HSTS,
  `X-Robots-Tag: noindex, nofollow`. No se añadió al sitemap.
- Capacidad administrativa positiva comprobada desde el backend; configuración
  técnica validada. No se probaron endpoints con sesiones de clientes.
- Árbol principal: pruebas nuevas backend correctas, build correcto y 277 pruebas
  frontend correctas antes de añadir la última regresión Nginx. No se presenta
  ese árbol con trabajo pendiente como la entrega pública.

Los fixtures y capturas son sintéticos. **Cero inferencias facturadas**, sin
documentos reales ni acceso a contenido de carteras o chats de clientes.

## Operación y reversión

Copias externas cifradas verificadas a las **20:04:15** y **20:11:09 UTC**. La
segunda precede a la activación definitiva. No se repitió un ensayo de restauración
en esta entrega; se conserva el procedimiento y las pruebas anteriores.

Migración aditiva `2026_11_06_000000_create_business_admins`: tabla de permisos e
índices de fechas, sin modificaciones de datos comerciales. Identidad del entorno
privado verificada por hash antes/después. Código/configuración de IA y sus flags,
precios, límites, dependencias, Compose y proxy preservados. El único ajuste de
Nginx añade `/admin` a la lista explícita de rutas SPA recargables.

Backend/worker/scheduler/web actualizados. Los contenedores de PostgreSQL, ClamAV
y proxy conservaron sus identificadores. Siete servicios en ejecución; monitor
ejecutado con `Result=success`; timers de monitor, copias, vigilancia de copia y
conservación activos; firewall activo. Sin cambios de DNS ni secretos.

Se realizó **una reversión automática**: la primera entrega tenía el componente
Vue pero faltaba su ruta directa en Nginx. El verificador detectó el 404, retiró
la autorización recién concedida, restauró imágenes/enlace anterior y reanudó
los controles. Se corrigió con regresión específica y comprobación del Nginx
candidato antes del segundo intento. No se borró la migración ni datos de clientes.

Checkpoint privado definitivo: `/opt/alquivo/shared/admin-business-20261106-r2`.
Contiene referencias de reversión, huella de entorno, log de construcción y marca
de activación. Imágenes anteriores conservadas como
`alquivo-admin-rollback:20261106-r2-{backend,worker,scheduler,web}`.
Reversión operativa: revocar el permiso nuevo si se abandona el módulo, restaurar
esas imágenes y el enlace anterior, recrear solo cuatro servicios y verificar
salud/monitor. No usar `down -v` ni borrar la tabla como paso de rollback.

SHA-256 del artefacto desplegado:
`7b6b65bd31910cb8529d78e1a35c9f5106b85feb675aa85158cb4541b6a67805`.

Estas comprobaciones reducen riesgos; no son garantía de invulnerabilidad ni
certificación jurídica. Definiciones y próximas fases en [admin-business.md](admin-business.md).
