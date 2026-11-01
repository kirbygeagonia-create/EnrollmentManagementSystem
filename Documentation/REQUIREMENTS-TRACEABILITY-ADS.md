# ADS Requirements Traceability — SEAIT Enrollment Management System

**Source documents.** `ADS_Final_Project_Guide_EMS_updated.docx` (the authoritative guide, extracted to
`storage/app/scratch/ads-guide-flat.txt`) and `Enrollment_Management_System_Master_Implementation.md`
(the implementation prompt that restates it). Read the guide through
`php storage/app/scratch/lane155-read-ads-guide.php --json=storage/app/scratch/lane155-ads-population.json`, which
prints the population this file is built on: 24 problems, 106 guide questions, 12 concept rows, 8 presentation areas.

**Why this lives in `Documentation/` and not `docs/`.** The prompt prefers `docs/REQUIREMENTS_TRACEABILITY.md`.
This repository keeps every prose deliverable under `Documentation/`, and the citation gate
(`php storage/app/scratch/repo-file-line-citations.php`) reads that directory by name, so a new top-level `docs/`
folder would be invisible to the gates that keep a document honest. The deviation is stated here rather than
left silent.

**Every `Existing Implementation` line below was opened in the working tree on 2026-11-01.** Where a claim could
not be read from code it says so, and no requirement is marked Verified without a named test or probe.

---

## 1. Status summary

| ID | Problem (guide ¶) | Applicability | Status |
|---|---|---|---|
| P01 | Incomplete Enrollment Transaction (¶41) | Applies | Implemented, demonstration owed (T02) |
| P02 | Last Available Slot (¶51) | Applies | **Not built** — the capacity check runs outside the transaction and takes no lock |
| P03 | Duplicate Enrollment (¶61) | Applies | Partially — subject lines are guarded by a unique key; the active-seat rule is application code only |
| P04 | Incorrect Section Capacity (¶71) | Applies | Partially — counts are derived, never stored, so drift is impossible, but nothing enforces `count ≤ maxStudents` in the database |
| P05 | Simultaneous Student Updates (¶81) | Applies | **Not built** — no optimistic version, no lock, a silent overwrite is possible |
| P06 | Enrollment Cancellation (¶91) | Applies | Implemented, demonstration owed (T06) |
| P07 | Database Deadlock (¶101) | Applies | **Not built** — no deadlock error handling, no bounded retry, no stated lock order |
| P08 | Student Search Performance (¶111) | Applies | Partially — a composite name index exists but the search's `LIKE '%…%'` cannot use it |
| P09 | Enrollment History Performance (¶121) | Applies | Partially — the record page is set-based; the Registrar queue issues ≈5 queries per row |
| P10 | Concurrent Subject Registration (¶131) | Applies | **Not built** — same missing lock as P02; no concurrent test exists anywhere |
| P11 | Invalid Enrollment Data (¶141) | Applies | Implemented — foreign keys, NOT NULL, enums, `exists:` validation; CHECK constraints owed |
| P12 | Prerequisite Checking (¶151) | Applies | Implemented and pinned |
| P13 | Maximum Academic Load (¶161) | Partially — a band rule exists, a term unit cap does not | **Not built** (limit value is an open school question) |
| P14 | Faculty Teaching Load (¶171) | Partially — assignments are stored, no load limit is | Justified Partial with a controlled demonstration owed |
| P15 | Conflicting Class Schedules (¶181) | Applies | Implemented for rooms, officers and blocks; the student-side overlap across two blocks is owed |
| P16 | Transaction Failure During Enrollment (¶191) | Applies | Implemented, failure demonstration owed (T17) |
| P17 | Power Interruption During Enrollment (¶201) | Applies | **Not built** — no idempotency key, no rate limit on any POST, no "did my request commit" answer |
| P18 | Duplicate Student ID (¶212) | Applies | Implemented at the database — `uq_students_schoolid` |
| P19 | Accidental Deletion (¶223) | Partially — status-based cancellation, no restore path | Implemented for protection; restoration is an owner question |
| P20 | Reports During Active Enrollment (¶234) | Applies | **Not built** — no isolation level is set anywhere; a report reads across several auto-committed statements |
| P21 | Database Recovery (¶245) | Applies | **Not built** — backup and restore exist as commands in a guide, never as an executable procedure, and no drill has been run |
| P22 | Unauthorized Database Access (¶255) | Applies | Implemented and pinned |
| P23 | Heavy Enrollment Traffic (¶265) | Applies | Partially — indexes exist; nothing has been measured |
| P24 | Complete Enrollment-Day Challenge (¶276) | Applies | **Not built** — no concurrency or load harness exists in the repository |

