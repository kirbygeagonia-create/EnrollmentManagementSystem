# Required Final Report, development prompt section 16

Written 2026-10-27 (this box's clock; real UTC is one civil day earlier, see the authority docx
section 1.2). Branch `master`, at commit `8055cce` when this pass began, with this pass's files
uncommitted pending the owner's word.

The question this answers is the owner's: "is this file of instructions implemented?", the file being
`EnrollmentManagementSystem_Optimized_AI_Development_Prompt.md` (kept in Downloads, never in the tree).
The requirement-by-requirement inventory is
`Documentation/Prompt-Requirement-Matrix-2026-10-26.md`; this report is the eight-part summary that
section 16 asks for, and every figure in it was read this pass rather than recalled.

## 1. What was actually inspected

* The prompt itself, all 18 sections, read end to end and turned into a checklist of 44 named
  requirements: sections 1 to 3 (role, context, scope rules), 4 (the audit deliverable and its
  sixteen inspection lines), 5 (types and identity), 6.1 to 6.5 (workflow engine), 7.1 to 7.10
  (one entry per office), 8 (notifications and queue accuracy), 9 (accounts, audit, archiving),
  10 (privacy and security), 11 (UI, UX and print), 12 (backend and database rules), 13
  (phases 0 to 6), 14 (the test scenarios and the completion gate), 15 (commands), 16 (this
  report), 17 (operating instructions), 18 (external references, guidance only).
* The working tree: `git status`, branch, HEAD, `composer.json` and `package.json`, `routes/web.php`,
  `database/migrations`, `database/seeders/RbacSeeder.php`, `app/Enums`, `app/Support`,
  `app/Services`, `app/Http/Controllers/Admin`, `app/Policies`, `resources/js/Pages`,
  `resources/js/Components/ui`, `resources/js/Components/navigation`, `tests/Feature`.
* The running application: the new admin screen's route, its policy gate, and its filters, driven
  through feature tests rather than by reading the code alone.
* The database: live `ems` for row counts, `information_schema` for columns and foreign keys, and the
  seeder text for the permission catalogue, kept apart because they are different surfaces.
* The authority docx `Documentation/EMS Complete Documentation.docx` (the owner's ruling of
  2026-09-29 makes it the document of record), its generated flat dump, and the eleven claim
  checkers that read it.

## 2. Key findings

Confirmed working, verified by reading the code and the tests this pass, not by a test name:
one canonical person record (5.1); the four student classifications and the separate identity,
application, standing, enrollment and step vocabularies (5.2); hashed credentials and no public
registration (5.3); per-instance step snapshots so a changed box list cannot rewrite a signed
history (6.3); the configurable requirements catalogue (7.1); department-scoped examination with the
Registrar's adoption of a recorded result (7.2); standing derived from records and attempt history
(7.3); grade history by academic year and semester (7.4); the accounting invariants and the label
"Statement of Accounts" (7.5); the seven-box readiness checklist and the separate print documents
(7.6); permanent clearance records (7.7); restricted clinic detail (7.8); collision rules inside one
term (7.9); validation-only ID handling (7.10); the notification addressee read from three surfaces
(8); deactivated accounts unable to sign in, with the trail kept (9); private storage and the
trusted-proxy setting (10); and the migration discipline (12).

Two requirements were genuinely not implemented, and both are built by this pass:

1. Section 6.1 names four places workflow progress must be visible. Three were served; the fourth, an
   admin monitoring page, and section 6.3's admin view of progress, had no surface at all. This is the
   kind of requirement the matrix missed: it lists places rather than acts, so a gap register keyed to
   "configurable workflow" left the viewing half unscored.
2. Section 7.9 specifies the timetable grid as Monday through Sunday, 30-minute intervals, 7:00 AM
   through 9:30 PM. The grid ran to 9:00 PM, so a class meeting until half past nine was clamped and
   drawn as if it ended at nine. The clamp, not an error, is why no screen ever looked wrong.

