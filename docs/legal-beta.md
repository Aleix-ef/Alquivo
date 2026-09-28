# Textos legales de la beta

Revisión de trabajo: 24 de septiembre de 2026. **Redacción e integración implementadas; no es una certificación jurídica ni una autorización para abrir la beta con los campos incompletos.**

## Documentos y mantenimiento

| Ruta | Contenido |
| --- | --- |
| `/legal` | Aviso legal, titular, contacto y propiedad intelectual. |
| `/terms` | Condiciones de beta gratuita, cuenta, límites, baja, reclamaciones y derechos. |
| `/privacy` | Responsables, finalidades/bases, tickets, IA opcional, conservación, destinatarios y derechos. |
| `/cookies` | Inventario de cookies/almacenamiento propio, duración y control por el usuario. |
| `/data-processing` | Acuerdo del artículo 28 RGPD para datos tratados por cuenta del propietario y relación de proveedores. |

El contenido está en `frontend/src/content/legal.js`. Los datos públicos específicos del negocio se completan en `frontend/src/content/legalOperator.js`. No hay que cambiar componentes ni configurar variables secretas para rellenarlos. Todo lo que se escriba en ese archivo se incluye en el HTML público: solo información destinada a publicación. La vista escapa los datos y no ejecuta HTML introducido en el contenido.

Las cinco páginas se generan como HTML estático, son accesibles sin iniciar sesión y conservan `noindex`. Se enlazan desde la landing, guías, acceso/registro e interior. Incluyen índice por apartados y estilos de impresión para guardar PDF desde el navegador. Los formularios de registro y soporte incluyen una primera capa informativa de privacidad.

## Datos imprescindibles pendientes

- Titular real, NIF/CIF y domicilio profesional/de contacto; datos registrales si corresponden. `Alquivo` y un correo no sustituyen la identificación del prestador.
- Entidades contratadas para alojamiento, almacenamiento, copia externa, correo y, antes de habilitar IA, la entidad contractual del proveedor. Indicar ubicación del tratamiento y accesos remotos, no solo la región del servidor.
- Garantías concretas para transferencias internacionales, revisadas contra los acuerdos vigentes: no dar por supuesta residencia europea ni ausencia de transferencias.
- Plazos y procedimientos reales de borrado de copias, buzón de soporte y registros de infraestructura. Deben coincidir con las tareas/proveedores contratados, y describir las excepciones justificadas de conservación.
- Verificar en producción los nombres y duración de las cookies: el texto refleja `APP_NAME=Alquivo`, `SESSION_LIFETIME=120` y 90 días para dispositivos de confianza. Si cambia la configuración, actualizar el inventario.
- Revisión final por un profesional de protección de datos/contratación ajustada a la identidad, actividad, público y proveedores reales. Aclarar el tratamiento aplicable a propietarios particulares y profesionales según sus circunstancias.

`reviewedForPublication` permanece en `false`. El aviso visible de borrador solo desaparece cuando se completa la información exigida y se marca la revisión final. Este indicador **no bloquea por sí mismo el registro ni acredita cumplimiento**: no desplegar públicamente como servicio listo con los campos sin completar. Si la beta excluye IA, puede indicarse expresamente en su ficha de proveedor que está deshabilitada y no trata datos; antes de activarla hay que sustituirlo por la información contractual efectiva.

No se han publicado nombres, NIF ni domicilios inventados. No se ha contratado servidor/correo ni realizado revisión jurídica externa.

## Aceptación y versiones

La versión `2026-09-24` se define en el contenido del frontend y en `backend/config/legal.php`. El registro envía `terms_version`; el servidor exige la versión vigente y `terms_accepted=true`. Rechaza formularios antiguos con un mensaje para recargar y revisar las condiciones. Guarda `terms_accepted_at` y `terms_version`. Backend y frontend deben desplegarse juntos. La privacidad se presenta como información leída, no como un consentimiento universal ni aceptación de publicidad. La IA mantiene su activación separada.

No se modifican las fechas/versiones de usuarios existentes ni se les atribuye una nueva aceptación. Antes de incorporar cuentas reales antiguas bajo estas condiciones, recabar su aceptación por un mecanismo documentado; todavía no se ha añadido un flujo de reaceptación para cuentas existentes. La demo y sus datos históricos no prueban aceptación contractual de un cliente.

Antes de publicar una versión definitiva, guardar una copia inmutable del contenido y datos del titular/proveedores que se publicaron, junto con la fecha de entrada en vigor y el commit de lanzamiento. Incrementar la versión si cambia ese contenido: **no reutilizar la versión de este borrador para unas condiciones distintas**. Los cambios relevantes se comunicarán y, si hace falta, se recabará nueva aceptación; no sobrescribir el historial de aceptación anterior.

