# ADS Database Concept Compliance — SEAIT Enrollment Management System

Guide source: `ADS_Final_Project_Guide_EMS_updated.docx` §IV (paragraphs 291-319), read by
`php storage/app/scratch/lane155-read-ads-guide.php`, which counts **12 concept rows in the guide's own table**.
Each row below is one of those twelve, in the guide's order, and the status word is drawn only from its own
vocabulary — *Implemented*, *Demonstrated in test*, *Query plan/performance test*, *Backup/restore or recovery
evidence*, *Explained*, *N/A with explanation*. A concept is not Implemented because a document discusses it.

The system is Laravel 13.23 on MySQL 8 (Laragon), Inertia/React on the desk side, an SQLite `:memory:` suite, and
CI with three jobs (`.github/workflows/ci.yml`). Live `ems` answers **64 tables, 105 foreign keys, 96 permissions**
as read by `php storage/app/scratch/lane60-ratio-claims.php --run` on 2026-11-01 (its own verdict: 54 match, 0 stale,
0 unbound), and **13 unique constraints over 182 table constraints, 233 index rows** as printed by
`php storage/app/scratch/lane154-probe-duplicate-keys.php` on the same schema. Each figure is credited to the one
instrument that prints it, because a number attributed to a tool that never measured it is unfalsifiable.

---

## 1. Transactions (¶295) — Implemented

**Actual implementation.** 38 `DB::transaction(...)` sites in `app/`. The enrollment act is
`app/Services/EnrollmentIssuer.php:58`, whose closure writes the `enrollments` row and then the workflow form, so
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
  columns, 13 unique keys, and `php artisan ems:audit:record-states` (19 rules) weighs the record states a desk
  must not be able to reach. A read of the audit on live `ems` answers 0 findings.
- **Isolation** — **not** implemented for enrollment. The guide names `SELECT … FOR UPDATE` here, and the only
  `lockForUpdate` in `app/` is `app/Services/PrintService.php:140`, which sequences a document number. The capacity
  check at `app/Http/Controllers/Blocking/BlockingController.php:469-478` runs before the transaction opens at
  `:495`, so two assigns can both take the last seat. Reports likewise read outside any transaction, and no
  isolation level is set anywhere in the repository.
- **Durability** — depends on InnoDB's redo log and on a backup method the guide requires; the build has dump
  *commands in a guide* (`Documentation/Demo-Day-Guide.md:289-307`) and no executable, executed restore.

**Test method.** Four separate demonstrations, one per property, so the claim is not one paragraph.
**Remaining limitation.** Isolation and Durability are the two open halves; they are traceability rows P02/P10/P20
and P21.

---

## 4. Concurrency Control (¶301) — Not implemented for the enrollment path

