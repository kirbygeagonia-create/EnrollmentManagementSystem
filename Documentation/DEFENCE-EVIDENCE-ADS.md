# Final defence preparation — the 1,000-request question

Requirement: `Enrollment_Management_System_Master_Implementation.md` prompt part 45. Written 2026-11-01 against this checkout,
with every claim pointing at code, SQL, a configuration, a test or a measured run. The guide says not to rely on
theory; where something is theory here it is labelled as not built.

> **"If 1,000 enrollment requests are submitted at the same time, how can you prove that your database will keep
> enrollment records accurate, consistent, secure, and reliable, even if enrollment is processed by registrar staff
> rather than directly by students?"**

**The short answer.** Three of the four words in that question are enforced by the database, not by the php: the
seat a student holds, the fee sheet that prices it, and the subject line a student may not take twice are unique
keys. The fourth — *reliable* — is a transaction boundary plus a row lock, so that two desks cannot both read the
same last seat as free, and a bounded retry that turns the loser's 1213 into a second attempt instead of a 500.
What makes the claim provable rather than plausible is that the race was run: two real writers on one MySQL server,
against the pre-fix code and against the fixed code, and the pre-fix code overbooked.

---

## 1. How transactions protect enrollment operations

`EnrollmentIssuer::issue()` writes the enrollment, its workflow form and its requirement rows inside one
`DB::transaction`, so a record is never on file without the form the nine desks sign.
`BlockingController::assignStudents()` at `app/Http/Controllers/Blocking/BlockingController.php:484` opens its
transaction *before* it reads the block's held seats (`:488-491`) and closes it after the last box is signed, and
`RetryOnDeadlock::run()` at `:483` wraps the whole thing.

The point of the ordering is the failure mode: a capacity check that runs outside the transaction it guards is a
guess, because nothing makes the second reader wait. That is not an argument here — it is the measured difference
between HEAD's bytes and this batch's, in part 9 below.

## 2. How ACID properties are maintained

| Property | Where it lives | How it is shown |
|---|---|---|
| Atomicity | one transaction per enrollment act; `assignStudents()` at `:484`, `RegistrarController::drop()` in one transaction | a refused or over-large request leaves no block stamp, no schedule stamp and no signed box — `BlockCapacityAtomicityTest` |
| Consistency | 12 unique keys in 10 tables (§23.6.1), 105 foreign keys, `ems:audit:record-states` now 20 rules | the audit answers 0 on every `impossible` rule against live `ems`; the 20th weighs block seats against `maxStudents` |
| Isolation | row locks on the write path (`:485`, `:500`); **not** a transaction isolation level | the seat and sheet keys make the two racing writes mutually exclusive; a report still reads across statements, which is matrix P20 and is named as open |
| Durability | `innodb_flush_log_at_trx_commit = 1`, `sync_binlog = 1`, read from the server by `tools/ems-backup-drill.php` | the same drill restored a dump and compared 64 tables, three of them by ordered digest |

## 3. How concurrent enrollment is controlled

Pessimistically, on the row that carries the scarce resource, for the shortest span that can answer the question:

```sql
-- the act's first statement, inside the transaction
SELECT * FROM enrollments_blocks WHERE blockId = ? FOR UPDATE;
-- only now is the count taken, and it cannot change under us
SELECT COUNT(DISTINCT enrollmentId) FROM enrolledsubjects
 WHERE blockId = ? AND status <> 'dropped';
```

That is `lockForUpdate()` at `:485` and the count at `:488-491`. A `SELECT … FOR UPDATE` on the block row makes the
second writer wait for the first transaction to finish rather than let it read a stale number — which is the whole
defence of "one seat, two claims". Optimistic versioning was considered and is **not** implemented for the student
record (matrix P05); the seat does not need it because the key below refuses even a writer that never asks.

## 4. How section capacity is protected

Two independent halves:

