export function safeReturnPath(value, fallback = "/dashboard") {
  if (
    typeof value !== "string" ||
    !value.startsWith("/") ||
    value.startsWith("//") ||
    /[\\\u0000-\u001f]/.test(value)
  ) {
    return fallback;
  }

  const pathname = value.split(/[?#]/, 1)[0].replace(/\/+$/, "").toLowerCase();
  if (
    ["/login", "/register", "/forgot-password", "/reset-password"].includes(
      pathname,
    )
  ) {
    return fallback;
  }

  return value;
}
