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
submits and the face photo captured for an ID are written to the named **`documents`** disk and
read back only through routes that `authorize()` the owning record first
(`admission.documents.show`, `id.photo.view`). The `local` disk is rooted at
`storage/app/private` — the same folder the `documents` disk writes to — and it is reachable
at `/storage/{path}` for anyone holding a signature; see the two routes below. Setting
`FILESYSTEM_DISK=public` and
running `php artisan storage:link` would publish that whole folder under
`/storage/...`, where the framework serves any file whose name is known with no
authorization at all — the hashed filename is not a secret, it is only unguessable. If
a deployment ever needs public file serving for some *other* feature, give that feature
its own disk; do not move the default. Nothing in `storage/app` is committed: the
folder's own `.gitignore` keeps a machine full of real signed forms from shipping its
papers by accident, and the reference photographs of the physical forms are ignored at
the repository root for the same reason.

**Two routes answer with no login, and one secret stands in front of them.** Because
`config/filesystems.php` gives the `local` disk `'serve' => true`, Laravel registers
`GET|HEAD /storage/{path}` and `PUT /storage/{path}` for it **outside every middleware group** —
not `auth`, not `web`, no CSRF token. Nothing in the application ever asks for a signature
(`temporarySignedRoute` and `temporaryUrl` appear nowhere in `app/` or `resources/`), so in
normal use the two are dead doors, and what stops a stranger walking through one is
`ServeFile::hasValidSignature()` / `ReceiveFile::hasValidSignature()` — an HMAC of the path and
query, keyed by `APP_KEY`. Measured 2026-10-12 by driving the route through the HTTP kernel
(`storage/app/scratch/audit-24b-storage-routes.php`, pinned by `ServedUploadPathTest`): unsigned
→ 403; signed with this install's key → 204; signed with the **empty** secret, on an install
that never ran `key:generate` → **204, and the file is written**. Two faults follow, and
`php artisan ems:preflight` now fails the run for each:

- **`APP_KEY` left blank.** That is the state `.env.example` leaves it in, and §3 corrects it with
  one line. The damage reads as something else: every login throws on cookie encryption, so a
  keyless server looks like a broken session while `/storage/{path}` — which needs no encryption
  at all — keeps accepting uploads from anybody who can compute an HMAC over an empty key.
- **`'visibility' => 'public'` on the served disk.** That single line switches
  `ServeFile`'s signature check off: the unsigned `GET` this configuration refuses with 403
  then walks straight through and only a missing file answers 404 (measured the same way by
  `audit-24c-servegate.php`). An operator adding it to chase a broken image would publish every
  applicant paper and face photo, because the served `local` disk and the `documents` disk are
  rooted at the same folder.

To see what has accumulated there, run `php artisan ems:uploads:prune`. It lists the
files on the private disk that no record points at — superseded face captures, residue
from an older install — and deletes nothing unless given `--delete`. It looks only inside
`id-photos/` and `admission-documents/`, never at the disk's root.

The production-debug guard lives in `app/Providers/AppServiceProvider.php`:
booting with `APP_ENV=production` and `APP_DEBUG=true` throws a
`RuntimeException`, so a forgotten override can never ship stack traces.

Force HTTPS in the entry point (reverse proxy `X-Forwarded-Proto`, or
`URL::forceScheme('https')` in `AppServiceProvider::boot` when
`app()->isProduction()`).

**One caveat measured 2026-10-17, and pinned by `tests/Feature/SecurityHeadersTest.php`:** this build does
not trust any proxy — `bootstrap/app.php` never calls `trustProxies()` and `config('trustedproxy.proxies')`
is unset, so `Illuminate\Http\Middleware\TrustProxies` holds `$proxies = null` and Symfony discards
`X-Forwarded-Proto`. A forwarded header therefore changes nothing on its own: `app('request')->isSecure()`
stays false, and `SecurityHeaders` skips `Strict-Transport-Security` on every response even though browsers
see HTTPS. If TLS terminates at a proxy, either terminate it in the app or name that proxy's address in
`trustProxies(at: …)`. Do **not** answer this with `at: '*'` — that lets any client forge its own scheme and
source address, and things keyed on the address follow it: the login throttle builds its key from
`$this->ip()` (`app/Http/Requests/Auth/LoginRequest.php:97`) and every audit row stores `Request::ip()`
(`app/Observers/AuditLogObserver.php:102`) into `auditlogs.ipAddress`.

