#!/bin/sh

# Exit on error
set -e

echo "Starting deployment..."

# Run migrations
echo "Running migrations..."
php artisan migrate --force

# Cache config and routes
echo "Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Ensure log permissions
touch /var/www/html/storage/logs/worker.log
touch /var/www/html/storage/logs/scheduler.log
chown www-data:www-data /var/www/html/storage/logs/*.log

# Start Supervisor
echo "Starting Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
