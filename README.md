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

## Host on Render (free)

PHP is not a native Render runtime, so this API deploys as **Docker + PostgreSQL**. Blueprint: [`render.yaml`](render.yaml) ([docs](https://render.com/docs/deploy-php-laravel-docker)).

Free-tier limits (no credit card required):

- Web service **spins down after 15 minutes idle**; the next request takes ~1 minute to wake.
- Free Postgres is **1 GB** and **expires after 30 days** (then a 14-day grace period). Recreate or upgrade before then, or data is deleted.
- 750 free instance hours per month.

1. Generate a Laravel key:

```bash
php artisan key:generate --show
```

2. Open [Render Dashboard](https://dashboard.render.com/) → **New** → **Blueprint**. Connect this GitHub repo. Render reads `render.yaml` and creates `shatbha-api` + `shatbha-db` on the **free** plan.
3. When prompted for `APP_KEY`, paste the `base64:...` value from step 1. Do not use Render’s auto-generated secret — Laravel needs that exact format.
4. After the first deploy, open `https://YOUR-SERVICE.onrender.com/up`. Login:

```bash
curl -s -X POST https://YOUR-SERVICE.onrender.com/api/v1/login \
  -H 'Accept: application/json' \
  -d 'email=admin@shatbha.test&password=password'
```

Point the Flutter app at that URL with `--dart-define=API_BASE_URL=https://YOUR-SERVICE.onrender.com`.

Demo data seeds only if those users are missing.

**Manual setup** (no Blueprint): New **Web Service**, Language **Docker**, plan **Free**, Dockerfile path `./Dockerfile`. New **PostgreSQL**, plan **Free**. Set `DB_CONNECTION=pgsql`, `DATABASE_URL` / `DB_URL` to the database **Internal** URL, `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`.
