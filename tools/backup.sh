#!/usr/bin/env bash
# Consistent local, encrypted backup. No provider credentials or uploads involved.
set -Eeuo pipefail
umask 077
ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.yml}"
dc=(docker compose -f "$COMPOSE_FILE")
if [[ "$COMPOSE_FILE" == *production* ]]; then dc+=(--env-file .env.production); fi
REPOSITORY="${BACKUP_REPOSITORY_DIR:-$ROOT/backups/repository}"
PASSWORD="${BACKUP_PASSWORD_FILE:-$ROOT/secrets/backup.password}"
[[ -s "$PASSWORD" ]] || { echo 'Create secrets/backup.password first (see docs/release-candidate.md).'; exit 1; }
mkdir -p "$REPOSITORY" "$ROOT/backups"
exec 9>"$ROOT/backups/operation.lock"
flock -n 9 || { echo 'Another backup/restore is running.'; exit 1; }
stage="$(mktemp -d "$ROOT/backups/.stage-XXXXXX")"
services=()
cleanup() {
  local result=$?
  trap - EXIT
  if ((${#services[@]})); then "${dc[@]}" start "${services[@]}" || result=1; fi
  rm -rf -- "$stage"
  exit "$result"
}
trap cleanup EXIT
restic() {
  docker run --rm --network none \
    -e RESTIC_REPOSITORY=/repository -e RESTIC_PASSWORD_FILE=/run/backup.password \
    -v "$REPOSITORY:/repository" -v "$PASSWORD:/run/backup.password:ro" \
    -v "$stage:/data:ro" restic/restic:0.19.0 "$@"
}
docker image inspect restic/restic:0.19.0 >/dev/null 2>&1 || docker pull restic/restic:0.19.0
if [[ ! -f "$REPOSITORY/config" ]]; then restic init; fi
restic check
while IFS= read -r service; do
  case "$service" in backend|worker|scheduler|web) services+=("$service");; esac
done < <("${dc[@]}" ps --services --status running)
((${#services[@]})) || { echo 'No running application to back up.'; exit 1; }
echo 'Pausing application writes briefly for a consistent database + file snapshot…'
"${dc[@]}" stop "${services[@]}"
# Production image uses postgres admin and a distinct alquivo application database.
if [[ "$COMPOSE_FILE" == *production* ]]; then
  "${dc[@]}" exec -T postgres pg_dump -U postgres -d alquivo --format=custom --no-owner --no-acl > "$stage/database.dump"
else
  "${dc[@]}" exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --format=custom --no-owner --no-acl' > "$stage/database.dump"
fi
"${dc[@]}" run --rm --no-deps -T backend tar -C /app/storage/app/private -cf - . > "$stage/private.tar"
"${dc[@]}" run --rm --no-deps -T backend php tools/recovery-probe.php > "$stage/vault-probe.enc"
(cd "$stage" && sha256sum database.dump private.tar vault-probe.enc > SHA256SUMS)
restic backup /data --tag alquivo --host alquivo
restic check --read-data
echo 'Encrypted backup verified. Copy the repository OFF this machine before relying on it.'
echo 'The document keyring and APP_KEY are intentionally excluded: store them separately in your secret manager.'
