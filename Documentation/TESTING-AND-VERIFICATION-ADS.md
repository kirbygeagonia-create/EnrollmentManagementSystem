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
| Preconditions | A migrated schema. `uq_enrollments_active_seat` is on MySQL and cannot be on SQLite: the key was delivered on 2026-11-01, **withdrawn the same day** when CI's MySQL job showed the fixtures writing the state it forbids, and **returned the same day** after the census found eight fixture files holding that habit, each was repaired, and the repaired files rehearsed green on a scratch MySQL schema. |
| Test data | One student, one term, one course; the second row written with `enrollmentStatus` `pending` so no code path vetoes it. |
| Steps | `DatabaseLevelEnrollmentRulesTest`: insert the held seat, ask `EnrollmentIssuer::seatHolder()` about it, set it to `dropped`, ask again, then write the second row. |
| Expected | The application rule answers the held row and answers nothing once it is dropped, and the re-enrollment succeeds. A server-side refusal is expected **only** on a driver whose schema holds a key. |
| Actual | Both directions, twice. While the key was live the first time, MySQL (`ems_l170_a`, and live `ems` after that migration) refused the second insert with `uq_enrollments_active_seat` and accepted it after the drop. After the return the same pair was re-measured on a scratch MySQL schema with the connection pinned — the delivered rehearsal answers the refusal by key name and then the re-enrollment succeeding — and live `ems` carries the index today. On SQLite the case asserts the fee-sheet index present and the seat index absent, and says out loud that no database refusal is being claimed there. SQLite could never have held it — `ALTER TABLE … ADD COLUMN … GENERATED` is unsupported there, which is the asymmetry that made CI the only witness and the withdrawal necessary. |
| Logs / query output | the server's own words from the day the key was first live, quoted in the enforcement migration's docblock and again in `l171-suite.log`: `SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1487-11-1487:11' for key 'enrollments.uq_enrollments_active_seat'` — the shape that made the walkthrough fixture's double-mint visible after months of green. `SHOW INDEX FROM enrollments WHERE Key_name='uq_enrollments_active_seat'` on live `ems` after the return answers three rows, `studentId`, `termId`, `seatKey`, and `information_schema.COLUMNS` answers `VIRTUAL GENERATED` for the column; the withdrawal's own zero-row reading is kept in `storage/app/scratch/lane174-rehearsal-seat-key-withdrawal.php`, which is what proved the retreat reversible. |
| Result | **Demonstrated on MySQL, asserted absent on SQLite.** The duplicate-refusal half passes on the school's engine, the drop-releases-the-seat half passes on both drivers, and the application predicate is pinned where the suite can execute it. Both the retreat and the return are in the record rather than smoothed into one tense. |
| Evidence | `php storage/app/scratch/lane154-probe-duplicate-keys.php` (0 duplicate groups before the key, which is what made it safe to add), the two `1062` messages quoted in the enforcement migration's docblock, `DatabaseLevelEnrollmentRulesTest`'s cases on both drivers, `storage/app/scratch/lane187-census-fixture-seats.php` for the eight fixture files and its falsifier `storage/app/scratch/mutate-lane187-provenance.php`, and the withdrawal migration's `down()`, which is the proof the key could come back and did. |
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
| Notes | Deliberately not a `CHECK` constraint: the ceiling is held by the block row lock, the re-count under it, the room lock taken second in one fixed order, and the bounded `RetryOnDeadlock`; the range constraints the guide asks for stay matrix P11's open question, and `lane168` is what shows the race in both directions rather than this row asserting it. |

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

### T07 — concurrent student edit — **executed for the guard, not for two processes**

