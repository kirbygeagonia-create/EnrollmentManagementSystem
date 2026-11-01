# Implementation changelog — Advanced Database Systems

Requirement set: `Enrollment_Management_System_Master_Implementation.md`. Batch date 2026-11-01.
Companion documents: `REQUIREMENTS-TRACEABILITY-ADS.md` (what the guide asks and what the build answers),
`DATABASE-CONCEPT-COMPLIANCE-ADS.md`, `SYSTEM-ARCHITECTURE-ADS.md`, `DATABASE-DESIGN-ADS.md`,
`SECURITY-AND-RECOVERY-ADS.md`, `TESTING-AND-VERIFICATION-ADS.md`.

The strategy the guide asks for — inspect, then change safely without jeopardising the existing system — was applied
in this order: read the population from the guide itself, weigh each claim against the code and the live schema,
change only what the evidence supported, and make each change falsifiable before it was called done.

---

## 1. Code changed

| File | Change | Why |
|---|---|---|
| `app/Http/Controllers/Blocking/BlockingController.php` | `assignStudents()` restructured: the requested ids are de-duplicated and sorted before the transaction; the whole act is wrapped in `RetryOnDeadlock::run()`; the block row is locked with `lockForUpdate()` as the transaction's first act; the capacity count is re-taken under that lock; the room row is locked second, in that fixed order, and counted the same way. | Guide problems 2, 4, 7, 10, and the repository's own open finding DI-5 (`System-Design-Quality-Review.md`, 2026-09-15). The count used to run outside the transaction it guards, so two writers both read it and both wrote. Measured: HEAD overbooked a block of one with two seats. |
| `app/Support/RetryOnDeadlock.php` | New. Retries only MySQL 1213 and 1205, reads the code from `errorInfo[1]` before `getCode()`, bounds attempts, jitters the delay, and refuses with a message that says nothing was saved. Its docblock states the precondition for wrapping at all: the act must be one transaction. | Guide problem 7's "retry safely". A retry around an act that had already committed part of its work would duplicate it, so the helper says what it may be used for rather than being a general-purpose loop. |
| `app/Console/Commands/AuditRecordStates.php` | Twentieth rule `blockOverCapacity()`: each block's held seats (distinct enrollments, dropped excluded — the write guard's own definition) against the `maxStudents` the block declares. | Guide problem 4's read side. The write guard now makes the state unreachable from a desk; a restored dump or an edited row still has to be named. Measured 0 on live `ems` before it was added, so the standing gate stays green. |
| `storage/app/scratch/lane172-enrollment-day-load.php`, `lane172-issue-child.php` | New. Drives 1,000 creation acts through `EnrollmentIssuer::issue()` from ten processes on a scratch clone, then ten workers against one seat, then five drop-versus-issue races; reads every invariant back from the server; `--verify-only` re-runs the same questions against an existing schema so they can be falsified. | Prompt part 40 and guide problems 23 and 24, which asked for a load run rather than a load design. Delivered: 69.2 acts/s, 0 duplicate seats, 0 orphan forms, 1 winner on a contended seat, 0 students holding two seats after a raced cancellation. |
| `storage/app/scratch/mutate-lane172-load-integrity.php` | New. Damages a copy of the kept load schema — drops the seat key and plants a duplicate, adds an orphan form, deletes a form, doubles a form, overruns a block through `enrolledsubjects` — and requires `--verify-only` to name each and exit 1, with a clean control before and after and refusals for a source outside the scratch pattern. | The rule that a checker which has never printed a failure is not trusted. Two of its own plants were wrong before they were right: it named a generated column in an INSERT (MySQL 3105) and deleted a form without its dependent box rows (1451), which is the schema answering the harness. |

## 2. Database changed

`database/migrations/2026_11_01_1100000000_add_database_level_enrollment_rules.php` — additive, both guards
measured before writing:

- `uq_studentassessments_enrollment` on `studentassessments(enrollmentId)`. Closes DI-2, open since 2026-09-15.
- `uq_enrollments_active_seat` on `enrollments(studentId, termId, seatKey)`, where `seatKey` is a **VIRTUAL**
  generated column, non-null exactly when `enrollmentStatus <> 'dropped'` and a term is named. **Withdrawn the same
  day by the next migration in this file's list**, so the delivered schema keeps only the fee-sheet key.

Live `ems` was dumped to `C:\Users\ADMIN\ems-backups\ems-pre-db-enforcement-2026-11-01.sql` first (64 tables,
1.42 MB, `INSERT INTO enrollments` verified present), then migrated, then re-read: 37 enrollments, 24 students,
14 fee sheets and 3,526 audit rows, identical before and after. No row was deleted, altered or re-seated.

`database/migrations/2026_11_01_1600000000_withdraw_the_active_seat_key_until_the_fixtures_are_repaired.php` —
removes `uq_enrollments_active_seat` and its VIRTUAL `seatKey`, and nothing else. It is additive in the other
direction: `down()` re-adds the column as VIRTUAL (not STORED, for the `1215` reason above) and re-adds the key, so
the withdrawal is a step that can be walked back rather than a decision that destroys the work. The reason is stated
in the migration's own docblock and is not a schema problem: six test files write two active enrollments for one
student in one term, which is the state the key exists to refuse and the product forbids, and CI's MySQL job was the
only surface able to see them — the suite's `:memory:` schema compiles no `ALTER TABLE … ADD COLUMN … GENERATED`, so
the key never existed where the suite runs and every local green was silent about it. The fee-sheet key is left
standing because it is portable: SQLite enforces it and its case passes on both drivers.

`database/migrations/2026_11_01_1800000000_add_request_key_to_enrollments_table.php` — additive, and deliberately the
*other* shape. `enrollments.requestKey` is a nullable varchar(64) with `uq_enrollments_request_key` over it, which
both drivers build, so the guide's problem 17 is guarded by the server on the suite as well as on CI. Nullable means
NULLs stay outside the rule (MySQL and SQLite both treat them as distinct), so a seeder, a backfill and every caller
that has not been given a key keep working untouched — which is what makes this safe to add while the seat key is
still being repaired. Its guard runs before the ALTER and names a duplicate rather than skipping the key.

### 2b. Services and screens for problems 17 and 5

`database/migrations/2026_11_01_1700000000_add_row_version_to_students_table.php` — additive: `students.rowVersion`
unsignedInteger NOT NULL default 1. Guide problem 5 asks that two desks editing one student not silently overwrite,
and the schema default makes every row already on file version 1 rather than invalid. Portable on purpose: the
withdrawn seat key needed a generated column SQLite could not build, so the suite could never violate it and never
proved it either; a plain column with a comparison is enforced and exercised on both drivers. `Students` also declares
`protected $attributes = ['rowVersion' => 1];` because a schema default is **not** handed back to a model that was
just created — without it the object in memory carried null, the screen posted null, and the guard refused a desk that
had done nothing wrong. The column is deliberately outside `$fillable`: a client that can set its own version can walk
past the check, so the bump is a direct property assignment inside the write.

`EvaluationController::captureProfile()` reads the held version before the transaction and refuses a mismatch naming
both numbers and writing nothing — the refusal sits outside the transaction so a rejected save cannot leave a partial
profile, a re-stamped address row or a spent version. `resources/js/Components/EnrollmentProfileForm.jsx` puts
`student.rowVersion` on the wire through the form's transform, so the number comes from the record the page was
rendered from and never from a field a person can type.

