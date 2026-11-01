# Testing and Verification — Advanced Database Systems

Requirement set: `Enrollment_Management_System_Master_Implementation.md` prompt parts 39–42 (24 scenarios, each with ten
fields) and prompt part 45 (the defence answers). This file is the execution record for `REQUIREMENTS-TRACEABILITY-ADS.md` section 5.

Written 2026-11-01 against the working tree of this repository. Every figure below is read from a run made on that
date, and the file names the surface it came from, because an assertion total belongs to the run that printed it and
a case count belongs to discovery — not to the code.

**How to read "not executed".** A scenario this file marks not executed is not hidden and not counted as passing.
It says what would have to be true to run it, and what stops that today, in the same ten fields. That is the guide's
own instruction: an un-executed test that reads as executed is worse than one that reads as owed.

**Where the numbers come from.**

| Surface | What it gives |
|---|---|
| `php artisan test --log-junit storage/app/scratch/l171-suite-junit.xml` | the whole-suite run every figure here is taken from |
| `php storage/app/scratch/lane107-retake-class-figures.php` | a class's cases and assertions, read out of that junit rather than retyped |
| `php artisan test tests/Feature/Blocking/BlockCapacityAtomicityTest.php` | the capacity, locking and retry promises |
| `php artisan test tests/Feature/Enrollment/DatabaseLevelEnrollmentRulesTest.php` | the two new database keys, on SQLite |
| `DB_CONNECTION=mysql DB_DATABASE=ems_l170_a php artisan test tests/Feature/Enrollment/DatabaseLevelEnrollmentRulesTest.php` | the same file on the school's engine, rehearsed before any push |
| `php storage/app/scratch/lane168-probe-last-seat-race.php` | two real MySQL writers against one seat, HEAD's bytes and the batch's |
| `php storage/app/scratch/lane164-measure-scale-queries.php` | timings and plans over 100,000 students |
| `php storage/app/scratch/mutate-g-ads-capacity-tests.php` | the mutant harness; one leg per promise above |
| `php tools/ems-backup-drill.php --target=…` | the restore T22 rests on |

Case and assertion counts for a named class are taken from the delivered run's junit by the retake tool named above,
and no total is stated in prose in this file: a figure in a sentence no instrument reads is the one that rots.

---

## Group A — enrollment integrity under concurrency (guide problems 2, 3, 4, 7, 10)

### T03 — two requests, one seat

| Field | |
|---|---|
| Description | Two desks assign two different students to the last seat of one block, at the same instant. |
| Preconditions | MySQL 8; a block with `maxStudents` 1 and a schedule under it; two enrollments whose next pending box is Blocking; a live Blocking desk account. |
| Test data | The demo cast carried from live `ems` into the scratch clone `ems_l168_a`; enrollments 53 and 91 chosen by the block's own course, term and year level. |
| Steps | `lane168-probe-last-seat-race.php` clones `ems`, stages the block at exactly one free seat, starts two child processes against one server with the same start instant, then reads the block's held seats. |
| Expected | Exactly one writer assigns; the other is refused on `capacity`; the block never holds more than its own `maxStudents`. |
| Actual | Newest run (`storage/app/scratch/lane168-out.txt`, 2026-11-01): the batch's bytes gave 3 assigned, 3 refused on `capacity`, 0 deadlocks, max seats 1 of 1, clean 3/3; HEAD's bytes gave 6 assigned, 0 refused, **max seats 2 of 1**, clean 0/3 — every pair overbooked. The working bytes were re-taken after the HEAD swap and answered 3/3 again. An earlier run of the same probe also produced a real `SQLSTATE[40001] 1213` in one pair, which reached the desk as a 500; the overbooking is deterministic and the deadlock is not. |
| Logs / query output | `working clean 3/3 assigned 3 refused 3 deadlock 0 max seats 1 of 1`; `HEAD clean 0/3 assigned 6 refused 0 deadlock 0 max seats 2 of 1` and its line `HEAD failed by: overbooking the last seat`; the per-pair lines print each writer's status, its seats-after and its milliseconds, e.g. `pair: #53 assigned seats=2 385.2ms and #91 assigned seats=2 412.3ms`. |
| Result | **Pass** on the fixed act; HEAD's failure is the measured defect, which is what the pass is worth. |
| Evidence | `storage/app/scratch/lane168-out.txt` holds the whole capture; `git show HEAD:app/Http/Controllers/Blocking/BlockingController.php` is the pre-fix byte source the probe swaps in by hash. |
| Notes | The children call `assignStudents()` rather than the HTTP stack and say so: PHP's built-in server handles one request at a time, so an HTTP leg would serialise and read as no race — a false green. |

