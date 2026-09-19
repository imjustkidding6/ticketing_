#!/bin/sh
set -e

echo "Running Laravel optimizations..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Migrations are owned by the deploy workflow's one-off ECS task, which waits
# for them and aborts the deploy on failure. Running them here too meant every
# web task and every worker task raced each other and that one-off task.
# Local/compose environments set RUN_MIGRATIONS_ON_BOOT=true, where a single
# container is the only migrator.
if [ "${RUN_MIGRATIONS_ON_BOOT:-false}" = "true" ]; then
    echo "Running database migrations (RUN_MIGRATIONS_ON_BOOT=true)..."
    php artisan migrate --force
else
    echo "Skipping migrations on boot; the deploy pipeline owns them."
fi

echo "Starting Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor.d/supervisord.ini
