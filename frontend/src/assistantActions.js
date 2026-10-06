import {
  creationLabels,
  creationFields,
  creationProblem,
  creationPreviewValid,
  creationPayload,
  creationResultPath,
} from "./assistantCreationActions.js";

const uuid =
  /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
const proposalStatuses = new Set([
  "pending",
  "executed",
  "cancelled",
  "expired",
]);
const proposalTypes = new Set([
  "expense",
  "contact_phone",
  "rent_payment",
  "property_note",
  ...Object.keys(creationLabels),
]);
const entityId = (value) => Number.isSafeInteger(value) && value > 0;
const namedEntity = (value) =>
  entityId(value?.id) &&
  typeof value.name === "string" &&
  value.name.length > 0;
const moneyValue = (value) =>
  /^\d{1,10}(?:[.,]\d{1,2})?$/.test(String(value)) &&
  Number(String(value).replace(",", ".")) > 0;
const cents = (value) => {
  const [whole, fraction = ""] = String(value).replace(",", ".").split(".");
  return Number(whole) * 100 + Number(fraction.padEnd(2, "0"));
};
const validDate = (value) => {
  if (typeof value !== "string" || !/^\d{4}-\d{2}-\d{2}$/.test(value))
    return false;
  const parsed = new Date(`${value}T12:00:00Z`);
  return (
    Number.isFinite(parsed.getTime()) &&
    parsed.toISOString().slice(0, 10) === value
  );
};

export const actionLabels = {
  ...creationLabels,
  expense: {
    title: "Propuesta de gasto",
    confirm: "Confirmar gasto",
    executed: "Gasto registrado",
    saved: "Ya está guardado en tus movimientos.",
    destination: "Ver en Finanzas",
  },
  contact_phone: {
    title: "Cambio de teléfono",
    confirm: "Confirmar teléfono",
    executed: "Teléfono actualizado",
    saved: "El teléfono del contacto ya está actualizado.",
    destination: "Ver contactos",
  },
  rent_payment: {
    title: "Propuesta de cobro",
    confirm: "Confirmar cobro",
    executed: "Cobro registrado",
    saved: "El cobro ya está asociado a este recibo y reflejado en Finanzas.",
    destination: "Ver contrato",
  },
  property_note: {
    title: "Nota para el inmueble",
    confirm: "Confirmar nota",
    executed: "Nota añadida",
    saved: "La nota se ha añadido sin sustituir las notas anteriores.",
    destination: "Ver inmueble",
  },
};

export const expenseCategories = [
  { value: "maintenance", label: "Mantenimiento" },
  { value: "tax", label: "Impuestos" },
  { value: "insurance", label: "Seguro" },
  { value: "other", label: "Otro" },
];

// Metadata can reference a card, but it never grants permission to execute it.
export function actionProposalReferences(metadata) {
  const seen = new Set();
  return (Array.isArray(metadata?.proposals) ? metadata.proposals : [])
    .filter((proposal) => {
      if (
        !proposalTypes.has(proposal?.type) ||
        !uuid.test(proposal.id) ||
        seen.has(proposal.id)
      )
        return false;
      seen.add(proposal.id);
      return true;
    })
    .slice(0, 10)
    .map(({ id }) => ({ id }));
}

export function checkedActionProposal(proposal, expectedId, expectedType) {
  if (
    proposal?.id !== expectedId ||
    !uuid.test(proposal.id) ||
    !proposalTypes.has(proposal.type) ||
    (expectedType && proposal.type !== expectedType) ||
    !proposalStatuses.has(proposal.status) ||
    !Number.isInteger(proposal.revision) ||
    proposal.revision < 1 ||
    !proposal.preview ||
    !Number.isFinite(Date.parse(proposal.expires_at))
  ) {
    throw new Error(
      "La propuesta recibida no es válida. Vuelve a comprobarla.",
    );
  }
  const preview = proposal.preview;
  let valid = true;
  if (["expense", "rent_payment"].includes(proposal.type))
    valid =
      moneyValue(preview.amount) &&
      /^[A-Z]{3}$/.test(preview.currency) &&
      validDate(preview.transaction_date);
  if (proposal.type === "expense")
    valid &&=
      (!preview.property || namedEntity(preview.property)) &&
      !expenseEditProblem(expenseEditFields(preview));
  if (proposal.type === "contact_phone")
    valid &&=
      namedEntity(preview.contact) &&
      (preview.previous_phone === null ||
        typeof preview.previous_phone === "string") &&
      !actionEditProblem(proposal, { phone: preview.phone });
  if (proposal.type === "rent_payment")
    valid &&=
      namedEntity(preview.property) &&
      entityId(preview.lease?.id) &&
      entityId(preview.rent_charge?.id) &&
      typeof preview.rent_charge.period === "string" &&
      validDate(preview.rent_charge.due_date) &&
      moneyValue(preview.rent_charge.remaining_amount) &&
      !actionEditProblem(proposal, actionEditFields(proposal));
  if (proposal.type === "property_note")
    valid &&=
      namedEntity(preview.property) &&
      !actionEditProblem(proposal, { note: preview.note });
  if (creationLabels[proposal.type]) valid &&= creationPreviewValid(proposal);
  if (!valid)
    throw new Error(
      "Los datos de la propuesta no son válidos. Vuelve a comprobarla.",
    );
  return proposal;
}

