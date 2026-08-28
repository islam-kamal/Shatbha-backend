# Shatbha-backend

Laravel 12 API for **شطبة / Shatbha** — RTL finishing-pack ERP (Sanctum auth, customer journals, expenses, contractor jobs, P&L).

Companion Flutter app: run locally against this API, or point it at the hosted URL with `--dart-define=API_BASE_URL=…`.

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

## Host on Koyeb (free, no card)

PHP deploys as **Docker**. Use **Koyeb** for the API and **Neon** for Postgres. Koyeb’s own free database is only **5 compute hours per month**, so Neon is the durable free option (no card, scales to zero when idle).

1. Push this repo to GitHub (`islam-kamal/Shatbha-backend`).
2. Create a free Postgres database at [console.neon.tech](https://console.neon.tech) (no card). Click **Connect**, copy the connection string (must include `sslmode=require`). Use the **direct** (non-pooled) URI.
3. Sign up at [app.koyeb.com](https://app.koyeb.com) (no card). **Create Web Service** → **GitHub** → this repo → branch `main`.
4. Builder: **Dockerfile**. Instance: **Free** (Frankfurt or Washington, D.C.).
5. Exposed ports: keep Koyeb’s default **8000** (the start script binds nginx to `$PORT`). Health check path: `/up` if offered.
6. Environment variables (Bulk Edit):

```
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:PASTE_php_artisan_key_generate_show
APP_URL=https://{{ KOYEB_PUBLIC_DOMAIN }}
LOG_CHANNEL=stderr
DB_CONNECTION=pgsql
DATABASE_URL=postgresql://USER:PASS@HOST/neondb?sslmode=require
DB_URL={{ DATABASE_URL }}
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync
```

Generate `APP_KEY` with `php artisan key:generate --show`. Paste Neon’s URI into `DATABASE_URL`.
7. Deploy. URL looks like `https://shatbha-xxxxx.koyeb.app`. Check `/up`, then:

```bash
curl -s -X POST https://YOUR-APP.koyeb.app/api/v1/login \
  -H 'Accept: application/json' \
  -d 'email=admin@shatbha.test&password=password'
```

Point Flutter at `--dart-define=API_BASE_URL=https://YOUR-APP.koyeb.app`.

Free-tier notes: the web instance **sleeps after ~1 hour idle** (cold start on the next request). Neon also sleeps when idle. First request after sleep can take ~30–60s. Demo users seed only if they are missing.

**Render** (`render.yaml`) is a fallback. That workspace is locked from paid services until Sep 1, 2026 if a card was removed.
