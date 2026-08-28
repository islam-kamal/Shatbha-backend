#!/usr/bin/env bash
# Prepare Laravel, then start Apache. Do not block on Postgres.
set -uo pipefail

cd /var/www/html

if [ -z "${APP_URL:-}" ]; then
  if [ -n "${KOYEB_PUBLIC_DOMAIN:-}" ]; then
    export APP_URL="https://${KOYEB_PUBLIC_DOMAIN}"
  elif [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
    export APP_URL="$RENDER_EXTERNAL_URL"
  fi
fi

DB_URL_VALUE="${DB_URL:-${DATABASE_URL:-}}"
if [ -n "$DB_URL_VALUE" ] && [[ "$DB_URL_VALUE" != *"connect_timeout="* ]]; then
  separator='?'
  [[ "$DB_URL_VALUE" == *"?"* ]] && separator='&'
  DB_URL_VALUE="${DB_URL_VALUE}${separator}connect_timeout=5"
fi

cat > .env <<EOF
APP_NAME=Shatbha
APP_ENV=${APP_ENV:-production}
APP_KEY=${APP_KEY:-}
APP_DEBUG=${APP_DEBUG:-false}
APP_URL=${APP_URL:-}
LOG_CHANNEL=${LOG_CHANNEL:-stderr}
DB_CONNECTION=${DB_CONNECTION:-pgsql}
DATABASE_URL=${DB_URL_VALUE}
DB_URL=${DB_URL_VALUE}
SESSION_DRIVER=${SESSION_DRIVER:-database}
CACHE_STORE=${CACHE_STORE:-database}
QUEUE_CONNECTION=${QUEUE_CONNECTION:-sync}
EOF

if [ -f vendor/autoload.php ]; then
  php artisan package:discover --ansi || true
  php artisan config:cache || true
  php artisan route:cache || true
  (
    for i in $(seq 1 20); do
      if php artisan migrate --force && php artisan db:seed --force; then
        echo "Migrations complete"
        exit 0
      fi
      echo "Database not ready yet (${i}/20)..."
      sleep 3
    done
    echo "Database never became ready"
  ) &
else
  echo "WARNING: vendor/autoload.php missing"
fi

exec apache2-foreground