export function actionEditFields(proposal) {
  if (creationLabels[proposal.type]) return creationFields(proposal);
  const preview = proposal.preview;
  if (proposal.type === "expense") return expenseEditFields(preview);
  if (proposal.type === "contact_phone") return { phone: preview.phone };
  if (proposal.type === "property_note") return { note: preview.note };
  return {
    amount: String(preview.amount),
    transaction_date: preview.transaction_date,
    payment_method: preview.payment_method || "",
  };
}

export function actionEditProblem(proposal, fields) {
  if (creationLabels[proposal.type])
    return creationProblem(proposal.type, fields);
  if (proposal.type === "expense") return expenseEditProblem(fields);
  if (proposal.type === "contact_phone") {
    const phone = String(fields.phone || "").trim();
    if (
      typeof fields.phone !== "string" ||
      !phone ||
      phone.length > 30 ||
      !/^\+?[\d ().-]+$/.test(phone) ||
      phone.replace(/\D/g, "").length < 7 ||
      phone.replace(/\D/g, "").length > 15
    )
      return "Indica un teléfono válido de entre 7 y 15 dígitos.";
    return "";
  }
  if (proposal.type === "property_note") {
    if (typeof fields.note !== "string" || !fields.note.trim())
      return "Escribe la nota que quieres añadir.";
    return fields.note.length > 2000
      ? "La nota puede tener un máximo de 2.000 caracteres."
      : "";
  }
  if (!moneyValue(fields.amount))
    return "Indica un importe mayor que cero, con un máximo de dos decimales.";
  if (
    cents(fields.amount) > cents(proposal.preview.rent_charge.remaining_amount)
  )
    return "El cobro no puede superar el importe pendiente del recibo.";
  if (!validDate(fields.transaction_date)) return "Indica una fecha válida.";
  if (
    fields.payment_method != null &&
    (typeof fields.payment_method !== "string" ||
      fields.payment_method.length > 40)
  )
    return "El medio de pago puede tener un máximo de 40 caracteres.";
  return "";
}

export function actionRevisionPayload(proposal, fields) {
  if (creationLabels[proposal.type]) return creationPayload(proposal, fields);
  if (proposal.type === "expense")
    return expenseRevisionPayload(proposal, fields);
  const payload = { revision: proposal.revision };
  if (proposal.type === "contact_phone")
    return { ...payload, phone: fields.phone.trim() };
  if (proposal.type === "property_note")
    return { ...payload, note: fields.note.trim() };
  return {
    ...payload,
    amount: String(fields.amount).replace(",", "."),
    transaction_date: fields.transaction_date,
    payment_method: fields.payment_method?.trim() || null,
  };
}

// Never follow a destination supplied by the model or the API. Build known
// application routes from canonical, validated target IDs only.
export function actionResultPath(proposal) {
  if (proposal.status !== "executed") return null;
  if (creationLabels[proposal.type]) return creationResultPath(proposal);
  if (proposal.type === "expense") return "/finance";
  if (proposal.type === "contact_phone") return "/contacts";
  if (proposal.type === "rent_payment" && entityId(proposal.preview.lease?.id))
    return `/leases/${proposal.preview.lease.id}`;
  if (
    proposal.type === "property_note" &&
    entityId(proposal.preview.property?.id)
  )
    return `/properties/${proposal.preview.property.id}`;
  return null;
}

