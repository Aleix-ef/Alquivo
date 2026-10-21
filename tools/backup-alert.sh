#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
case "${1:-}" in
  alquivo-backup.service|alquivo-backup-watch.service|alquivo-backup-resume.service|alquivo-backup-test.service) ;;
  *) echo 'Invalid backup notification source.' >&2; exit 1 ;;
esac
image=$(docker inspect --format '{{.Image}}' alquivo-production-backend-1)
owner=$(stat -c '%u:%g' /opt/alquivo/shared/.env.production)
# Independent of the app/queue/database; no public ports or user data in the alert.
docker run --rm --network host --read-only --cap-drop ALL \
  --security-opt no-new-privileges:true \
  -v /opt/alquivo/shared/.env.production:/run/production.env:ro \
  -v "$ROOT/tools/backup-alert.php:/run/backup-alert.php:ro" \
  --user "$owner" "$image" php /run/backup-alert.php "$1" </dev/null
