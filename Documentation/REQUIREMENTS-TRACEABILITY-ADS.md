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
| P02 | Last Available Slot (¶51) | Applies | Built and measured 2026-11-01 — block row locked inside the transaction, count re-taken under it, room locked second; HEAD overbooked 2-of-1, the fix refused one writer per pair |
| P03 | Duplicate Enrollment (¶61) | Applies | Built and measured 2026-11-01 — subject lines guarded since the schema was written, and the seat and the fee sheet are now refused by unique keys on MySQL (`uq_enrollments_active_seat`, `uq_studentassessments_enrollment`) |
| P04 | Incorrect Section Capacity (¶71) | Applies | Built and pinned — count weighed against `maxStudents` by the assign guard and by the audit's twentieth rule; the room-level question stays open by name |
| P05 | Simultaneous Student Updates (¶81) | Applies | **Not built** — no optimistic version, no lock, a silent overwrite is possible |
| P06 | Enrollment Cancellation (¶91) | Applies | Implemented, demonstration owed (T06) |
| P07 | Database Deadlock (¶101) | Applies | Built for the assign path — one lock order, `RetryOnDeadlock` on 1213/1205 only, and the deadlock was observed on the school's server, not assumed |
| P08 | Student Search Performance (¶111) | Applies | Measured, not repaired — the leading-wildcard search scans ~98,800 index rows where a prefix touches ~1,111 (plans, both runs agreeing); timings move between runs and are quoted from the captured file; the semantic choice waits on the school |
| P09 | Enrollment History Performance (¶121) | Applies | Measured, not repaired — every history read is an index seek, but a 20-row page costs 40 queries against 2 batched |
| P10 | Concurrent Subject Registration (¶131) | Applies | Partly — the P02 lock and a two-writer harness both exist now; the subject-line race itself is undriven |
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
| P21 | Database Recovery (¶245) | Applies | Built and executed 2026-11-01 — `tools/ems-backup-drill.php` restored a fresh dump into a scratch schema, compared 64 tables row for row, three ordered digests MATCHES, and read the durability variables from the server |
| P22 | Unauthorized Database Access (¶255) | Applies | Implemented and pinned |
| P23 | Heavy Enrollment Traffic (¶265) | Applies | Built and measured 2026-11-01 — the queries are weighed on 100,000 students and 507,420 subject lines, and `lane172` ran 1,000 acts through ten real processes at 69 per second with every integrity question read back from the server |
| P24 | Complete Enrollment-Day Challenge (¶276) | Applies | Built and measured 2026-11-01 — 1,000 issues, 10 workers, one contended seat (1 winner, 9 refusals) and five drop-vs-issue races, ending with no student holding two active seats and no orphan form; the lost-update and report-isolation halves stay with P05 and P20 |

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
  `Documentation/System-Design-Quality-Review.md:79`, recorded 2026-09-15 and open for six weeks.

### P03 — Duplicate Enrollment (¶61-70)

- **Objective.** The same request twice, including simultaneously, must not create two records.
- **Existing implementation.** Subject lines: `database/migrations/2026_08_09_1000000004_extend_enrolledsubjects_table.php:33`
  declares `uq_enrollment_subject_attempt` on (`enrollmentId`, `subjectId`, `attempt_number`) — a second identical
  line is refused by the server. The active-seat rule is code: `EnrollmentIssuer::seatHolder()` at
  `app/Services/EnrollmentIssuer.php:37-43` reads a non-dropped enrollment for a student and term, and a caller
  refuses when one exists. `studentassessments` has no unique key on `enrollmentId` (measured against
  `database/migrations` — no `unique(` names that table).
- **Gap closed.** The seat rule used to have no database-level enforcement, so a second route or a concurrent request
  could walk past it, and a second fee sheet for one enrollment was likewise unguarded — the review document's DI-2,
  open since 2026-09-15. Both are refused by the server now.
- **What was delivered.** `uq_studentassessments_enrollment` on `studentassessments(enrollmentId)`, which travels on
  both drivers, and `uq_enrollments_active_seat` on `enrollments(studentId, termId, seatKey)` where `seatKey` is a
  **VIRTUAL** generated column, non-null exactly when the status is not `dropped` and a term is named. MySQL ignores
  NULLs in a unique index, which is precisely the ruling that a drop releases the seat, so re-enrollment stays legal
  without a trigger. Written as
  `database/migrations/2026_11_01_1100000000_add_database_level_enrollment_rules.php`. The usual spelling for a
  generated column is STORED; it was tried and measured, and the `ALTER TABLE` answers
  `1215 Cannot add foreign key constraint`, because a STORED change rebuilds a table eleven other tables point at.
  VIRTUAL adds in place, accepts the unique index, and keeps the same NULL behaviour. The difference is recorded here
  rather than smoothed over, because a reader who tries STORED next time will meet the same error.
- **Affected components.** Database, backend.
- **Testing method.** T04 (sequential duplicate), plus a case that inserts over the HTTP stack and asserts the
  database, not the controller, is what refused.
- **Evidence.** `php storage/app/scratch/lane154-probe-duplicate-keys.php` answered 0 duplicate groups on live `ems`
  for every key this row proposes — the precondition for adding a constraint additively instead of repairing data
  first. `tests/Feature/Enrollment/DatabaseLevelEnrollmentRulesTest.php` then pins both keys: the fee-sheet refusal is
  asserted on every driver, the seat refusal only on MySQL, and the class names the half the SQLite suite cannot reach
  in its own output instead of skipping it quietly.