| Field | |
|---|---|
| Description | Two authorized desks save the same student record at the same moment. |
| Preconditions | Two authenticated writers and one `students` row — and the version column this row once said the schema does not have: `students.rowVersion` is `NOT NULL default 1`, added as matrix P05, and every desk save that touches the person record carries it. |
| Test data | The same record edited with two different telephone numbers, one save sent with the version the first read returned and one with the version already superseded. |
| Steps | Read; save; read again; save with the stale version; read the row back and see which number is on the file and what the version says. |
| Expected | The second save is refused with a named conflict and the newer save survives. |
| Actual | **Refused as designed**, on one connection. `tests/Feature/Evaluation/ProfileCaptureTest.php` carries four cases: `a_stale_version_is_refused_and_the_newer_save_survives`, `a_successful_save_bumps_the_version_by_exactly_one`, `a_payload_without_a_version_is_refused_rather_than_overwriting`, `the_version_is_not_something_a_payload_may_write_directly`. What they cannot show is two processes in the same instant, because one connection cannot contend with itself — that half is the shape `lane190` now uses for the subject act and has not yet been driven for the person record. |
| Result | **Pass** for the lost-update rule; the simultaneous-process demonstration is owed and is named as owed rather than folded into this pass. |
| Logs / query output | the four names above were read out of `tests/Feature/Evaluation/ProfileCaptureTest.php` itself, not recalled; the class runs inside the delivered whole-suite run, and no assertion total is quoted here, because a total belongs to the run that printed it. |
| Evidence | P05's row in `REQUIREMENTS-TRACEABILITY-ADS.md` (now `Built`), `tests/Support/DeskStudentPayload.php` for the payload shape, and §4 of `DATABASE-CONCEPT-COMPLIANCE-ADS.md` for the concurrency claim this row belongs to. |
| Notes | Prompt part 40 asks for this simulation and prompt part 39 forbids fabricating a result. Both are answered by writing the absence in the ten-field shape rather than inventing a run. |

### T02 — failure mid-commit — **executed at the database boundary**

| Field | |
|---|---|
| Description | A fault arrives after some rows are written but before the commit, and the record must not be left half-enrolled. |
| Preconditions | A scratch schema seeded from live `ems`, an active staff account to sign the act, and a term an enrollment already sits on. `lane198-probe-forced-rollback.php` refuses if any of those is missing. |
| Test data | Two fresh students cloned from an active row, each with its own `requestKey` (`RBK-A-…`, `RBK-B-…`), so the two acts cannot be confused with each other or with the schema's standing rows. |
| Steps | (1) Drive `App\Services\EnrollmentIssuer::issue()` once to completion and count `enrollments`, `enrollmentworkflow`, `workflowsteps` before and after. (2) Drive the same act again with a query listener registered on the same connection that throws the first time it sees the workflow-form insert, then re-read all three counts and ask whether the failed key left any row. |
| Expected | The first act leaves one enrollment, one form and the box rows that form names. The second leaves nothing at all: no enrollment under its key, none for that student, and no movement in any of the three tables. |
| Actual | **Both halves observed.** The commit added exactly +1 enrollment, +1 form, +7 boxes. The fault fired once, after the enrollment row was already visible on that connection (the probe measures this at the instant of the fault rather than assuming it from statement order), the act threw `RuntimeException`, and the re-read answered 0 rows for that key, 0 for that student, and +0/+0/+0 across the three tables. |
| Result | **Pass.** The guide's failure scenario is demonstrated on the engine, at the boundary the application does not control. What is *still* not shown is a real power loss or a killed connection, which is T19 and P21's question rather than this row's. |
| Logs / query output | `storage/app/scratch/lane198-out.txt`, four lines and a verdict, taken on `ems_l197_a`: `COMMIT key …: enrollment 1, form 1, boxes 7 (tables +1/+1/+7) ok`, `ORDER … YES (so the rollback had something to undo)`, `ROLLBACK key …: enrollments 0, that student's enrollments 0; tables +0/+0/+0 nothing survived`. |
| Evidence | `storage/app/scratch/lane198-probe-forced-rollback.php` (the instrument), `storage/app/scratch/mutate-lane198-rollback-probe.php` (its falsifier), this row, and guide problems 1 and 16 in `GUIDE-PROBLEM-ANSWERS-ADS.md`. |
| Notes | This row used to read "not reachable as posed", and the reason it stopped being true is worth keeping: that judgement looked for an **input** that fails mid-loop, and the assign act validates `enrollmentIds.*` with `exists:` before its transaction opens, so no bad id ever reaches it. A real mid-transaction fault is a **database** event (a deadlock, a lost connection, a refused write), and Laravel notifies a query listener after each statement inside that same transaction. Throwing there adds no seam to the act; it is the failure arriving where the act cannot choose whether to see it. |

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
| Test data | The synthetic scale schema `storage/app/scratch/lane163-build-scale-schema.php` builds (100,000 students) on this box, one student holding a joined enrollment history and five confirmed subject lines, and the 20-row Registrar queue page the probe reads twice — once per row, once batched. |
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
| Result | **Pass**, on the creation path and no further. The capacity race under concurrent writers is `lane168`'s act (T03/T11). The lost-update case is now built as P05 and pinned by four cases in `ProfileCaptureTest` (T07), and the daily report reads inside one transaction as P20 (T21) — but this run claims neither, because it drove the issue path only, and the subject-line act that `lane190` measured the same way still has no retry (P10). |
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
| Test data | A `--single-transaction` dump of live `ems` as it stands on this box — 64 tables, the 1.42 MB the drill's own footer reports — restored into a scratch schema the tool was told to own, never into the source. |
| Steps | `php tools/ems-backup-drill.php --target=ems_drill` — dump `--single-transaction`, refuse any dump carrying `DROP DATABASE`, restore, compare every base table by `COUNT(*)`, then three ordered digests. |
| Expected | Every table matches, and the durability settings the recovery promise depends on are printed. |
| Actual | 64 tables compared; `students`, `enrollments` and `payments` digests `MATCHES`; `innodb_flush_log_at_trx_commit = 1`, `sync_binlog = 1`. |
| Logs / query output | the drill's own footer, quoted where it is kept in `Documentation/SECURITY-AND-RECOVERY-ADS.md` §2: `VERDICT: 0 table(s) missing, 0 row-count or checksum difference(s), dump 1,415,647 bytes, restore exit 0`, above the two durability lines `innodb_flush_log_at_trx_commit  1` and `sync_binlog  1`. |
| Result | **Pass**, as an executed drill — recorded in `SECURITY-AND-RECOVERY-ADS.md`. |
| Evidence | `php tools/ems-backup-drill.php --target=ems_drill` prints the verdict quoted above and refuses the run rather than proceeding when the dump carries a `DROP DATABASE`, when the target is a name it was not told to own, or when the file it wrote holds no `CREATE TABLE` for the schema it claims to copy; its output is kept in `Documentation/SECURITY-AND-RECOVERY-ADS.md` §2 rather than re-typed here. |
| Notes | No scheduled job and no point-in-time recovery exist; the document says so rather than implying the drill is a policy. |