---

## 2. Per-problem traceability

### P01 — Incomplete Enrollment Transaction (guide ¶41-50)

- **Source / objective.** Enrollment spans several subjects; one fails; the rest must not stand.
- **Applicability.** Applies. The build's enrollment act is `EnrollmentIssuer::issue()`.
- **Existing implementation.** `app/Services/EnrollmentIssuer.php:58` opens `DB::transaction(fn)` and returns the
  closure's value, so the `enrollments` insert and the workflow form written at `:76` and `:78` commit together or
  not at all. The Registrar's approve and drop acts wrap their subject lines, the status transition and the box
  signature in one transaction (`app/Http/Controllers/Registrar/RegistrarController.php:321`, `:368`). 38
  `DB::transaction` sites exist across `app/`.
- **Gap.** No test forces a failure *between* two subject writes and proves nothing survived.
- **Required solution.** None new for the mechanism; the demonstration is owed.
- **Affected components.** Backend, database.
- **Testing method.** `tests/Feature/…/EnrollmentTransactionAtomicityTest.php` (to be written): throw inside the
  transaction after the first line, assert `enrollments`, `enrolledsubjects`, `workflowsteps` and
  `enrollmentstatushistory` all answer zero.
- **Evidence.** Pending.
- **Status.** Implemented; T01/T02 not started.

### P02 — Last Available Slot (¶51-60)

- **Objective.** Two requests, one seat; the database must not let both have it.
- **Applicability.** Applies. A section is `blocks` and its room capacity is `rooms.capacity`.
- **Existing implementation.** `app/Http/Controllers/Blocking/BlockingController.php:469-472` counts distinct
  `enrollmentId` values that are not dropped, `:474` compares the sum against `$block->maxStudents` and `:475-477`
  throws, and only at `:495` does `DB::transaction` begin. The check is therefore outside the transaction and no
  row is locked: two simultaneous assigns both read the same count and both write. The only `lockForUpdate` in
  `app/` is `app/Services/PrintService.php:140`, on the document-number sequence.
- **Gap.** Concurrency control absent; enforcement is application-level only, which the guide refuses.
- **Required solution.** Lock the block row (`lockForUpdate`) as the transaction's first act, re-run the capacity
  count inside it, then write; take the room row after the block row in one fixed order so two paths cannot cross.
- **Affected components.** Backend, database.
- **Testing method.** T03 on a scratch MySQL schema with two real connections, one seat, two assign calls.
- **Evidence.** Pending.
- **Status.** Not started. **This is the repository's own open finding DI-5** in
  `Documentation/System-Design-Quality-Review.md:79`, recorded 2026-09-15 and never repaired.

### P03 — Duplicate Enrollment (¶61-70)

- **Objective.** The same request twice, including simultaneously, must not create two records.
- **Existing implementation.** Subject lines: `database/migrations/2026_08_09_1000000004_extend_enrolledsubjects_table.php:33`
  declares `uq_enrollment_subject_attempt` on (`enrollmentId`, `subjectId`, `attempt_number`) — a second identical
  line is refused by the server. The active-seat rule is code: `EnrollmentIssuer::seatHolder()` at
  `app/Services/EnrollmentIssuer.php:37-43` reads a non-dropped enrollment for a student and term, and a caller
  refuses when one exists. `studentassessments` has no unique key on `enrollmentId` (measured against
  `database/migrations` — no `unique(` names that table).
