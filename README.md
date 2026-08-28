# Shatbha-backend

Laravel 12 API for **شطبة / Shatbha** — RTL finishing-pack ERP (Sanctum auth, customer journals, expenses, contractor jobs, P&L).

Companion Flutter app: run locally against this API, or point it at the Render URL with `--dart-define=API_BASE_URL=…`.

## Demo users

| Email | Password | Role |
|---|---|---|
| `admin@shatbha.test` | `password` | مدير — full access including P&L |
| `clerk@shatbha.test` | `password` | كاتب — income statement returns **403** |

Seeded fixtures: contractor remaining **7,000**; P&L net **900** (supervision cash 1,000 − office 100).

## Local run

Default is **SQLite**. PHP 8.2+ and Composer required.

```bash
composer install
touch database/database.sqlite
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve --host=127.0.0.1 --port=8000
```

API root: `http://127.0.0.1:8000/api/v1`

```bash
curl -s -X POST http://127.0.0.1:8000/api/v1/login \
  -H 'Accept: application/json' \
  -d 'email=admin@shatbha.test&password=password'
```

Optional MySQL is in `docker-compose.yml`:

```bash
docker compose up -d mysql
# then set DB_CONNECTION=mysql, DB_DATABASE=shatbha, DB_USERNAME=shatbha, DB_PASSWORD=secret
php artisan migrate:fresh --seed
```

Tests: `php artisan test`

## Host on Render

PHP is not a native Render runtime, so this API deploys as **Docker + PostgreSQL**. Blueprint: [`render.yaml`](render.yaml) ([docs](https://render.com/docs/deploy-php-laravel-docker)).

1. Generate a Laravel key:

```bash
php artisan key:generate --show
```

2. Open [Render Dashboard](https://dashboard.render.com/) → **New** → **Blueprint**. Connect this GitHub repo. Render reads `render.yaml` and creates `shatbha-api` + `shatbha-db`.
3. When prompted for `APP_KEY`, paste the `base64:...` value from step 1. Do not use Render’s auto-generated secret — Laravel needs that exact format.
4. After the first deploy, open `https://YOUR-SERVICE.onrender.com/up`. Login:

```bash
curl -s -X POST https://YOUR-SERVICE.onrender.com/api/v1/login \
  -H 'Accept: application/json' \
  -d 'email=admin@shatbha.test&password=password'
```

Demo data seeds only if those users are missing.

**Manual setup** (no Blueprint): New **Web Service**, Language **Docker**, Dockerfile path `./Dockerfile`. New **PostgreSQL**. Set `DB_CONNECTION=pgsql`, `DATABASE_URL` / `DB_URL` to the database **Internal** URL, `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`.
