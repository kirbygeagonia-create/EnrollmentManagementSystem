# Audit Remediation — 2026-08-29

Full-code audit (SEAIT-EMS-Audit-Report.md) verified and remediated. All 21
findings confirmed valid; fixes below. Gates at time of commit: Pest 216/216,
PHPStan 0 errors, Pint clean, ESLint 0/0, Vite build OK, `npm audit` 0
vulnerabilities.

## Critical

- **§2.1 Open self-registration** — `GET/POST /register` removed
  (`routes/auth.php`); `RegisteredUserController`, `Register.jsx`,
  `User` model, `UserFactory` deleted. `RegistrationTest` now asserts the
  route is dead and no account can be created. Staff accounts are created
  exclusively via Admin → User Management (`user.create`).
- **§2.2 Unguarded StudentController** — new `StudentPolicy` (viewAny/view/
  quickSearch) behind a new seeded `students.view` permission; granted to all
  functional desk roles + OfficeHead/Dean/ProgramHead; deliberately NOT to
  bare `Staff` or `Instructor`. `HandleInertiaRequests` shares
  `can.studentsView`; `GlobalSearchModal` and `MegaAppLauncher` gate the UI.
  `PermissionMatrixTest` now covers `students.index` (Staff → 403).
- **§2.3 `APP_DEBUG=true` shipping default** — production-boot guard added in
  `AppServiceProvider` (refuses to run with debug on in production);
  production overrides documented in `.env.example` + `DEPLOYMENT.md`.

## High

- **§3.1 CI/CD** — `.github/workflows/ci.yml`: Pest + PHPStan + Pint (SQLite),
  Pest on MySQL 8 service, ESLint + Vite build, on every push/PR.
- **§3.2 SQLite/MySQL test drift** — MySQL job added to CI (above). Local
  drift already fixed: the org-realignment migration now no-ops on non-MySQL
  drivers and the base `staffusers` migration ships the realigned schema
  (nullable officeId, role enum incl. `instructor`). The SQLite suite —
  broken since that migration landed — is green again (216/216).
- **§3.3 Admin bootstrap** — `php artisan ems:create-admin` command
  (`app/Console/Commands/CreateAdminUser.php`); documented in DEPLOYMENT.md.
- **§3.4 Upload validation** — `submitRequirement` now enforces
  `mimes:pdf,jpg,jpeg,png,doc,docx` (mirrors the frontend allow-list).
- **§3.5 PDF pipeline** — Puppeteer/Chromium provisioning documented in
  DEPLOYMENT.md §6 (code-side: nothing to vendor into the repo).
- **§3.6 Session cookies** — `SESSION_SECURE_COOKIE` added to `.env.example`
  with production guidance; HSTS emitted by new SecurityHeaders middleware.

## Medium

- **§4.1 Magic office IDs** — new `App\Enums\OfficeId` backed enum; all bare
  `officeId !==/=== N` comparisons replaced across 8 policies,
  `AuthServiceProvider`, and `RbacSeeder` match arms.
- **§4.2 Security headers** — new `SecurityHeaders` middleware (nosniff,
  frame-deny, referrer-policy, permissions-policy, HSTS when HTTPS),
  registered globally in `bootstrap/app.php`.
- **§4.3 TypeScript** — incremental adoption plan documented (DEPLOYMENT.md);
  bulk rewrite intentionally not attempted in this pass.
- **§4.4 Dead `User` model** — deleted with its factory; default `users` table
  removed from the base migration (password_reset_tokens + sessions kept).
- **§4.5 `$request->all()`** — `storeCourse` now creates from the validated
  array only.
- **§4.6 nanoid advisory** — `npm audit fix` applied; `npm audit` clean.
- **§4.7 Queue worker** — Supervisor/systemd provisioning documented in
  DEPLOYMENT.md §5.

## Low

- **Timestamps** — migration adds nullable `created_at/updated_at` to
  students, admissions, enrollments, payments, studentassessments,
  studentclearances, clinicrecords, idrequests; `$timestamps` enabled on
  those models only.
- **Password policy** — staff creation enforces
  `Password::min(8)->mixedCase()->numbers()->symbols()`; the same policy is
  used by `ems:create-admin`.
- **ENUM rigidity** — kept by design (rationale in DEPLOYMENT.md).
- **Doc drift** — audit-log decision note added to DEPLOYMENT.md.
- **Unpinned dev deps** — larastan `^3.10`, pint `^1.30`, pest `^4.7`,
  pest-plugin-laravel `^4.1`.

## Extra fixes found while executing (pre-existing, verified at HEAD)

- `blocking.assignStudents` gate let an `OfficeHead` from *any* office
  through, which then died with a 500 inside `WorkflowService`'s office-scope
  check. Gate + `BlockingPolicy::assignStudents` now require the Blocking
  office (5) for non-sysadmin users → clean 403. (`unauthorized office
  cannot assign` test was already failing at HEAD.)
- 4 stale PHPStan findings (nullsafe/coalesce on non-nullable enum access) in
  `StudentController`, `Staffusers`, `WorkflowService` — cleaned up.