### T04 — duplicate enrollment

| Field | |
|---|---|
| Description | The same student enrolled twice in one term, including by a route that never asks the application rule. |
| Preconditions | A migrated schema. **`uq_enrollments_active_seat` is not on it today**: the key was delivered on 2026-11-01 and withdrawn the same day, so this row records both the measurement that was taken while it was live and the assertion the suite can actually make now. |
| Test data | One student, one term, one course; the second row written with `enrollmentStatus` `pending` so no code path vetoes it. |
| Steps | `DatabaseLevelEnrollmentRulesTest`: insert the held seat, ask `EnrollmentIssuer::seatHolder()` about it, set it to `dropped`, ask again, then write the second row. |
| Expected | The application rule answers the held row and answers nothing once it is dropped, and the re-enrollment succeeds. A server-side refusal is expected **only** on a driver whose schema holds a key. |
| Actual | While the key was live, on 2026-11-01: MySQL (`ems_l170_a`, and live `ems` after that migration) refused the second insert with `uq_enrollments_active_seat` and accepted it after the drop, both directions measured. On the schema today, **neither driver holds the key**: the case asserts the fee-sheet index exists on both, asserts the seat index is absent on both, and asserts the application predicate plus the drop-and-retry. SQLite could never have held it anyway — `ALTER TABLE … ADD COLUMN … GENERATED` is unsupported there, which is the asymmetry that made CI the only witness and the withdrawal necessary. |
| Logs / query output | the server's own words from the day the key was live, quoted in the enforcement migration's docblock and again in `l171-suite.log`: `SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1487-11-1487:11' for key 'enrollments.uq_enrollments_active_seat'` — the shape that made the walkthrough fixture's double-mint visible after months of green. `SHOW INDEX FROM enrollments` on a schema migrated today answers no row for that name, and the withdrawal is proved by `storage/app/scratch/lane174-rehearsal-seat-key-withdrawal.php`. |
| Result | **Partly demonstrated.** The duplicate-refusal half was a Pass on MySQL while the key was live and is now an assertion about an absent index plus a present application rule; the drop-releases-the-seat half passes on both drivers. Stated as a retreat rather than left reading as a current server guarantee. |
| Evidence | `php storage/app/scratch/lane154-probe-duplicate-keys.php` (0 duplicate groups before the key, which is what made it safe to add), the two `1062` messages quoted in the enforcement migration's docblock, `DatabaseLevelEnrollmentRulesTest`'s own three cases, and the withdrawal migration's `down()`, which is the proof the key can come back. |
| Notes | Deliberately NOT a trigger: the guide allows either, and a generated column keeps the rule in the schema without a stored procedure to lose on restore. |

### T05 — capacity reached

