<script setup>
import { computed, onBeforeUnmount, ref, watch } from "vue";
import api from "../api";
import {
  checkedActionProposal,
  actionLabels,
  actionResultPath,
  actionRefreshEvents,
  actionEditFields,
  actionEditProblem,
  expenseCategories,
  mutateActionProposal,
} from "../assistantActions";

const props = defineProps({
  proposalId: { type: String, required: true },
  enabled: { type: Boolean, default: false },
});
const emit = defineEmits(["executed", "navigate"]);
const proposal = ref(null),
  verified = ref(false),
  busy = ref(false),
  editing = ref(false);
const fields = ref({}),
  error = ref(""),
  notice = ref("");
let generation = 0,
  controller,
  notifiedId = null,
  expiryTimer;
const locallyExpired = ref(false);
const status = computed(() =>
  locallyExpired.value && proposal.value?.status === "pending"
    ? "expired"
    : proposal.value?.status,
);
const canAct = computed(
  () =>
    props.enabled &&
    verified.value &&
    status.value === "pending" &&
    !busy.value,
);
const labels = {
  pending: "Pendiente de tu confirmación",
  cancelled: "Propuesta cancelada",
  expired: "Propuesta caducada",
};
const actionLabel = computed(() => actionLabels[proposal.value?.type]);
const resultPath = computed(
  () => proposal.value && actionResultPath(proposal.value),
);
const pendingMessage = computed(
  () =>
    ({
      expense: "No se ha creado ningún gasto.",
      contact_phone: "El teléfono del contacto todavía no ha cambiado.",
      rent_payment:
        "No se ha registrado ningún cobro. Un cobro parcial dejará el resto del recibo pendiente.",
      property_note:
        "No se ha añadido ninguna nota. Se añadirá al final, sin sustituir las notas existentes.",
    })[proposal.value?.type],
);
const money = (value) =>
  new Intl.NumberFormat("es-ES", {
    style: "currency",
    currency: proposal.value?.preview.currency || "EUR",
  }).format(Number(value));
const date = (value) => {
  const parsed = new Date(`${value?.slice(0, 10)}T12:00:00`);
  return Number.isNaN(parsed.getTime())
    ? "Sin fecha"
    : parsed.toLocaleDateString("es-ES", { dateStyle: "long" });
};

function apply(current, { notify = false } = {}) {
  proposal.value = checkedActionProposal(
    current,
    props.proposalId,
    proposal.value?.type,
  );
  verified.value = true;
  clearTimeout(expiryTimer);
  const remaining = new Date(current.expires_at).getTime() - Date.now();
  locallyExpired.value =
    current.status === "pending" &&
    (!Number.isFinite(remaining) || remaining <= 0);
  if (current.status === "pending" && remaining > 0) {
    expiryTimer = setTimeout(
      () => {
        locallyExpired.value = true;
      },
      Math.min(remaining, 2147483647),
    );
  }
  if (notify && current.status === "executed" && notifiedId !== current.id) {
    notifiedId = current.id;
    for (const { name, detail } of actionRefreshEvents(current))
      window.dispatchEvent(new CustomEvent(name, { detail }));
    emit("executed", current);
  }
}

function requestContext() {
  controller?.abort();
  controller = new AbortController();
  return {
    version: ++generation,
    options: { signal: controller.signal, timeout: 20000 },
  };
}

async function refresh() {
  const { version, options } = requestContext();
  busy.value = true;
  verified.value = false;
  error.value = "";
  editing.value = false;
  try {
    const { data } = await api.get(
      `/assistant/proposals/${props.proposalId}`,
      options,
    );
    if (version !== generation) return;
    apply(data.proposal, { notify: true });
  } catch (exception) {
    if (version !== generation) return;
    error.value =
      exception.response?.status === 404
        ? "Esta propuesta ya no está disponible. Puedes preparar una nueva desde el chat."
        : "No hemos podido comprobar esta propuesta. No repitas la misma acción: comprueba primero su estado.";
  } finally {
    if (version === generation) busy.value = false;
  }
}

function beginEdit() {
  if (!canAct.value) return;
  fields.value = actionEditFields(proposal.value);
  editing.value = true;
  error.value = "";
  notice.value = "";
}

