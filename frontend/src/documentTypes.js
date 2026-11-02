export const documentTypes = Object.freeze(
  [
    {
      value: "contract",
      label: "Contrato",
      plural: "Contratos",
      issuedLabel: "Fecha del contrato",
      expiryLabel: "Fecha de fin o revisión",
      hasExpiry: true,
      hint: "Estas fechas organizan el archivo. Las condiciones del alquiler se gestionan en Alquileres.",
      placeholder: "Ej. Contrato · Piso Centro",
    },
    {
      value: "invoice",
      label: "Factura",
      plural: "Facturas",
      issuedLabel: "Fecha de la factura",
      hasExpiry: false,
      hint: "La fecha de la factura es opcional. Los pagos y sus vencimientos se gestionan en Finanzas.",
      placeholder: "Ej. Luz · noviembre 2026",
    },
    {
      value: "insurance",
      label: "Seguro",
      plural: "Seguros",
      issuedLabel: "Fecha de emisión",
      expiryLabel: "Fin de cobertura o revisión",
      hasExpiry: true,
      hint: "Anota las fechas que figuran en la póliza, si quieres recibir avisos de revisión.",
      placeholder: "Ej. Seguro de hogar · Piso Centro",
    },
    {
      value: "tax",
      label: "Impuesto",
      plural: "Impuestos",
      issuedLabel: "Fecha del justificante",
      hasExpiry: false,
      hint: "Puedes anotar la fecha del justificante. Los pagos se registran en Finanzas.",
      placeholder: "Ej. IBI · 2026",
    },
    {
      value: "certificate",
      label: "Certificado",
      plural: "Certificados",
      issuedLabel: "Fecha de expedición",
      expiryLabel: "Fecha de caducidad, si consta",
      hasExpiry: true,
      hint: "No todos los certificados caducan. Añade esa fecha solo si figura en el documento.",
      placeholder: "Ej. Certificado energético · Piso Centro",
    },
    {
      value: "other",
      label: "Otro documento",
      plural: "Otros",
      issuedLabel: "Fecha del documento",
      expiryLabel: "Revisar el documento el",
      hasExpiry: true,
      hint: "Añade fechas solo si te ayudan a organizar o revisar este archivo.",
      placeholder: "Opcional",
    },
  ].map(Object.freeze),
);

export function documentType(category) {
  return (
    documentTypes.find((type) => type.value === category) ||
    documentTypes.at(-1)
  );
}