| Field | |
|---|---|
| Description | A block with two seats is offered three students. |
| Preconditions | RefreshDatabase; a Blocking desk account; the block's own schedule. |
| Test data | `BlockCapacityAtomicityTest::blockWithSchedule(2)` plus three enrollments at the Blocking box. |
| Steps | POST all three; then POST the two that fit. |
| Expected | The three-student request is refused on `capacity` and writes nothing anywhere — no block stamp, no schedule stamp, no signed box. The two-seat request then succeeds. |
| Actual | As expected; the block ends holding exactly two distinct seats. |
| Logs / query output | the case names as the suite prints them — `a request larger than the seats left seats nobody`, `the audit names a block holding more students than it declares` — and the rule's own line from `php artisan ems:audit:record-states`: `ok A block holding more students than it declares 0`, with the command's footer `Rules run: 20`. |
| Result | **Pass**. |
| Evidence | `php storage/app/scratch/mutate-g-ads-capacity-tests.php` legs `dropped-rows-consume-a-seat`, `requested-ids-not-sorted`, `room-row-not-locked`. |

### T08 — controlled deadlock

| Field | |
|---|---|
| Description | A real 1213 occurs, and the act either clears it or refuses with nothing written. |
| Preconditions | MySQL 8 with two writers; the retry helper wrapped around one transaction. |
| Test data | Same stage as T03. |
| Steps | Run the pair; also drive `RetryOnDeadlock::run()` with a thrown 1213, a 1205, a 1062 and exhaustion. |
| Expected | 1213 and 1205 are retried a bounded number of times; every other condition propagates on the first pass; exhaustion refuses and says nothing was saved. |
| Actual | HEAD's act met a genuine `1213` in one pair of an earlier run and it surfaced as a 500; in the newest 3-pair run it met none and overbooked all three pairs instead. The locked act met none across three passes of three pairs. Unit cases confirm the four boundaries (1213 retried, 1205 retried, 1062 propagated, exhaustion refuses). |
| Logs / query output | `lane168-out.txt`: `working … deadlock 0`, `HEAD … deadlock 0`, `working-restored … deadlock 0` for the newest run — the deadlock column reads 0 here and read 1 in the earlier capture, which is the honest difference between a race and a schedule. |
| Result | **Pass** (the helper), and the deadlock is **observed, not simulated** — but observed intermittently: two writers deadlock only when the interleaving allows it, so the case pins the helper's boundaries and the probe pins the overbooking. |
| Evidence | the probe's `deadlock` column; `BlockCapacityAtomicityTest`'s four helper cases. |
| Notes | SQLite cannot deadlock, so no suite run reaches this code — which is why the cases hand it the exceptions instead of pretending the driver produced them. |

### T11 — popular-section contention

| Field | |
|---|---|
| Description | Several writers on the section everyone wants. |
| Preconditions | One block, one seat, N concurrent writers. |
| Test data | `--pairs=3` (six writers) against `maxStudents` 1. |
| Steps | As T03; the room row is locked second so two blocks sharing a room cannot cross either. |
| Expected | One winner per pair, always, and no invariant violation. |
| Actual | 3 of 3 pairs clean on the fixed act; 3 of 3 pairs overbooked on HEAD's (six writers, one seat each, 0 refusals). |
| Logs / query output | the same file's `working-restored` block: `pair: #53 assigned seats=1 342.3ms and #91 refused[capacity] seats=1 275.1ms`, then `=> assigned 3, refused 3, deadlock 0, other 0, clean pairs 3/3, max seats 1 of 1`. |
| Result | **Pass**. |
| Evidence | the probe's per-pair lines, which print each writer's status, its measured seats-after, and its milliseconds. |
| Notes | Practical scale, not a load test: the guide forbids uncontrolled load against a live system, and this ran on a clone. |

### T07 — concurrent student edit — **not executed**