- **Status.** Built 2026-11-01 on the driver the school runs. This closes the review document's DI-2 alongside the
  DI-5 that P02 closed; see `Documentation/System-Design-Quality-Review.md:79`.

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
  the exclusion of dropped rows is read wherever seats are counted — the roster's own closure at
  `BlockingController.php:57-58` and the write guard at `:488-491`, with the same clause repeated at every other
  count site in that controller. The numbers of those further sites are deliberately not listed: a comma list of
  line numbers is judged only on its FIRST number by the citation gate, so the rest age unseen, and this batch's
  insertion aged all seven of them by one to nineteen rows while the gate printed `0 problem(s)`. See also
  `AssessmentController.php:191` and `app/Support/EnrollmentReadiness.php:133`.
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
- **Gap.** The Registrar queue loop at `app/Http/Controllers/Registrar/RegistrarController.php:97-106` calls the
  checklist per page row, and `app/Support/EnrollmentReadiness.php:65,109-111,144,163,183` each issue a query, so a
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
  scratch clone, which is the harness this row and P24 both need. It drives the *assign* path; the path that adds
  subject lines to one enrollment concurrently is still undriven.
- **Required solution.** The P02 lock (delivered) plus a concurrent harness (delivered); what remains is a case for
  two writers adding subject lines to one enrollment, which needs the P03 database key before it can be settled
  rather than merely observed. **Status.** Harness built 2026-11-01; scenario not yet driven.

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
- **What the ten properties say today.** No block over capacity — `blocks over declared capacity 0`, and the rule is
  the audit's twentieth. No prohibited duplicate — `duplicate active seats 0`, enforced by
  `uq_enrollments_active_seat`, with 12 refusals all carrying driver code 1062. No partial records from failed
  transactions — `orphan workflow forms 0` and `committed acts without a form 0`. Committed records still there —
  the server's own count of load enrollments reconciles with the workers' tallies. Authorization enforced — the acts
  run as a real office-4 desk account through the service the desks call. Database up and consistent — the run
  finishes with the clone readable and the source weighed unchanged.
- **Which two it does not reach, and why.** No load breach and no bypassed prerequisite: the creation act writes no
  subject line, so load and prerequisite are the *proposal* path (P13's ceiling is an open school figure; P12 is
  already pinned by `EnrollmentReadinessTest`). No concurrent student modification either — that is P05, not built.
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
| Concurrency Control (¶301) | Implemented for one act | `BlockingController.php:485` and `:500`, `RetryOnDeadlock`, measured by `lane168` | P05 and P10's own races, which no writer yet serialises |
| Locking (¶303) | Three sites | `PrintService.php:140` (document number), the block row and the room row in the assign act | any further path that counts a shared resource |
| Deadlock Handling (¶305) | Implemented and observed | `App/Support/RetryOnDeadlock.php`, `1213`/`1205` only | extending it to a second write path, and P17's key before any act is retried blind |
| Database Constraints (¶307) | Implemented | the unique keys §23.6.1 lists, weighed by `lane44-unique-keys.php`, including the two enrollment keys this batch added | nothing owed for the keys; the range `CHECK`s are named in P11 |
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
3. **ACID** — **delivered as far as the build goes.** Isolation is the row lock the guide names (`FOR UPDATE`),
   durability is the executed restore; atomicity and consistency are the keys and the audit. The one property the
   system does not hold is a transaction isolation level around a report, which is P20 and is written as absent.
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
| T04 | duplicate enrollment | yes, `DatabaseLevelEnrollmentRulesTest` — the second active seat refused by `uq_enrollments_active_seat` on MySQL, the second fee sheet refused on both drivers |
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
| T18 | interruption and retry | partly — `RetryOnDeadlock` is pinned (retries 1213/1205, propagates everything else, refuses `attempts: 0`, says *Nothing was saved*), and no case interrupts a real process between the write and the commit, which is P17's request-key work |
| T19 | duplicate student id | yes, intake validation |
| T20 | deletion and restoration | no |
| T21 | report during enrollment | no |
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

- **Built and measured.** P02's row lock and refusal, P03's two unique keys, P04's guard plus the audit's twentieth
  rule, P07's deadlock handling, P21's restore drill, P23's and P24's enrollment-day run. Each has an instrument that
  can show it failing.
- **Measured but not repaired.** P08 and P09 — the plans and timings are in this file, and the two changes they
  point at are decisions (what a search may stop matching; how a page's checklist is batched), not defaults to invent.
- **Partly.** P10's own race and P14's justified cap.
- **Not built.** P05 (a silent overwrite of one student record), P13 (the term unit ceiling, which the school has
  not stated), P17 (no request key, so "did my save commit" is unanswerable), P20 (no isolation around a report).
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
constraint is absent. The additive fix is a unique key over `enrollmentId`; it is not taken in this batch because the
duplicate it would refuse is exactly the re-issue question P17's request key has to answer first, and a key that
refuses a legitimate re-issue is a worse defect than the one it prevents.

The sentence this one replaces read "Nine problems need new code" and then listed thirteen — the membership defect
this repository has met in its own registers (lane 64), caught here by making the count come from the table instead
of from the hand that wrote the table. Every figure in this file is either read from the code on the date above or
printed by `lane154`, `lane155`, `lane164`, `lane167`, `lane168` or `lane169`; none is carried forward from a
previous day.
