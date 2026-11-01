# Final implementation report — Advanced Database Systems

Requirement set: `Enrollment_Management_System_Master_Implementation.md`, prompt part 44 (twelve parts). Batch date 2026-11-01.
Every claim below is either a file this repository holds, a command that printed a figure, or a stated absence.

---

## 1. Executive summary

The starting condition was a working Laravel enrollment system with a documented weakness the project had named
itself and never closed: the section-capacity check ran outside the transaction it was supposed to guard, so two
desks could both read the last seat as free. That was recorded as DI-5 in `System-Design-Quality-Review.md` on
2026-09-15 and left open for six weeks; a second finding, DI-2 (a fee sheet with no database-level uniqueness), sat
beside it. Neither was reachable by the test suite, because SQLite cannot contend with itself.

This batch did five things. It **measured** the guide's claims against the repository instead of accepting them
(24 problems, each weighed against code and against live `ems`), **closed** the race with a row lock, a re-count
under it, a fixed lock order and a retry scoped to the two conditions that mean "somebody held it first",
**pushed two rules into the database** as unique keys so a writer that never asks the application is still refused,
**produced the recovery evidence** the guide asks for by actually restoring a dump and comparing 64 tables, and
**proved each promise can fail** with a mutant harness and a two-writer race probe that shows the pre-fix code
overbooking a block of one seat.

The improvements are not uniform, and the report does not make them look so: five of the guide's twenty-four
problems were already implemented and needed only demonstration, four are now built and measured, two are measured
but deliberately not repaired, and six remain open with their reasons written down.

## 2. Repository findings

| | |
|---|---|
| Stack | Laravel **13.23.0** (`composer.json` `^13.8`; `artisan --version`), Inertia + React 18 on the front, MySQL 8 in production, SQLite `:memory:` in the suite. Not Laravel 11, which a note in this project had repeated without re-reading. |
| Architecture | A monolith with nine office desks behind one workflow form; controllers resolve a record, `App\Support` holds the shared predicates (`DeskQueues`, `CollegeScope`, `WorkflowInbox`, `ListSort`, `EnrollmentReadiness`), and papers are rendered from Blade templates by Browsershot. |
| Database | 64 tables (56 domain + 8 framework), 480 columns, 105 foreign keys (71 NO ACTION / 29 SET NULL / 4 CASCADE / 1 RESTRICT), 12 unique keys in 10 tables, 138 indexes — all read from `information_schema` on 2026-11-01. |
| Real weaknesses found | The unlocked capacity check (DI-5); no uniqueness on `studentassessments` (DI-2); a search whose leading wildcard cannot use the index the schema carries; a Registrar queue issuing 40 queries for a 20-row page; **no isolation level anywhere**, so a report reads across statements; **no idempotency key**, so an interrupted request cannot be answered; and a room weighed per-schedule rather than per-room, which means the room's declared capacity is not the rule it reads like. |
| Also found, while reading | A bell addressed by office alone could name a record its recipient's own policy refuses; `**` globs in php match nothing, so two censuses had been passing by reading no file; and a document cited a roster filter at a line that held a docblock opener. |

## 3. Requirements coverage

Read out of `REQUIREMENTS-TRACEABILITY-ADS.md` section 1 by `storage/app/scratch/lane169-read-ads-status.php`, which
prints the five bucket tallies and refuses the run unless they add to the 24 rows it opens. The counts are not copied
into this table, because a tally written into a second document is a second answer to one question — the membership
below is the claim, and the tool owns the number:

| Bucket | Problems |
|---|---|
| Built and measured | P02 seat race, P03 duplicate seat and fee sheet at the server, P04 capacity guard + audit rule, P07 deadlock handling, P21 restore drill, P23 traffic at scale, P24 the enrollment-day challenge |
| Measured, not repaired | P08 search plan, P09 page query count |
| Partly | P10, P14 |
| Not built | P05, P13, P17, P20 |
| Implemented before this batch | P01, P06, P11, P12, P15, P16, P18, P19, P22 |

Demonstrated through a controlled test rather than argued: T03, T04, T05, T08, T09, T10, T11, T22 — each named with
its instrument and its measured outcome in `TESTING-AND-VERIFICATION-ADS.md`. No problem is marked N/A: the guide's
own list applies to this schema, and where the school's number is missing that is recorded as `Not built` with the
question attached, not as not-applicable.