- **Gap.** The seat rule has no database-level enforcement, so a second route or a concurrent request bypasses it;
  a second fee sheet for one enrollment is likewise unguarded (the review document's DI-2, still open).
- **Required solution.** Add `uq_studentassessments_enrollment` and a stored generated column on `enrollments`
  that is non-null only for a status that holds a seat, with a unique index over (`studentId`, `termId`, that
  column) — MySQL ignores NULLs in a unique index, which is exactly the "a drop releases the seat" ruling, so
  re-enrollment stays legal without a trigger.
- **Affected components.** Database, backend.
- **Testing method.** T04 (sequential duplicate), plus a case that inserts over the HTTP stack and asserts the
  database, not the controller, is what refused.
- **Evidence.** Pending; the precondition is measured — `php storage/app/scratch/lane154-probe-duplicate-keys.php`
  answers 0 duplicate groups on live `ems` for every key this row proposes.
- **Status.** Partially implemented; the database half is not started.

### P04 — Incorrect Section Capacity (¶71-80)

- **Objective.** Counts and capacity must not disagree.
- **Existing implementation.** There is no stored counter to drift: the headcount is derived at read time
  (`app/Http/Controllers/Blocking/BlockingController.php:469-472`, and the tiles at `:103-105`). A dropped line is
  excluded by `where('status','!=',…Dropped)` at `:470`.
- **Gap.** Nothing proves `count ≤ maxStudents` holds for rows that were written by another route, and no
  reconciliation command reports a violation.
- **Required solution.** A rule in `ems:audit:record-states` that selects every block whose non-dropped distinct
  enrollment count exceeds `blocks.maxStudents`, so the invariant is weighed on demand rather than assumed; the
  P02 lock is what keeps it true at write time.
- **Affected components.** Database, tooling.
- **Testing method.** T05 (capacity reached refuses further enrollment) and a new audit rule case.
- **Evidence.** Pending.
- **Status.** Not started.

### P05 — Simultaneous Student Updates (¶81-90)

- **Objective.** Two authorized users editing one record must not silently overwrite.
- **Existing implementation.** None: no `version`, `lockVersion` or `revision` column exists in any migration
  (grepped `database/migrations`), and no enrollment write takes a lock.
- **Gap.** Lost-update is possible on the profile capture and on the Registrar's edit path.
- **Required solution.** Optimistic locking on the record the desk edits: a stored version stamped by the server
  into the payload, echoed by the form, and refused with a named conflict message when it no longer matches.
- **Affected components.** Database, backend, frontend.
- **Testing method.** T07 — two PATCHes carrying the same version; the second must be refused and must not write.
- **Evidence.** Pending.
- **Status.** Not started.

### P06 — Enrollment Cancellation (¶91-100)

- **Objective.** A multi-subject cancellation must be consistent and must not count as enrolled.
- **Existing implementation.** `RegistrarController::drop()` at `app/Http/Controllers/Registrar/RegistrarController.php:368`
  runs one transaction that sets the enrollment to `dropped` and every proposed or confirmed subject line to
  dropped; `app/Services/EnrollmentStateMachine.php:48-50` states the ruling in code ("A drop is terminal"), and
  the exclusion of dropped rows is read at `BlockingController.php:57,137,392,470,484,518,553`,
  `AssessmentController.php:191` and `app/Support/EnrollmentReadiness.php:133`.
- **Gap.** No test proves a mid-cancellation failure leaves nothing half-dropped.
- **Required solution.** None new; the demonstration is owed.
- **Affected components.** Backend, database.
- **Testing method.** T06 with an injected failure between the line update and the status write.
- **Evidence.** Pending.
- **Status.** Implemented; test not started.

### P07 — Database Deadlock (¶101-110)

- **Objective.** Prevent, detect, retry safely.
- **Existing implementation.** None. Grepping `app/`, `tools/`, `tests/` for `1213`, `1205`, `deadlock`,
  `lock wait timeout`, `retry` around writes answers nothing; the only deliberate duplicate-key catch is
  `app/Support/NoticeDelivery.php:32-37` on error 1062.
- **Gap.** No lock order is stated, no deadlock error is handled, no bounded retry exists.
- **Required solution.** One helper that (a) takes locks in one fixed order (block, then room, then enrollment),
  (b) catches 1213 and 1205, (c) retries a bounded number of times with jitter, and (d) refuses to retry anything
  whose effect is not idempotent — which is why P17's request key is a prerequisite for this row.
- **Affected components.** Backend, database.
- **Testing method.** T08 — a controlled two-connection deadlock on a scratch schema, plus a case proving the
  retry does not duplicate a committed effect.
- **Evidence.** Pending.
- **Status.** Not started.

### P08 — Student Search Performance (¶111-120)

- **Objective.** Search stays fast as `students` grows.
- **Existing implementation.** `database/migrations/2026_08_02_1000000005_create_students_table.php:35` creates the
  composite `idx_students_names (lastName, firstName)` and `:32` the unique `schoolIdNumber`; the search at
  `app/Http/Controllers/StudentController.php:40-43` applies `like "%{$search}%"` to four columns, which cannot
  use that index, and `:70` paginates 20. 51 leading-wildcard searches exist across 15 controllers.
- **Gap.** The index the schema carries is not the index the query uses; no `EXPLAIN` evidence exists.
- **Required solution.** Make the directory's name search sargable (prefix `LIKE 'term%'` for the surname, keeping
  the contains-match as an explicit narrower path) or add a `fullText` index and use `MATCH … AGAINST`; then
  measure `EXPLAIN` and timings before and after on a schema holding generated synthetic students, never real ones.
