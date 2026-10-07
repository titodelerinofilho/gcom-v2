#!/bin/sh
set -eu
# Cache is generated per image; sessions and logs remain in the persistent volume.
rm -rf /app/var/cache/prod
php /app/bin/console cache:warmup --env=prod --no-debug
exec docker-php-entrypoint "$@"
