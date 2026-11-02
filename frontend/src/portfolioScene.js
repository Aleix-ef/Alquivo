export const sceneGroupSize = 6;

// Never carry a selection across groups, searches or portfolios if it is absent.
export function sceneSelectionId(properties, selectedId = null) {
  return (
    properties.find((property) => property.id === selectedId)?.id ??
    properties[0]?.id ??
    null
  );
}

export function sceneBuildingFrame(propertyCount) {
  const floors = Math.min(
    sceneGroupSize,
    Math.max(1, Math.floor(Number(propertyCount) || 1)),
  );
  return {
    // Keep the base, roof and selected-floor movement inside the final frame.
    // Tighter framing avoids enlarging the empty margins along with the drawing.
    viewBox: `58 44 560 ${262 + floors * 88}`,
    desktopHeight: 166 + floors * 32,
    mobileHeight: 190 + floors * 8,
  };
}

export function filterSceneProperties(properties, query = "") {
  const normalize = (value) =>
    String(value ?? "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLocaleLowerCase("es");
  const words = normalize(query).trim().split(/\s+/).filter(Boolean);
  return properties.filter((property) => {
    const haystack = normalize(
      [property.name, property.city, property.address_line]
        .filter(Boolean)
        .join(" "),
    );
    return words.every((word) => haystack.includes(word));
  });
}

export function scenePropertyFacts(property) {
  const leases = (property?.leases || []).filter(
    (lease) => lease.status === "active",
  );
  const amount = (value) =>
    value !== null &&
    value !== undefined &&
    value !== "" &&
    Number.isFinite(Number(value))
      ? Number(value)
      : null;
  const rent = leases.map((lease) => amount(lease.monthly_rent));
  const value = amount(property?.current_value);
  const monthlyRent =
    !leases.length || rent.some((value) => value === null)
      ? null
      : rent.reduce((sum, value) => sum + Math.round(value * 100), 0) / 100;
  return {
    rented: leases.length > 0,
    leaseCount: leases.length,
    value,
    debt: amount(property?.outstanding_debt),
    // Contracted rent, never cash received or a calculated outstanding balance.
    monthlyRent,
    // Same gross annualized-rent/current-value basis as the dashboard; not net profit.
    grossYield:
      value > 0 && monthlyRent !== null
        ? Math.round(((monthlyRent * 12) / value) * 10000) / 100
        : null,
  };
}

export async function loadSceneProperties(api, signal) {
  // Existing, authorized endpoint: include every page, not the dashboard's six-item preview.
  const { fetchAllPages } = await import("./pagination.js");
  const items = await fetchAllPages(api, "/properties", { signal });
  return items.filter(
    (item) => Number.isSafeInteger(Number(item.id)) && Number(item.id) > 0,
  );
}
