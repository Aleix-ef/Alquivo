#!/usr/bin/env bash
set -Eeuo pipefail
WORK="${BACKUP_WORK_DIR:-/opt/alquivo/shared/backups}"
MOUNT="${BACKUP_EXTERNAL_MOUNT:-/mnt/alquivo-backups}"
mountpoint -q -- "$MOUNT" || { echo 'External backup mount is missing.' >&2; exit 1; }
[[ ! -e "$WORK/paused-services" ]] || { echo 'Application resume journal still exists.' >&2; exit 1; }
[[ -f "$WORK/last-success" ]] || { echo 'No verified external backup has completed.' >&2; exit 1; }
age=$(( $(date +%s) - $(stat -c %Y -- "$WORK/last-success") ))
(( age >= 0 && age <= 30 * 3600 )) || { echo 'Last verified external backup is older than 30 hours or has an invalid timestamp.' >&2; exit 1; }
echo 'External backup freshness is within 30 hours.'