async function mutate(action) {
  if (!canAct.value || (action === "confirm" && editing.value)) return;
  const requestedRevision = proposal.value.revision;
  if (action === "revise") {
    error.value = actionEditProblem(proposal.value, fields.value);
    if (error.value) return;
  }
  const { version, options } = requestContext();
  busy.value = true;
  verified.value = false;
  error.value = "";
  notice.value = "";
  try {
    const result = await mutateActionProposal(
      api,
      action,
      proposal.value,
      fields.value,
      options,
    );
    if (version !== generation) return;
    if (result.proposal) {
      apply(result.proposal, { notify: true });
      if (
        !result.error ||
        result.proposal.status !== "pending" ||
        result.proposal.revision !== requestedRevision ||
        result.error.response?.status === 409
      )
        editing.value = false;
    }
    if (result.error && result.proposal?.status !== "executed") {
      const validation = Object.values(
        result.error.response?.data?.errors || {},
      ).flat();
      error.value =
        result.error.response?.status === 409
          ? "La propuesta ha cambiado. Revisa los datos actualizados antes de confirmar."
          : validation[0] ||
            "No hemos podido confirmar el resultado de la operación. Comprueba su estado antes de continuar.";
    } else if (action === "revise") {
      notice.value = `Cambios guardados en la propuesta. Revisa los datos y pulsa «${actionLabel.value.confirm}» para aplicarlos.`;
    }
  } catch {
    if (version === generation)
      error.value =
        "No hemos podido comprobar el resultado. Comprueba el estado antes de continuar.";
  } finally {
    if (version === generation) busy.value = false;
  }
}

watch(
  () => props.proposalId,
  () => {
    proposal.value = null;
    notice.value = "";
    notifiedId = null;
    refresh();
  },
  { immediate: true },
);
onBeforeUnmount(() => {
  generation++;
  controller?.abort();
  clearTimeout(expiryTimer);
});
</script>

