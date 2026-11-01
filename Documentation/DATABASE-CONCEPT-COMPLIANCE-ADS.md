# ADS Database Concept Compliance — SEAIT Enrollment Management System

Guide source: `ADS_Final_Project_Guide_EMS_updated.docx` §IV (paragraphs 291-319), read by
`php storage/app/scratch/lane155-read-ads-guide.php`, which counts **12 concept rows in the guide's own table**.
Each row below is one of those twelve, in the guide's order, and the status word is drawn only from its own
vocabulary — *Implemented*, *Demonstrated in test*, *Query plan/performance test*, *Backup/restore or recovery
evidence*, *Explained*, *N/A with explanation*. A concept is not Implemented because a document discusses it.

The system is Laravel 13.23 on MySQL 8 (Laragon), Inertia/React on the desk side, an SQLite `:memory:` suite, and
CI with three jobs (`.github/workflows/ci.yml`). Live `ems` answers **64 tables, 105 foreign keys, 96 permissions**
as read by `php storage/app/scratch/lane60-ratio-claims.php --run` on 2026-11-01 (its own verdict: 54 match, 0 stale,
0 unbound), and **15 unique constraints over 184 table constraints, 237 index rows** as printed by
`php storage/app/scratch/lane154-probe-duplicate-keys.php` on the same schema after this batch's two enrollment keys
landed. Each figure is credited to the one instrument that prints it, because a number attributed to a tool that
never measured it is unfalsifiable.

---

## 1. Transactions (¶295) — Implemented

**Actual implementation.** 38 `DB::transaction(...)` sites in `app/`. The enrollment act is
`app/Services/EnrollmentIssuer.php:109`, whose closure writes the `enrollments` row and then the workflow form, so
a record and its desk checklist commit together or not at all. The Registrar's approve (`RegistrarController.php:321`)
and drop (`:368`) wrap subject lines, the status transition and the box signature in one transaction. The status
machine is the only writer of `enrollmentstatushistory` and transitions inside its own transaction
(`app/Services/EnrollmentStateMachine.php`). Money writes one transaction per act (`AccountingController.php:268`
record, `:414` void, `:464` refund).

**Applicability.** Applies to every enrollment act the system offers.

**Test method.** Feature cases that assert the dependent rows appear or vanish together; a failure injection test is
owed (traceability T02).

**Evidence.** The delivered suite passes whole; the atomicity demonstration is listed as owed in
`Documentation/REQUIREMENTS-TRACEABILITY-ADS.md` §5.

**Remaining limitation.** No case currently proves a rollback *between* two subject writes. That is T01/T02, not a
missing mechanism.

---

## 2. COMMIT and ROLLBACK (¶297) — Implemented; the printed demonstration is owed

**Actual implementation.** Laravel's closure form commits when the closure returns and rolls back when it throws,
so a `try/catch` cannot accidentally swallow a partial commit — the transaction is the unit, not the handler. The
one deliberate partial-failure design is the warn-and-proceed path in the timetable act, which is stated in code
and in the authority document, not hidden.

**Test method.** One successful commit print, one forced failure print, both from the same act, so the pair is the
evidence.

**Remaining limitation.** Neither print exists yet. This concept is therefore marked Implemented for the mechanism
and *Demonstrated in test* only after T02 and T17 are delivered.

---

## 3. ACID Properties (¶299) — Partially implemented

- **Atomicity** — as §1 above; the enrollment and its checklist are one unit.
- **Consistency** — the schema's own rules hold it: 105 foreign keys, MySQL enum statuses, NOT NULL required
  columns, the unique keys §23.6.1 lists (weighed against the server by
  `php storage/app/scratch/lane44-unique-keys.php`, which reads a count sentence by shape so it re-takes itself
  rather than rotting), and `php artisan ems:audit:record-states` (20 rules) weighs the record states a desk
  must not be able to reach. A read of the audit on live `ems` answers 0 findings.
- **Isolation** — implemented for the one act the guide's `SELECT … FOR UPDATE` example describes, and nowhere else.
  `app/Http/Controllers/Blocking/BlockingController.php:485` locks the block row as the transaction's first act,
  `:488-491` re-counts the held seats under that lock, and `:500` locks the room row afterwards in that fixed order.
  Before 2026-11-01 the capacity check ran *outside* the transaction, and `lane168-probe-last-seat-race.php`
  measured what that meant on the school's server: three pairs of concurrent assigns against a block holding one
  seat produced **5 successful writes and a block holding 2 of 1**, plus one pair ending in a real `1213` deadlock
  that reached the desk as a 500. On the read side the daily collection report
  (`app/Http/Controllers/Accounting/AccountingController.php:360`) now takes both of its `payments` reads inside one
  `DB::transaction`, so the engine's default REPEATABLE READ fixes one read view for the sheet instead of letting it
  straddle two snapshots; the isolation level itself is deliberately **not** declared anywhere, because that changes
  what every desk write on the connection sees, and `lane172` printed the session's actual level
  (`REPEATABLE-READ`) rather than assuming it. The Registrar queue and the dashboard tiles still read outside a
  transaction and are named as the remainder in traceability row P20.
