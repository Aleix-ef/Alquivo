export function calendarDate(date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
}

export function parseCalendarDate(value) {
  if (typeof value !== "string" || !/^[1-9]\d{3}-\d{2}-\d{2}$/.test(value))
    return null;
  const [year, month, day] = value.split("-").map(Number);
  const date = new Date(year, month - 1, day, 12);
  return calendarDate(date) === value ? date : null;
}

function addDays(date, count) {
  const result = new Date(date);
  result.setDate(result.getDate() + count);
  return result;
}

export function calendarMonthDays(month, today = calendarDate(new Date())) {
  const first = parseCalendarDate(`${month}-01`);
  if (!first) throw new Error("Mes no válido");
  const monday = addDays(first, -((first.getDay() + 6) % 7));
  return Array.from({ length: 42 }, (_, index) => {
    const date = addDays(monday, index);
    const key = calendarDate(date);
    return {
      date: key,
      number: date.getDate(),
      inMonth: key.startsWith(`${month}-`),
      today: key === today,
    };
  });
}

export function shiftCalendarMonth(month, delta) {
  const first = parseCalendarDate(`${month}-01`);
  if (!first) throw new Error("Mes no válido");
  first.setMonth(first.getMonth() + delta);
  const result = calendarDate(first).slice(0, 7);
  return parseCalendarDate(`${result}-01`) ? result : month;
}

export function calendarRange(month) {
  const days = calendarMonthDays(month);
  // UTC reminders can fall on a different local date. Fetch boundary days,
  // then group locally; date-only rent/contract/document events keep their date.
  return {
    from: `${calendarDate(addDays(parseCalendarDate(days[0].date), -1))} 00:00:00`,
    to: `${calendarDate(addDays(parseCalendarDate(days.at(-1).date), 1))} 23:59:59`,
  };
}

export function calendarEventDate(event) {
  if (event.type === "reminder" && event.starts_at) {
    const timestamp = new Date(event.starts_at);
    if (!Number.isNaN(timestamp.getTime())) return calendarDate(timestamp);
  }
  return parseCalendarDate(event.date) ? event.date : null;
}

export function reminderLocalInput(timestamp) {
  const date = new Date(timestamp);
  if (Number.isNaN(date.getTime())) return "";
  return `${calendarDate(date)}T${String(date.getHours()).padStart(2, "0")}:${String(date.getMinutes()).padStart(2, "0")}`;
}

export function calendarEventTitle(event) {
  if (event.type !== "rent") return event.title;
  return (
    {
      "Alquiler pending": "Alquiler pendiente",
      "Alquiler partial": "Cobro parcial",
      "Alquiler paid": "Alquiler cobrado",
      "Alquiler cancelled": "Alquiler cancelado",
    }[event.title] || event.title
  );
}
