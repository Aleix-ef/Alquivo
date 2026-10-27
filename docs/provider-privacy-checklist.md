# Cierre de proveedores y privacidad antes de beta

## Revisión privada pendiente con web ya accesible — 27/10/2026

El titular autorizó abrir HTTPS mientras revisa estos acuerdos. Acceso público confirmado desde datos móviles, sin cambios de datos/IA/permisos; landing todavía en lista de espera. Esto **no completa** contratos ni opciones privadas por inferencia. [Guía concreta, por proveedor](provider-closeout-quick-guide.md), [alcance de apertura](public-web-opening-20261027.md). No se han firmado contratos ni inspeccionado facturas/cuentas privadas en esta tarea.

## Publicación y conservación comprobadas — 27/10/2026

La revisión legal `2026-10-27-r1` se ha desplegado en app y landing. Se ha instalado/verificado `journald` para los siete servicios con ventana de 30 días, límite de 256 MB y tarea horaria; ya no se considera pendiente su migración temporal. Monitor, copias y restricción de acceso siguen activos. [Entrega y comprobaciones](deployment-legal-retention-20261027.md).

Este avance no acredita los acuerdos de cuentas privadas ni una supresión de correo. Gmail solo tuvo pruebas según el titular; Zimbra se revisa manualmente. Los apartados siguientes conservan sus checkpoints históricos y no sustituyen este estado actualizado.

## Correo aclarado y conservación preparada — 27/10/2026

El titular confirma que Gmail solo tuvo pruebas: no hay consultas ni documentos de clientes que migrar/eliminar de ese buzón. No se ha inspeccionado ni borrado correo. Zimbra ya tiene «Soporte cerrado», según el titular; queda adoptada la organización prevista para revisar consultas cerradas, sin afirmar que exista borrado automático o una purga ya ejecutada. [Procedimiento manual](privacy-operations.md).

La revisión editorial local **2026-10-27** retira la ficha condicional de Google y las referencias de historial de clientes en Gmail de app/landing. No borra archivos legales anteriores ni modifica aceptaciones existentes. El canal Gmail para administración/avisos técnicos sigue siendo independiente del soporte de clientes.

Consulta SSH de solo lectura: app saludable, siete servicios activos, logs `json-file`/`local` con límites por tamaño. El cambio a journal con caducidad temporal está preparado para una entrega controlada; **no está instalado** y no se da el punto por cerrado. [Configuración, validación y aplicación](log-retention.md). Las comprobaciones contractuales de proveedores no cambian por esta confirmación.

## Redacción pública cerrada; operación y cuentas privadas separadas — 26/10/2026

Preparada la revisión editorial **2026-10-26** de app y landing, sin desplegar ni abrir el servicio. Ya no se presentan campos completos como borrador ni tareas internas como contenido contractual. Esto no da por aceptados acuerdos, aplicada la política del buzón ni implantada caducidad temporal de los logs de contenedores. Estos extremos siguen abiertos según la evidencia real. [Alcance, elecciones y publicación](legal-beta.md).

