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
  // Operational targets, not claims that a production server is already running.
  backupRetention:
    "Política prevista para la beta: copias diarias cifradas, con un máximo de 30 días. Pendiente de configurar el destino externo, su caducidad y el ensayo de restauración; no hay todavía copias de producción.",
  mailboxRetention:
    "Consultas hasta 12 meses después de su cierre, salvo información concreta que deba conservarse por obligación legal o reclamación, con acceso restringido. Debe aplicarse también al buzón receptor y a sus copias; la configuración del buzón está pendiente de verificar.",
  infrastructureLogRetention:
    "Política prevista para la beta: hasta 14 días para logs técnicos bajo control de Alquivo y 30 días para su auditoría de seguridad, sin cuerpos de mensajes, documentos o credenciales. Pendiente de aplicar en el servidor. Los registros propios de cada proveedor se rigen por sus condiciones verificadas, no por este plazo.",
  providers: [
    {
      service: "Alojamiento y almacenamiento de documentos",
      name: "Hetzner Online GmbH (previsto; servidor aún no contratado)",
      location:
        "Se elegirá una región de la Unión Europea; ubicación concreta pendiente de contratación.",
      safeguards:
        "Acuerdo de tratamiento disponible en la cuenta Hetzner. Antes de abrir la beta, formalizarlo y comprobar ubicación, accesos y subencargados del producto contratado.",
    },
    {
      service: "Copias de seguridad",
      name: "Destino externo pendiente de contratación",
      location:
        "Por confirmar; se prevé un destino separado del servidor y situado en la Unión Europea.",
      safeguards:
        "Copias cifradas y claves separadas; proveedor, acuerdo, conservación y restauración pendientes de comprobar. No se presenta una copia local como copia externa.",
    },
    {
      service: "Envío de correo transaccional",
      name: "Plus Five Five, Inc. (Resend)",
      location: "Estados Unidos (tratamiento principal según su DPA)",
      safeguards:
        "Cláusulas contractuales tipo incorporadas al DPA de Resend; revisar la cuenta y sus subencargados antes de publicar",
    },
    {
      service:
        "Web pública y lista de espera, DNS, antispam y reenvío de correo entrante",
      name: "Cloudflare, Inc. (Pages, D1, Turnstile y Email Routing)",
      location:
        "Infraestructura internacional; no se acredita residencia exclusivamente europea. D1 almacena solo la lista de espera, no la cartera ni sus documentos.",
      safeguards:
        "DPA de Cloudflare y cláusulas contractuales tipo para transferencias restringidas. Revisar las condiciones de la cuenta y los subencargados. Email Routing reenvía el correo al buzón indicado; no es el buzón de soporte.",
    },
    {
      service: "Buzón receptor de soporte",
      name: "Google (Gmail gratuito)",
      location:
        "Servicio internacional; no se ha acreditado una región contractual europea para este buzón.",
      safeguards:
        "Recepción actual confirmada por el titular. No se atribuye a Gmail gratuito el acuerdo empresarial de Google Workspace. Pendiente de revisar o sustituir por un buzón profesional y aplicar su conservación antes de la beta.",
    },
    {
      service:
        "Asistente de IA (OpenAI), solo cuando esté habilitado y lo actives",
      name: "OpenAI (API; entidad contractual de la cuenta pendiente de confirmar)",
      location:
        "Servicio internacional; no se ha acreditado residencia europea para el proyecto. Puede haber tratamiento fuera del Espacio Económico Europeo.",
      safeguards:
        "Verificar y conservar el acuerdo de tratamiento de la cuenta y sus garantías de transferencia antes de abrir la IA. store=false no equivale a retención cero; desactivar la cesión voluntaria para entrenamiento. Document AI real sigue deshabilitada.",
    },
  ],
};
