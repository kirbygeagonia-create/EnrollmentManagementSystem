# SEAIT Enrollment Management System

A web application for the enrollment office of South East Asian Institute of Technology, Inc.
It carries one student from an application form through to a validated ID, and it records every
step so a desk can say who did what, when, and under which signature.

It is a Laravel 13 application with an Inertia/React front end, a MySQL 8 database, role and
permission access control, a nine-office workflow, itemized tuition assessment with receipts,
clearance, ID and clinic routing, examination records, PDF documents on the school's letterhead,
an audit trail, and a documentation set that is checked by its own instruments.

---

## 1. What the system does

The unit of work is an **enrollment**: one student, in one term, in one program. Everything else
hangs off it.

```
Admission -> Evaluation -> Assessment -> Accounting -> Registrar -> Blocking -> Clinic -> ID
(applicant)  (department)  (fee sheet)   (cash)        (enroll)    (seating)   (health)  (photo)
```

The first step is the application; the seven that follow are the boxes on the enrollment's workflow
form, described in section 3.

Concretely the system:

- takes an applicant through a multi-step intake form, saved as a **Draft** and resumed without
  re-entering data, then verified requirement by requirement and approved or rejected with a reason;
- administers entrance examinations (general and course-specific) and lets the Registrar adopt a
  result from provisional to official;
- lets the department evaluate the record, propose or confirm subjects against a pinned
  curriculum, credit transferee and shifter equivalencies with its own decision and reason, and
  refuse (return) a record to a named office with a reason;
- computes an itemized fee sheet from the curriculum and the fee catalogue, applies scholarships
  and adjustments, and takes money against it with official receipts;
- seats the student in a block (section), enforces that block's capacity against its own count, and
  builds the timetable from lecture and lab units per subject, meeting time, room and instructor,
  refusing a room or an officer double-booked at the same hour and a block in two places at once;
- routes the enrolled record to the clinic and the ID office, and holds the clearance checklist,
  line by line, with approval, rejection with a stated reason, or waiver, inside a clearance period
  that the Clearance desk opens, extends, or closes;
- prints the papers each desk owes the student, on the school letterhead, and logs every issuance;
- shows each office its queue, its counts, and its filters, and notifies the office whose turn it
  is next, with a link that lands on the record.

---

## 2. The nine desks

Office ids are named cases in [app/Enums/OfficeId.php](app/Enums/OfficeId.php). No policy, seeder
or screen writes a bare office integer.

| Office | Id | Desk route | The work it does |
|---|---|---|---|
| Registrar | 1 | `registrar.index` | Approve enrollment, drop a record, print forms, certificates, class cards and subject load |
| Accounting | 2 | `accounting.index` | Record payment, refund, void a receipt; daily collections |
| Scholarship / Assessment | 3 | `assessment.index` | Build and adjust the fee sheet, apply scholarship coverage |
| Guidance | 4 | `shift.index` | Department evaluation routing, shift requests, academic standing |
| Blocking | 5 | `blocking.index` | Blocks and sections, capacity, schedules, timetable CSV import |
| Admission | 6 | `admission.index` | Intake drafts, requirement verification, approve or reject |
| Academic / Department | 7 | `evaluation.index` | Evaluate, propose and confirm subjects, credit equivalencies, return |
| Clinic | 11 | `clinic.index` | Health records and the clinic box |
| ID Office | 22 | `id.index` | ID requests, photo capture, validation |

`AdmissionController` and `ClearanceController` deliberately build their own lists: their dashboard
cards count a `pending` queue while the desk page must also show drafts and rows it may still act
on, so unifying them would hide records rather than align numbers. The other seven take their rows
from the same call their card counts.

Clearance is a tenth queue page rather than a tenth office: a clearance is a checklist whose lines
belong to the nine offices above, each approved by its own desk, and the overall status is the
result of that set rather than one signature.

---

## 3. The enrollment workflow

The one declaration of both the words and the order of the boxes is
`WorkflowService::boxOrder()` in [app/Services/WorkflowService.php](app/Services/WorkflowService.php):

