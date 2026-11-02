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
  day by the next migration in this file's list and returned the same day by the one after that**, so the delivered
  schema carries the fee-sheet key on both drivers and the seat key on the school's.

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

`database/migrations/2026_11_01_1900000000_return_the_active_seat_key_now_that_fixtures_hold_one_seat_per_term.php` —
the walk-back `down()` promised, taken the same day. The population is the census's, not the failure message's:
`storage/app/scratch/lane187-census-fixture-seats.php` reads every enrollment call site in `tests/`, binds each student
and term to where the value actually came from (a helper that mints its own person makes the call's own line the
identity; a person the caller minted once is shared by every line below the assignment, and keying it on the call line
is what reported one violation where CI refused six), classifies a row as released when its status is `dropped`, and
answers eight files writing two active seats for one student in one term, and **exits 1 rather than reporting a count**
while any call site's provenance it cannot bind — which is how the ten sites it could not bind came to a reader instead
of disappearing into a clean number. Its falsifier
`storage/app/scratch/mutate-lane187-provenance.php` carries one leg per promise — a clean helper that mints its own
student, a shared pair that must be reported, a caller-minted person that must be, a memoising maker that must not, a
`replicate()` maker classified as a maker, a name collision across two files that must not collapse, and refusals for
an unknown maker, an unresolvable name, an empty population and an unread flag. Each of the eight was repaired to name
a later term, drop the prior seat, or take a second student, and the six that CI first reached were rehearsed green on
a scratch MySQL schema with `DB_CONNECTION` pinned before the key went back. The migration's `up()` refuses a schema
that already holds the key, runs the duplicate guard first and names the student and term of any pair it finds, adds
the column as VIRTUAL because STORED answers `1215` on a table eleven others point at, and drops the column again if
the index add throws, so a half-applied schema cannot be left behind. Live `ems` was dumped first to
`C:\Users\ADMIN\ems-backups\ems-pre-seat-key-return-20261101-072527.sql` (1.42 MB, 64 `CREATE TABLE`, no
`DROP DATABASE` line), then migrated, then re-read: 37 enrollments, 24 students and 14 fee sheets, identical to the
counts taken before the write, with `SHOW INDEX` answering the three-column unique key and `information_schema`
answering `VIRTUAL GENERATED` for the column.

`database/migrations/2026_11_01_1800000000_add_request_key_to_enrollments_table.php` — additive, and deliberately the
*other* shape. `enrollments.requestKey` is a nullable varchar(64) with `uq_enrollments_request_key` over it, which
both drivers build, so the guide's problem 17 is guarded by the server on the suite as well as on CI. Nullable means
NULLs stay outside the rule (MySQL and SQLite both treat them as distinct), so a seeder, a backfill and every caller
that has not been given a key keep working untouched — which is what made this safe to add while the seat key was
still being repaired. Its guard runs before the ALTER and names a duplicate rather than skipping the key.

### 2b. Services and screens for problems 17 and 5

