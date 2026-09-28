export const supportStatuses = {
  waiting_support: "Pendiente de soporte",
  waiting_customer: "Respuesta del equipo",
  closed: "Resuelto",
};

export function supportStatusLabel(status, team = false) {
  if (status === "waiting_support")
    return team ? "Por responder" : "Esperando respuesta";
  if (status === "waiting_customer")
    return team ? "Esperando al cliente" : "Tienes respuesta";
  return supportStatuses[status] || "Ticket abierto";
}

export function ticketReference(id) {
  return String(id || "")
    .replaceAll("-", "")
    .slice(0, 8)
    .toUpperCase();
}

export function supportEndpoint(mode) {
  if (mode === "team") return "/support/team/conversations";
  if (mode === "public") return "/public/support/chat/conversations";
  return "/support/chat/conversations";
}

export function mergeSupportMessages(current, incoming) {
  const messages = new Map(current.map((message) => [message.id, message]));
  for (const message of incoming) messages.set(message.id, message);
  return [...messages.values()].sort((a, b) => a.id - b.id);
}