| Field | |
|---|---|
| Description | Two authorized desks save the same student record at the same moment. |
| Preconditions | Two authenticated writers, one `students` row, and a version column or row lock the schema does not have. |
| Test data | The same record edited with two different telephone numbers in two processes. |
| Steps | Both read the record; both save; read the row back and see whose number is on the file. |
| Expected | The second save is refused with a named conflict, or nothing is silently lost. |
| Actual | Not run. `database/migrations` holds no `version`, `lockVersion` or `revision` column and no enrollment or student write takes a lock, so a run today would only photograph last-write-wins and present it as coverage. |
| Result | **Not executed**, and not executed because the protection does not exist — the case belongs with matrix P05, which is `Not built`. |
| Logs / query output | none, because nothing was run. The measurement behind the refusal is the grep itself: a version-shaped column name appears in no migration in the repository. |
| Evidence | P05's row in `REQUIREMENTS-TRACEABILITY-ADS.md`, and §4 of `DATABASE-CONCEPT-COMPLIANCE-ADS.md`, which keeps this as the honest half of the concurrency claim. |
| Notes | Prompt part 40 asks for this simulation and prompt part 39 forbids fabricating a result. Both are answered by writing the absence in the ten-field shape rather than inventing a run. |

### T02 — failure mid-commit — **not executed, and not reachable as posed**

| Field | |
|---|---|
| Description | A fault arrives after some subject lines are written but before the commit, and the record must not be left half-enrolled. |
| Preconditions | A way to fail inside the transaction the act opens. |
| Test data | The assign act's own enrollment ids, one of them impossible. |
| Steps | The first draft posted `enrollmentIds = […, 999999]` and believed it had produced a mid-loop failure. |
| Expected | Nothing is written anywhere when the act fails part-way. |
| Actual | Not reachable as posed: `enrollmentIds.*` is validated `exists:enrollments,enrollmentId`, so the bad id is refused before the transaction opens — the refusal the first draft counted as a mid-commit failure was a validation error, and the handler then renders it as a 500, which is not the state under test. |
| Result | **Not executed.** What is proven instead is the reachable half: T05's all-or-nothing refusal (a three-student request that seats nobody and leaves both free seats available) and a shape guard that the whole act sits inside one `DB::transaction`. |
| Logs / query output | none for the un-run case. T05's line in `l171-suite.log` reads `a request larger than the seats left seats nobody`, and the audit's `A block holding more students than it declares 0`. |
| Evidence | `tests/Feature/Blocking/BlockCapacityAtomicityTest.php`, and `mutate-g-ads-capacity-tests.php` legs that put the count back outside the transaction and watch the named case go red. |
| Notes | A mid-loop fault would need an injection seam the controller does not offer; adding one for a test is a change to the act, not evidence about it, so this row says unreachable rather than pretending a seam. |

---

## Group B — performance at volume (guide problems 8, 9, 23)

### T09 — student search at volume

| Field | |
|---|---|
| Description | The directory's name search stays usable as `students` grows. |
| Preconditions | `ems_l163_a`: live `ems`' structure, 100,000 synthetic students, 101,484 enrollments, 507,420 subject lines, capacity invariants intact. |
| Test data | needle `Scale12`; `LIMIT 20`; four spellings of the same question. |
| Steps | `lane164-measure-scale-queries.php` times each and prints the `EXPLAIN` row. |
| Expected | The plan names an index the query can use, or the gap is reported. |
| Actual | Newest capture: contains `10.54 ms` on `index:idx_students_names:rows=98795`; prefix `0.97 ms` `range:rows=1111`; exact school id `0.94 ms` `const:uq_students_schoolid:rows=1`; fulltext `14.74 ms` `fulltext:rows=1`. The first reading of the same command on the same schema answered 2.68, 0.53, 0.19 and 0.78 ms. The plans and their row estimates agreed both times; the milliseconds did not, so the claim this row carries is the ~89x difference in rows touched, and every timing is quoted with the run that printed it. |
| Logs / query output | `storage/app/scratch/lane164-out.txt`, the file the run writes: `contains '%Scale12%'  10.54 ms  rows=20` then `plan students:index:idx_students_names:rows=98795`, and `prefix 'Scale12%'  0.97 ms  rows=20` then `plan students:range:idx_students_names:rows=1111`. |
| Result | **Measured, not repaired.** The guide's premise is confirmed with numbers. |
| Evidence | the probe's table; `lane167-probe-capacity-index.php` for the two capacity counts. |
| Notes | The repair changes what a desk can find, so it is a school ruling; the fulltext index exists only on the scratch schema, added by the probe to price the option. |