One item is recorded as a divergence rather than a defect: `lane50-date-day-shift.php` refuses on a
date cell bound to nothing at `resources/js/Components/ui/PrintLayout.jsx:57`, which is the client
clock the lane already documents as an exception. Nothing in this pass touched that file. Lanes 46 to
50 are hand commands, not CI steps.

## 3. Changes implemented, grouped

Workflow monitor (6.1, 6.3): `app/Http/Controllers/Admin/WorkflowMonitorController.php` and
`resources/js/Pages/Admin/WorkflowMonitor/Index.jsx`, routed at `/admin/workflow-monitor` and linked
from the admin launcher and sub-navigation. One row per record still in the pipeline: phases signed
against phases total, which desk acts next, that desk's own name, the last signature time, and for a
returned record the reason category, the sending desk, the destination and the note, read from the same
`enrollmentreturns` row the receiving desk reads. Five tiles taken from clones made before the page
split: records in view, awaiting a desk, signed through, returned for correction, and records with no
workflow form, that last one being section 4's "missing workflow tracker" line turned into a count.
Read-only by design: configuring the order is gap G-6 and waits on the school, so the page names the
definition the records walk instead of pretending to edit one.

One definition of whose turn it is: `app/Support/WorkflowInbox.php` now answers `nextBox()` and
`progress()` from an eager-loaded box list when a screen has one, so the monitor, the desk pages and
the notification bell read the same rule with no per-row query.

Rights: `database/seeders/RbacSeeder.php` declares `workflow.view` in a fifteenth module;
`app/Providers/AuthServiceProvider.php` defines the ability `workflow.monitor`;
`app/Http/Middleware/HandleInertiaRequests.php` shares `can.workflowMonitor`.

Timetable grid: `resources/js/Pages/Blocking/Show.jsx` extended to 29 rows to 9:30 PM, with the slot
length and the end minute named as constants so the row count is derived, and the heading corrected to
the span it draws.

Authority docx, pass 275, 275b and 276: the new right in the permission catalogue, the module count and
total re-typed from the seeder, the SysAdmin and Admin role rows, a section 1.1 record row, four aged
pointers re-taken, and two lane 49 blind-spot figures re-read from that instrument. Pass 277 carries the
section 22.3 desk-head row and the live catalogue figures after the reseed; it reads the server itself
and refuses unless the reseed has landed.

No migration and no schema change is part of this pass.

## 4. Data and schema impact

