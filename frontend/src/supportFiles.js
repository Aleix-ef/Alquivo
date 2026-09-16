export const supportFileLimits = { count: 3, bytes: 2 * 1024 * 1024 };
export function validateSupportFiles(files) {
  if (files.length > supportFileLimits.count)
    return "Puedes adjuntar hasta 3 archivos.";
  for (const file of files) {
    if (file.size > supportFileLimits.bytes)
      return `«${file.name}» supera los 2 MB.`;
    if (
      !/\.(jpe?g|png|webp|pdf)$/i.test(file.name) ||
      (file.type &&
        !["image/jpeg", "image/png", "image/webp", "application/pdf"].includes(
          file.type,
        ))
    ) {
      return "Solo se admiten imágenes JPG, PNG, WebP y documentos PDF.";
    }
  }
  return "";
}