<template>
  <section
    class="assistant-action-preview"
    :aria-busy="busy"
    :aria-label="actionLabel?.title || 'Propuesta del asistente'"
  >
    <header>
      <strong>{{
        status === "executed"
          ? actionLabel.executed
          : labels[status] || "Comprobando propuesta…"
      }}</strong>
      <span v-if="proposal">Revisión {{ proposal.revision }}</span>
    </header>
    <template v-if="proposal">
      <dl class="assistant-action-summary">
        <div v-if="proposal.type !== 'contact_phone'">
          <dt>Inmueble</dt>
          <dd>
            {{
              proposal.preview.property?.name || "Gasto general de la cartera"
            }}
          </dd>
        </div>
        <template v-if="proposal.type === 'contact_phone'">
          <div>
            <dt>Contacto</dt>
            <dd>{{ proposal.preview.contact.name }}</dd>
          </div>
          <div>
            <dt>Teléfono actual</dt>
            <dd>{{ proposal.preview.previous_phone || "Sin teléfono" }}</dd>
          </div>
          <div>
            <dt>Nuevo teléfono</dt>
            <dd class="assistant-action-amount">
              {{ proposal.preview.phone }}
            </dd>
          </div>
        </template>
        <div v-if="proposal.type === 'property_note'">
          <dt>Nota que se añadirá</dt>
          <dd class="assistant-action-note">{{ proposal.preview.note }}</dd>
        </div>
        <template v-if="['expense', 'rent_payment'].includes(proposal.type)">
          <div v-if="proposal.type === 'rent_payment'">
            <dt>Recibo</dt>
            <dd>
              {{ proposal.preview.rent_charge.period }} · vence el
              {{ date(proposal.preview.rent_charge.due_date) }}
            </dd>
          </div>
          <div v-if="proposal.type === 'rent_payment'">
            <dt>Pendiente antes de este cobro</dt>
            <dd>{{ money(proposal.preview.rent_charge.remaining_amount) }}</dd>
          </div>
          <div>
            <dt>Importe</dt>
            <dd class="assistant-action-amount">
              {{ money(proposal.preview.amount) }}
            </dd>
          </div>
          <div v-if="proposal.type === 'expense'">
            <dt>Concepto</dt>
            <dd>{{ proposal.preview.description }}</dd>
          </div>
          <div v-if="proposal.type === 'expense'">
            <dt>Categoría</dt>
            <dd>
              {{
                expenseCategories.find(
                  (category) => category.value === proposal.preview.category,
                )?.label || proposal.preview.category
              }}
            </dd>
          </div>
          <div v-if="proposal.type === 'expense'">
            <dt>Fecha</dt>
            <dd>{{ date(proposal.preview.transaction_date) }}</dd>
          </div>
          <div v-if="proposal.type === 'rent_payment'">
            <dt>Fecha del cobro</dt>
            <dd>{{ date(proposal.preview.transaction_date) }}</dd>
          </div>
          <div v-if="proposal.type === 'rent_payment'">
            <dt>Medio de pago</dt>
            <dd>{{ proposal.preview.payment_method || "Sin especificar" }}</dd>
          </div>
        </template>
        <div v-if="proposal.type === 'expense'">
          <dt>Estado del gasto</dt>
          <dd>
            {{
              proposal.preview.status === "paid"
                ? "Pagado"
                : "Pendiente de pago"
            }}
          </dd>
        </div>
      </dl>
      <form
        v-if="editing"
        class="assistant-action-form"
        @submit.prevent="mutate('revise')"
      >
        <label v-if="['expense', 'rent_payment'].includes(proposal.type)"
          >Importe ({{ proposal.preview.currency }})<input
            v-model="fields.amount"
            inputmode="decimal"
            required
            :disabled="busy"
        /></label>
        <label v-if="proposal.type === 'expense'"
          >Concepto<input
            v-model="fields.description"
            required
            maxlength="180"
            :disabled="busy"
        /></label>
        <label v-if="proposal.type === 'expense'"
          >Categoría<select v-model="fields.category" :disabled="busy">
            <option
              v-for="category in expenseCategories"
              :key="category.value"
              :value="category.value"
            >
              {{ category.label }}
            </option>
          </select></label
        >
        <label v-if="['expense', 'rent_payment'].includes(proposal.type)"
          >Fecha<input
            v-model="fields.transaction_date"
            type="date"
            required
            :disabled="busy"
        /></label>
        <label v-if="proposal.type === 'expense'"
          >Estado<select v-model="fields.status" :disabled="busy">
            <option value="paid">Pagado</option>
            <option value="pending">Pendiente de pago</option>
          </select></label
        >
        <label v-if="proposal.type === 'contact_phone'"
          >Nuevo teléfono<input
            v-model="fields.phone"
            type="tel"
            autocomplete="off"
            maxlength="30"
            required
            :disabled="busy"
        /></label>
        <label v-if="proposal.type === 'rent_payment'"
          >Medio de pago (opcional)<input
            v-model="fields.payment_method"
            maxlength="40"
            placeholder="Por ejemplo, transferencia"
            :disabled="busy"
        /></label>
        <label v-if="proposal.type === 'property_note'"
          >Nota para añadir<textarea
            v-model="fields.note"
            rows="4"
            maxlength="2000"
            required
            :disabled="busy"
          ></textarea
          ><small>{{ fields.note.length }}/2.000 caracteres</small></label
        >
        <p>
          El contacto, inmueble o recibo de destino no cambia al editar. Para
          elegir otro, cancela esta propuesta y pide una nueva.
        </p>
        <div class="assistant-action-buttons">
          <button class="button primary" type="submit" :disabled="!canAct">
            Guardar cambios de la propuesta
          </button>
          <button
            class="button secondary"
            type="button"
            :disabled="busy"
            @click="editing = false"
          >
            Volver sin guardar
          </button>
        </div>
      </form>
      <template v-else-if="status === 'pending'">
        <p>
          {{ pendingMessage }} Solo se aplicará al pulsar «{{
            actionLabel.confirm
          }}»; escribir «sí» en el chat no lo confirma.
        </p>
        <p v-if="!enabled">
          Las acciones del asistente no están disponibles en este momento.
        </p>
        <div class="assistant-action-buttons">
          <button
            class="button primary"
            type="button"
            :disabled="!canAct"
            @click="mutate('confirm')"
          >
            {{ busy ? "Comprobando…" : actionLabel.confirm }}
          </button>
          <button
            class="button secondary"
            type="button"
            :disabled="!canAct"
            @click="beginEdit"
          >
            Editar propuesta
          </button>
          <button
            class="button secondary"
            type="button"
            :disabled="!canAct"
            @click="mutate('cancel')"
          >
            Cancelar propuesta
          </button>
        </div>
      </template>
      <p v-else-if="status === 'executed'">
        {{ actionLabel.saved }} No necesitas repetir la acción.
      </p>
      <p v-else-if="status === 'expired'">
        El plazo para confirmar ha terminado. Comprueba el estado antes de
        preparar otra propuesta.
      </p>
      <p v-else-if="status === 'cancelled'">
        Esta propuesta no ha modificado tus datos.
      </p>
      <RouterLink
        v-if="resultPath"
        class="assistant-support-link"
        :to="resultPath"
        @click="emit('navigate')"
        >{{ actionLabel.destination }} →</RouterLink
      >
    </template>
    <p v-if="notice" role="status">{{ notice }}</p>
    <p v-if="error" class="assistant-error" role="alert">{{ error }}</p>
    <button
      v-if="!verified && !busy"
      class="button secondary"
      type="button"
      @click="refresh"
    >
      Comprobar estado
    </button>
    <button
      v-else-if="status === 'expired' && !busy"
      class="button secondary"
      type="button"
      @click="refresh"
    >
      Actualizar estado
    </button>
    <p v-if="busy" role="status">Comprobando el estado guardado…</p>
  </section>
</template>