`database/migrations/2026_11_01_1700000000_add_row_version_to_students_table.php` — additive: `students.rowVersion`
unsignedInteger NOT NULL default 1. Guide problem 5 asks that two desks editing one student not silently overwrite,
and the schema default makes every row already on file version 1 rather than invalid. Portable on purpose: the
withdrawn seat key needed a generated column SQLite could not build, so the suite could never violate it and never
proved it either, and the key that returned is MySQL-only for the same reason; a plain column with a comparison is
enforced and exercised on both drivers. `Students` also declares
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
| `storage/app/scratch/lane198-probe-forced-rollback.php` | the guide's failure pair from ONE act: the commit (one enrollment, one form, seven boxes) and a fault thrown from a query listener between the act's two writes, re-reading the three tables to `+0/+0/+0`, with the row's visibility at the instant of the fault measured in the listener. Refuses a `--schema=` that is the live database, an unpinned connection, an injection that never fires, a fault that fires before any write, or a throw that escapes its guards. |
| `storage/app/scratch/mutate-lane198-rollback-probe.php` | the rollback probe's falsifier: a needle moved to a statement the act never issues must make the run refuse, a fault moved before any write must make the order line refuse, and the live schema, an unknown flag, an unpinned connection and an absent schema must each be refused by their own message. |
| `storage/app/scratch/lane200-check-report-buckets.php` + `mutate-lane200-report-buckets.php` | the final report's two id rows compared against the buckets `lane169` computes, reading membership from each row's second cell only; the harness drops an id, adds a phantom one, moves a record clause back into the cell, renames the marker, grows a third cell, and exercises both refusal paths — nine legs, all killed, the delivered document never mutated. |
| `tests/Feature/Registrar/EnrollmentDropTest.php` (ninth case) | guide problem 6's failure half: a query listener throws at the load retire, after the transition and its history row are written, and the case re-reads the status, every line's status, the reason column and the history count — refusing its own run if the injection never fired or if the controller swallowed the throw. |
| `storage/app/scratch/mutate-t06-drop-atomicity-tests.php` | three plants inside `drop()`'s own byte span (transaction removed; load update hoisted out of it; fault caught by the controller), each required to make that case fail **naming its own assertion**, because a plant that does not compile also prints `1 failed`. The span is located rather than needle-matched because the closure signature and the load block each occur twice in this controller. |
| `storage/app/scratch/mutate-g-ads-capacity-tests.php` | 10 legs, one per promise above, each requiring its named case to go red; green control before and after; three files restored by hash. |
| `storage/app/scratch/lane163-build-scale-schema.php` | builds `ems_l163_a` (100,000 students, 101,484 enrollments, 507,420 subject lines) and refuses a fixture that violates the invariants the probes measure. |
| `storage/app/scratch/lane164-measure-scale-queries.php`, `lane167-probe-capacity-index.php` | timings with the `EXPLAIN` row beside each figure, on the busiest partition rather than the first one. |
| `storage/app/scratch/lane169-read-ads-status.php` | reads the matrix's own Status column and prints the buckets, refusing when they do not add to 24. |
| `tools/ems-backup-drill.php` | the executed restore behind `SECURITY-AND-RECOVERY-ADS.md`. |
| `storage/app/scratch/lane201-inspect-guide-form.php`, `lane202-probe-cell.php`, `lane204-read-guide-window.php`, `lane205-probe-guide-anchors.php`, `lane206-dump-paragraph.php` | the read-only probes behind the answered form copy: the ballot census (108 literal `☐`, no form fields), a cell's own paragraph list, a paragraph window printed by BOTH ordinal and byte offset (two probes disagreed because one counted only non-empty paragraphs), the heading/row/answer-cell pairing for all 24 problems, and one paragraph's raw runs so a writer sees a split instead of guessing it. |
| `storage/app/scratch/lane203-fill-guide-form.php` | builds `Documentation/ADS_Final_Project_Guide_EMS_ANSWERED.docx` from the answer file: ticks each box whose OWN label the answer marks ☑, refuses a verdict word the form has no box for and a concept whose name disagrees with the guide's, requires all six analysis fields, answers the 25 §V sub-items one-to-one, fills the four §VII lines inside their own runs, and applies ticks, fills and 430 inserted paragraphs in ONE descending pass because two offset spaces would each shift the other. |
| `storage/app/scratch/lane207-verify-guide-copy.php` | reads the copy back window by window: each problem's answer must sit between its own `Problem N —` heading and the next, with its title, its seven fields and its enumerated question count; refuses the school's blank form with 24 defects, which is its positive control. |
| `storage/app/scratch/mutate-lane203-guide-form.php` | the writer's falsifier, 24 legs against throwaway copies of the form and the answer file (`--guide=` / `--answers=`): label-vs-position ticking, a concept row whose name is not the guide's, two ☑ in one applicability row, a verdict word the paper cannot show, a short §V list, a §VII line with no underscores, a duplicated §VII label, a missing required field, the retired `s`-modifier regression read back as 48 rows, and a control that re-reads the input form's hash because a writer that edits the school's blank original destroys it. |

