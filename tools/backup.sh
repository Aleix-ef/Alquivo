#!/usr/bin/env bash
# Consistent encrypted backup; the repository may be an already mounted network target.
set -Eeuo pipefail
umask 077
ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.yml}"
dc=(docker compose -f "$COMPOSE_FILE")
if [[ "$COMPOSE_FILE" == *production* ]]; then dc+=(--env-file .env.production); fi
REPOSITORY="${BACKUP_REPOSITORY_DIR:-$ROOT/backups/repository}"
PASSWORD="${BACKUP_PASSWORD_FILE:-$ROOT/secrets/backup.password}"
WORK="${BACKUP_WORK_DIR:-$ROOT/backups}"
[[ "$WORK" == /* && ! -L "$WORK" ]] || { echo 'BACKUP_WORK_DIR must be an absolute non-symlink directory.' >&2; exit 1; }
[[ $# -eq 0 || ( $# -eq 1 && "$1" == --resume-only ) ]] || { echo 'Usage: backup.sh [--resume-only]' >&2; exit 1; }
mkdir -p "$WORK"
exec 9>"$WORK/operation.lock"
flock -n 9 || { echo 'Another backup/restore is running.'; exit 1; }
stage=''
services=()
journal="$WORK/paused-services"
resume() {
  if ((${#services[@]})); then
    if ! "${dc[@]}" start --wait --wait-timeout 120 "${services[@]}" </dev/null; then return 1; fi
    rm -f -- "$journal"
    services=()
  fi
}
cleanup() {
  local result=$?
  trap - EXIT
  if ! resume; then result=1; fi
  if [[ -n "$stage" ]]; then rm -rf -- "$stage"; fi
  exit "$result"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
if [[ -e "$journal" ]]; then
  mapfile -t services < "$journal"
  ((${#services[@]})) || { echo 'Invalid paused-service journal.' >&2; exit 1; }
  for service in "${services[@]}"; do
    case "$service" in backend|worker|scheduler|web) ;; *) services=(); echo 'Invalid paused-service journal.' >&2; exit 1 ;; esac
  done
  resume
fi
[[ "${1:-}" != --resume-only ]] || exit 0
[[ -s "$PASSWORD" ]] || { echo 'Create the backup password first (see docs/release-candidate.md).'; exit 1; }
RETENTION="${BACKUP_RETENTION_DAYS:-}"
[[ -z "$RETENTION" || "$RETENTION" =~ ^[1-9][0-9]{0,3}$ ]] || { echo 'Invalid BACKUP_RETENTION_DAYS.' >&2; exit 1; }
mkdir -p "$REPOSITORY"
stage="$(mktemp -d "$WORK/.stage-XXXXXX")"
restic() {
  docker run --rm --network none \
    -e RESTIC_REPOSITORY=/repository -e RESTIC_PASSWORD_FILE=/run/backup.password \
    -v "$REPOSITORY:/repository" -v "$PASSWORD:/run/backup.password:ro" \
    -v "$stage:/data:ro" restic/restic:0.19.0 "$@" </dev/null
}
docker image inspect restic/restic:0.19.0 >/dev/null 2>&1 || docker pull restic/restic:0.19.0
if [[ ! -f "$REPOSITORY/config" ]]; then restic init; fi
restic check
while IFS= read -r service; do
  case "$service" in backend|worker|scheduler|web) services+=("$service");; esac
done < <("${dc[@]}" ps --services --status running)
((${#services[@]})) || { echo 'No running application to back up.'; exit 1; }
echo 'Pausing application writes briefly for a consistent database + file snapshot…'
printf '%s\n' "${services[@]}" > "$journal"
"${dc[@]}" stop "${services[@]}"
# Production image uses postgres admin and a distinct alquivo application database.
if [[ "$COMPOSE_FILE" == *production* ]]; then
  "${dc[@]}" exec -T postgres pg_dump -U postgres -d alquivo --format=custom --no-owner --no-acl > "$stage/database.dump"
else
  "${dc[@]}" exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --format=custom --no-owner --no-acl' > "$stage/database.dump"
fi
"${dc[@]}" run --rm --no-deps -T backend tar -C /app/storage/app/private -cf - . </dev/null > "$stage/private.tar"
"${dc[@]}" run --rm --no-deps -T backend php tools/recovery-probe.php </dev/null > "$stage/vault-probe.enc"
(cd "$stage" && sha256sum database.dump private.tar vault-probe.enc > SHA256SUMS)
# The consistent staging copy is complete: do not keep production offline while uploading.
resume
restic backup /data --tag alquivo --host alquivo
restic check --read-data
if [[ -n "$RETENTION" ]]; then
  restic forget --tag alquivo --host alquivo --keep-within "${RETENTION}d" --keep-last 1 --prune
  restic check
fi
touch "$WORK/last-success"
echo 'Encrypted backup verified. An off-machine repository is required for disaster recovery.'
echo 'The document keyring and APP_KEY are intentionally excluded: store them separately in your secret manager.'
