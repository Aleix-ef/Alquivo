const appPath =
  /^\/(?:dashboard|properties(?:\/[1-9]\d*)?|leases(?:\/[1-9]\d*)?|contacts|finance|reports|fiscality|support|issues|calendar|documents|settings|plans|privacy|terms)$/;

export function safeSources(metadata) {
  return (Array.isArray(metadata?.sources) ? metadata.sources : [])
    .filter(
      (source) =>
        typeof source?.label === "string" &&
        typeof source?.path === "string" &&
        appPath.test(source.path),
    )
    .slice(0, 8);
}

// AI text never becomes HTML; only known, read-only navigation destinations become links.
export function contentParts(content) {
  const expression = /\[([^\]]+)\]\(([^\s)]+)\)/g;
  const parts = [];
  let cursor = 0;
  for (const match of content.matchAll(expression)) {
    if (match.index > cursor)
      parts.push({ text: content.slice(cursor, match.index) });
    parts.push(
      appPath.test(match[2])
        ? { text: match[1], to: match[2] }
        : { text: match[0] },
    );
    cursor = match.index + match[0].length;
  }
  if (cursor < content.length) parts.push({ text: content.slice(cursor) });
  return parts;
}

export function replyPose(message) {
  return [
    "insufficient_data",
    "support_required",
    "out_of_scope",
    "read_only",
  ].includes(message?.metadata?.kind)
    ? "uncertain"
    : "idle";
}