| `app/Support/AuditAct.php` | the one vocabulary of named desk acts, the map from each desk route to the act it performs, the event words a plain edit keeps, and the bracketed scope that restores the previous label in a `finally` so a nested act cannot leak its name. The word list lives here and nowhere else — two answers to "what is this act called" is how `assigned` and `role.change` would otherwise sit in one column. |
| `app/Http/Middleware/NameAuditAct.php` | names the act for every row one desk request writes. The alternative — a wrapper at each write — needed an import line in ten controllers and would still have missed the second, third and fourth row an act touches; here an act is added by one map line, and the test refuses a map entry whose route name no longer resolves. |
| `tests/Feature/Audit/AuditActNamingTest.php` | the mechanism (an act names its rows; a plain edit keeps the event word; nesting restores; a thrown act clears; an unknown word is refused by name), the map's two guards (every route named exists, every value is a listed act), the reachability guard (no act word that nothing writes), the vocabulary equality, and the case that drives a real request — because every other case would stay green if the middleware were never registered. |
| `storage/app/scratch/mutate-g37-audit-act-tests.php` | the falsifier, one leg per promise, each requiring its named case to fail with its own assertion: the middleware unregistered, the middleware ignoring its map, a renamed route, a word outside the list, an act reaching nothing, the vocabulary emptied, the screen typing its own words, the observer ignoring the act, the label not restored, an unknown word accepted, a duplicate and a stray in the list, and the issuing act unwired. The harness prints its own leg total. |
| `storage/app/scratch/lane210-repoint-g37-md.php` | re-points markdown pointers by byte-read endpoints, validating every pair before writing anything and applying longest-pointer-first because `:275,283` is the head of `:275,283,344`. Written because this batch aged twenty-one markdown numbers and the citation gate reported three. |
| `storage/app/scratch/refresh-docx-pass403.php` / `refresh-docx-pass404.php` | close §28.3 G-37 and its three registers, re-take the two rows that told a reader to filter the log by `updated`, refuse unless the server answers the column counts the new cells quote, and clone the §1.1 revision row with every run emptied and order asserted on read-back. |

