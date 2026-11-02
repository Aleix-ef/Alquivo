<script setup>
import { computed } from "vue";
import { formatMoney } from "../money.js";
import { shortDate } from "../datePresentation.js";
import { paymentWindowLabel } from "../paymentWindow.js";
import { scenePropertyFacts } from "../portfolioScene.js";

const props = defineProps({
  property: { type: Object, required: true },
  currency: { type: String, default: "EUR" },
});
const facts = computed(() => scenePropertyFacts(props.property));
const contracts = computed(() =>
  (props.property.leases || []).filter((lease) => lease.status === "active"),
);
const money = (value) => formatMoney(value, props.currency);
const percent = (value) =>
  new Intl.NumberFormat("es-ES", { maximumFractionDigits: 2 }).format(value) +
  " %";
const tenantNames = (lease) =>
  (lease.participants || [])
    .filter((person) => person.pivot?.role === "tenant")
    .map((person) => person.name)
    .filter(Boolean)
    .join(", ");
</script>

<template>
  <div
    class="portfolio-scene-detail"
    role="region"
    :aria-label="`Información de ${property.name}`"
  >
    <p class="portfolio-detail-location">
      <span :class="{ 'is-rented': facts.rented }">{{
        facts.rented ? "Alquilado" : "Sin contrato activo"
      }}</span>
      <span v-if="property.city"> · {{ property.city }}</span>
    </p>
    <dl>
      <div>
        <dt>Renta contratada / mes</dt>
        <dd>
          {{
            facts.monthlyRent === null
              ? facts.rented
                ? "Sin importe registrado"
                : "Sin contrato activo"
              : money(facts.monthlyRent)
          }}
        </dd>
      </div>
      <div>
        <dt>Valor estimado</dt>
        <dd>
          {{ facts.value === null ? "Sin valoración" : money(facts.value) }}
        </dd>
      </div>
      <div>
        <dt>Rentabilidad bruta</dt>
        <dd>
          {{
            facts.grossYield === null
              ? "No disponible"
              : percent(facts.grossYield)
          }}
        </dd>
      </div>
      <div v-if="contracts.length === 1">
        <dt>Días de cobro</dt>
        <dd>{{ paymentWindowLabel(contracts[0]) }}</dd>
      </div>
    </dl>
    <p v-if="facts.grossYield !== null" class="portfolio-detail-method">
      Renta anualizada sobre valor estimado, antes de gastos e impuestos.
    </p>
    <ul
      v-if="contracts.length"
      class="portfolio-detail-contracts"
      aria-label="Contratos activos"
    >
      <li
        v-for="(lease, index) in contracts.slice(0, 2)"
        :key="lease.id ?? index"
      >
        <strong>{{
          tenantNames(lease) ||
          (contracts.length > 1 ? `Contrato ${index + 1}` : "Contrato")
        }}</strong>
        <span
          >{{
            lease.start_date
              ? shortDate(lease.start_date)
              : "Inicio no registrado"
          }}
          →
          {{
            lease.end_date
              ? shortDate(lease.end_date)
              : "Sin fecha de fin registrada"
          }}</span
        >
      </li>
      <li v-if="contracts.length > 2" class="portfolio-detail-more">
        Y {{ contracts.length - 2 }} más en la ficha.
      </li>
    </ul>
    <RouterLink :to="`/properties/${property.id}`"
      >Ver ficha del inmueble <span aria-hidden="true">↗</span></RouterLink
    >
  </div>
</template>
