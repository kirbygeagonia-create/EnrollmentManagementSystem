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

---

## Addendum (2026-10-29) — the direct answer, and what changed since

**Is the development prompt fully implemented? No — and the honest sentence is two clauses, not one.**
Everything the prompt asks the *product* to do is now built or classified, and the classification is what
this addendum extends: a second sweep of the prompt found that the first one had scored 14 of its **55**
requirement-bearing headings, and the unvisited sections produced ten more open gaps, recorded as G33
through G42 in the matrix. None of them is a missing office or a missing workflow; they are quality and
completeness clauses of the kind that stay invisible while the desks work.

### 1. What was inspected in this pass

The prompt file itself, heading by heading, counted by script rather than remembered: **357 enumerated
requirement lines across 55 headings**. The matrix at
`Documentation/Prompt-Requirement-Matrix-2026-10-26.md` now carries §7 (the sections the first pass never
reached) and §8 (§11 and §14), and every `File.php:NN` citation in both sections resolves —
`repo-file-line-citations.php` reports 0 problems across every dated owner-facing document in the tree — and it now refuses rather
than passing when such a document exists that it does not scan, so a sheet issued after this one cannot
be quietly unread. No citation total is quoted here on purpose: the count moves the moment a document
is added, and a figure that ages is a figure that will one day be believed falsely, after refusing six
shorthand paths on the first draft.

### 3. Changes implemented since the report was written

Lane 85: three places that answered a calendar day from the machine's clock rather than the school's —
`app/Services/EnrollmentIssuer.php` stamping `formIssuedDate`, the certificate's printed fallback in
`app/Http/Controllers/Registrar/RegistrarController.php`, and the Active/Inactive term filter in
`app/Http/Controllers/Admin/ReferenceDataController.php` — now call `SchoolTime::today()`; lane 50 gained a
PHP half so the class cannot return unnoticed, with the mutants to prove it; and the dead seeder
`tools/seed_demo_data.php` stopped directing a reader to a database dump that left the repository on
2026-10-26. No schema change, no new permission, no data rewritten.

### 6. Testing evidence for this addendum (every figure in this section was read on 2026-10-27, at the close of that
batch; the file count in particular moves as instruments are added, so it is a record of that run, not a property of
the repository)

Whole suite 931 passed / 6,242 assertions with `--list-tests` discovering the same 931 and
`ems:ci:no-silent-skips` reading 0 skipped; PHPStan 0 errors; Pint PASS 414 files; ESLint silent at
`--max-warnings=0`; Vite built; `ems:print-fidelity` 8/8. The three new cases were proved by
`storage/app/scratch/mutate-lane85-school-day-tests.php` and the checker's new classes by
`storage/app/scratch/mutate-lane50-checker.php` (13 legs) and
`storage/app/scratch/mutate-lane70-queue-reads.php`. CI is `completed / success` on all three jobs for the
pushed head. The demo pipeline was rehearsed on a clone before it was run against live data, and live `ems`
was re-read before and after and did not move.

### 7. Outstanding, after this sweep

Ten gaps, each with its § reference, risk and smallest coherent fix in the matrix:

- **G33** a rejected admission carries no reason at all, and the table has no column for one (§6.5) — the
  highest-consequence finding of the sweep, because the prompt states the rule twice.
- **G34** a clearance requirement may be refused with the remark left empty (§6.5).
- **G35** a notification shows no title and no protected deep link, so a desk reads the sentence and then
  hunts for the record (§8).
- **G36** nothing prevents a duplicate notice when a queued listener is retried — invisible on this box,
  real on the school's server, which runs `database` plus a worker (§8).
- **G37** the audit table records CRUD verbs, not the seven action families the prompt names, and holds no
  reason column (§9).
- **G38** clinical `findings` render on the Student 360 to every holder of `students.view`; disclosure scope
  belongs to the school and its Data Protection Officer (§10).
- **G39** print views re-declare status tone maps that omit the two return states (§11).
- **G40** no list table sorts (§11, §14). Its second premise — that two queues do not paginate — was retired on
  2026-10-29 after it was measured: no work queue lacks pagination, so the sorting half is the whole of the gap.