### T10 — enrollment history retrieval

| Field | |
|---|---|
| Description | One student's whole history, and a 20-row page of it, against hundreds of thousands of rows. |
| Preconditions | Same schema. |
| Steps | Join history for one student; read subject lines for one student; then the same two reads once per row for 20 rows versus batched. |
| Expected | A single record reads from an index; the page does not multiply queries per row. |
| Actual | Newest run: one student joined `41.22 ms rows=1`; their lines `13.05 ms rows=5`; per-row loop `57.16 ms / 40 queries`; batched `6.23 ms / 2 queries`. The first run of the same command answered 0.80, 0.49, 8.31 and 0.77 ms. Both agree on what matters: every single-record read is an index seek, and the page costs 40 queries where 2 will do. |
| Logs / query output | the same capture file's lines `per-row loop for 20 rows  57.16 ms  (40 queries)` and `same two reads batched for 20 rows  6.23 ms  (2 queries)`, plus the plan string for the joined read: `e:ref:fk_enrollments_studentid:rows=1`, then `at:eq_ref:PRIMARY:rows=1` and `ay:eq_ref:PRIMARY:rows=1`. |
| Result | **Measured, not repaired** — the retrieval is fine, the queue's loop is the gap (matrix P09). |
| Evidence | the probe's P09 block. |
| Notes | The fix is code work, not a school question; the ceiling case belongs with a Registrar-queue test. |

### T23 / T24 — heavy traffic and the enrollment-day challenge — **executed 2026-11-01**

| Field | |
|---|---|
| Description | A thousand enrollment acts, submitted as close to simultaneously as this box can drive them, by staff desks rather than by students. |
| Preconditions | A scratch clone of live `ems` (`ems_l172_a`) carrying the new seat key, a busy term and program, one active office-4 desk account; the tool refuses any schema outside `ems_l172_[a-z]` and weighs live `ems`' row counts before and after the clone. |
| Test data | 1,000 synthetic students staged inside the clone (school numbers prefixed `LOAD-`), one pre-existing student for the contended seat, five pairs staged to `enrolled` so a Registrar may drop them. |
| Steps | `php storage/app/scratch/lane172-enrollment-day-load.php --requests=1000 --workers=10 --keep` — ten processes issue a hundred records each through `EnrollmentIssuer::issue()`, then ten workers issue for one seat at one start instant, then five pairs race a state-machine drop against a re-enrollment. Integrity is read from the server afterwards, not from the children's arithmetic. |
| Expected | Every act commits whole or is refused; no student holds two active seats in one term; no workflow form outlives the transaction that made it; legitimate business refusals counted apart from technical errors. |
| Actual | 1,000 created, 0 refused, 0 error in 14.45 s — 69.2 acts per second, median 113.6 ms per request with ten workers resident. Contention on one seat: 1 winner, 9 refusals, exactly one active seat afterwards. The five cancel races left 2 students with one active seat, 3 with none (the drop won and its paired issue lost, which is legal and is what a desk re-runs), and **0 with more than one**. |
| Logs / query output | the capture `storage/app/scratch/lane172-load-out.txt`: `duplicate active seats 0`, `orphan workflow forms 0`, `committed acts without a form 0`, `records with two forms 0`, `blocks over declared capacity 0`, `driver 1062 12`, `session isolation REPEATABLE-READ`, `innodb_lock_wait_timeout 50 s`, `threads_connected 1`, `max_used_connections 11`, `L172_EXIT=0`. |
| Result | **Pass**, on the creation path and no further. The capacity race under concurrent writers is `lane168`'s act (T03/T11); the lost-update case (P05) and the consistent-read report case (P20) are not built, so this row does not claim them. |
| Evidence | `storage/app/scratch/lane172-enrollment-day-load.php` and `lane172-issue-child.php`; the integrity questions are falsified by `mutate-lane172-load-integrity.php`, which damages a copy of this very schema and requires the same tool to name each defect. |
| Notes | The 12 refusals are all driver 1062 — the business rule refusing a second seat — and prompt part 40 says a high rejection rate is not a failure when the rejection is correct. The tool prints legitimate refusals and technical errors in separate columns for that reason; a single "failed" count would mix the rule working with the machine breaking. |

