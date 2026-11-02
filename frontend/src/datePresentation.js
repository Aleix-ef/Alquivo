import { parseCalendarDate } from "./calendar.js";

// Recorded date-only values must not shift a day when rendered in another zone.
export function shortDate(value) {
  const date =
    typeof value === "string" ? parseCalendarDate(value.slice(0, 10)) : null;
  return date
    ? new Intl.DateTimeFormat("es-ES", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
      }).format(date)
    : "Sin fecha";
}

export function shortPeriod(value) {
  const date =
    typeof value === "string" && /^\d{4}-\d{2}$/.test(value)
      ? parseCalendarDate(`${value}-01`)
      : null;
  return date
    ? new Intl.DateTimeFormat("es-ES", {
        month: "long",
        year: "numeric",
      }).format(date)
    : "Sin mensualidad";
}
