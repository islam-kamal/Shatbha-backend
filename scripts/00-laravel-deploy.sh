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

export PGCONNECT_TIMEOUT=8

if ! php scripts/write-env.php; then
  echo "ERROR: cannot reach Neon. Apache will start but login will fail."
else
  if [ -f vendor/autoload.php ]; then
    php artisan package:discover --ansi || true
    php artisan config:clear || true
    echo "Running migrations..."
    if php artisan migrate --force && php artisan db:seed --force; then
      echo "Migrations complete"
    else
      echo "ERROR: migrations failed"
    fi
    php artisan config:cache || true
    php artisan route:cache || true
  else
    echo "WARNING: vendor/autoload.php missing"
  fi
fi

exec apache2-foreground