**What exists.** Optimistic serialization by status: a box that is already signed refuses a second signature
(`WorkflowService`'s `WorkflowAlreadySignedException`), and `EnrollmentIssuer::seatHolder()` (`app/Services/EnrollmentIssuer.php:37-43`)
answers the enrollment that already holds a student's seat in a term. Notifications dedupe on a unique key
(`app/Support/NoticeDelivery.php:32-37` catching MySQL 1062), which is the one place this build uses the database
as the arbiter of a race.

**What is missing.** No row lock, no version column, no atomic conditional update on the shared resources — the
block, the room, the seat, the fee sheet. The repository already recorded this: DI-5 in
`Documentation/System-Design-Quality-Review.md:79` names the blocking race as open since 2026-09-15.

**Test method.** Two independent MySQL connections driving the same act (T03, T07, T11).
**Status honest word.** Not implemented, therefore *Demonstrated in test* cannot be claimed either.

---

## 5. Locking (¶303) — One site only

**Actual implementation.** `app/Services/PrintService.php:140` — `lockForUpdate()->max('documentNumber')` inside the
issuance transaction, with the reserved number skipped so a re-issued sheet never duplicates a Document No. That is
a correct pessimistic lock on the only sequence the system mints.

**Remaining limitation.** No enrollment write locks anything. The plan is in the traceability matrix: lock the block
row, then the room row, in one fixed order, and re-count capacity inside the transaction.

---

## 6. Deadlock Handling (¶305) — Not implemented

Grepping `app/`, `tools/`, `tests/` for `1213`, `1205`, `deadlock`, `lock wait timeout` and any retry loop around a
write returns nothing but prose that uses "deadlock" for a state-machine trap. There is no lock order to violate
today (because there are no locks), so there is also no deadlock prevention, detection, or bounded retry. The
controlled demonstration the guide allows for an application that does not naturally deadlock is owed with P07.

---

## 7. Database Constraints (¶307) — Implemented

**Actual implementation.** Primary keys on every domain table (camelCase ids, e.g. `studentId`); 105 foreign keys
with the delete behaviour catalogued per table in the authority document §23 and weighed by
`php storage/app/scratch/lane43-delete-guards.php`; NOT NULL on the columns intake cannot leave empty (a Draft
application defers address, guardian and background, never the person); enums for every status word
(`enrollments.enrollmentStatus`, `enrolledsubjects.status`, `payments.paymentStatus`, …); and 13 unique constraints
listed in §23.6.1, of which the enrollment-relevant ones are `uq_students_schoolid`, `uq_staff_username`,
`uq_enrollment_subject_attempt`, `uq_student_clearanceperiod`, `uq_payments_ornumber`, `uq_student_scholarship_term`,
`uq_documentprintlog_issue`, `uq_notifications_dedupe`.

**Applicability.** Applies; the two keys the enrollment rules still need are named in
`Documentation/REQUIREMENTS-TRACEABILITY-ADS.md` P03 and are safe to add because live `ems` answers 0 duplicate
groups for each of them.

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
(traceability T09).
**Status honest word.** Implemented for the constraints, *Query plan/performance test* not yet delivered.

---

## 9. Query Optimization (¶311) — Not measured

`app/Services/AcademicStandingService.php` reads the whole grade set in one joined, column-named query rather than
per subject (`:177` sums `lectureUnits + labUnits` over that result), and the desk lists paginate server-side. The
known regressions are named, not guessed: the Registrar queue issues roughly five queries per page row through
`app/Support/EnrollmentReadiness.php:65,109-111,144,163,183`, and `AccountingController.php:372` filters a datetime
with `whereDate`, which is non-sargable. Only one test in the suite asserts a query count
(`tests/Feature/Print/PrintEnrollmentFormPayloadTest.php:206-227`); there is no query budget in CI.

**Test method.** A ceiling assertion per desk page plus `EXPLAIN` output.

---

## 10. Data Integrity (¶313) — Implemented

Statuses move through one machine (`EnrollmentStateMachine`), money states are weighed by two audit rules added
with the over-collection ruling (a receipt may not exceed what the account owes; a `paid` status must match its
money), cancelled rows are excluded from every count and unit total, and `ems:audit:record-states` answers 0
findings on live `ems` today. The identifier rules the guide cares about — a student ID cannot duplicate, one
clearance per student per period, one OR number — are unique constraints, not application checks.

**Remaining limitation.** The seat rule (one active enrollment per student per term) is still application code, so
this concept is Implemented with one named exception.

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

## 12. Recovery (¶317) — Documented only

**What exists.** A dump command with `--single-transaction` and a restore redirect in
`Documentation/Demo-Day-Guide.md:289-307`; the same command in `Documentation/Offline-Presentation-Guide.md:89`; a
dump validator (`tools/validate_demo_backup.php`) that reads a dump's INSERTs and reports structural surprises; and
a hard-won policy in `AGENTS.md` that a dump never enters the repository and that a database is never cloned by
rewriting a dump with `sed`.

**What the guide asks and the build does not do.** An executed backup, an executed restore into an isolated schema,
and a row-by-row comparison proving the restore. The nightly job and the restore drill are unchecked boxes in
`Documentation/First_Build_Plan.md:128` and `:187`. No point-in-time or binlog procedure exists, and the server's
durability configuration (`innodb_flush_log_at_trx_commit`, `sync_binlog`) has never been read into this project's
evidence.

**Status honest word.** *Explained*, not *Backup/restore or recovery evidence*, until traceability row P21 is built.

---

## Summary in the guide's own words

| # | Concept | Status word for §IV's checkbox |
|---|---|---|
| 1 | Transactions | Implemented |
| 2 | COMMIT / ROLLBACK | Implemented, demonstration owed |
| 3 | ACID Properties | Explained, two of four demonstrated after P02/P20/P21 |
| 4 | Concurrency Control | Not implemented → to be Demonstrated in test |
| 5 | Locking | Partially implemented (one sequence) |
| 6 | Deadlock Handling | N/A-with-demonstration not yet earned; goal is Demonstrated in test |
| 7 | Database Constraints | Implemented |
| 8 | Indexing | Implemented; performance test owed |
| 9 | Query Optimization | Partially implemented; measurement owed |
| 10 | Data Integrity | Implemented with one named exception |
| 11 | Database Security | Implemented |
| 12 | Recovery | Explained; backup/restore evidence owed |
