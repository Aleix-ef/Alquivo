export const supportStatuses = {
  waiting_support: "Pendiente de soporte",
  waiting_customer: "Respuesta del equipo",
  closed: "Resuelta",
};

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
