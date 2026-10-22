import { test } from "node:test";
import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import { spawnSync } from "node:child_process";
import { parse } from "@vue/compiler-sfc";
import {
  calendarDate,
  parseCalendarDate,
  calendarMonthDays,
  shiftCalendarMonth,
  calendarRange,
  calendarEventDate,
  calendarEventTitle,
  reminderLocalInput,
} from "../src/calendar.js";

test("month view is six complete Monday-first weeks, including neighbouring dates", () => {
  const days = calendarMonthDays("2026-10", "2026-10-22");
  assert.equal(days.length, 42);
  assert.equal(days[0].date, "2026-09-28");
  assert.equal(days.at(-1).date, "2026-11-08");
  assert.equal(new Set(days.map((day) => day.date)).size, 42);
  assert.equal(days.filter((day) => day.inMonth).length, 31);
  assert.deepEqual(
    days.filter((day) => day.today).map((day) => day.date),
    ["2026-10-22"],
  );
  assert.equal(parseCalendarDate(days[0].date).getDay(), 1);
});

test("leap years, Sunday starts and crossing a year do not lose dates", () => {
  assert.equal(
    calendarMonthDays("2024-02").filter((day) => day.inMonth).length,
    29,
  );
  assert.equal(
    calendarMonthDays("2025-02").filter((day) => day.inMonth).length,
    28,
  );
  assert.equal(calendarMonthDays("2026-11")[0].date, "2026-10-26");
  assert.equal(shiftCalendarMonth("2026-12", 1), "2027-01");
  assert.equal(shiftCalendarMonth("2026-01", -1), "2025-12");
  assert.equal(shiftCalendarMonth("2026-03", -1), "2026-02");
});

test("invalid and impossible query dates cannot roll silently into another month", () => {
  for (const value of [
    undefined,
    ["2026-10-22"],
    "2026-02-29",
    "2026-02-31",
    "2026-13-01",
    "2026-00-01",
    "2026-10-00",
    "2026-1-2",
    "2026-10-22<script>",
  ])
    assert.equal(parseCalendarDate(value), null);
  assert.equal(calendarDate(parseCalendarDate("2024-02-29")), "2024-02-29");
  assert.throws(() => calendarMonthDays("2026-13"));
});

test("API query covers the entire visible grid plus UTC reminder boundary days", () => {
  assert.deepEqual(calendarRange("2026-10"), {
    from: "2026-09-27 00:00:00",
    to: "2026-11-09 23:59:59",
  });
  assert.deepEqual(calendarRange("2026-12"), {
    from: "2026-11-29 00:00:00",
    to: "2027-01-11 23:59:59",
  });
});

test("date-only events never shift with timezone and invalid timestamps fail safely", () => {
  for (const type of ["rent", "lease_end", "document_expiry"])
    assert.equal(calendarEventDate({ type, date: "2026-10-22" }), "2026-10-22");
  assert.equal(
    calendarEventDate({
      type: "reminder",
      date: "2026-10-22",
      starts_at: "invalid",
    }),
    "2026-10-22",
  );
  assert.equal(calendarEventDate({ type: "rent", date: "invalid" }), null);
  assert.equal(reminderLocalInput("invalid"), "");
});

test("DST and local editing round-trip retain the reminder time without moving date-only events", () => {
  const url = new URL("../src/calendar.js", import.meta.url).href;
  for (const TZ of ["UTC", "Europe/Madrid", "America/Los_Angeles"]) {
    const program = `import assert from 'node:assert/strict'; import {calendarMonthDays,calendarEventDate,calendarDate,reminderLocalInput} from ${JSON.stringify(url)};
      assert.equal(calendarMonthDays('2026-03')[0].date,'2026-02-23');
      assert.equal(calendarMonthDays('2026-03').at(-1).date,'2026-04-05');
      const iso='2026-10-31T23:30:00.000Z';
      assert.equal(new Date(reminderLocalInput(iso)).toISOString(),iso);
      assert.equal(calendarEventDate({type:'reminder',starts_at:iso}),calendarDate(new Date(iso)));
      assert.equal(calendarEventDate({type:'rent',date:'2026-10-31'}),'2026-10-31');`;
    const result = spawnSync(
      process.execPath,
      ["--input-type=module", "-e", program],
      { env: { ...process.env, TZ }, encoding: "utf8" },
    );
    assert.equal(result.status, 0, `${TZ}: ${result.stderr}`);
  }
});

test("existing rental event statuses are shown in Spanish without reinterpreting amounts", () => {
  assert.equal(
    calendarEventTitle({ type: "rent", title: "Alquiler partial" }),
    "Cobro parcial",
  );
  assert.equal(
    calendarEventTitle({ type: "rent", title: "Alquiler paid" }),
    "Alquiler cobrado",
  );
  assert.equal(
    calendarEventTitle({ type: "reminder", title: "Mi recordatorio" }),
    "Mi recordatorio",
  );
});

test("calendar retains native table semantics, daily detail, reminder operations and dialog protection", async () => {
  const source = await readFile(
    new URL("../src/views/CalendarView.vue", import.meta.url),
    "utf8",
  );
  const { errors } = parse(source);
  assert.deepEqual(errors, []);
  assert.match(source, /<table/);
  assert.match(source, /scope="col"/);
  assert.match(source, /:aria-pressed/);
  assert.match(source, /Mes anterior/);
  assert.match(source, /Mes siguiente/);
  assert.match(source, /useDialog\(show, drawer, closeForm\)/);
  assert.match(source, /useRecordAnchor\(loading, "reminder"\)/);
  assert.match(source, /calendarRange\(month.value\)/);
  assert.match(source, /formatMoney\(event.amount\)/);
  assert.match(source, /AbortController/);
  assert.match(source, /new Date\(form.value.starts_at\).toISOString\(\)/);
});