| Order | Box | Signing office |
|---|---|---|
| 1 | Department Evaluation | Guidance (4) |
| 2 | Assessment | Scholarship (3) |
| 3 | Accounting Payment | Accounting (2) |
| 4 | Registrar Approval | Registrar (1) |
| 5 | Blocking and Scheduling | Blocking (5) |
| 6 | Clinic | Clinic (11) |
| 7 | ID Validation | ID Office (22) |

The Assessment box is created only for a `firstYear` or `transferee` student; a `continuing` or
`shifter` record skips it, so one enrollment holds six boxes and another holds seven. That is why
the tracker numbers the boxes a **record** holds, which is a different question from which desk is
next institutionally, and why the sequence travels to the browser as a JSON list and never as a map
keyed by office id (JavaScript re-sorts integer-like keys ascending, which would silently destroy
the order the school signs in).

Statuses of the record itself (`App\Enums\EnrollmentStatus`): `pending`, `evaluated`, `assessed`,
`paid`, `enrolled`, `dropped`, plus the two return states `returnedToEvaluation` and
`returnedToAdmission`. Only `EnrollmentStateMachine::transition()` moves a record between them, and
every move writes an `enrollmentstatushistory` row.

A return is one act (`ReturnController` and `App\Services\ReturnRecorder`) available at three desks
under one permission. It is stored as an `enrollmentreturns` row in columns rather than prose: who
returned it and when, the office and step it came from, the destination office and its step, the
category, the desk's note, the expected correction, and the status the record held before and after.
The notification that goes to the receiving desk is addressed from the destination, not from the
box list as it stood before the return re-opened it.

---

## 4. Stack and layout

| Layer | Choice |
|---|---|
| Framework | Laravel 13 (`composer.json` requires `^13.8`) |
| PHP | 8.3+ (`pdo_mysql`, `mbstring`, `dom`, `gd`, `intl`, `zip`) |
| Front end | Inertia 2 + React 18, Tailwind, Headless UI, Vite 8 |
| Database | MySQL 8 (Laragon on this box); the test suite also runs on SQLite |
| Access control | `spatie/laravel-permission` 8 with this repository's enums |
| PDF | `spatie/browsershot` driving a Chromium from the machine (`.npmrc` sets `ignore-scripts=true`, so no bundled download) |
| Tests | Pest 4 / PHPUnit 12, PHPStan (larastan) 3, Pint, ESLint 10 |
| Routing to JS | `tightenco/ziggy` |

```
app/
  Console/Commands/     nine artisan commands, all namespaced ems:*
  Enums/                48 domain vocabularies (statuses, types, offices)
  Http/Controllers/     25 controllers, one folder per desk
  Http/Middleware/      four: the shared Inertia payload, security headers, audit naming, the
                        deactivated-account gate
  Models/               55 Eloquent models
  Services/             9: the enrollment state machine, workflow, issuing, returns, printing,
                        academic standing, and the Chromium locator PDF rendering needs
  Support/              19 single-definition helpers (see below)
database/
  migrations/           109 files
  seeders/              6: Rbac, Settings, Starter/Dev reference data, notifications, DatabaseSeeder
resources/js/
  Pages/                62 screens, grouped by desk (Accounting, Admission, Assessment, Blocking,
                        Clearance, Clinic, Evaluation, Exam, ID, Registrar, Students, Admin, Auth, Profile)
  Components/ui/        the shared controls: DataTable, StatCard, Modal, StepProgress, statusLabel.js
routes/web.php          184 route declarations
tests/Feature/          29 folders plus root-level guards; E2E holds the walkthrough legs
tools/                  demo data, backup drill, load probes (not analysed by PHPStan by design)
storage/app/scratch/    the instruments, harnesses and docx passes (over a thousand tracked .php)
Documentation/          the deliverables; the .docx named below is the authority
```

---

## 5. Rules that live in exactly one place

Each of these is the only definition in the system. A second copy of any of them is the defect this
repository's instruments are built to catch.

