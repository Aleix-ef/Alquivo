export const adminSections = [
  ["users", "Usuarios"],
  ["plans", "Planes"],
  ["activation", "Activación"],
  ["revenue", "Ingresos"],
  ["ai", "Alquivo AI"],
  ["usage", "Uso"],
  ["retention", "Retención"],
  ["acquisition", "Captación"],
];
export const adminSection = (value) =>
  adminSections.some(([key]) => key === value) ? value : "users";
const number = new Intl.NumberFormat("es-ES");
export const count = (value) =>
  value == null ? "—" : number.format(Number(value));
export function nanoUsd(value) {
  if (value == null) return "—";
  return new Intl.NumberFormat("es-ES", {
    style: "currency",
    currency: "USD",
    minimumFractionDigits: 4,
    maximumFractionDigits: 6,
  }).format(Number(value) / 1e9);
}
export function storage(value) {
  if (value == null) return "—";
  const n = Number(value);
  if (n < 1024) return `${count(n)} B`;
  const power = Math.min(3, Math.floor(Math.log(n) / Math.log(1024)));
  return `${new Intl.NumberFormat("es-ES", { maximumFractionDigits: 2 }).format(n / 1024 ** power)} ${["B", "KB", "MB", "GB"][power]}`;
}
export const adminDate = (value) =>
  value
    ? new Intl.DateTimeFormat("es-ES", {
        dateStyle: "medium",
        timeZone: "Europe/Madrid",
      }).format(new Date(value.length === 10 ? `${value}T12:00:00Z` : value))
    : "—";
export function recentRange(days = 30, now = new Date()) {
  const to = new Intl.DateTimeFormat("en-CA", {
    timeZone: "Europe/Madrid",
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).format(now);
  const from = new Date(`${to}T12:00:00Z`);
  from.setUTCDate(from.getUTCDate() - days + 1);
  return { from: from.toISOString().slice(0, 10), to };
}
export function chartBuckets(series = []) {
  const size = Math.max(1, Math.ceil(series.length / 60));
  const result = [];
  for (let i = 0; i < series.length; i += size) {
    const group = series.slice(i, i + size);
    result.push({
      from: group[0].date,
      to: group.at(-1).date,
      count: group.reduce((n, item) => n + item.registrations, 0),
    });
  }
  return result;
}
