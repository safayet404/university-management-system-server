#!/bin/sh
set -e

echo "=== UniCore Docker Entrypoint ==="

# Generate app key if not set
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
    echo "→ Generating APP_KEY..."
    php artisan key:generate --force
fi

# Clear caches
echo "→ Clearing and caching config..."
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations
echo "→ Running migrations..."
php artisan migrate --force

# Seed if empty
USER_COUNT=$(php artisan tinker --execute="echo \App\Models\User::count();" 2>/dev/null | tail -1 || echo "0")
if [ "$USER_COUNT" = "0" ]; then
    echo "→ Seeding database..."
    php artisan db:seed --force
else
    echo "→ Database already seeded (${USER_COUNT} users), skipping."
fi

# Storage link
php artisan storage:link --force 2>/dev/null || true

# Fix permissions
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

echo "=== Starting services ==="
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf