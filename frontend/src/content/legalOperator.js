// Public information only: this file is included in the website and static HTML.
// Complete it using the signed provider agreements before publishing the beta.
// Never put credentials or private infrastructure details here.
export const legalOperator = {
  name: "",
  taxId: "",
  address: "",
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
      service: "Correo transaccional y buzón de soporte",
      name: "",
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
