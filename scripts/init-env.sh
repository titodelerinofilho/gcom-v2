#!/bin/sh
set -eu
umask 077

env_file=${1:-.env}

if [ ! -f "$env_file" ]; then
    cp .env.example "$env_file"
fi

temporary_file=$(mktemp "${env_file}.tmp.XXXXXX")
trap 'rm -f "$temporary_file"' EXIT INT TERM

# Never source the environment file or print credentials.
awk -v app_secret="$(openssl rand -hex 32)" \
    -v app_password="$(openssl rand -hex 32)" \
    -v owner_password="$(openssl rand -hex 32)" '
function key_of(line) {
    return substr(line, 1, index(line, "=") - 1)
}
function missing(value) {
    gsub(/^[[:space:]]+|[[:space:]]+$/, "", value)
    gsub(/^["\047]|["\047]$/, "", value)

    return value == "" || value ~ /^REPLACE/
}
FNR == NR {
    original[++count] = $0

    if ($0 ~ /^[A-Z_][A-Z0-9_]*=/) {
        key = key_of($0)
        values[key] = substr($0, index($0, "=") + 1)
    }

    next
}
/^[A-Z_][A-Z0-9_]*=/ {
    key = key_of($0)
    defaults[++default_count] = key
    default_values[key] = substr($0, index($0, "=") + 1)
}
END {
    legacy["POSTGRES_DB"] = "DATABASE_NAME"
    legacy["POSTGRES_USER"] = "DATABASE_OWNER_USER"
    legacy["POSTGRES_PASSWORD"] = "DATABASE_OWNER_PASSWORD"
    legacy["APP_DATABASE_USER"] = "DATABASE_USER"
    legacy["APP_DATABASE_PASSWORD"] = "DATABASE_PASSWORD"

    for (key in legacy) {
        replacement = legacy[key]

        if (!(replacement in values) && key in values) {
            values[replacement] = values[key]
        }
    }

    for (index_default = 1; index_default <= default_count; index_default++) {
        key = defaults[index_default]

        if (!(key in values)) {
            values[key] = default_values[key]
        }
    }

    if (missing(values["APP_SECRET"])) {
        values["APP_SECRET"] = app_secret
    }

    if (missing(values["DATABASE_PASSWORD"])) {
        values["DATABASE_PASSWORD"] = app_password
    }

    if (missing(values["DATABASE_OWNER_PASSWORD"])) {
        values["DATABASE_OWNER_PASSWORD"] = owner_password
    }

    for (index_original = 1; index_original <= count; index_original++) {
        line = original[index_original]

        if (line ~ /^[A-Z_][A-Z0-9_]*=/) {
            key = key_of(line)

            if (key in legacy || key == "DATABASE_URL") {
                continue
            }

            print key "=" values[key]
            written[key] = 1
        } else {
            print line
        }
    }

    for (index_default = 1; index_default <= default_count; index_default++) {
        key = defaults[index_default]

        if (!(key in written)) {
            print key "=" values[key]
        }
    }
}
' "$env_file" .env.example > "$temporary_file"

mv "$temporary_file" "$env_file"
trap - EXIT INT TERM
printf '%s\n' 'Ambiente preparado; segredos existentes preservados.'