- **Write side:** the count above is compared with `blocks.maxStudents` *inside* the lock, and the request is
  refused naming the three numbers (`current`, `max`, `requested`) so the desk can see why.
- **Read side:** the audit's twentieth rule, `blockOverCapacity()` in `app/Console/Commands/AuditRecordStates.php`
  at `:453-464`, weighs every block's held seats against its own declared capacity. A block that got over its
  limit from a restored dump or a hand-edited row is reported instead of believed.

Both count the same way: distinct enrollments, dropped lines excluded. Two definitions of "held" between a guard and
a reconciliation is how a check passes while the rule it guards is broken.

## 5. How duplicate enrollment is prevented

By the application **and by the server**, and the reader should know that the server half retreated for an afternoon.
Since 2026-11-01 the rule is `EnrollmentIssuer::seatHolder()` plus the reconciliation rule in `ems:audit:record-states`
plus `uq_enrollments_active_seat`: the generated-column key was delivered, withdrawn the same day when CI's MySQL job
showed the fixtures writing the state it forbids, and returned the same day once those fixtures were repaired — so this
section states what holds today and keeps the retreat in the record rather than in a footnote:

```sql
-- delivered on 2026-11-01 by add_database_level_enrollment_rules, withdrawn the same day by
-- 2026_11_01_1600000000_withdraw_the_active_seat_key_until_the_fixtures_are_repaired.php, and returned the same day by
-- 2026_11_01_1900000000_return_the_active_seat_key_now_that_fixtures_hold_one_seat_per_term.php.
-- Re-read on live `ems` after that migration: SHOW INDEX answers the three-column unique key over
-- studentId, termId, seatKey, and information_schema answers `VIRTUAL GENERATED` for the column.
ALTER TABLE enrollments
  ADD COLUMN seatKey VARCHAR(32) GENERATED ALWAYS AS (
    CASE WHEN enrollmentStatus <> 'dropped' AND termId IS NOT NULL
         THEN CONCAT(studentId, ':', termId) ELSE NULL END
  ) VIRTUAL,
  ADD UNIQUE KEY uq_enrollments_active_seat (studentId, termId, seatKey);
```

`seatKey` was non-null exactly when the row held a seat, and MySQL ignores NULLs in a unique index, so **a drop
releases the seat** with no trigger and no deleted history — the Registrar may enroll the student they dropped in the
same term, which is ruling 17's behaviour. It was withdrawn because six test files each wrote two active seats for
one student and one term, a state the key refuses and the product forbids, and CI's MySQL job was the only surface
that could see them: the suite's SQLite schema compiles no `ADD COLUMN … GENERATED`, so the key has never existed
there and local green said nothing about it. The key came back the same day, and the count it came back on is the
census's rather than the failure message's: `storage/app/scratch/lane187-census-fixture-seats.php` reads every
enrollment call site in `tests/`, binds the person and the term to where each came from, and answers **eight** files
holding the habit; `storage/app/scratch/mutate-lane187-provenance.php` proves that reading with one leg per promise.
Each file was repaired to name a later term, drop the prior seat, or take a second student, the repaired files
rehearsed green on a scratch MySQL schema with the connection pinned, and
`2026_11_01_1900000000_return_the_active_seat_key_now_that_fixtures_hold_one_seat_per_term.php` re-added column and
index behind a duplicate guard that names any student still holding two active seats, so the migration refuses rather
than half-applies. Before the key and after it, the rule was one php method (`EnrollmentIssuer::seatHolder()`), which a
second route or a concurrent request could walk past — which
is why the locking in part 9 and the audit rule are stated beside it rather than underneath it.
`studentassessments` gained `uq_studentassessments_enrollment` on the same day and keeps it, closing the review
document's DI-2: that key is portable, so it holds on both drivers.

## 6. How student academic-load limits are enforced

