#!/bin/sh
set -e

echo "Running Laravel optimizations..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Running database migrations..."
php artisan migrate --force

echo "Starting Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor.d/supervisord.ini
