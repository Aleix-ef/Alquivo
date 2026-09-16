export const sessionKeys = {
  user: "alquivo_user",
  portfolio: "alquivo_portfolio",
};
// Compatibility only: migrate old display caches without changing the cookie session.
const legacyKeys = {
  alquivo_user: "ig_user",
  alquivo_portfolio: "ig_portfolio",
};
const keys = [...Object.values(sessionKeys), ...Object.values(legacyKeys)];

// Session cookies authenticate requests; these values only cache display data.
// Some browsers disable storage, and old or interrupted writes may be invalid.
export function readSessionValue(key) {
  try {
    let raw = localStorage.getItem(key);
    const legacyKey = legacyKeys[key];
    if (raw === null && legacyKey) raw = localStorage.getItem(legacyKey);
    const value = JSON.parse(raw || "null");
    if (
      value &&
      typeof value === "object" &&
      !Array.isArray(value) &&
      legacyKey
    ) {
      try {
        localStorage.setItem(key, JSON.stringify(value));
        localStorage.removeItem(legacyKey);
      } catch {
        /* An unavailable display cache must never prevent access. */
      }
    }
    return value && typeof value === "object" && !Array.isArray(value)
      ? value
      : null;
  } catch {
    return null;
  }
}

export function persistSession(data) {
  try {
    localStorage.setItem(sessionKeys.user, JSON.stringify(data.user));
    localStorage.setItem(sessionKeys.portfolio, JSON.stringify(data.portfolio));
    for (const key of Object.values(legacyKeys)) localStorage.removeItem(key);
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
