# Textos legales de la beta

Revisión de trabajo: 2 de octubre de 2026. **Redacción e integración implementadas; no es una certificación jurídica ni una autorización para abrir la beta con los campos incompletos.**

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

- El titular ha comunicado su alta como autónomo y ha autorizado publicar su identidad, NIF, condición de persona física, domicilio y correo, ya incorporados. Confirmar que el domicilio facilitado es el de contacto que debe figurar públicamente y mantenerlo actualizado si cambia.
- Resend está identificado como proveedor de envío y Cloudflare como reenvío al buzón Gmail gratuito. Falta confirmar la entidad y condiciones efectivas del buzón, así como el alojamiento, almacenamiento, copia externa y la entidad contractual del proveedor de IA antes de abrirla al público. Un proyecto creado en Hetzner no equivale a un servidor contratado. Indicar ubicación del tratamiento y accesos remotos, no solo la región del servidor.
- Garantías concretas para transferencias internacionales, revisadas contra los acuerdos vigentes: no dar por supuesta residencia europea ni ausencia de transferencias.
- Plazos y procedimientos reales de borrado de copias, buzón de soporte y registros de infraestructura. Deben coincidir con las tareas/proveedores contratados, y describir las excepciones justificadas de conservación.
- Verificar en producción los nombres y duración de las cookies: el texto refleja `APP_NAME=Alquivo`, `SESSION_LIFETIME=120` y 90 días para dispositivos de confianza. Si cambia la configuración, actualizar el inventario.
- Revisión final por un profesional de protección de datos/contratación ajustada a la identidad, actividad, público y proveedores reales. Aclarar el tratamiento aplicable a propietarios particulares y profesionales según sus circunstancias.
- Revisar el uso de Gmail gratuito para consultas y adjuntos personales; no presentarlo como Google Workspace ni atribuirle su acuerdo empresarial de tratamiento. Valorar un buzón profesional antes de recibir documentos de clientes.

`reviewedForPublication` permanece en `false`. El aviso visible de borrador solo desaparece cuando se completa la información exigida y se marca la revisión final. Este indicador **no bloquea por sí mismo el registro ni acredita cumplimiento**: no desplegar públicamente como servicio listo con los campos sin completar. Si la beta excluye IA, puede indicarse expresamente en su ficha de proveedor que está deshabilitada y no trata datos; antes de activarla hay que sustituirlo por la información contractual efectiva.

No se han inventado contratos ni datos de proveedores. La entrega de un correo de recuperación desde la aplicación fue confirmada por el destinatario; esto no verifica todos los tipos de correo. Aún no se ha contratado el servidor ni las copias externas y no se ha realizado revisión jurídica externa.

## Aceptación y versiones

La versión de borrador actual es `2026-10-02`, definida en el frontend y en `backend/config/legal.php`. Actualiza la oferta de la beta a **50 inmuebles y 5 GB** de documentos y fotos, únicamente con funciones habilitadas para el público; las funciones en revisión no están incluidas y la IA conserva sus límites de uso. El archivo de la revisión anterior `2026-10-01-r2` se conserva sin cambios. El registro envía `terms_version`; el servidor exige la versión vigente y `terms_accepted=true`. Rechaza formularios antiguos con un mensaje para recargar y revisar las condiciones. Guarda `terms_accepted_at` y `terms_version`, y evidencia mínima independiente de esa aceptación. Backend y frontend deben desplegarse juntos. La privacidad se presenta como información leída, no como un consentimiento universal ni aceptación de publicidad. La IA mantiene su activación separada y su aviso no cambia en esta revisión.

No se modifican las fechas/versiones de usuarios existentes ni se les atribuye una nueva aceptación. Antes de incorporar cuentas reales antiguas bajo estas condiciones, recabar su aceptación por un mecanismo documentado; todavía no se ha añadido un flujo de reaceptación para cuentas existentes. La demo y sus datos históricos no prueban aceptación contractual de un cliente.

Antes de publicar una versión definitiva, guardar una copia inmutable del contenido y datos del titular/proveedores que se publicaron, junto con la fecha de entrada en vigor y el commit de lanzamiento. Incrementar la versión si cambia ese contenido: **no reutilizar la versión de este borrador para unas condiciones distintas**. Los cambios relevantes se comunicarán y, si hace falta, se recabará nueva aceptación; no sobrescribir el historial de aceptación anterior.

`node tools/archive-legal.mjs` guarda el contenido público, datos del operador, avisos de activación y versiones en `docs/legal-revisions/<version>.json`, con SHA-256. Rechaza sobrescribir una revisión cuyo contenido ha cambiado. `node tools/archive-legal.mjs --check` verifica coincidencia frontend/backend y archivo, también en CI. El archivo actual está marcado **borrador**, no aprobación jurídica. Incluir la revisión final en el commit/artefacto de lanzamiento y conservarlo fuera del servidor; un archivo local y una huella no son un depósito notarial ni almacenamiento WORM. Al completar proveedores o revisar un texto, incrementar versiones y generar otro archivo.

## Evidencia mínima de aceptación y retirada — 1 de octubre de 2026

`legal_acceptances` registra únicamente cuenta de origen (FK anulable), correo cifrado con `APP_KEY`, ámbito (`terms`, `assistant`, `document_ai`), acción, versión, fecha y caducidad. No contiene chats, notas, documentos, IP ni prompts; no hay endpoint ni acceso para soporte/IA. Solo se consulta por personal autorizado para acreditar el tratamiento o atender derechos/responsabilidades. Aceptación y estado se guardan en una transacción con bloqueo de usuario; repetir una aceptación vigente no fabrica eventos ni cambia la primera fecha. No se reconstruyen aceptaciones históricas sin evidencia.

Revocar chat/documentos sigue borrando sus contenidos/borradores y no borra operaciones confirmadas ni reinicia cuotas. La prueba mínima queda separada y caduca: política técnica provisional de **1.095 días** después de sustituir/retirar una aceptación o borrar la cuenta, configurable en código mediante `legal.evidence_retention_days`. La aceptación vigente se conserva mientras esté activa. No es un plazo legal universal ni una justificación para guardar carteras: debe revisarse con el asesor según bases, responsabilidades y minimización antes de publicar. El borrador de privacidad refleja ese plazo. `legal:prune` elimina evidencias caducadas cada día a las 03:00; requiere scheduler, alertas y caducidad coherente de copias.

Avisos nuevos: chat `2026-10-01`; simulación documental `documents-simulation-2026-10-01`. Los permisos anteriores no autorizan nuevas consultas/propuestas con la revisión nueva: será necesaria aceptación expresa si la función está disponible. No se cambia el gate global de IA ni se habilita Document AI real. Los usuarios de prueba anteriores no reciben una aceptación ficticia.

Fuentes: [RGPD, art. 7.1 y retirada, art. 7.3](https://www.boe.es/doue/2016/119/L00001-00088.pdf), [LOPDGDD, art. 32 sobre bloqueo](https://www.boe.es/buscar/act.php?id=BOE-A-2018-16673#a32). La evidencia técnica no determina por sí sola los plazos legales ni sustituye el procedimiento restringido de conservación/bloqueo que corresponda.

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