Partly, and the gap is named rather than papered. Both rules that do exist live in the proposal act,
`Evaluation\EvaluationController::proposeSubjects()`: a chosen elective group outside its curriculum row's
`elective_min_choices`/`elective_max_choices` is refused, and a non-elective curriculum subject that was never
proposed is refused too — minus the subjects an **approved** credit row exempts, because a credit the department
declined does not drop its requirement. The band is pinned by `tests/Feature/Evaluation/ElectiveValidationTest.php`
(11 cases, including the group sizes exactly at the minimum and exactly at the maximum, which pass).
`App\Support\EnrollmentReadiness` is not one of those guards: it answers
clearance, verified documents and prerequisites, and says nothing about a load. There is
**no term unit ceiling**, because the number is the school's to state (matrix gap **P13**, still `Not built`, and one
of the items on the open school-question register — `lane69-open-inventory.php` answers what waits on an office). The honest answer in a defence is "the rule is a figure we have not been
given; inventing 21 units would be data the school never approved."

## 7. How prerequisite requirements are checked

`App\Support\EnrollmentReadiness` answers per subject whether the prerequisites are satisfied, reading credited
subjects through `Creditedsubjects::approved()` — the one definition of "counts" that the prerequisite gate, the
mandatory-block diff and the standing derivation all share, so three readers cannot disagree. Pinned by
`tests/Feature/Evaluation/CreditEquivalencyDecisionTest.php`. A credit a department declined does not satisfy a
prerequisite, which is the case that keeps the shared scope honest.

## 8. How schedule conflicts are detected

`App\Support\TimetableConflicts` asks three questions of one builder — the same officer at two hours, the same room
at two hours, and **the block itself** meeting in two places at once — all scoped inside the block's own term,
because `schedules` carries no term and rooms and officers are deliberately reused across years. Half-open
comparison, so a class starting exactly when another ends is back-to-back and not a collision. The same two rules
exist as audit rules so a write path that bypasses the form is visible; pinned by `AuditRecordStatesTest`.

## 9. How locking and deadlock handling work

`App\Support\RetryOnDeadlock.php` retries **only** 1213 and 1205, reads the code from `errorInfo[1]` before
`getCode()` (the two drivers word a refusal differently), bounds the attempts, jitters the wait so two losers do not
collide again on the same tick, and on exhaustion raises a validation message that says *Nothing was saved — please
try again*. A duplicate key (1062), a missing table or a dropped connection propagates on the first pass: repeating
a condition that will never clear is a silent loop with a name.

**The measured part.** `storage/app/scratch/lane168-probe-last-seat-race.php` cloned live `ems`, staged one block
at exactly one free seat, and started two processes at the same instant. Three pairs on this batch's bytes:
3 assigned, 3 refused on `capacity`, 0 deadlocks, max 1 seat of 1. Three pairs on HEAD's bytes: **5 assigned,
0 refused, one block holding 2 seats against `maxStudents` 1**, and one pair ended in

```
SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock
```

which is the guide's deadlock, produced by the pre-fix code, on the school's engine — surfacing at the desk as a 500.

## 10. How database constraints preserve integrity

105 foreign keys, of which 71 refuse a delete (NO ACTION), 29 set null, 4 cascade (the RBAC pivots a seeder prunes)
and 1 restricts — read from `information_schema.REFERENTIAL_CONSTRAINTS` on 2026-11-01 rather than from this
document's own earlier sentences, which carry two different older readings; every desk delete answers those with a
hand-written list that `lane43-delete-guards.php` weighs against the server, because a generic "reload and retry"
page names no record. 12 unique keys in 10 tables are
listed exhaustively in §23.6.1 and `lane44-unique-keys.php` refuses the run when the list and the server differ.
Enums are real (`enrollmentStatus`, `paymentStatus`, …) and `SQLiteGrammar::typeEnum` carries the CHECK into the
suite's schema, so a value outside the domain is refused on both drivers.

