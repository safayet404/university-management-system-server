#!/bin/sh
set -e

echo "=== UniCore Docker Entrypoint ==="

# Create .env from environment variables if it doesn't exist
if [ ! -f /var/www/.env ]; then
    echo "→ Creating .env file..."
    cat > /var/www/.env << ENVEOF
APP_NAME=${APP_NAME:-UniCore}
APP_ENV=${APP_ENV:-production}
APP_KEY=${APP_KEY:-}
APP_DEBUG=${APP_DEBUG:-false}
APP_URL=${APP_URL:-http://localhost}
APP_TIMEZONE=Asia/Dhaka

LOG_CHANNEL=stderr
LOG_LEVEL=error

DB_CONNECTION=${DB_CONNECTION:-pgsql}
DB_HOST=${DB_HOST:-localhost}
DB_PORT=${DB_PORT:-5432}
DB_DATABASE=${DB_DATABASE:-neondb}
DB_USERNAME=${DB_USERNAME:-postgres}
DB_PASSWORD=${DB_PASSWORD:-}
DB_SSLMODE=${DB_SSLMODE:-require}

CACHE_STORE=${CACHE_STORE:-file}
SESSION_DRIVER=${SESSION_DRIVER:-file}
SESSION_LIFETIME=120
QUEUE_CONNECTION=${QUEUE_CONNECTION:-sync}

SANCTUM_STATEFUL_DOMAINS=${SANCTUM_STATEFUL_DOMAINS:-localhost}
CORS_ALLOWED_ORIGINS=${CORS_ALLOWED_ORIGINS:-http://localhost:3000}
ENVEOF
fi

# Generate app key if not set
if [ -z "$APP_KEY" ]; then
    echo "→ Generating APP_KEY..."
    php artisan key:generate --force
fi

# Clear and cache
echo "→ Caching config/routes/views..."
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
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache 2>/dev/null || true

echo "=== Starting services ==="
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
