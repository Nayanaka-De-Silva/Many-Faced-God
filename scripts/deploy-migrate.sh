#!/bin/sh
# Runs INSIDE the app container during deploy: waits for a real, authenticated
# app -> database connection, then applies migrations.
#
# The db service healthcheck only proves MySQL answers inside the db container;
# it does not prove the app container can reach it. When the app joins shared
# networks another stack also puts a "db" service on, the name resolves
# non-deterministically and connections fail intermittently (issue #74). An
# authenticated `SELECT 1` from here is the real check.
#
# Why `mysql`, not `mysqladmin ping`: ping reports success on access-denied, so
# it only proves something is listening. Not `php artisan db:show`: its output
# formatter needs the intl extension, absent from this image, so it always exits
# non-zero. --skip-ssl-verify-server-cert: mysql 8.0 presents a self-signed cert
# the client rejects by default.
#
# DB_HOST/DB_PORT come from the container environment (compose env_file). A
# deployed .env need not set DB_PORT -- Laravel's own config default covers the
# app, and an empty "$DB_PORT" would collapse -P"" to a bare -P that swallows
# the next argument.
set -e

db_host="${DB_HOST:-mfg-db}"
db_port="${DB_PORT:-3306}"

db_probe() {
    mysql --protocol=TCP -h"$db_host" -P"$db_port" \
        -u"$DB_USERNAME" -p"$DB_PASSWORD" \
        --skip-ssl-verify-server-cert -e "SELECT 1"
}

attempt=0
echo "Checking app -> db connectivity before running migrations..."
until db_probe >/dev/null 2>&1; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 10 ]; then
        echo "ERROR: database not reachable from app after 30s" >&2
        db_probe >&2 || true
        exit 1
    fi
    echo "Waiting for app -> db readiness ($attempt/10)..."
    sleep 3
done

php artisan migrate --force
