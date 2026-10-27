// Public information only: this file is included in the website and static HTML.
// Editorially reviewed public notices. Private provider agreements and operational
// checks are tracked separately in docs/provider-privacy-checklist.md.
// Never put credentials or private infrastructure details here.
export const legalOperator = {
  name: "Aleix Escañuela Fresneda",
  legalForm: "Trabajador autónomo (persona física)",
  taxId: "21807679E",
  address: "C/ Vistabella, 8, 2.º B, 03802 Alcoy (Alicante), España",
  registry: "", // Only if registration details apply to the operator.
  email: "soporte@alquivo.com",
  // Content review only: not a certification or proof of private account settings.
  reviewedForPublication: true,
  backupRetention:
    "Copias externas diarias cifradas de la base de datos y los archivos, en Alemania, con una ventana de conservación de 30 días. Se conserva además la última copia válida si se interrumpe la generación de copias nuevas; esa excepción puede superar la ventana mientras se resuelve la incidencia. La eliminación en la aplicación no borra inmediatamente las copias: no se usan como archivo ordinario y, al restaurarlas, deben reaplicarse las bajas y caducidades. Se ha comprobado la restauración en un entorno aislado. Las copias técnicas del proveedor de alojamiento se rigen por las condiciones de ese servicio.",
  mailboxRetention:
    "Las consultas se conservan durante su tramitación y hasta 12 meses después de su cierre. La revisión y supresión del buzón son manuales; este plazo no implica un borrado automático al cumplir el año. Solo se conserva información concreta durante más tiempo cuando exista una obligación legal o una reclamación que lo justifique, con acceso restringido. Las copias y la recuperación de mensajes del proveedor tienen sus propios plazos; borrar un mensaje del buzón no acredita su eliminación inmediata de todas esas copias.",
  infrastructureLogRetention:
    "Los registros diarios de la aplicación tienen una rotación configurada de 14 días y los de auditoría de seguridad, de 30 días; la limpieza depende de las ejecuciones de rotación. Los registros operativos del servidor y los contenedores se conservan en el journal del VPS con una ventana de 30 días y un límite de espacio de 256 MB. La rotación diaria y la limpieza horaria trabajan por bloques: puede haber un margen hasta el siguiente ciclo, y el límite de espacio puede reducir el historial disponible. Se utilizan para diagnosticar fallos y proteger el servicio, no como archivo de conversaciones o documentos. Los registros propios de cada proveedor y sus copias se rigen por sus condiciones, no por estos plazos.",
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
        "Copias cifradas antes de enviarse al destino externo y transporte cifrado. Material de recuperación separado del servidor; restauración aislada comprobada. Hetzner publica sus condiciones de protección de datos y ofrece un acuerdo de encargo para los servicios que lo requieren. La ubicación de este destino no acredita por sí sola la ubicación de todos los subencargados.",
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
        "El DPA publicado por Resend incorpora cláusulas contractuales tipo para las transferencias a las que resultan aplicables y se incorpora a sus condiciones de servicio. La región de envío no equivale a almacenamiento exclusivo en Europa. Solo se comunica el contenido necesario para enviar cada correo.",
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
        "Cloudflare publica un DPA y cláusulas contractuales tipo para transferencias restringidas. El tratamiento depende del servicio y sus subencargados; no se atribuye residencia europea a toda su red. Email Routing ya no se utiliza para recibir el correo de soporte.",
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
        "Buzón profesional con acceso restringido. OVHcloud publica un acuerdo de tratamiento con obligaciones de confidencialidad, seguridad, asistencia y regulación de subencargados. El traslado del buzón no elimina los mensajes históricos ni supone cifrado de extremo a extremo. No se remiten las consultas nuevas a Gmail.",
    },
    {
      service:
        "Asistente de IA (OpenAI), solo cuando esté habilitado y lo actives",
      name: "OpenAI Ireland Ltd. (API; contraparte prevista en el DPA publicado para clientes establecidos en España/EEE)",
      data: "Solo si se habilita y activas el chat: mensajes y contexto autorizado necesario para responder, incluidos los datos que tú escribas. El chat no envía archivos originales para análisis documental; Document AI real permanece deshabilitada.",
      references: [
        {
          label: "Acuerdo de tratamiento de OpenAI",
          href: "https://openai.com/policies/data-processing-addendum/",
        },
        {
          label: "Tratamiento y conservación de la API de OpenAI",
          href: "https://developers.openai.com/api/docs/guides/your-data",
        },
      ],
      location:
        "Servicio internacional; no se ha acreditado residencia europea para el proyecto. Puede haber tratamiento fuera del Espacio Económico Europeo.",
      safeguards:
        "El DPA publicado regula el tratamiento y las transferencias internacionales, incluidas cláusulas contractuales tipo o decisiones de adecuación cuando procedan. La app fuerza store=false; esto no equivale a retención cero de registros de prevención de abusos. La API no utiliza el contenido para entrenar modelos por defecto salvo adhesión voluntaria; Alquivo no solicita esa cesión. No se afirma residencia exclusivamente europea ni se habilita análisis documental real.",
    },
  ],
};