## 11. How indexing and query optimization improve performance

Measured on a scratch schema of 100,000 students, 101,484 enrollments and 507,420 subject lines. The column that
settles the argument is the plan, not the millisecond: this command run twice on the same schema answered 2.68 ms
and 10.54 ms for the first row below, 0.53 ms and 0.97 ms for the second, 8.31 ms and 57.16 ms for the per-row page —
so a timing is a property of the run that printed it, while the rows each plan expects to touch agreed both times.
The newest capture is saved at `storage/app/scratch/lane164-out.txt`.

| Query | Plan the server chose | Rows it expects to touch |
|---|---|---|
| name `LIKE '%Scale12%'` (what the code does today) | `index:idx_students_names` — a leading wildcard cannot use the index left-to-right | ~98,800 |
| name `LIKE 'Scale12%'` | `range:idx_students_names` | ~1,111 |
| school id exact | `const:uq_students_schoolid` | 1 |
| one student's joined history | `ref` on the student FK, then two `eq_ref:PRIMARY` | 1 |
| 20-row page, per-row reads | 40 queries | one seek per row |
| live queue, 2026-11-02, per-row repeats | 25 statements over 5 rows (5.0 per row) | five named call sites — `EnrollmentReadiness` lines 148, 160, 187 and 197, and `Gradescale` line 36 — the last of which re-reads a school-wide constant for every record |
| same queue, after the school-wide reads were resolved once per page | 15 statements over 5 rows (3.0 per row) | `EnrollmentReadiness` lines 154, 218 and 228 remain per-row; the scale and catalogue reads drop to zero when handed in, pinned by `PrerequisiteGateTest` |
| the same page, batched | 2 queries | one seek per relation |
| block capacity count, fullest block 40 of 40 | `ref:fk_enrolledsubjects_blockid` | ~200 |

The last row retires a claim this batch first believed: **the capacity count was never the bottleneck** at a real
distribution — the 410 ms reading was an artefact of a fixture that put 101,484 students in one block of 40, which
the builder now refuses to make, and the honest re-reads are 0.86 ms and 2.77 ms, both of which are a seek, not a
scan. The bottleneck in this system is contention, and that is what part 9 answers.

## 12. How access control protects student records

Every desk route asks a permission, and the sensitive ones ask a policy too. Spatie's `Gate::before` grants SysAdmin
before any policy body runs, which is exactly why a gate ability may never share a permission's name —
`AbilityNameCollisionTest` pins that. A staff account with a `unitId` is college-bound and `App\Support\CollegeScope`
is the only statement of it: the policies ask `holds()`, the lists go through an `of*()` method, and the
notification bell now asks the same predicate, so a notice cannot name a record its recipient cannot open.
`Students::$hidden` keeps `passwordHash` out of its own serialisations, and where a sort joins a table the shared
helper pins the select to the base model's columns, because a joined `select *` ships the student's birthdate to a
cashier's screen (G-51). Served uploads are gone: `config/filesystems.php` no longer marks the private disk
`serve => true`, so no route exists to sign (G-50).

## 13. How failed operations are rolled back

`DB::transaction` rolls back on any exception leaving the closure — including the `ValidationException` the capacity
guard raises, which is why an over-large request seats nobody: the assertion in
`a_request_larger_than_the_seats_left_seats_nobody` is that all three students are still un-stamped **and** that the
two seats the block has are still available afterwards. The retry helper is safe for exactly this reason and says so
in its own docblock: it may wrap an act that is one transaction, never an act that has already committed part of
its work.

## 14. How committed information is protected

Committed money and status changes are append-only evidence: `enrollmentstatushistory` is written only by
`EnrollmentStateMachine::transition()`, `auditlogs` records the act and the actor, and `documentprintlog` mints its
number under a `SELECT … FOR UPDATE` on the counter row so two certificates cannot share one. Nothing in `app/` or
`routes/` deletes a completed clearance, and the guard for that is a test rather than a convention. Durability
settings were read, not assumed: `innodb_flush_log_at_trx_commit = 1` with `sync_binlog = 1`.

