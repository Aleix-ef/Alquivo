# Cierre práctico de proveedores — 27/10/2026

Esta guía es una comprobación del titular en sus cuentas, no otra funcionalidad que programar. No se han aceptado contratos en su nombre ni se da por completado un proveedor por leer su web. Guardar documentos y evidencias **fuera de Git**, en un archivo privado protegido.

## Qué debe quedar de cada servicio

1. Titular de la cuenta: datos profesionales correctos del titular de Alquivo. Si ya son correctos, no cambiarlos otra vez.
2. Condiciones y **acuerdo de tratamiento/DPA aplicable**, con versión/fecha y constancia de su incorporación a la cuenta/pedido. Puede incorporarse electrónicamente a las condiciones: no inventar una firma manuscrita adicional.
3. Confirmación de servicios/productos cubiertos, lugares y transferencias, subencargados, conservación y opciones efectivas. No se exige una falsa promesa de «todo en Europa» o «retención cero».

Un PDF público no acredita por sí solo el contrato de la cuenta; una factura no sustituye sus condiciones. Si no se identifica la vinculación, pedir confirmación al proveedor. Registrar fecha, estado y qué se ha comprobado; no publicar facturas completas, cuentas privadas, claves o códigos de recuperación.

## Orden práctico

### 1. Hetzner — copia externa

Abrir https://accounts.hetzner.com/account/dpa y comprobar si ya existe un DPA que cubra la Storage Box. Si no, leer y formalizar el adecuado desde la cuenta, indicando categorías y personas afectadas conforme al servicio real.

Descripción orientativa para revisar y adaptar, no autorización para copiar categorías incorrectas: almacenamiento de copias cifradas de cuentas, datos de inmuebles/contactos/contratos/cobros y documentos aportados por propietarios; interesados como titulares de cuentas, inquilinos y otros contactos que aparezcan en esa documentación. Guardar el acuerdo formalizado. No renovar/cancelar uno existente a ciegas.

Hetzner explica el procedimiento electrónico y sus anexos en [Data Protection at Hetzner](https://docs.hetzner.com/general/company-and-policy/data-protection-at-hetzner/).

### 2. Resend — envío de correo

Entrar en **Settings → Documents** y descargar el DPA propio. Resend indica que está prefirmado y se ejecuta al crear la cuenta, sin contrafirma separada. Confirmar que es la cuenta del dominio/remitente de producción, guardar documento y condiciones con fecha. No ampliar permisos ni regenerar la clave que funciona.

[Documentos de cuenta](https://resend.com/docs/knowledge-base/downloading-documents), [seguridad y tratamiento](https://resend.com/security). La región de envío no implica almacenamiento exclusivamente europeo.

### 3. OVHcloud — VPS y Zimbra

Guardar el DPA que el titular ya aportó, las condiciones de **VPS** y **Zimbra** aplicables y la evidencia del pedido/aceptación electrónica. Confirmar que se corresponden con los servicios efectivamente contratados y sus datos de titular. Si ya están incorporados, no solicitar otra firma sin necesidad.

[Catálogo oficial de contratos](https://www.ovhcloud.com/es-es/terms-and-conditions/contracts/). No sustituir automáticamente el acuerdo de su pedido por el PDF más reciente si no se ha comprobado su aplicabilidad.

### 4. Cloudflare — landing, D1, Turnstile y DNS

Conservar [DPA](https://www.cloudflare.com/cloudflare-customer-dpa/) y condiciones aplicables a la cuenta, con constancia de incorporación. El DPA publicado forma parte del acuerdo principal: revisar su aplicación al plan y servicios usados, no buscar obligatoriamente un PDF personalizado si no existe. Confirmar quién tiene acceso a D1 y su jurisdicción/ubicación efectiva sin exportar inscripciones ni recrear la base. Si la cuenta no lo acredita, consultar al soporte.

No cambiar DNS, MX, Turnstile o bases por esta revisión ni atribuir residencia europea a toda la red.

### 5. OpenAI — API del asistente

Confirmar que la organización/proyecto revisado es el de la clave de producción de Alquivo, no solo ChatGPT personal. Guardar el acuerdo de servicios y DPA aplicables, con su entidad/versión/constancia de incorporación; no deducir la contraparte exclusivamente de un emisor de factura.

En **Data controls → Sharing**, mantener desactivadas las tres opciones de compartir feedback, evaluaciones/fine-tuning y entradas/salidas. Si se modifican, guardar y recargar para comprobar persistencia. En la pantalla de cuenta ya aportada, la recomendación de minimizar el registro voluntario sigue siendo `API call logging: Disabled`; no se afirma que ya se haya cambiado ni que habilitar por llamada equivalga por sí solo a filtración.

Alquivo fuerza `store=false`. Esto **no equivale a retención cero** ni elimina los registros de prevención de abusos. No hace falta contratar ZDR ni cambiar el modelo para revisar la cuenta; documentar el tratamiento realmente disponible y las garantías aplicables.

[OpenAI Docs: datos y conservación de la API](https://developers.openai.com/api/docs/guides/your-data). La API no usa el contenido para entrenar por defecto, salvo adhesión voluntaria; eso no equivale a ausencia de conservación.

## Qué comunicar después

Basta inicialmente con indicar qué proveedores están revisados y cuáles no, por ejemplo «Hetzner: acuerdo formalizado y descargado; Resend: DPA descargado; OpenAI: Sharing desactivado en el proyecto correcto». Esa confirmación registra lo comunicado por el titular, no reemplaza el archivo privado ni una valoración de protección de datos.

La aplicación se ha hecho accesible por petición expresa mientras se revisa este punto. No captar documentación real de clientes dando por cerrado el cumplimiento solo porque cargue desde Internet. La landing sigue siendo lista de espera.
