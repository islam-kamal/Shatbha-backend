#!/usr/bin/env bash
set -uo pipefail

cd /var/www/html

if [ -z "${APP_URL:-}" ]; then
  if [ -n "${KOYEB_PUBLIC_DOMAIN:-}" ]; then
    export APP_URL="https://${KOYEB_PUBLIC_DOMAIN}"
  elif [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
    export APP_URL="$RENDER_EXTERNAL_URL"
  fi
fi

export PGCONNECT_TIMEOUT=5

DB_URL_VALUE="${DB_URL:-${DATABASE_URL:-}}"
if [ -n "$DB_URL_VALUE" ] && [[ "$DB_URL_VALUE" != *"connect_timeout="* ]]; then
  separator='?'
  [[ "$DB_URL_VALUE" == *"?"* ]] && separator='&'
  DB_URL_VALUE="${DB_URL_VALUE}${separator}connect_timeout=5"
fi

if [ -z "$DB_URL_VALUE" ]; then
  echo "ERROR: DATABASE_URL / DB_URL is empty"
fi

cat > .env <<EOF
APP_NAME=Shatbha
APP_ENV=${APP_ENV:-production}
APP_KEY=${APP_KEY:-}
APP_DEBUG=${APP_DEBUG:-false}
APP_URL=${APP_URL:-}
LOG_CHANNEL=stderr
LOG_LEVEL=error
DB_CONNECTION=pgsql
DATABASE_URL=${DB_URL_VALUE}
DB_URL=${DB_URL_VALUE}
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
EOF

if [ -f vendor/autoload.php ]; then
  php artisan package:discover --ansi || true
  php artisan config:clear || true

  echo "Running migrations..."
  migrated=0
  for i in $(seq 1 8); do
    if php artisan migrate --force; then
      migrated=1
      break
    fi
    echo "Database not ready yet (${i}/8)..."
    sleep 2
  done

  if [ "$migrated" -eq 1 ]; then
    php artisan db:seed --force || echo "Seeding skipped or failed"
  else
    echo "ERROR: migrations failed; login will return a database error"
  fi

  php artisan config:cache || true
  php artisan route:cache || true
else
  echo "WARNING: vendor/autoload.php missing"
fi

exec apache2-foreground
