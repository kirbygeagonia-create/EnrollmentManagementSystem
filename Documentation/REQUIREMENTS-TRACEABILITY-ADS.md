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

**The guide's own questions are answered elsewhere, in the guide's own frame.** This register judges each
problem's status; `Documentation/GUIDE-PROBLEM-ANSWERS-ADS.md` answers every item the guide asks — its 106
guide questions, the Applicability mark and the N/A line of each of the 24 problems, the 12 concept rows of
part IV, the 25 presentation sub-items of part V, the defense question of part VI and the four fill-ins of
part VII. The two populations were read by two instruments and agree: `lane155` prints "24 problems, 106
guide questions, 12 concept rows, 8 presentation areas", and
`php storage/app/scratch/lane195-count-guide-answers.php` prints the same census as its own denominator and
refuses until each item carries an answer — proved by `php storage/app/scratch/mutate-lane195-guide-answers.php`.
Applicability here and there is one answer, not two: the counter compares each mark against this register's
verdict word and reports `DISAGREES-REGISTER`.

---

## 1. Status summary

| ID | Problem (guide ¶) | Applicability | Status |
|---|---|---|---|
| P01 | Incomplete Enrollment Transaction (¶41) | Applies | Implemented, demonstration owed (T02) |
| P02 | Last Available Slot (¶51) | Applies | Built and measured 2026-11-01 — block row locked inside the transaction, count re-taken under it, room locked second; HEAD overbooked 2-of-1, the fix refused one writer per pair |
| P03 | Duplicate Enrollment (¶61) | Applies | Built — subject lines guarded since the schema was written, the fee sheet by `uq_studentassessments_enrollment` since 2026-11-01, and the active seat by `uq_enrollments_active_seat` over the VIRTUAL `seatKey`: the key was added the same day, **withdrawn the same day** after CI showed test files writing two active seats for one student and term, and returned the same day after the census found eight such files and each was repaired and rehearsed on a scratch MySQL schema. `seatHolder()` stays the desk's guard on both drivers; the key is MySQL-only because SQLite compiles no generated `ALTER` |
| P04 | Incorrect Section Capacity (¶71) | Applies | Built and pinned — count weighed against `maxStudents` by the assign guard and by the audit's twentieth rule; the room-level question stays open by name |
| P05 | Simultaneous Student Updates (¶81) | Applies | **Built** — `students.rowVersion` is compared before the profile write, a mismatch is refused naming both numbers and writing nothing, and a successful save spends exactly one version |
| P06 | Enrollment Cancellation (¶91) | Applies | Implemented, demonstration owed (T06) |
| P07 | Database Deadlock (¶101) | Applies | Built for the assign path — one lock order, `RetryOnDeadlock` on 1213/1205 only, and the deadlock was observed on the school's server, not assumed |
| P08 | Student Search Performance (¶111) | Applies | Measured, not repaired — the leading-wildcard search scans ~98,800 index rows where a prefix touches ~1,111 (plans, both runs agreeing); timings move between runs and are quoted from the captured file; the semantic choice waits on the school |
| P09 | Enrollment History Performance (¶121) | Applies | Partly built, both halves measured (2026-11-02) — every history read is an index seek, but a 20-row page costs 40 queries against 2 batched (that is `lane164`'s reading of its own run). Re-measured 2026-11-02 by `lane191-attribute-registrar-queries.php` against live `ems` through the HTTP stack, which names the call sites the older figure could only count: the queue listed **5 rows** and **5.0 statements repeated per row**, from `EnrollmentReadiness` lines 148 (`curriculumsubjects`), 160 (`curriculums`), 187 (best grade per subject) and 197 (approved credits), plus `Gradescale` line 36 (the passing ceiling). Two of those five are **school-wide constants re-read once per record**, so the cheapest half of the repair is a per-request memoize inside the class both the list and the record page already call — no second definition of any gate. Capture `storage/app/scratch/lane191-out.txt`. The two **school-wide** reads — the passing ceiling and the curriculum catalogue — are now resolved once per page and threaded in as optional arguments that default to the fresh read, the shape `clearanceVerdict()` already used for the window, and `lane191` re-run answers **3.0 per row from three sites** (`EnrollmentReadiness` lines 154, 218, 228). Pinned by two new `PrerequisiteGateTest` cases: the preloaded answer must equal the fresh answer in both directions, and the scale and catalogue must be read twice on the fresh path and **zero** times when handed in. Half 2 — batching those three by `enrollmentId` — stays open as task #45 |
| P10 | Concurrent Subject Registration (¶131) | Applies | **Built** (2026-11-01) — measured first, then repaired on the same instrument. `lane190-probe-subject-line-race.php` releases two children at ONE enrollment with ONE identical payload on a scratch clone of live `ems`, each calling the real `proposeSubjects()`. Before: 10 writers gave 6 proposals and **4 unretried `1213` deaths**, every pair still settling on exactly the payload's 6 rows with 0 duplicate `(enrollmentId, subjectId, attempt_number)` — the data was protected by `uq_enrollment_subject_attempt` and the transaction; the desk was not. The act now takes `Enrollments::…->lockForUpdate()` as its first statement inside the transaction, re-reads the model the lock made authoritative, runs inside `RetryOnDeadlock`, and moves the record only when it has not already been moved. After: **10 proposed, 0 deadlock, 0 driver error, 0 duplicates** (exit 0). The middle reading is why the last branch exists: lock and retry alone answered 5 proposals and 5 `Cannot transition enrollment from 'evaluated' to 'evaluated'` throws |
| P11 | Invalid Enrollment Data (¶141) | Applies | Implemented — foreign keys, NOT NULL, enums, `exists:` validation; CHECK constraints owed |
| P12 | Prerequisite Checking (¶151) | Applies | Implemented and pinned |
| P13 | Maximum Academic Load (¶161) | Partially — the unit arithmetic exists, no load rule is enforced anywhere in `app/` | **Not built** (limit value is an open school question; the year-level band the row once cited is not in the file that citation named) |
| P14 | Faculty Teaching Load (¶171) | Partially — assignments are stored, no load limit is | Justified Partial with a controlled demonstration owed |
| P15 | Conflicting Class Schedules (¶181) | Applies | Implemented for rooms, officers and blocks; the student-side overlap across two blocks is owed |
| P16 | Transaction Failure During Enrollment (¶191) | Applies | Implemented, failure demonstration owed (T17) |
| P17 | Power Interruption During Enrollment (¶201) | Applies | **Built** — `enrollments.requestKey` names the act, `EnrollmentIssuer::issue()` answers the record that already exists, and a UNIQUE index refuses a second row for one key on both drivers |
| P18 | Duplicate Student ID (¶212) | Applies | Implemented at the database — `uq_students_schoolid` |
| P19 | Accidental Deletion (¶223) | Partially — status-based cancellation, no restore path | Implemented for protection; restoration is an owner question |
| P20 | Reports During Active Enrollment (¶234) | Applies | Built and measured 2026-11-01 — the daily collection report takes both of its `payments` reads inside one transaction, so InnoDB's default REPEATABLE READ fixes one read view for the sheet; the isolation level itself is deliberately unset |
| P21 | Database Recovery (¶245) | Applies | Built and executed 2026-11-01 — `tools/ems-backup-drill.php` restored a fresh dump into a scratch schema, compared 64 tables row for row, three ordered digests MATCHES, and read the durability variables from the server |
| P22 | Unauthorized Database Access (¶255) | Applies | Implemented and pinned |
| P23 | Heavy Enrollment Traffic (¶265) | Applies | Built and measured 2026-11-01 — the queries are weighed on 100,000 students and 507,420 subject lines, and `lane172` ran 1,000 acts through ten real processes at 69 per second with every integrity question read back from the server |
| P24 | Complete Enrollment-Day Challenge (¶276) | Applies | Built and measured 2026-11-01 — 1,000 issues, 10 workers, one contended seat (1 winner, 9 refusals) and five drop-vs-issue races, ending with no student holding two active seats and no orphan form; the lost-update and report-isolation halves stay with P05 and P20 |

---

## 2. Per-problem traceability

### P01 — Incomplete Enrollment Transaction (guide ¶41-50)

- **Source / objective.** Enrollment spans several subjects; one fails; the rest must not stand.
- **Applicability.** Applies. The build's enrollment act is `EnrollmentIssuer::issue()`.
- **Existing implementation.** `app/Services/EnrollmentIssuer.php:109` opens `DB::transaction(fn)` and returns the
  closure's value, so the `enrollments` insert and the workflow form written at `:76` and `:78` commit together or
  not at all. The Registrar's approve and drop acts wrap their subject lines, the status transition and the box
  signature in one transaction (`app/Http/Controllers/Registrar/RegistrarController.php:330`, `:368`). 38
  `DB::transaction` sites exist across `app/`.
- **Gap.** No test forces a failure *between* two subject writes and proves nothing survived.
- **Required solution.** None new for the mechanism; the demonstration is owed.
- **Affected components.** Backend, database.
- **Testing method.** T01, executed 2026-11-02 by two cases in
  `tests/Feature/Evaluation/RetakeAttemptTest.php` — `a_multi_subject_commit_writes_every_line_it_names` (a
  two-subject proposal writes two lines and exactly one `evaluated` history row) and
  `a_refused_proposal_leaves_the_existing_load_in_place` (a proposal refused for a duplicate subject leaves the
  line count and the status where they were). The forced throw *inside* the transaction after the first line,
  asserting `enrollments`, `enrolledsubjects`, `workflowsteps` and `enrollmentstatushistory` all answer zero, is
  still owed as T02; `php storage/app/scratch/mutate-g-t01-subject-commit.php` kills the two delivered cases by
  planting exactly those two defects (insert only the first line; disable the duplicate guard).
- **Evidence.** `storage/app/scratch/lane189-suite.log` and the delivered run recorded below; the case pair and
  its falsifier are in the two files named a line above.
- **Status.** Implemented and demonstrated for the commit half (T01); T02's forced-rollback print is owed.

### P02 — Last Available Slot (¶51-60)

- **Objective.** Two requests, one seat; the database must not let both have it.
- **Applicability.** Applies. A section is `blocks` and its room capacity is `rooms.capacity`.
- **Existing implementation.** Built 2026-11-01. `app/Http/Controllers/Blocking/BlockingController.php:471-473`
  de-duplicates the requested ids and sorts them into one canonical order; `:483` wraps the act in
  `App\Support\RetryOnDeadlock::run()`; `:484` opens the transaction; `:485` locks the block row with
  `lockForUpdate()` as the transaction's first act; `:488-491` re-counts distinct non-dropped `enrollmentId` values
  *under* that lock; `:492-496` refuses above `maxStudents`; `:500` then locks the room row — a fixed order, block
  before room — and `:502-510` counts and refuses the room the same way. Before this batch the count ran outside the
  transaction and no row was locked, and the only `lockForUpdate` in `app/` was
  `app/Services/PrintService.php:140` on the document-number sequence.
- **Gap.** The application half is closed. The database still holds no rule that refuses a 41st seat if a future
  writer bypasses this path, which is P04's generated-column proposal (task #31) rather than this row.
- **Required solution.** As above, delivered: lock, re-count under the lock, one fixed order, bounded retry of only
  the two conditions that mean "somebody held it first".
- **Affected components.** Backend, database.
- **Testing method.** T03 over two real MySQL connections on a scratch clone, and the shape guards on SQLite.
- **Evidence.** `php storage/app/scratch/lane168-probe-last-seat-race.php --pairs=3` measured both sides on the
  school's server: HEAD's act ran 3 pairs to **5 assigns, 0 refusals, a block holding 2 seats against
  `maxStudents` 1**, and one pair ended in a real `SQLSTATE[40001] 1213` deadlock that reached the desk as a 500;
  the locked act ran the same 3 pairs to **3 assigns, 3 `capacity` refusals, 0 deadlocks, max 1 seat**, and the
  third pass (working bytes restored) matched the first. `tests/Feature/Blocking/BlockCapacityAtomicityTest.php`
  pins the shape (lock inside the transaction, both rows locked, sorted ids, dropped rows not consuming a seat)
  and the retry helper's boundaries, proved by
  `php storage/app/scratch/mutate-g-ads-capacity-tests.php`, which plants one defect per promise, prints its own leg
  total instead of having one written here, restores every file it touched by hash, and refuses when a needle matches
  nothing rather than reporting a kill it did not land.
- **Status.** Built and measured 2026-11-01. **This closes the repository's own open finding DI-5** in
  `Documentation/System-Design-Quality-Review.md:98`, recorded 2026-09-15 and open for six weeks.

### P03 — Duplicate Enrollment (¶61-70)

- **Objective.** The same request twice, including simultaneously, must not create two records.
- **Existing implementation.** Subject lines: `database/migrations/2026_08_09_1000000004_extend_enrolledsubjects_table.php:33`
  declares `uq_enrollment_subject_attempt` on (`enrollmentId`, `subjectId`, `attempt_number`) — a second identical
  line is refused by the server. The active-seat rule is code: `EnrollmentIssuer::seatHolder()` at
  `app/Services/EnrollmentIssuer.php:39-45` reads a non-dropped enrollment for a student and term, and a caller
  refuses when one exists. `studentassessments` has no unique key on `enrollmentId` (measured against
  `database/migrations` — no `unique(` names that table).
- **What was delivered, and what was taken back out.** `uq_studentassessments_enrollment` on
  `studentassessments(enrollmentId)` is on the schema, portable to the suite's SQLite, and closes the review
  document's DI-2. `uq_enrollments_active_seat` over (`studentId`, `termId`, a VIRTUAL generated `seatKey`) was added
  the same morning — measured, not guessed: STORED answers `1215 Cannot add foreign key constraint` because rebuilding
  a table eleven other tables point at is what a STORED change does, while VIRTUAL adds in place, takes the index, and
  keeps the NULL-means-no-seat behaviour that makes a drop release the seat without a trigger.
  **It was withdrawn the same day** by
  `database/migrations/2026_11_01_1600000000_withdraw_the_active_seat_key_until_the_fixtures_are_repaired.php`,
  because CI's MySQL 8 job — the only engine where the key exists at all — refused test files that create two
  ACTIVE enrollments for one student in one term. `RetakeAttemptTest`'s comment says the second record simulates "a
  retake in a later term" and its helper hands both rows the same term; the constraint was right and the fixtures
  were not. **It was returned the same day**, by
  `database/migrations/2026_11_01_1900000000_return_the_active_seat_key_now_that_fixtures_hold_one_seat_per_term.php`,
  after the census that reads every call site in `tests/` — `storage/app/scratch/lane187-census-fixture-seats.php`,
  proved by `storage/app/scratch/mutate-lane187-provenance.php` — answered **eight** files rather than the six CI
  happened to reach first, each was repaired to name a later term, drop the prior seat, or take a second student, and
  the repaired files rehearsed green on a scratch MySQL schema with `DB_CONNECTION` pinned (lane 87's rule). The
  return migration runs a duplicate guard before the `ALTER` and names the student and term of any pair it finds, so
  it refuses rather than half-applies. The SQLite suite remains structurally blind to this key, which is why the
  census is the pre-flight and not the suite.
- **Affected components.** Database, backend.
- **Testing method.** T04 (sequential duplicate), plus a case that inserts over the HTTP stack and asserts the
  database, not the controller, is what refused.
- **Evidence.** `php storage/app/scratch/lane154-probe-duplicate-keys.php` answered 0 duplicate groups on live `ems`
  for every key this row proposed — the precondition for adding a constraint additively rather than repairing data
  first. `tests/Feature/Enrollment/DatabaseLevelEnrollmentRulesTest.php` pins the fee-sheet refusal on both drivers,
  asserts the seat key **where the driver can build it and its absence where it cannot** (MySQL refuses a second
  active seat by name and releases it on a drop; SQLite reports no such index, and the case says out loud that no
  database refusal is being claimed there rather than skipping), and pins the application predicate on the seat's real
  path: held, then released by a drop, then taken again.
- **Status.** Built. The fee sheet and the seat are both server-enforced on the school's engine, the seat additionally
  by `EnrollmentIssuer::seatHolder()` on every desk path, and the SQLite suite enforces the seat in php alone because
  it compiles no generated `ALTER`. The review document's DI-2 is closed; DI-5 was closed by P02; DI-1's seat family is
  closed at the database layer on the driver that can hold the key.

### P04 — Incorrect Section Capacity (¶71-80)

- **Objective.** Counts and capacity must not disagree.
- **Existing implementation.** There is no stored counter to drift: the headcount is derived at read time — the
  roster tiles at `app/Http/Controllers/Blocking/BlockingController.php:103-106` and the write guard's own count at
  `:488-491`, both excluding dropped lines (`:58` and `:489`), both counting *distinct* enrollments because one
  student carries several subject rows. A dropped line is therefore not a held seat on either surface.
- **Gap.** A room is not weighed as a room. `:502-505` counts the rows stamped with **one** `scheduleId`, so when ten
  sections share a hall the check never sees the other nine — it enforces "this section fits this room", not "this
  room's total load fits". That is registered here rather than silently "fixed", because making it a room-level sum
  would refuse data the app itself produces today; it is a school question about what `rooms.capacity` means.
  Nothing proved the block invariant held for rows written by another route either, until this batch.
- **Required solution.** A rule in `ems:audit:record-states` that selects every block whose non-dropped distinct
  enrollment count exceeds `blocks.maxStudents`, so the invariant is weighed on demand rather than assumed; the
  P02 lock is what keeps it true at write time.
- **Affected components.** Database, tooling.
- **Testing method.** T05 (capacity reached refuses further enrollment) and a new audit rule case.
- **Evidence.** Delivered 2026-11-01 as the command's twentieth rule, `blockOverCapacity()` at
  `app/Console/Commands/AuditRecordStates.php:453-464` with its entry at `:150-153`. It uses the guard's definition
  of "held" rather than a second one. Measured before it was written: live `ems` answers **0** blocks over their own
  `maxStudents`, so the rule joins the standing gate green rather than turning it red with pre-existing damage.
  `php artisan ems:audit:record-states` now prints `Rules run: 20` from its own tally, and
  `the_audit_names_a_block_holding_more_students_than_it_declares` in
  `tests/Feature/Blocking/BlockCapacityAtomicityTest.php` plants the over-capacity row directly (the desk path can no
  longer produce it) and requires the rule's FAIL line, then drops the seat and requires the same rule's `ok` line.
  `php storage/app/scratch/mutate-g-ads-capacity-tests.php` kills both halves — dropping the dropped-row filter and
  moving the comparison to `>=` each make that case go red. T05 itself is answered by
  `a_request_larger_than_the_seats_left_seats_nobody`, which also proves the two seats a refused request left behind
  are still available.
- **Status.** Write guard and read reconciliation both built and pinned; the room-level question stays open by
  decision and is named above rather than counted as done.

### P05 — Simultaneous Student Updates (¶81-90)

- **Objective.** Two authorized users editing one record must not silently overwrite.
- **Existing implementation.** Now: `students.rowVersion` (unsignedInteger, NOT NULL, default 1) added by
  `database/migrations/2026_11_01_1700000000_add_row_version_to_students_table.php`, read by the profile screen and
  compared in `EvaluationController::captureProfile()` before any write. Before this batch there was none: no
  `version`, `lockVersion` or `revision` column existed in any migration.
- **Gap that remains.** The version is on `students` alone. A desk holding two *different* records — the address rows
  and the guardian rows, which are separate tables the same screen edits — can still overwrite one another, and the
  Registrar's edit path does not touch the person record, so it is unaffected rather than protected.
- **Required solution.** Delivered as the guide asks: a stored version stamped by the server into the payload
  (`EnrollmentProfileForm.jsx` puts `student.rowVersion` on the wire, so it is the record's number, never a typed
  field), echoed back, and refused with a named conflict message when it no longer matches. The column is outside
  `$fillable` on purpose: a client that could set its own version could walk past the check.
- **Affected components.** Database, backend, frontend.
- **Testing method.** T07 — two saves carrying the same version; the second is refused and writes nothing. Pinned by
  four cases in `tests/Feature/Evaluation/ProfileCaptureTest.php` and falsified by
  `storage/app/scratch/mutate-p05-version-tests.php` (comparison removed, bump removed, `required` downgraded to
  `nullable`, refusal stripped of its message, column made fillable — each killed by the case that names it).
- **Evidence.** `Tests: 11 passed` for that file locally; `storage/app/scratch/lane189-suite.log` carries the
  delivered whole-suite reading that contains it — `ProfileCaptureTest` present, `Tests: 1224 passed (7656
  assertions)`, duration 2212.95 s. (The log name this cell first carried belonged to lane 181, which left two
  HEAD exports and no log at all, and the delivered run for its cases is the lane189 capture.
  Found by `lane194-ads-artefact-citations.php`, which is the reason the correction names a path it can also prove
  is tracked.) A schema default is not returned to a model that was just created, which is why
  `Students` now declares `protected $attributes = ['rowVersion' => 1];` — without it the in-memory object posted a
  null version and the guard refused a screen that had done nothing wrong.
- **Status.** Not started.

### P06 — Enrollment Cancellation (¶91-100)

- **Objective.** A multi-subject cancellation must be consistent and must not count as enrolled.
- **Existing implementation.** `RegistrarController::drop()` at `app/Http/Controllers/Registrar/RegistrarController.php:377`
  runs one transaction that sets the enrollment to `dropped` and every proposed or confirmed subject line to
  dropped; `app/Services/EnrollmentStateMachine.php:48-50` states the ruling in code ("A drop is terminal"), and
  the exclusion of dropped rows is read wherever seats are counted — the roster's own closure at
  `BlockingController.php:57-58` and the write guard at `:488-491`, with the same clause repeated at every other
  count site in that controller. The numbers of those further sites are deliberately not listed: a comma list of
  line numbers is judged only on its FIRST number by the citation gate, so the rest age unseen, and this batch's
  insertion aged all seven of them by one to nineteen rows while the gate printed `0 problem(s)`. See also
  `AssessmentController.php:191` and `app/Support/EnrollmentReadiness.php:139`.
- **Gap.** No test proves a mid-cancellation failure leaves nothing half-dropped.
- **Required solution.** None new; the demonstration is owed.
- **Affected components.** Backend, database.
- **Testing method.** T06 with an injected failure between the line update and the status write.
- **Evidence.** Pending.
- **Status.** Implemented; test not started.

### P07 — Database Deadlock (¶101-110)

- **Objective.** Prevent, detect, retry safely.
- **Existing implementation.** Built 2026-11-01. `app/Support/RetryOnDeadlock.php` is the one statement of the rule:
  `run()` at `:38-66` retries only `self::DEADLOCK` (1213, `:27`) and `self::LOCK_WAIT_TIMEOUT` (1205, `:29`),
  `codeOf()` at `:69-74` reads `errorInfo[1]` before falling back to `getCode()` because the two drivers word a
  refusal differently, and exhaustion raises a `ValidationException` at `:54-59` whose sentence says
  *Nothing was saved* — a desk told to retry without being told the record is unchanged will assume it was.
  The lock order lives at `app/Http/Controllers/Blocking/BlockingController.php:485` (block) then `:500` (room),
  with the requested ids sorted at `:472` so two overlapping assigns take the enrollment rows in one order.
  Before this batch, grepping `app/`, `tools/`, `tests/` for `1213`, `1205`, `deadlock` or a retry around writes
  answered nothing; the only deliberate driver-code catch was `app/Support/NoticeDelivery.php:32-37` on 1062.
- **Gap.** The retry is scoped to one act. Every other write path still has no lock order and no retry, and the
  helper's own docblock states the condition under which wrapping is safe (one transaction, fully rolled back)
  rather than letting any caller borrow it blind.
- **Required solution.** As delivered for the Blocking path; extend per path only where a path both counts a
  shared resource and writes it.
- **Affected components.** Backend, database.
- **Testing method.** T08 — a controlled two-connection deadlock on a scratch schema, plus cases proving the retry
  does not swallow a non-retryable condition and does not repeat a refusal the desk must read.
- **Evidence.** The deadlock is not hypothetical: `lane168-probe-last-seat-race.php` observed a real
  `SQLSTATE[40001] 1213` on HEAD's act in one of three pairs, surfacing as a 500 at the desk. The locked act ran
  nine writes across three pairs with `deadlock 0`. `BlockCapacityAtomicityTest` covers the helper's boundaries
  (1213 and 1205 retried; 1062, 1146, 2002 and 1451 propagated on the first pass; a `ValidationException` never
  retried; `attempts: 0` refused), proved by `mutate-g-ads-capacity-tests.php`.
- **Status.** Built for the Blocking assign path; the measured deadlock proves the condition the guide describes
  occurs on this schema. Not extended to other paths, and that is stated rather than counted as complete.

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
- **Evidence.** `php storage/app/scratch/lane164-measure-scale-queries.php` over a scratch schema the guide's scale
  asks for — `ems_l163_a`, built by `lane163-build-scale-schema.php --students=100000` from live `ems`' structure:
  **100,000 students, 101,484 enrollments, 507,420 subject lines**. The newest capture is saved beside the tool at
  `storage/app/scratch/lane164-out.txt`; the numbers below are read from it.

  **What is stable is the work the plan does, not the millisecond.** Two runs of this same command on the same
  schema answered the contains-search at 2.68 ms and at 10.54 ms, the prefix at 0.53 ms and 0.97 ms, the fulltext
  match at 0.78 ms and 14.74 ms, and the batched page read at 0.77 ms and 6.23 ms — a latency on this box moves with
  whatever else the box is doing, exactly as an assertion total belongs to the run that printed it. The plans agreed
  with themselves across both runs, and that is the finding:

  | read | plan the server chose | rows it expects to touch |
  |---|---|---|
  | `like '%Scale12%'` (what the desk does today) | `index:idx_students_names` | ~98,800 |
  | `like 'Scale12%'` | `range:idx_students_names` | ~1,111 |
  | `schoolIdNumber` exact | `const:uq_students_schoolid` | 1 |
  | `MATCH … AGAINST` on a fulltext index | `fulltext:ft_students_names` | 1 |

  An ~89x difference in rows touched between the leading-wildcard read and the prefix is the guide's claim proved
  rather than asserted, and it survives any millisecond. The fulltext index exists only on the scratch schema — the
  probe adds it at `storage/app/scratch/lane164-measure-scale-queries.php:92` to price the option — and live `ems`
  holds five indexes on `students` with no fulltext among them.
- **Status.** Measured, not repaired, and deliberately so. Both candidate fixes change what a desk can find: a
  prefix match stops answering for a surname typed from its middle, and a `MATCH … AGAINST` in boolean mode follows
  MySQL's word-splitting and minimum-token rules. That is a search-semantics ruling for the school, not a default to
  invent; the plans, the row counts and the two readings are the deliverable for this row.
  `lane167-probe-capacity-index.php` also retires one belief written earlier in this batch's working notes: the
  capacity count is **not** a bottleneck at this scale — the fullest partition (block 377 at 40 of `maxStudents` 40,
  out of 507,420 subject lines) answers 2.77 ms in the newest capture and 0.86 ms in the earlier one, and the
  optimizer keeps the FK index either way. The first reading of 300 ms came from a degenerate fixture that put
  101,484 students in one block of 40, which `lane163` now refuses to build.

### P09 — Enrollment History Performance (¶121-130)

- **Objective.** One student's whole history from hundreds of thousands of rows.
- **Existing implementation.** `app/Http/Controllers/StudentController.php:90-107` eager-loads the record page's
  relations, and `app/Services/AcademicStandingService.php:177` sums `lectureUnits + labUnits` inside a set-based
  read rather than per row. `enrollments` is indexed on `studentId`, `courseId` and `termId`, plus the composite
  `idx_enrollments_term_status` at `database/migrations/2026_08_04_1000000052_add_composite_indexes_for_heavy_screens.php:24`.
- **Gap.** The Registrar queue loop at `app/Http/Controllers/Registrar/RegistrarController.php:106-115` calls the
  checklist per page row, and `app/Support/EnrollmentReadiness.php:66,109-111,144,163,183` each issue a query, so a
  20-row page costs roughly a hundred queries. No query-count ceiling guards it (the suite has exactly one such
  assertion, `tests/Feature/Print/PrintEnrollmentFormPayloadTest.php:206-227`).
- **Required solution.** Batch the checklist's reads for the page (one query per relation, keyed by the page's
  enrollment ids) and pin the count.
- **Affected components.** Backend, database.
- **Testing method.** T10 with `DB::enableQueryLog()` and an asserted ceiling, plus a volume rehearsal on a
  generated schema.
- **Evidence.** Same schema and instrument as P08, newest capture in `storage/app/scratch/lane164-out.txt`. A single
  student's history is a `ref` hop on `fk_enrollments_studentid` expecting one row, then two `eq_ref:PRIMARY` hops;
  their subject lines are a `ref` on `uq_enrollment_subject_attempt` expecting five. The *shape* is the finding:
  every read is an index seek, so retrieval from hundreds of thousands of rows is not the problem — the page loop is.
  Measured on the same run, a 20-row page built per row costs **40 queries** where the same two reads batched cost
  **2**, and the two timings that came back were 57.16 ms against 6.23 ms (an earlier run of the same command on the
  same schema answered 8.31 ms against 0.77 ms). The 20x query gap is the claim worth carrying; the milliseconds are
  this box's, on that minute, and the file names them as such.
- **Status.** Measured, not repaired. Batching the checklist per page is the fix the evidence supports and it is
  code work, not a school question — the ceiling case belongs with the Registrar queue's own test once written. The
  guide's "hundreds of thousands of rows" precondition is now reproducible on demand by
  `lane163-build-scale-schema.php`, which is what makes the claim checkable next time instead of arguable.

### P10 — Concurrent Subject Registration (¶131-140)

- Same missing lock as P02, and no case in `tests/` opens two connections at once (grepped `tests/` for `curl_init`,
  `pcntl_fork`, `Http::post`, `parallel` — zero files). What exists now is the instrument rather than a case:
  `storage/app/scratch/lane168-probe-last-seat-race.php` runs two child processes against one MySQL server on a
  scratch clone, which is the harness this row and P24 both need. It drives the *assign* path, and on 2026-11-01
  `storage/app/scratch/lane190-probe-subject-line-race.php` drove the other one the same way: two children, one
  enrollment, one payload, released at one shared instant.
- **Required solution.** The P02 lock (delivered) plus a concurrent harness (delivered) plus a case for two writers
  adding subject lines to one enrollment. The P03 key the row said it needed before the scenario could be *settled*
  rather than merely observed arrived the same day, and the scenario is now driven: 10 writers gave 6 proposals and
  4 unretried `1213` deaths, while every pair settled on exactly the payload's 6 rows with 0 duplicate
  `(enrollmentId, subjectId, attempt_number)` — so the row set is settled by the key and the transaction, and what
  the run exposes is an act that no writer retries. **Status.** Harness built and the race driven 2026-11-01; the
  lock-plus-`RetryOnDeadlock` repair landed the same day and the same instrument re-measured it (10 of 10 clean), so
  what remains is a Pest case for the shape — SQLite cannot deadlock, so the case would assert the lock order, and
  `lane190` carries the concurrency proof. **Status.** Harness built, race driven and repaired 2026-11-01.

### P11 — Invalid Enrollment Data (¶141-150)

- **Existing implementation.** The server holds 182 table constraints in live `ems`, of which 13 are unique
  (`php storage/app/scratch/lane154-probe-duplicate-keys.php` prints the reading beside its duplicate counts);
  foreign keys refuse an enrollment that names no student or course, `enrollments` columns are NOT NULL, statuses
  are MySQL enums, and the desk's requests validate `exists:` before write. The delete-side consequences are
  covered by `php storage/app/scratch/lane43-delete-guards.php`.
- **Gap.** No `CHECK` constraint expresses a range rule the guide names (capacity, units ≥ 0). The two unique keys
  this row asked for arrived with P03 on 2026-11-01, so the remaining half is the narrower one.
- **Required solution.** Add the CHECKs the columns can honestly carry, and keep the SQLite suite's escape hatch in
  every migration that writes raw MySQL.
- **Testing method.** T12 — a POST naming a non-existent subject, student and section must be refused, and the
  refusal must survive a route that skips validation.
- **Status.** Implemented for the existing constraints and for P03's two keys; the range `CHECK`s are not started,
  which is the honest remainder of this row.

### P12 — Prerequisite Checking (¶151-160)

- **Existing implementation.** The relationship is `curriculumsubjects.prerequisiteSubjectId`
  (`database/migrations/2026_08_02_1000000029_create_curriculumsubjects_table.php:15`, indexed `:20`, foreign key
  `:22`); the gate is `app/Support/EnrollmentReadiness.php:124-150`, which reads the student's satisfied subjects —
  including approved equivalencies — and refuses when a confirmed load sits behind an unmet prerequisite. The
  crediting decision that can satisfy one is `App\Models\Creditedsubjects::approved()`.
- **Status.** Implemented. **Evidence.** `tests/Feature/Registrar/PrerequisiteGateTest.php` and the credit
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

- As P01: the mechanism exists (`EnrollmentIssuer.php:109`), the midway demonstration does not. A `try/catch` that
  swallows without rolling back is refused by the framework's own closure form, which is worth saying out loud in
  the defense. **Status.** Implemented; T17 demonstration not started.

### P17 — Power Interruption During Enrollment (¶201-211)

- **Existing implementation.** InnoDB durability and the transaction boundaries above; `LoginRequest` rate-limits
  (`app/Http/Requests/Auth/LoginRequest.php:47-82`) and nothing else does.
- **Built in this batch.** `enrollments.requestKey` (varchar 64, nullable) with `uq_enrollments_request_key` over it,
  added by `database/migrations/2026_11_01_1800000000_add_request_key_to_enrollments_table.php`.
  `EnrollmentIssuer::issue($attributes, $issuer, $requestKey)` reads the key first and answers the enrollment that
  already exists — one record, one workflow form — and a second request that races past the read is refused by the
  UNIQUE index and then answered the same way, so the index is the guard and the read is only the fast path. One key
  may not name two different acts: a key already bound to a different student or term raises a validation refusal
  naming both records rather than handing over somebody else's enrollment. The desk half mints the key when the act
  is opened (`resources/js/Components/ui/requestKey.js`, used by `Pages/Evaluation/Index.jsx` and
  `Pages/Evaluation/ShiftRequests.jsx`) and replaces it after a success, so a retry of the same send is the same act
  while a second student issued in the same visit is a different one.
- **Why the index is portable and the seat key is not.** A plain nullable column with an ordinary unique
  index exists on the suite's SQLite driver as well as on MySQL, so `tests/Feature/Enrollment/EnrollmentRequestKeyTest.php`
  can assert the server's own refusal. `uq_enrollments_active_seat` needed a generated column, SQLite cannot build
  one, and the whole local suite was green over a rule only CI could see.
- **Testing method.** T18, as the service's own cases plus the wire: replay answers the first record; the index
  refuses a second row carrying one key on whichever driver the suite runs; two unkeyed acts stay two acts; a
  cross-act replay is refused and leaves the original untouched; the page is read to prove the desk actually sends a
  key. Proved by `storage/app/scratch/mutate-p17-idempotency-tests.php`, one leg per promise — including the plant
  that takes `requestKey` out of `Enrollments::$fillable`, which is the silent one: mass assignment drops a field
  with no error at all, and a dedupe whose key never reaches the column looks exactly like a working one.
- **What is still owed.** The guide's other half — a rate limit on the POST — is not built: no `throttle` middleware
  sits on any desk route, so a stuck desk can still send one act ten times. The key makes each send answer the same
  record; it does not make the sends fewer. A lookup route by key is also not built, because the desk that lost its
  response is the desk that can re-send, and the re-send already answers with the record.
- **Status.** Built for the enrollment-issuing acts.

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

- **Existing implementation.** The isolation reading was true when this row was written and is kept because it is
  what was measured: grepping the repository for `ISOLATION LEVEL`, `READ COMMITTED`, `REPEATABLE READ`,
  `SERIALIZABLE`, `transaction_isolation` finds only `config/cache.php` and `composer.lock` false hits, and
  `config/database.php:14` sets the session **time zone** and nothing about isolation.
- **What was built 2026-11-01.** The daily collection report — the one sheet the guide's problem describes, the
  action beginning at `app/Http/Controllers/Accounting/AccountingController.php:360` — now takes both of its
  `payments` reads inside one `DB::transaction`, so InnoDB's default REPEATABLE READ fixes a single read view for
  the sheet and a receipt committed by another session mid-render cannot appear in the row list and not the totals.
  The isolation level is **not** declared: raising or lowering it changes what every desk write on the connection
  sees, which is an engine-wide decision the school has not made, so the run names the default it measured instead
  (`lane172` printed `session isolation REPEATABLE-READ` off the live server).
- **What remains, named rather than counted as done.** `RegistrarController::index` and the dashboard's aggregate
  tiles still read outside a transaction. They are lists and counters rather than a signed sheet a desk reconciles
  against the drawer, so this row does not extend its claim to them; the same wrap applies whenever one of them
  starts costing more than one snapshot.
- **Testing method.** T21 — the report's two `payments` reads must sit one transaction level deeper than the request
  started at, and the totals must still equal a direct read.
- **Status.** Built and measured 2026-11-01 for the daily collections report, pinned by
  `tests/Feature/Accounting/PartPaymentAndRefundTest.php` and falsified by
  `php storage/app/scratch/mutate-p20-consistent-read-tests.php`, which takes the wrapper off and requires that
  named case to go red (4 legs, control green before and after, tree restored by hash). The other report screens
  are the remainder above.

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
- **Gap that is now closed.** Nothing was measured. The guide requires performance improvements proved, not assumed,
  and the two largest known costs are P08's unusable name index and P09's per-row checklist.
- **What was delivered.** One measured bench: `lane163-build-scale-schema.php` at a stated row count (100,000
  students, 507,420 subject lines, capacity invariants enforced by the builder), `lane164-measure-scale-queries.php`
  printing timings beside each `EXPLAIN`, and `lane172-enrollment-day-load.php` driving the write path itself from
  ten processes — 1,000 acts at 69.2 per second, median 113.6 ms per request, every integrity question read back
  from the server and falsified by `mutate-lane172-load-integrity.php`.
- **Testing method.** T23's measurement plus T24's load run. **Status.** Built and measured 2026-11-01. The
  limitation is stated rather than papered over: ten processes on one box is a practical scale, not a data centre,
  and a timing belongs to the run that printed it.

### P24 — Complete Enrollment-Day Challenge (¶276-290)

- **Existing implementation.** Before this batch nothing concurrent existed: no test opened two connections and no
  script drove parallel requests; `pcntl` appeared only as a CI PHP extension
  (`.github/workflows/ci.yml:23,211`). `lane168` now drives two writers against one seat, and `lane172` drives ten
  processes at a thousand acts.
- **What the ten properties say today.** No block over capacity — `blocks over capacity 0`, and the rule is
  the audit's twentieth. No prohibited duplicate — `dup-seats 0`. Both re-read today with
  `php storage/app/scratch/lane172-enrollment-day-load.php --verify-only --schema=ems_l172_a --term=11`, which answers
  1000 load-students, 1002 load-enrollments, 0 duplicates, 0 orphan forms, 0 acts without a form, 0 records with two
  forms, 0 blocks over capacity. That schema carries the seat key: it was cloned from live `ems` on the day the key was
  first live, and the withdrawal migration ran against live `ems` alone, so `information_schema.STATISTICS` still
  answers `uq_enrollments_active_seat` over `studentId, termId, seatKey` there — which is why today's `dup-seats 0` is a
  reading taken on a schema where the server really does refuse, not only the audit's arithmetic. **The whole run has
  since been re-taken on this build** (`--keep`, 10 workers over 1,000 issues, then 10 workers at one deliberately
  single-seated student): the volume phase created 1,000, refused 0 and errored 0 in 16.45 s; contention on one seat
  gave exactly one winner and 9 refusals; and the run's own driver breakdown attributes **8 of the refusals to code
  1062 — the server's unique key — and 3 to `EnrollmentIssuer::seatHolder()`**, so both layers are seen refusing on
  one schema instead of one being claimed for the other. The cancel-against-enroll pair alongside it cancelled 5,
  created 3 and refused 2 with no error, which is a drop releasing a seat while another writer is asking for it. The
  12-refusals reading belongs to the day the key was first live and is left as written rather than re-aged. No partial records from failed
  transactions — `orphan workflow forms 0` and `committed acts without a form 0`. Committed records still there — the
  server's own count of load enrollments reconciles with the workers' tallies. Authorization enforced — the acts
  run as a real office-4 desk account through the service the desks call. Database up and consistent — the run
  finishes with the clone readable and the source weighed unchanged.
- **Which two it does not reach, and why.** No load breach and no bypassed prerequisite: the creation act writes no
  subject line, so load and prerequisite are the *proposal* path (P13's ceiling is an open school figure; P12 is
  already pinned by `PrerequisiteGateTest`). No concurrent student modification either — the version guard is built
  (P05, `students.rowVersion`, four cases in `ProfileCaptureTest`), and the simultaneous-process half of it is the
  demo T07 still owes.
  Saying so is the difference between a challenge met and a claim stretched.
- **Status.** Built and measured 2026-11-01 on the creation path, with its limits named above. This is the deliverable
  the defence question is asked about, and the answer it gives is a run, not a design.

---

## 3. Required database concepts (guide ¶291-319, 12 rows)

| Concept (guide ¶) | Status | Where | Evidence owed |
|---|---|---|---|
| Transactions (¶295) | Implemented | 38 `DB::transaction` sites; `EnrollmentIssuer.php:58` | T01, T02 |
| COMMIT / ROLLBACK (¶297) | Implemented, partly demonstrated | same; the assign act's refusal is pinned by `BlockCapacityAtomicityTest` | a printed failed-commit inside the enrolment path (T01) |
| ACID Properties (¶299) | Partial | Atomicity and Consistency by the above; Isolation now by the P02 row lock (P20's report boundary is still open); Durability by the P21 drill | four separate demonstrations, of which isolation and durability now have one each |
| Concurrency Control (¶301) | Implemented on the seat, the room, the block, the sheet and the subject line | `BlockingController.php:485` and `:500`, `RetryOnDeadlock`, measured by `lane168`; `EvaluationController::proposeSubjects()` locked and retried, measured by `lane190` | The person record's simultaneous-process demo (T07) and the unversioned address and guardian rows |
| Locking (¶303) | Three sites | `PrintService.php:140` (document number), the block row and the room row in the assign act | any further path that counts a shared resource |
| Deadlock Handling (¶305) | Implemented and observed | `app/Support/RetryOnDeadlock.php`, `1213`/`1205` only | extending it to a second write path; P17's request key now makes a blind retry answerable, which is what the guide asks before a retry is widened |
| Database Constraints (¶307) | Implemented | the unique keys §23.6.1 lists, weighed by `lane44-unique-keys.php` — thirteen keys in ten tables on the 2026-11-01 schema, of which this batch contributes `uq_enrollments_request_key` on `enrollments` and `uq_studentassessments_enrollment` on `studentassessments`, and returns `uq_enrollments_active_seat` over the VIRTUAL `seatKey` the same batch added, withdrew and repaired the fixtures for | nothing owed for the keys; the range `CHECK`s are named in P11 |
| Indexing (¶309) | Implemented, unusable by one query | `idx_students_names`, `idx_enrollments_term_status`, `idx_enrolledsubjects_block_status` | the plan is printed: a leading-wildcard search reads ~98,800 index rows where a prefix reads ~1,111, and the estimates agreed across two runs |
| Query Optimization (¶311) | Measured 2026-11-01 | P08, P09, `lane164-measure-scale-queries.php` | the two repairs the measurements point at, which wait on a search-semantics ruling |
| Data Integrity (¶313) | Implemented, extended today | state machine, `ems:audit:record-states` (its 20th rule weighs block capacity), lane 43 delete guards | nothing owed for P04's block side |
| Database Security (¶315) | Implemented | policies, RBAC, hidden hashes, audit redaction | T23 matrix |
| Recovery (¶317) | Drilled 2026-11-01 | `tools/ems-backup-drill.php`, 64 tables compared and three ordered digests MATCHES | a scheduled run and point-in-time recovery, which the school has not asked for |

No concept is marked Implemented because a document discusses it.

---

## 4. Presentation requirements (guide ¶320-354, 8 areas)

Each area was re-read on 2026-11-01 rather than carried forward: a list of what is owed that stops growing is the
same defect as a stale row, and six of the eight below said "owed" for things this batch had already delivered.

1. **Database Design** — ERD, tables and relationships, keys. **Delivered.** The diagram is generated from the
   schema by `storage/app/scratch/lane162-generate-erd.php` into `Documentation/DATABASE-DESIGN-ADS.md`, with the
   key inventory beside it; the census and §23.6.1 in the authority document are kept equal to `information_schema`
   by `lane60` and `lane44` rather than restated by hand.
2. **Transactions** — **partly delivered.** One meaningful transaction per enrollment act, COMMIT and ROLLBACK
   evidenced by `BlockCapacityAtomicityTest` (a refusal that seats nobody, and the seats still free afterwards).
   Still owed: a failure injected *inside* a commit, which the assign path cannot produce from a desk because its
   ids are validated first — stated in `TESTING-AND-VERIFICATION-ADS.md` as T01/T02 rather than simulated.
3. **ACID** — **delivered as far as the build goes.** Isolation is the row lock the guide names (`FOR UPDATE`) for
   the write path and one transaction around the sheet the cashier prints, so a report no longer straddles two
   snapshots; durability is the executed restore; atomicity and consistency are the keys and the audit. What the
   system still does not do is declare an isolation level, which is a deliberate refusal to change what every desk
   write on the connection sees — the engine's default is measured and named instead (REPEATABLE READ, read from the
   session by `lane172`).
4. **Concurrency** — **delivered, with numbers attached.** The scenario, the chosen strategy (pessimistic row
   locks in one fixed order over a canonically sorted id list) and the testing evidence are in
   `DEFENCE-EVIDENCE-ADS.md` parts 3, 4 and 9; the two-writer outcome is `lane168`'s own table, and the
   thousand-act enrollment-day run is `lane172`'s (`storage/app/scratch/lane172-load-out.txt`), carried into
   `DATABASE-DESIGN-ADS.md` as a result rather than a plan.
5. **Deadlock** — **delivered.** A possible scenario was not invented: a real `1213` appeared on HEAD's act during
   the race probe, and the handling strategy with its boundaries is `App\Support\RetryOnDeadlock`.
6. **Indexing** — **delivered as evidence, not as a fix.** Why each column, and the plan beside each timing, are in
   `DEFENCE-EVIDENCE-ADS.md` part 11; what the plans recommend (a sargable search, a batched page) is registered as
   measured-not-repaired with its reason.
7. **Testing** — **delivered.** `TESTING-AND-VERIFICATION-ADS.md` carries the scenario, preconditions, data, steps,
   expected, actual, verdict, evidence and notes for the twenty-four cases, and marks each un-executed one as
   un-executed with what blocks it.
8. **Workflow and Applicability** — state the workflow, justify each N/A or Partial. The build's workflow is
   registrar-assisted and office-assisted across nine desks — there is no student login (public `/register` is
   removed and `students` is deliberately not notifiable), which the guide allows, and each Partial in section 1
   above carries its reason.

---

## 5. Test map T01-T24

The prompt's table is the queue this document answers to. T12, T13, T16, T19 and T23 name cases the suite already
holds (`EnrollmentReadinessTest`, `PermissionMatrixTest`, the store and intake cases) rather than duplicating them,
and the table above says which; the rest are this batch's own. Every case in
`Documentation/TESTING-AND-VERIFICATION-ADS.md` is written in the prompt's field form — test id, description,
preconditions, test data, steps, expected, actual, pass or fail, logs, evidence, notes — and a test that cannot run
is marked **not executed** with the reason in the same shape rather than being left off the list. The prompt's ninth
field, *relevant logs or query output*, is now its own row per case and is filled from a captured file
(`lane164-out.txt`, `lane168-out.txt`, `lane172-load-out.txt`, `l171-suite.log`) or from the server's own message
quoted in the artefact that holds it; nothing in that file quotes a line no run printed.

| Test | Scenario | Exists today? |
|---|---|---|
| T01 | multi-subject commit | no |
| T02 | failure mid-commit | no — see the note under the table for why the assign path cannot produce it |
| T03 | two requests, one slot | yes, `lane168-probe-last-seat-race.php` (two writers, one MySQL server, HEAD vs working) |
| T04 | duplicate enrollment | pass — the fee sheet is refused by `uq_studentassessments_enrollment` on both drivers, and the second active seat by `uq_enrollments_active_seat` on MySQL, where the delivered case also shows the drop releasing it; on SQLite the key is asserted absent rather than skipped, and `EnrollmentIssuer::seatHolder()` is the rule the suite can actually execute. `DatabaseLevelEnrollmentRulesTest` carries both halves, and its own totals belong to the run that printed them |
| T05 | capacity reached | yes, `BlockCapacityAtomicityTest` (a request larger than the seats left seats nobody) |
| T06 | multi-subject cancellation | no |
| T07 | concurrent student edit | no |
| T08 | controlled deadlock | yes, same probe — HEAD answered a real `1213`, the locked act answered none |
| T09 | search at volume | yes, `lane164-measure-scale-queries.php` over 100,000 students with each plan printed |
| T10 | history retrieval | yes, same instrument (0.80 ms joined, and 40 queries vs 2 batched for a page) |
| T11 | popular-section contention | yes, `lane168` on a block staged at exactly one free seat |
| T12 | invalid references | yes, by `exists:` validation cases |
| T13 | missing prerequisite | yes, `EnrollmentReadinessTest` |
| T14 | load exceeded | no |
| T15 | faculty load | not executed — no limit is defined (P14) |
| T16 | schedule conflict | partially, resource side |
| T17 | DB exception mid-flow | yes, `the_helper_retries_a_deadlock_and_a_lock_wait_and_nothing_else` (the exception side; no injected fault) |
| T18 | interruption and retry | built and pinned by `tests/Feature/Enrollment/EnrollmentRequestKeyTest.php` — replay answers the first record, the UNIQUE index refuses a second row for one key on the driver the suite runs on, a cross-act replay is refused, and the desk page is read to prove it sends a key. What no case does is kill a real process between the write and the commit, which is a harness of processes rather than a phpunit case (T23's `lane172` is that harness, and it does not interrupt mid-transaction) |
| T19 | duplicate student id | yes, intake validation |
| T20 | deletion and restoration | no |
| T21 | report during enrollment | yes, `the_daily_report_takes_its_two_reads_inside_one_transaction` (the daily collections sheet; the Registrar queue and dashboard tiles are named as the remainder in P20) |
| T22 | backup and restore | yes, `tools/ems-backup-drill.php` executed: 64 tables compared row for row and three ordered digests MATCHES, with the durability variables read from the server |
| T23 | unauthorized access | yes, `PermissionMatrixTest` and policy cases |
| T24 | high-volume challenge | no — the design is written, the run is not |

Two rows need their reason stated rather than left as a blank. **T02** asks that a failure mid-commit leave nothing
half-written; on the assign path that failure is unreachable from a desk, because `enrollmentIds.*` is validated
`exists:enrollments`, so a bad id is refused before the transaction opens — the first draft of this batch's test
thought it had produced a mid-loop failure and had in fact produced a validation refusal, which is why the case is
written as an over-large request instead. **T17** is pinned from the exception side, not from a real fault: the suite
runs on SQLite, which does not deadlock, so the case hands the helper the two driver conditions rather than
pretending the driver produced them.

---

## 6. What remains, stated plainly

Read off §1's own Status column by `php storage/app/scratch/lane169-read-ads-status.php`, which prints the bucket
counts and refuses the run when they do not add to the 24-row population or when a row's lead word is unclassified.
This file states no tally, because the buckets are a property of §1's cells and every row re-taken here moves them:

- **Built and measured.** P02's row lock and refusal, P04's guard plus the audit's twentieth
  rule, P07's deadlock handling, P21's restore drill, P23's and P24's enrollment-day run. Each has an instrument that
  can show it failing.
- **Measured but not repaired.** P08 and P09 — the plans and timings are in this file, and the two changes they
  point at are decisions (what a search may stop matching; how a page's checklist is batched), not defaults to invent.
- **Partly.** P03's active-seat rule (the fee sheet is server-enforced, the seat is `seatHolder()`'s until six
  fixture files are repaired), P10's own race and P14's justified cap.
- **Not built.** P13 (the term unit ceiling, which the school has
  not stated), P17's rate-limit half (no POST is throttled, so a stuck desk can still send the same act ten times —
  the key makes each one answer the same record rather than making the sends fewer).
- **Implemented.** P01, P06, P11, P12, P15, P16, P18, P19, P22 — of which P01, P06 and P16 still owe the
  demonstration the guide asks for rather than the behaviour.

Two inputs genuinely wait on the school and are not hidden behind a default: the term unit ceiling (P13) and any
faculty load cap (P14). The presentation pack is delivered rather than owed: the ERD is generated into
`Documentation/DATABASE-DESIGN-ADS.md` by `storage/app/scratch/lane162-generate-erd.php`, the twelve-part report is
`Documentation/FINAL-REPORT-ADS.md`, the defence answers are `Documentation/DEFENCE-EVIDENCE-ADS.md`, and the
evidence index is `Documentation/EVIDENCE-PACK-ADS.md`.

One finding the load run produced about the schema itself, recorded here rather than fixed silently:
`enrollmentworkflow` is guarded by PRIMARY on `workflowId` and a **non-unique** FK index on `enrollmentId`
(measured with `SHOW INDEX` by `mutate-lane172-load-integrity.php`), so "one workflow form per enrollment" is held by
`WorkflowService::createWorkflow()` and the existence check around it, not by the server. The harness can therefore
plant two forms on one record — which is how it proves the checker sees the defect, and also how it proves the
constraint is absent. The additive fix is a unique key over `enrollmentId`. It was left out of that batch because the
duplicate it would refuse is exactly the re-issue question P17's request key had to answer first — and P17 is now
built, so a re-send of one act is answered with the record that exists instead of reaching the form at all. That
leaves the key safe to add and nothing in the way of it; it is still not added in the same breath as the feature that
justified it, because a schema guard deserves its own refused-against-real-data check (`lane154` measured zero
duplicate forms) and its own row in §23.6.1.

The sentence this one replaces read "Nine problems need new code" and then listed thirteen — the membership defect
this repository has met in its own registers (lane 64), caught here by making the count come from the table instead
of from the hand that wrote the table. Every figure in this file is either read from the code on the date above or
printed by `lane154`, `lane155`, `lane164`, `lane167`, `lane168` or `lane169`; none is carried forward from a
previous day.
