# Shatbha-backend

Laravel 12 JSON API for **شطبة / Shatbha** — a small RTL finishing-pack ERP used by a companion Flutter app.

The API covers authentication, company profile, customers and contractors, customer journals (cash / goods / labor / returns), office expenses, contractor jobs and payments, and management reports including a manager-only profit & loss (income statement).

Repository: [github.com/islam-kamal/Shatbha-backend](https://github.com/islam-kamal/Shatbha-backend)

---

## Contents


1. [What this project does](#what-this-project-does)
2. [Features](#features)
3. [Tech stack](#tech-stack)
4. [Requirements](#requirements)
5. [Local development](#local-development)
6. [How to continue work on it](#how-to-continue-work-on-it)
7. [API overview](#api-overview)
8. [Demo users](#demo-users)
9. [Flutter app](#flutter-app)
10. [Project layout](#project-layout)
11. [Tests](#tests)
12. [Deployment](#deployment)
    - [Two ways to deploy](#two-ways-to-deploy)
    - [Way 1 — Reset (wipes all data)](#way-1--reset-wipes-all-data)
    - [Way 2 — Update in place (keeps all data)](#way-2--update-in-place-keeps-all-data)
13. [Troubleshooting](#troubleshooting)

---

## What this project does

Shatbha is a finishing-pack (تشطيب) workshop system. Staff record:

- **Customers** (اتفاق / إشراف) and their ledger: sales of goods, labor, cash collections, and returns
- **Contractors** and **jobs** (quantity × unit price) with staged **payments**
- **Office expenses** by category
- **Reports**: customer balances, contractor remaining, expenses by category, and a **P&L** (supervision cash minus office bills) for the manager only

All data is scoped to a **company**. Seeded demo company is **شطبة**. Users authenticate with Laravel Sanctum tokens; the Flutter client stores that token and sends `Authorization: Bearer …` on every request.

---

## Features

| Area | What it does |
|---|---|
| **Auth** | Login returns a Sanctum token + user + company. Logout revokes the current token. `GET /me` returns the session user. |
| **Roles** | `admin` (مدير) sees everything including P&L. `clerk` (كاتب) can run day-to-day journals but gets **403** on the income statement. |
| **Company** | Name, subtitle, pack. Shown on login and editable via `GET/PUT /company`. |
| **Parties** | Customers and contractors: name, phone, kind (`agreement` / `supervision`), opening balance, agreement estimate, supervision percent. |
| **Customer journal** | Entries: `cash`, `goods`, `labor`, `return`. Per-customer **statement** with opening / sales / collect / returns / closing. |
| **Expenses** | Dated expenses with optional category. List totals and **by-category** report. |
| **Work types & expense categories** | Lookup tables the Flutter forms use when creating entries. |
| **Contractor jobs** | Job = qty × unit price. Payments cannot exceed remaining. Sequence is assigned automatically. |
| **Reports** | Customer closings, contractor remaining, expenses by category, income statement (admin). |
| **Multi-tenant-lite** | Every query filters by `company_id` of the logged-in user. |

Seeded demo numbers (after `migrate:fresh --seed`): contractor remaining **7,000**; P&L net **900** (supervision cash 1,000 − office 100).

---

## Tech stack

| Piece | Choice |
|---|---|
| Framework | Laravel 12 |
| PHP | **8.4.1+** (Composer lockfile requires this; local PHP 8.5 is fine) |
| Auth | Laravel Sanctum (API tokens named `flutter`) |
| Local DB | SQLite (`database/database.sqlite`) |
| Production DB | PostgreSQL (Neon, or Render Postgres) |
| HTTP | Apache in Docker (`php:8.4-apache`, document root `public/`) |
| Queue / cache / session | Database drivers in production; `QUEUE_CONNECTION=sync` on the free host |
| Tests | PHPUnit feature tests (`tests/Feature`) |

---

## Requirements

- PHP **8.4+** with extensions: `pdo_sqlite` (local), `pdo_pgsql` (production), `mbstring`, `xml`, `curl`, `zip`
- [Composer](https://getcomposer.org/) 2
- Optional: Docker (production image and optional MySQL via `docker-compose.yml`)
- Optional: Flutter SDK for the mobile client

---

## Local development

Default database is **SQLite**. No Docker required for day-to-day API work.

First time (or when you want a **clean local DB** — this is [Way 1](#way-1--reset-wipes-all-data), it deletes local data):

```bash
git clone https://github.com/islam-kamal/Shatbha-backend.git
cd Shatbha-backend
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed
php artisan serve --host=127.0.0.1 --port=8000
```

API root: `http://127.0.0.1:8000/api/v1`  
Health (no auth): `http://127.0.0.1:8000/up`

Login:

```bash
curl -s -X POST http://127.0.0.1:8000/api/v1/login \
  -H 'Accept: application/json' \
  -d 'email=admin@shatbha.test&password=password'
```

Use the returned `token` as:

```bash
curl -s http://127.0.0.1:8000/api/v1/me \
  -H 'Accept: application/json' \
  -H "Authorization: Bearer YOUR_TOKEN"
```

To update local code **without** wiping SQLite, use [Way 2](#way-2--update-in-place-keeps-all-data) (`migrate --force` + `EcosystemSeeder`, not `migrate:fresh`).

### Optional MySQL

`docker-compose.yml` can start MySQL. Then in `.env`:

```env
DB_CONNECTION=mysql
DB_DATABASE=shatbha
DB_USERNAME=shatbha
DB_PASSWORD=secret
```

```bash
docker compose up -d mysql
php artisan migrate:fresh --seed
```

### Optional local Postgres

Same as production: set `DB_CONNECTION=pgsql` and either discrete `DB_HOST` / `DB_USERNAME` / `DB_PASSWORD` or `DATABASE_URL`.

---

## How to continue work on it

This is a small Laravel API. Typical flow:

1. **Branch from `main`**, keep changes focused (one feature or fix per PR).
2. **Add or change a migration** under `database/migrations` if the schema changes. Run `php artisan migrate` locally.
3. **Update the model** in `app/Models` (`$fillable`, relations, helpers such as `ContractorJob::remaining()`).
4. **Expose it** in `app/Http/Controllers/Api` and register the route in `routes/api.php` (prefix is already `api/v1` via `bootstrap/app.php`).
5. **Protect reports** with `->middleware('admin')` if only the manager should see them (`EnsureAdmin`).
6. **Seed or factory** demo data in `database/seeders/DatabaseSeeder.php` so Flutter and tests stay consistent.
7. **Cover with a feature test** in `tests/Feature` (login, 403 for clerk, journal math). Run `php artisan test`.
8. **Keep JSON shapes stable** if the Flutter app already consumes them (`data`, `token`, `user`, amounts as strings from `number_format`).
9. **Do not commit `.env`**, Neon passwords, or `APP_KEY`. Use placeholders in docs.

Useful Artisan:

```bash
php artisan route:list --path=api
php artisan migrate                          # apply new tables/columns; keeps rows
php artisan migrate:fresh --seed             # RESET: drops every table (local/demo only)
php artisan db:seed --class=EcosystemSeeder  # safe extras; does not wipe journals
php artisan tinker
php artisan test
php artisan key:generate --show              # print a key for hosting (do not commit)
```

API prefix and Sanctum are wired in `bootstrap/app.php`. CORS is open enough for a mobile client; if you add a web admin later, tighten `config/cors.php`.

---

## API overview

Base path: **`/api/v1`**. JSON: send `Accept: application/json`. Authenticated routes need `Authorization: Bearer {token}`.

### Public

| Method | Path | Description |
|---|---|---|
| `GET` | `/up` | Laravel health (HTML “Application up”). Not under `/api`. |
| `POST` | `/api/v1/login` | Body: `email`, `password`. Returns `token` + `user`. |
| `GET` | `/api/v1/db-status` | Whether the app can `SELECT 1` on the current DB (for hosting debug). |

### Authenticated

| Method | Path | Notes |
|---|---|---|
| `POST` | `/logout` | Revokes current token |
| `GET` | `/me` | Current user + company |
| `GET` / `PUT` | `/company` | Company profile (`name`, `subtitle`, `pack`) |
| `GET` / `POST` | `/customers` | Parties with `type=customer` |
| `GET` / `POST` | `/contractors` | Parties with `type=contractor` |
| `GET` / `POST` / `PUT` / `DELETE` | `/parties` | Generic party CRUD; `POST` needs `type` |
| `GET` / `POST` | `/work-types` | Lookup |
| `GET` / `POST` | `/expense-categories` | Lookup |
| `GET` / `POST` | `/customer-entries` | Query: `from`, `to`, `customer_id` |
| `GET` | `/customers/{id}/statement` | Opening, sales, collect, returns, closing |
| `GET` / `POST` | `/expenses` | Query: `from`, `to`. List includes `total` |
| `GET` | `/reports/expenses` | Totals by category |
| `GET` / `POST` | `/jobs` | Contractor jobs |
| `POST` | `/jobs/{id}/payments` | Body: `amount`, `paid_on` |
| `GET` | `/reports/customers` | Closing balances |
| `GET` | `/reports/contractors` | Remaining per contractor |
| `GET` | `/reports/income-statement` | **Admin only.** Query: `from`, `to` |

### Example bodies

**Create customer**

```json
{
  "name": "عميل جديد",
  "phone": "01000000000",
  "kind": "supervision",
  "opening_balance": 0,
  "supervision_percent": 10
}
```

`kind` is `agreement` or `supervision`.

**Customer entry**

```json
{
  "customer_id": 1,
  "entry_date": "2026-08-29",
  "entry_type": "cash",
  "title": "تحصيل",
  "amount": 500,
  "notes": null
}
```

`entry_type`: `cash` | `goods` | `labor` | `return`. Use `labor_amount` / `return_amount` when those types apply.

**Job + payment**

```json
{ "contractor_id": 1, "title": "محارة", "qty": 100, "unit_price": 70 }
```

```json
{ "amount": 1000, "paid_on": "2026-08-29" }
```

---

## Demo users

Created by `DatabaseSeeder` (idempotent: skipped if the email already exists).

| Email | Password | Role |
|---|---|---|
| `admin@shatbha.test` | `password` | مدير — full access including P&L |
| `clerk@shatbha.test` | `password` | كاتب — income statement returns **403** |

Change these in production or disable seeding on a real customer database.

---

## Flutter app

Point the client at this API with a dart-define (no trailing slash):

```bash
flutter run --dart-define=API_BASE_URL=http://127.0.0.1:8000
```

Hosted example (replace with your Northflank URL):

```bash
flutter run --dart-define=API_BASE_URL=https://p02--YOUR-SERVICE--XXXX.code.run
```

The app should `POST /api/v1/login`, store `token`, and send it on subsequent calls. Arabic validation messages come from the API (e.g. wrong password, payment exceeds remaining).

---

## Project layout

```
app/Http/Controllers/Api/   JSON controllers
app/Http/Middleware/        EnsureAdmin
app/Models/                 Company, User, Party, CustomerEntry, Expense, …
bootstrap/app.php           Routing, Sanctum, middleware aliases
routes/api.php              /api/v1 routes
database/migrations/        Schema
database/seeders/           Demo company, users, journals
scripts/                    Docker boot: write .env from DATABASE_URL, migrate, seed
Dockerfile                  Multi-stage: composer:2 → php:8.4-apache
render.yaml                 Render Blueprint (free web + Postgres)
```

Boot in Docker (`scripts/00-laravel-deploy.sh`):

1. Parse `DATABASE_URL` / `DB_URL` into `DB_HOST`, `DB_USERNAME`, etc. (`scripts/write-env.php`). Neon **pooler** hostnames (`-pooler`) are rewritten to the direct endpoint.
2. PDO ping with `sslmode=require` (retries for Neon cold start).
3. `php artisan migrate --force` then `php artisan db:seed --force` (see [Way 1](#way-1--reset-wipes-all-data) vs [Way 2](#way-2--update-in-place-keeps-all-data)).
4. Start Apache on port **80**.

---

## Tests

```bash
php artisan test
```

`tests/Feature/AuthAndJournalTest.php` covers admin login, clerk blocked from P&L, and journal/statement behaviour. Use `RefreshDatabase` + `DatabaseSeeder` for new cases.

---

## Deployment

The app is a **Docker** image (`Dockerfile`). PHP is not a native runtime on most free PaaS hosts, so you always deploy the container. Runtime needs **PHP 8.4** (the lockfile will fail on 8.2).

**Secrets stay in the host’s environment UI**, never in git.

There are **two deploy paths**. Pick one before you run Artisan. Mixing them (especially `migrate:fresh` on a live Neon/Postgres) will delete customers, journals, expenses, and jobs.

---

### Two ways to deploy

| | Way 1 — Reset | Way 2 — Update in place |
|---|---|---|
| **Keeps existing data?** | **No.** Drops every table and reseeds demo rows. | **Yes.** Applies new schema only. Journals, parties, jobs, and expenses stay. |
| **Command that matters** | `php artisan migrate:fresh --seed` | `php artisan migrate --force` then `db:seed --class=EcosystemSeeder` |
| **When to use** | First empty database, local demo reset, or you **intentionally** want a clean start. | Every production/code update after the app already has real (or demo) data. |
| **Typical host** | Local SQLite, brand-new Neon, or recreating the database. | SSH / existing server, or a Docker restart against the **same** Postgres. |

`migrate` only runs **new** migration files. `migrate:fresh` **drops all tables first**. `EcosystemSeeder` is idempotent (skips vendors/products/projects that already exist). `DatabaseSeeder` also skips if `admin@shatbha.test` already exists, then calls `EcosystemSeeder` — but **`migrate:fresh` always wipes first**, so Way 1 still destroys data.

---

### Way 1 — Reset (wipes all data)

Use this when the database should start from zero: empty host, local “start over”, or you accept losing everything.

**What is deleted:** all tables and rows — users, company, customers, contractors, customer entries, expenses, jobs, payments, marketplace/ecosystem seed data.

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate:fresh --seed
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`migrate:fresh --seed` drops the schema, recreates it, then runs `DatabaseSeeder` (demo company **شطبة**, admin/clerk, sample journals) and `EcosystemSeeder` (marketplace vendors, sample project).

Local equivalent (same wipe):

```bash
php artisan migrate:fresh --seed
php artisan serve --host=127.0.0.1 --port=8000
```

**Do not run Way 1 on production Neon** unless you mean to erase the live ERP.

On Docker/Northflank, a **normal redeploy does not run `migrate:fresh`**. Data is only wiped if you drop/recreate the Neon project, empty the database, or you SSH in and run `migrate:fresh` yourself.

---

### Way 2 — Update in place (keeps all data)

Use this for every later deploy: new features, bug fixes, new migrations. The database file/server stays the same; only missing tables/columns are added.

```bash
git pull origin main

composer install --no-dev --optimize-autoloader

php artisan migrate --force

php artisan db:seed --class=EcosystemSeeder --force

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

| Step | Why |
|---|---|
| `git pull origin main` | New code, migrations, and seeders. |
| `composer install --no-dev --optimize-autoloader` | Production PHP packages; no test/dev packages. |
| `php artisan migrate --force` | Run **pending** migrations only. Existing rows are not deleted. `--force` is required when `APP_ENV=production`. |
| `php artisan db:seed --class=EcosystemSeeder --force` | Fill marketplace/project **gaps** if missing. Does **not** recreate the demo journals or wipe parties. |
| `config:cache` / `route:cache` / `view:cache` | Faster boot. After `.env` changes, run `php artisan config:cache` again. |

Do **not** use `migrate:fresh`, `db:wipe`, or `migrate:refresh` here.

If you only need schema updates and no ecosystem rows, you can skip the `EcosystemSeeder` line. Do **not** run `php artisan db:seed --force` (full `DatabaseSeeder`) as a substitute for `migrate:fresh` — on an already-seeded DB it is mostly a no-op (admin email exists), but it is still the wrong habit for production; prefer the class-specific seeder above.

After Way 2, login, customers, and reports should look the same as before the deploy. New API routes from `main` become available once `route:cache` finishes.

**Docker note:** `scripts/00-laravel-deploy.sh` already uses `migrate --force` (Way 2 style) plus a full `db:seed --force`. That seed is safe on a populated DB because `DatabaseSeeder` returns early when the admin user exists. It is **not** a wipe. To match this README exactly on a VM, run the Way 2 block instead of `migrate:fresh`.

### Tools in this repo

| Tool | File / role |
|---|---|
| **Dockerfile** | Production image: Composer vendor build + Apache + `pdo_pgsql` |
| **scripts/00-laravel-deploy.sh** | Container entry: write `.env`, migrate, then Apache |
| **scripts/write-env.php** | Turns `DATABASE_URL` into Laravel `DB_*` vars |
| **render.yaml** | Render Blueprint: free web + free Postgres |
| **Neon** | External Postgres (no card). Use the **direct** URI, not the pooler |
| **Northflank Sandbox** | Current free Docker host used for this project |

### Environment variables (all hosts)

Set these as **runtime** variables (not only build args). After changing them, **redeploy**.

| Variable | Example / notes |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | Output of `php artisan key:generate --show` |
| `APP_URL` | Public HTTPS URL of the service (no trailing slash) |
| `LOG_CHANNEL` | `stderr` |
| `DB_CONNECTION` | `pgsql` |
| `DATABASE_URL` | Neon **direct** URI: `postgresql://USER:PASS@HOST/neondb?sslmode=require` |
| `DB_URL` | Same as `DATABASE_URL` |
| `SESSION_DRIVER` | `database` |
| `CACHE_STORE` | `database` |
| `QUEUE_CONNECTION` | `sync` |

Do **not** use Neon’s hostname that contains `-pooler`. Do **not** add `channel_binding=require` unless you know the driver supports it.

Generate `APP_KEY` locally:

```bash
php artisan key:generate --show
```

---

### A. Northflank Sandbox + Neon (current free path)

Koyeb’s free/starter plan is closed for new accounts (Mistral acquisition). Northflank Sandbox includes always-on services (no 15-minute sleep like Render free web).

#### 1. Neon (database)

1. Create a project at [neon.tech](https://neon.tech) (free, no card).
2. Copy the **direct** connection string (host like `ep-….aws.neon.tech`, **not** `ep-…-pooler`).
3. Database name is usually `neondb`. SSL is required.

#### 2. GitHub

Push `main` to [islam-kamal/Shatbha-backend](https://github.com/islam-kamal/Shatbha-backend). Northflank builds from this repo.

#### 3. Northflank service

1. Sign up at [app.northflank.com](https://app.northflank.com) → **Sandbox**.
2. Connect GitHub and create a **project**.
3. **Create service** → **Combined** (build + run) → this repo, branch `main`.
4. Build: **Dockerfile**, path `./Dockerfile`, context `.`.
5. **Networking**
   - Public HTTP on port **80** (Apache). The public hostname looks like `p02--<service>--<id>.code.run`.
   - If the UI also exposes port **9000** (PHP-FPM), leave it **not public**. Opening 9000 as HTTP gives connection errors.
6. **Environment → Runtime variables** (this is required; build-time env is not enough):

   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_KEY=base64:YOUR_KEY
   APP_URL=https://p02--YOUR-SERVICE--XXXX.code.run
   LOG_CHANNEL=stderr
   DB_CONNECTION=pgsql
   DATABASE_URL=postgresql://USER:PASS@HOST/neondb?sslmode=require
   DB_URL=postgresql://USER:PASS@HOST/neondb?sslmode=require
   SESSION_DRIVER=database
   CACHE_STORE=database
   QUEUE_CONNECTION=sync
   ```

   Set `APP_URL` after the first deploy once you know the `*.code.run` URL, then redeploy.
7. Deploy. In **Runtime logs** you should see something like:
   - `Runtime env keys: APP_KEY APP_URL DATABASE_URL`
   - `Neon connected (neondb)`
   - migrate/seed output, then Apache.

First boot against an **empty** Neon DB is effectively Way 1 (demo seed). Later pushes/redeploys against the **same** Neon URL are Way 2 (schema only; data stays). Recreating the Neon project is Way 1 again (all previous data is gone).

#### 4. Verify

```bash
# Health (no database required for this page)
curl -sI https://YOUR-SERVICE.code.run/up

# Database
curl -s https://YOUR-SERVICE.code.run/api/v1/db-status

# Login
curl -s -X POST https://YOUR-SERVICE.code.run/api/v1/login \
  -H 'Accept: application/json' \
  -d 'email=admin@shatbha.test&password=password'
```

Neon may sleep when idle; the first request after that can take ~30 seconds (the boot script retries).

Flutter:

```bash
--dart-define=API_BASE_URL=https://YOUR-SERVICE.code.run
```

---

### B. Render (Blueprint)

File: `render.yaml`. Dashboard: **New → Blueprint** → connect this Git repo.

- **Web**: Docker, plan `free`, health check `/up`.
- **Postgres**: plan `free` (expires after 30 days on free; web spins down after ~15 minutes idle).

`DATABASE_URL` / `DB_URL` are wired from the Render database. Set `APP_KEY` in the dashboard (`sync: false` in the blueprint). After the first deploy, set `APP_URL` to `https://<service>.onrender.com`.

**Workspace note:** If a Render workspace had a payment method removed, **paid** services can stay locked until a date Render shows in the UI (this project previously hit a lock until **1 Sep 2026**). The blueprint is already on **free** plans. After the lock lifts, Blueprint deploy should work without a card for those free resources.

---

### C. Other options

| Host | Notes |
|---|---|
| **Koyeb** | Free/Starter not available for new accounts. |
| **ClawCloud Run** | [run.claw.cloud](https://run.claw.cloud) — Docker, GitHub-linked credits (~$5). Same env vars as above. |
| **Fly.io / Railway** | Docker + Postgres; usually need a card. Same `Dockerfile` and env list. |

---

### Deploy checklist

1. Choose **Way 1** (wipe) or **Way 2** (keep data) before running Artisan.
2. `git push origin main` (PaaS) or `git pull origin main` (VM) so the host has the latest code.
3. Runtime env includes `APP_KEY` and a **complete** `DATABASE_URL`.
4. Public port is **80**, not 9000.
5. `GET /up` returns 200.
6. `GET /api/v1/db-status` shows `"ok": true` and driver `pgsql`.
7. Login returns a `token`, not `"Database is not ready"`.
8. After Way 2, existing customers/journals are still there. After Way 1, only seeded demo data remains.
9. Flutter `API_BASE_URL` matches `APP_URL` (https, no trailing slash).
10. If you cached config, rebuild caches after changing `.env`: `php artisan config:cache`.

---

## Troubleshooting

| Symptom | Likely cause | What to do |
|---|---|---|
| `/up` works, login says **Database is not ready** | Runtime env missing `DATABASE_URL`; Laravel fell back to SQLite | Put `DATABASE_URL` under **Run → Environment → Runtime**, redeploy. Logs should list `DATABASE_URL` in “Runtime env keys”. |
| Logs: `No application encryption key` | Missing `APP_KEY` | Add runtime `APP_KEY`, redeploy. |
| Logs: `Neon is not connected` / incomplete URL | Empty or truncated `DATABASE_URL` | Paste the full URI; no spaces; password special chars URL-encoded. |
| Connection refused on public URL | Wrong port (9000) or process never bound 80 | Public port **80** only. Confirm boot script finished and Apache started. |
| Build fails: PHP version / composer | Image older than 8.4 | Use the repo `Dockerfile` (`php:8.4-apache-bookworm`). |
| SSL / Neon errors | Pooler host or missing `sslmode=require` | Direct host; `?sslmode=require`. Boot script strips `-pooler`. |
| First request ~30s then OK | Neon cold start | Expected on free Neon. |
| Clerk login works, P&L is 403 | By design | Use `admin@shatbha.test` for income statement. |
| Payment 422 Arabic message | Amount > remaining | Reduce `amount`. |
| Customers/journals gone after deploy | Way 1 (`migrate:fresh`) or a new empty Neon DB | Restore from backup if you have one. Later updates must use [Way 2](#way-2--update-in-place-keeps-all-data). |
| Config/routes look stale after pull | Old `config:cache` / `route:cache` | Re-run the three cache commands from Way 2. |

---

## License

Private project unless otherwise stated in the repository settings.