## 4. Database improvements

- `uq_studentassessments_enrollment` — one fee sheet per enrollment, portable across both drivers.
- `uq_enrollments_active_seat (studentId, termId, seatKey)` with `seatKey` a **virtual** generated column that is
  non-null exactly when the row holds a seat. MySQL ignores NULLs, so `dropped` releases the seat without a trigger
  and without deleting the record. Both were refused-to-add guards first: the migration names the violating rows
  rather than skipping the key.
- Chosen over a stored generated column for a measured reason: a stored column rebuilds `enrollments`, which eleven
  child tables reference, and MySQL 8.4 answers `1215 Cannot add foreign key constraint` for it — with
  `foreign_key_checks` off as well.
- Transaction and locking behaviour documented in parts 1 and 9 of `DEFENCE-EVIDENCE-ADS.md`; the twentieth audit rule
  reconciles block capacity on demand; §23.6.1 of the authority document now lists all twelve keys because its own
  checker refuses a list that has stopped being complete.
- Nothing destructive: no dropped column, no re-typed data, no `truncate`, no backfill beyond the generated
  expression the server computes itself.

## 5. Frontend and backend improvements

Backend: `BlockingController::assignStudents()` (lock order, in-transaction count, sorted request, retry),
`App\Support\RetryOnDeadlock` (new), `AuditRecordStates::blockOverCapacity()` (new rule). Earlier in the same
workstream: the notification bell now carries a title, an action, a record reference and a deep link built by one
helper, and is addressed through `CollegeScope`; the workflow monitor gives an overseer one row per record in the
pipeline.

Frontend: no screen changed in this batch's capacity work, which is deliberate — the refusal arrives as an existing
`capacity` error and the desk already renders it. The wider tranche wired eighteen of the thirty-six list tables to
one sortable mechanism with the sort applied server-side, and put the refresh-pending state in the shared table so a
page cannot forget it.

Endpoints: none added, none removed. The route the race lives on is `POST /blocking/{block}/assign`.

## 6. Security improvements

Pinned this batch: the bell cannot name a record its recipient cannot open (the scope half of matrix gap G-44), and
every `OfficeId` case must answer a route that exists, checked at test time so folding a desk into another cannot
leave notices pointing nowhere. Standing, and verified rather than assumed this session: permissions behind Spatie
with a `Gate::before` that must never meet an ability of the same name; `Students::$hidden`; the shared select pin
that stops a sorted join shipping a student's `passwordHash` and birthdate to a cashier; the private uploads folder
that is no longer served at all; HSTS gated on a forwarded scheme an operator must name explicitly, with the
catch-all refused; audit-log redaction of sensitive keys; and every `fetch()` in the front end checking `res.ok`
before it paints, so a refusal cannot render as a zero.

## 7. Test results

- Whole suite: read the run of 2026-11-01 in `storage/app/scratch/l170-suite.log` and its junit in
  `storage/app/scratch/l170-suite-junit.xml`. Per-class case and assertion figures are re-taken from that junit by
  `php storage/app/scratch/lane107-retake-class-figures.php`, and no total is typed into this file: an assertion
  count is a property of the run that printed it, and CI's two jobs answer different ones for the same code.
- New classes: `BlockCapacityAtomicityTest` (12 cases) and `DatabaseLevelEnrollmentRulesTest` (4 cases), the latter
  rehearsed on MySQL as well as SQLite.
- Gates: PHPStan `[OK] No errors` on this tree; Pint, ESLint, the Vite build, the docx sweeps and the mutation
  harnesses are listed with their verdicts in `IMPLEMENTATION-CHANGELOG-ADS.md` section 3 and the delivery notes below.
- Failures and limits, stated: the seat key does not exist on SQLite (no generated `ALTER`), so that half is judged
  on the MySQL job; no academic-load or faculty ceiling can be tested because no number is defined; the thousand-
  request run has not been made; and the two measured performance gaps (P08, P09) are unfixed by decision.
- Performance measurements: the seven-row table in `DEFENCE-EVIDENCE-ADS.md` part 11, each figure beside its `EXPLAIN`.

## 8. Migration and safety information

