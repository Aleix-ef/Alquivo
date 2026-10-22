export const accountSections = [
  {
    id: "profile",
    label: "Perfil",
    description: "Tus datos de contacto y preferencias de la cartera.",
  },
  {
    id: "security",
    label: "Seguridad",
    description:
      "Contraseña, verificación en dos pasos y dispositivos de confianza.",
  },
  {
    id: "plan",
    label: "Plan y uso",
    description: "Consulta los límites y el espacio de tu cuenta.",
  },
  {
    id: "privacy",
    label: "Privacidad",
    description:
      "Controla el uso de tus conversaciones y los datos de tu cuenta.",
  },
];
export function accountSection(query = {}, hash = "") {
  if (
    typeof query.section === "string" &&
    accountSections.some((s) => s.id === query.section)
  )
    return query.section;
  const anchor = hash.replace(/^#/, "");
  if (accountSections.some((s) => s.id === anchor)) return anchor;
  if (query.checkout || query.billing) return "plan";
  return "profile";
}