**Time zone: two processes write into these tables.** `config/app.php` resolves
`'timezone' => env('APP_TIMEZONE', 'UTC')` and nothing in `.env` or `.env.example` sets that variable, so the
application runs in UTC while the school keeps Manila time (UTC+8) — a record made before 08:00 local is
stored with the previous day and every printed timestamp runs eight hours behind the wall clock in the room.
Whether to set `APP_TIMEZONE=Asia/Manila` is a decision to take deliberately, not one to inherit, and the
owner has declined it twice (most recently 2026-10-14, with the state above recorded as a known condition
rather than a defect). Until 2026-10-14 the variable did nothing at all: the config line was the literal
`'UTC'`, so this paragraph's own instruction could not be followed — the same species of gap as the two §2
claims that turned out to be false. The second half is not a decision: measured on the development box on
2026-10-12, PHP stamped `2026-10-13 05:32` while MySQL's `NOW()` answered `2026-10-12 18:32` for the same
instant. The two epochs agree to the second — the clocks are fine — but the database session runs in another
zone, and `auditlogs.createdAt` and `enrollmentstatushistory.changedAt` carried a MySQL `CURRENT_TIMESTAMP`
default into plain DATETIME columns that store no zone. Whichever process stamps the row decides the hours a
desk reads.

**Ruled 2026-10-13: match the database to the app, and take the defaults away.** The repository
half is in the repository: `auditlogs.createdAt` and `enrollmentstatushistory.changedAt` were DATETIME
columns with a `CURRENT_TIMESTAMP` default. Three of the four writers already passed `now()` —
`AuditLogObserver` and `EnrollmentStateMachine` both did — while the two `Auditlogs::create` calls in
`UserManagementController`, which write the `assigned` row when someone's roles change, did not: they
were the one path where MySQL stamped a record with its own clock. The models fill the column themselves now
(`Auditlogs::booted()` and `Enrollmentstatushistory::booted()`), and
`2026_10_13_1000000001_drop_database_stamped_audit_defaults.php` removes the two defaults, so a row
written without a timestamp fails loudly instead of silently landing eleven hours from its neighbours.
The `useCurrent()` on `jobs.failed_at` stays — that column belongs to the framework's own queue schema,
which Laravel writes.

**The server half is a setting, not a migration.** Tell MySQL to answer in the app's zone:
`default-time-zone='+00:00'` in the server's own config (Laragon: `my.ini`; production: `my.cnf`) and
restart it, or set `time_zone` per connection. Until that is done,
`php artisan ems:preflight` fails the run and prints both strings — on the development box `NOW()`
answered `2026-10-13 03:52` where PHP stamped `2026-10-13 14:52`, the epochs agreeing to the second
while `session` and `global` resolve to `SYSTEM` and `system_time_zone` reads UTC-11. Nothing in the
schema now offers the database the chance to answer, which is why the two zones being apart is a report
to clear rather than a corruption in progress.

**State on the development box: in force and verified.** The line sits in
`C:\laragon\bin\mysql\mysql-8.4.3-winx64\my.ini` under `[mysqld]`, and MySQL has been restarted by the
owner — the agent editing this repository does not stop or start the database service, which is why the
restart is a human step and not something that "just happens" when the file changes. Verified against the
server itself rather than assumed: `@@global.time_zone` is `+00:00`, `@@system_time_zone` still reads
`UTC-11` (the Windows zone, which the setting deliberately overrides), and `NOW()` and `UTC_TIMESTAMP()`
return the same string. `php artisan ems:preflight` reports `OK Clocks agree — PHP and mysql write the same
stamp`. Before the restart, the same command had reported PHP writing `2026-10-14 08:53:11` against the
database's `2026-10-13 21:53:11`, eleven hours apart, so the row is what distinguishes a config file that
*contains* a setting from a server that is *running* with it.

**Ruled 2026-10-14, in addition: the application forces its own connections.** `config/database.php` sets
`PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'"` from `DB_SESSION_TIME_ZONE`, so every connection
Laravel opens states the zone instead of inheriting the operating system's — which matters because the school's
server may never get a `my.cnf` edit, and a DATETIME stores no zone to fall back on. Proven rather than
assumed: with `DB_SESSION_TIME_ZONE='+07:00'` the app's own session moved to `+07:00` while
`@@global.time_zone` stayed `+00:00`, and `NOW()` moved with it. Three consequences to keep in view:

- **This covers the application's clients only.** A `mysqldump`, a cron job, phpMyAdmin or the next
  developer's `tinker` does not carry the init command and still stamps the server's own hour into the same
  column. The `my.cnf` line below is therefore not made redundant by this — it is the fix, and forcing is the
  safety net.
