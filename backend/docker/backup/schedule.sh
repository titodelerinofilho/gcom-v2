#!/bin/sh
set -eu
: "${BACKUP_INTERVAL_SECONDS:=86400}"
case "$BACKUP_INTERVAL_SECONDS" in ''|*[!0-9]*) exit 1;; esac
[ "$BACKUP_INTERVAL_SECONDS" -ge 60 ] || exit 1
while true; do
    if ! /usr/local/bin/backup.sh; then
        printf '{"event":"backup.failed"}\n' >&2
        sleep 60
    else
        sleep "$BACKUP_INTERVAL_SECONDS"
    fi
done