Live `ems` was dumped first to `C:\Users\ADMIN\ems-backups\ems-pre-db-enforcement-2026-11-01.sql` (64 tables,
1.42 MB, verified to contain `INSERT INTO enrollments`), then `php artisan migrate`, then re-read: 37 enrollments,
24 students, 14 fee sheets, 3,526 audit rows — identical before and after. The rehearsal order used CI's own
(migrate from empty on `ems_l170_a`, run the new class with `DB_CONNECTION` pinned) before anything touched the live
schema, because naming a MySQL schema without pinning the connection makes Laravel open a SQLite file named after it.

`down()` drops the index and the generated column, and the migration is additive on the way up, so a deployment that
must not change data can still take it. Operations that need a human: none for this batch's schema, and the two
figures that would need one are the school's (report part 9).

## 9. Remaining issues

Open engineering: P05 (a silent overwrite of one student record — no version column), P17 (no request key, so an
interrupted act cannot be retried safely and "did my save commit?" is unanswerable), P20 (no isolation level around a
report), P13 and P14 (two load ceilings with no number), P10's own subject-line race, and the room-capacity
semantics named in report part 2. Open with the school: the term unit ceiling, any faculty cap, the search semantics that P08's
measurement now prices, restoration policy behind P19, and the thirteen items on the dated decision sheets that a
window has to answer.

Known limits of the proof: the race probe drives the controller action rather than the HTTP stack, because PHP's
built-in server serialises requests; and a rehearsal on a scratch schema is a surface CI cannot read, so its
figures are not quoted as the suite's.

## 10. Final project readiness

**Ready to demonstrate:** the capacity race and its closure (live, on the school's engine, in both directions), the
two database keys, the audit's reconciliation of block capacity, the executed restore, the notification addressing,
and the measured search and page-query behaviour. Each has a named test or probe and can be re-run.

**Not ready to claim:** any load ceiling, safe
retry of an interrupted enrollment, a report that reads one consistent snapshot, and protection against two desks
editing one student record. These are listed as open rather than presented as covered, which is the distinction prompt part 43
of the guide asks for and the one this report keeps.

## 11. Modified files and references

Code: `app/Http/Controllers/Blocking/BlockingController.php`, `app/Support/RetryOnDeadlock.php` (new),
`app/Console/Commands/AuditRecordStates.php`.
Schema: `database/migrations/2026_11_01_1100000000_add_database_level_enrollment_rules.php`.
Tests: `tests/Feature/Blocking/BlockCapacityAtomicityTest.php` (new),
`tests/Feature/Enrollment/DatabaseLevelEnrollmentRulesTest.php` (new).
Instruments: `storage/app/scratch/lane168-probe-last-seat-race.php` + `lane168-assign-child.php` +
`lane168-probe-desk-rights.php`, `mutate-g-ads-capacity-tests.php`, `lane163-build-scale-schema.php`,
`lane164-measure-scale-queries.php`, `lane167-probe-capacity-index.php`, `lane169-read-ads-status.php`,
`tools/ems-backup-drill.php`, docx passes 379–383.
Documents: the five ADS files plus `TESTING-AND-VERIFICATION-ADS.md`, `IMPLEMENTATION-CHANGELOG-ADS.md` and
`DEFENCE-EVIDENCE-ADS.md` (all new), `AGENTS.md`, `DEPLOYMENT.md`,
`Documentation/Prompt-Requirement-Matrix-2026-10-26.md`, and `Documentation/EMS Complete Documentation.docx`.

## 12. Next steps

1. Answer the thirteen school items on the dated sheets — two of them (P13, P14) unblock tests that cannot be
   written without a number.
2. Add the request key (P17). It is the prerequisite for extending the retry beyond one act and for answering a
   desk that lost power mid-save.
3. Batch the Registrar queue's checklist reads, which P09's measurement prices at 40 queries against 2.
4. Rule on the room's meaning, then make the room check either per-room or honest about being per-section.
5. Take the load run further than the creation act: the thousand-issue bench is delivered (`lane172`,
   `storage/app/scratch/lane172-load-out.txt`), and what it does not yet reach is a thousand concurrent *assigns*
   against deliberately short block capacity, which is `lane168`'s act widened from two writers to N.
6. Decide the search semantics, then move the directory's `LIKE '%…%'` to the spelling the measurement supports.
7. Before deployment: a scheduled dump, since the recovery story today is "the last one a person took".
