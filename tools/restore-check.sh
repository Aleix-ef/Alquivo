#!/usr/bin/env bash
# Verifies a backup in new disposable containers, never overwrites the live database.
set -Eeuo pipefail
umask 077
ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
REPOSITORY="${BACKUP_REPOSITORY_DIR:-$ROOT/backups/repository}"
PASSWORD="${BACKUP_PASSWORD_FILE:-$ROOT/secrets/backup.password}"
KEYRING="${RECOVERY_KEYRING:-$ROOT/secrets/documents.json}"
[[ -s "$KEYRING" && -s "$PASSWORD" ]] || { echo 'Recovery requires the backed-up document keyring and backup password.'; exit 1; }
exec 9>"$ROOT/backups/operation.lock"
flock -n 9 || { echo 'Another backup/restore is running.'; exit 1; }
stage="$(mktemp -d "$ROOT/backups/.recovery-XXXXXX")"
export RECOVERY_DIRECTORY="$stage"
project="alquivo-recovery-$(date +%s)-$$"
dc=(docker compose -p "$project" -f docker-compose.restore.yml)
cleanup() {
  local result=$?
  trap - EXIT
  "${dc[@]}" down -v --remove-orphans || result=1
  rm -rf -- "$stage"
  exit "$result"
}
trap cleanup EXIT
docker run --rm --network none \
  -e RESTIC_REPOSITORY=/repository -e RESTIC_PASSWORD_FILE=/run/backup.password \
  -v "$REPOSITORY:/repository:ro" -v "$PASSWORD:/run/backup.password:ro" \
  -v "$stage:/restore" restic/restic:0.19.0 --no-lock restore "${1:-latest}" --tag alquivo --target /restore
(cd "$stage/data" && sha256sum -c SHA256SUMS)
cp -- "$KEYRING" "$stage/documents.json"
# The disposable verifier runs as root to read the host-owned 0600 recovery bundle.
"${dc[@]}" up -d --wait postgres
"${dc[@]}" exec -T postgres pg_restore -U alquivo -d alquivo --no-owner --no-acl --exit-on-error < "$stage/data/database.dump"
"${dc[@]}" run --rm --no-deps -T --user root backend tar -C /app/storage/app/private -xf /recovery/data/private.tar
"${dc[@]}" run --rm --no-deps -T --user root backend php artisan migrate:status --no-ansi
"${dc[@]}" run --rm --no-deps -T --user root backend php tools/recovery-probe.php /recovery/data/vault-probe.enc
"${dc[@]}" run --rm --no-deps -T --user root backend php artisan vault:files
echo 'Recovery drill passed: PostgreSQL restored and every referenced private file decrypted and size-checked.'
