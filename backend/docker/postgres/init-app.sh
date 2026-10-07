#!/bin/sh
set -eu

if [ "$DATABASE_USER" = "$POSTGRES_USER" ]; then
    printf '%s\n' 'A conta da aplicação deve ser diferente da conta proprietária.' >&2
    exit 1
fi

# Read credentials inside psql, without exposing passwords in command arguments.
# This also runs from make init for databases with an existing persistent volume.
psql --no-psqlrc --quiet -v ON_ERROR_STOP=1 \
    --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<'SQL'
\getenv app_user DATABASE_USER
\getenv app_password DATABASE_PASSWORD
\getenv owner_user POSTGRES_USER
\getenv owner_password POSTGRES_PASSWORD
\getenv db_name POSTGRES_DB

BEGIN;

SELECT format('CREATE ROLE %I LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE', :'app_user')
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = :'app_user')
\gexec

ALTER ROLE :"app_user" LOGIN PASSWORD :'app_password' NOSUPERUSER NOCREATEDB NOCREATEROLE;
ALTER ROLE :"owner_user" PASSWORD :'owner_password';

GRANT CONNECT ON DATABASE :"db_name" TO :"app_user";
GRANT USAGE, CREATE ON SCHEMA public TO :"app_user";

COMMIT;
SQL

printf '%s\n' 'Credenciais PostgreSQL sincronizadas; dados existentes preservados.'
