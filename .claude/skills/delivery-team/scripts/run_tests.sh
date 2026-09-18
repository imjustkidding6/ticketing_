#!/usr/bin/env bash
# Run the PHPUnit suite against the dockerised test database.
#
# Why this exists: phpunit.xml pins DB_USERNAME/DB_PASSWORD to root/root, and
# PHPUnit sets those before Laravel loads .env -- Dotenv is immutable, so .env
# cannot override them. When the local MySQL container's root password is
# anything else, every test fails on authentication before executing, which
# looks exactly like "the branch broke everything". Passing the real credentials
# explicitly sidesteps the whole trap.
#
# Any arguments are forwarded to `artisan test`, e.g.
#   run_tests.sh --filter=TicketControllerTest
#   run_tests.sh --compact

set -uo pipefail

CONTAINER="${TICKETING_APP_CONTAINER:-ticketing-app}"
DB_HOST="${TEST_DB_HOST:-mysql}"
DB_PORT="${TEST_DB_PORT:-3306}"

# Two agents running the suite at once against the same database tear each
# other's schema down mid-run; it surfaces as "table users already exists",
# which reads like a broken branch rather than a collision. Set TEST_DB_NAME
# to give your run its own database when anyone else might be testing.
DB_NAME="${TEST_DB_NAME:-ticketing_test}"

if ! docker ps --format '{{.Names}}' | grep -qx "$CONTAINER"; then
    echo "error: container '$CONTAINER' is not running." >&2
    echo "Start it with 'make up' (or docker compose up -d), or set" >&2
    echo "TICKETING_APP_CONTAINER to the right name." >&2
    exit 2
fi

# Prefer an explicitly supplied password; otherwise read the one the app is
# actually configured with, so this keeps working if the container is rebuilt.
if [ -n "${TEST_DB_PASSWORD:-}" ]; then
    DB_PASSWORD="$TEST_DB_PASSWORD"
else
    DB_PASSWORD="$(grep -E '^DB_PASSWORD=' .env 2>/dev/null | head -1 | cut -d= -f2- | tr -d '"'"'"'')"
fi

if [ -z "$DB_PASSWORD" ]; then
    echo "error: no database password found. Set TEST_DB_PASSWORD or DB_PASSWORD in .env." >&2
    exit 2
fi

ARGS=("$@")
if [ ${#ARGS[@]} -eq 0 ]; then
    ARGS=(--compact)
fi

# Create the database when it is not the shared default, so TEST_DB_NAME needs
# no setup step of its own.
if [ "$DB_NAME" != "ticketing_test" ]; then
    DB_C=$(docker ps --filter ancestor=mysql:8.0 --format '{{.Names}}' | grep -i ticketing | head -1)
    if [ -n "$DB_C" ]; then
        docker exec "$DB_C" mysql -uroot -p"$DB_PASSWORD" \
            -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`;" 2>/dev/null \
            && echo "Using isolated test database: ${DB_NAME}"
    fi
fi

echo "Running: artisan test ${ARGS[*]}  (db ${DB_NAME} on ${DB_HOST}:${DB_PORT} in ${CONTAINER})"

docker exec \
    -e DB_HOST="$DB_HOST" \
    -e DB_PORT="$DB_PORT" \
    -e DB_DATABASE="$DB_NAME" \
    -e DB_PASSWORD="$DB_PASSWORD" \
    "$CONTAINER" php artisan test "${ARGS[@]}"

STATUS=$?

# Tests run inside the container execute as root, which leaves the branding
# fixture directory root-owned and breaks any later host-side run with a
# confusing "Permission denied" from FilesystemIterator. Clean it up while we
# still have the container handy.
docker exec "$CONTAINER" rm -rf storage/framework/testing/disks/public/tenant-logos 2>/dev/null || true

exit $STATUS
