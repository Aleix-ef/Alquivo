// Public information only: this file is included in the website and static HTML.
// Complete it using the signed provider agreements before publishing the beta.
// Never put credentials or private infrastructure details here.
export const legalOperator = {
  name: "Aleix Escañuela Fresneda",
  legalForm: "Trabajador autónomo (persona física)",
  taxId: "21807679E",
  address: "C/ Vistabella, 8, 2.º B, 03802 Alcoy (Alicante), España",
  registry: "", // Only if registration details apply to the operator.
  email: "soporte@alquivo.com",
  reviewedForPublication: false,
  // Confirmed infrastructure; private agreements/account settings still need owner review.
  backupRetention:
    "Copias externas diarias cifradas de la base de datos y los archivos, en Alemania, con una ventana de conservación de 30 días. Se conserva además la última copia válida si se interrumpe la generación de copias nuevas; esa excepción puede superar la ventana mientras se resuelve la incidencia. La eliminación en la aplicación no borra inmediatamente las copias: no se usan como archivo ordinario y, al restaurarlas, deben reaplicarse las bajas y caducidades. Se ha comprobado la restauración en un entorno aislado. Las copias técnicas del proveedor de alojamiento se rigen por las condiciones de ese servicio.",
  mailboxRetention:
    "Política prevista: consultas hasta 12 meses después de su cierre, salvo información concreta que deba conservarse por obligación legal o reclamación, con acceso restringido. Pendiente de aplicar y verificar en OVHcloud, sus copias y los mensajes históricos que puedan seguir en Gmail; cambiar de buzón no acredita su borrado.",
  infrastructureLogRetention:
    "Los registros diarios de la aplicación tienen una rotación configurada de 14 días y los de auditoría de seguridad, de 30 días; la limpieza depende de las ejecuciones de rotación. Los registros operativos de los contenedores se limitan por tamaño, no por antigüedad: queda pendiente establecer y comprobar su plazo máximo. No deben incluir cuerpos de mensajes, documentos ni credenciales. Los registros propios de cada proveedor se rigen por sus condiciones, no por estos plazos.",
  providers: [
    {
      service: "Alojamiento y almacenamiento de documentos",
      name: "OVH HISPANO S.L. (OVHcloud VPS)",
      data: "Datos de cuenta y cartera, contratos, contactos, movimientos, documentos y registros necesarios para prestar y proteger la aplicación.",
      references: [
        {
          label: "Condiciones y acuerdo de tratamiento de OVHcloud",
          href: "https://www.ovhcloud.com/es-es/terms-and-conditions/contracts/",
        },
      ],
      location:
        "Servidor y almacenamiento principal en Gravelines, Francia (Unión Europea). No se afirma que todos los accesos de soporte o subencargados del proveedor estén limitados a ese país.",
      safeguards:
        "Acuerdo de tratamiento de OVHcloud y condiciones del VPS, con obligaciones de confidencialidad, seguridad y asistencia. El servidor limita los accesos y los documentos de la aplicación se almacenan privados y cifrados. No se atribuye cifrado de extremo a extremo ni acceso exclusivo europeo al conjunto del servicio.",
    },
    {
      service: "Copias de seguridad",
      name: "Hetzner Online GmbH (Storage Box)",
      data: "Copias cifradas de la base de datos y archivos privados. Las claves de recuperación se custodian separadas del destino de copias.",
      references: [
        {
          label: "Protección de datos de Hetzner",
          href: "https://www.hetzner.com/legal/privacy-policy/",
        },
      ],
      location:
        "Falkenstein, Alemania (Unión Europea), separado del servidor principal en Francia.",
      safeguards:
        "Copias cifradas antes de enviarse al destino externo y transporte cifrado. Material de recuperación separado del servidor; restauración aislada comprobada. Acuerdo de tratamiento y condiciones de Hetzner aplicables al servicio, sujetos a la comprobación documental de la cuenta.",
    },
    {
      service: "Envío de correo transaccional",
      name: "Plus Five Five, Inc. (Resend)",
      data: "Dirección de destino y contenido de los correos que envía el servicio, incluidos enlaces de verificación/recuperación, códigos de seguridad y avisos de soporte. No se envía la cartera completa por este canal.",
      references: [
        {
          label: "Acuerdo de tratamiento de Resend",
          href: "https://resend.com/legal/dpa",
        },
      ],
      location: "Estados Unidos (tratamiento principal según su DPA)",
      safeguards:
        "Cláusulas contractuales tipo incorporadas al DPA de Resend; revisar la cuenta y sus subencargados antes de publicar",
    },
    {
      service: "Web pública y lista de espera, DNS y antispam del formulario",
      name: "Cloudflare, Inc. (Pages, D1, Turnstile y DNS)",
      data: "Datos técnicos de navegación y antispam; email, nombre y rango de inmuebles facilitados en la lista de espera, con evidencia de consentimiento. D1 no contiene la base de datos de las carteras ni los contratos.",
      references: [
        {
          label: "Acuerdo de tratamiento de Cloudflare",
          href: "https://www.cloudflare.com/cloudflare-customer-dpa/",
        },
      ],
      location:
        "Infraestructura internacional; no se acredita residencia exclusivamente europea. D1 almacena solo la lista de espera, no la cartera ni sus documentos.",
      safeguards:
        "DPA de Cloudflare y cláusulas contractuales tipo para transferencias restringidas. Revisar las condiciones de la cuenta y los subencargados. Email Routing ya no se utiliza para recibir el correo de soporte.",
    },
    {
      service: "Buzón receptor de soporte",
      name: "OVH HISPANO S.L. (OVHcloud Zimbra Starter)",
      data: "Remitente, destinatario, asunto, contenido y adjuntos de las consultas que se envían al buzón y sus respuestas. Evita enviar contraseñas, contratos completos o datos de terceros innecesarios.",
      references: [
        {
          label: "Condiciones de correo y tratamiento de OVHcloud",
          href: "https://www.ovhcloud.com/es-es/terms-and-conditions/contracts/",
        },
      ],
      location:
        "Almacenamiento del buzón en Francia según la información pública de Zimbra. No se afirma que todos los accesos de soporte o subencargados se limiten a Francia.",
      safeguards:
        "Buzón contratado y recepción/respuesta confirmadas. El titular identifica OVH HISPANO S.L. en su factura y ha aportado el acuerdo de tratamiento de OVHcloud de 17/10/2025. Debe conservarse la evidencia de incorporación al pedido y las condiciones y subencargados aplicables. El cambio de proveedor no demuestra por sí solo cumplimiento ni aplica automáticamente la política de conservación.",
    },
    {
      service: "Mensajes históricos del buzón anterior, si aún se conservan",
      name: "Google (Gmail gratuito; sin recepción nueva de soporte)",
      data: "Únicamente consultas y adjuntos del buzón anterior que aún deban conservarse. Este servicio ya no recibe las consultas nuevas de soporte.",
      references: [
        {
          label: "Política de privacidad de Google",
          href: "https://policies.google.com/privacy?hl=es",
        },
      ],
      location:
        "Servicio internacional; no se ha acreditado una región contractual europea para este buzón.",
      safeguards:
        "La recepción nueva se ha trasladado a OVHcloud. Pueden quedar mensajes, adjuntos o copias anteriores en Gmail: revisar su necesidad, restringir su acceso y suprimir lo que no deba conservarse. No se atribuye a Gmail gratuito el acuerdo empresarial de Google Workspace ni se da por borrado su historial.",
    },
    {
      service:
        "Asistente de IA (OpenAI), solo cuando esté habilitado y lo actives",
      name: "OpenAI (API; entidad contractual de la cuenta pendiente de confirmar)",
      data: "Solo si se habilita y activas el chat: mensajes y contexto autorizado necesario para responder, incluidos los datos que tú escribas. El chat no envía archivos originales para análisis documental; Document AI real permanece deshabilitada.",
      references: [
        {
          label: "Tratamiento y conservación de la API de OpenAI",
          href: "https://developers.openai.com/api/docs/guides/your-data",
        },
      ],
      location:
        "Servicio internacional; no se ha acreditado residencia europea para el proyecto. Puede haber tratamiento fuera del Espacio Económico Europeo.",
      safeguards:
        "Verificar y conservar el acuerdo de tratamiento de la cuenta y sus garantías de transferencia antes de abrir la IA. store=false no equivale a retención cero; desactivar la cesión voluntaria para entrenamiento. Document AI real sigue deshabilitada.",
    },
  ],
};
