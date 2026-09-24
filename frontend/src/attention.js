// Presentation only: ordering, balances and priority come from Laravel.
export function attentionDestination(item) {
  if (item?.action?.mode !== "navigate") return null;
  const path = item.action.path;
  const id =
    item.entity?.type === "rent_charge"
      ? item.evidence?.lease_id
      : item.entity?.id;
  if (["rent_charge", "lease"].includes(item.entity?.type))
    return Number.isSafeInteger(id) && id > 0 && path === `/leases/${id}`
      ? path
      : null;
  if (!Number.isSafeInteger(id) || id < 1) return null;
  const fixed = {
    issue: `/issues#issue-${id}`,
    document: `/documents#document-${id}`,
  };
  if (
    item.entity?.type === "reminder" &&
    /^\d{4}-\d{2}-\d{2}$/.test(item.date ?? "")
  )
    return path === `/calendar?date=${item.date}#reminder-${id}` ? path : null;
  return fixed[item.entity?.type] === path ? path : null;
}

export function attentionMoney(value, currency = "EUR") {
  if (!/^\d+\.\d{2}$/.test(value ?? "")) return "—";
  const [whole, cents] = value.split(".");
  // Do not round a decimal string through a floating-point number.
  return `${BigInt(whole).toLocaleString("es-ES")},${cents} ${currency === "EUR" ? "€" : currency}`;
}

export function attentionDate(value) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value ?? "")) return "Sin fecha límite";
  return new Intl.DateTimeFormat("es-ES", {
    day: "numeric",
    month: "short",
    year: "numeric",
    timeZone: "UTC",
  }).format(new Date(`${value}T00:00:00Z`));
}