None. No table, column, index or foreign key moved, so the schema census, the unique-key list and the
delete-guard inventory are unchanged. The RBAC change is data: one new `permissions` row and one new
role grant, applied by `php artisan db:seed --class=RbacSeeder`. Live `ems` was dumped to
`C:\Users\ADMIN\ems-backups\` before that seed ran, and the seeder prunes only rights it no longer
declares, which is none of the existing ones.

## 5. Security and permission changes

`workflow.view` is the whole institutional read: who may see every record's position. It is seated with
`SysAdmin` (which holds everything), with `Admin` through the rule that gives that overseer role every
`.view` right and no mutation right, and with `OfficeHead` because the repository pins that every
`.view` right belongs to every desk head; `PermissionMatrixTest` fired when the new right did not
follow that rule, and the seeder was corrected rather than the test exempted. No intake, assessment,
clinic or ID desk role holds it. The gate ability is named `workflow.monitor`, deliberately not
`workflow.view`, because Spatie's `Gate::before` would answer a same-named ability from the permission
alone and skip the ability body.

The screen shows no medical notes, no grades, no document contents and no credentials: a name, a
number, a program, a term, a status, a desk, a timestamp and a return note. Access to a record's
details still goes through that record's own policies, and the monitor links out to the student page
rather than reproducing it.

## 6. Testing evidence

Commands, run on this box:

* `php artisan test` (whole suite, SQLite `:memory:`, JUnit written to
  `storage/app/scratch/wm-junit.xml`): the run covering the monitor and the seating fix is
  `storage/app/scratch/wm-suite5.txt`; its own `Tests:` line is the reading, and this document states
  no suite total on purpose, because an assertion total is a property of a run rather than of the code.
  A previous whole-suite run failed exactly one case, `PermissionMatrixTest` office-head data set 14,
  and the seeder was corrected for it.
* `php artisan test tests/Feature/Admin/WorkflowMonitorTest.php`: 16 passed, 69 assertions. It pins the
  refusal without the right, the phase read from the record's own boxes, a signature moving the row to
  the next desk and agreeing with `WorkflowInbox::nextOffice()`, the desk filter keeping only records
  whose next open box is that desk, a later open box not offered to the desk behind it, tiles counting
  30 records while the page holds 25, a record with no form named rather than hidden, a signed-through
  record, a return's category origin and destination, the newest of two returns, a dropped record kept
  out of the count, a status the enum cannot hold refused with a message, the choices equal to the
  enums, the definition stated as unversioned, and the search reaching the columns it labels.
* `php artisan test tests/Feature/Blocking/TimetableGridSpanTest.php` and
  `php storage/app/scratch/mutate-timetable-grid-tests.php`: four cases and five planted defects, each
  required to turn its named case red. Their first measured run is recorded in
  `storage/app/scratch/wm-grid.txt` once executed.
* `vendor/bin/phpstan analyse`: 0 errors. `vendor/bin/pint --test`: PASS over 413 files.
  `npx eslint resources/js --max-warnings=0`: exit 0, captured from the command itself rather than
  from a pipe. `npm run build`: built.
* Document and code instruments, this run: citation sweep 742 citations over 162 files with 0 line
  problems; section reference sweep 949 references, 0 problems; repository citation sweep 694
  references over 319 files, 0 problems; filter contract 57 bindings, 0 problems; tile scope 12 screens
  and 48 tiles, 0 problems; tile fallback 0 problems; zero-versus-absence 0 problems; instrument figure
  binder 9 judged, 9 match, 0 stale, 8 history; row-count claims 10 bound, 0 stale over 3,272 pairs;
  empty-table inventory agrees; peso claims agree with the money tables with its two documented blind
  spots; open-claims checker silent; lane 70 queue coverage 108 of 108 with 0 stale records;
  OOXML contract CLEAN; `verify-docx.php` CONSISTENT; `word-open-copy-test.ps1` opened the file in
  real Word, 233 pages and 186 tables.

## 7. Outstanding items

Four rows wait on SEAIT, all on `Documentation/School-Decision-Sheet-2026-10-27.md`: the return reason
categories (the build ships four, the school may rename them from Reference Data), whether a department
may return a record to Admission, which phases are mandatory if a workflow may be configured (G-6, the
half of section 6.3 this pass deliberately did not build), and what "Failed" means for a walk-in
enrollment (G-8, which is why `workflowsteps.stepStatus` still holds three values). Two more wait on a
person rather than a ruling: G-5's spreadsheet half needs the school to say which workbook it holds,
and G-13's proxy address is set at install time on a server that has not been provisioned.

One item is a classification question, not code: the returnee type (G-9, section 5.2). The behavior
exists and is tested; naming the class needs the school's continuing-versus-returning rule, which the
prompt itself says must not be guessed.

## 8. Future scope intentionally not built

Public online applicant registration and remote document submission (the staff `/register` route stays
removed), student self-service and any student grade portal, online evaluation or remote approval
queues, online payment gateway and remote proof-of-payment approval, remote clinic clearance and
online-only completion, automatic official enrollment from an online submission, biometric or automated
identity matching from a photo, physical ID card production, and OCR timetable scanning.
`ApplicationMode::Online` remains in the schema as a dormant value referenced only by its own cast, so
historical rows stay readable without any path that activates them.