## 15. How backups and recovery procedures work

`tools/ems-backup-drill.php` is the executable answer: it dumps `--single-transaction` **without** `--databases`
(whose `/*!40000 DROP DATABASE …*/` comment is what destroyed live `ems` once before), refuses any dump carrying
`DROP DATABASE`, restores into a scratch schema named by a pattern it will accept, compares every base table by
`COUNT(*)`, then compares three ordered digests computed in php rather than `GROUP_CONCAT` — whose 1024-byte
`group_concat_max_len` would have compared prefixes and called them equal. Run 2026-11-01: 64 tables, digests
`MATCHES`. `SECURITY-AND-RECOVERY-ADS.md` states the limit beside it: no scheduled job exists, and there is no
point-in-time recovery, so the restore point is the last dump a person took.

## 16. How the registrar-assisted workflow uses the same mechanisms

Because there is no student login: public `/register` is removed, students are deliberately not notifiable, and
every one of these acts is a desk act. The nine offices sign boxes on one workflow form; the gates above are the
desk's gates, so "processed by registrar staff rather than directly by students" does not select a weaker path — it
is the only path. Two consequences are recorded rather than hidden: a student cannot submit, so throughput is the
number of desks (the guide's 1,000 requests arrive as ~1,000 staff clicks, which is why contention is realistic and
not theoretical), and the bell a signature raises is addressed by office *and* by college, which is the same
`CollegeScope` the desk's own list uses.

## 17. What evidence supports the claim

| Claim | Evidence |
|---|---|
| The race was real | `lane168` on HEAD's bytes: 5 writes, one block at 2 of 1, one `1213` |
| The race is closed | same probe, this batch's bytes: 3 of 3 pairs one-winner, 0 deadlocks, max 1 of 1 |
| A thousand acts keep the rules | `lane172-enrollment-day-load.php --requests=1000 --workers=10`: 69.2 acts/s, `duplicate active seats 0`, `orphan workflow forms 0`, `committed acts without a form 0`, one winner on a contended seat against nine 1062 refusals, and five drop-versus-issue races ending with no student holding two seats |
| The load tool's own questions can fail | `mutate-lane172-load-integrity.php` damages a copy of the kept schema and requires `--verify-only` to name each defect; the control reads clean before and after |
| Every promise has a guard | `mutate-g-ads-capacity-tests.php`: 10 legs, one per promise, all killed, control green before and after, three files restored by hash |
| The keys hold on the school's engine | `DatabaseLevelEnrollmentRulesTest` run against `ems_l170_a` (MySQL 8) as well as SQLite |
| Nothing was guessed | `lane154` (0 duplicate groups before each key), `lane163`'s invariant refusal, `lane60` and `lane44` agreeing with `information_schema` |
| The rules are counted, not narrated | `ems:audit:record-states` answers 20 rules and 0 `impossible` findings on live `ems` |
| A block over its own capacity is impossible from a desk and reported if it happens | write guard at `:485-496`; audit rule `blockOverCapacity()` |

**Not claimed.** No academic-load ceiling (P13), no faculty cap (P14), no isolation level around a report (P20), no
request key so an interrupted act can be retried safely (P17), no concurrent edit guard on the student record (P05).
The thousand-request run is no longer on that list: `lane172` drove 1,000 creation acts from ten processes at
69.2 per second on a clone of the school's engine and read every integrity question back from the server
(`storage/app/scratch/lane172-load-out.txt`). What is still not claimed is a load run against **live** `ems`, which
the guide forbids and this repository refuses, and the capacity race under many writers, which is `lane168`'s
two-writer act rather than a thousand. Each open item is listed in the matrix with the reason it is open, and the
defence answer is that the system does not pretend to them.
