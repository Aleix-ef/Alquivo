import { createApp, nextTick } from "vue";
import { createPinia } from "pinia";
import { createRouter, createMemoryHistory } from "vue-router";
import DashboardView from "../../src/views/DashboardView.vue";
import IssuesView from "../../src/views/IssuesView.vue";
import DocumentsView from "../../src/views/DocumentsView.vue";
import CalendarView from "../../src/views/CalendarView.vue";
import api from "../../src/api";
import "../../src/theme.css";
import "../../src/style.css";
if (!import.meta.env.DEV) throw new Error("Development fixture only");
if (new URLSearchParams(location.search).has("compact"))
  document.body.style.width = "390px";
const host = document.querySelector("#fixture"),
  output = document.querySelector("#results");
const errors = [],
  results = [];
let app,
  calls = 0,
  fail = false,
  calendarParams;
const original = Array.from({ length: 6 }, (_, i) => ({
  id: `rent_charge:${i + 1}`,
  type: "rent_overdue",
  entity: { type: "rent_charge", id: i + 1 },
  property: { id: 1, name: "Apartamento de la plaza de San Nicolás" },
  priority: {
    code: i ? "upcoming" : "overdue",
    rank: i ? 3 : 0,
    label: i ? "Próximamente" : "Fecha superada",
  },
  title: i ? "Alquiler pendiente" : "Alquiler cobrado parcialmente",
  date: "2026-09-24",
  amount: "399.99",
  description: {
    text: i
      ? "Saldo según los cobros registrados."
      : "<img src=x onerror=alert(1)> Texto registrado, no instrucciones.",
  },
  action: { mode: "navigate", path: "/leases/8", label: "Ver mensualidad" },
  evidence: { lease_id: 8 },
}));
let items = structuredClone(original);
const summary = () => ({
  total: items.length,
  currency: "EUR",
  timezone: "UTC",
  windows_days: { rent: 14, lease: 60, issue: 14, document: 30, reminder: 14 },
});
api.get = async (path, options) => {
  if (path === "/dashboard") {
    calls++;
    if (fail) throw new Error("Synthetic network error");
    return {
      data: {
        period: "2026-09",
        metrics: {},
        properties: [],
        attention: items,
        attention_summary: summary(),
      },
    };
  }
  if (path === "/issues")
    return {
      data: {
        data: [
          {
            id: 3,
            title: "Grifo",
            property: { name: "Centro" },
            status: "open",
          },
        ],
        last_page: 1,
      },
    };
  if (path === "/documents")
    return {
      data: {
        data: [
          { id: 4, name: "Documento de prueba", size: 1000, category: "other" },
        ],
        last_page: 1,
      },
    };
  if (path === "/calendar") {
    calendarParams = options.params;
    return {
      data: {
        events: [
          {
            id: "reminder-5",
            reminder_id: 5,
            type: "reminder",
            title: "Visita",
            date: "2020-01-10",
            starts_at: "2020-01-10T10:00:00Z",
          },
        ],
      },
    };
  }
  return { data: { data: [], last_page: 1 } };
};
const settle = async () => {
  await new Promise((resolve) => setTimeout(resolve, 0));
  for (let i = 0; i < 10; i++) {
    await Promise.resolve();
    await nextTick();
  }
};
function check(value, text) {
  if (!value) throw new Error(text);
}
async function mount(component, path = "/dashboard") {
  app?.unmount();
  host.innerHTML = "";
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: "/:pathMatch(.*)*", component: { template: "<div />" } }],
  });
  await router.push(path);
  await router.isReady();
  app = createApp(component);
  app.config.errorHandler = (e) => errors.push(e.message);
  app.use(createPinia()).use(router).mount(host);
  await settle();
  return router;
}
try {
  const router = await mount(DashboardView);
  check(
    host.querySelectorAll(".attention-facts li").length === 4,
    "Four initial facts",
  );
  check(host.textContent.includes("Hay 6 asuntos"), "Count from backend");
  check(host.textContent.includes("399,99 €"), "Exact cents");
  check(
    !host.querySelector(".attention-panel img"),
    "Text must not become HTML",
  );
  host.querySelector(".attention-panel > button").click();
  await settle();
  check(
    host.querySelectorAll(".attention-facts li").length === 6,
    "Expand every item",
  );
  host.querySelector(".attention-facts a").click();
  await settle();
  check(
    router.currentRoute.value.path === "/leases/8",
    "Authorized destination",
  );
  results.push("PASS: list, exact amount, escaping, expansion and navigation");
  items = [];
  window.dispatchEvent(new Event("alquivo:finance-changed"));
  await settle();
  check(
    calls === 2 && host.textContent.includes("Todo al día."),
    "Payment refresh must clear attention",
  );
  check(
    !host.querySelector(".attention-facts"),
    "No fabricated empty-state items",
  );
  results.push("PASS: resolved payment refresh and empty state");
  fail = true;
  window.dispatchEvent(new Event("alquivo:leases-changed"));
  await settle();
  check(
    host.textContent.includes("No hemos podido preparar tu resumen"),
    "Load errors must not claim all clear",
  );
  fail = false;
  results.push("PASS: unavailable data is not all clear");
  await mount(IssuesView, "/issues#issue-3");
  check(
    document.activeElement?.id === "issue-3",
    "Focus specific issue after load",
  );
  await mount(DocumentsView, "/documents#document-4");
  check(
    document.activeElement?.id === "document-4",
    "Focus specific document after load",
  );
  await mount(CalendarView, "/calendar?date=2020-01-10#reminder-5");
  check(
    document.activeElement?.id === "reminder-5",
    "Focus historical reminder",
  );
  check(
    calendarParams.from === "2020-01-10 00:00:00" &&
      calendarParams.to === "2020-01-10 23:59:59",
    "Historical reminder date interval",
  );
  results.push(
    "PASS: issue/document/reminder record anchors including past dates",
  );
  items = structuredClone(original);
  await mount(DashboardView);
  const panel = host.querySelector(".attention-panel");
  check(panel.scrollWidth <= panel.clientWidth + 1, "No panel overflow");
  check(errors.length === 0, errors.join("; "));
  results.push("PASS: responsive panel and no Vue errors");
  window.scrollTo({ top: 0, behavior: "instant" });
  document.body.dataset.result = "passed";
} catch (e) {
  results.push(`FAIL: ${e.message}`);
  document.body.dataset.result = "failed";
}
output.textContent = results.join("\n");
