# SEAIT EMS — Deployment Guide

This guide closes the deployment gaps identified in the 2026-08 full code audit
(§2.3 debug mode, §3.3 admin bootstrap, §3.5 PDF pipeline, §3.6 session cookies,
§4.7 queue worker). Read it top to bottom on a fresh deployment.

---

## 1. Requirements

- PHP 8.3+ (extensions: `pdo_mysql`, `mbstring`, `dom`, `gd`, `intl`, `zip`)
- Composer 2
- Node.js 22+ and npm
- MySQL 8
- A process manager (Supervisor / systemd) for the queue worker
- Headless Chromium for PDF printing (see §6)

## 2. Environment configuration

Copy `.env.example` to `.env`, then apply the **mandatory production overrides**:

```env
APP_ENV=production
APP_DEBUG=false                # the app REFUSES TO BOOT in production if true
APP_URL=https://your-domain.example
SESSION_SECURE_COOKIE=true     # HTTPS-only session cookies (audit §3.6)
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ems
DB_USERNAME=ems_user
DB_PASSWORD=<strong password>

QUEUE_CONNECTION=database      # requires the worker in §5
CACHE_STORE=database
```

The production-debug guard lives in `app/Providers/AppServiceProvider.php`:
booting with `APP_ENV=production` and `APP_DEBUG=true` throws a
`RuntimeException`, so a forgotten override can never ship stack traces.

Force HTTPS in the entry point (reverse proxy `X-Forwarded-Proto`, or
`URL::forceScheme('https')` in `AppServiceProvider::boot` when
`app()->isProduction()`).

## 3. Install & migrate

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force     # roles, permissions, notifications
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

## 4. Bootstrap the first SysAdmin (audit §3.3)

`RbacSeeder` creates roles/permissions only — no accounts. Create the initial
administrator once, immediately after seeding:

```bash
php artisan ems:create-admin
```

It prompts for name/username/email/password (password policy: min 8 chars,
mixed case, numbers, symbols), assigns the `SysAdmin` role, and warns if a
SysAdmin already exists. Log in at `/login` and change the password from
Profile → Update Password. **All subsequent staff accounts are created through
Admin → User Management** (public `/register` is intentionally disabled —
audit §2.1).

## 5. Queue worker (audit §4.7)

`QUEUE_CONNECTION=database` means PDF/print jobs and other queued work only
execute when a persistent worker runs. Under Supervisor:

```ini
[program:ems-queue]
command=php /path/to/artisan queue:work --tries=3 --timeout=0 --max-time=3600
autostart=true
autorestart=true
user=www-data
stopwaitsecs=3600
```

or systemd: `php artisan queue:work` with `Restart=always`.

## 6. PDF printing — Puppeteer/Chromium (audit §3.5)

`app/Services/PrintService.php` uses `spatie/browsershot`. Puppeteer is
deliberately **not** in `package.json` (Chromium is a heavy dev dependency);
production must provide it:

```bash
npm install puppeteer          # downloads a bundled Chromium
```

or point Browsershot at a system Chromium:
`Browsershot::html(...)->setChromePath('/usr/bin/chromium-browser')`.

Verify with the print-fidelity smoke test:
`php artisan ems:print-fidelity` (renders sample PDFs from real DB data into
`storage/app/prints/fidelity/`).

## 7. CI/CD (audit §3.1, §3.2)

`.github/workflows/ci.yml` runs on every push/PR:
- **Pest + PHPStan + Pint** against SQLite (fast lane)
- **Pest against MySQL 8** service container (catches MySQL/SQLite drift —
  the class of bug behind the `add_auto_increment` migration)
- **ESLint + Vite build**

Do not merge with red CI.

## 8. Post-deploy smoke test

1. `/up` returns 200 (health route).
2. `/register` returns 404 (self-registration disabled).
3. Login as the SysAdmin from §4 works over HTTPS; session cookie has `Secure`.
4. Admin → User Management creates a second account (proves RBAC + hashing).
5. Trigger a queued print job and confirm the worker processes it.

---

## Design-decision notes (audit low-priority items)

- **MySQL `ENUM` state columns** (audit: "rigid"): intentionally kept. New
  status values are rare and versioned migrations are the safer transactional
  guarantee for a registrar workflow; PHP backed enums mirror them.
- **Audit-log doc drift**: `Documentation/EMS Complete Documentation.docx`
  claims an audit-log table was "deliberately rejected", but the shipped
  design includes `Auditlogs` (+ `audit.view` permission, observer on all
  models). The documentation pre-dates the decision reversal; treat the code
  as the source of truth.
- **TypeScript adoption (§4.3)**: the frontend is plain JSX across 150+ pages.
  Bulk conversion in one pass is not verifiable by the current gates. Adopt
  incrementally: enable `allowJs` in `tsconfig.json`, convert shared
  components (`resources/js/Components/**`) to `.tsx` first, then page by
  page; Vite handles both transparently.
