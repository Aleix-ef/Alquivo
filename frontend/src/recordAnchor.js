// These links locate rendered, already-authorized data; they never fetch a record
// by an untrusted ID, open a form, or execute its action.
export function recordAnchorId(hash, prefix) {
  if (
    typeof hash !== "string" ||
    typeof prefix !== "string" ||
    !/^[a-z][a-z-]*$/.test(prefix)
  )
    return null;
  const match = hash.match(new RegExp(`^#${prefix}-([1-9][0-9]*)$`));
  if (!match) return null;
  const id = Number(match[1]);
  return Number.isSafeInteger(id) ? id : null;
}

export function revealRecord(element) {
  if (!element) return () => {};
  element.classList.add("record-anchor-active");
  const action = element.querySelector("[data-record-action]:not(:disabled)");
  // Native fragment navigation can focus the row after Vue has focused its
  // action. Keep that late focus on the action without clicking/submitting it.
  const focusAction = () => action?.focus({ preventScroll: true });
  if (action) element.addEventListener("focus", focusAction);
  (action || element).focus({ preventScroll: true });
  element.scrollIntoView({ block: "center" });
  return () => {
    element.removeEventListener("focus", focusAction);
    element.classList.remove("record-anchor-active");
  };
}