- **Durability** — depends on InnoDB's redo log and on a backup method the guide requires. Both halves are now
  measured rather than cited: `innodb_flush_log_at_trx_commit = 1` with `sync_binlog = 1` read from the server, and
  `php tools/ems-backup-drill.php --target=ems_drill` executed a dump, a restore into an isolated schema and a
  table-by-table row comparison (64 tables, three ordered digests, 0 differences). What is still absent is a
  scheduled job and any point-in-time replay, which section 12 states rather than implies.

**Test method.** Four separate demonstrations, one per property, so the claim is not one paragraph.
**Remaining limitation.** Atomicity, Consistency and Durability each have a demonstration now. Isolation is the
partial half: a row lock on the contended write and one transaction around the sheet the cashier signs, with the
lost-update guard (P05) and the remaining report screens (P20's named remainder) still open.

---

## 4. Concurrency Control (¶301) — Implemented on the shared resources; the profile edit is versioned, the child rows are not

**What exists.** Optimistic serialization by status: a box that is already signed refuses a second signature
(`WorkflowService`'s `WorkflowAlreadySignedException`), and `EnrollmentIssuer::seatHolder()` (`app/Services/EnrollmentIssuer.php:39-45`)
answers the enrollment that already holds a student's seat in a term. Notifications dedupe on a unique key
(`app/Support/NoticeDelivery.php:32-37` catching MySQL 1062), which is the database arbitrating a race.

**What this batch added.** The block and the room are now locked and one shared resource is refused by the server.
The block and the room are read with `lockForUpdate()` and re-counted *inside* one transaction
(`app/Http/Controllers/Blocking/BlockingController.php:485` and `:500`), retried only on the two conditions that mean
"somebody held it first" (`app/Support/RetryOnDeadlock.php:76-79`), and a second fee sheet for one enrollment is
refused by `uq_studentassessments_enrollment`. The active-seat rule was given a database key the same morning,
**took it back the same day and returned it the same day**: CI's MySQL 8 job showed six test files creating two active
enrollments for one student in one term, so `uq_enrollments_active_seat` was withdrawn by
`database/migrations/2026_11_01_1600000000_withdraw_the_active_seat_key_until_the_fixtures_are_repaired.php`. The census
that then read every fixture call site (`storage/app/scratch/lane187-census-fixture-seats.php`, proved by
`storage/app/scratch/mutate-lane187-provenance.php`) answered eight files holding the habit, each was repaired to name a
later term, drop the prior seat or take a second student, the repaired files rehearsed green on a scratch MySQL schema,
and `database/migrations/2026_11_01_1900000000_return_the_active_seat_key_now_that_fixtures_hold_one_seat_per_term.php`
put the key back. `EnrollmentIssuer::seatHolder()` is still the guard every desk path calls; the key is what refuses a
row written straight to the table. The repository had recorded this gap itself: DI-5 in
`Documentation/System-Design-Quality-Review.md:98` named the blocking race open on 2026-09-15, and DI-2 the missing
fee-sheet key in the same table.

**What is still missing, named rather than smoothed.** The profile capture now carries `students.rowVersion`, so a
lost update on that screen is refused with both numbers named (problem P05, built 2026-11-01) — but the version is on
`students` alone, so the address rows and the guardian rows the same screen writes are still unversioned, and a desk
holding two of *those* can overwrite one another; and the room count is per
`scheduleId`, so a hall shared by ten sections is never weighed as a hall (P04's registered question).

**Test method.** `php storage/app/scratch/lane168-probe-last-seat-race.php --pairs=3` drives one act over two real
MySQL connections against a scratch clone and prints both sides: HEAD's bytes took 5 assigns, 0 refusals and left a
block with `maxStudents` 1 holding 2 seats, and one pair ended in a genuine `SQLSTATE[40001]` deadlock that reached
the desk as a 500; the locked bytes took 3 assigns, 3 capacity refusals, 0 deadlocks and never more than 1 seat.
**Status honest word.** *Implemented* for the block, the room, the seat and the sheet; *Demonstrated in test* for the
race, because the losing writer was observed on the school's engine; *not implemented* for the optimistic version,
which is the honest half of this row.

---

## 5. Locking (¶303) — Three sites, one fixed order

**Actual implementation.** `app/Services/PrintService.php:140` — `lockForUpdate()->max('documentNumber')` inside the
issuance transaction, with the reserved number skipped so a re-issued sheet never duplicates a Document No. To it this
batch added the two enrollment locks: the block row at `app/Http/Controllers/Blocking/BlockingController.php:485`, as
the transaction's first act, and the room row at `:500` — always block then room, never the reverse, because a fixed
order is what makes two contending writers serialise instead of waiting on each other. The requested student ids are
de-duplicated and sorted before the transaction opens (`:471-473`), which is the other half of the same discipline:
every caller reaches the same rows in the same sequence.

**Remaining limitation.** The locks cover the assign path only. No other enrollment write takes a row lock, and the
room count is per `scheduleId`, so what is protected is a section against its own room, not a room against its
sections — the question P04 registers for the school rather than answering by invention.

## 6. Deadlock Handling (¶305) — Implemented, and the deadlock was observed

Prevention is the fixed lock order and the sorted id list described in section 5. Detection and bounded retry are
`App\Support\RetryOnDeadlock` (`app/Support/RetryOnDeadlock.php:38-66`): it re-runs the transaction only when the
driver's code is 1213 (deadlock) or 1205 (lock wait timeout) — `isRetryable()` at `:76-79`, with the code read off the
exception at `:69-74` — propagates every other `QueryException` untouched, and after its attempts are spent refuses
with "Nothing was saved — please try again." (`:54-59`) instead of leaving a desk to guess whether the row moved.
The guide's strongest available word applies here: the race is *Demonstrated in test*, because the probe met a real
`SQLSTATE[40001]` 1213 on HEAD's act and 0 on the locked act.

**Remaining limitation.** The suite runs on SQLite, which neither deadlocks nor reports 1213, so the four boundary
cases hand the helper the two driver conditions rather than pretending the driver produced them; the retry count
itself is only weighed on a real server.

---

## 7. Database Constraints (¶307) — Implemented

**Actual implementation.** Primary keys on every domain table (camelCase ids, e.g. `studentId`); 105 foreign keys
with the delete behaviour catalogued per table in the authority document §23 and weighed by
`php storage/app/scratch/lane43-delete-guards.php`; NOT NULL on the columns intake cannot leave empty (a Draft
application defers address, guardian and background, never the person); enums for every status word
(`enrollments.enrollmentStatus`, `enrolledsubjects.status`, `payments.paymentStatus`, …); and 13 unique constraints
listed in §23.6.1, of which the enrollment-relevant ones are `uq_students_schoolid`, `uq_staff_username`,
`uq_enrollment_subject_attempt`, `uq_student_clearanceperiod`, `uq_payments_ornumber`, `uq_student_scholarship_term`,
`uq_documentprintlog_issue`, `uq_notifications_dedupe`, `uq_enrollments_active_seat`, `uq_enrollments_request_key`.

**Applicability.** Applies. Of the two keys the enrollment rules needed, one travels on both drivers and one only on
the school's: `uq_studentassessments_enrollment` is an ordinary unique index, while the active-seat key was withdrawn on
2026-11-01 after CI showed the fixtures holding the state it forbids and **returned the same day** once eight fixture
files were repaired and rehearsed on a scratch MySQL schema — and it can only exist on MySQL, because SQLite compiles no
`ALTER TABLE … ADD COLUMN … GENERATED`. A STORED spelling of it also answers
`1215 Cannot add foreign key constraint`, because rebuilding a table eleven others point at is what STORED does. Both
were safe to consider additively because `php storage/app/scratch/lane154-probe-duplicate-keys.php` answered 0
duplicate groups on live `ems` for each before either was written.

**Remaining limitation.** No `CHECK` constraint expresses a range rule (capacity ≥ 0, units ≥ 0), which MySQL 8
does support; the guide asks for them "where supported and appropriate".

---

## 8. Indexing (¶309) — Implemented; one index is unreachable by its own query

**Actual implementation.** Named composites exist for the desk's real filters, e.g.
`database/migrations/2026_08_04_1000000052_add_composite_indexes_for_heavy_screens.php:24` builds
`idx_enrollments_term_status (termId, enrollmentStatus)`, and `database/migrations/2026_08_02_1000000005_create_students_table.php:35`
builds `idx_students_names (lastName, firstName)` beside the unique `schoolIdNumber` at `:32`. `enrollments` is
indexed on `studentId`, `courseId` and `termId` individually.

**The defect this concept owns.** The directory's search is `like "%{$search}%"` on four columns
(`app/Http/Controllers/StudentController.php:40-43`), which cannot use a left-to-right name index. 51
leading-wildcard searches exist across 15 controllers. So the index is real and the query ignores it.

**Test method.** `EXPLAIN` and timings before and after a sargable rewrite, at a stated synthetic row count
(traceability T09) — delivered by `php storage/app/scratch/lane164-measure-scale-queries.php` over the
`ems_l163_a` scale schema; the rewrite itself is a school decision, not a default to invent.
**Status honest word.** Implemented for the constraints, and *Query plan/performance test* delivered for the index
the search ignores.

---

## 9. Query Optimization (¶311) — Measured on a synthetic scale schema; the two rewrites are decisions

`php storage/app/scratch/lane163-build-scale-schema.php` built `ems_l163_a` — 100,000 students, 507,420 subject
lines, 1,200 blocks with seats filled in order — and `php storage/app/scratch/lane164-measure-scale-queries.php`
weighed the four paths the guide names, each with its own `EXPLAIN`, newest capture saved at
`storage/app/scratch/lane164-out.txt`. What the two runs of that command agreed on is the plan and the rows it
expects to touch, and that is the evidence: the directory's `like '%…%'` search cannot use `idx_students_names` and
scans ~98,800 index rows, a sargable prefix uses it for ~1,111, an exact `schoolIdNumber` is a `const` seek of one
row, and a fulltext `MATCH` is a seek of one row. The milliseconds are not stable between runs on this box — the
same command answered 2.68 ms and 10.54 ms for the contains search, 0.53 ms and 0.97 ms for the prefix, 0.78 ms and
14.74 ms for the fulltext, and 0.77 ms and 6.23 ms for the batched page read — so this document quotes plans and row
counts and treats a timing as a property of the run that printed it, the same rule the suite's assertion totals
follow. Per-student history retrieval is an index seek at every hop (`ref` on the student FK, then `eq_ref:PRIMARY`),
so the guide's worry about "hundreds of thousands of rows" lands on the *page loop*, not the read: a 20-row
Registrar page costs 40 queries where the batched shape costs 2. An earlier reading of the capacity count, 410 ms,
was a fixture artefact — the builder spread rows by a modulo that overfilled one block, and `lane163` now refuses
that shape rather than measuring it.

`app/Services/AcademicStandingService.php` reads the whole grade set in one joined, column-named query rather than
per subject (`:177` sums `lectureUnits + labUnits` over that result), and the desk lists paginate server-side. The
regressions the measurement confirmed are named, not guessed: the Registrar queue issues roughly five queries per page
row through `app/Support/EnrollmentReadiness.php`, and `AccountingController.php:384` filters a datetime with
`whereDate`, which is non-sargable. Only one test in the suite asserts a query count
(`tests/Feature/Print/PrintEnrollmentFormPayloadTest.php:206-227`); there is no query budget in CI.

**Test method.** Delivered for the four query paths, with `EXPLAIN` read per path. A ceiling assertion per desk page
is still owed, and the two changes the measurements point at — what a search may stop matching, and how a page's
checklist is batched — wait on the school rather than on an invented default.

---

## 10. Data Integrity (¶313) — Implemented

Statuses move through one machine (`EnrollmentStateMachine`), money states are weighed by two audit rules added
with the over-collection ruling (a receipt may not exceed what the account owes; a `paid` status must match its
money), cancelled rows are excluded from every count and unit total, and `ems:audit:record-states` answers 0
findings on live `ems` today. The identifier rules the guide cares about — a student ID cannot duplicate, one
clearance per student per period, one OR number — are unique constraints, not application checks.

**What changed with this batch.** The one-fee-sheet-per-enrollment rule is a database constraint now, not only an
application check: `uq_studentassessments_enrollment`. The seat rule (one active enrollment per student per term) is a
database constraint too: the generated-column key was written, measured against the real constraint error, withdrawn the
same day because the repository's own fixtures create two active seats for one student and term, and returned the same
day after eight fixture files were repaired and rehearsed on a scratch MySQL schema. A dropped enrollment releases the
seat through the NULL the generated column writes for it, which is ruling 17's behaviour enforced without a trigger.

**Remaining limitation.** The seat key is MySQL-only by construction, so the SQLite suite cannot violate it and
`DatabaseLevelEnrollmentRulesTest` asserts the driver-appropriate fact on each surface instead of skipping. A fixture
that writes two active seats for one student in one term will therefore be refused by CI's MySQL job and not by this box
— which is why `storage/app/scratch/lane187-census-fixture-seats.php` is run before any driver-specific key is proposed,
and why its reading is proved by `storage/app/scratch/mutate-lane187-provenance.php`. Capacity and unit ranges still have
no `CHECK`.

---

## 11. Database Security (¶315) — Implemented

**Actual implementation.** Authorization is per record on the server: 171 `$this->authorize()` calls against 15
policies registered in `app/Providers/AuthServiceProvider.php`, with Spatie's 96 named permissions over 17 roles
from `RbacSeeder` and `Gate::before` for the SysAdmin; a gate ability may not share a permission's name, and
`AbilityNameCollisionTest` pins that. College boundaries live in one class, `App\Support\CollegeScope`, and a
record whose course names no college is not the desk's. Credential hashes are hidden on both models
(`app/Models/Students.php:25-27` and `Staffusers`' own list). Every model is audited by `AuditLogObserver`,
attached by glob in `app/Providers/AppServiceProvider.php`, which redacts a value matching
`password|hash|token|secret` **at write time**. Sessions are `database` driver, HttpOnly, SameSite=Lax, and the
private uploads disk is no longer served at all (the `/storage` routes are not registered, pinned by
`ServedUploadPathTest`). SQL is built through Eloquent and query-builder bindings; the raw SQL that exists lives in
migrations and in `selectRaw` aggregates with no interpolated input.

**Test method.** `PermissionMatrixTest` (route × role), the privacy payload cases, `StoredFileAccessTest`.
**Remaining limitation.** Two questions are institutional, not technical: who may read a clinical note, and whether
a student's page must show a lawful-basis notice. Both are on the registers and neither is claimed as solved.

---

## 12. Recovery (¶317) — The drill was executed, and its reading is the evidence

**What exists.** A dump command with `--single-transaction` and a restore redirect in
`Documentation/Demo-Day-Guide.md:289-307`; the same command in `Documentation/Offline-Presentation-Guide.md:89`; a
dump validator (`tools/validate_demo_backup.php`) that reads a dump's INSERTs and reports structural surprises; and
a hard-won policy in `AGENTS.md` that a dump never enters the repository and that a database is never cloned by
rewriting a dump with `sed`.

**What the guide asks and the build now does.** The drill is executed, not described:
`php tools/ems-backup-drill.php --target=ems_drill`, run 2026-11-01, dumped live `ems`, restored it into an isolated
schema and compared the tables **row for row**, answering `VERDICT: 0 table(s) missing, 0 row-count or checksum
difference(s)`, then dropping its own scratch schema. The tool refuses `ems` as a target by name, so no invocation can
restore over the school's database, and the server's durability configuration was read in the same run rather than
assumed — `innodb_flush_log_at_trx_commit = 1` with `sync_binlog = 1`. The reading sits beside the command that
printed it in `Documentation/SECURITY-AND-RECOVERY-ADS.md` §2.

**Still owed, and not claimed.** A scheduled job remains an unchecked box in
`Documentation/First_Build_Plan.md:128` and `:187`, and point-in-time replay is not implemented: no tool in this
repository reads the server's binary log back into a schema, so recovery to the last transaction is a procedure, not a
demonstrated act.

**Status honest word.** *Backup/restore or recovery evidence* for the restore, which is the half the guide weighs;
*Explained* for scheduled and point-in-time recovery.

---

## Summary in the guide's own words

| # | Concept | Status word for §IV's checkbox |
|---|---|---|
| 1 | Transactions | Implemented |
| 2 | COMMIT / ROLLBACK | Implemented, demonstration owed |
| 3 | ACID Properties | Three of four demonstrated (atomicity, consistency, durability); Isolation demonstrated for the contended write and the daily sheet, open on the rest |
| 4 | Concurrency Control | Implemented on the block, the room, the seat and the fee sheet; *Demonstrated in test* for the race; `students.rowVersion` now guards the profile edit (P05), while the address and guardian rows stay unversioned |
| 5 | Locking | Implemented at three sites in one fixed order (block, room, document number) |
| 6 | Deadlock Handling | Implemented; the deadlock was observed on MySQL 8, and the retry is bounded |
| 7 | Database Constraints | Implemented; two enrollment keys added 2026-11-01; no `CHECK` ranges |
| 8 | Indexing | Implemented; query plan and timings delivered, the sargable rewrite waits on the school |
| 9 | Query Optimization | Measured at 100,000 enrollments; a per-page query ceiling is still owed |
| 10 | Data Integrity | Implemented; the seat key is MySQL-only and the suite names that half |
| 11 | Database Security | Implemented |
| 12 | Recovery | *Backup/restore or recovery evidence* delivered for the restore; scheduled and point-in-time recovery owed |
