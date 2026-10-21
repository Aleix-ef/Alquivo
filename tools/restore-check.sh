#!/usr/bin/env bash
# Verifies a backup in new disposable containers, never overwrites the live database.
set -Eeuo pipefail
umask 077
ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
REPOSITORY="${BACKUP_REPOSITORY_DIR:-$ROOT/backups/repository}"
PASSWORD="${BACKUP_PASSWORD_FILE:-$ROOT/secrets/backup.password}"
KEYRING="${RECOVERY_KEYRING:-$ROOT/secrets/documents.json}"
WORK="${BACKUP_WORK_DIR:-$ROOT/backups}"
[[ -s "$KEYRING" && -s "$PASSWORD" ]] || { echo 'Recovery requires the backed-up document keyring and backup password.'; exit 1; }
[[ "$WORK" == /* && ! -L "$WORK" ]] || { echo 'BACKUP_WORK_DIR must be an absolute non-symlink directory.' >&2; exit 1; }
mkdir -p "$WORK"
exec 9>"$WORK/operation.lock"
flock -n 9 || { echo 'Another backup/restore is running.'; exit 1; }
stage="$(mktemp -d "$WORK/.recovery-XXXXXX")"
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
trap 'exit 130' INT
trap 'exit 143' TERM
docker run --rm --network none \
  -e RESTIC_REPOSITORY=/repository -e RESTIC_PASSWORD_FILE=/run/backup.password \
  -v "$REPOSITORY:/repository:ro" -v "$PASSWORD:/run/backup.password:ro" \
  -v "$stage:/restore" restic/restic:0.19.0 --no-lock restore "${1:-latest}" --tag alquivo --target /restore
(cd "$stage/data" && sha256sum -c SHA256SUMS)
cp -- "$KEYRING" "$stage/documents.json"
# The disposable verifier runs as root to read the host-owned 0600 recovery bundle.
"${dc[@]}" up -d --wait --wait-timeout 90 postgres </dev/null
"${dc[@]}" exec -T postgres pg_restore -U alquivo -d alquivo --no-owner --no-acl --exit-on-error < "$stage/data/database.dump"
"${dc[@]}" run --rm --no-deps -T --user root backend tar -C /app/storage/app/private -xf /recovery/data/private.tar </dev/null
"${dc[@]}" run --rm --no-deps -T --user root backend php artisan migrate:status --no-ansi </dev/null
"${dc[@]}" run --rm --no-deps -T --user root backend php tools/recovery-probe.php /recovery/data/vault-probe.enc </dev/null
"${dc[@]}" run --rm --no-deps -T --user root backend php artisan vault:files </dev/null
echo 'Recovery drill passed: PostgreSQL restored and every referenced private file decrypted and size-checked.'