export function actionRefreshEvents(proposal) {
  if (proposal.status !== "executed") return [];
  const events = {
    expense: ["alquivo:finance-changed"],
    contact_phone: ["alquivo:contacts-changed"],
    rent_payment: ["alquivo:finance-changed", "alquivo:leases-changed"],
    property_note: ["alquivo:properties-changed"],
    property_create: ["alquivo:properties-changed"],
    contact_create: ["alquivo:contacts-changed"],
    lease_create: ["alquivo:leases-changed", "alquivo:properties-changed"],
  };
  return (events[proposal.type] || []).map((name) => ({
    name,
    detail: {
      propertyId: proposal.preview.property?.id ?? proposal.result?.property_id,
      contactId: proposal.preview.contact?.id ?? proposal.result?.contact_id,
      leaseId: proposal.preview.lease?.id ?? proposal.result?.lease_id,
    },
  }));
}

// Backwards-compatible exports for the initial expense integration.
export const expenseProposalReferences = actionProposalReferences;
export const checkedExpenseProposal = checkedActionProposal;

export function expenseEditFields(preview) {
  return {
    amount: String(preview.amount),
    category: preview.category,
    description: preview.description || "",
    transaction_date: preview.transaction_date?.slice(0, 10) || "",
    status: preview.status,
  };
}

export function expenseEditProblem(fields) {
  if (
    !/^\d{1,10}(?:[.,]\d{1,2})?$/.test(String(fields.amount)) ||
    Number(String(fields.amount).replace(",", ".")) <= 0
  )
    return "Indica un importe mayor que cero, con un máximo de dos decimales.";
  if (!expenseCategories.some(({ value }) => value === fields.category))
    return "Selecciona una categoría para el gasto.";
  if (!fields.description?.trim())
    return "Añade un concepto para reconocer el gasto.";
  if (fields.description.length > 180)
    return "El concepto puede tener un máximo de 180 caracteres.";
  const date = new Date(`${fields.transaction_date}T12:00:00Z`);
  if (
    !/^\d{4}-\d{2}-\d{2}$/.test(fields.transaction_date) ||
    !Number.isFinite(date.getTime()) ||
    date.toISOString().slice(0, 10) !== fields.transaction_date
  )
    return "Indica una fecha válida.";
  if (!["paid", "pending"].includes(fields.status))
    return "Selecciona si el gasto está pagado o pendiente.";
  return "";
}

export function expenseRevisionPayload(proposal, fields) {
  return {
    revision: proposal.revision,
    amount: String(fields.amount).replace(",", "."),
    category: fields.category,
    description: fields.description.trim(),
    transaction_date: fields.transaction_date,
    status: fields.status,
  };
}

// A lost response is not a failed write. Read the authoritative receipt; never
// issue a second mutation or ask the model to repeat it automatically.
export async function mutateActionProposal(
  api,
  action,
  proposal,
  fields,
  options = {},
) {
  if (!["revise", "confirm", "cancel"].includes(action))
    throw new Error("Acción no permitida.");
  checkedActionProposal(proposal, proposal.id);
  if (action === "revise") {
    const problem = actionEditProblem(proposal, fields);
    if (problem) throw new Error(problem);
  }
  const endpoint = `/assistant/proposals/${proposal.id}`;
  const payload =
    action === "revise"
      ? actionRevisionPayload(proposal, fields)
      : { revision: proposal.revision };
  try {
    const { data } = await api.post(`${endpoint}/${action}`, payload, options);
    return {
      proposal: checkedActionProposal(
        data.proposal,
        proposal.id,
        proposal.type,
      ),
      error: null,
    };
  } catch (error) {
    if (options.signal?.aborted) throw error;
    try {
      const { data } = await api.get(endpoint, options);
      return {
        proposal: checkedActionProposal(
          data.proposal,
          proposal.id,
          proposal.type,
        ),
        error,
      };
    } catch {
      return { proposal: null, error };
    }
  }
}
export const mutateExpenseProposal = mutateActionProposal;

export function assistantRequest(
  previous,
  { message, propertyId, conversationId },
  createId = () => crypto.randomUUID(),
) {
  if (
    previous?.message === message &&
    previous.propertyId === propertyId &&
    previous.conversationId === conversationId
  )
    return previous;
  return {
    clientRequestId: createId(),
    message,
    propertyId,
    conversationId,
    runId: null,
    status: "new",
  };
}

export function mergeAssistantMessages(current, incoming) {
  const messages = new Map(current.map((message) => [message.id, message]));
  for (const message of incoming) {
    if (message?.id && ["user", "assistant"].includes(message.role))
      messages.set(message.id, message);
  }
  return [...messages.values()];
}