- **G41** §14's "no console errors, and empty/loading/responsive states checked" clause has no instrument,
  so it is unverified rather than passing (§14).
- **G42** a deactivated account is refused at login by rule and by no test, and an open session is not
  re-checked (§9, §14).

Four of those need a school answer before code: G35's destination per notice type, G37's audit vocabulary,
G38's disclosure scope, and whether a browser job belongs in CI for G41. The rest are buildable now, in the
prompt's own phase order: G33 and G34 in phase 2, G42 in phase 1, G35 and G36 in phase 2, G39 and G40 in
phase 5, G37 and G41 as documented decisions.

The four school questions already on `Documentation/School-Decision-Sheet-2026-10-27.md` are unaffected and
still open, and G6's configurable workflow definition still waits on which phases are mandatory.

## 17. Third addendum — what this batch built, and the answer to "is it fully implemented?"

Built since the second addendum, all of it read back against the running application rather than stated from
the plan:

- **G34** closed. A clearance line refused on a blank reason is refused by the server, and a reasonless refusal
  moves neither the line nor the clearance's overall status. The directional case — an approval with nothing
  typed still passes — is what proves the rule did not break the common act.
- **G42** closed on its testable half. A deactivated account is refused at the login screen by a case that then
  authenticates the same account once it is active again, so the pair cannot pass by the credentials failing.
  Cutting an already-open session is a policy answer and is item 2 on the 2026-10-29 sheet.
- **G33** closed. `admissions.rejectionReason` (varchar 500, nullable — a rejection filed before the column
  stays valid and simply shows no reason), refused under ten characters, typed into an opt-in box on the shared
  `CauseEffectModal`, printed on the rejected panel, and the screen's invented sentence
  `Requirement rejected / needs resubmission.` is deleted rather than reworded.
- **G43 opened by measuring, not by reading code, and closed the same way.** Desk acts wrote `now()` into columns
  the server calls `date`; Eloquent formats a `date` cast in the application's timezone, so the record filed the
  machine's day. Lane 50's PHP half could not see the shape at all (it reads `now()->toDateString()` and
  `whereDate(...)`, and answered 0 over those files), so lane 87 binds each write to the **migrations** instead:
  eight column names over forty-eight write sites, thirty-eight feeding a DATE column, all thirty-eight now
  answered from the school's day, and ten instants printed UNBOUND because §1.2 says their viewer's zone is right.

### Is the development prompt fully implemented? No.

Eleven register items came out of the sweep (G33 to G43). Five are closed — G33, G34, G39, G43 and
half of G42 — and the closure of G43 was measured twice over: the instrument that found it first reported the
generator's share at three writes when it held twenty-seven, because it printed one row per column name, and the
count a tool prints is a property of how it prints.
What remains divides honestly into two kinds:

- **One buildable now, unbuilt; one contained**: G36 (nothing stops a duplicate notice when a queued listener
  retries — it wants a unique key, so it waits on the decision to spend a migration), and G40, whose sorting
  mechanism and first wired queue landed on 2026-10-29 while 35 tables still offer no order; its pagination half is
  measured now rather than owed (§28.3 keeps
  G-40 open for exactly that reason).
