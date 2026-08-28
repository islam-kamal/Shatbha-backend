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

# Koyeb sets PORT to the exposed port (default 8000). nginx-php-fpm listens on 80.
PORT="${PORT:-80}"
if [ "$PORT" != "80" ]; then
  echo "Binding nginx to port ${PORT}..."
  grep -rlE 'listen(\s+\[::\])?\s+80\b' /etc/nginx 2>/dev/null | while read -r f; do
    sed -i "s/listen \[::\]:80/listen [::]:${PORT}/g; s/listen 80/listen ${PORT}/g" "$f"
  done
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
  echo "Database never became ready"
  exit 1
fi

echo "Seeding demo data if empty..."
php artisan db:seed --force
