#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
mkdir -p "$ROOT/secrets"
if [[ -e "$ROOT/secrets/backup.password" ]]; then
  echo 'Existing backup password preserved.'
  exit 0
fi
(set -o noclobber; openssl rand -base64 48 > "$ROOT/secrets/backup.password")
echo 'Backup password generated without displaying it. Save it separately in your password manager.'
