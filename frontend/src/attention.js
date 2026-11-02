// Presentation only: ordering, balances and priority come from Laravel.
export function attentionDestination(item) {
  if (item?.action?.mode !== "navigate") return null;
  const path = item.action.path;
  const id = item.entity?.id;
  if (!Number.isSafeInteger(id) || id < 1) return null;
  if (item.entity?.type === "rent_charge") {
    const destination = `/finance#rent-charge-${id}`;
    const leaseId = item.evidence?.lease_id;
    // Existing snapshots may still point to the lease. Upgrade only the exact
    // old destination backed by its evidence, never a supplied arbitrary URL.
    const legacy =
      Number.isSafeInteger(leaseId) &&
      leaseId > 0 &&
      path === `/leases/${leaseId}`;
    return path === destination || legacy ? destination : null;
  }
  if (item.entity?.type === "lease") {
    const destination = `/leases/${id}#lease-${id}`;
    return path === destination || path === `/leases/${id}`
      ? destination
      : null;
  }
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
