<script setup>
import { computed, ref, watch } from "vue";
import { Plus, Trash2, Save, Download } from "@lucide/vue";
import { fiscalDraft, fiscalMoney, fiscalPayload } from "../fiscality";
const props = defineProps({
  item: Object,
  categories: Object,
  year: Number,
  saving: Boolean,
  errors: Object,
  downloading: Boolean,
});
const emit = defineEmits(["save", "dirty", "download"]);
const form = ref(fiscalDraft(props.item.inputs));
const original = JSON.stringify(form.value);
const dirty = computed(() => JSON.stringify(form.value) !== original);
watch(dirty, (value) => emit("dirty", value), { immediate: true });
const moneyFields = [
  [
    "building_cost",
    "Coste de compra de la construcción",
    "Incluye gastos de adquisición atribuibles a la construcción. Excluye el suelo.",
  ],
  [
    "cadastral_building",
    "Valor catastral de construcción",
    "Lo encontrarás desglosado en el recibo del IBI.",
  ],
  [
    "prior_depreciation",
    "Amortización acumulada anterior",
    "Hasta el cierre del año anterior, aunque se declarara fuera de Alquivo. Escribe 0 solo si corresponde.",
  ],
];
function addPeriod() {
  form.value.periods.push({ start: "", end: "", use: "rented" });
  form.value.records_reviewed = false;
}
function addExpense() {
  form.value.expenses.push({
    category: "ibi",
    amount: "",
    allocation: "annual",
    description: "",
    document_id: null,
  });
  form.value.records_reviewed = false;
}
function submit() {
  emit("save", fiscalPayload(form.value));
}
function changed(event) {
  if (event.target.dataset.review !== "final")
    form.value.records_reviewed = false;
}
function reset() {
  form.value = JSON.parse(original);
}
defineExpose({ reset });
</script>

