<script setup>
import { computed, onBeforeUnmount, ref, watch } from "vue";
import api from "../api";
import {
  creationLabels,
  propertyCreationTypes,
} from "../assistantCreationActions.js";
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
  creationEnabled: { type: Boolean, default: false },
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
    (!creationLabels[proposal.value?.type] || props.creationEnabled) &&
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
      property_create: "No se ha creado el inmueble.",
      contact_create: "No se ha creado el contacto ni vinculado a un alquiler.",
      lease_create:
        "Se creará un borrador, sin activar el alquiler ni emitir mensualidades.",
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
        <div
          v-if="
            [
              'expense',
              'rent_payment',
              'property_note',
              'lease_create',
            ].includes(proposal.type)
          "
        >
          <dt>Inmueble</dt>
          <dd>
            {{
              proposal.preview.property?.name || "Gasto general de la cartera"
            }}
          </dd>
        </div>
        <template
          v-if="['property_create', 'contact_create'].includes(proposal.type)"
        >
          <div>
            <dt>Nombre</dt>
            <dd>{{ proposal.preview.name }}</dd>
          </div>
          <div v-if="proposal.type === 'property_create'">
            <dt>Tipo</dt>
            <dd>
              {{
                propertyCreationTypes.find(
                  (t) => t.value === proposal.preview.type,
                )?.label
              }}
            </dd>
          </div>
          <div v-if="proposal.type === 'contact_create'">
            <dt>Tipo de contacto</dt>
            <dd>
              {{ proposal.preview.kind === "company" ? "Empresa" : "Persona" }}
            </dd>
          </div>
        </template>
        <template v-if="proposal.type === 'property_create'">
          <div>
            <dt>Dirección</dt>
            <dd>{{ proposal.preview.address_line }}</dd>
          </div>
          <div>
            <dt>Ciudad</dt>
            <dd>{{ proposal.preview.city || "Sin indicar" }}</dd>
          </div>
          <div>
            <dt>Precio de compra</dt>
            <dd>
              {{
                proposal.preview.purchase_price == null
                  ? "Sin registrar"
                  : money(proposal.preview.purchase_price)
              }}
            </dd>
          </div>
          <div>
            <dt>Valoración actual</dt>
            <dd>
              {{
                proposal.preview.current_value == null
                  ? "Sin registrar"
                  : money(proposal.preview.current_value)
              }}
            </dd>
          </div>
        </template>
        <template v-if="proposal.type === 'contact_create'">
          <div>
            <dt>Correo</dt>
            <dd>{{ proposal.preview.email || "Sin indicar" }}</dd>
          </div>
          <div>
            <dt>Teléfono</dt>
            <dd>{{ proposal.preview.phone || "Sin indicar" }}</dd>
          </div>
        </template>
        <template v-if="proposal.type === 'lease_create'">
          <div>
            <dt>Inquilinos</dt>
            <dd>
              {{ proposal.preview.contacts.map((c) => c.name).join(", ") }}
            </dd>
          </div>
          <div>
            <dt>Inicio</dt>
            <dd>{{ date(proposal.preview.start_date) }}</dd>
          </div>
          <div>
            <dt>Fin</dt>
            <dd>
              {{
                proposal.preview.end_date
                  ? date(proposal.preview.end_date)
                  : "Sin fecha final"
              }}
            </dd>
          </div>
          <div>
            <dt>Renta mensual</dt>
            <dd class="assistant-action-amount">
              {{ money(proposal.preview.monthly_rent) }}
            </dd>
          </div>
          <div>
            <dt>Fianza</dt>
            <dd>{{ money(proposal.preview.deposit_amount) }}</dd>
          </div>
          <div>
            <dt>Día de cobro</dt>
            <dd>{{ proposal.preview.payment_day }}</dd>
          </div>
          <div>
            <dt>Estado</dt>
            <dd>Borrador · no emite mensualidades</dd>
          </div>
        </template>
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
        <template
          v-if="['property_create', 'contact_create'].includes(proposal.type)"
        >
          <label
            >Nombre<input
              v-model="fields.name"
              required
              maxlength="120"
              :disabled="busy"
          /></label>
        </template>
        <template v-if="proposal.type === 'property_create'">
          <label
            >Tipo<select v-model="fields.type" :disabled="busy">
              <option
                v-for="type in propertyCreationTypes"
                :key="type.value"
                :value="type.value"
              >
                {{ type.label }}
              </option>
            </select></label
          >
          <label
            >Dirección<input
              v-model="fields.address_line"
              required
              maxlength="255"
              :disabled="busy"
          /></label>
          <label
            >Ciudad (opcional)<input
              v-model="fields.city"
              maxlength="100"
              :disabled="busy"
          /></label>
          <label
            >Precio de compra (opcional)<input
              v-model="fields.purchase_price"
              inputmode="decimal"
              :disabled="busy"
          /></label>
          <label
            >Valoración actual (opcional)<input
              v-model="fields.current_value"
              inputmode="decimal"
              :disabled="busy"
          /></label>
        </template>
        <template v-if="proposal.type === 'contact_create'">
          <label
            >Tipo<select v-model="fields.kind" :disabled="busy">
              <option value="person">Persona</option>
              <option value="company">Empresa</option>
            </select></label
          >
          <label
            >Correo (opcional)<input
              v-model="fields.email"
              type="email"
              maxlength="255"
              :disabled="busy"
          /></label>
          <label
            >Teléfono (opcional)<input
              v-model="fields.phone"
              type="tel"
              maxlength="30"
              :disabled="busy"
          /></label>
        </template>
        <template v-if="proposal.type === 'lease_create'">
          <label
            >Fecha inicial<input
              v-model="fields.start_date"
              type="date"
              required
              :disabled="busy"
          /></label>
          <label
            >Fecha final (opcional)<input
              v-model="fields.end_date"
              type="date"
              :disabled="busy"
          /></label>
          <label
            >Renta mensual<input
              v-model="fields.monthly_rent"
              inputmode="decimal"
              required
              :disabled="busy"
          /></label>
          <label
            >Fianza<input
              v-model="fields.deposit_amount"
              inputmode="decimal"
              required
              :disabled="busy"
          /></label>
          <label
            >Día de cobro<input
              v-model="fields.payment_day"
              type="number"
              step="1"
              min="1"
              max="28"
              required
              :disabled="busy"
          /></label>
        </template>
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
        <p
          v-if="!['property_create', 'contact_create'].includes(proposal.type)"
        >
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
        <p
          v-if="!enabled || (creationLabels[proposal.type] && !creationEnabled)"
        >
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
