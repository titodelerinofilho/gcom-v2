#!/bin/sh
set -eu
umask 077
: "${BACKUP_RETENTION_DAYS:=30}"
case "$BACKUP_RETENTION_DAYS" in ''|*[!0-9]*) echo 'Invalid retention' >&2; exit 1;; esac
[ "$BACKUP_RETENTION_DAYS" -gt 0 ] || exit 1
stamp=$(date -u +%Y%m%dT%H%M%SZ)
tmp=$(mktemp "/backups/gcom-$stamp.partial-XXXXXX")
file="/backups/gcom-$stamp-${tmp##*-}.dump"
trap 'rm -f "$tmp"' EXIT INT TERM
pg_dump --format=custom --no-owner --no-acl --file="$tmp" --dbname="$PGDATABASE"
# Validate the archive directory before publishing the file.
pg_restore --list "$tmp" > /dev/null
mv "$tmp" "$file"
(cd /backups && sha256sum "$(basename "$file")" > "$(basename "$file").sha256")
printf '%s\n' "$stamp" > /backups/last-success
# Delete only published backups after a successful new backup.
find /backups -name 'gcom-*.dump' -type f -mtime +"$BACKUP_RETENTION_DAYS" -exec rm -f '{}' \;
find /backups -name 'gcom-*.dump.sha256' -type f -mtime +"$BACKUP_RETENTION_DAYS" -exec rm -f '{}' \;
printf '{"event":"backup.completed","file":"%s","time":"%s"}\n' "$file" "$stamp"
