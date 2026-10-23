export function validPaymentWindow(start, end) {
  const day = (value) =>
    ["number", "string"].includes(typeof value) &&
    String(value).trim() !== "" &&
    Number.isInteger(Number(value)) &&
    Number(value) >= 1 &&
    Number(value) <= 31;
  return day(start) && day(end) && Number(start) <= Number(end);
}

export function paymentWindowLabel(lease) {
  const end = Number(lease.payment_day);
  const start = Number(lease.payment_day_start ?? end);
  if (!validPaymentWindow(start, end)) return "sin plazo registrado";
  return start === end ? `el día ${end}` : `del ${start} al ${end}`;
}
