// Shared public contract. No credentials or application/user data belong here.
export const waitlistConsent = {
  version: "2026-10-02.waitlist.v1",
  text: "Quiero recibir por email la invitación y las comunicaciones necesarias sobre mi acceso a la beta de Alquivo.",
};
export const waitlistAction = "beta_waitlist";
export const waitlistPropertyCounts = [
  { value: "0", label: "Aún ninguno" },
  { value: "1", label: "1 inmueble" },
  { value: "2-5", label: "De 2 a 5" },
  { value: "6-10", label: "De 6 a 10" },
  { value: "11+", label: "Más de 10" },
];
export const waitlistSuccess =
  "Te avisaremos cuando puedas entrar en la beta gratuita de Alquivo. No necesitas hacer nada más.";

export function isTurnstileTestKey(key) {
  return /^[123]x0+(?:AA|AB|BB|FF)$/.test(key);
}
