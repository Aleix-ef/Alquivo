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
  backupRetention: "",
  mailboxRetention: "",
  infrastructureLogRetention: "",
  providers: [
    {
      service: "Alojamiento y almacenamiento de documentos",
      name: "",
      location: "",
      safeguards: "",
    },
    { service: "Copias de seguridad", name: "", location: "", safeguards: "" },
    {
      service: "Envío de correo transaccional",
      name: "Plus Five Five, Inc. (Resend)",
      location: "Estados Unidos (tratamiento principal según su DPA)",
      safeguards: "Cláusulas contractuales tipo incorporadas al DPA de Resend; revisar la cuenta y sus subencargados antes de publicar",
    },
    {
      service: "Reenvío de correo entrante",
      name: "Cloudflare, Inc. (Email Routing)",
      location: "",
      safeguards: "",
    },
    {
      service: "Buzón receptor de soporte",
      name: "Google (Gmail gratuito)",
      location: "",
      safeguards: "",
    },
    {
      service:
        "Asistente de IA (OpenAI), solo cuando esté habilitado y lo actives",
      name: "",
      location: "",
      safeguards: "",
    },
  ],
};
