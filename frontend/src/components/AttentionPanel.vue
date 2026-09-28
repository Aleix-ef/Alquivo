<script setup>
import { computed, ref } from "vue";
import { CircleCheck, ArrowUpRight } from "@lucide/vue";
import {
  attentionDestination,
  attentionMoney,
  attentionDate,
} from "../attention";
const props = defineProps({
  items: { type: Array, default: () => [] },
  summary: { type: Object, required: true },
});
const visible = ref(2);
const shown = computed(() => props.items.slice(0, visible.value));
</script>

<template>
  <section
    id="attention"
    class="panel attention-panel"
    aria-labelledby="attention-heading"
  >
    <header>
      <p class="eyebrow">Según tus registros</p>
      <h2 id="attention-heading">Qué necesita mi atención</h2>
      <p class="attention-intro" role="status">
        {{
          summary.total
            ? `Hay ${summary.total} ${summary.total === 1 ? "asunto que necesita" : "asuntos que necesitan"} tu atención.`
            : "Todo al día."
        }}
      </p>
    </header>
    <ul v-if="items.length" class="attention-facts">
      <li
        v-for="item in shown"
        :key="item.id"
        :data-priority="item.priority.code"
      >
        <div class="attention-fact-main">
          <span class="attention-badge">{{ item.priority.label }}</span>
          <h3>{{ item.title }}</h3>
          <p class="attention-property">
            {{ item.property?.name || "Cartera general" }}
          </p>
          <p>{{ item.description.text }}</p>
          <time v-if="item.date" :datetime="item.date">{{
            attentionDate(item.date)
          }}</time>
          <span v-else>Sin fecha límite registrada</span>
        </div>
        <div class="attention-fact-action">
          <strong v-if="item.amount !== null">{{
            attentionMoney(item.amount, summary.currency)
          }}</strong>
          <RouterLink
            v-if="attentionDestination(item)"
            :to="attentionDestination(item)"
            class="button secondary"
            >{{ item.action.label }}
            <ArrowUpRight :size="16" aria-hidden="true"
          /></RouterLink>
        </div>
      </li>
    </ul>
    <div v-else class="attention-empty">
      <CircleCheck :size="28" aria-hidden="true" />
      <p>
        No hay avisos dentro de los plazos que revisamos. Se tienen en cuenta
        sólo los datos que has registrado.
      </p>
    </div>
    <button
      v-if="visible < items.length"
      type="button"
      class="button secondary"
      @click="visible += 10"
    >
      Ver más avisos ({{ items.length - visible }} pendientes de mostrar)
    </button>
    <details class="attention-rules">
      <summary>Qué se revisa y cómo se ordena</summary>
      <p>
        Primero, fechas superadas; después, lo que toca hoy, incidencias
        marcadas para revisar y próximos vencimientos. Un alquiler parcialmente
        cobrado aparece una sola vez, con su saldo pendiente.
      </p>
      <p>
        Próximos plazos: alquileres {{ summary.windows_days.rent }} días,
        contratos {{ summary.windows_days.lease }}, incidencias
        {{ summary.windows_days.issue }}, documentos
        {{ summary.windows_days.document }} y recordatorios
        {{ summary.windows_days.reminder }}. También se muestran registros
        anteriores sin resolver.
      </p>
      <p>
        No se generan mensualidades ni se interpreta la validez legal de
        contratos o documentos. Zona horaria: {{ summary.timezone }}.
      </p>
    </details>
  </section>
</template>

<style scoped>
.attention-panel {
  padding: clamp(1.2rem, 3vw, 2rem);
  margin-bottom: 24px;
}
.attention-panel h2 {
  font-size: clamp(1.25rem, 2vw, 1.6rem);
  margin: 0.3rem 0;
}
.attention-intro {
  color: var(--muted);
  margin: 0.6rem 0 1.4rem;
  font-size: 1rem;
}
.attention-facts {
  list-style: none;
  padding: 0;
  margin: 0 0 1rem;
}
.attention-facts li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
  border-top: 1px solid var(--line);
  padding: 1.3rem 0;
}
.attention-fact-main {
  min-width: 0;
  overflow-wrap: anywhere;
}
.attention-fact-main h3 {
  font-size: 1.06rem;
  margin: 0.55rem 0;
}
.attention-fact-main p {
  margin: 0.35rem 0;
  color: var(--muted);
  font-size: 0.95rem;
  line-height: 1.6;
}
.attention-fact-main .attention-property {
  color: var(--ink);
  font-weight: 600;
}
.attention-fact-main time,
.attention-fact-main > span:last-child {
  display: block;
  margin-top: 0.65rem;
  font-size: 0.9rem;
  color: var(--muted);
}
.attention-badge {
  display: inline-block;
  border: 1px solid var(--line);
  border-radius: 99px;
  padding: 0.3rem 0.65rem;
  font-size: 0.85rem;
}
li[data-priority="overdue"] .attention-badge {
  color: #ffbaaa;
  background: #492521;
  border-color: #875349;
}
li[data-priority="today"] .attention-badge {
  color: #f6da9a;
  background: #40351f;
  border-color: #78673e;
}
.attention-fact-action {
  display: flex;
  align-items: flex-end;
  flex-direction: column;
  gap: 0.8rem;
  flex-shrink: 0;
}
.attention-fact-action strong {
  font-size: 1.15rem;
  font-variant-numeric: tabular-nums;
}
.attention-fact-action .button {
  min-height: 44px;
}
.attention-empty {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding-bottom: 1rem;
  color: var(--muted);
  line-height: 1.6;
}
.attention-empty svg {
  flex-shrink: 0;
  color: var(--accent);
}
.attention-rules {
  border-top: 1px solid var(--line);
  margin-top: 1rem;
  padding-top: 1rem;
  font-size: 0.95rem;
  line-height: 1.7;
  color: var(--muted);
}
.attention-rules summary {
  cursor: pointer;
  min-height: 32px;
  color: var(--ink);
}
@media (max-width: 640px) {
  .attention-facts li {
    align-items: stretch;
    flex-direction: column;
    gap: 1rem;
  }
  .attention-fact-action {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
  }
}
</style>
