# System Architecture (ADS presentation item 1 companion)

**What this file is.** The Advanced Database Systems guide asks for `docs/SYSTEM_ARCHITECTURE.md`. This repository
already holds one architecture authority — `Documentation/EMS Complete Documentation.docx` — and the owner ruled
on 2026-09-29 that where any other document disagrees with it, the docx wins. A second document that *restates*
architecture would be a second definition of one thing, which is the defect class this project has refused in
year levels, tone maps, sort vocabularies and addressee maps. So this file states **where each fact lives** and
only the few numbers a reader needs to orient, each credited to the instrument that prints it. Re-measure with
the named command before quoting any of them.

## Stack, read from the repository rather than recalled

| Layer | What the checkout answers | How to re-check |
|---|---|---|
| Framework | Laravel **13.23.0** (`composer.json` `^13.8`) — not 11, which an earlier note here repeated without re-reading | `php artisan --version` |
| Frontend | Inertia + React 18.3, pages under `resources/js/Pages`, shared controls under `resources/js/Components/ui` | `npm run build` output |
| Database | MySQL 8 through Laragon; the suite runs SQLite `:memory:` (`phpunit.xml` pins it) | `php storage/app/scratch/lane60-ratio-claims.php --run` |
| PHP | `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`, not on PATH | `where php` will not find it; name the binary |
| CI | three jobs: Pest+PHPStan+Pint on SQLite, Pest on MySQL 8, ESLint+Vite build (`.github/workflows/ci.yml`) | `gh run view <id> --json status,conclusion` |

Schema and table counts are deliberately **not** typed here: `lane60-ratio-claims.php --run` prints them and the
authority document §24 carries the per-table dictionary, so a figure in this file would be a third copy.

## Request path

`routes/web.php` (only `['auth']` middleware; no `can:` constraints) → a controller in `app/Http/Controllers/…` →
`$this->authorize()` against one of the 15 policies in `app/Policies/` → Eloquent through `app/Models/` → an Inertia
response rendered by a page under `resources/js/Pages`. Server-side authorization is the enforcement; the frontend
navigation is derived from the shared `can` map in `app/Http/Middleware/HandleInertiaRequests.php` and answers to
rights, never to hand-kept office numbers (`App\Enums\OfficeId` is the only spelling of an office in PHP).

Where a screen lists rows, the shared control is `resources/js/Components/ui/DataTable.jsx`, and where that list
can be ordered the handler is `App\Support\ListSort` — one mechanism. How many screens are wired to it is a figure
that moves, so it is read rather than typed here: `php storage/app/scratch/lane140-measure-wired-screens.php`
answers the wired pages, the wired actions, and every claim the documents make about both.

## The enrollment pipeline

Admission → Department Evaluation → Scholarship Assessment → Accounting → Registrar → Blocking → Clinic → ID,
held as rows in `enrollmentworkflow` and `workflowsteps`; the status vocabulary in `App\Enums\EnrollmentStatus` and
the only writer of `enrollmentstatushistory` is `App\Services\EnrollmentStateMachine`. Three things are worth
saying to a database examiner:

- **An enrollment is issued, not edited into existence.** `App\Services\EnrollmentIssuer::issue()` opens one
  transaction that writes the record and its workflow form together, and `seatHolder()` answers the enrollment that
  already holds a student's seat in a term — the seat is released by a drop, which is why a drop is terminal
  (`EnrollmentStateMachine` declares `'dropped' => []`).
- **Money and status are different claims.** A receipt is `Payments::held()` (`paid` or `partial`); a status says a
  moment. `AccountingController::record()` refuses a peso above what the account owes, and
  `php artisan ems:audit:record-states` weighs both as two of its rules.
- **The bell is addressed from the record, not the completer.** `App\Support\WorkflowInbox::nextOffice()` answers
  whose turn it is and `actors()` resolves that office's active accounts *that may reach the record*, asking
  `App\Support\CollegeScope` — the same boundary the desk's own list applies.

## Cross-cutting subsystems

| Concern | One place | Read it at |
|---|---|---|
| College boundary | `App\Support\CollegeScope` | §7 of the authority document |
| Desk queue populations | `App\Support\DeskQueues` | matrix gap G-46's row |
| Notice parts and delivery | `App\Support\NoticeFacts`, `App\Support\NoticeDelivery` | §19 of the docx, §28.3 G-35 and G-36 |
| School civil day vs application clock | `App\Support\SchoolTime`, `config/school.php` | §1.2 and §12 of the docx |
| Timetable conflicts | `App\Support\TimetableConflicts` | §26 of the docx |
| Sort vocabulary | `App\Support\ListSort`, `resources/js/Components/ui/listSort.js` | §28.3 G-40 |
| Audit trail | `app/Observers/AuditLogObserver.php`, attached per model in `AppServiceProvider` | §29.2 |
| Print and issuance | `App\Services\PrintService`, `ems:print-fidelity` | §25 of the docx |

## Verification gates the architecture depends on

Pest (whole run, never per directory), PHPStan at level configured in `phpstan.neon` covering `app/`, Pint
`--test`, ESLint `--max-warnings=0`, the Vite build, and the document instruments in `storage/app/scratch/` that
keep the prose honest — citation bounds, section references, figures attributed to the tool that prints them,
unique-key inventories, delete-guard lists, filter contracts and tile scopes. `tools/` is analysed by neither
PHPStan nor Pint, so a change there is proven by running it and reading its output.

**What this file does not claim.** It does not restate the data dictionary, the register of findings, or the office
responsibilities; those live in the docx and are read from there. If a line number in this file ever disagrees
with the code, the code is right and `php storage/app/scratch/repo-file-line-citations.php` is the check that says
so — it reads this document, so a stale pointer here is a gate failure, not a silent drift.