### T17 DB exception mid-flow, T18 interruption and retry — **executed on both halves**

The exception taxonomy is pinned (`App/Support/RetryOnDeadlock` retries 1213 and 1205 only, propagates everything
else, refuses `attempts: 0`, and says *Nothing was saved*). The mid-flow exception is now **observed, not argued**:
`storage/app/scratch/lane198-probe-forced-rollback.php` registers a query listener on the act's own connection, lets
the enrollment row be written, throws at the next statement, and re-reads `enrollments`, `enrollmentworkflow` and
`workflowsteps` to report `+0/+0/+0 nothing survived` — the capture is `storage/app/scratch/lane198-out.txt`, and
`storage/app/scratch/mutate-lane198-rollback-probe.php` proves the probe refuses when the fault never fires and when
it fires before any write. The interruption-and-retry half is built and pinned by
`tests/Feature/Enrollment/EnrollmentRequestKeyTest.php`: a replay of one `requestKey` answers the enrollment the first
send created and opens no second workflow form; the UNIQUE index refuses a second row carrying that key **on the
driver the suite runs on**, which is the property the generated-column seat key cannot have; two unkeyed acts stay two acts; a
key that arrives naming a different student or term is refused rather than answered with somebody else's record; and
the issuing page is read to prove the desk actually puts the key on the wire. What still no case does is kill a real
process between the write and the commit — the injected fault arrives at the query boundary inside a live process,
which is the transaction's own failure path but not the machine losing the worker. That half belongs to the load
harness (`lane172`), which drives separate processes but does not interrupt one mid-transaction, and is stated as a
limit rather than papered over. Proved by
`storage/app/scratch/mutate-p17-idempotency-tests.php`, one leg per promise, including the leg that takes
`requestKey` out of `Enrollments::$fillable` — the silent failure, since mass assignment drops an unlisted field with
no error at all.

---

## Group D — the rest of the twenty-four

