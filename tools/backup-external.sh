#!/usr/bin/env bash
# Guard for an explicitly mounted network backup target; no credentials here.
# Configuration/mounting, expiry, monitoring and recovery still need real setup.
set -Eeuo pipefail
ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
MOUNT="${BACKUP_EXTERNAL_MOUNT:-}"
[[ "$MOUNT" == /* && -d "$MOUNT" ]] || { echo 'Set BACKUP_EXTERNAL_MOUNT to an existing absolute network mount path.' >&2; exit 1; }
mountpoint -q -- "$MOUNT" || { echo 'External backup target is NOT mounted; refusing a local fallback.' >&2; exit 1; }
TYPE="$(findmnt -rn -o FSTYPE --mountpoint "$MOUNT")"
case "$TYPE" in
  cifs|smb3|fuse.sshfs|sshfs) ;;
  *) echo 'Backup target must be a configured SMB/CIFS or SSHFS network mount, not a local filesystem.' >&2; exit 1 ;;
esac
[[ ! -L "$MOUNT/alquivo-restic" ]] || { echo 'The backup repository must not be a symbolic link.' >&2; exit 1; }
export BACKUP_REPOSITORY_DIR="$MOUNT/alquivo-restic"
export COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.production.yml}"
exec bash "$ROOT/tools/backup.sh"