---

## Group C — constraints, failures, and recovery

### T12 invalid references, T13 missing prerequisite, T19 duplicate student id, T23 unauthorized access

Already covered by cases in the repository — `exists:` validation on every foreign key, `EnrollmentReadinessTest`
for the prerequisite gate, intake validation plus `uq_students_schoolid` for the identity, and
`PermissionMatrixTest` with the policy cases for access. They are listed in the matrix rather than duplicated here,
which is the guide's "reuse existing evidence" instruction and this repository's rule against two answers to one word.
**Logs / query output** for all four is the same run: their case names are printed line by line in
`storage/app/scratch/l171-suite.log`, and the counts per class come out of that run's junit through
`lane107-retake-class-figures.php` rather than being retyped here.
**Logs / query output:** the four boundary cases print in `l171-suite.log`, and the load run's `driver 1062 12` line
in `lane172-load-out.txt` is the field evidence that a server refusal is classified as a business rejection rather
than a technical error — the distinction prompt part 40 asks for.

### T22 — backup and restore

| Field | |
|---|---|
| Description | A dump taken from the live schema is restored and the rows compared. |
| Preconditions | `mysqldump` and `mysql` binaries; a scratch target named `ems_drill[_a-z]`. |
| Steps | `php tools/ems-backup-drill.php --target=ems_drill` — dump `--single-transaction`, refuse any dump carrying `DROP DATABASE`, restore, compare every base table by `COUNT(*)`, then three ordered digests. |
| Expected | Every table matches, and the durability settings the recovery promise depends on are printed. |
| Actual | 64 tables compared; `students`, `enrollments` and `payments` digests `MATCHES`; `innodb_flush_log_at_trx_commit = 1`, `sync_binlog = 1`. |
| Logs / query output | the drill's own footer, quoted where it is kept in `Documentation/SECURITY-AND-RECOVERY-ADS.md` §2: `VERDICT: 0 table(s) missing, 0 row-count or checksum difference(s), dump 1,415,647 bytes, restore exit 0`, above the two durability lines `innodb_flush_log_at_trx_commit  1` and `sync_binlog  1`. |
| Result | **Pass**, as an executed drill — recorded in `SECURITY-AND-RECOVERY-ADS.md`. |
| Notes | No scheduled job and no point-in-time recovery exist; the document says so rather than implying the drill is a policy. |

### T17 DB exception mid-flow, T18 interruption and retry — **executed for the retry half**

The exception taxonomy is pinned (`App/Support/RetryOnDeadlock` retries 1213 and 1205 only, propagates everything
else, refuses `attempts: 0`, and says *Nothing was saved*). The interruption-and-retry half is now built and pinned by
`tests/Feature/Enrollment/EnrollmentRequestKeyTest.php`: a replay of one `requestKey` answers the enrollment the first
send created and opens no second workflow form; the UNIQUE index refuses a second row carrying that key **on the
driver the suite runs on**, which is the property the withdrawn seat key never had; two unkeyed acts stay two acts; a
key that arrives naming a different student or term is refused rather than answered with somebody else's record; and
the issuing page is read to prove the desk actually puts the key on the wire. What still no case does is kill a real
process between the write and the commit — that belongs to the load harness (`lane172`), which drives separate
processes but does not interrupt one mid-transaction, and is stated as a limit rather than papered over. Proved by
`storage/app/scratch/mutate-p17-idempotency-tests.php`, one leg per promise, including the leg that takes
`requestKey` out of `Enrollments::$fillable` — the silent failure, since mass assignment drops an unlisted field with
no error at all.

