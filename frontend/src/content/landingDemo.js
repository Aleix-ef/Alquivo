// Public, synthetic illustration only. Never reads accounts or calls the AI/API.
// Store cents as integers so the preview and its answer always reconcile.
export const demoPeriod = "Octubre · ejemplo";
export const demoRentals = Object.freeze([
  Object.freeze({ name: "Piso Centro", dueCents: 75000, receivedCents: 75000 }),
  Object.freeze({
    name: "Apartamento Mar",
    dueCents: 55000,
    receivedCents: 30000,
  }),
  Object.freeze({ name: "Garaje Norte", dueCents: 10000, receivedCents: 0 }),
]);
export const demoExpenses = Object.freeze([
  Object.freeze({
    property: "Piso Centro",
    description: "Fontanería",
    cents: 8400,
  }),
  Object.freeze({
    property: "Apartamento Mar",
    description: "Comunidad",
    cents: 4600,
  }),
]);
export const demoSummary = Object.freeze({
  receivedCents: demoRentals.reduce(
    (sum, rental) => sum + rental.receivedCents,
    0,
  ),
  pendingCents: demoRentals.reduce(
    (sum, rental) => sum + rental.dueCents - rental.receivedCents,
    0,
  ),
  expenseCents: demoExpenses.reduce((sum, expense) => sum + expense.cents, 0),
});
export const demoPending = Object.freeze(
  demoRentals
    .filter((rental) => rental.receivedCents < rental.dueCents)
    .map((rental) =>
      Object.freeze({
        name: rental.name,
        cents: rental.dueCents - rental.receivedCents,
      }),
    ),
);

export const demoMoney = (cents) =>
  new Intl.NumberFormat("es-ES", {
    style: "currency",
    currency: "EUR",
    maximumFractionDigits: 2,
  }).format(cents / 100);

// Unknown during prerender/loading must not be interpreted as "not available".
export function publicAssistantState(product) {
  return product?.loaded &&
    !product.error &&
    typeof product.features?.assistant === "boolean"
    ? product.features.assistant
    : null;
}