<template>
  <form class="fiscal-form" @submit.prevent="submit" @input="changed">
    <header class="fiscal-form-heading">
      <div>
        <p class="eyebrow">Ficha fiscal · {{ year }}</p>
        <h2>{{ item.property.name }}</h2>
        <p>{{ item.property.address_line }}</p>
      </div>
      <RouterLink
        :to="`/properties/${item.property.id}`"
        class="button secondary"
        >Ver inmueble</RouterLink
      >
    </header>
    <div class="fiscal-reference">
      <strong>Lo que ya has registrado en Alquivo</strong>
      <div class="fiscal-source-grid">
        <span
          >Cargos exigibles
          <b>{{ fiscalMoney(item.source.charge_income_cents) }}</b></span
        >
        <span
          >Ingresos cobrados
          <b>{{ fiscalMoney(item.source.paid_income_cents) }}</b></span
        >
        <span
          >Gastos pagados
          <b>{{ fiscalMoney(item.source.paid_expenses_cents) }}</b></span
        >
      </div>
      <p>
        Son referencias del 100 % del inmueble. Comprueba que el año está
        completo; un cobro pendiente puede seguir siendo un ingreso fiscal.
      </p>
    </div>
    <fieldset :disabled="saving">
      <legend>1. Titularidad y uso</legend>
      <p>
        Indica el periodo del año en el que te corresponde declarar este
        inmueble y tu porcentaje de plena propiedad.
      </p>
      <div class="fiscal-fields">
        <label
          >Tu titularidad (%)<input
            v-model="form.ownership_percent"
            inputmode="decimal"
            placeholder="Ej. 50"
        /></label>
        <label
          >Desde<input
            v-model="form.owned_from"
            type="date"
            :min="`${year}-01-01`"
            :max="`${year}-12-31`"
        /></label>
        <label
          >Hasta<input
            v-model="form.owned_to"
            type="date"
            :min="`${year}-01-01`"
            :max="`${year}-12-31`"
        /></label>
      </div>
      <p>
        Completa todos los días de ese periodo. «Disponible» incluye segunda
        residencia y días vacíos a tu disposición.
      </p>
      <div
        v-for="(period, index) in form.periods"
        :key="index"
        class="fiscal-period-row"
      >
        <label
          >Inicio<input
            v-model="period.start"
            type="date"
            required
            :min="`${year}-01-01`"
            :max="`${year}-12-31`"
        /></label>
        <label
          >Fin<input
            v-model="period.end"
            type="date"
            required
            :min="`${year}-01-01`"
            :max="`${year}-12-31`"
        /></label>
        <label
          >Uso<select v-model="period.use">
            <option value="rented">Alquilado como vivienda habitual</option>
            <option value="available">A mi disposición</option>
            <option value="habitual">Mi vivienda habitual</option>
          </select></label
        >
        <button
          class="icon-button"
          type="button"
          :aria-label="`Eliminar periodo ${index + 1}`"
          @click="form.periods.splice(index, 1)"
        >
          <Trash2 :size="16" />
        </button>
      </div>
      <button
        class="button secondary"
        type="button"
        :disabled="form.periods.length >= 24"
        @click="addPeriod"
      >
        <Plus :size="16" />Añadir periodo
      </button>
      <label class="fiscal-check"
        ><input v-model="form.ordinary_case" type="checkbox" /><span
          >Es una vivienda adquirida por compra y mi porcentaje se mantiene
          durante el periodo indicado. He revisado los casos especiales de abajo
          y ninguno se aplica.</span
        ></label
      >
      <details class="fiscal-help">
        <summary>Qué casos requieren todavía un cálculo específico</summary>
        <p>
          Herencia, donación, usufructo, actividad económica, alquiler temporal
          o por habitaciones, alquiler a familiares, varios contratos con
          condiciones fiscales distintas, rehabilitaciones o mejoras pendientes
          de amortizar, mobiliario amortizable, saldos de dudoso cobro, rentas
          irregulares y devoluciones de intereses hipotecarios. Si alguno se
          aplica, guarda la ficha sin marcar esta confirmación para que aparezca
          pendiente de revisión.
        </p>
      </details>
    </fieldset>
    <fieldset :disabled="saving">
      <legend>2. Ingresos y contrato</legend>
      <div class="fiscal-fields">
        <label
          >Ingresos íntegros exigibles (€)<input
            v-model="form.income"
            inputmode="decimal"
            placeholder="Importe del 100 % del inmueble"
          /><small
            >Sin fianzas reembolsables. Incluye rentas exigibles aunque no estén
            cobradas; revisa los impagos.</small
          ></label
        >
        <label
          >Fecha fiscal del contrato<input
            v-model="form.contract_start"
            type="date"
            :max="`${year}-12-31`"
          /><small
            >Contrato que origina las rentas. Una prórroga no siempre constituye
            un contrato nuevo.</small
          ></label
        >
        <label
          >Tratamiento de la vivienda<select v-model="form.reduction_case">
            <option :value="null">Pendiente de comprobar</option>
            <option value="standard">
              Alquiler habitual, reducción ordinaria
            </option>
            <option value="special">
              Podría aplicar una reducción especial
            </option>
            <option value="unknown">Necesito revisarlo</option></select
          ><small
            >La reducción ordinaria se determina por la fecha del contrato.
            Confirma que cumple sus requisitos legales.</small
          ></label
        >
      </div>
      <details class="fiscal-help">
        <summary>
          Consultar cargos registrados ({{ item.source.charge_count }})
        </summary>
        <p v-if="!item.source.charges.length">
          No hay cargos en este ejercicio. Puedes completar el ingreso fiscal
          con tus registros.
        </p>
        <div
          v-for="charge in item.source.charges"
          :key="charge.id"
          class="fiscal-detail-row"
        >
          <span>{{ charge.date }} · Cargo #{{ charge.id }}</span
          ><strong>{{ charge.amount }} €</strong>
        </div>
      </details>
    </fieldset>
    <fieldset :disabled="saving">
      <legend>3. Gastos y justificantes</legend>
      <p>
        Introduce el importe del 100 % del inmueble. «Anual» significa coste del
        año natural completo, que prorratearemos por días alquilados. «Solo
        alquiler» significa que ya has separado el importe correspondiente a ese
        uso.
      </p>
      <p class="fiscal-hint">
        La devolución del préstamo y las mejoras no se incluyen como gasto
        corriente. Intereses y reparaciones tienen un límite conjunto.
      </p>
      <article
        v-for="(expense, index) in form.expenses"
        :key="index"
        class="fiscal-expense-row"
      >
        <div class="fiscal-fields">
          <label
            >Categoría<select v-model="expense.category">
              <option
                v-for="(label, key) in categories"
                :key="key"
                :value="key"
              >
                {{ label }}
              </option>
            </select></label
          >
          <label
            >Importe (€)<input
              v-model="expense.amount"
              required
              inputmode="decimal"
          /></label>
          <label
            >Asignación<select v-model="expense.allocation">
              <option value="annual">Anual, prorratear</option>
              <option value="rental">Solo alquiler, ya asignado</option>
            </select></label
          >
          <label
            >Concepto<input
              v-model="expense.description"
              required
              maxlength="200"
              placeholder="Ej. IBI anual 2025"
          /></label>
          <label
            >Justificante<select v-model="expense.document_id">
              <option :value="null">Sin vincular</option>
              <option
                v-for="document in item.source.documents"
                :key="document.id"
                :value="document.id"
              >
                {{ document.name }}
              </option>
            </select></label
          >
        </div>
        <button
          class="button secondary"
          type="button"
          @click="form.expenses.splice(index, 1)"
        >
          <Trash2 :size="15" />Quitar gasto
        </button>
      </article>
      <button
        class="button secondary"
        type="button"
        :disabled="form.expenses.length >= 60"
        @click="addExpense"
      >
        <Plus :size="16" />Añadir gasto
      </button>
    </fieldset>
    <fieldset :disabled="saving">
      <legend>4. Amortización y catastro</legend>
      <p>
        Usa valores del 100 % del inmueble. La construcción y el historial son
        necesarios si hubo alquiler; el catastro total y su revisión, si hubo
        días a tu disposición.
      </p>
      <div class="fiscal-fields">
        <label v-for="[key, label, help] in moneyFields" :key="key"
          >{{ label }} (€)<input
            v-model="form[key]"
            inputmode="decimal"
          /><small>{{ help }}</small></label
        >
        <label
          >Amortización anual (%)<input
            v-model="form.depreciation_rate"
            inputmode="decimal"
            placeholder="Entre 1 y 3"
          /><small
            >Comprueba el criterio aplicado en tu historial fiscal.</small
          ></label
        >
        <label
          >Valor catastral total (€)<input
            v-model="form.cadastral_total"
            inputmode="decimal"
          /><small>Incluye suelo y construcción.</small></label
        >
        <label
          >Valoración colectiva catastral<select
            v-model="form.cadastral_revision"
          >
            <option :value="null">Pendiente de comprobar</option>
            <option value="since_2012">
              Notificada, con efectos desde 2012 hasta 2025
            </option>
            <option value="before_2012">
              Notificada, anterior a 2012 / sin revisión posterior
            </option>
            <option value="unknown">Sin valor notificado o no lo sé</option>
          </select></label
        >
      </div>
    </fieldset>
    <fieldset :disabled="saving">
      <legend>5. Importes pendientes de otros años</legend>
      <p>
        Solo financiación y reparaciones pendientes de deducir. Indica el saldo
        que quedaba al empezar {{ year }}, al 100 % del inmueble y con la misma
        titularidad. Se aplican primero los saldos anteriores; los caducados se
        muestran aparte.
      </p>
      <div
        v-for="(carry, index) in form.carryforwards"
        :key="index"
        class="fiscal-period-row"
      >
        <label
          >Año de origen<select v-model="carry.year">
            <option v-for="offset in 5" :key="offset" :value="year - offset">
              {{ year - offset }}
            </option>
          </select></label
        >
        <label
          >Saldo pendiente (€)<input
            v-model="carry.amount"
            inputmode="decimal"
            required
        /></label>
        <button
          class="icon-button"
          type="button"
          :aria-label="`Quitar saldo de ${carry.year}`"
          @click="form.carryforwards.splice(index, 1)"
        >
          <Trash2 :size="16" />
        </button>
      </div>
      <button
        class="button secondary"
        type="button"
        :disabled="form.carryforwards.length >= 5"
        @click="form.carryforwards.push({ year: year - 1, amount: '' })"
      >
        <Plus :size="16" />Añadir saldo anterior
      </button>
    </fieldset>
    <fieldset :disabled="saving">
      <legend>6. Revisión de la ficha</legend>
      <label
        >Notas para revisar con tu gestor<textarea
          v-model="form.notes"
          rows="3"
          maxlength="1000"
        />
      </label>
      <label class="fiscal-check"
        ><input
          v-model="form.records_reviewed"
          data-review="final"
          type="checkbox"
        /><span
          >He revisado los importes exigibles, la asignación de los gastos, los
          justificantes y el historial. He incluido los saldos pendientes que
          correspondan y comprobado los requisitos de la reducción por
          vivienda.</span
        ></label
      >
      <p>
        Puedes guardar una ficha incompleta y continuar después. El cálculo
        aparecerá cuando estén los datos necesarios y el caso esté cubierto.
      </p>
    </fieldset>
    <div
      v-if="Object.keys(errors || {}).length"
      class="fiscal-error"
      role="alert"
    >
      <strong>Revisa estos datos:</strong>
      <p v-for="(messages, field) in errors" :key="field">{{ messages[0] }}</p>
    </div>
    <footer class="fiscal-form-actions">
      <span>{{
        dirty ? "Tienes cambios sin guardar" : "Datos guardados"
      }}</span>
      <button
        v-if="dirty"
        class="button secondary"
        type="button"
        :disabled="saving"
        @click="reset"
      >
        Descartar cambios
      </button>
      <button class="button" type="submit" :disabled="saving || !dirty">
        <Save :size="16" />{{ saving ? "Guardando…" : "Guardar ficha" }}
      </button>
      <button
        class="button secondary"
        type="button"
        :disabled="dirty || saving || downloading"
        @click="emit('download', 'pdf')"
      >
        <Download :size="16" />PDF del inmueble
      </button>
    </footer>
  </form>
</template>
