// First-party enhancement: no app/session bundle, tracking or browser storage.
const form = document.getElementById("waitlist-form");
if (form && form.dataset.preview !== "true") {
  const fields = document.getElementById("waitlist-fields");
  const button = document.getElementById("waitlist-submit");
  const error = document.getElementById("waitlist-error");
  const success = document.getElementById("waitlist-success");
  let widget;
  let loading;
  let pending;
  let busy = false;

  function showError(message) {
    error.textContent = message;
    error.hidden = false;
    error.focus();
  }

  function loadChallenge() {
    if (loading) return loading;
    loading = new Promise((resolve, reject) => {
      const script = document.createElement("script");
      let settled = false;
      const fail = () => {
        if (settled) return;
        settled = true;
        clearTimeout(timer);
        script.remove();
        reject(new Error("challenge_unavailable"));
      };
      const timer = setTimeout(fail, 12000);
      script.src =
        "https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit";
      script.async = true;
      script.onload = () => {
        if (settled) return;
        clearTimeout(timer);
        try {
          widget = window.turnstile.render("#waitlist-challenge", {
            sitekey: form.dataset.siteKey,
            theme: "dark",
            language: "es",
            size: "flexible",
            action: "beta_waitlist",
            appearance: "interaction-only",
            execution: "execute",
            "response-field": false,
            callback: (token) => pending?.resolve(token),
            "error-callback": () => {
              pending?.reject(new Error("challenge_unavailable"));
              return true;
            },
            "expired-callback": () =>
              pending?.reject(new Error("challenge_expired")),
            "timeout-callback": () =>
              pending?.reject(new Error("challenge_expired")),
          });
          settled = true;
          resolve();
        } catch {
          fail();
        }
      };
      script.onerror = fail;
      document.head.append(script);
    }).catch((reason) => {
      loading = undefined;
      throw reason;
    });
    return loading;
  }

  // Do not connect to the anti-spam provider until the form is used.
  form.addEventListener("focusin", () => loadChallenge().catch(() => {}), {
    once: true,
  });
  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (busy || !form.reportValidity()) return;
    const values = new FormData(form);
    busy = true;
    fields.disabled = true;
    button.textContent = "Enviando tu solicitud…";
    form.setAttribute("aria-busy", "true");
    error.hidden = true;
    let challengeTimer;
    try {
      await loadChallenge();
      const token = await new Promise((resolve, reject) => {
        pending = { resolve, reject };
        challengeTimer = setTimeout(
          () => reject(new Error("challenge_expired")),
          180000,
        );
        window.turnstile.execute(widget);
      });
      clearTimeout(challengeTimer);
      pending = undefined;
      const response = await fetch("/api/waitlist", {
        method: "POST",
        credentials: "omit",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        signal: AbortSignal.timeout(15000),
        body: JSON.stringify({
          email: values.get("email"),
          name: values.get("name"),
          property_count: values.get("property_count"),
          website: values.get("website"),
          consent: values.get("consent") === "on",
          consent_version: form.dataset.consentVersion,
          turnstile_token: token,
        }),
      });
      const data = await response.json();
      if (!response.ok || data.ok !== true) {
        showError(
          data.message ||
            "No se ha podido guardar tu solicitud. Inténtalo de nuevo.",
        );
        return;
      }
      // Never claim success on a timeout, HTML/404 or failed database write.
      form.hidden = true;
      success.hidden = false;
      document.getElementById("waitlist-success-title").focus();
    } catch (reason) {
      showError(
        reason.message?.startsWith("challenge_")
          ? "No hemos podido completar la comprobación antispam. Inténtalo de nuevo o escribe a soporte@alquivo.com."
          : "No hemos podido confirmar tu solicitud. Revisa tu conexión e inténtalo de nuevo; no se duplicará.",
      );
    } finally {
      clearTimeout(challengeTimer);
      pending = undefined;
      busy = false;
      fields.disabled = false;
      button.textContent = "Solicitar acceso gratis";
      form.removeAttribute("aria-busy");
      if (!form.hidden && widget !== undefined) window.turnstile.reset(widget);
    }
  });
}
