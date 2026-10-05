// API amounts have at most two decimals. Compute form limits in integer cents:
// 380.03 - 330.01 must not become a max of 50.01999999999998.
function cents(value) {
  const decimal = String(value ?? 0);
  if (!/^\d{1,10}(?:\.\d{1,2})?$/.test(decimal)) {
    throw new Error("El importe del recibo no es válido.");
  }
  const [whole, fraction = ""] = decimal.split(".");
  return Number(whole) * 100 + Number(fraction.padEnd(2, "0"));
}

export function rentPaymentLimits(charge, transactionAmount = "0.00") {
  const remaining = charge
    ? cents(charge.amount) - cents(charge.paid_amount)
    : 0;
  return {
    remaining: (remaining / 100).toFixed(2),
    maximum: ((remaining + cents(transactionAmount)) / 100).toFixed(2),
  };
}
