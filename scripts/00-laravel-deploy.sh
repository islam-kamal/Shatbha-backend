#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

if [ -z "${APP_URL:-}" ]; then
  if [ -n "${KOYEB_PUBLIC_DOMAIN:-}" ]; then
    export APP_URL="https://${KOYEB_PUBLIC_DOMAIN}"
  elif [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
    export APP_URL="$RENDER_EXTERNAL_URL"
  fi
fi

# php-fpm drops env vars by default, so HTTP requests would miss APP_KEY.
if grep -q '^clear_env' /usr/local/etc/php-fpm.d/www.conf 2>/dev/null; then
  sed -i 's/^clear_env.*/clear_env = no/' /usr/local/etc/php-fpm.d/www.conf
else
  echo 'clear_env = no' >> /usr/local/etc/php-fpm.d/www.conf
fi

DB_URL_VALUE="${DB_URL:-${DATABASE_URL:-}}"
cat > .env <<EOF
APP_NAME=Shatbha
APP_ENV=${APP_ENV:-production}
APP_KEY=${APP_KEY:-}
APP_DEBUG=${APP_DEBUG:-false}
APP_URL=${APP_URL:-}
LOG_CHANNEL=${LOG_CHANNEL:-stderr}
DB_CONNECTION=${DB_CONNECTION:-pgsql}
DATABASE_URL=${DATABASE_URL:-}
DB_URL=${DB_URL_VALUE}
SESSION_DRIVER=${SESSION_DRIVER:-database}
CACHE_STORE=${CACHE_STORE:-database}
QUEUE_CONNECTION=${QUEUE_CONNECTION:-sync}
EOF

if [ ! -f vendor/autoload.php ]; then
  echo "Running composer..."
  composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --working-dir=/var/www/html
fi

echo "Discovering packages..."
php artisan package:discover --ansi

echo "Caching config..."
php artisan config:cache

echo "Caching routes..."
php artisan route:cache

echo "Waiting for Postgres..."
ok=0
for i in $(seq 1 40); do
  if php artisan migrate --force; then
    ok=1
    break
  fi
  echo "Database not ready yet (${i}/40)..."
  sleep 3
done
if [ "$ok" -ne 1 ]; then
  echo "Database never became ready (HTTP will still start)"
else
  echo "Seeding demo data if empty..."
  php artisan db:seed --force
fi
