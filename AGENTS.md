# Project Instructions — SEAIT Enrollment Management System

## The two rules that outrank everything below

1. **Never commit or push until the user says so explicitly.** This overrides the
   commit-before-dispatch habit described elsewhere in this file. Ask, then act.
2. **`Documentation/EMS Complete Documentation.docx` is the authority.** Owner ruling
   2026-09-29. Where the storyboard, the use-case guide or this file disagree with it,
   the docx wins — and when the code moves, the docx is edited to follow in the same
   batch of work, never left describing an older build.

## Gate requirements before any push

All five must pass, then watch CI to green and read `gh`'s own exit code:

- **Pest** — run `php artisan test` **whole**, never per-directory; per-directory runs hide
  cross-file breakage. The SQLite suite is the default (`phpunit.xml` pins `:memory:`); the E2E
  walkthrough and admin smoke run against the real MySQL `ems` database and skip when it is unreachable.
- **PHPStan** — 0 errors. **Pint** — `--test` passes. **ESLint** — `npx eslint resources/js --max-warnings=0`.
- **Vite build** — succeeds. CI runs three jobs (`.github/workflows/ci.yml`: Pest+PHPStan+Pint on
  SQLite, Pest on MySQL 8, ESLint+build). Do not merge red.
- After any gate change, also run `php artisan ems:print-fidelity` and
  `php tools/seed_full_demo_pipeline.php` and **read their warnings** — a green suite does not
  prove a desk can demonstrate the rule.
- Local green is not enough, twice proven: tests that pass on this machine because Chrome, a
  `storage/app/prints` folder, or a live-`ems` row exists will fail on the runner. A test that
  binds a fake service must have **every** method its route reaches — an un-overridden method
  quietly calls the real one; poison the real method once and confirm the test still passes.

## Working with the live `ems` database

- Dump before every live write: `mysqldump -u root ems > C:\Users\ADMIN\ems-backups\ems-pre-<what>-<date>.sql`,
  then verify the file landed and contains the table you are about to change.
- `php artisan db:seed --class=RbacSeeder` prunes permissions the seeder no longer declares, so
  retiring a right is a seeder edit plus a reseed, not a migration.
- RBAC pivots are renamed: `staff_roles`, `role_permissions` with columns `roleId` / `permissionId`;
  there is **no** `model_has_roles`. `staff_roles.model_type` is stored double-escaped
  (`App\\Models\\Staffusers`), so a single-backslash SQL literal matches nothing and every account
  looks role-less.
- Never delete rows found during probing. Deleting probe residue is its own decision for the owner.

## Facts new code must respect

- **Stack:** Laravel + Inertia/React (`resources/js/Pages`), MySQL 8 via Laragon, PHP at
  `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`. `localhost:8080` is Laragon Apache;
  `php artisan serve` silently shadows it on loopback, so check which server answered a probe.
- Public `/register` is removed. Staff accounts come from Admin → User Management or
  `php artisan ems:create-admin`.
- Student 360 and quick-search require the seeded `students.view` permission; bare `Staff` and
  `Instructor` do not hold it. The frontend reads shared prop `can.studentsView`.
- Office ids: use `App\Enums\OfficeId` (Registrar=1, Accounting=2, Scholarship=3, Guidance=4,
  Blocking=5, Admission=6, Academic=7, Clinic=11, IdOffice=22) — never a bare integer in PHP, and
  never a hand-kept office list in JSX. Descriptors that gate a card by number went stale when
  ruling 12 folded legacy office 8 into Registrar; navigation now answers to a right the desk's own
  route asks (`DashboardController::QUEUES`, `HandleInertiaRequests`' `can` map).
- Spatie registers `Gate::before`, so **a gate ability must never share a permission's name** — the
  permission would grant it and the policy body would never run (`AbilityNameCollisionTest` pins it).
  Note `Gate::before` also grants SysAdmin everything before any policy runs.
- Staff relations are named `*User` (`evaluatedByUser`, `registrarProcessedByUser`, `approvedByUser`,
  `receivedByUser`); the bare FK columns return ints. `Schedulemeetings.startTime/endTime` are plain
  strings — casting them breaks Blocking's conflict detection.
- Money in the drawer is `Payments::held()` (`paid` or `partial`). "Is clearance season?" is
  answered only by `Clearanceperiods::accepting()` (open **or** extended). Year level is derived
  from the student's own record, never asserted as a constant.
- Migrations containing raw MySQL must guard on the driver (`getDriverName() !== 'mysql'`) — the
  SQLite suite depends on that escape hatch.
- Record timestamps exist only on students, admissions, enrollments, payments, studentassessments,
  studentclearances, clinicrecords and idrequests.

## Editing the authority docx

`word/document.xml` is ~5.7M characters and its sentences are split across `<w:t>` runs, so:
edit through `storage/app/scratch/docx-edit-engine.php` with absolute offsets verified before each
write; never reflow existing tables from memory; **one offset cannot carry two edits** (an insert
and a rewrite at the same byte corrupt the document); re-measure every `File.php:NN` citation an
edit invalidates and verify it by *meaning* (which method the line sits in), not arithmetic;
before believing a clean verdict from any checker, feed it a mutant and watch it fail; re-run
`dump-docx-flat.php` before trusting `probe-docx-cite-method.php`, which reads that generated dump;
then prove the file still opens — `docx-ooxml-check.php`, `verify-docx.php`, and
`word-open-copy-test.ps1`, which opens a hash-identical copy in real Word and quits only the
Word instance it started.

## Agent dispatch

Scope agents to non-overlapping paths, run independent lanes in background, reconcile before
advancing dependent work, and never poll a background task — wait for its notification.
