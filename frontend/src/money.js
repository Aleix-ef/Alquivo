// API amounts are numbers or decimal strings in major currency units, not cents.
// Keep cents visible in summaries as well as individual records.
export function formatMoney(value, currency = "EUR") {
  return new Intl.NumberFormat("es-ES", {
    style: "currency",
    currency,
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value ?? 0);
}
