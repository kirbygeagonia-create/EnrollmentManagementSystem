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

FILESYSTEM_DISK=local          # never `public` — see below
```

**Uploaded files stay on the private disk.** Requirement documents an applicant
submits and the face photo captured for an ID are written to the **default** disk and
read back only through routes that `authorize()` the owning record first
(`admission.documents.show`, `id.photo.view`). The `local` disk is rooted at
`storage/app/private`, which no web path reaches. Setting `FILESYSTEM_DISK=public` and
running `php artisan storage:link` would publish that whole folder under
`/storage/...`, where the framework serves any file whose name is known with no
authorization at all — the hashed filename is not a secret, it is only unguessable. If
a deployment ever needs public file serving for some *other* feature, give that feature
its own disk; do not move the default. Nothing in `storage/app` is committed: the
folder's own `.gitignore` keeps a machine full of real signed forms from shipping its
papers by accident, and the reference photographs of the physical forms are ignored at
the repository root for the same reason.

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
php artisan db:seed --force     # roles, permissions, settings, starter reference data, notifications
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

§28 **C-10** asked whether a queue worker is part of the installed deployment. Answered
2026-10-06, with two different answers for two machines.

**The demo/UAT machine runs `QUEUE_CONNECTION=sync`.** On `database` with no worker, notices were
never written: 56 jobs sat in `jobs` while the only two `notifications` rows carried the seeder's own
timestamp — so every "the desk that must act next is notified" claim in the documentation and in
`Documentation/Per-Office-UAT-Walkthrough.md` was a claim about a queue nobody was draining. With
`sync`, a notification is written inside the request and the bell shows what a desk just did;
delivery was proved on live `ems` the same day (2 → 5 rows, 0 jobs queued).

**Production keeps `database` and runs a worker.** Do not copy `sync` to the school's server: a
synchronous listener holds the HTTP request through whatever the listener does, and a slow or failing
notification then fails the desk's action with it. What is actually queued is the two event
listeners `SendEnrollmentNotification` and `SendWorkflowNotification` (both `ShouldQueue`, both
registered in `EventServiceProvider`); PDF rendering is a synchronous Browsershot call inside the
print request, not queued work, so §6's browser requirement applies whether or not a worker runs.

Under Supervisor:

```ini
[program:ems-queue]
command=php /path/to/artisan queue:work --tries=3 --timeout=0 --max-time=3600
autostart=true
autorestart=true
user=www-data
stopwaitsecs=3600
```

or systemd: `php artisan queue:work` with `Restart=always`. If a machine is ever left on `database`
without a worker, `php artisan queue:clear database` empties the backlog — it discards jobs, it does
not run them, so prefer starting the worker once the notice content has been checked.

## 6. PDF printing — Puppeteer + a system browser (audit §3.5)

`app/Services/PrintService.php` uses `spatie/browsershot`, whose `bin/browser.cjs`
does `require('puppeteer')`. Without that module every PDF route fails with
`MODULE_NOT_FOUND` (the React print pages still print on screen). `puppeteer` is now
declared in `package.json`, so `npm ci` installs it — and because `.npmrc` sets
`ignore-scripts=true`, Puppeteer's postinstall never runs, so no bundled Chromium is
downloaded. The browser binary comes from the machine instead:

`App\Services\ChromiumLocator` resolves it for both the PDF routes and
`php artisan ems:print-fidelity`, in this order: `EMS_CHROME_PATH` from `.env`, then
Chrome/Edge in the usual Windows machine-wide **and per-user** locations, then
`google-chrome`, `google-chrome-stable`, `/opt/google/chrome/chrome`,
`chromium-browser`, `chromium`, `/snap/bin/chromium` on Linux. Install Chrome or
Edge on every desk that prints, or point `EMS_CHROME_PATH` at the binary.

A configured `EMS_CHROME_PATH` is honored strictly: if that file does not exist the
printer reports no browser rather than picking a different one, because the four papers
are signed off against one specific rendering and another layout engine can reflow them.
If nothing is found at all, `PrintService::generatePdf()` refuses **before** it builds the
document and the desk is sent back to the screen it was on with a message naming both
fixes — the download no longer ends on a 500 page. `ems:print-fidelity` answers the same
question on the command line. Note that the print-log row is written before the render,
because the document number is printed on the page, so a refusal still consumes the
number it issued.

Note that the templates reference the letterhead logo through `asset()`, so an
absolute `APP_URL` must be reachable from the printing process for the seal to appear.

Verify with the print-fidelity smoke test:
`php artisan ems:print-fidelity` (renders sample PDFs from real DB data into
`storage/app/prints/fidelity/`; expect `8/8 rendered`).

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