## Compromisos que requieren operación

Estos textos son una propuesta concreta. Publicarlos supone cumplir también sus compromisos organizativos, no solo mostrar páginas:

- Avisar con al menos 30 días del cierre de beta o reducción sustancial, salvo obligación legal/urgencia de seguridad. No convertir gratis a pago sin contratación expresa.
- Comunicar cambios de subencargados con al menos 15 días para oposición y gestionar las objeciones antes del nuevo tratamiento.
- Proporcionar asistencia para derechos y devolución de datos. Hay CSV de inmuebles/contratos/movimientos y descarga de documentos; la devolución de otros datos se gestiona con soporte. No se promete un exportador completo que aún no existe.
- Responder a derechos dentro de los plazos legales, verificando identidad proporcionalmente, y mantener registro restringido de solicitudes/incidencias. No pedir DNI por defecto.
- Atender incidentes y avisar sin dilación indebida al propietario responsable cuando corresponda. Preparar contactos y procedimiento; no confundir el plazo de notificación del responsable a una autoridad con un permiso para retrasar el aviso del encargado.
- Compromisos de confidencialidad del personal, contratos de encargo con proveedores, evaluación de riesgos, control de accesos y comprobaciones de restauración/borrado.
- Documentar y reaplicar eliminaciones después de restaurar una copia. El código de reintentos de borrado de archivos no equivale a tener resuelto ese procedimiento de restauración.
- No afirmar un borrado completo de datos externos antes de comprobar correo, copias y proveedores. Los controles de IA apagada deben seguir apagados hasta verificar sus condiciones de tratamiento.

Los tickets se conservan 180 días desde la última actividad; la limpieza requiere scheduler operativo. Las notificaciones de tickets al equipo no llevan mensajes ni adjuntos. El formulario alternativo por correo sí remite el contenido al buzón. Esta diferencia aparece en la política.

## Fuentes oficiales consultadas

- [RGPD: Reglamento (UE) 2016/679](https://eur-lex.europa.eu/legal-content/ES/TXT/?uri=celex%3A32016R0679): principios, bases, información, derechos, encargo, seguridad y transferencias (arts. 5–6, 12–22, 28, 32–36, 44 y siguientes).
- [LSSI, Ley 34/2002, texto consolidado](https://www.boe.es/buscar/act.php?id=BOE-A-2002-13758): identificación del prestador, comunicaciones comerciales y almacenamiento en equipos (arts. 10, 21 y 22).
- [AEPD: guía para cumplir el deber de informar](https://www.aepd.es/guias/guia-modelo-clausula-informativa.pdf): presentación por capas y lenguaje comprensible.
- [AEPD: guía sobre cookies](https://www.aepd.es/guias/guia-cookies.pdf): tecnologías necesarias y elección para usos que requieran consentimiento.
- [AEPD: contenido del encargo de tratamiento](https://www.aepd.es/preguntas-frecuentes/2-tus-obligaciones-como-responsable-del-tratamiento/8-responsable-y-encargado-del-tratamiento/FAQ-0238-cual-seria-el-contenido-del-contrato-de-encargo-de-tratamiento).

Los textos son específicos para el funcionamiento revisado de Alquivo. No se ha añadido un banner de cookies de publicidad porque el código revisado no incorpora publicidad ni analítica de terceros; revisar esta conclusión al añadir proveedores, widgets o scripts.

## Verificación de esta integración

Pruebas de registro/beta/permisos: 18 pruebas y 142 aserciones. Frontend: 46 pruebas. Compilación y comprobación del HTML estático: 10 páginas, incluidas las cinco legales; títulos, anclas, enlaces, política de indexación y CSP verificados. La prueba de SEO comprueba también que backend y frontend utilizan la misma versión legal.

El plazo de 90 días del dispositivo de confianza limita su validez, pero no ejecuta un borrado programado de todos los registros caducados. El texto distingue esa validez de la conservación de metadatos. Las métricas de IA se limpian por meses: 12 meses completos más el actual.

Verificación local completada el 25 de septiembre: rutas y cabeceras comprobadas por HTTP tras reconstruir y actualizar Docker. Inspección visual de privacidad en escritorio y móvil; las cinco páginas legales y el registro se han cargado a 390 px sin desbordamiento horizontal ni excepciones JavaScript. La emulación de impresión confirma fondo blanco y ocultación de cabecera/soporte. No se ha realizado una revisión jurídica externa ni una comprobación de producción.