---

## Group D — the rest of the twenty-four

| Test | Scenario | State | Logs / query output |
|---|---|---|---|
| T01 | multi-subject commit | not executed — the enrolment act's happy path runs in `EnrollmentIssuer` tests; no case counts the subject lines written by one commit | none yet; the count would come from a case that does not exist, and quoting a number from the seed instead would be the fabricated result prompt part 39 forbids |
| T06 | multi-subject cancellation | not executed — `drop()` is one transaction; no case asserts the line set after a refusal | none yet; `RegistrarController.php:368` is the act a future case would drive |
| T14 | load exceeded | not executed — the elective-band rule exists; the ceiling is a school figure | none; the band rule's own message is the only text a run could print, and P13 is the open figure |
| T15 | faculty load | not executed — no limit is defined (matrix P14), so there is no expectation to assert | none; nothing in `app/` holds a faculty-load predicate to fail |
| T16 | schedule conflict | partly — rooms, officers and the block's own hour are covered by `TimetableConflicts` and its tests; two blocks for one student are not (P15) | the suite's own lines in `l171-suite.log` for the three covered branches; the two-block case is the gap |
| T20 | deletion and restoration | not executed — no restore path exists, and the guide's own question is open with the school (P19) | none; `lane43-delete-guards.php` prints the 72 refusing keys, which is the measurement behind the absence |
| T21 | report during enrollment | executed for the daily collection report — both `payments` reads sit inside one transaction, so the sheet is one snapshot; the Registrar queue and the dashboard tiles are named in matrix P20 as still reading outside one | the case's own name in the run, `the_daily_report_takes_its_two_reads_inside_one_transaction`, and `lane172-load-out.txt`'s `session isolation REPEATABLE-READ`, which is the default the case relies on and deliberately does not change |

---

## What the suite says as a whole

The run that contains this batch's cases is
`storage/app/scratch/l171-suite-junit.xml` / `l171-suite.log`, read on 2026-11-01. Its
`Tests:` line, the discovery count from `artisan test --list-tests`, and the per-class figures for
`BlockCapacityAtomicityTest` and `DatabaseLevelEnrollmentRulesTest` are the three readings that must agree, and the
reason no total is quoted in this file's prose: an assertion count is a property of a run, and the two MySQL and
SQLite jobs answer different ones. The three do agree: the log answers 1212 passed with no failure and no skipped
line, `--list-tests` discovers 1212, and the junit carries the per-class entries the retake tool reads.

The run before it, `l170-suite.log`, answered two failures, and both were this batch's own consequences rather than
unrelated rot — recorded here because each is a finding:

- `DemoDatasetRecordStatesTest` asserted the record-state audit prints nineteen rules; the batch added a twentieth
  (`A block holding more students than it declares`), and the guard went red with "actual size 20 matches expected
  size 19". The expectation moved with the register; the command's own footer (`Rules run: 20`) is where the number
  lives.
- `EnrollmentWalkthroughTest > transferee with credit transfer completes pipeline` died with
  `SQLSTATE[23000] … 1062 Duplicate entry '1487-11-1487:11' for key 'enrollments.uq_enrollments_active_seat'`. The
  case approved an admission — which issues the enrollment — and then minted a second enrollment for the same
  student and term through a helper that wrote the table directly, so the controller's `seatHolder()` guard never
  ran. `DatabaseTransactions` rolled the pair back inside the test, which is why no census of live `ems` had ever
  seen the shape. The fixture now walks the record the approval issued; the gate's rule was not softened.