| Rule | Owner |
|---|---|
| Money the school is holding | `Payments::held()` (status `paid` or `partial`) |
| What an account still owes | `Studentassessments::outstandingBalance()`, which floors at zero |
| Which desk acts next | `WorkflowInbox::nextOffice()`, read by the bell and by the monitor |
| A timetable collision | `App\Support\TimetableConflicts`, asked of officer, room and the block's own hour, inside the term the block belongs to |
| What must be true before a record proceeds | `App\Support\EnrollmentReadiness`, the same answers the gate and the queue tile give |
| The clearance checklist's names | `App\Support\ProvisionalClearanceRequirements`, the one list the seeder and the backfill migration both write from |
| One notice per act per recipient | `App\Support\NoticeDelivery::once()`, keyed on the history or step row that names the act |
| The sentence a refusal gives | `App\Support\DenialReason`, shared by the 403 page and the JSON caller |
| Each dashboard queue | `App\Support\DeskQueues`, one builder per desk |
| The college boundary | `App\Support\CollegeScope`, the only place `unitId` is interpreted |
| A credit that counts | `Creditedsubjects::approved()` |
| An adopted exam result | `Examresults::isOfficial()` |
| Whether clearance season is open | `Clearanceperiods::accepting()` (open or extended) |
| The school's civil day | `App\Support\SchoolTime`, reading `config/school.php` |
| How a year level is spelled | `App\Support\YearLevel::format()` in PHP, `formatYearLevel()` in JS |
| A list's order | `App\Support\ListSort` on the server, `DataTable` as the only control |
| What an audit row is called | `App\Support\AuditAct`, named at the request boundary |
| Notice text, reference and link | `App\Support\NoticeFacts::forEnrollment()` |
| Load ceilings and teaching limits | `App\Support\LoadLimits`, bound by whatever the school enters |

**Time.** The application stores and compares instants in UTC and forces `+00:00` on every MySQL
session. The school's civil day is a separate declaration (`SCHOOL_TIMEZONE`, default
`Asia/Manila`). A desk-facing day or month boundary is always a half-open interval between two
instants from `SchoolTime`, never `whereDate()` against a `DATETIME` and never `now()` into a `DATE`
column. A calendar day reaches the browser as text and is read from its own string
(`calendarDay.js`), so it does not shift a day for a viewer in another zone.

**Money.** An official receipt may not state more than the account owes; the guard refuses above the
outstanding balance. `paid` on a receipt means the money that cleared the balance, `partial` means
every peso taken before that, and a status is a claim about the account rather than about the money.
A grant may not push a settled account into a credit.

---

## 6. Access control

Roles and permissions are declared in `database/seeders/RbacSeeder.php` and reseeded rather than
migrated (retiring a right is a seeder edit plus `php artisan db:seed --class=RbacSeeder`). Measured
against this box's live `ems` on 2026-11-06: **97 permissions, 311 role-permission grants, all 97
granted to at least one role, and 17 roles**, with 21 active staff accounts.

The 17 roles: `SysAdmin`, `Admin`, `AdmissionOfficer`, `GuidanceStaff`, `DeptEvaluator`, `Dean`,
`ProgramHead`, `ScholarshipOfficer`, `AccountingStaff`, `RegistrarDesk`, `RegistrarApprover`,
`BlockingCoordinator`, `ClinicStaff`, `IdOfficer`, `OfficeHead`, `Staff`, `Instructor`.

Three rules that are easy to get wrong and are pinned by tests:

- Spatie registers `Gate::before`, so a gate ability must never share a permission's name, or the
  permission would grant it and the policy body would never run (`AbilityNameCollisionTest`).
  `SysAdmin` is granted everything before any policy runs.
- A staff account with a non-null `unitId` is **college-bound** and sees only its own college's
  rows; a null `unitId` is bounded by nothing. A dropdown must be filtered by the same rule the
  policy asks, or the screen offers work the save refuses.
- Public `/register` is removed. Staff accounts come from Admin, User Management, or
  `php artisan ems:create-admin` for the first SysAdmin.

Every desk route asks a named permission, and a refusal states its reason on the page (and as JSON
for a caller that asked for JSON). A deactivated account is refused at the login screen **and** at
its next request mid-session, by one sentence held in `DenialReason`.