- **Six waiting on the school's window**, each on `Documentation/School-Decision-Sheet-2026-10-29.md` with what
  the answer costs and how it comes back off: G35 (where a notice takes the desk), G42's session cut-off,
  G38 (who may read a clinical note, not just its outcome), G37 (the audit trail's action vocabulary),
  G33's fixed-list-versus-free-text half, G41 (whether a browser job belongs in the pipeline).

Plus G6 from the first pass (a configurable workflow definition) and the four items on the 2026-10-27 sheet.

### Evidence for this batch, read from the instruments named

- `tests/Feature/Admission/AdmissionRejectionReasonTest.php` declares seven cases (`#[Test]` counted in the file);
  the whole suite was then run twice: the first pass answered **944 passed, 2 failed** — the two failures were
  fixtures elsewhere that post `admission.reject` with no body — and after the fixture repair the gate answers
  **946 passed**, with `php artisan test --list-tests` discovering the same 946, so no case was stolen by an
  insertion. No assertion total is stated for the file, because an assertion total belongs to a run and not to
  the code (lane 58's rule, applied to this document).
- PHPStan: `[OK] No errors` whole-tree. Pint `--test`: PASS, 417 files. ESLint: silent at
  `--max-warnings=0`. Vite: `✓ built in 32.80s`. `ems:print-fidelity`: 8/8 rendered, no warning line.
- Authority document: passes 279, 279b, 280 and 280b. `sweep-docx-citations.php` answers 742 citations over
  162 files with 0 line problems and 1 blind spot (the quoted example that is a pointer by design);
  `lane60-ratio-claims.php --run` verdicts "every bound ratio, census figure and column count equals what the
  server answers"; `lane64-empty-table-inventory.php --verify` agrees with `COUNT(*)`; `lane59-instrument-counts.php`
  refuses until the lane 37 figure in this repository's own instructions is re-typed to what the sweep prints;
  OOXML `CLEAN`, `verify-docx` `CONSISTENT`, and real Word opens the hash-identical copy at 234 pages / 186 tables.
- `repo-file-line-citations.php`: 160 citations across 16 repository files and office sheets, 0 problems.
- Live `ems`: the migration ran after a verified dump (`C:\Users\ADMIN\ems-backups\ems-pre-g33-rejection-reason-20261029.sql`,
  1.4 MB, checked for `DROP DATABASE`/`CREATE DATABASE` before anything was pointed at the server); `admissions`
  still holds 4 rows and `rejectionReason` answers `varchar(500) YES NULL`.

### What this batch did not finish, stated as work and not as a pass

1. **The register cascade is written, and it caught a figure that was already stale.** Pass 281 put G-33 … G-43
   into §28.3 as five-column rows and moved §28.1's index range to `G-1 … G-43` and §29.4's family cell to the
   measured row total; pass 282 put the six school questions into §28.5 and moved §29.4's "Open decisions" cell
   and the prose that states the same figure in words. §29.4's family cell turned out to state **48 against the
   49 rows it counts before this batch touched it** — the same "count that stopped growing" defect §28.3 G-30
   exists to record, printed by the pass as its own finding rather than absorbed. §28.3's newest row also
   carries a verdict where its header promises an actor, which `lane61-who-owes.php` reads; that inconsistency
   is in the register, not in these passes, and is recorded for whoever re-measures it.
2. **The lane-70 queue reads aged.** This batch moved 32 citation numbers in `ClearanceController.php` and 21 in
   `AdmissionController.php`, so records keyed to the old ranges now report STALE. They were left stale rather
   than regenerated, because the generator's file-keyed fallback would attach prose written against the previous
   layout to lines nobody has opened since — the conservative direction is a row that reads unread.
3. **G33's harness was run, and two of its six mutants survived the first attempt.** They are recorded in
   `AGENTS.md` rather than smoothed out: the school-day case could not fail while the two clocks happened to
   agree, so it now freezes the instant and asserts the disagreement first; and the canned-sentence mutant was
   aimed at the rejection case, where a `??` can never fire — the fallback bites only on an approval, which is
   where the leg points now. `mutate-g33-rejection-reason-tests.php` ends `ALL KILLED: 6 leg(s), 0 bad` with a
   green control before and after and every touched file restored by sha1. The whole-suite run then caught two
   fixtures elsewhere in `tests/` (`ExamControllerTest`) that post `admission.reject` with no body at all; the
   fixtures were repaired, not the rule.
4. **G40 is contained, not closed** — one mechanism and two queues (Registrar, then Admission the same day); the
   pagination half was weighed the same day and no work queue is in it, so what is left is wiring the other 34 tables
   to `ListSort`; and G43 was closed after this
   sentence's first draft, by binding the write to the migrations rather than to a live database.
5. ~~**G34's five cases have no mutant harness.**~~ **Closed the same day.**
   `mutate-g34-clearance-reason-tests.php` answers `ALL KILLED: 4 leg(s), 0 bad` — requiredness dropped, the
   ten-character floor dropped, the requirement re-pointed at an approval, and the desk's own word not
   persisted, each killing its named case with a green control before and after. The register row was re-taken
   by pass 284, because a row that understates what has since been proved is still a false row. What the
   harness does **not** cover is stated in the row itself: the cross-office 403 case belongs to the policy, so
   no leg there would notice a policy widening.