- **`ems:preflight`'s "Clocks agree" would now pass on an unconfigured server**, because the app agrees with
  itself. So the row carries a note when the session and the server default differ, naming both and the
  clients left outside it; a check that can only be red or silent is a check an operator learns to skim.
- **`DB_SESSION_TIME_ZONE=` (empty) turns the forcing off**, and it does so by removing the statement rather
  than sending an empty zone. The second half is not pedantry: `env()` answers `''` for an empty variable, and
  MySQL rejects `SET time_zone = ''` — so the obvious way to write this switch would have failed *every*
  connection in the application instead of merely losing the setting. Both sides are pinned by
  `tests/Feature/Console/PreflightTest.php`, and the empty-value case was proved by removing the guard and
  watching that test go red.

If `APP_TIMEZONE` is ever set, `DB_SESSION_TIME_ZONE` has to move with it, and `preflight` is what catches
anyone who sets one and not the other.

**The server line, and where it applies.** The same line belongs in the `my.cnf` of whichever machine the
campus install lands on — `default-time-zone='+00:00'` under `[mysqld]` — followed by a restart, or
`SET GLOBAL time_zone='+00:00'` as a stopgap that dies on the next start. That machine is not provisioned
yet: this repository holds no host, credential, compose file or deploy script for it, and the build plan
still lists the on-campus server as an unchecked box. So this is a step for whoever installs the system, not
open work on the code — and it is written here rather than tracked as a task, because a task for a machine
that does not exist reads as forgotten work to every reader after the first.

On a server where the line has not been applied, the `Clocks agree` row there still reads OK *with* the note
above — the application agrees with itself, since it forces its own session zone. The note is the thing to
act on, not the colour of the line.