| `app/Http/Middleware/RejectDeactivatedAccount.php` | re-reads the signed-in account's own `status` on every request in the web group, so a desk switched off while its tab was open is stopped at its next act rather than at its next login. The status is asked, never the role. |
| `tests/Feature/Auth/DeactivatedAccountStopsMidSessionTest.php` | directional on both sides of the flip — the same account and the same route are asked while active and while deactivated, so a guard that refused everything fails as surely as one that refuses nothing; plus the signed-out state, the logout escape, the guest path, the registration, and the rule that both doors say one sentence. |
| `storage/app/scratch/mutate-g42-account-stop-tests.php` | the falsifier: the guard unregistered, the status never read, the refusal inverted, the JSON surface dropped, the reason emptied, and the login screen spelling the sentence again. Its session-invalidation leg is withheld with the reason in the file, because `logout()` already ends the signed-in state and a plant that drops `invalidate()` cannot fail. The harness prints its own leg total. |
| `storage/app/scratch/lane212-weigh-question-register.php` | weighs §29.4's "Open decisions" cell against the rows physically under §28.5's own heading, read from the archive rather than the flat dump because a dump that drops empty cells cannot state a row's structure. It exists because two docx rows were added to that register and no instrument could see the count cell stop growing. It finds the cell by shape (the cell whose whole text is the label, the next cell numeric), so a reworded heading refuses rather than silently judging nothing, and it tells a missing dump, an empty value, an unknown flag, a non-zip and a non-numeric cell apart by message. |
| `storage/app/scratch/mutate-lane212-question-register.php` | the weigher's falsifier: a stale cell, a deleted row, a reworded label, a non-numeric cell, a missing heading, a text file named `.docx`, a missing path, an unknown flag, an empty flag value, and a byte-identical control that must change no verdict. The harness prints its own leg total and deletes its planted copies, verifying the deletion by listing the directory rather than by its own exit code. |
| `storage/app/scratch/refresh-docx-pass405.php` to `refresh-docx-pass410.php` | the six docx passes this batch wrote: the two §28.3 rows for the session cut-off, the §1.1 revision row, the two new §28.5 question rows, the count cell re-taken from the weigher's own output, the §1.1 row and the `auditlogs` column cell for the act vocabulary, and the repair of the two complaints the earlier five created — a pointer that quoted the ADS guide's own numbering as though it were this document's section, and the §28.8 actor table left counting rows its own column no longer charges to an office. Pass 408 is the one that makes the weigher's verdict load-bearing: it writes the number the weigher printed, then re-runs the weigher as its own read-back. |
| `App\Support\DeskQueues::blocking()` | the ninth dashboard card's population, ruled 2026-11-02 as item 9 of the 2026-10-29 sheet: the card counts what its own page lists. `BlockingController::index()` now builds its rows from it, so the card and the list are one call and the class holds no exception any more; the enrollments waiting at the Blocking box are still what the workflow bell addresses, and that method is unchanged. |
| `tests/Feature/Dashboard/DashboardQueueCountMatchesDeskTest.php` (the blocking case) | the badge equal to the tile the same page prints, on a fixture where the retired predicate answers a *different* number — one enrollment whose first open box is Blocking beside two blocks — because an equality between two zeros would certify any badge. The two method names in that class lost their numerals the day the counts moved (four lists, six desks), which is the membership defect this repository has refused in lanes 46, 61, 64 and 90 arriving inside a test name. |
| `storage/app/scratch/refresh-docx-pass411.php` | closes §28.3 G-46's last sentence, which still told the reader the blocking card was owed. The row's `Closed 2026-10-30` verdict is not re-dated; the new sentence carries the day the ninth card moved, so the row reads as a record. |

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
- `Documentation/ADS_Final_Project_Guide_EMS_ANSWERED.docx` — new: the school's own form, answered. 43 boxes ticked
  (one applicability verdict per problem, 19 across the 12 concept rows because ACID and Recovery each carry two),
  24 problem answer blocks written into their own cells, all 25 §V sub-item answers, §IV's N/A explanation, §VI's
  defense answer and the four §VII fill-ins. Its content is projected from `GUIDE-PROBLEM-ANSWERS-ADS.md`, never
  retyped into it, so one document owns each verdict; Word opens it at 37 pages with 50 tables and
  `docx-ooxml-check.php` reads CLEAN.
- `Documentation/GUIDE-PROBLEM-ANSWERS-ADS.md` — a provenance paragraph naming the Word copy, the rebuild command
  and the verifier, and stating that a new option wording changes this file first because the form has no box for a
  word it does not offer.

## 5. Instruments repaired while doing the work

Each of these produced a clean-looking verdict before it was fixed, which is the only reason they are listed.

- `mutate-g46-dashboard-queues.php` carried two `case` strings naming test methods by their counts — `four_desk_lists…`
  and `the_six_desks…` — so the day those lists grew, the harness would have reported `No tests found` as a failed
  control over a green tree. Both methods are renamed without numerals and the legs follow; two new legs plant the
  retired *subject* (the waiting-enrollments badge) and the re-inlined block list, and the delivered run answers
  every leg killed with the control green before and after.
- A first draft of `refresh-docx-pass411.php` wrote its text edit as `[from, to, from, 1]`, meaning "the needle is its
  own anchor". The engine reads slot 3 as a *different* string that must own the needle, so anchor == needle resolves
  to nothing and the pass refused with `no needle owned by the anchor`. The two-element shape is the one for a needle
  already proved unique, and the pass proves that uniqueness itself before opening the archive.
- `lane127-resolve-lines.php` answered `12 HITS` for `DeskQueues.php:96`, because that HEAD line is a bare `}`. That
  is the correct refusal — an ambiguous line is not a re-point — and the markdown pair came instead from the window
  the docx shift tool proved byte-identical across three copies (`87-96 → 85-94`), not from the delta arithmetic.

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
- `lane203`'s first cut appended each analysis field only when it parsed, and the answer file spells the workflow
  field three ways (`… or why this is N/A`, `… and N/A justification`, `… adapted`), so Problems 19 and 24 reached
  the paper without a field the guide asks for while every count still read 24 blocks. The six fields are required
  now, and a missing one refuses the run.
