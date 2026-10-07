#!/bin/sh
set -eu
# Refuse to restore into the configured application database.
file=${1:?Usage: restore.sh /backups/archive.dump new_validation_database}
target=${2:?Provide a NEW validation database name}
case "$target" in ''|*[!a-zA-Z0-9_]*) echo 'Invalid database name' >&2; exit 1;; esac
[ "$target" != "$PGDATABASE" ] || { echo 'Refusing to restore over application database' >&2; exit 1; }
[ -f "$file" ] && [ -f "$file.sha256" ] || { echo 'Archive or checksum missing' >&2; exit 1; }
(cd "$(dirname "$file")" && sha256sum -c "$(basename "$file").sha256")
# createdb fails if the target already exists. No drop, --clean, or overwrite.
createdb --maintenance-db="$PGDATABASE" "$target"
pg_restore --exit-on-error --single-transaction --no-owner --no-acl --dbname="$target" "$file"
printf '{"event":"restore.completed","database":"%s"}\n' "$target"