| Test | Scenario | State | Logs / query output |
|---|---|---|---|
| T01 | multi-subject commit | executed 2026-11-02 — two cases in `tests/Feature/Evaluation/RetakeAttemptTest.php`: `a_multi_subject_commit_writes_every_line_it_names` (a two-subject proposal leaves 2 proposed lines and exactly one `→ evaluated` row in `enrollmentstatushistory`) and `a_refused_proposal_leaves_the_existing_load_in_place` (a payload naming one subject twice is refused and the record's existing line survives, unmoved). Killed both ways by `php storage/app/scratch/mutate-g-t01-subject-commit.php`: inserting only the first line of the payload fails the first case with its own sentence, and disabling the duplicate-payload guard fails the second — each restore verified by hash, and the file answers 10 passed / 37 assertions on the clean tree |
| T06 | multi-subject cancellation | executed 2026-11-02 — nine cases in `tests/Feature/Registrar/EnrollmentDropTest.php`, of which the guide's own third question is `a fault between the two writes of a drop leaves the load and the status exactly as they were`: a query listener throws the moment the load retire is issued, after the status transition and its history row are already written on that connection, and the case re-reads the status, every line's status, the reason column and the history count. It refuses its own run if the injection never fired or if the controller swallowed the throw | the case is in the delivered run; `php storage/app/scratch/mutate-t06-drop-atomicity-tests.php` answers `ALL KILLED`, planting the transaction removed, the load update hoisted out of it, and the fault caught by the controller — each required to make that case fail **naming its own assertion**, because a plant that does not compile also prints `1 failed`. Control green before and after, controller restored by hash; the harness prints its own leg total. `RegistrarController.php:382` is the act it drives |
| T14 | load exceeded | not executed — the elective-band rule exists; the ceiling is a school figure | none; the band rule's own message is the only text a run could print, and P13 is the open figure |
| T15 | faculty load | not executed — no limit is defined (matrix P14), so there is no expectation to assert | none; nothing in `app/` holds a faculty-load predicate to fail |
| T16 | schedule conflict | partly — rooms, officers and the block's own hour are covered by `TimetableConflicts` and its tests; two blocks for one student are not (P15) | the suite's own lines in `l171-suite.log` for the three covered branches; the two-block case is the gap |
| T20 | deletion and restoration | not executed — no restore path exists, and the guide's own question is open with the school (P19) | none; `lane43-delete-guards.php` prints the 72 refusing keys, which is the measurement behind the absence |
| T21 | report during enrollment | executed for the daily collection report — both `payments` reads sit inside one transaction, so the sheet is one snapshot; the Registrar queue and the dashboard tiles are named in matrix P20 as still reading outside one | the case's own name in the run, `the_daily_report_takes_its_two_reads_inside_one_transaction`, and `lane172-load-out.txt`'s `session isolation REPEATABLE-READ`, which is the default the case relies on and deliberately does not change |

---

## What the suite says as a whole

The run that contained the 2026-11-01 batch's cases is
`storage/app/scratch/l171-suite-junit.xml` / `l171-suite.log`, read on 2026-11-01. Its
`Tests:` line, the discovery count from `artisan test --list-tests`, and the per-class figures for
`BlockCapacityAtomicityTest` and `DatabaseLevelEnrollmentRulesTest` are the three readings that must agree, and the
reason no total is quoted in this file's prose: an assertion count is a property of a run, and the two MySQL and
SQLite jobs answer different ones. The three do agree: the log answers 1212 passed with no failure and no skipped
line, `--list-tests` discovers 1212, and the junit carries the per-class entries the retake tool reads.

The run that contains the 2026-11-02 cases — the rollback case on the issuing act's sibling, `T02`'s probe being an
instrument rather than a phpunit case, and the forced-failure case on the cancellation act — is
`storage/app/scratch/l199-suite-junit.xml` / `l199-suite.log`. On this box it answers **1232 passed with 7,695
assertions**, `SUITE_EXIT=0`, no failure and no skip, and `artisan test --list-tests` discovers the same 1232, which
is the check that the inserted method did not steal the `#[Test]` attribute of the case below it. One surface is
named, deliberately: the runner answers a different assertion total for the same code, and a sentence that carried
both would be one claim judged on its first figure.

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
