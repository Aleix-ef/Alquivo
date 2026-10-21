#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
PASSWORD="${BACKUP_PASSWORD_FILE:-$ROOT/secrets/backup.password}"
[[ "$PASSWORD" == /* && ! -L "$PASSWORD" ]] || { echo 'Backup password must be an absolute non-symlink path.' >&2; exit 1; }
mkdir -p "$(dirname -- "$PASSWORD")"
if [[ -e "$PASSWORD" ]]; then
  echo 'Existing backup password preserved.'
  exit 0
fi
(set -o noclobber; openssl rand -base64 48 > "$PASSWORD")
echo 'Backup password generated without displaying it. Save it separately in your password manager.'