---

## 7. Printed documents

Six document types (`App\Enums\DocumentType`): subject load, class card, certificate, clearance
slip, block schedule, and the enrollment form. Each desk print is the paper: it mints its own
document number, writes its own `documentprintlog` row, and renders the same Blade template a
download does.

The letterhead (name, campus address, Registrar landline) comes from the `settings` table, seeded
from `Documentation/SEAIT_Official_Information.md` §1 and editable on the Admin screen. A print
template falls back to the same string, so a fresh install cannot print a different name from one
that has been edited. The clearance slip marks itself `(Student's Copy)` and carries the course code
wording the school's own paper uses.

`php artisan ems:print-fidelity` renders the set from the live database so the papers can be read
rather than assumed; the comparison sheet is
`Documentation/Print-Fidelity-Comparison-2026-10-10.md`.

---

## 8. Commands

| Command | What it does |
|---|---|
| `ems:preflight` | Checks the install (env pair, database, unapplied migrations, unseeded RBAC, private disks, application key, writable folders, a browser for PDFs, the queue connection, the host's hour against the school's). Writes nothing; a non-zero exit means a desk would break on this server. |
| `ems:create-admin` | Prompts for the first SysAdmin under the password policy. |
| `ems:audit:record-states` | Runs 20 rules that report records no desk action can produce (an ID signed without a photo, a box signed by nobody, a workflow finished while a box is open, a receipt `paid` over a balance still owed, a block over capacity, colliding schedules). IMPOSSIBLE findings fail the run; DECISION counts are reported. Writes nothing. |
| `ems:import:students` | Dry-runs a Registrar student sheet against the real rules and names every collision. Writes nothing. |
| `ems:import:timetable` | Previews and publishes a timetable CSV. Refuses the whole sheet on any collision unless `--fill-gaps`; `--json=` feeds the Blocking desk screen. |
| `ems:print-fidelity` | Renders the document set from live data for paper comparison. |
| `ems:uploads:prune` | Reports uploaded files that no record references any more; it removes them only when given `--delete`. |
| `ems:benchmark` | Times the heavy queries. |
| `ems:ci:no-silent-skips` | Refuses a CI run in which a guard quietly skipped instead of asserting. |

Under `tools/`: `seed_full_demo_pipeline.php` (the demo cast and the year-level evidence, run by CI),
`seed_bulk_demo_dataset.php` (a population that satisfies all 20 audit rules and delivers at least
800 rows per desk), `seed_demo_data.php`, `ems-backup-drill.php`, `validate_demo_backup.php`, and
 assorted probes. `Python/` (an embedded interpreter on this box), the reference scans of real
signed Registrar forms in `Images/`, and every database dump are ignored and never committed.

---

## 9. Testing and quality gates

The suite is Pest, in `tests/Feature`, organised by desk with cross-cutting folders (`Rbac`,
`Sorting`, `Print`, `E2E`, `Notification`, `Workflow`, `Console`). On the delivered whole run on this
box, 2026-11-06: **1393 passed and 8 skipped, 8287 assertions**, and `artisan test --list-tests`
discovers the same 1401, which is the check that no inserted method stole a `#[Test]` attribute. The
eight skips are the E2E walkthrough legs, which skip by design on the in-memory SQLite driver and run
against a seeded MySQL schema in CI's own step. An assertion total is a property of the run that
printed it, so a figure here is always quoted with its surface and its date.

Run it whole, never per directory: a per-directory run hides cross-file breakage.

CI (`.github/workflows/ci.yml`) runs three jobs and none may be red:

1. Backend, SQLite: Pint, PHPStan, the full Pest suite, then the document and instrument gates.
2. Backend, MySQL 8: the suite against MySQL, the seeders and the demo pipeline in CI's own order,
   then the E2E walkthrough with `--fail-on-skipped`.
3. Frontend: ESLint at `--max-warnings=0` and the Vite build.