- `lane203`'s heading parse carried the `s` modifier, so a one-line "title" captured the whole markdown block and
  each inserted line re-quoted the answer file's own `☐ Applies to our system` row: 67 ballot characters where 43
  were planned. The per-row read-back caught it and deleted its own half-written copy; the retired modifier is now
  a harness leg that must make the run refuse with `48 applicability rows`.
- `lane194-ads-artefact-citations.php` exempted the delivered copy as "the school's guide docx, held outside the
  tree" purely by NAME-SHAPE, so a living artefact the repository now holds stopped being judged; and three separate
  loops matched the class list, one of which printed the reason as the literal word `Array` after the values gained a
  second field. There is one `$classExempt` now, existence is weighed before the classes whose claim is "not in this
  repository", and HEAD exports and pre-change dumps keep exempting despite local existence because those files are
  on this box by design. `mutate-lane194-ads-citations.php` carries both directions.
- `word-open-copy-test.ps1` hardcoded the authority docx, so a delivered copy could not be opened in real Word
  without a second script that would re-implement the same test. It takes `-Source` and `-Report` now, defaulting to
  the authority document; the first run against the new copy read a stale report file and had to be re-taken after
  deleting it, which is the reading this project keeps paying for — decide from what the command printed, not from
  what the file beside it used to say.
- `mutate-g37-audit-act-tests.php` reported, on thirteen of its seventeen legs, that the tree was not restored after
  the vocabulary file — while the restore was working. Its hash table was built with `array_map('md5', $originals)`
  over an array keyed by FILENAME, so `array_map` preserved those keys, and the guard compared each file against
  `$hashes[$i]` with a numeric `$i` — an undefined key, compared to a real md5, which never agrees. A harness that
  accuses a clean tree of staying dirty is worse than one that cannot fail, because the next reader relaxes the guard
  instead of reading it; the comparison is keyed by filename now, and the leg that failed first is the one that found
  it. (This bullet originally quoted the complaint's own text, and the artefact gate read the filename inside that
  quotation as a path citation to a file at the repository root — which is the same lesson the citation gate already
  carries: a citation-shaped token is a citation even inside prose about citations.)
- The same harness's `page-types-its-own-words` leg demanded the case fail with `name a word nothing writes`, and the
  case failed earlier with `the page is typing its own action words again` — its own assertion that fires first. A
  leg's expected sentence must be read from the code's order, not from the last assertion in the list.
- The register pass wrote its `read-back refused [...]` message with a `"` opening and a `'` closing, which parses as
  one long string and fails at runtime rather than at lint; and `printf("%2$d")` inside double quotes interpolated
  `$d` away, so the format became `%2 ` and PHP threw "Unknown format specifier". Both are this file's oldest trap —
  a quote or a `$` inside a string that is built by interpolation — arriving in a pass script written ten minutes
  after the last time it was named.
- `Documentation/Prompt-Requirement-Matrix-2026-10-26.md` claimed for weeks that prompt §9 is the section-capacity
  requirement and that the prompt states no seven-family audit vocabulary. The prompt was opened this session: §9 is
  titled "Admin, Access Control, Audit Logs, and Archiving" and its Audit logs subsection enumerates exactly seven
  families. The correction that had been made *to* the register was the error, and the row it accused was right — so
  the reading of an outside document is a claim that needs the document, not a grep of the tree.

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

`ems_l163_a` (scale measurements), `ems_l170_a` (the migration rehearsed from empty) and `ems_l197_a` (the
MySQL-parity rehearsal and the commit-and-forced-failure pair) are named by tracked files and are therefore kept;
`drop-retired-scratch-databases.php` will print KEEP for them. Live `ems` was verified
unchanged before and after every clone, by row count read in both schemas.