- **Affected components.** Database, backend.
- **Testing method.** T09 with a measured row count and the plan printed.
- **Evidence.** Pending.
- **Status.** Not started (index exists but is unreachable).

### P09 — Enrollment History Performance (¶121-130)

- **Objective.** One student's whole history from hundreds of thousands of rows.
- **Existing implementation.** `app/Http/Controllers/StudentController.php:90-107` eager-loads the record page's
  relations, and `app/Services/AcademicStandingService.php:177` sums `lectureUnits + labUnits` inside a set-based
  read rather than per row. `enrollments` is indexed on `studentId`, `courseId` and `termId`, plus the composite
  `idx_enrollments_term_status` at `database/migrations/2026_08_04_1000000052_add_composite_indexes_for_heavy_screens.php:24`.
- **Gap.** The Registrar queue loop at `app/Http/Controllers/Registrar/RegistrarController.php:97-106` calls the
  checklist per page row, and `app/Support/EnrollmentReadiness.php:65,109-111,144,163,183` each issue a query, so a
  20-row page costs roughly a hundred queries. No query-count ceiling guards it (the suite has exactly one such
  assertion, `tests/Feature/Print/PrintEnrollmentFormPayloadTest.php:206-227`).
- **Required solution.** Batch the checklist's reads for the page (one query per relation, keyed by the page's
  enrollment ids) and pin the count.
- **Affected components.** Backend, database.
- **Testing method.** T10 with `DB::enableQueryLog()` and an asserted ceiling, plus a volume rehearsal on a
  generated schema.
- **Evidence.** Pending.
- **Status.** Not started.

### P10 — Concurrent Subject Registration (¶131-140)

- Same missing lock as P02, and no test in the repository opens two connections at once (grepped `tests/` for
  `curl_init`, `pcntl_fork`, `Http::post`, `parallel` — zero files).
- **Required solution.** The P02 lock plus a concurrent harness; the harness is also P24's engine, so it is built
  once. **Status.** Not started.

### P11 — Invalid Enrollment Data (¶141-150)

- **Existing implementation.** The server holds 182 table constraints in live `ems`, of which 13 are unique
  (`php storage/app/scratch/lane154-probe-duplicate-keys.php` prints the reading beside its duplicate counts);
  foreign keys refuse an enrollment that names no student or course, `enrollments` columns are NOT NULL, statuses
  are MySQL enums, and the desk's requests validate `exists:` before write. The delete-side consequences are
  covered by `php storage/app/scratch/lane43-delete-guards.php`.
- **Gap.** No `CHECK` constraint expresses a range rule the guide names (capacity, units ≥ 0), and the seat and
  fee-sheet keys of P03 are missing.
- **Required solution.** Add the CHECKs the columns can honestly carry and the two unique keys of P03; keep the
  SQLite suite's escape hatch in every migration that writes raw MySQL.
- **Testing method.** T12 — a POST naming a non-existent subject, student and section must be refused, and the
  refusal must survive a route that skips validation.
- **Status.** Implemented for the existing constraints; the additions are not started.

### P12 — Prerequisite Checking (¶151-160)

