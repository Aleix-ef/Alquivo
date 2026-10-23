<script setup>
const payment_day_start = defineModel("start", { type: [String, Number] });
const payment_day = defineModel("end", { type: [String, Number] });
</script>

<template>
  <fieldset class="payment-window-fields">
    <legend>Plazo de cobro mensual</legend>
    <div class="payment-window-inputs">
      <label
        >Del día<input
          v-model="payment_day_start"
          type="number"
          min="1"
          max="31"
          step="1"
          inputmode="numeric"
          required
      /></label>
      <label
        >Al día<input
          v-model="payment_day"
          type="number"
          min="1"
          max="31"
          step="1"
          inputmode="numeric"
          required
      /></label>
    </div>
    <small class="muted"
      >Ambos días incluidos. En meses más cortos se usa el último día del
      mes.</small
    >
  </fieldset>
</template>

<style scoped>
.payment-window-fields {
  grid-column: 1 / -1;
  margin: 0;
  padding: 0;
  border: 0;
  min-width: 0;
}
.payment-window-fields legend {
  margin-bottom: 0.65rem;
  font-weight: 600;
}
.payment-window-inputs {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rem;
}
.payment-window-fields small {
  display: block;
  margin-top: 0.5rem;
  line-height: 1.5;
}
</style>