El DPA público vigente de OpenAI (efectivo 01/01/2026) identifica **OpenAI Ireland Ltd. para clientes establecidos en el EEE/Suiza** y se incorpora al acuerdo de servicios. Por ello la ficha pública identifica esa contraparte prevista para España, en lugar de dar por verificada OpenAI OpCo, LLC a partir del nombre comunicado. No se solicita una firma separada por defecto ni se acredita la cuenta privada por consultar el DPA. [DPA](https://openai.com/policies/data-processing-addendum/), [acuerdo, apartado 5.3](https://openai.com/policies/services-agreement/).

## Comprobación de OpenAI aportada por el titular — 26/10/2026

- **Entidad comunicada:** OpenAI OpCo, LLC. Se registra como información aportada por el titular; no se ha consultado la factura ni el contrato de la cuenta API. Sigue pendiente contrastar la contraparte y el DPA aplicable, sin sustituirlos por los términos de ChatGPT personal.
- **Captura de Data controls → Sharing:** las tres opciones aparecen en `Disabled`: compartir feedback, datos de evaluaciones/fine-tuning y entradas/salidas. La captura no acredita que se hayan guardado cambios ni identifica por sí sola la organización/proyecto de producción. Confirmar que es la organización usada por Alquivo y, si se editaron los ajustes, guardar y recargar para comprobar su persistencia.
- Mantener desactivada la compartición voluntaria en la organización de producción; no activarla para obtener los tokens gratuitos ofrecidos en esa pantalla. No se han cambiado ajustes privados ni hecho inferencias en este bloque.
- **Captura posterior de Data retention:** `Audit logging` muestra el botón de activación y `API call logging` tiene seleccionado `Enabled per call`, no `Disabled`. No aparecen controles que acrediten ZDR/MAM ni residencia europea. No se interpreta la presencia de esta pestaña como aprobación de controles especiales.
- **Comprobación del código, sin llamadas API ni acceso al VPS:** `OpenAIProvider::generate()` fuerza `store=false` al enviar `/responses`, incluso si el llamante pasa `true`. Se ha contrastado tanto el árbol de trabajo como el código del commit de la última entrega registrada (`ae5309f4cf17142ec0ad675e756f855dbe2428d4`). Existe regresión específica en `AiInfrastructureTest::test_provider_forces_store_false_and_sanitizes_error_bodies`; no se ha vuelto a ejecutar en esta revisión documental. Esto evita el almacenamiento ordinario de respuestas para recuperación, pero no elimina registros de seguridad ni las excepciones de conservación del proveedor.
- **Recomendación pendiente de confirmación del titular:** seleccionar `Disabled` en `API call logging` para minimizar el registro voluntario en la organización de producción y guardar si se solicita. Se mantiene `store=false` en código, pues la propia pantalla advierte del almacenamiento por defecto de Responses. No se ha modificado este ajuste ni se afirma que la selección actual implique una fuga de datos.
- No es necesario activar `Audit logging` para esta comprobación; registra acciones/configuración de la organización, no equivale al historial de chats. La pantalla advierte de que después requiere contactar con OpenAI para desactivarlo: no se activa como un paso rutinario.
- **Pendiente documental:** conservar los acuerdos aplicables y el DPA, con su evidencia de incorporación a la cuenta API, en el archivo privado. La captura no acredita aceptación contractual. No se necesitan claves, datos bancarios ni documentos de usuarios.
- Según la [documentación oficial de controles de datos](https://developers.openai.com/api/docs/guides/your-data), la API no usa datos para entrenamiento por defecto salvo adhesión voluntaria. Esto no equivale a retención cero: existen registros de prevención de abusos, generalmente hasta 30 días, con las excepciones indicadas por el proveedor. No se acredita ZDR/MAM ni residencia UE por esta captura.

**Alcance:** avance del inventario, no aprobación jurídica ni apertura de beta. No se cambian textos legales publicados, aceptaciones o `reviewedForPublication`. Los estados técnicos anteriores que indican «clave de producción ausente / IA apagada» son históricos: la IA se activó posteriormente para pruebas restringidas, como recoge el [checklist vigente](beta-opening-checklist.md); no se ha vuelto a comprobar el VPS en esta revisión documental.

## Estado posterior al despliegue — 23/10/2026

La revisión legal **2026-10-23-r2** está publicada en **la aplicación del VPS**, entrega `2b552eff41bf053195c6feed8f0564b90380ed57`. Añade categorías de datos y alcance por proveedor, ubicaciones conocidas y enlaces a documentación oficial; distingue servicios activos, funciones opcionales apagadas e historial Gmail. Las referencias públicas no prueban la aceptación contractual de las cuentas privadas. `reviewedForPublication=false`: siguen pendientes las evidencias privadas y la aplicación de la conservación del buzón/histórico y del límite temporal de logs de contenedores.

Copia externa previa a la entrega: **23/10, 16:22:45 UTC**, completada con integridad comprobada por el servicio. No se repite una restauración en este bloque ni se modifica la retención. La landing de Cloudflare requiere publicación independiente; no hubo push ni cambio de Pages. No se abre la beta ni se activa IA. [Informe técnico y alcance](php-security-review-20261023.md). El bloque siguiente es el checkpoint previo a esta publicación.

## Actualización contrastada — 23/10/2026

Este estado sustituye las previsiones de infraestructura del bloque histórico de abajo. Textos locales y archivo legal actualizados a **2026-10-23**, todavía sin aprobación de publicación. No se han aceptado contratos de proveedores en nombre del titular ni publicado estas páginas.

- **Alojamiento:** VPS de OVHcloud en **Gravelines, Francia**, ya operativo y restringido por red. No Hetzner Cloud. Entidad OVH HISPANO S.L. identificada por el titular al pie de su factura.
- **Copias externas:** Hetzner Storage Box en **Falkenstein, Alemania**, cifrado previo al envío y transporte cifrado, copia diaria de base/archivos y restauración aislada comprobada. Ventana de 30 días **más la última copia válida**: `--keep-last 1` impide prometer un máximo absoluto de 30 días si cesan las copias. Última ejecución correcta comprobada: 23/10, 01:19:43 UTC. Material de recuperación descargado y comprobado por el titular el 21/10; no se afirma una segunda custodia independiente.
- **Buzón:** OVH HISPANO S.L., Zimbra Starter, recepción/respuesta confirmadas. El DPA aportado es el de 17/10/2025; conservar en privado la evidencia de su incorporación al pedido. Cloudflare ya no reenvía soporte a Gmail; el historial anterior no se da por eliminado.
- **Logs:** configuración efectiva de Laravel comprobada por SSH sin leer logs: canal `daily`, 14 días; seguridad, 30. La rotación de contenedores es por tamaño y aún no prueba un límite temporal. No extrapolar los 14 días a todos los registros del VPS/proveedores.
- **Soporte en la aplicación:** cuenta verificada y MFA activo, autorizada con el comando existente para `/support/inbox`. No es un permiso de superadministrador ni de revisión de conversaciones.
- **OpenAI:** pruebas nuevas únicamente sintéticas/locales. En el último control del VPS la clave de producción no está configurada y la IA sigue apagada; no hay Document AI real. No se acredita región europea, retención cero ni opciones privadas de compartición de datos.

### Cierre que aún depende del titular

1. Conservar acuerdos/condiciones aplicables y evidencia de incorporación de OVH, Hetzner, Cloudflare, Resend y OpenAI; confirmar entidad/opciones privadas de la cuenta API y garantías de transferencia. Los enlaces públicos no acreditan por sí solos todos esos extremos.
2. Aplicar y documentar la revisión de conservación del buzón (12 meses tras cierre) y resolver mensajes históricos/copiados de Gmail. No existe una purga automática del buzón implementada por esta tarea.
3. Fijar/comprobar caducidad de logs operativos, completar registro privado y evaluación de riesgos y revisar los textos con los servicios reales. Después aprobar una revisión nueva, desplegarla coherentemente y comprobar aceptación/HTTPS. No modificar el archivo histórico ni las aceptaciones de usuarios anteriores.

Fuentes contrastadas: [AEPD: relación responsable/encargado](https://www.aepd.es/preguntas-frecuentes/2-tus-obligaciones-como-responsable-del-tratamiento/8-responsable-y-encargado-del-tratamiento), [contratos OVHcloud](https://www.ovhcloud.com/es-es/terms-and-conditions/contracts/), [DPA Resend](https://resend.com/legal/dpa), [controles de datos OpenAI](https://developers.openai.com/api/docs/guides/your-data). La revisión local de textos no sustituye la incorporación contractual ni una revisión jurídica ajustada a la actividad.

## Registro histórico — 19/10/2026

Estado de trabajo: **19 de octubre de 2026**. No es una certificación jurídica ni una aceptación de contratos en nombre del titular. La nueva revisión de textos `2026-10-19` sigue en borrador. Las evidencias privadas se guardan fuera del repositorio y del chat, en un archivo protegido; aquí no se guardan facturas completas, documentos de clientes, credenciales ni códigos de recuperación.

## Comprobado y pendiente

| Servicio | Estado conocido | Qué falta acreditar |
| --- | --- | --- |
| OVHcloud Zimbra Starter | Contratado por el titular; acceso al buzón y pruebas de recepción/respuesta confirmados. `redes@alquivo.com` preparado como alias. MX/SPF, dos CNAME DKIM y SRV comprobados públicamente. **MFA del área de cliente activado y recuperación guardada**, según el titular. Emisor confirmado por el titular: **OVH HISPANO S.L.**; DPA aportado de 17/10/2025 y contrastado con la fuente oficial. | Conservar evidencia de los acuerdos incorporados al pedido. Verificar acceso/MFA del buzón por separado, conservación, copias y terceros aplicables al filtrado de correo. La documentación pública no acredita la configuración privada ni la aceptación de la cuenta. |
| Cloudflare | Landing, D1 de interesados, Turnstile y DNS operativos. Email Routing dejó de utilizarse; sus MX se retiraron. | Acuerdo aplicable a la cuenta, subencargados, permisos/MFA y ubicación real de D1; una región propuesta no acredita su configuración. |
| Resend | Envíos de la app ya integrados; registros de envío y DKIM conservados. Recuperación recibida en una prueba anterior. Fuentes públicas de DPA, subencargados, conservación, MFA y claves revisadas el 19/10/2026. | Descargar el DPA de la cuenta, confirmar MFA/recuperación, miembros y permisos de la clave. Contrastar retención/configuración y probar los flujos de producción; no se ha accedido a la cuenta ni enviado correo en esta revisión. |
| OpenAI API | Proyecto existente y evaluación sintética anterior. No se hacen inferencias en este bloque. | Entidad contractual, acuerdo y garantías aplicables, opciones de compartición/retención de la cuenta y revisión humana antes de apertura. No se activa IA pública ni análisis documental real. |
| Google/Gmail | Ya no recibe correo nuevo de soporte. Puede seguir siendo cuenta administrativa y conservar consultas anteriores. | Revisar mensajes/adjuntos históricos, exportaciones y clientes locales; no declarar que se han borrado ni aplicar a Gmail gratuito el acuerdo de Workspace. |
| Hetzner / copias externas | Solo proyecto de alojamiento creado; sin VPS ni destino externo contratados según el estado comunicado. | Contratación, entidad/región, acuerdos, acceso, retención y recuperación real. No hace falta pagar ahora para documentar los servicios que ya existen. |
| GitHub | Código; no es almacén de documentos ni carteras. Primera entrega etiquetada `v0.1.0-beta.1`. | Accesos/MFA y futura clave de despliegue de solo lectura; no subir la carpeta privada de evidencias. |
| Stripe | Pagos de beta desactivados. | Revisar aparte antes de cobrar; el flag local no cancela suscripciones o enlaces antiguos externos. |

Una factura acredita una compra, no todos los términos del tratamiento. Un enlace público al DPA tampoco demuestra por sí solo qué contrato está incorporado a la cuenta. La aceptación puede constar electrónicamente; no inventar una firma manuscrita si el proveedor incorpora el acuerdo a sus condiciones. La [AEPD explica la necesidad de un encargo vinculante y su contenido](https://www.aepd.es/preguntas-frecuentes/2-tus-obligaciones-como-responsable-del-tratamiento/8-responsable-y-encargado-del-tratamiento/FAQ-0238-cual-seria-el-contenido-del-contrato-de-encargo-de-tratamiento).

## 1. OVH: avances confirmados y documentación pendiente

1. **Completado según el titular el 19/10/2026:** MFA del área de cliente activado y recuperación guardada. No se han pedido ni leído códigos, secretos o capturas del QR. Mantener los medios de recuperación fuera de Git, del chat y de la dependencia exclusiva del buzón protegido. [Guía oficial](https://docs.ovhcloud.com/es/guides/account-and-service-management/account-information/secure-ovhcloud-account-with-2fa).
2. Esa protección corresponde al **área de cliente**, no acredita MFA en el webmail ni en IMAP/SMTP. Para el buzón: contraseña exclusiva y fuerte, dispositivos/clientes conocidos y sin reenvíos a cuentas personales. No habilitar protocolos o accesos adicionales innecesarios.
3. **Completado según el titular:** el proveedor que aparece al pie de la factura es **OVH HISPANO S.L.**, NIF **B83834747**. No se ha accedido a la factura completa. El titular aportó el texto del DPA de 17/10/2025, que identifica a OVH Hispano SL y declara formar parte del contrato. Se ha contrastado esa versión con el PDF enlazado desde el catálogo oficial; sigue pendiente conservar evidencia de su incorporación al pedido concreto.
4. Desde la cuenta/pedido conservar condiciones generales, particulares de Zimbra y acuerdo de tratamiento aplicables, versión/fecha, subencargados y evidencia de su incorporación. El [catálogo oficial de contratos](https://www.ovhcloud.com/es-es/terms-and-conditions/contracts/) permite localizar estos documentos; contrastarlos con la contratación efectiva, no asumir que un PDF público es el aceptado. Si falta evidencia, consultarlo con el soporte del proveedor.
5. Registrar almacenamiento, acceso de soporte, ubicaciones de subencargados, conservación de mensajes eliminados/copias y procedimiento de devolución/baja. [OVH publica almacenamiento del buzón en Francia](https://www.ovhcloud.com/es-es/emails/); eso no permite afirmar ausencia de accesos externos, cifrado de extremo a extremo o cumplimiento automático de todo Alquivo.

No se cambian más MX, remitente o transporte de Laravel para estas tareas. La app continúa usando Resend, no la contraseña de Zimbra.

### Documentación pública contrastada el 19/10/2026

- [DPA, 17/10/2025](https://contract.eu.ovhapis.com/1.0/pdf/OVH_Data_Protection_Agreement-es.pdf): regula instrucciones, confidencialidad, seguridad, incidentes, derechos, baja y subencargados. Admite acceso remoto; no acredita tratamiento exclusivamente francés.
- [Condiciones de Zimbra, 20/01/2025](https://contract.eu.ovhapis.com/1.0/pdf/Zimbra-es.pdf): las copias técnicas no son un backup recuperable garantizado para el cliente. Tras finalizar el servicio, los contenidos quedan inaccesibles y se conservan 15 días sin garantía de recuperación: exportar antes de la baja. Existen análisis antispam/antimalware y tratamientos de contenido/metadatos por colaboradores. Comprobar terceros y plazos aplicables al servicio efectivo; estos supuestos no son la política ordinaria del buzón. Las condiciones requieren doble factor: comprobar su cobertura en las cuentas de correo, no darla por acreditada por el MFA del área de cliente.
- [Subencargados, 16/10/2025](https://contract.eu.ovhapis.com/1.0/pdf/OVH_Sub_processors-es.pdf): contiene entidades del grupo en la UE y, con el alcance indicado, Reino Unido/Canadá; otros países dependen del servicio. Remite a condiciones particulares para terceros externos. La consulta no acredita acceso efectivo de cada entidad a este buzón ni autoriza a afirmar «todo en Francia». El PDF se pudo revisar mediante descarga pública, aunque el lector web falló con su redirección.

Guardar los originales aplicables y la evidencia del pedido en el archivo privado. No se han aceptado términos ni creado copias de correos de usuarios. Los domicilios de OVH difieren entre algunos documentos/versiones; conservar la factura efectiva sin sustituir su dirección por una tomada de un PDF genérico.

Si el pedido no identifica los documentos o el filtrado no detalla terceros/retención/MFA, solicitar a OVH, sin adjuntar mensajes de clientes: «Para mi servicio Zimbra Starter, ¿podéis confirmar las versiones de las condiciones generales, particulares y DPA incorporadas a mi pedido, los subencargados y países aplicables —incluido el filtrado—, los plazos de eliminación/copias técnicas y las opciones de MFA para el buzón?». No asumir que todas esas respuestas faltan en la cuenta: revisarla antes de abrir un ticket.

El borrador legal archivado `2026-10-19` permanece intacto y todavía dice entidad pendiente. Esta confirmación se incorporará a la próxima revisión versionada; no sobrescribir el archivo ni marcarlo como aprobado por este avance.

## 2. Después: Cloudflare, Resend y OpenAI, una cuenta cada vez

### Cloudflare

- Comprobar identidad profesional, accesos autorizados/MFA y qué servicios se usan: Pages/Functions, D1, Turnstile, DNS y dominio. Email Routing no figura como receptor actual.
- Conservar las condiciones aplicables a esa cuenta y el [DPA de Cloudflare](https://www.cloudflare.com/cloudflare-customer-dpa/), con su evidencia de incorporación; revisar la lista de subencargados y garantías de transferencia. Un plan gratuito no equivale por sí solo a ausencia ni a suficiencia del acuerdo.
- Verificar en el panel la jurisdicción/ubicación efectiva de D1 sin recrear la base. La guía propone UE, pero no se ha inspeccionado la cuenta. No afirmar residencia europea de todos los servicios por el lugar de D1.
- Registrar quién puede leer/exportar la lista y cómo se aplican bajas/caducidad y copias del proveedor. Las solicitudes no se envían automáticamente por correo.

**Siguiente comprobación del titular, sin cambiar DNS ni crear bases:**

1. Perfil → **My Profile → Authentication**: confirmar MFA y recuperación independiente. Si ya están activos, no regenerar códigos innecesariamente. [Instrucciones oficiales](https://developers.cloudflare.com/fundamentals/user-profiles/2fa/).
2. En **Storage & databases → D1 SQL Database**, abrir la base que usa la lista de espera y consultar su información/configuración. Anotar jurisdicción y ubicación si se muestran; si no aparecen, dejar pendiente, sin ejecutar consultas de suscriptores ni compartirlos. Una preferencia `weur` no equivale a una restricción `eu`, y la jurisdicción no se cambia sobre una base existente. No recrearla ni migrarla por esta comprobación. [Alcance de las ubicaciones D1](https://developers.cloudflare.com/d1/configuration/data-location/).
3. Conservar condiciones y DPA aplicables a la cuenta, versión y evidencia de incorporación. El DPA público consultado es **v6.4, efectivo 03/04/2026**, y se vincula al acuerdo principal; su consulta no acredita una aceptación privada. No hace falta contratar un plan de pago para hacer esta revisión.

### Resend

**Revisión pública realizada; comprobaciones privadas pendientes.** Cloudflare sigue pendiente de confirmación de MFA/D1/acuerdos: avanzar con Resend no cierra esa cuenta.

1. **Perfil → Enable MFA**, si no está activo. Guardar la recuperación en el gestor, separada del dispositivo autenticador. Si se activó antes del 08/10/2026, comprobar si ya existe código de recuperación; la documentación explica cómo obtenerlo sin asumir que todas las cuentas antiguas disponen de él. Comprobar también los miembros del equipo: la protección es individual. No pedir códigos ni capturas del QR. [Guía MFA](https://resend.com/docs/knowledge-base/how-can-i-add-mfa).
2. **Settings → Documents:** descargar el DPA propio en el archivo privado y conservar las condiciones/datos profesionales de la cuenta. Resend indica que el documento está prefirmado y se incorpora al darse de alta, sin contrafirma separada; no encargar una firma adicional por defecto. La descarga de esta cuenta no se ha comprobado. [Dónde descargarlo](https://resend.com/docs/knowledge-base/downloading-documents). El [DPA público](https://resend.com/legal/dpa), actualizado el 31/12/2025, identifica a **Plus Five Five, Inc.**
3. **API Keys:** comprobar permiso de envío y restricción al dominio `alquivo.com`; no hace falta ver ni compartir el valor secreto. No ampliar a acceso completo para verificar entregas ni revocar/regenerar la clave que funciona sin preparar su sustitución. Se pueden consultar permisos y dominio en el panel. [Gestión oficial](https://resend.com/docs/dashboard/api-keys/introduction).
4. **Domains → alquivo.com:** comprobar verificación y opciones de seguimiento de aperturas/clics; para mensajes transaccionales de cuenta, preferir desactivarlas. No cambiar MX del buzón OVH, la región o DNS al revisar estos ajustes. No se ha confirmado ni cambiado el tracking de esta cuenta.

**Ubicación y conservación declaradas por el proveedor:** [Resend distingue](https://resend.com/security/gdpr) región de envío de almacenamiento en EE. UU., con garantías de transferencia descritas en su DPA. Publica 30 días para correos/logs en Free/Pro/Scale, copias de siete días y eliminación de datos restantes dentro de 90 días tras cerrar la cuenta. No presentar esos plazos como ejecución verificada ni aplicarlos a logs propios, facturación o evidencias legales.

Conservar la [lista de subencargados](https://resend.com/legal/subprocessors), actualizada el 27/08/2026, junto al DPA. La [explicación de privacidad](https://resend.com/security/gdpr) distingue Anthropic para editor/soporte IA de los envíos ordinarios API; también declara muestreo mediante RunPod para seguridad. No afirmar «sin subencargados de IA» ni «cada correo va a Anthropic», ni subir mensajes de clientes a editores o chats externos para esta revisión. No se cambia la IA de Alquivo.

**Revisión local, no entrega real:** `SupportTicketNotification` y `SupportTicketNotifier` conservan el aviso mínimo de referencia/enlace sin contenido ni adjuntos del ticket. El formulario antiguo de soporte no tiene la misma minimización, como ya recoge [correo](email-delivery.md); no extender esa afirmación a todos los endpoints. En HTTPS siguen pendientes alta, recuperación, verificación, MFA, tickets, worker, rebotes y cabeceras SPF/DKIM/DMARC. No se han leído `.env`, claves o correos ni llamado a la API de Resend en esta preparación.

### OpenAI API

- Conservar el acuerdo de la cuenta API y su DPA aplicable, con la entidad que figure efectivamente en contrato/factura, incorporación/aceptación, subencargados y garantías. No sustituirlo por las condiciones de una suscripción personal de ChatGPT.
- Revisar en organización/proyecto las opciones de compartir datos y mantenerlas desactivadas. Documentar el régimen de retención y residencia realmente contratado; no asumir aprobación de ZDR, controles especiales ni región UE.
- La [documentación oficial de OpenAI](https://developers.openai.com/api/docs/guides/your-data) distingue almacenamiento de respuestas y registros de prevención de abusos: `store=false` no elimina por sí solo estos últimos. La configuración se confirma en la cuenta; no hace falta enviar un prompt real, comprar saldo o dar una clave administrativa para este inventario.
- No se altera Luna/Sol, los límites de coste, consentimientos, permisos ni flags. Document AI permanece en simulación; faltan revisión humana independiente y los recorridos de producción indicados en los informes de evaluación.

## 3. Conservación y mensajes anteriores

- Antes de dar por cerrada la migración, revisar también Gmail, papelera, exportaciones, reenvíos y dispositivos que descargaran mensajes. Conservar únicamente lo necesario y justificado, con acceso restringido. No borrar indiscriminadamente conversaciones o evidencias de una reclamación: el titular decide y documenta las excepciones. No se ha accedido ni borrado correo desde esta tarea.
- La política de trabajo ya propuesta es **hasta 12 meses tras el cierre de la consulta**, con excepciones justificadas y restringidas. Pendiente de aplicarla en el buzón y de contrastarla con las copias propias del proveedor. Preparar una revisión periódica manual y documentar la primera ejecución; no afirmar que existe una purga automática.
- Las metas de backups 30 días, logs propios 14 días y auditoría 30 días siguen sin desplegar. No trasladar esos plazos a logs/copias de un proveedor sin verificarlos.
- Revisar cabeceras reales SPF/DKIM/DMARC para el webmail y Resend antes de afirmar autenticación completa. Publicar un CNAME no prueba por sí solo la firma del mensaje; no se ha verificado DMARC ni cambiado su política en este bloque.

## 4. Evidencia mínima que se conserva fuera de Git

Para cada proveedor registrar: servicio/uso, titular de la cuenta, entidad del proveedor, documentos y versión, fecha y forma de incorporación, ubicación de almacenamiento y accesos, subencargados/transferencias, conservación/baja, permisos/MFA y fecha de revisión. Distinguir **confirmado por el titular**, **comprobado técnicamente**, **declarado por el proveedor** y **pendiente**. No sustituir unos por otros.

Una carpeta protegida por proveedor puede contener pedido/factura, acuerdos aplicables, anexos/subencargados y una nota de verificación de cuenta. La copia pública de los textos propios sí se archiva en Git; los contratos completados, credenciales y capturas privadas de cuentas no. No se ha creado ni rellenado un registro privado con datos supuestos.

## 5. Qué permite cerrar este bloque

- [x] Documentar el cambio real de buzón y conservar el historial legal anterior.
- [x] Preparar textos de landing/app y archivo nuevo, sin cambiar la finalidad o consentimiento de la lista de espera.
- [x] Confirmación del titular: MFA del área de cliente OVH activado y recuperación guardada; razón social emisora OVH HISPANO S.L.
- [x] Identificar y contrastar públicamente DPA, condiciones particulares Zimbra y lista de subencargados, sin atribuirles una aceptación privada comprobada.
- [x] Revisar fuentes públicas de Resend y preparar pasos concretos para descargar el DPA y comprobar MFA, clave, conservación y seguimiento.
- [ ] Conservar evidencias de incorporación al pedido y completar acceso/MFA del buzón, terceros, conservación y copias.
- [ ] Revisar del mismo modo Cloudflare, Resend y OpenAI.
- [ ] Aplicar conservación y resolver los mensajes históricos de Gmail y sus copias.
- [ ] Completar inventario privado, evaluación de riesgos, procedimientos y revisión profesional ajustada a la actividad.
- [ ] Contratar alojamiento/copias cuando proceda y completar sus evidencias, conservación y restauración.
- [ ] Generar la revisión definitiva nueva cuando se pueda respaldar con los servicios y controles reales, desplegar coherentemente y comprobar HTTPS. No dar por aprobada la beta al rellenar esta lista.

La preparación local no publica textos nuevos en la landing ni despliega Laravel. Para actualizar la landing conectada a GitHub, revisar cambios, pruebas y secretos, aprobar su subida y comprobar el resultado de Pages. No mover la etiqueta `v0.1.0-beta.1`.

El último avance actualiza solo documentación de preparación. No cambia páginas legales ni sus versiones/archivos, cuentas, DNS, configuración, presupuestos o permisos. Las pruebas del apartado siguiente corresponden a la preparación anterior, no a una nueva ejecución de toda la suite en este avance.

Comprobaciones repetidas en este avance: archivo `launch-readiness.test.js` correcto, archivo legal verificado sin cambio de huella, detector de secretos sin patrones conocidos y `git diff --check` correcto. No se ha repetido la suite completa, enviado correo, publicado cambios ni realizado llamadas de pago.

## Verificación local de esta preparación

190 pruebas frontend correctas, incluida la compilación de la landing con transporte antispam sintético y los controles de proveedor/archivo legal. Backend focalizado: 44 pruebas y 513 aserciones correctas (registro, evidencia legal, MFA, enlaces de correo y beta), SQLite en memoria y notificaciones/peticiones externas simuladas o bloqueadas. El registro rechaza la revisión anterior sin alterar sus evidencias ni las fechas/versiones de cuentas existentes. No es una nueva evaluación de modelos ni una prueba de entrega real de correo.

Build cliente/SSR de diez páginas correcto; nueva revisión legal archivada y detector de secretos sin patrones conocidos. La comprobación no demuestra aceptación de contratos, configuración de MFA en proveedores, ejecución de conservación ni conformidad jurídica. Los avisos de IA mantienen su versión y no se han realizado inferencias, enviado correos o modificado servicios privados.