`App/Services/EnrollmentIssuer::issue()` takes a third argument, `$requestKey`. A key already on file is answered with
the enrollment it created — same record, no second workflow form — and a second request that races past the read is
refused by the index and then answered the same way, so **the index is the guard and the read is only the fast path**
(lane 116's rule, applied to the record the school cares more about). A key that arrives naming a different student or
term raises a validation refusal naming both ids instead of handing over the first student's enrollment, because a
wrong record delivered as a success is worse than a refusal.

`Enrollments::$fillable` gained `requestKey`. This is the one line in the batch that a green suite could not have
caught on its own: mass assignment drops an unlisted field with no error, no warning and no failing query, so leaving
it out would have written keyless rows and let the whole mechanism report success.
`storage/app/scratch/mutate-p17-idempotency-tests.php` plants exactly that omission, among its legs.

`EvaluationController::store()` and `ShiftRequestController`'s decision accept and forward the key
(`nullable|string|max:64`); `resources/js/Components/ui/requestKey.js` mints it when an act is opened and the issuing
page replaces it after a success, because a page that issues five students performs five acts with five keys. The
Admission approval path still creates its enrollment directly at `AdmissionController.php:594` and is **not** keyed in
this batch — it already consults `seatHolder()` before writing, so it cannot mint a second seat, but it has no answer
to "did my approval commit", and that is stated here rather than covered by a claim.

Two choices worth recording:

- **VIRTUAL, not STORED.** A stored generated column rebuilds the table, and `enrollments` is referenced by eleven
  child tables, so MySQL 8.4 answered `1215 Cannot add foreign key constraint` — with `foreign_key_checks` off too.
  The virtual form is added in place, and the unique index over it is accepted. Both refusals were observed before
  the spelling was chosen.
- **No trigger, no stored procedure.** The guide permits them; the generated column keeps the rule inside the schema,
  which means a restore carries it and a future reader finds it in `information_schema` rather than in a routine.
- **The migration refuses to run over violating data** and names the enrollment or the student/term pair. A silent
  skip would leave an install believing it was guarded.

## 3. Tests and instruments added

| Name | What it pins |
|---|---|
| `tests/Feature/Blocking/BlockCapacityAtomicityTest.php` | 12 cases: the count inside the transaction after the lock, both rows locked, the sorted request, a refusal that seats nobody, a dropped line that consumes no seat, and `RetryOnDeadlock`'s four boundaries. |
| `tests/Feature/Enrollment/DatabaseLevelEnrollmentRulesTest.php` | the two keys, each on the driver that can hold it; SQLite names what it cannot judge instead of skipping. Rehearsed on MySQL on `ems_l170_a` before any push. |
| `storage/app/scratch/lane168-probe-last-seat-race.php` + `lane168-assign-child.php` | two real MySQL writers against one seat, three passes (working, HEAD restored by hash, working again), refusing when HEAD produces the clean outcome. |
| `storage/app/scratch/mutate-g-ads-capacity-tests.php` | 10 legs, one per promise above, each requiring its named case to go red; green control before and after; three files restored by hash. |
| `storage/app/scratch/lane163-build-scale-schema.php` | builds `ems_l163_a` (100,000 students, 101,484 enrollments, 507,420 subject lines) and refuses a fixture that violates the invariants the probes measure. |
| `storage/app/scratch/lane164-measure-scale-queries.php`, `lane167-probe-capacity-index.php` | timings with the `EXPLAIN` row beside each figure, on the busiest partition rather than the first one. |
| `storage/app/scratch/lane169-read-ads-status.php` | reads the matrix's own Status column and prints the buckets, refusing when they do not add to 24. |
| `tools/ems-backup-drill.php` | the executed restore behind `SECURITY-AND-RECOVERY-ADS.md`. |

## 4. Documents changed

- **Authority docx** (the record of this batch's own edits):
  - pass 379 — the notice-scope wording that preceded this batch.
  - pass 380 — §18's command chapter: nineteen rules → twenty, the enumeration extended with the capacity rule, and
    the reading that made it worth adding (live `ems` 0, the racing scratch schema 1).
  - pass 381 — three `BlockingController.php` pointers the shift tool would not write, taken by reading. One of them
    (`filters its roster by it`) had been a wrong target — a docblock opener — *before* this batch touched the file.
  - pass 382 — §23.6.1 gains the two key rows (four cells each, cloned from the scholarship row) and its tally sentence
    moves to twelve keys in ten tables; §24's `enrollments` heading moves 23 → 24.
  - pass 383 — §24's column-aggregate row 437 → 438, typed only after `lane60` confirmed that exact pair.
- `AGENTS.md` — the rule-count sentences that the twentieth rule moved, and nothing else: a dated row is never re-aged.
- `DEPLOYMENT.md` §8 — the twentieth rule described where an operator reads rule counts.
- `Documentation/Prompt-Requirement-Matrix-2026-10-26.md` — the audit's rule count beside the row that cites it.
- The five ADS documents listed at the top, of which `TESTING-AND-VERIFICATION-ADS.md` and this file are new.

## 5. Instruments repaired while doing the work

Each of these produced a clean-looking verdict before it was fixed, which is the only reason they are listed.

- `lane163` filled blocks with `$sid % count($blockIds)`, which over-filled the first ones by the demo cast's id
  remainder — a block holding 41 seats against `maxStudents` 40. The fixture then violated the invariant its probes
  claimed to measure. Now filled in order, topped up from the real id high-water mark, and refused if any synthetic
  block is over capacity.
- `lane167` / `lane164` took the *first* block, then the busiest, then discovered the seed stamped no `scheduleId` at
  all, so the room count printed `NOT MEASURED`. The fixture now creates rooms, schedules and stamps the matching
  subject line the way the controller does.
- An earlier reading in this batch's working notes called the capacity count a bottleneck (410 ms). It was an artefact
  of the degenerate distribution above; the honest reading is a `ref` seek over ~200 rows on a block at 40 of 40 out
  of 507,420 rows, with the optimizer keeping the FK index either way — and the millisecond moves between runs of the
  same command on the same schema (0.86 ms and 2.77 ms on the two 2026-11-01 readings), which is why the documents
  quote the plan and cite the captured file rather than carrying a timing as a property of the code. **The count is
  not the problem; the race is.**
- `lane127-repoint-md-cites.php` carried a hand-kept list of eleven documents and reported `0 pointer(s)` over two
  files it never opened, which held five pointers into the file that had just moved. It now derives its population
  from the `Documentation` tree and refuses a walk that opened too few files.
- `lane44-unique-keys.php` could not read past `Ten keys in eight tables`, so a truer sentence made it UNBOUND.
  Its vocabulary now runs through twenty and accepts digits.
- `mutate-g-ads-capacity-tests.php`'s first cut read only a `Tests: N passed` line, so every killed leg would have
  looked BAD; it now reads failures in their own shape. Two of its anchors also had to be re-cut once, and one leg's
  needle interpolated a php variable away in a double-quoted string.
- `lane169-read-ads-status.php`'s first cut reported every row as malformed because `trim($line, '| ')` left the
  trailing newline attached — a refusal pointing at the reader, not the register.

## 6. Deliberately not done

- The search rewrite (prefix or fulltext). Measured, priced, and left: both spellings change what a desk can find.
- The Registrar-queue batching. Measured (40 queries against 2), and it is code work — but it is a separate change
  with its own citations, not a rider on this batch.
- A request key (matrix P17). It is the prerequisite for retrying anything beyond one transaction, so the retry
  helper is scoped to one act and says so.
- Room-level occupancy. The app weighs a room per schedule, so a room-wide invariant would flag data the app itself
  produces; the gap is registered, not silently "fixed".
- A faculty load cap and the term unit ceiling (P13, P14): the numbers are the school's, and inventing a default is
  the failure mode this repository refuses.
- A scheduled backup or point-in-time recovery: `SECURITY-AND-RECOVERY-ADS.md` records the executed drill and the
  absence beside it.

## 7. Rehearsal schemas

`ems_l163_a` (scale measurements) and `ems_l170_a` (the migration rehearsed from empty) are named by tracked files
and are therefore kept; `drop-retired-scratch-databases.php` will print KEEP for them. Live `ems` was verified
unchanged before and after every clone, by row count read in both schemas.