- **Existing implementation.** The relationship is `curriculumsubjects.prerequisiteSubjectId`
  (`database/migrations/2026_08_02_1000000029_create_curriculumsubjects_table.php:15`, indexed `:20`, foreign key
  `:22`); the gate is `app/Support/EnrollmentReadiness.php:124-150`, which reads the student's satisfied subjects —
  including approved equivalencies — and refuses when a confirmed load sits behind an unmet prerequisite. The
  crediting decision that can satisfy one is `App\Models\Creditedsubjects::approved()`.
- **Status.** Implemented. **Evidence.** `tests/Feature/Enrollment/EnrollmentReadinessTest.php` and the credit
  cases in `tests/Feature/Evaluation/CreditEquivalencyDecisionTest.php`. T13 is therefore a re-read of an existing
  case, not new work.

### P13 — Maximum Academic Load (¶161-170)

- **Existing implementation.** Units are stored per subject (`subjects.lectureUnits`, `subjects.labUnits`,
  `decimal(3,1)`) and summed for standing at `app/Services/AcademicStandingService.php:177`. The evaluation screen
  enforces a per-band subject count (`Documentation/` §7's elective band min–max, refused in
  `app/Http/Controllers/Evaluation/EvaluationController.php`).
- **Gap.** There is no term unit ceiling anywhere, and the guide forbids inventing the school's number.
- **Required solution.** A configurable limit (a `settings` row per year level, read through the existing
  `config('settings.*')` path) that the enrollment transaction re-validates against the summed units of the
  non-dropped load, refusing when the setting is absent rather than assuming a value. The unresolved value goes on
  the school decision sheet.
- **Testing method.** T14 with the limit set in a fixture, plus a case proving an unset limit refuses instead of
  passing.
- **Status.** Not started; blocked on one school figure, not on code.

### P14 — Faculty Teaching Load (¶171-180)

- **Existing implementation.** Assignments are real: `schedules.instructorId`
  (`database/migrations/2026_08_02_1000000034_create_schedules_table.php:15`, indexed `:18`, foreign key to
  `staffusers.userId` `:23`), and `app/Support/TimetableConflicts.php:71` already refuses a meeting that
  double-books an instructor in one term.
