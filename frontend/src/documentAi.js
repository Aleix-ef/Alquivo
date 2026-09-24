export const documentStates = {
  queued: "En cola",
  processing: "Preparando borrador",
  needs_review: "Pendiente de revisión",
  confirmed: "Confirmado",
  failed: "No se pudo procesar",
  cancelled: "Cancelado",
  expired: "Caducado",
};
export const documentFields = {
  issuer: "Emisor",
  invoice_number: "Número de factura",
  date: "Fecha de factura",
  description: "Concepto",
  subtotal: "Base / subtotal",
  vat: "IVA (importe)",
  total: "Total",
  category: "Categoría",
  property_hint: "Referencia al inmueble",
  currency: "Moneda",
  start_date: "Inicio",
  end_date: "Fin",
  monthly_rent: "Renta mensual",
  deposit_amount: "Fianza",
  periodicity: "Periodicidad",
  payment_day: "Día de pago",
  rent_update_clause: "Cláusula de actualización de renta",
  other_clauses: "Otras cláusulas",
  relevant_dates: "Fechas relevantes",
  participants: "Participantes",
};
export function reviewValues(values) {
  const copy = JSON.parse(JSON.stringify(values));
  for (const [key, value] of Object.entries(copy)) {
    if (value === "") copy[key] = null;
  }
  for (const key of ["property_id", "lease_id"]) {
    if (copy[key] != null) copy[key] = Number(copy[key]);
  }
  return copy;
}
export function receiptPath(receipt) {
  if (Number.isSafeInteger(receipt?.lease_id) && receipt.lease_id > 0)
    return `/leases/${receipt.lease_id}`;
  if (
    Number.isSafeInteger(receipt?.transaction_id) &&
    receipt.transaction_id > 0
  )
    return "/finance";
  return null;
}
export function documentError(error) {
  const data = error?.response?.data;
  const fields = Object.values(data?.errors || {})
    .flat()
    .filter((v) => typeof v === "string");
  return (
    fields.join(" ") ||
    data?.message ||
    "No se ha podido completar la operación. Recarga el estado antes de intentarlo otra vez."
  );
}