Alongside the tests sits a large family of tracked instruments in `storage/app/scratch`
(lane-numbered checkers plus, for most of them, a `mutate-*` harness that plants the defect and
requires the named check to fail). The standing principle: a checker that cannot fail has checked
nothing, an unread claim is reported as a refusal and never as a clean verdict, and every count a
tool prints is read from that tool rather than typed into prose. Gates a batch must leave green are
listed in `AGENTS.md`.

---

## 10. Documentation

`Documentation/EMS Complete Documentation.docx` is the **authority** (owner ruling 2026-09-29).
Where the storyboard, the use-case guide or `AGENTS.md` disagree with it, that document wins, and
when the code moves the document is edited in the same batch rather than left describing an older
build. It is 260 pages over 186 tables, read from the Word-open test run against the committed bytes
on 2026-11-06, and it is maintained by numbered offset edits verified by citation and figure sweeps.

Other deliverables in the same folder, by use:

- Walkthrough and demo: `Per-Office-UAT-Walkthrough.md`, `Demo-Day-Guide.md`,
  `Offline-Presentation-Guide.md`, `System_Storyboard_Walkthrough.md`.
- Design and data: `SYSTEM-ARCHITECTURE-ADS.md`, `DATABASE-DESIGN-ADS.md`,
  `DATABASE-CONCEPT-COMPLIANCE-ADS.md`, `Functional-Decomposition-Diagram.md`,
  `Use-Case-Diagram-Guide.md` and the generated figures in `Diagrams/`.
- Verification: `REQUIREMENTS-TRACEABILITY-ADS.md`, `TESTING-AND-VERIFICATION-ADS.md`,
  `EVIDENCE-PACK-ADS.md`, `DEFENCE-EVIDENCE-ADS.md`, `SECURITY-AND-RECOVERY-ADS.md`,
  `FINAL-REPORT-ADS.md`, `IMPLEMENTATION-CHANGELOG-ADS.md`.
- Requirements traced to the development prompt: `Prompt-Requirement-Matrix-2026-10-26.md` and
  `Prompt-Completion-Report-2026-10-27.md`.
- Audits and rulings: the 2026-09 UI/UX and forms audits, `Audit-Remediation-2026-08-29.md`,
  `SEAIT-EMS-Round3-Current-State.md`, the three `School-Decision-Sheet-*.md`, the Registrar print
  and window sheets, and the answered ADS guide copies.
- `SEAIT_Official_Information.md` carries the institutional facts the letterhead and the seeders read.

---

## 11. Install and run

Full instructions, including the mandatory production overrides, the queue worker, the browser for
PDFs and the post-deploy smoke test, are in [DEPLOYMENT.md](DEPLOYMENT.md). The short version:

```bash
cp .env.example .env                      # then set APP_KEY, DB_*, and the pair rules
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan ems:create-admin
php artisan ems:preflight
```

`db:seed` runs `RbacSeeder`, `SettingsSeeder`, the reference data and the notification seeder; on a
machine that will demo the desks, follow it with `php tools/seed_full_demo_pipeline.php`, which
writes the demo cast and refuses its own run when a record it built is in a state no desk could
produce.

For local development on this box: Laragon Apache serves the app on `localhost:8080` (note that
`php artisan serve` silently shadows it on loopback, so check which server answered a probe), and
`npm run dev` runs Vite. `php artisan test` needs no server.

Seeding order matters: `db:seed` runs before `DevReferenceDataSeeder`, and any seeder that creates
staff accounts must call `RbacSeeder::assignDeskRolesFromEnums()` afterwards, or desk heads end up
with no roles and cannot open their own desks.

---

## 12. Running it offline

The system runs with no internet. There is no outbound HTTP call anywhere in `app/` (measured, not
recalled), the fonts are served from this repository, and the demo machine is configured for it:
`MAIL_MAILER=log`, `QUEUE_CONNECTION=sync`, and `CACHE_STORE` and `SESSION_DRIVER` on files. Note
that the shipped `.env.example` names `database` for cache, session and queue, which is the
production shape, so a fresh clone is not offline-ready until those four keys are set. Two more
things to know:

