const methods = new Set(["email", "authenticator", "recovery"]);
export function readTwoFactorResult(data) {
  if (
    data?.challenge_complete === false &&
    Array.isArray(data.methods_remaining) &&
    data.methods_remaining.length &&
    data.methods_remaining.every((method) => methods.has(method)) &&
    !data.user
  ) {
    return { complete: false, remaining: data.methods_remaining };
  }
  if (
    data?.challenge_complete === true &&
    Number.isSafeInteger(data.user?.id) &&
    data.user.id > 0
  )
    return { complete: true, remaining: [] };
  throw new Error(
    "No se ha podido confirmar el acceso. Vuelve a iniciar sesión.",
  );
}