- **Gap.** No instructor entity, no per-instructor unit or hour cap, and no screen that would show an overload.
- **Applicability.** Partially applies, as the guide itself anticipates ("If your system does not manage faculty
  assignments…"). The system records who teaches what; it does not manage a load limit, so a module must not be
  invented to satisfy a checkbox.
- **Required solution.** State the boundary honestly in the presentation, and give the equivalent demonstration: a
  SQL query over `schedules` joined to `subjects` that totals units per instructor per term, run against the demo
  data, with the cap rule left as what the school would have to set.
- **Testing method.** T15 marked **not executed**, with the reason: no load limit is defined, so there is no rule
  to violate yet.
- **Status.** Justified Partial.

### P15 — Conflicting Class Schedules (¶181-190)

- **Existing implementation.** `app/Support/TimetableConflicts.php` judges three questions — officer, room, and the
  block's own hour — inside one term, with half-open intervals so back-to-back is not a collision, and
  `ems:audit:record-states` carries the same two rules. Ruled and pinned by
  `tests/Feature/Timetable/…` and `AuditRecordStatesTest`.
- **Gap.** The guide's scenario is a *student* enrolled in two subjects whose times overlap. The block conflict is
  judged for the resource, and an assign refuses a block that already meets in another room at the same hour, but
  no path compares the requested block against the student's other confirmed blocks.
- **Required solution.** Extend the same helper with a student-side read (the enrollment's other non-dropped lines'
  schedules) and reuse the half-open interval rule, inside the P02 lock so two simultaneous assigns cannot slip a
  pair through.
- **Testing method.** T16.
- **Status.** Not started (the resource side is implemented).

### P16 — Transaction Failure During Enrollment (¶191-200)

- As P01: the mechanism exists (`EnrollmentIssuer.php:58`), the midway demonstration does not. A `try/catch` that
  swallows without rolling back is refused by the framework's own closure form, which is worth saying out loud in
  the defense. **Status.** Implemented; T17 demonstration not started.

### P17 — Power Interruption During Enrollment (¶201-211)

- **Existing implementation.** InnoDB durability and the transaction boundaries above. Nothing else: no
  idempotency column exists on `enrollments`, `enrolledsubjects` or `studentassessments`, and `routes/web.php`
  carries zero `throttle` middleware on any POST (only `LoginRequest` rate-limits,
  `app/Http/Requests/Auth/LoginRequest.php:47-82`).
- **Gap.** After an interruption, neither the desk nor the system can answer "did that request commit?", and a
  re-submit is only stopped by a read-then-write guard.
- **Required solution.** A request key minted before the POST and stored with a UNIQUE index, so a retry hits the
  constraint and the server answers with the record the first attempt created; plus a lookup route the desk can
  call with that key.
- **Testing method.** T18 — submit, kill the connection before the response, retry with the same key, assert one
  record and a legible answer.
- **Status.** Not started.

### P18 — Duplicate Student ID (¶212-222)

- **Existing implementation.** `uq_students_schoolid` at
  `database/migrations/2026_08_02_1000000005_create_students_table.php:32` is the database's own refusal, and the
  intake form validates uniqueness before submit. This is the guide's "the database must be the final authority",
  already true.
- **Gap.** The 1062 path is not turned into a desk-readable message on every intake route, and no test drives two
  simultaneous registrations.
- **Testing method.** T19 sequential and simultaneous.
- **Status.** Implemented at the database; the graceful-error half is not started.

### P19 — Accidental Deletion (¶223-233)

- **Existing implementation.** No model uses `SoftDeletes` and no migration declares a `deletedAt` column
  (grepped `app/` and `database/migrations`) — because the enrollment record is never deleted: cancellation is the
  `dropped` status, `app/Services/EnrollmentStateMachine.php:48-50` makes it terminal, and a hard delete of a
  student, enrollment, payment, clearance or admission row does not exist in `app/` or `routes/`. Money rows keep
  their history: `AccountingController` voids and refunds by status, never by deletion. `app/Observers/AuditLogObserver.php`
  records `created`, `updated` and `deleted` with actor, table, entity id, old and new values, ip and instant, and
  redacts a credential-looking value at write time.
- **Gap.** Restoration: a dropped enrollment cannot be reinstated, which is a ruling (the student comes back on a
  new record), not an oversight — so the honest answer is the ruling plus a demonstration that the audit trail and
  the prior history survive a drop and a re-enroll. Reference-data deletes remain hard deletes guarded by the
  delete-guard sweep.
- **Testing method.** T20 — drop, re-enroll, assert the history of both records and the derived count.
- **Status.** Protection implemented; restoration is an owner question recorded on the decision sheet, not a code
  gap.

### P20 — Reports During Active Enrollment (¶234-244)

- **Existing implementation.** None of the isolation question: grepping the repository for `ISOLATION LEVEL`,
  `READ COMMITTED`, `REPEATABLE READ`, `SERIALIZABLE`, `transaction_isolation` finds only `config/cache.php` and
  `composer.lock` false hits. `config/database.php:14` sets the session **time zone** and nothing about isolation.
  Every report screen (`RegistrarController::index`, `AccountingController::dailyReport` at `:360-404`,
  `DashboardController`) runs its reads outside any transaction, so a multi-query report can straddle two
  snapshots.
- **Required solution.** Run each report inside one transaction on a consistent snapshot, state the chosen level and
  why (MySQL's default REPEATABLE READ gives the snapshot at first read; the guide asks for a coherent point in
  time), keep it read-only so enrollment traffic is not blocked, and measure that it does not wait on locks.
- **Testing method.** T21 — a report whose first query is followed by a committed enrollment must print the number
  from before it, and the next report must show the new one.
- **Status.** Not started.

### P21 — Database Recovery (¶245-254)

- **Existing implementation.** Commands in prose: `Documentation/Demo-Day-Guide.md:289-307` (dump with
  `--single-transaction`, restore by redirect) and `Documentation/Offline-Presentation-Guide.md:89`; a validator
  that reads a dump (`tools/validate_demo_backup.php`) and a probe that counts its rows. The nightly backup job and
  the restore drill are **unchecked boxes** in `Documentation/First_Build_Plan.md:128` and `:187`. No binlog or
  point-in-time tooling exists.
- **Gap.** The guide's own warning applies: creating a backup file does not prove recovery. Nothing in this
  repository has ever restored a dump and compared the rows.
- **Required solution.** An executable pair: back up live `ems` into `C:\Users\ADMIN\ems-backups` (never into the
  repository), restore into a named scratch schema, verify per-table counts and a checksum of a few ordered selects,
  and refuse to run if the target is `ems` itself. Read and print the server's durability settings
  (`innodb_flush_log_at_trx_commit`, `sync_binlog`) so the durability claim in ACID is a measurement.
- **Testing method.** T22 — the drill, with its printed before/after counts as the evidence.
- **Status.** Not started.

### P22 — Unauthorized Database Access (¶255-264)

- **Existing implementation.** Server-side, per record: 171 `$this->authorize()` calls against 15 policies
  registered in `app/Providers/AuthServiceProvider.php`, so the route is not the only gate; 96 named permissions
  over 17 roles from `RbacSeeder`; `Staffusers::$hidden` and `Students::$hidden`
  (`app/Models/Students.php:25-27`) keep a credential hash out of every payload; `AuditLogObserver` records the
  actor and redacts secrets at write time; sessions are `database` driver, HttpOnly, Lax
  (`config/session.php:21,185,202`); CSRF is the framework default with the SPA sending `X-XSRF-TOKEN`. The college
  boundary is one class, `App\Support\CollegeScope`.
- **Gap.** Frontend hiding is never the protection, and the repository proves it: `PermissionMatrixTest` asserts
  each route's right and `AbilityNameCollisionTest` refuses a gate ability wearing a permission's name. The honest
  remaining risks are the privacy gaps the requirement matrix already registers (a clinical note readable without
  a field-level rule, and who may see it — an owner question).
- **Testing method.** T23 — a matrix of role × route refusals, which already exists; the ADS evidence pack quotes
  it rather than duplicating it.
- **Status.** Implemented and pinned.

### P23 — Heavy Enrollment Traffic (¶265-274)

- **Existing implementation.** Index work has been done before: `add_composite_indexes_for_heavy_screens` and
  `add_performance_indexes` add composites over the columns the desks filter, and the queue lists paginate.
- **Gap.** Nothing is measured. The guide requires performance improvements proved, not assumed, and the two
  largest known costs are P08's unusable name index and P09's per-row checklist.
- **Required solution.** One measured bench: a generated synthetic schema at a stated row count, the same query set
  run before and after each change, timings and plans printed.
- **Testing method.** T24's non-destructive half. **Status.** Not started.

### P24 — Complete Enrollment-Day Challenge (¶276-290)

- **Existing implementation.** Nothing concurrent exists in the repository: no test opens two connections, no
  script drives parallel requests, and `pcntl` appears only as a CI PHP extension
  (`.github/workflows/ci.yml:23,211`).
- **Required solution.** An integration harness that builds a synthetic enrollment-day schema, drives N parallel
  registrar assigns through the HTTP stack, and then checks the ten properties the prompt lists: no block over
  capacity, no prohibited duplicate, no load breach, no bypassed prerequisite, no accepted schedule conflict, counts
  accurate, no partial records from failed transactions, committed records still there, authorization enforced, the
  database up and consistent. Run at a practical scale, state the limitation, and carry the 1,000-request design.
- **Status.** Not started. **This is the deliverable the defense question is asked about.**

---

## 3. Required database concepts (guide ¶291-319, 12 rows)

| Concept (guide ¶) | Status | Where | Evidence owed |
|---|---|---|---|
| Transactions (¶295) | Implemented | 38 `DB::transaction` sites; `EnrollmentIssuer.php:58` | T01, T02 |
| COMMIT / ROLLBACK (¶297) | Implemented, not demonstrated | same | a printed failed-commit and a committed pair |
| ACID Properties (¶299) | Partial | Atomicity and Consistency by the above; Isolation by P02/P20 work; Durability by P21 | four separate demonstrations |
| Concurrency Control (¶301) | Not implemented | P02, P03, P05, P10 | T03, T07, T11 |
| Locking (¶303) | One site only | `PrintService.php:140` | the enrollment locks this batch adds |
| Deadlock Handling (¶305) | Not implemented | P07 | T08 controlled deadlock |
| Database Constraints (¶307) | Implemented | 182 constraints, 13 unique on live `ems` | the two new keys of P03 |
| Indexing (¶309) | Implemented, partially unusable | `idx_students_names`, `idx_enrollments_term_status` | `EXPLAIN` before/after |
| Query Optimization (¶311) | Not measured | P08, P09 | timings and plans |
| Data Integrity (¶313) | Implemented | state machine, `ems:audit:record-states`, lane 43 delete guards | the new capacity rule |
| Database Security (¶315) | Implemented | policies, RBAC, hidden hashes, audit redaction | T23 matrix |
| Recovery (¶317) | Documented only | `Demo-Day-Guide.md:289-307` | the restore drill, T22 |

No concept is marked Implemented because a document discusses it.

---

## 4. Presentation requirements (guide ¶320-354, 8 areas)

1. **Database Design** — ERD, tables and relationships, keys. This repository already carries the schema census in
   `Documentation/EMS Complete Documentation.docx` §24 and the key inventory in §23.6.1; the ADS pack needs an ERD
   artefact, which does not exist yet and is owed.
2. **Transactions** — one meaningful transaction (exists), COMMIT and ROLLBACK, a failure scenario with the
   observed result (owed).
3. **ACID** — four properties, and the guide names `FOR UPDATE` for isolation and a backup method for durability,
   which is exactly P02 and P21.
4. **Concurrency** — a simultaneous enrollment scenario, the chosen strategy, testing evidence (all owed).
5. **Deadlock** — a possible scenario and the handling strategy (owed).
6. **Indexing** — indexes, why those columns, plan or performance evidence (the first two exist, the third is owed).
7. **Testing** — scenario, expected, actual, evidence (this file's §5 and the test documents).
8. **Workflow and Applicability** — state the workflow, justify each N/A or Partial. The build's workflow is
   registrar-assisted and office-assisted across nine desks — there is no student login (public `/register` is
   removed and `students` is deliberately not notifiable), which the guide allows, and each Partial above carries
   its reason.

---

## 5. Test map T01-T24

The prompt's table is the queue this document answers to. Today, T12, T13, T16, T19, T23 overlap cases that already
exist in the suite (`EnrollmentReadinessTest`, `PermissionMatrixTest`, `Store` and intake cases) and each will be
re-pointed to the named existing case rather than duplicated; the rest are new. Every row of this table will carry
the ten fields the prompt demands — description, preconditions, test data, steps, expected, actual, pass or fail,
logs, evidence — in `Documentation/TESTING-AND-VERIFICATION-ADS.md`, and a test that cannot run will say so with its
reason rather than being left off the list.

| Test | Scenario | Exists today? |
|---|---|---|
| T01 | multi-subject commit | no |
| T02 | failure mid-commit | no |
| T03 | two requests, one slot | no |
| T04 | duplicate enrollment | no |
| T05 | capacity reached | no |
| T06 | multi-subject cancellation | no |
| T07 | concurrent student edit | no |
| T08 | controlled deadlock | no |
| T09 | search at volume | no |
| T10 | history retrieval | no |
| T11 | popular-section contention | no |
| T12 | invalid references | yes, by `exists:` validation cases |
| T13 | missing prerequisite | yes, `EnrollmentReadinessTest` |
| T14 | load exceeded | no |
| T15 | faculty load | not executed — no limit is defined (P14) |
| T16 | schedule conflict | partially, resource side |
| T17 | DB exception mid-flow | no |
| T18 | interruption and retry | no |
| T19 | duplicate student id | yes, intake validation |
| T20 | deletion and restoration | no |
| T21 | report during enrollment | no |
| T22 | backup and restore | no |
| T23 | unauthorized access | yes, `PermissionMatrixTest` and policy cases |
| T24 | high-volume challenge | no |

---

## 6. What remains, stated plainly

Nine problems need new code (P02, P03's database half, P04's rule, P05, P07, P09, P13, P15's student side, P17, P20,
P21, P23, P24), four need a demonstration of something that already exists (P01, P06, P16, P18), one is a justified
Partial (P14), one is an owner ruling recorded rather than a defect (P19's restoration), and the presentation pack
owes an ERD. Two inputs genuinely wait on the school and are not hidden behind a default: the term unit ceiling
(P13) and any faculty load cap (P14). Every figure in this file is either read from the code on the date above or
printed by `lane154` and `lane155`; none is carried forward from a previous day.