## 3. Install & migrate

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force     # roles, permissions, settings, starter reference data, notifications
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan ems:preflight
```

`ems:preflight` turns this document into a command: it checks the environment pair, the
database connection, unapplied migrations, an unseeded RBAC, the privacy of the `documents`
disk, whether that served folder keeps its signature, the application key, the folders Laravel
must write into, a browser for PDFs, and the queue connection — and
for each failure it names the fix rather than the symptom. It writes nothing and changes
nothing, so it is safe to run before §4 and again after any change below. A non-zero exit code
means at least one desk would break on this server.

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

The letterhead does not travel over HTTP. Every print template embeds it as a data URI
(`App\Support\PrintAssets::logoDataUri()` reads `public/images/logos/seait-logo.png` from disk,
measured 2026-10-13: `asset(` appears 0 times in the six files under `resources/views/prints/`),
so the seal needs no `APP_URL` reachable from the printing process — that inlining is what stops
a single-threaded server deadlocking on the render, and it is why a PDF is correct with the
network down. `APP_URL` still matters to the desks: it is the base of every link and asset the
browser loads.

Verify with the print-fidelity smoke test:
`php artisan ems:print-fidelity` (renders sample PDFs from real DB data into
`storage/app/prints/fidelity/`; expect `8/8 rendered`).

## 7. CI/CD (audit §3.1, §3.2)

`.github/workflows/ci.yml` runs on every push/PR:
- **Pest + PHPStan + Pint** against SQLite (fast lane)
- **Pest against MySQL 8** service container (catches MySQL/SQLite drift —
  the class of bug behind the `add_auto_increment` migration), followed by the **demo pipeline** and its own
  **end-to-end walkthrough** step against the same container
- **ESLint + Vite build**
- each backend job ends with `php artisan ems:ci:no-silent-skips`, which
  fails the run when a test skipped for a reason this project has not agreed to

**How the walkthrough gets MySQL.** Its legs read reference rows that migrations never create — courses, subjects,
rooms, terms, the current curriculum, a religion and the seeded roles — so they need a *seeded* database, and they
cannot be seeded before the main suite because `RefreshDatabase` migrates fresh. The MySQL job therefore runs the
suite first, then `migrate --force && db:seed && db:seed --class=DevReferenceDataSeeder`, then
`php tools/seed_full_demo_pipeline.php` (every desk queue, and a step that fails on any finding the tool's own
report lists), then `vendor/bin/phpunit
tests/Feature/E2E --fail-on-skipped`. A leg names its database through `E2E_DB_DATABASE`; with nothing set it uses the
developer's live `ems`, which is how walkthrough §2.6 runs it locally. `--fail-on-skipped` is what makes that step
honest — four green skips would otherwise read as four passes, and measured on 2026-10-12 an unseeded target
produced exactly that until the flag was added. Host, port, user and password are read from the ordinary `DB_*`
configuration and deliberately not pinned in the test: the repository's own defaults are `root` with no password while
the CI container runs `root`/`root`, and the first version hardcoded the former, so every leg skipped with `Access
denied` and the step exited 1. A skip now names the host, port and user it tried, so a credential mismatch reads as a
fact rather than a mystery.

**Seeding does not pin an id.** `StarterReferenceDataSeeder` keys its lists by *name*, so a reference row's id
is whatever the table's `AUTO_INCREMENT` counter hands out — and a rollback does not rewind that counter.
Measured on 2026-10-12 against a rehearsal database driven in CI's own order: after the suite the `religions`
table was empty with its counter at 53, so the seeders wrote ids 53..60 and the walkthrough's `religionId => 1`
died on `students_religionid_foreign` — four errors, exit 2 — while the SQLite and Frontend jobs stayed
green. The two legs that insert the student through the model named the constraint; the two that post to the
intake route were refused by its `exists:religions,religionId` rule and printed Laravel's response-context
helper instead (`Call to a member function all() on array`), so one cause read two different ways in the
same log. The class now runs with exception handling off, which makes the intake legs say
`The selected religion id is invalid.` — the sentence the app had been trying to print.
The walkthrough now reads a religion out of the table instead of assuming one, and `religions` joined its guard
list so an install without it skips with the table named. Courses and terms are the exception and may stay
literals: `DevReferenceDataSeeder` pins those ids (`['courseId' => $id]`, `['termId' => $id]`), so an explicit
insert lands where the code says regardless of the counter.

**The same trap sat in the demo seeder, and it was quieter.** `tools/seed_full_demo_pipeline.php` wrote
`feeTypeId => 1..4`, `subjectId => 5` ("IT101") and `roomId => 1`, plus a hand-kept office to user map,
on a machine where those numbers happen to be right. On a freshly migrated and seeded install fee type 1
is the **Clearance Slip Replacement**, so every demo charge attaches to the wrong paper and the foreign
key accepts it, and subject 5 is RH1 rather than IT101 — a wrong row that lands quietly. Where the reference
rows had been rebuilt a second time those numbers are absent altogether and the run aborts on the schedule
insert. Both are fixed by
resolving names — `subjectCode`, `roomName`, `feeName`, and the seeded `officeN_head` accounts — which
changes nothing on the developer's database: all fifteen lookups were checked to return the ids the
literals used. **Closed on 2026-10-16.** The tool no longer assumes its named demo students exist: it creates
`DEMO-2026-001` with the enrollment today's receipt is filed against and `DEMO-2026-004` with the completed
prior-term load her standing evidence is graded into, so an empty schema comes out of one run holding the whole
demo — and it exits 1 whenever its own desk report lists a finding, instead of printing the success banner over
it and returning 0 (proved against a planted overpayment: exit 1, banner withheld, and 0 again once the receipt
was restored). What that makes testable is `tests/Feature/E2E/DemoDatasetRecordStatesTest`, which reads
`ems:audit:record-states` line by line against that dataset.

**And the step earned its keep on the first run.** CI's Pest step runs before the seeders, so every insert a
test rolled back had still spent its `AUTO_INCREMENT` value — and two counter assumptions that a locally built
schema could not see died on it. `charges` was still being written from the closure default
`[1 => 11250, 2 => 1500, 3 => 4500, 4 => 250]`, which is not fee type 1 on a spent schema, so the run aborted on
`charges_feetypeid_foreign`; that map is now `demo_fee_lines()`, keyed by the four `feeName` lookups. And three
clearance-approval sites fell back to a literal Admin user id for the two checklist lines whose office was
missing from the desk map — the map covered seven offices while `ProvisionalClearanceRequirements::NAMES` holds
nine — so Admission and Academic lines were signed by whichever account happened to be `userId` 1. The map now
covers all nine, every signer resolves through `desk_signer_for()`, and the desk report refuses an approval or a
box signed across offices by asking `WorkflowService::maySignAcrossOffices()` — the same predicate the write
path uses, so SysAdmin's legitimate cross-office signature is not reported as a defect (both edges proved:
an office head's cross-office signature exits 1, staff8's does not). The rehearsal that finds this class of
thing is CI's own order against a MySQL schema — `migrate`, the seeders, the pipeline, then
`vendor/bin/phpunit tests/Feature/E2E --fail-on-skipped` — measured green locally that way before the push.
`tools/` itself is still outside every static gate — PHPStan analyses `app`, and `pint.json` excludes `tools`. `tools/` itself is still outside every gate —
PHPStan analyses `app`, and `pint.json` excludes `tools` — so the file is proven by running it and reading what
it prints, which is now also what CI does.

**What CI still does not prove.** `ems:print-fidelity` is not a CI step, so no PDF is rendered and no page
layout verified there — compare printed papers against the physical forms on the machine that will produce
them. And an agreed skip is still a skip: the allowlist in `AssertNoSilentSkips.php` is deliberately short
(the walkthrough's legs and the demo-dataset check, both needing the seeded database only the later steps have,
and one Preflight row that needs a gitignored folder),
so read it before concluding a green job covered everything.

**The runner image is pinned to `ubuntu-24.04`, not floated.** `ubuntu-latest` was scheduled to move to
Ubuntu 26 on 2026-10-19, and one check depends on what the image ships: `PrintRendererTest` locates a
browser from the runner and **skips rather than fails** when it finds none — so a leaner image would have
made CI green while silently subtracting proof. The skip guard above turns that silence red; the pin buys
the decision about when to move. When you do move it, expect the guard to say what the new image lacks.

Do not merge with red CI.

## 8. Post-deploy smoke test

1. `php artisan ems:preflight` exits 0 — including `Clocks agree`, the check that a server does
not stamp the same moment two different ways, and `Application key` and `Served folder`, the two
rows that check the routes nobody logs in to use (§2). `Clocks agree` is the one that reports whether
the §2 ruling is actually in force on this machine: it prints the string PHP writes and the string the
database writes for the same instant. Read the *note*, not only the colour — since 2026-10-14 the
application forces its own session zone, so a server nobody configured still comes up OK, and the line
then says `this connection's session is X while the server defaults to Y`. That sentence is the one to
act on: it means `mysqldump`, cron and phpMyAdmin are stamping Y. A plain FAIL means `APP_TIMEZONE` and
`DB_SESSION_TIME_ZONE` were set apart from each other.
2. `/up` returns 200 (health route).
3. `/register` returns 404 (self-registration disabled).
4. Login as the SysAdmin from §4 works over HTTPS; session cookie has `Secure`.
5. Admin → User Management creates a second account (proves RBAC + hashing).
6. Download one paper from each desk and confirm it arrives as a PDF. Printing is **not** queued
   work (§5/§6): Browsershot runs inside the request, so this step proves the browser binary, not
   the worker. Then confirm the notification bell of the desk that must act next shows the row the
   action just wrote — on this machine (`sync`) that happens in the same request; on production it
   is the worker's job, so check `jobs` is empty and the row exists.
7. `php artisan ems:audit:record-states` exits 0. This is the command the audit should have
   had from the start: every impossible record this project found by hand — a validated ID
   request with no face photo, a workflow box completed with no officer on it, a request still
   open under a box its own office had already signed, a notice addressed to a class that does
   not exist — is one of its twelve rules. A non-zero count means a write path regressed or a row
   was edited behind a desk's back, so fix the path or restore from a dump rather than editing
   the status column. Two of those twelve are different in kind and only ever `note`, because each counts
   a state an office has ruled on rather than a defect: a Clinic box signed with no health assessment
   behind it, and an ID box signed with no validated request behind it — the second one is enrollment 53,
   whose contradictory request row was deleted 2026-10-11 while its signature was deliberately left
   standing. A report tool must not decide those over the office's hand, so it counts them and stays
   green. The other ten fail the run, and the third of the once-noted rules joined them on 2026-10-15:
   printed papers attributable to nobody (docx §25 P-14) had been kept as a note only because 21 legacy
   rows sat in that state and pruning or attributing them was somebody's call. That call is made — the
   rows are behind a manifest — and no paper this application prints can leave all three keys NULL, so a
   key-less row now names a write path. On a server that has only been migrated and RBAC-seeded — no demo
   pipeline — every rule reads 0. On the demo database the three read 21, 7 and 8 on 2026-10-12; on live
   `ems` they read 0, 0 and 1 on 2026-10-15: the 21 key-less print rows were pruned behind a manifest, the
   7 past-term records whose Clinic box the pipeline had signed before it was made to write the assessment
   were retired through the Registrar drop — and the seeder was changed the same day, because that drop
   only freed the seat its G-2 step checks, and the next run re-created five of the seven within the hour.
   It now writes the assessment and the validated request behind every box it signs, so these two counts
   stay where they are; a retired enrollment is excluded from the rules either way, since a number nobody
   will act on teaches the operator to skim it. What remains is enrollment 53: the ID box whose
   contradictory request row was deleted 2026-10-11 while its signature was deliberately left standing.
8. `php artisan ems:uploads:prune` reports what the private disk holds that no record points at,
   and deletes nothing until told `--delete` (§2).

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
