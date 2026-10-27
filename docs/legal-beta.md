# Textos legales de la beta

## Entrega aplicada — 27/10/2026, revisión r1

La revisión **2026-10-27-r1** está desplegada en la app y publicada en la landing. Release de código `050731bc131b7e4a4f2d8f16b7fb86217b79b37a`; archivo legal inmutable SHA-256 `b8eb87498730cf821f89b00d17ff128aaeab545e47bbdb44a4e955773dfcd9db`. Se han aplicado las páginas/enlaces y la conservación nativa de logs descrita en el texto. No se reescriben aceptaciones ni se abre el acceso público. Revisión editorial, no certificación jurídica ni verificación de contratos privados.

[Entrega, pruebas, comprobaciones y reversiones](deployment-legal-retention-20261027.md). Los checkpoints siguientes son históricos; donde contradigan este apartado, prevalece el estado de esta entrega.

## Aclaración del buzón de pruebas — 27/10/2026

El titular confirma que Gmail nunca tuvo consultas/documentos de clientes, solo pruebas. Nueva edición local **2026-10-27**: elimina su ficha como posible custodio de consultas de clientes y las referencias correspondientes en app/landing; conserva las revisiones anteriores y no modifica aceptaciones existentes. No se han leído ni eliminado mensajes. «Soporte cerrado» ya está creada en Zimbra; la revisión y supresión siguen siendo manuales. La información pública de logs sigue describiendo su estado real de rotación por tamaño: el cambio temporal preparado no se presenta como instalado. [Operación](privacy-operations.md), [logs](log-retention.md).

Nuevo archivo inmutable verificado: SHA-256 `c36b2c5c7152eb149b61e39bd86a9371e1cdf43d37c43c0a1f31f01104546f12`. **218 frontend y 34 regresiones backend/230 aserciones correctas**; la revisión `2026-10-26` permanece intacta. No hay despliegue ni push en este bloque.

## Edición cerrada para entrega — 26/10/2026

Revisión **2026-10-26**, frontend/backend sincronizados y nuevo archivo inmutable, sin modificar las revisiones ni las aceptaciones anteriores. Se cierra la redacción pública solicitada por el titular: aviso legal, condiciones gratuitas, privacidad, cookies, encargo y páginas de privacidad/aviso de la landing. Los datos identificativos completos siguen accesibles en las páginas legales, no en los mensajes visibles de los formularios. `reviewedForPublication=true` expresa **revisión editorial de estos textos**, no certificación jurídica, verificación de acuerdos privados ni autorización de apertura. El estado técnico/contractual se conserva por separado en [proveedores](provider-privacy-checklist.md).

Presentación y elecciones:

- Dos enlaces habituales: **Privacidad** e **Información legal**. Desde el aviso legal se llega a condiciones, cookies y encargo. En las páginas legales se mantiene también el índice completo, la impresión y el acceso sin sesión.
- Acceso, registro y recuperación tienen los dos enlaces, sin identidad/domicilio en el pie. Registro y soporte conservan una primera capa informativa en un desplegable nativo cerrado y accesible, no contenido eliminado ni texto ilegible.
- Registro: una casilla **no premarcada** acepta condiciones y encargo aplicable; el backend exige la versión vigente y conserva evidencia cifrada. No equivale a consentimiento publicitario, de IA o de mejora. No se reescriben aceptaciones existentes.
- Soporte: se retira la casilla obligatoria de «he leído privacidad», no el aviso informativo. Los endpoints admiten la ausencia del antiguo campo y siguen aceptando clientes anteriores; no crean una aceptación ni se cambian sesión, permisos, CSRF, antispam, escaneo o límites de adjuntos.
- La solicitud de aviso de beta conserva su consentimiento específico no premarcado. Activación de IA y participación opcional en mejora mantienen sus elecciones y versiones independientes. No se habilita ninguna función nueva.
- No se añade un banner de cookies publicitarias: no se han integrado analítica ni píxeles. La información técnica y las elecciones voluntarias de seguridad permanecen disponibles.

Los textos describen la revisión **manual** del correo y la rotación **por tamaño** de logs de contenedores; no presentan como ejecutadas purgas, contratos o ajustes privados no comprobados. OpenAI identifica la contraparte prevista en el DPA publicado para clientes establecidos en España/EEE (OpenAI Ireland Ltd.), sin confundirla con el emisor comunicado por el titular ni atribuir residencia europea o retención cero. Las consultas históricas de Gmail se describen condicionalmente, sin afirmar su existencia o eliminación.

**Entrega:** cambios locales preparados y probados; no desplegados ni subidos por esta tarea. Desplegar frontend/backend de la app juntos por el nuevo `terms_version`; Pages tiene su publicación independiente. No abrir el firewall, no fabricar aceptación de usuarios anteriores ni marcar como cerrados los contratos privados y la conservación operativa por haber terminado la redacción.