- `public/hot` makes every asset URL point at the Vite dev server. That is invisible to the host
  machine and fatal for a LAN client or a phone, so a shared demo must be built (`npm run build`)
  and must not leave the file behind.
- PDF routes need a local Chromium found by Browsershot. Without one, the on-screen print pages
  still render and only the PDF fails.

`php storage/app/scratch/lane261-make-offline-bundle.php` assembles a copyable kit (git archive of
the tracked tree plus a fresh database dump) into `C:/Users/ADMIN/ems-offline/`, dated by the
school's civil day. Dumps never enter the repository.

---

## 13. Security posture

- No public registration; staff accounts are created by an admin.
- Every desk route is gated by a named permission, and every record read passes a policy; college
  bound accounts are narrowed by `CollegeScope` on both the list and the dropdown.
- The private upload disk is not served. `GET /storage/...` and `PUT /storage/...` are not
  registered, so uploaded papers and face photos cannot be fetched by URL, and an authorised read
  goes through a route that asks the policy.
- Security headers, including HSTS, are emitted when the app actually sees HTTPS. Behind a proxy
  that terminates TLS, name that proxy in `TRUSTED_PROXIES` and re-run `config:cache`; the wildcard
  is refused, because it would let any client forge its own address and scheme, and both the login
  throttle and the audit log read the client address.
- Staff and student credentials are hashed with the framework's default bcrypt driver (this
  repository ships no `config/hashing.php`, and the stored rows on live `ems` carry the `$2y$12$`
  prefix). `Students::$hidden` and `Staffusers::$hidden` keep the hash out of every payload, and a
  desk list's student columns are pinned to the identity the screen paints, so a sort that joins a
  relation cannot widen the row into leaking them.
- `APP_DEBUG=false` is enforced: the app refuses to boot in production with debug on.
- Writes are logged to `auditlogs` with the named act, the actor, the record and the reason, and
  sensitive fields are redacted by the observer.
- Backup and restore are drilled, not assumed; the drill and its numbers are in
  `Documentation/SECURITY-AND-RECOVERY-ADS.md`.

---

## 14. What is still open

Held honestly rather than smoothed. Three items are open, one of them owed code and two of them
owed an answer from the school:

- Sort order on the seven bounded reference sets. The owner ruled these take a client-side order,
  because a server order there would only re-rank the rows already on screen; the ruling is not yet
  built.
- The school's list of rejection reasons to sit beside the free-text reason the Admission desk
  already requires. The refusal and its ten-character minimum are built; the vocabulary is the
  school's.
- The privacy notice, its lawful basis, and a retention rule. Measured rather than recalled: a grep
  for `Data Privacy`, `RA 10173`, `lawful basis`, `privacy notice` and `retention period` across
  `app/`, `resources/js`, `config/`, `database/` and `routes/` answers nothing. The container, the
  wording and the window are the school's to state (decision sheet items 7 and 8), and this
  repository will not invent them.

What does exist on the privacy side is narrower and real: credential hashes are hidden on both
models, a desk row's columns are pinned to the identity a screen paints, clinical notes require
`clinic.view`, and `ems:uploads:prune` reports the uploaded files no record references any more.

Two load ceilings (a maximum study load per term and a bounded teaching load) are seeded blank by
design. `App\Support\LoadLimits` holds the rule and reads its number from the `settings` table, so
it binds the moment a figure is entered on the Admin screen; inventing the number here would be
making the school's decision for it.

---

## 15. Contributing and house rules

The two rules that outrank the rest, from `AGENTS.md`:

1. Do not commit or push until the owner says so explicitly.
2. The authority document wins, and it moves in the same batch as the code.

Beyond those: never exercise a new write path on the live database without a dump and a scratch
rehearsal first; dump before every live write; never delete a row found while probing; never clone a
database by editing a mysqldump; do not launch two mutating runs at once; re-measure a figure rather
than re-typing it; and quote the verdict an instrument printed from its own output, not from the
shape of the command that was run.

---

*South East Asian Institute of Technology, Inc.*
National Highway, Purok 7, Crossing Rubber, Tupi, South Cotabato, Philippines 9505
Registrar: (083) 226-1602
