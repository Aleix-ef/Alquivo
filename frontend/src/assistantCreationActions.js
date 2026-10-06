const id = (value) => Number.isSafeInteger(value) && value > 0;
const name = (value) =>
  typeof value === "string" && value.trim() && value.length <= 120;
const date = (value) => {
  if (typeof value !== "string" || !/^\d{4}-\d{2}-\d{2}$/.test(value))
    return false;
  const parsed = new Date(`${value}T12:00:00Z`);
  return (
    Number.isFinite(parsed.getTime()) &&
    parsed.toISOString().slice(0, 10) === value
  );
};
const money = (value, optional = false) =>
  (optional && (value === null || value === "" || value === undefined)) ||
  (/^\d{1,10}(?:[.,]\d{1,2})?$/.test(String(value)) &&
    Number(String(value).replace(",", ".")) >= 0);
const nullable = (value) =>
  typeof value === "string" ? value.trim() || null : (value ?? null);
const amount = (value) =>
  nullable(value) === null ? null : String(value).replace(",", ".");

export const propertyCreationTypes = [
  { value: "housing", label: "Vivienda" },
  { value: "commercial", label: "Local" },
  { value: "office", label: "Oficina" },
  { value: "garage", label: "Garaje" },
  { value: "storage", label: "Trastero" },
  { value: "land", label: "Terreno" },
  { value: "building", label: "Edificio" },
  { value: "other", label: "Otro" },
];
export const creationLabels = {
  property_create: {
    title: "Nuevo inmueble",
    confirm: "Crear inmueble",
    executed: "Inmueble creado",
    saved: "El inmueble ya está guardado en tu cartera.",
    destination: "Ver inmueble",
  },
  contact_create: {
    title: "Nuevo contacto",
    confirm: "Crear contacto",
    executed: "Contacto creado",
    saved:
      "El contacto ya está en Personas. Todavía no está vinculado a un contrato.",
    destination: "Ver Personas",
  },
  lease_create: {
    title: "Contrato en borrador",
    confirm: "Crear borrador de contrato",
    executed: "Borrador creado",
    saved:
      "Puedes revisar y activar el contrato desde Alquileres. No se han emitido mensualidades.",
    destination: "Ver contrato",
  },
};

export function creationFields(proposal) {
  const p = proposal.preview;
  if (proposal.type === "property_create")
    return {
      name: p.name,
      type: p.type,
      address_line: p.address_line,
      city: p.city || "",
      purchase_price: p.purchase_price ?? "",
      current_value: p.current_value ?? "",
    };
  if (proposal.type === "contact_create")
    return {
      name: p.name,
      kind: p.kind,
      email: p.email || "",
      phone: p.phone || "",
    };
  return {
    start_date: p.start_date,
    end_date: p.end_date || "",
    monthly_rent: p.monthly_rent,
    deposit_amount: p.deposit_amount,
    payment_day: p.payment_day,
  };
}

export function creationProblem(type, f) {
  if (type !== "lease_create" && !name(f.name))
    return "Indica un nombre de hasta 120 caracteres.";
  if (type === "property_create") {
    if (!propertyCreationTypes.some(({ value }) => value === f.type))
      return "Selecciona el tipo de inmueble.";
    if (
      typeof f.address_line !== "string" ||
      !f.address_line.trim() ||
      f.address_line.length > 255
    )
      return "Indica la dirección del inmueble.";
    if (f.city != null && (typeof f.city !== "string" || f.city.length > 100))
      return "La ciudad puede tener hasta 100 caracteres.";
    if (!money(f.purchase_price, true) || !money(f.current_value, true))
      return "Revisa los importes: no negativos y con hasta dos decimales.";
  } else if (type === "contact_create") {
    if (!["person", "company"].includes(f.kind))
      return "Selecciona persona o empresa.";
    if (
      f.email &&
      (typeof f.email !== "string" ||
        f.email.length > 255 ||
        !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email))
    )
      return "Revisa el correo del contacto.";
    if (f.phone != null && (typeof f.phone !== "string" || f.phone.length > 30))
      return "El teléfono puede tener hasta 30 caracteres.";
  } else {
    if (
      !date(f.start_date) ||
      (f.end_date && (!date(f.end_date) || f.end_date < f.start_date))
    )
      return "Revisa las fechas de inicio y fin.";
    if (
      !money(f.monthly_rent) ||
      Number(String(f.monthly_rent).replace(",", ".")) <= 0 ||
      !money(f.deposit_amount)
    )
      return "Revisa la renta y la fianza, con hasta dos decimales.";
    if (
      !Number.isInteger(Number(f.payment_day)) ||
      Number(f.payment_day) < 1 ||
      Number(f.payment_day) > 28
    )
      return "El día de cobro debe estar entre 1 y 28.";
  }
  return "";
}

export function creationPreviewValid(proposal) {
  const p = proposal.preview;
  for (const key of [
    "city",
    "email",
    "phone",
    "end_date",
    "purchase_price",
    "current_value",
  ]) {
    if (p[key] != null && typeof p[key] !== "string") return false;
  }
  if (creationProblem(proposal.type, creationFields(proposal))) return false;
  if (
    ["property_create", "lease_create"].includes(proposal.type) &&
    !/^[A-Z]{3}$/.test(p.currency)
  )
    return false;
  if (proposal.type === "lease_create") {
    if (
      typeof p.monthly_rent !== "string" ||
      typeof p.deposit_amount !== "string" ||
      !Number.isInteger(p.payment_day)
    )
      return false;
    if (
      p.status !== "draft" ||
      !id(p.property?.id) ||
      !name(p.property.name) ||
      p.property_id !== p.property.id
    )
      return false;
    if (
      !Array.isArray(p.contacts) ||
      !p.contacts.length ||
      p.contacts.length > 20 ||
      !Array.isArray(p.contact_ids)
    )
      return false;
    if (
      p.contacts.length !== p.contact_ids.length ||
      new Set(p.contact_ids).size !== p.contact_ids.length ||
      p.contacts.some(
        (c) => !id(c.id) || !name(c.name) || !p.contact_ids.includes(c.id),
      )
    )
      return false;
  }
  if (proposal.status === "executed") {
    const key = {
      property_create: "property_id",
      contact_create: "contact_id",
      lease_create: "lease_id",
    }[proposal.type];
    if (!id(proposal.result?.[key])) return false;
  }
  return true;
}

export function creationPayload(proposal, fields) {
  const f = creationFields({ ...proposal, preview: fields });
  if (proposal.type === "property_create")
    return {
      revision: proposal.revision,
      ...f,
      city: nullable(f.city),
      purchase_price: amount(f.purchase_price),
      current_value: amount(f.current_value),
    };
  if (proposal.type === "contact_create")
    return {
      revision: proposal.revision,
      ...f,
      email: nullable(f.email),
      phone: nullable(f.phone),
    };
  return {
    revision: proposal.revision,
    ...f,
    end_date: nullable(f.end_date),
    monthly_rent: amount(f.monthly_rent),
    deposit_amount: amount(f.deposit_amount),
    payment_day: Number(f.payment_day),
  };
}

export function creationResultPath(proposal) {
  if (proposal.type === "property_create" && id(proposal.result?.property_id))
    return `/properties/${proposal.result.property_id}`;
  if (proposal.type === "contact_create" && id(proposal.result?.contact_id))
    return "/contacts";
  if (proposal.type === "lease_create" && id(proposal.result?.lease_id))
    return `/leases/${proposal.result.lease_id}`;
  return null;
}
