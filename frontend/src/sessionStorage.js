const keys = ["ig_user", "ig_portfolio"];

// Session cookies authenticate requests; these values only cache display data.
// Some browsers disable storage, and old or interrupted writes may be invalid.
export function readSessionValue(key) {
  try {
    const value = JSON.parse(localStorage.getItem(key) || "null");
    return value && typeof value === "object" && !Array.isArray(value)
      ? value
      : null;
  } catch {
    return null;
  }
}

export function persistSession(data) {
  try {
    localStorage.setItem("ig_user", JSON.stringify(data.user));
    localStorage.setItem("ig_portfolio", JSON.stringify(data.portfolio));
  } catch {
    // The app remains usable with its cookie and in-memory session.
  }
}

export function clearSessionStorage() {
  for (const key of keys) {
    try {
      localStorage.removeItem(key);
    } catch {
      // Storage access is optional, including when signing out.
    }
  }
}
