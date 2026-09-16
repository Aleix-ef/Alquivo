export const fiscalMoney = (cents) =>
  cents == null
    ? "Pendiente"
    : new Intl.NumberFormat("es-ES", {
        style: "currency",
        currency: "EUR",
      }).format(cents / 100);

export function fiscalDraft(inputs = {}) {
  return {
    ownership_percent: null,
    owned_from: null,
    owned_to: null,
    ordinary_case: false,
    records_reviewed: false,
    reduction_case: null,
    contract_start: null,
    income: null,
    building_cost: null,
    cadastral_building: null,
    cadastral_total: null,
    cadastral_revision: null,
    prior_depreciation: null,
    depreciation_rate: null,
    periods: [],
    expenses: [],
    carryforwards: [],
    notes: "",
    ...JSON.parse(JSON.stringify(inputs)),
  };
}

export function fiscalPayload(draft) {
  const result = JSON.parse(JSON.stringify(draft));
  const decimal = (value) =>
    value === "" || value == null
      ? null
      : String(value).trim().replace(",", ".");
  for (const key of [
    "ownership_percent",
    "income",
    "building_cost",
    "cadastral_building",
    "cadastral_total",
    "prior_depreciation",
    "depreciation_rate",
  ])
    result[key] = decimal(result[key]);
  for (const key of [
    "owned_from",
    "owned_to",
    "contract_start",
    "reduction_case",
    "cadastral_revision",
  ])
    result[key] ||= null;
  result.expenses = result.expenses.map((item) => ({
    ...item,
    amount: decimal(item.amount),
    document_id: item.document_id || null,
  }));
  result.carryforwards = result.carryforwards.map((item) => ({
    year: Number(item.year),
    amount: decimal(item.amount),
  }));
  return result;
}