Validación: **616 pruebas backend correctas, 4.289 aserciones y dos skips de IA real**; **218 frontend correctas**, incluidas tres regresiones del render real de Vue para enlaces/desplegables/casillas y las pruebas HTTP de la landing con transporte antispam sintético. Build de app/HTML público y SEO de diez páginas correctos; archivo legal verificado (SHA-256 `ad4561bc6318b6874bf5c8432ca8b73c25d1bdb7b3483d0b76f9d71bc3ac064e`). La suite frontend requirió permisos para escuchar en localhost: no se alteran los tests para omitir esa comprobación. Sin navegador conectado para revisión visual/interactiva; no se presenta el render automatizado como QA visual. Cero inferencias reales, envíos de correo o accesos a datos de clientes.

Fuentes revisadas: [información por capas, AEPD](https://www.aepd.es/derechos-y-deberes/conoce-tus-derechos/derecho-de-informacion), [LSSI, arts. 10 y 22](https://www.boe.es/buscar/act.php?id=BOE-A-2002-13758), [cookies, AEPD](https://www.aepd.es/guias/guia-cookies.pdf), [DPA de OpenAI](https://openai.com/policies/data-processing-addendum/), [DPA Resend](https://resend.com/legal/dpa), [DPA Cloudflare](https://www.cloudflare.com/cloudflare-customer-dpa/), [condiciones OVHcloud](https://www.ovhcloud.com/es-es/terms-and-conditions/contracts/), [privacidad Hetzner](https://www.hetzner.com/legal/privacy-policy/). Los checkpoints siguientes son históricos donde contradigan esta edición.

## Publicación en la app — 23/10/2026, revisión r2

La revisión **2026-10-23-r2** está desplegada en el VPS con la entrega `2b552eff41bf053195c6feed8f0564b90380ed57`. Frontend/backend y archivo legal sincronizados; SHA-256 del snapshot: `f1362e76bc5d3dcab48c86f55b3c45b35e6e566c1f78ab59152128f6df727a15`. Se conserva el historial y no se reescriben aceptaciones. Las páginas públicas de la app responden por HTTPS y muestran proveedores efectivos, alcance de datos, ubicaciones conocidas y referencias oficiales. La información sobre calidad de IA aclara que solo aplica si la función ofrece su control de activación; no activa captura ni otras funciones.

**Publicación no equivale a aprobación jurídica:** `reviewedForPublication=false`. Faltan evidencias privadas de contratos/opciones efectivas y cierre operativo de conservación del buzón, histórico y logs de contenedores. La landing Cloudflare no se ha publicado en esta entrega: sus cambios están preparados en código. [Revisión y comprobaciones](php-security-review-20261023.md), [pendientes de proveedores](provider-privacy-checklist.md). Los apartados inferiores documentan checkpoints anteriores y no sustituyen este estado.

## Revisión 2026-10-23

Actualizados el alojamiento efectivo (OVH HISPANO S.L., Gravelines), copias externas (Hetzner Storage Box, Falkenstein), entidad del buzón y conservación configurada. La política de backup distingue ventana de 30 días y última copia válida; no promete retención cero en el proveedor de IA ni residencia exclusivamente europea. Se mantiene la identidad completa solo donde corresponde y el acceso por enlaces/desplegables, sin retirar información obligatoria ni casillas.

Frontend/backend sincronizados; nueva revisión archivada sin sobrescribir las anteriores. No se reescriben aceptaciones de cuentas existentes ni cambian versiones/permisos opcionales de IA. Se conserva `reviewedForPublication=false`: faltan evidencias privadas de proveedores, política operativa de buzón/histórico y límite temporal de logs de contenedores. El contenido preparado no equivale a una aprobación jurídica ni está desplegado. Detalle en [proveedores](provider-privacy-checklist.md).

## Historial

Revisión de trabajo: 22 de octubre de 2026. **Redacción e integración implementadas; no es una certificación jurídica ni una autorización para abrir la beta con los campos incompletos.**

Actualización `2026-10-22-r2`, presentación: la identidad y la información extensa de registro/soporte/lista de espera se mantienen en desplegables nativos «Información de privacidad», cerrados inicialmente y accesibles con teclado y sin JavaScript. La lista de espera y sus páginas legales mantienen sus enlaces. Se repliegan también los detalles de mejora de IA y simulación documental; siguen visibles sus elecciones y explicaciones esenciales. No se eliminan datos del titular de las páginas legales ni se cambian finalidades, versiones de los permisos de IA, casillas o controles de aceptación. Archivo nuevo sin sobrescribir `2026-10-22`; las aceptaciones existentes no se reescriben. Pruebas: 196 frontend correctas, build/SSR y SEO de diez páginas; 23 regresiones backend de evidencia legal y calidad/269 aserciones. Cambios locales pendientes de despliegue.

Actualización `2026-10-19`: OVHcloud Zimbra como receptor real de soporte, Cloudflare sin Email Routing y posibles mensajes históricos en Gmail. Se actualiza también la privacidad de la lista de espera y se captura su fuente en el archivo legal; finalidad y texto/versionado del consentimiento de la lista no cambian. Entidad contractual de OVH, acuerdos y conservación siguen pendientes; MFA de su área de cliente aún no activado según el titular. [Lista de cierre](provider-privacy-checklist.md). El aviso de IA no cambia por esta migración de correo.

Actualización `2026-10-05`: referencia breve del inmueble dentro del mismo chat y altas de inmueble/contacto/contrato en borrador en preview del administrador local. Se informa de los datos nuevos introducidos por el usuario (incluidos dirección, correo y teléfono) y la alternativa manual. El aviso del asistente cambia a `2026-10-05` y requiere nueva aceptación para utilizar IA, sin bloquear acceso/formularios ni fabricar aceptaciones. La simulación documental mantiene su aviso anterior. [Alcance y limitaciones](assistant-expansion.md).

La preparación actual y los pasos del titular están en [la guía de lanzamiento](beta-launch-guide.md). Se revisaron fuentes públicas de proveedores; no se aceptaron contratos en cuentas privadas ni se contrató servidor/copias. El titular sí contrató y configuró el nuevo buzón OVH. Los objetivos propuestos son copias diarias hasta 30 días, consultas por email hasta 12 meses desde su cierre (con excepciones justificadas), logs técnicos propios 14 días y auditoría propia 30 días. Su ejecución y las condiciones de registros propios de proveedores siguen pendientes.

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
- Resend sigue enviando; OVHcloud recibe el correo de soporte. Cloudflare presta web/D1/Turnstile/DNS, sin reenvío actual. Falta acreditar los acuerdos/entidades efectivos del buzón y el resto de cuentas, alojamiento, almacenamiento, copia externa y proveedor de IA antes de abrirla al público. Un proyecto creado en Hetzner no equivale a un servidor contratado. Indicar ubicación del tratamiento y accesos remotos, no solo la región del servidor.
- Garantías concretas para transferencias internacionales, revisadas contra los acuerdos vigentes: no dar por supuesta residencia europea ni ausencia de transferencias.
- Plazos y procedimientos reales de borrado de copias, buzón de soporte y registros de infraestructura. Deben coincidir con las tareas/proveedores contratados, y describir las excepciones justificadas de conservación.
- Verificar en producción los nombres y duración de las cookies: el texto refleja `APP_NAME=Alquivo`, `SESSION_LIFETIME=120` y 90 días para dispositivos de confianza. Si cambia la configuración, actualizar el inventario.
- Revisión final por un profesional de protección de datos/contratación ajustada a la identidad, actividad, público y proveedores reales. Aclarar el tratamiento aplicable a propietarios particulares y profesionales según sus circunstancias.
- Resolver las consultas/adjuntos históricos de Gmail, exportaciones y copias locales que aún se conserven; no presentarlo como receptor actual ni como Google Workspace. Aplicar retención en OVH y sus copias; activar MFA del área de cliente sin confundirlo con MFA del propio webmail.

`reviewedForPublication` permanece en `false`. El aviso visible de borrador solo desaparece cuando se completa la información exigida y se marca la revisión final. Este indicador **no bloquea por sí mismo el registro ni acredita cumplimiento**: no desplegar públicamente como servicio listo con los campos sin completar. Si la beta excluye IA, puede indicarse expresamente en su ficha de proveedor que está deshabilitada y no trata datos; antes de activarla hay que sustituirlo por la información contractual efectiva.

No se han inventado contratos ni datos de proveedores. La entrega de un correo de recuperación desde la aplicación fue confirmada por el destinatario; esto no verifica todos los tipos de correo. Aún no se ha contratado el servidor ni las copias externas y no se ha realizado revisión jurídica externa.

## Aceptación y versiones

La versión de borrador actual es `2026-10-19`, definida en frontend y `backend/config/legal.php`. Mantiene la beta de **50 inmuebles y 5 GB** solo con funciones públicas habilitadas y límites de IA. Distingue proveedores efectivos/previstos, conservación propuesta y almacenamiento API frente a registros de seguridad; no promete retención cero ni residencia europea de OpenAI. Los archivos anteriores, incluido `2026-10-05`, se conservan sin cambios. El registro exige `terms_version` vigente y `terms_accepted=true`; rechaza formularios antiguos para recargar/revisar, y guarda fecha, versión y evidencia mínima independiente. Desplegar frontend/backend juntos. La privacidad es información leída, no consentimiento universal/publicidad. La IA mantiene activación separada y su aviso anterior, sin cambios por esta migración de correo.

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
