#!/usr/bin/env bash
# Interactive, offline verification. Never print recovered secrets or accept a passphrase argument.
set -Eeuo pipefail
umask 077
export LC_ALL=C
directory="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
[[ $# -le 1 ]] || { echo 'Uso: bash comprobar-copia.sh [archivo.tar.age]' >&2; exit 1; }
if [[ $# -eq 1 ]]; then
  bundle="$1"
else
  bundles=("$directory"/alquivo-recovery-*.tar.age)
  [[ ${#bundles[@]} -eq 1 && -f "${bundles[0]}" ]] || {
    echo 'Indica un único paquete cifrado .tar.age como argumento.' >&2
    exit 1
  }
  bundle="${bundles[0]}"
fi
[[ -f "$bundle" && ! -L "$bundle" && $(stat -c %s -- "$bundle") -le 5242880 ]] || {
  echo 'El paquete debe ser un archivo regular de hasta 5 MiB.' >&2; exit 1;
}
bundle="$(cd -- "$(dirname -- "$bundle")" && pwd)/$(basename -- "$bundle")"
[[ -t 0 && -t 1 ]] || { echo 'Ejecuta la comprobación en tu terminal interactiva.' >&2; exit 1; }
age="${ALQUIVO_AGE_BIN:-age}"
if ! command -v "$age" >/dev/null; then
  age="$directory/herramientas/usr/bin/age"
fi
[[ -x "$(command -v "$age" 2>/dev/null)" ]] || { echo 'Falta la herramienta age.' >&2; exit 1; }
runtime="${XDG_RUNTIME_DIR:-/dev/shm}"
[[ -d "$runtime" && ! -L "$runtime" ]] || { echo 'Falta un directorio temporal en memoria.' >&2; exit 1; }
case "$(findmnt -rn -o FSTYPE -T "$runtime")" in
  tmpfs|ramfs) ;;
  *) echo 'Se requiere almacenamiento temporal en memoria (tmpfs/ramfs).' >&2; exit 1 ;;
esac
stage=$(mktemp -d "$runtime/alquivo-recovery-check.XXXXXX")
trap 'rm -rf -- "$stage"' EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
echo 'Escribe la contraseña que elegiste para el paquete. No se mostrará.'
"$age" --decrypt --output "$stage/recovery.tar" "$bundle"
[[ $(stat -c %s -- "$stage/recovery.tar") -le 5242880 ]] || { echo 'Paquete descifrado demasiado grande.' >&2; exit 1; }
tar --list --file "$stage/recovery.tar" > "$stage/entries"
tar --list --verbose --file "$stage/recovery.tar" > "$stage/types"
declare -A seen=()
while IFS= read -r entry; do
  case "$entry" in .|./) continue ;; esac
  name="${entry#./}"
  case "$name" in
    production.env|backup.password|storagebox.credentials|documents.json|SHA256SUMS) ;;
    *) echo 'El paquete contiene rutas no esperadas.' >&2; exit 1 ;;
  esac
  [[ -z "${seen[$name]:-}" ]] || { echo 'El paquete contiene entradas duplicadas.' >&2; exit 1; }
  seen[$name]=1
done < "$stage/entries"
[[ ${#seen[@]} -eq 5 ]] || { echo 'Faltan archivos del paquete de recuperación.' >&2; exit 1; }
while IFS= read -r entry; do
  case "${entry:0:1}" in -|d) ;; *) echo 'No se permiten enlaces ni archivos especiales.' >&2; exit 1 ;; esac
done < "$stage/types"
mkdir "$stage/files"
tar --extract --file "$stage/recovery.tar" --directory "$stage/files" --no-same-owner --no-same-permissions --keep-old-files
cd "$stage/files"
for name in production.env backup.password storagebox.credentials documents.json SHA256SUMS; do
  [[ -s "$name" && -f "$name" && ! -L "$name" ]] || { echo 'Archivo de recuperación ausente o inválido.' >&2; exit 1; }
done
declare -A hashes=()
while read -r digest name; do
  [[ "$digest" =~ ^[a-fA-F0-9]{64}$ ]] || { echo 'Manifiesto de integridad inválido.' >&2; exit 1; }
  case "$name" in production.env|backup.password|storagebox.credentials|documents.json) ;; *) echo 'Manifiesto con rutas inesperadas.' >&2; exit 1 ;; esac
  [[ -z "${hashes[$name]:-}" ]] || { echo 'Manifiesto con entradas duplicadas.' >&2; exit 1; }
  hashes[$name]=1
done < SHA256SUMS
[[ ${#hashes[@]} -eq 4 ]] || { echo 'Manifiesto incompleto.' >&2; exit 1; }
sha256sum --check --status --strict SHA256SUMS || { echo 'La integridad del paquete no coincide.' >&2; exit 1; }
[[ ! -L "$bundle.verified" ]] || { echo 'No se puede guardar la evidencia en un enlace.' >&2; exit 1; }
checksum=$(sha256sum -- "$bundle")
printf '%s %s\n' "${checksum%% *}" "$(date -u +%FT%TZ)" > "$bundle.verified"
echo 'VERIFICACIÓN CORRECTA: paquete descifrado y cuatro archivos íntegros.'
echo 'No se han mostrado claves. Los archivos descifrados temporales se eliminan al salir.'
echo 'Conserva otra copia del paquete cifrado fuera de este ordenador y guarda su contraseña.'
