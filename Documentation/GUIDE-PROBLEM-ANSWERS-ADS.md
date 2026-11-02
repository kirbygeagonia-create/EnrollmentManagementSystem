# ADS Final Project Guide — Every Problem, Concept and Checklist Item Answered
### SEAIT Enrollment Management System

Guide source: `ADS_Final_Project_Guide_EMS_updated.docx`, read out of the archive by
`php storage/app/scratch/lane155-read-ads-guide.php` and flattened to
`storage/app/scratch/ads-guide-flat.txt`. This file answers that guide's own answerable population,
in the guide's own order and words: **24 problems carrying 106 guide questions**, each problem's
Applicability mark and its "if N/A or different from our workflow" line, the 12 rows of §IV's
concept table plus its N/A explanation, the 25 sub-items of §V's presentation requirements, §VI's
defense question, §VII's four fill-ins, and §I's six task verbs. `lane155` printed that population;
`php storage/app/scratch/lane195-count-guide-answers.php` weighs this file against it and refuses
until every item carries an answer, so the completeness claim is an instrument's reading, not a
sentence.

Applicability is taken from the P register in
`Documentation/REQUIREMENTS-TRACEABILITY-ADS.md` rather than restated here, because two answers to
"does this problem apply" is the defect this project has refused in every other vocabulary. Where
this file and that register disagree, the register is wrong and says so.

Answering rules honoured, from §II.B: a feature the system does not have is not claimed; a problem
that partially applies names which part; every N/A or different-workflow line carries its
justification and, where the concept still has to be shown, the controlled test or the design that
would show it; the demonstrations are database-level (constraints, transactions, locks, indexes,
permissions, a restore) rather than screenshots of a screen; and every record named is from the
synthetic demo cast, never a real student.

Workflow, stated once because §II.A asks for it: enrollment here is **registrar-assisted and
department-assisted**, with a walk-in applicant entering through the Admission desk. Students have
no login accounts in this build. Each enrollment still requires a valid student record
(`EnrollmentIssuer::issue()` writes the enrollment and its workflow form in one transaction, and
`students.schoolIdNumber` is unique), so §II.A's "each enrollment must be associated with a valid
student record" holds even though no student ever clicks.

The six task verbs of §I are carried by every problem block below, in this order: the concept
involved, the design, the implementation, the test or demonstration, the evidence, and the
workflow difference or N/A justification. Each guide question is quoted verbatim and answered on
the line under it.

## I. The six things the guide asks for, and where each is answered

The guide's overview says "Your task is to:" and lists six verbs. Each problem block below carries all
six, in this order, so the verbs are answered per problem rather than once for the whole document:

- *Analyze each database problem.* — the block's opening lines and the answer under each of its guide
  questions.
- *Identify the database concept involved.* — the **Concept involved** line.
- *Design an appropriate solution that fits your system.* — the **Design** line.
- *Implement the solution where applicable.* — the **Implementation** line, naming the file, the
  transaction, the lock, the key or the constraint; and, where nothing is built, saying so.
- *Test the solution or demonstrate an equivalent scenario.* — the **Test or demonstration** line,
  naming the case, the probe or the executed drill, or the T-id that is owed.
- *Provide evidence that the solution works, such as screenshots, SQL output, logs, or query plans.* —
  the **Evidence** line, naming a tracked capture, a test file or an instrument's own output.

The guide's part II is answered once, in the header above: the workflow statement (part A) and the
answering rules (part B: no claimed feature the build lacks, which parts apply when only a part does,
every N/A justified with its equivalent test, database-level demonstrations, synthetic data only).
Part C's concept list — transactions, COMMIT and ROLLBACK, ACID, concurrency control, locking,
deadlocks, constraints, indexing, query optimization, data integrity, security, recovery — is the
twelve-row table answered in part IV below.

---

## III. Database Problems to Solve

### Problem 1 — Incomplete Enrollment Transaction

**Concept involved:** transactions, atomicity, COMMIT and ROLLBACK, one unit of work per act.
**Applicability:** ☑ Applies to our system ☐ Partially applies ☐ N/A
**Design:** an enrollment act is one closure transaction, never a per-subject commit, so the act's
writes (subject lines, the status transition and its history row, the box signature, the fee sheet)
commit together or not at all.
**Implementation:** 38 `DB::transaction` sites in `app/`. `App\Services\EnrollmentIssuer::issue()`
writes the enrollment and its workflow form together; `RegistrarController`'s approve and drop wrap
subject lines, the transition and the signature; `Evaluation\EvaluationController::proposeSubjects()`
deletes then re-inserts the whole load inside a transaction whose **first statement** is a row lock
on the enrollment, followed by a re-read so the second writer never replays from a stale copy.
`App\Services\EnrollmentStateMachine` is the only writer of `enrollmentstatushistory`.
**Test or demonstration:** T01, executed 2026-11-02, two cases in
`tests/Feature/Evaluation/RetakeAttemptTest.php` — a two-subject proposal leaves exactly two
proposed lines and exactly one `evaluated` history row, and a refused proposal leaves the existing
load untouched and does not move the status. Proved by
`php storage/app/scratch/mutate-g-t01-subject-commit.php` (insert only the first line; disable the
duplicate guard; each must make its named case fail). The forced-failure print the guide also asks
for (T02) is still owed.
**Evidence:** those two cases, `tests/Feature/E2E/EnrollmentWalkthroughTest.php` driving the whole
chain, and `storage/app/scratch/lane190-out.txt` for the same act under two real writers.
**Workflow difference, or why this is N/A:** none. The registrar types the load the student asked
for; the act is the same one transaction either way.

**Q1.1** What should happen to the other successfully processed subjects?
**Answer:** they are undone with the failed one. Nothing in this system commits a subject
individually, so there is no partially-saved load to reason about; the enrollment keeps exactly the
lines it held before the act began, which is what T01's second case asserts.

**Q1.2** How will you ensure the enrollment is completed as one operation when required?
**Answer:** by putting every write the act needs inside one `DB::transaction` closure and taking the
row lock on the shared resource **before** the first write, so a second writer cannot interleave
between the delete and the insert. Where all-or-nothing is not required, that is said rather than
quietly broken: the timetable capacity mismatch is deliberately warn-and-proceed, stated in the code
and in the authority document.

**Q1.3** How will the system handle the failure?
**Answer:** the exception leaves the closure, Laravel rolls the transaction back, and the desk gets
a named reason instead of a stack — `App\Support\DenialReason` prints it on the 403 page and as JSON
for a screen that asked for JSON, validation errors land on the field. A 1213 or 1205 is retried by
`App\Support\RetryOnDeadlock` and, when its attempts are spent, refuses with "Nothing was saved —
please try again." The attempt itself is in `auditlogs` either way.

**Q1.4** Which transaction operations are needed?
**Answer:** BEGIN at the closure's first statement, the writes, COMMIT when the closure returns,
ROLLBACK when it throws; plus the retry decision around the whole transaction, and the idempotency
key of Problem 17 so that a *resubmission* after a failure is answered with the record that exists
instead of a second act.

### Problem 2 — Last Available Slot

**Concept involved:** concurrency control, pessimistic row locking, serialising two writers on one
resource.
**Applicability:** ☑ Applies to our system ☐ Partially applies ☐ N/A
**Design:** the section's own row is locked before its holders are counted, so the count and the
write belong to one window that nobody else can enter.
**Implementation:** `Blocking\BlockingController`'s assign act takes
`Enrollments`-side locks in a fixed order — `lockForUpdate()` on the `blocks` row as the
transaction's first statement, the holder count re-taken inside it and compared with
`Blocks.maxStudents`, the `rooms` row locked **second**, and the requested student ids de-duplicated
and sorted before the transaction opens. The act runs inside `App\Support\RetryOnDeadlock`.
**Test or demonstration:** `tests/Feature/Blocking/BlockCapacityAtomicityTest.php`, and two real
MySQL connections driven by `php storage/app/scratch/lane168-probe-last-seat-race.php --pairs=3`,
whose children call `assignStudents()` directly because PHP's built-in server handles one request at
a time and an HTTP pair would serialise and read as a fixed bug.
**Evidence:** `storage/app/scratch/lane168-out.txt`. HEAD's bytes: 5 assigns, 0 refusals, a block
with `maxStudents` 1 holding 2 seats, and one genuine `SQLSTATE[40001]` 1213 that reached the desk
as a 500. The locked bytes: 3 assigns, 3 capacity refusals, 0 deadlocks, never more than 1 seat —
repeated, the second time after the working bytes were restored by hash.
**Workflow difference:** the guide says requests "may be entered by students or by registrar staff";
here they are entered by the Registrar or a blocking officer, which is the same contended resource
and the same lock.

**Q2.1** How will the system determine which request gets the available slot?
**Answer:** the engine does, not the application. The first transaction to hold the block row's
exclusive lock counts, writes and commits; the second waits on that lock, and when it is granted it
re-counts against a section that is now full and is refused with the capacity reason. Arrival order
at the database is the tie-break, which is the honest answer: nobody is promised a queue position
the application never had.

**Q2.2** How will you prevent the section from exceeding its capacity?
**Answer:** the count is taken under the lock and compared with `Blocks.maxStudents` before any
student is seated, so overbooking needs two committed writers and there is only ever one inside that
window. After the fact, the record-state audit's twentieth rule ("a block holding more students than
it declares") weighs the same question over the whole schema, so a count that drifts by another path
is reported rather than trusted.

**Q2.3** How will simultaneous transactions be handled?
**Answer:** one blocks, then re-reads; the pair never both writes. If the two hold different locks
that each other needs, InnoDB detects it and kills one with 1213, and `RetryOnDeadlock` re-runs that
one while every other `QueryException` propagates untouched. Before this act was locked, the probe
observed that 1213 reaching the desk as an unhelpful 500, which is the reason the retry wrapper sits
outside the transaction rather than inside it.

**Q2.4** What database mechanism can be used?
**Answer:** `SELECT ... FOR UPDATE` (`lockForUpdate()`) on the section row, a single fixed lock order
across every writer, short transactions, and — as the last line of defence rather than the primary
one — the unique keys of Problem 3, which refuse a row written straight to the table.

### Problem 3 — Duplicate Enrollment

**Concept involved:** declarative integrity — unique constraints — plus idempotency.
**Applicability:** ☑ Applies to our system ☐ Partially applies ☐ N/A
**Design:** every "at most one of X per Y" rule the school states becomes a UNIQUE index, and the
application predicate that runs first only exists to give the desk a readable reason.
**Implementation:** `uq_enrollment_subject_attempt` on `enrolledsubjects (enrollmentId, subjectId,
attempt_number)`; `uq_studentassessments_enrollment` (one fee sheet per enrollment);
`uq_enrollments_active_seat` on `enrollments (studentId, termId, seatKey)` where `seatKey` is a
**VIRTUAL** generated column that is non-null exactly when the enrollment is not dropped and names a
term — so MySQL ignores the NULL, a drop releases the seat with no trigger and no deleted row; plus
`uq_students_schoolid`, `uq_payments_ornumber`, `uq_notifications_dedupe` and
`uq_enrollments_request_key`. Application predicates: `EnrollmentIssuer::seatHolder()`, the
proposal's duplicate guard, request validation.
**Test or demonstration:** `tests/Feature/Enrollment/DatabaseLevelEnrollmentRulesTest.php` —
driver-appropriate, never skipped: on MySQL the index refuses by name and a drop releases, on SQLite
the same case asserts the key is absent **and** that the application predicate holds.
`tests/Feature/Evaluation/RetakeAttemptTest.php` pins the attempt key by handing the same subject
twice. `php storage/app/scratch/lane154-probe-duplicate-keys.php` answered 0 duplicate groups on
live `ems` before either enrollment key was written, which is what made them safe to add additively.
**Evidence:** those files, and §23.6.1 of the authority document, which claims to list *every*
unique index in the domain tables and is weighed by `php storage/app/scratch/lane44-unique-keys.php`.
**Workflow difference:** a duplicate here is normally the Registrar's double-click, not a student's,
which is why Problem 17's request key is the second half of this answer.

**Q3.1** How will you prevent the same student from being enrolled in the same section more than once?
**Answer:** the attempt key refuses a second row for the same enrollment, subject and attempt, and
the active-seat key refuses a second active enrollment for the same student and term. Together those
two are the guide's rule: one person cannot sit the same subject twice, and cannot hold the same
term's seat twice.

**Q3.2** Should the application, database, or both enforce the rule?
**Answer:** both, and the division is deliberate. The database is the only arbiter that sees every
writer, including a raw insert, a second session and a fixture that bypasses a controller; the
application is the only place that can say *which* record already holds the seat. The app gives the
reason, the key guarantees the outcome.

**Q3.3** What happens if both requests arrive simultaneously?
**Answer:** both may pass the application check and only one commit survives; the loser receives
error 1062 from the server and is translated into a named conflict rather than a page that tells the
desk to reload and retry something that can never work. When the two requests are actually one act
re-sent, Problem 17's key makes the second send answerable: it returns the enrollment that exists.

**Q3.4** Which database constraints can be used?
**Answer:** UNIQUE composite indexes on the natural key of the rule, including a generated column
when the rule is conditional (active means not dropped), plus the foreign keys that make a duplicate
impossible to orphan. A `CHECK` cannot express "no second row elsewhere in the table", which is why
uniqueness — not an expression — carries this rule. The range rules the guide likes elsewhere
(capacity non-negative, units non-negative) are still owed a `CHECK`; that gap is named in §IV row 7.

### Problem 4 — Incorrect Section Capacity

**Concept involved:** derived state versus stored state, consistency, aggregate weighing.
**Applicability:** ☑ Applies to our system ☐ Partially applies ☐ N/A
**Design:** the section holds no running counter that can drift. `Blocks.maxStudents` is the school's
declaration; the number of holders is counted from `enrollments` every time it is needed.
**Implementation:** the assign act counts holders under the block lock (Problem 2); the block/room
comparison warns when `maxStudents` exceeds the room's capacity and says so in the returned reason;
`php artisan ems:audit:record-states` carries the twentieth rule, which counts every block's holders
against its own ceiling over the whole schema; every desk tile that reports a count clones its
builder **before** `paginate()`, so the number describes the whole filtered set rather than the
twenty rows on screen.
**Test or demonstration:** `tests/Feature/Blocking/BlockCapacityAtomicityTest.php`, the record-state
audit test, and `php storage/app/scratch/lane172-enrollment-day-load.php`, which built the whole
contention picture on a scratch schema and read the integrity questions back from the server.
**Evidence:** `storage/app/scratch/lane172-load-out.txt` and `lane172-harness-out.txt`; the scale
builder `php storage/app/scratch/lane163-build-scale-schema.php` (1,200 blocks with seats filled in
order) which now **refuses** the overfilled fixture shape that once made a 410 ms reading a lie.
**Workflow difference:** none — the Registrar or the officer sets the ceiling, the server holds it.
**Open question, named rather than smoothed:** the room count is taken per `scheduleId`, so a hall
shared by ten sections is never weighed as a hall. That is a school rule, not a default to invent,
and the P register carries it as P04's remainder.

**Q4.1** How should the system maintain accurate section capacity?
**Answer:** by not storing a second truth. A derived count cannot go stale; a running counter can,
and would need every write path — including the ones a fixture and a seeder use — to remember to
maintain it. The declared ceiling lives on the row, the occupancy is asked of the children.

**Q4.2** Which operations should be processed together?
**Answer:** the count, the comparison against the ceiling, the seat writes and the status transition
— all inside one transaction on the locked block row. Outside the lock the count is a guess about a
set that is still moving.

**Q4.3** How can the database prevent invalid capacity information?
**Answer:** `maxStudents` is NOT NULL and typed, the ceiling cannot be silently absent, and the
active-seat key stops the per-student form of over-enrollment at the server. A cross-table inequality
("holders must not exceed the ceiling") is not expressible as a constraint in MySQL, so the durable
prevention is the lock plus an audit rule that re-weighs the whole table, which is what the record
-state audit exists to do.

**Q4.4** How will you test the solution?
**Answer:** three ways, and all three were run. Two real writers against one seat on a scratch clone
(`lane168`, both directions), an audit pass over the delivered and the scale schema, and a load run
that ends with every integrity question read back from the server rather than from the tool's own
counters.

### Problem 5 — Simultaneous Student Updates

**Concept involved:** lost update, optimistic concurrency control, version columns.
**Applicability:** ☑ Applies to our system ☐ Partially applies ☐ N/A
**Design:** the person record carries a version. A writer sends the version it read; the server
compares; a mismatch refuses and writes nothing.
**Implementation:** `students.rowVersion` (NOT NULL, default 1) declared on the model's `$attributes`
as well, so an in-memory object tells the truth after a create. The profile capture in
`Evaluation\EvaluationController` compares before writing and spends exactly one version per
successful save; the refusal names both numbers.
**Test or demonstration:** `tests/Feature/Evaluation/ProfileCaptureTest.php` and
`tests/Feature/Evaluation/RetentionGateTest.php`; the walkthrough fixture in
`tests/Feature/E2E/EnrollmentWalkthroughTest.php` reads the version back rather than assuming it.
**Evidence:** the two cases, and the schema reading in §24's `students` row of the authority document.
**Workflow difference:** the two users are two desk accounts (a Registrar and an evaluator, or two
registrars), not a student editing their own profile — this build has no student login.

**Q5.1** What could happen to the changes?
**Answer:** before this repair, exactly the classic lost update: both read version 1, both write, and
the second save silently erases the first with no error on either screen. Nothing in the row records
that it was overwritten twice.

**Q5.2** How should the system decide whether one change overwrites another or whether a conflict must be resolved?
**Answer:** it does not decide by itself and it does not merge. The version is compared, and a
mismatch is **refused** — the desk is told the record moved and must re-read it. Choosing a winner
silently is the defect, so the honest rule is: nobody's write is dropped without somebody being told.

**Q5.3** How will simultaneous modifications be handled?
**Answer:** optimistically, because a person record may be held open on a screen for minutes and a
row lock is the wrong tool for that. The lock belongs to the short, contended acts (Problem 2 and 10)
where two writers genuinely race for one resource; the version belongs to the long, human one.

**Q5.4** What concurrency problem could occur?
**Answer:** the lost update, first, and with it a second one this project actually met while building
the fix: a schema DEFAULT is not handed back to the model you just created, so a payload built from
an in-memory object posted a null version and the guard refused its own fixture. A guard whose
fixture trips it is not a concurrency test at all, which is why the model declares the same default.
**Remainder, named:** `studentaddresses` and `studentguardians` rows written by the same screen are
still unversioned, so two desks holding one of *those* can still overwrite each other.

### Problem 6 — Enrollment Cancellation

**Concept involved:** multi-row transaction, status machine, referential integrity as a brake.
**Applicability:** ☑ Applies to our system ☐ Partially applies ☐ N/A
**Design:** a cancellation is a status change over a set of rows inside one transaction, not a delete.
**Implementation:** `RegistrarController`'s drop wraps the subject lines going `dropped`, the
enrollment's own transition and the box signature in one transaction;
`App\Services\EnrollmentStateMachine` is the only writer of `enrollmentstatushistory` and validates
the edge before it moves; the money rows (fee sheet, receipts) are left as evidence rather than
erased, because a refund is a different act with its own transaction.
**Test or demonstration:** the delivered walkthrough drops lines and the state machine refuses an
illegal edge, but the guide's specific demonstration (which rows exist after a *refused*
cancellation) is **T06, not executed**: `drop()` is one transaction and no case yet asserts the line
set after a refusal.
**Evidence:** `tests/Feature/Enrollment/EnrollmentReturnTest.php` covers the neighbouring return act
end to end, `php storage/app/scratch/lane43-delete-guards.php` prints which foreign keys would refuse
a delete of any of these rows, and the register keeps T06 open by name.
**Workflow difference:** "a student cancels through an online request" is not this build; a student
asks and the Registrar or the department performs the drop. The database work is identical.

**Q6.1** Which database records need to be modified?
**Answer:** `enrolledsubjects` (each requested line to `dropped`), `enrollments` (its status, when
the whole enrollment is cancelled), `enrollmentstatushistory` (one row per move, written by the
state machine), `workflowsteps` (the boxes that must re-open or be signed), and nothing in
`payments` or `studentassessments` — those keep the money history. The active seat is released by
construction, because the generated `seatKey` goes null the moment the status is `dropped`.

**Q6.2** Should all cancellation operations succeed together?
**Answer:** yes, where all-or-nothing is required: the lines, the transition and the signature are
one transaction, because a half-cancelled enrollment is a record no desk can read.

**Q6.3** What happens if one cancellation operation fails?
**Answer:** every one of them is undone, the load stays as it was, no box is left half-signed, and the
desk is given the reason. The status machine's own refusal (`InvalidStateTransitionException`) is the
failure this project met live while fixing Problem 10, which is why the act re-reads the record
inside the lock before moving it.

**Q6.4** How will database consistency be maintained?
**Answer:** one writer per kind of fact (the state machine for status and history), enums so an
impossible status cannot be stored, 72 foreign keys that refuse a delete outright plus the derived
capacity count, and a record-state audit that re-weighs the states over the whole schema so a
consistency rule that stops holding is reported instead of assumed.

### Problem 7 — Database Deadlock

**Concept involved:** deadlock detection, lock ordering, bounded retry.
**Applicability:** ☑ Applies to our system ☐ Partially applies ☐ N/A
**Design:** make deadlocks rare by construction (one order, short transactions, sorted ids) and make
the remaining ones survivable (detect, retry a bounded number of times, then refuse with words).
**Implementation:** every contended act takes the same order — block then room — and sorts its
requested ids before opening the transaction; `App\Support\RetryOnDeadlock` re-runs a transaction
only when the driver's code is 1213 (deadlock) or 1205 (lock wait timeout), propagates every other
`QueryException` untouched, and after its attempts are spent refuses with "Nothing was saved —
please try again." instead of leaving a desk to guess whether the row moved.
**Test or demonstration:** the deadlock was **observed on the school's engine**, not theorised.
`lane168` met a real `SQLSTATE[40001]` 1213 on HEAD's assign act and none on the locked act;
`lane190` met four unretried 1213 deaths in ten writers and then none.
**Evidence:** `storage/app/scratch/lane168-out.txt`, `storage/app/scratch/lane190-out.txt`, and
`php storage/app/scratch/mutate-lane190-race-probe.php` proving the probe's own guards are read.
**Workflow difference:** none. The two transactions are two desk writers.

**Q7.1** How could this happen in an enrollment system?
**Answer:** two acts that each need the block row and the room row, or the enrollment row and its
children, in opposite orders; or one act locking a set of students in an order that differs from
another act's set. Both of the concrete instances here were measured: the assign act over two
sections sharing resources, and the subject-line proposal over one enrollment and its lines.

**Q7.2** What happens when both transactions are waiting?
**Answer:** InnoDB's detector ends the wait by rolling one transaction back with 1213. Before the
retry helper existed, that exception reached the desk as the generic "conflicts with information
already on file, reload and retry" page from `bootstrap/app.php`, which names no record and asks the
desk to repeat something that can never work — so the failure mode was not a hang, it was an
unhelpful 500.

**Q7.3** How can the system prevent or handle the situation?
**Answer:** prevent by one fixed lock order and sorted, de-duplicated id lists so two callers reach
the same rows in the same sequence; handle by a bounded retry on the two codes that mean "somebody
held it first", by making the retried act safe to re-run (the unique attempt key means a re-run
cannot double a line), and by a refusal with words when the bound is spent.

**Q7.4** What strategies can reduce the possibility of deadlocks?
**Answer:** keep transactions short and take every lock at the top; acquire locks in one global
order; index the columns the lock walks so the set of touched rows is small; do not upgrade a lock
after reading; retry with a bound rather than forever; and give the desk an idempotency key so a
human pressing the button again is not a second act. The suite's limit is stated too: SQLite neither
deadlocks nor reports 1213, so four boundary cases hand the helper the two driver codes rather than
pretending the driver produced them, and the retry count itself is only weighed on a real server.

### Problem 8 — Student Search Performance

**Concept involved:** indexing, selectivity, sargability of the predicate.
**Applicability:** ☑ Applies to our system ☐ Partially applies ☐ N/A
**Design:** index the columns the desk actually searches, and write the predicate so the index can
be used. The second half is the one this build has not yet been allowed to do.
**Implementation:** `idx_students_names (lastName, firstName)` and the unique `schoolIdNumber` in the
`students` table's own migration, `idx_enrollments_term_status (termId, enrollmentStatus)` for the
queue, individual FK indexes on `studentId`, `courseId`, `termId`. The directory's search, however,
is `like '%…%'` over four columns in `StudentController`, which cannot use a left-to-right name
index; 51 leading-wildcard searches exist across 15 controllers.
**Test or demonstration:** `php storage/app/scratch/lane164-measure-scale-queries.php` over the scale
schema `ems_l163_a` (100,000 students, 507,420 subject lines), each path with its own `EXPLAIN`.
**Evidence:** `storage/app/scratch/lane164-out.txt` — the contains search scans roughly 98,800 index
rows, a sargable prefix touches roughly 1,111, an exact `schoolIdNumber` is a `const` seek of one
row, and a fulltext `MATCH` is a seek of one row. Milliseconds are not quoted without their capture:
the same command answered 2.68 ms and then 10.54 ms for the same path, so the plan and the row
estimate are the evidence and a timing is a property of the run that printed it.
**Workflow difference:** none, and the honest status word is **Measured, not repaired.**

**Q8.1** Which fields are commonly used to search for students?
**Answer:** surname and given name (the desk hears a name first), the school ID number, and — as
filters rather than searches — the program, year level and term. The global search box adds the ID
and the name; the directory adds a status and a term narrowing.

**Q8.2** How can the database improve search performance?
**Answer:** by matching the predicate to an index the engine can walk: a prefix (`Mags%`) or a
fulltext index for a contains search, and the unique index for an exact ID. Rewriting `LIKE '%x%'`
into a sargable form is what turns a 98,800-row scan into about 1,111 seeks' worth of work.

**Q8.3** Which fields should be indexed, and why?
**Answer:** the name pair, because every desk starts with a name and surname-first matches how the
list is read; `schoolIdNumber`, because it is unique and therefore a single-row seek; the enrollment
foreign keys and the ` (termId, enrollmentStatus)` composite, because every queue filters a term and
a status together. Uniqueness is chosen where the rule is a rule, not only a speed question.

**Q8.4** How will you determine whether the index improves performance?
**Answer:** `EXPLAIN` before and after at a stated row count, and the rows-estimated field rather
than a stopwatch. That comparison is already made — the plan says the current search cannot use the
index and the prefix plan says it does. What is missing is the change itself, and it waits on the
school: a rewrite decides what a search may *stop* matching, and inventing that rule would fail
§II.B. The demonstration is registered as T09 delivered for the measurement and the rewrite as
P08's open half.

### Problem 9 — Enrollment History Performance

**Concept involved:** index seeks, covering reads, and eliminating the N-plus-one query loop.
**Applicability:** ☑ Applies to our system ☐ Partially applies ☐ N/A
**Design:** the per-student read is one seek per hop; the page's aggregate work is done once per page
and every child set is fetched in batches.
**Implementation:** history retrieval walks `enrollments` on the student foreign key, then its
children — the plan is `ref` on the FK then `eq_ref` on the primary, so "hundreds of thousands of
rows" does not turn into a scan. The measured cost was elsewhere: the Registrar queue re-read a
school-wide passing line and the whole curriculum catalogue for every record. Both are resolved once
per page in `RegistrarController::index()` and threaded into
`App\Support\EnrollmentReadiness::prerequisitesMet()` as optional arguments that default to the
fresh read, so every other caller keeps its old behaviour exactly.
**Test or demonstration:** T09 and T21's query-count half. `php
storage/app/scratch/lane191-attribute-registrar-queries.php` attributes each repeated statement to
the application frame that issued it and counts repeats per listed row: **5.0 per row from five call
sites before, 3.0 per row from three after.** The remaining three — the passed set, the approved credits and the
declared prerequisites — now come from `App\Support\ReadinessBatch`, constructed once from the page's own rows
after `paginate()` and threaded through the checklist as an optional argument that defaults to the fresh read, so
the record page and the approval policy keep answering from the table. `lane191` re-run attributes each of the
three to one batch statement per page and answers **0 repeating statements per row**, then refuses its own verdict
because a five-row queue page no longer holds a repeat to weigh: the refusal is the reading, and the
before-and-after count is pinned by a case instead.
**Evidence:** `storage/app/scratch/lane191-out.txt` and `lane164-out.txt`; pinned by two cases in
`tests/Feature/Registrar/PrerequisiteGateTest.php` — one asserts the preloaded answer equals the
fresh answer **in both directions** (a blocked load and the same load after the prerequisite is
genuinely passed), one asserts the two reads leave the per-row loop, matching each column fully
quoted so both drivers answer the same way.
**Workflow difference:** the Registrar's screen is where the history is read; a student has no login
to read it with.

**Q9.1** Which tables and columns are involved?
**Answer:** `enrollments` (with `studentId`, `termId`, `courseId`, `enrollmentStatus`),
`enrolledsubjects` (`enrollmentId`, `subjectId`, `status`, `attempt_number`),
`enrollmentstatushistory` (`enrollmentId`, `fromStatus`, `toStatus`, `changedAt`),
`studentassessments` and `payments` for money, `workflowsteps` for the desk trail, `grades` and
`creditedsubjects` for standing and equivalencies, and `auditlogs` for who acted.

**Q9.2** Which columns should be indexed?
**Answer:** every child's foreign key back to the enrollment or the student, the composite
`(termId, enrollmentStatus)` for the queue, and the unique natural keys that are also rules. A second
index on `changedAt` inside the history table would only serve a date range the desk never asks for,
so it is not there and its absence is a decision.

**Q9.3** How can the query be optimized?
**Answer:** by removing loops, not by adding hints. Fetch the page's ids, then one query per child
set with `whereIn`; resolve school-wide constants once per request; select the columns a screen
paints instead of the whole row (which is also the privacy rule); and clone the builder *before*
`paginate()` so an aggregate describes the set rather than the page.

**Q9.4** How can performance improvement be measured?
**Answer:** by a count that is attributed, not by a stopwatch. `lane191` gives repeated statements per
listed row and names the five (then three) call sites, so a later batch can show its own number on
the same instrument; `lane164` gives the plan per path. One test in the whole suite asserts a query
count today and no CI step enforces a budget, which is stated rather than glossed.

### Problem 10 — Concurrent Subject Registration

**Concept involved:** serialising writers, the unique key as arbiter, retry.
**Applicability:** ☑ Applies to our system ☐ Partially applies ☐ N/A
**Design:** one writer at a time per enrollment, the load re-read inside the lock, and a key that
makes a double line impossible even after a retry.
**Implementation:** `Evaluation\EvaluationController::proposeSubjects()` is wrapped in
`RetryOnDeadlock::run()` around a `DB::transaction` whose first statement locks the enrollment row,
followed by `refresh()` so the record's real status — not a stale copy — decides whether the
transition runs; the writes are the delete-then-insert of the load, guarded by
`uq_enrollment_subject_attempt`.
**Test or demonstration:** `php storage/app/scratch/lane190-probe-subject-line-race.php --pairs=5`
starts two real child processes released at **one** `--start-at` instant against a scratch clone of
live `ems`, with one enrollment and one identical payload, each calling the act directly.
**Evidence:** `storage/app/scratch/lane190-out.txt` holds three readings: before the lock, 10 writers
gave 6 proposals and **4 unretried 1213 deaths** while every pair settled on exactly the payload's
rows with 0 duplicate groups (the key worked; the act still died); after the lock and retry, 5
writers gave 5 `InvalidStateTransitionException` refusals — the lock had made the stale model
visible; after the re-read, 10 of 10 clean, 0 deadlock, 0 duplicates. Proved by
`php storage/app/scratch/mutate-lane190-race-probe.php`.
**Workflow difference:** "several students, one popular section" is the Registrar's assign act
(Problem 2); this problem's shared object in our build is one student's subject line set, and the
same concurrency tools answer both.

**Q10.1** How will the database process multiple enrollment requests?
**Answer:** as separate transactions, serialised on the row each one needs. Different students
targeting one section serialise on the block row; two writers on one enrollment serialise on the
enrollment row. Nobody reads a count that another writer is still changing.

**Q10.2** How will you prevent incorrect enrollment counts?
**Answer:** count under the lock, write under the same lock, and let a unique key refuse the state
rather than trusting the check — the two together are why the delivered run shows 0 duplicate
(enrollment, subject, attempt) groups even in the pre-fix reading where four writers died.

**Q10.3** What concurrency-control mechanism will you use?
**Answer:** pessimistic row locking (`lockForUpdate()`) as the transaction's first statement, a fixed
lock order, a bounded retry on 1213 and 1205 only, and — for the record's *state* — a re-read after
the lock, because a lock that lets you finally see the truth is useless if the code keeps acting on
the copy it read beforehand.

**Q10.4** How will you test simultaneous enrollment?
**Answer:** with two processes, and a start gate. An HTTP pair is the wrong instrument on this stack
(the built-in server handles one request at a time), and without a shared release instant the second
writer usually begins after the first has committed, which makes a race that did not happen look like
a race that is closed. The probe therefore prints both sides, and its pass condition for the pre-fix
bytes is deliberately *unsafe*: a HEAD leg that produces the clean outcome is reported BAD, because
an instrument that cannot show the defect makes its own green meaningless.

### Problem 11 — Invalid Enrollment Data

**Concept involved:** declarative integrity — primary keys, foreign keys, NOT NULL, domain enums,
uniqueness — with request validation on top.
**Applicability:** ☑ Applies to our system ☐ Partially applies ☐ N/A
**Design:** state the rule where it holds for every writer, and give the desk a readable message from
the layer that can write one.
**Implementation:** 105 foreign keys across 64 tables in live `ems`, whose delete behaviour is
catalogued per table in §23 and weighed by `php storage/app/scratch/lane43-delete-guards.php`; NOT
NULL on the columns a Draft application may not defer (a draft defers address, guardian and
educational background, never the person); an enum for every status word (`enrollments`,
`enrolledsubjects`, `payments`, `admissions.applicantType`, and the rest); the unique keys of
Problem 3; and `exists:`, `rule` and typed validation in the requests that feed them.
**Test or demonstration:** `tests/Feature/Enrollment/DatabaseLevelEnrollmentRulesTest.php`, thirteen
cases that ask each driver what it can carry instead of skipping; the enum vocabulary guard
`tests/Support/EnumColumnOrder.php`; `tests/Feature/Errors/DataConflictTest.php` for the rendered
conflict; `tests/Feature/Console/` for the record-state audit's twenty rules.
**Evidence:** those, plus §23.6.1's index list weighed by `lane44-unique-keys.php`.
**Workflow difference:** invalid data arrives through a desk form here, so `exists:` validation runs
first — but the demonstration is at the table, per §II.B, by inserting a bad row with the guard
bypassed.

**Q11.1** How can the database prevent invalid records?
**Answer:** by owning the invariants that must survive every application bug: a foreign key refuses a
line naming a subject that does not exist, NOT NULL refuses an enrollment naming no student, the enum
refuses a status word the state machine has never heard of, and a unique key refuses the second of
anything that must be one.

**Q11.2** Which constraints should be used?
**Answer:** PRIMARY on each surrogate key, FOREIGN on every relationship (with the delete action
chosen per rule — RESTRICT or NO ACTION where a child must not be silently taken, SET NULL where the
reference is metadata), NOT NULL on required facts, UNIQUE on natural keys, and enum domains for
closed vocabularies. A `CHECK` is the right tool for a range and is **not yet used** — that is this
answer's honest gap.

**Q11.3** Which rules should be enforced by the database?
**Answer:** the structural ones: identity, reference, domain and uniqueness. The academic ones
(prerequisites, load, capacity, schedule conflict) stay in the application because they need a read
under a lock and a sentence a person can act on — but capacity and uniqueness have a database half
too, which Problems 2, 3 and 4 deliver.

**Q11.4** How will you test these constraints?
**Answer:** by writing the bad row on purpose, on the driver that runs the test and again on the
school's engine, and asserting the *name* of the refusal. `DatabaseLevelEnrollmentRulesTest` does
exactly that: on MySQL the index refuses by name and a drop releases the seat; on SQLite the case
asserts the key is absent and that the application predicate holds. Asserting an absence is not the
same as skipping.

### Problem 12 — Prerequisite Checking

**Concept involved:** a self-referencing academic rule, and one definition of it.
**Applicability:** ☑ Applies to our system ☐ Partially applies ☐ N/A
**Design:** the requirement is stored as data, the verdict is computed by one method, and every path
that may seat a student asks that method.
**Implementation:** `curriculumsubjects.prerequisiteSubjectId`, a nullable foreign key to `subjects`
with ON DELETE SET NULL, so the requirement belongs to a curriculum's placement of a subject.
`App\Support\EnrollmentReadiness::prerequisitesMet()` reads the student's completed lines and the
**approved** credit equivalencies (`Creditedsubjects::approved()`, the one scope the prerequisite
gate, the mandatory-block diff and the readiness list all share) against
`Gradescale::passingCeiling()`. The proposal save, the Registrar's approval, the record page and the
blocking reason all call it.
**Test or demonstration:** `tests/Feature/Registrar/PrerequisiteGateTest.php`, plus the crediting
cases in `tests/Feature/Evaluation/` that pin a declined equivalency not satisfying anything.
**Evidence:** that class, `Documentation/DEFENCE-EVIDENCE-ADS.md`, and Problem 9's preload-equals-fresh
assertion, which is what keeps the one definition one.
**Workflow difference:** the guide allows online or registrar handling; here the department and the
Registrar both hit the same gate, and no student can reach it directly.

**Q12.1** Where should prerequisite information be stored?
**Answer:** in the curriculum's subject placement — a column, not a comment and not code. Storing it
per program lets two curricula state two rules, which is what the school wants; a hardcoded map would
put the rule where nobody can audit it.

**Q12.2** How will the system check the student's academic history?
**Answer:** by resolving the student's own curriculum, collecting the subject ids the student has
already satisfied (passed grades within the school's passing line, and approved credit equivalencies),
and refusing the proposal when a requested subject names a prerequisite outside that set — naming the
missing subject in the refusal.

**Q12.3** How should the system prevent an invalid enrollment?
**Answer:** at the act that would create it, inside the same transaction, so a refusal writes nothing;
and by showing the same verdict on the checklist *before* the desk acts, so the promise and the server
never disagree.

**Q12.4** How will the rule be enforced consistently across different workflows?
**Answer:** one method, four readers. The consistency is not left to convention: the preloaded answer
and the fresh answer are asserted **equal in both directions**, because a threaded value that
disagrees with the read it replaced is a second definition of the gate wearing a performance costume.

### Problem 13 — Maximum Academic Load

**Concept involved:** an aggregate rule, validation timing, concurrency over an aggregate.
**Applicability:** ☐ Applies to our system ☑ Partially applies ☐ N/A
**Design:** the arithmetic and the timing exist; the threshold does not, and this build will not
invent it.
**Implementation:** `App\Services\AcademicStandingService` computes each subject's units as
`lectureUnits + labUnits` and sums them, so a term's load is derivable today; `Blocks` and the
curriculum carry the per-subject unit counts. **No maximum-load rule is enforced anywhere in `app/`.**
That was checked rather than recalled: grepping `maxLoad`, `academicLoad`, `unitLimit`, `minUnits`,
`maxUnits`, `minSubjects` and `maxSubjects` through the controllers and the readiness helper finds no
such predicate. The year-level *band* the P register once cited as existing is not in the file its own
citation names, and the register's P13 applicability cell is corrected by this batch rather than
copied forward.
**Test or demonstration:** T14 — **not executed**, because there is no expectation to assert until the
school states the ceiling. The nearest delivered thing is Problem 1's refused-proposal case, which
proves the transaction shape a load rule would sit inside.
**Evidence:** the register row P13 ("Not built, limit value is an open school question"), and the
school decision sheets that ask for it.
**Workflow difference:** the guide's scenario is a student exceeding a load; here the desk proposes the
load, so the rule would fire at the proposal save.

**Q13.1** How will the system calculate the student's total units?
**Answer:** it already does, per subject and summed, in the standing service. The load figure is the
same arithmetic the transcript uses, so a ceiling would compare against a number the school already
trusts.

**Q13.2** When should validation occur?
**Answer:** inside the proposal transaction, after the delete and before the insert, so the set being
validated is the set about to be written — and after the row lock, so a concurrent writer cannot make
the sum a stale one.

**Q13.3** How could concurrent enrollment requests affect this rule?
**Answer:** two acts on one enrollment are already serialised by the enrollment row lock (Problem 10),
so the per-record aggregate cannot be double-counted. Two acts on *different* records for one student
in one term are held apart by `uq_enrollments_active_seat`: a student gets one active seat per term.
A rule that summed across two draft records would need the same lock on the student, which is the
design that follows once the school says what the ceiling counts.

**Q13.4** How will the system prevent the student from exceeding the allowed load?
**Answer:** **it does not yet, and this is the honest answer.** The mechanism is ready — one comparison
inside the proposal transaction, a refusal naming the units and the ceiling, an audit rule alongside
`ems:audit:record-states`, and T14 asserting it — and the missing input is a school figure, which
§II.B forbids inventing. The design is stated here so the concept is demonstrable the day the number
arrives.

### Problem 14 — Faculty Teaching Load

**Concept involved:** an aggregate over assignment rows, referential accuracy, conflict detection.
**Applicability:** ☐ Applies to our system ☑ Partially applies ☐ N/A
**Design:** assignments are stored and their times are weighed; a load *limit* is not, because the
school has not stated one. The guide explicitly invites this limitation to be explained.
**Implementation:** `schedules` carries `blockId`, `subjectId`, `roomId` and `instructorId`, the last a
foreign key to `staffusers.userId`, with an index on `(blockId, subjectId)` and one on
`instructorId`; the hours live in `schedulemeetings` as plain day/time strings. So load is countable
today — rows per instructor per term, or units per instructor per term — and is not currently
computed or capped anywhere in `app/`. What **is** built is the collision half:
`App\Support\TimetableConflicts` asks three questions of one builder (the same officer, the same room,
the same block's own hour) inside one term, half-open so back-to-back is not a collision, and
`ems:audit:record-states` carries the same two boundaries as rules.
**Test or demonstration:** `php storage/app/scratch/mutate-lane55-conflicts.php` drives the conflict
pair (its third leg is deliberately expected green before and after, because a correct expectation is
not a defect), and the audit test pins that a meeting starting exactly as another ends is not flagged
and a collision across two terms is not flagged. T15 is **not executed** for load: no limit exists to
assert against.
**Evidence:** those two files, §23's `schedules` row, and the register's P14 row.
**Workflow difference:** faculty are `staffusers` accounts with the teaching right, not a separate
person table; a load question is therefore an officer-and-section question, which the conflict code
already asks.

**Q14.1** How can the database track faculty teaching assignments?
**Answer:** as it does now — one `schedules` row per section-subject-room-and-instructor, with the
term reached through the block (deliberately: rooms and officers are reused every year, so an
unscoped query reads a retired term's meeting as a conflict for the term being built).

**Q14.2** How can the system calculate teaching load?
**Answer:** an aggregate over `schedules` joined to the block's term, counting sections or summing the
subject's units, grouped by `instructorId`. The query needs nothing the schema does not already hold;
what it lacks is a threshold and the school's decision whether load means sections, contact hours or
units.

**Q14.3** How can conflicting or excessive assignments be detected?
**Answer:** the **conflicting** half is detected and shipped: officer, room and the block's own hour,
inside one term, half-open. The **excessive** half is one comparison away from the same shape — an
aggregate and a ceiling at the act that writes the assignment, which is precisely the mechanism
Problem 2 already proves at a section's seat.

**Q14.4** How can assignment records be kept accurate?
**Answer:** foreign keys refuse an assignment naming a section or an instructor that does not exist,
the term scope comes from the block rather than a duplicated column on the schedule, the record-state
audit re-weighs collisions over the whole schema, and the audit log names who changed a timetable and
when. A load rule would add a fifth guard; nothing today silently contradicts the four that exist.

### Problem 15 — Conflicting Class Schedules

**Concept involved:** interval overlap, term scoping, referential and time comparison.
**Applicability:** ☑ Applies to our system ☐ Partially applies ☐ N/A
**Design:** judge a collision inside one term, over three resources, with half-open intervals.
**Implementation:** `App\Support\TimetableConflicts` — the same officer, the same room, and the same
block's own hour (a group meeting in two rooms at one time is the defect the desk exists to prevent).
The term scope travels `whereHas` through the block because `schedules` carries no term, and the
comparison is `start < otherEnd AND end > otherStart`, so a meeting that starts exactly as another
ends is back-to-back and unflagged. A schedule whose block is missing has no term to scope by, and
the filter is dropped rather than answered "no conflict" — widening is the honest failure.
**Test or demonstration:** T16, **partly**. The room, officer and block-hour rules are covered by
`TimetableConflicts` and its cases, `tests/Feature/Blocking/TimetableGridSpanTest.php` and the two
audit boundaries. The **student-side** overlap across two different blocks is owed: nothing today
compares one student's two sections' meeting times.
**Evidence:** `php storage/app/scratch/mutate-lane55-conflicts.php` (three legs, read), the audit
test, and the register's P15 row.
**Workflow difference:** the collision is weighed when the officer builds the timetable and when the
Registrar assigns a student; the student's own two-section overlap would be weighed at the assign act.

**Q15.1** How will the system detect schedule conflicts?
**Answer:** for the resources the timetable owns, by the three-question comparison above, scoped to
the term; for the student, by the seat rule today (one active enrollment per term) and — as the owed
half — by comparing the meeting intervals of the blocks the student is about to hold.

**Q15.2** Which database records must be compared?
**Answer:** `schedules` (with `blockId`, `subjectId`, `roomId`, `instructorId`), `blocks` and its
`termId` and `yearLevel`, `schedulemeetings` for the day and time strings, `rooms` for capacity, and
`enrollments` plus `enrolledsubjects` for what the student actually holds.

**Q15.3** How will overlapping time intervals be identified?
**Answer:** half-open, so adjacent periods do not collide, on the day-of-week set, inside one term.
The two boundaries are asserted, not assumed: back-to-back is unflagged, and a collision across two
terms is unflagged, because the term is what makes two meetings comparable.

**Q15.4** How will an invalid enrollment be prevented?
**Answer:** at the timetable act the three rules refuse the assignment; at the student act the active
seat key refuses a second enrollment in the same term, so a student cannot accumulate two sections of
one term through two records. The remaining case — two blocks inside one enrollment whose hours
overlap — is the owed half of T16 and is named as owed rather than described as done.

### Problem 16 — Transaction Failure During Enrollment

**Concept involved:** transaction boundaries, ROLLBACK, commit ordering, idempotency.
**Applicability:** ☑ Applies to our system ☐ Partially applies ☐ N/A
**Design:** one act, one transaction; a failure leaves the record as it was; a resubmission is
answered, not repeated.
**Implementation:** as Problem 1, plus the deliberate boundaries: the money act (`record`, `void`,
`refund`) writes its receipt, its balance and its status in one transaction; the print act reserves
its Document No. under `lockForUpdate()` **before** rendering, so a failure cannot consume a number
twice; the notification write dedupes on a unique key so a retried act does not ring twice.
**Test or demonstration:** T17 — **failure demonstration owed.** The mechanism is pinned (T01's
refused-proposal case shows a failure leaving the old load in place, and the state machine's own
refusal is covered), but the guide asks for the pair of prints from one act: one commit, one forced
failure. Neither print exists yet.
**Evidence:** `tests/Feature/E2E/EnrollmentWalkthroughTest.php`, `storage/app/scratch/lane190-out.txt`
(the forced failure was a live one), and the register's P16 row.
**Workflow difference:** none in the database work — the only difference is who triggers the act. The
guide's scenario is a student mid-submission; here the interrupted submission is the Registrar's or the
department's, and the failure lands in the same place because the transaction boundary is the act, not
the user. The one thing the guide's student-initiated version would need and this build does not have is
a student-facing retry screen; the idempotency key of Problem 17 is what makes the desk's retry safe.

**Q16.1** Which operations should belong to the same transaction?
**Answer:** the writes that make one act true: the enrollment row and its workflow form; the subject
lines, the transition and the history row; the box signature and the status it implies; the receipt,
the recomputed balance and the status that follows from the money. Not the visit, and not the
notification — that one is idempotent by key, which is a different guarantee.

**Q16.2** What should happen when one operation fails?
**Answer:** all of them are undone and the desk is told. A rollback that nobody can see is how a
silent partial state is born; here the reason reaches the screen and the attempt reaches `auditlogs`.

**Q16.3** How will partially completed records be prevented where all-or-nothing behavior is required?
**Answer:** by the closure form, which commits when the closure returns and rolls back when it throws,
so a `try/catch` around the act cannot accidentally leave half a commit; by one writer per fact; and
by unique keys that refuse the second copy when a retry re-runs the act.

**Q16.4** How will COMMIT and ROLLBACK be used?
**Answer:** issued by the framework on the transaction's behalf — BEGIN at the first statement, COMMIT
on return, ROLLBACK on throw — with `App\Support\RetryOnDeadlock` deciding whether a rolled-back act
is re-run at all, and a bounded number of attempts after which the act refuses in words. No
application path issues a bare COMMIT by hand, which is the safer spelling: the transaction is the
unit, not the handler.

### Problem 17 — Power Interruption During Enrollment

**Concept involved:** crash semantics of transactions, recovery, idempotency, backup.
**Applicability:** ☑ Applies to our system ☐ Partially applies ☐ N/A
**Design:** make an act atomic and make a resubmission safe, so an interrupted desk either has the
record or has nothing — and repeating the attempt never creates a second one.
**Implementation:** `enrollments.requestKey`, a nullable varchar with `uq_enrollments_request_key`
(portable: it exists on the suite's SQLite driver too, unlike the generated seat column), and
`EnrollmentIssuer::issue()` answering the record that already carries the key instead of issuing
another. The key is in the model's `$fillable` — deliberately stated, because mass assignment drops an
unlisted field with no error and no failing query, and a dedupe whose key never reaches the column is
a dedupe that reports success. The browser mints the key when the act is opened and replaces it after
a success (`resources/js/Components/ui/requestKey.js`), so five students in one visit are five acts
with five keys.
**Test or demonstration:** `tests/Feature/Enrollment/EnrollmentRequestKeyTest.php`, including a case
that the index refuses a second row carrying one key **on this driver**, measured as a real server
refusal.
**Evidence:** that class, the register's P17 row, `Documentation/SECURITY-AND-RECOVERY-ADS.md`.
**Workflow difference:** the guide's user is a student; here the interrupted machine is a desk's, and
the same key answers the same question.

**Q17.1** What problems might occur if the enrollment process is interrupted halfway?
**Answer:** an enrollment with no subject lines, a workflow form signed twice or not at all, a fee
sheet with a total and no charge rows, two records for one act, a receipt dated against nothing, and
a student who believes they are enrolled while the Registrar believes they are not. Every one of those
is a *partial* state, which is why the fix is the transaction boundary rather than a repair job.

**Q17.2** What should happen to enrollment changes that were not yet committed?
**Answer:** nothing visible. They are rolled back by the engine at crash recovery through the undo
log, and the next reader sees the record as it stood before the act. No application code has to
recognise the interruption.

**Q17.3** How will the system prevent a student from being left with a partially saved enrollment?
**Answer:** the act is one transaction (Problem 1), and the *submission* is idempotent: when power
returns and the desk presses the button again, `issue()` finds the enrollment that already carries
that request key and answers it, and the unique index makes a second row for one key impossible even
if two retries race.

**Q17.4** How can the user confirm whether the enrollment was completed once power returns?
**Answer:** three ways that do not require trusting memory. Re-send the same key and read the answer
(it is the record, not a duplicate). Open the Registrar's queue or the student's record page and see
the status with its phase and its checklist. Read `enrollmentstatushistory` and `auditlogs`, which
name each move, its desk and its moment. A slip that was already printed carries its own Document No.,
and the print log row was minted before the paper, so the number on the sheet matches the row.

**Q17.5** Which database mechanisms (transactions, logs, backups, power protection) reduce the damage?
**Answer:** transactions and InnoDB's redo and undo logs (committed work survives, uncommitted work
disappears); the audit and status-history tables, which are the application's own log; scheduled
dumps held outside the repository and a restore that has actually been executed;
`innodb_flush_log_at_trx_commit` read from the server by the drill rather than assumed from a
default. Power protection itself — a UPS and a graceful shutdown — belongs to the school's server
room, and this build states that as an institutional fact rather than claiming a feature it does not
have.

### Problem 18 — Duplicate Student ID

**Concept involved:** uniqueness as a rule, race between two checks, error translation.
**Applicability:** ☑ Applies to our system ☐ Partially applies ☐ N/A
**Design:** the ID is unique because the database says so; the application check exists to give the
ordinary case a readable message.
**Implementation:** `uq_students_schoolid` on `students.schoolIdNumber`, declared in the table's own
migration, beside `uq_staff_username`. The intake path validates
`unique:students,schoolIdNumber`, so the second ordinary attempt gets a field error.
**Test or demonstration:** `tests/Feature/Errors/DataConflictTest.php` pins that a duplicate-key
refusal reaching the application is rendered as a named conflict (the captured message quotes the
key name) rather than the generic page; `php storage/app/scratch/lane154-probe-duplicate-keys.php`
answered 0 duplicate groups on live `ems`, which is the census that made the key safe.
**Evidence:** that class, the migration, §23.6.1.
**Workflow difference:** both staff members are desk accounts, and one of them may be an Admission
officer creating a walk-in record rather than a Registrar.

**Q18.1** Why can a duplicate Student ID cause problems for enrollment records and reports?
**Answer:** because it is how a person is recognised on paper and in an aggregate. Two records sharing
it merge every enrollment, fee sheet, clearance and grade into one student in any report that joins
on the ID, and split the same person across two histories — the totals stop describing anyone.

**Q18.2** Which database constraint should prevent two student records from sharing the same Student ID?
**Answer:** the UNIQUE index on `students.schoolIdNumber`, named
`uq_students_schoolid`.

**Q18.3** What happens if both staff members submit the registration at the same moment?
**Answer:** both pass the application's `unique:` check against a table that then holds neither, and
the server decides: the first commit wins, the second is refused with error 1062. An application-only
rule would have written two records; this is the case that makes Problem 3's "both layers" answer
concrete.

**Q18.4** How should the system inform the second staff member that the Student ID is already in use?
**Answer:** the ordinary path returns a validation error on the field, which keeps the desk on the
record and the form intact. The race path arrives as a duplicate-key exception, which is exactly what
`DataConflictTest` pins: it must be translated into a named conflict that says which value clashed,
not the generic "conflicts with information already on file, reload and retry" page that asks a desk
to repeat something that can never work.

**Q18.5** How will you test that the duplicate registration is rejected?
**Answer:** by writing the duplicate on purpose. A case posts the second registration and asserts the
refusal and the message, and the same census that made the key safe (`lane154`) is re-run whenever a
new unique key is considered, so the preconditions are measured before the constraint, not after.

### Problem 19 — Accidental Deletion

**Concept involved:** referential actions as a brake, audit trails, soft versus hard delete, backup.
**Applicability:** ☐ Applies to our system ☑ Partially applies ☐ N/A
**Design:** refuse what must not be deleted, retire records by status rather than removing them, and
say plainly that no desk-facing restore exists.
**Implementation:** of live `ems`' 105 foreign keys, 72 refuse a delete outright (71 with NO ACTION plus the one
RESTRICT on `shiftingrequests.requestedBy`) and
only four cascade (the RBAC pivots the seeder's own prune relies on). Every desk delete answers those
with a hand-written child list (`blockedDeletionMessage()` and its siblings) that
`php storage/app/scratch/lane43-delete-guards.php` weighs against the server's actual referential
actions. An enrollment is never deleted: it goes to `dropped`, which releases the seat because the
generated `seatKey` becomes null. Every act is recorded in `auditlogs` (actor, action, IP, moment,
with sensitive values redacted).
**Test or demonstration:** T20 — **not executed.** No restore path exists, and the guide's question is
still open with the school. The protection half is covered by the delete-guard tests and the audit
trail; the restoration half has no demonstration because there is nothing to demonstrate beyond a
dump restore.
**Evidence:** `lane43-delete-guards.php`'s own output, the observers' tests in
`tests/Feature/Observers/`, `tools/ems-backup-drill.php` and `Documentation/SECURITY-AND-RECOVERY-ADS.md`.
**Workflow difference and N/A justification:** the scenario is a staff member deleting an enrollment
record. This system does not offer that act, so the *accidental deletion* is prevented by refusing it
and the *recovery* question is N/A in the application while remaining answered at the database: a
dump and an executed restore. Whether a desk-facing undo is required at all is an owner decision,
registered rather than invented.

**Q19.1** What data could be lost or become inconsistent after the deletion?
**Answer:** the person's whole enrollment trail — subject lines, the fee sheet and its receipts,
clearance rows and their approvals, the workflow boxes, and the history that explains a status. The
inconsistency is worse than the loss: reports whose totals move without any record saying so, and a
capacity count that silently drops a holder.

**Q19.2** How can the system prevent accidental or unauthorized deletion?
**Answer:** three brakes. The schema: 72 foreign keys that refuse the delete. The application: each
desk lists its own children and refuses before the query, and a right the role does not hold is
refused by the policy first. And the boundary: a record whose course names no college is not the
desk's, so `App\Support\CollegeScope` stops an account from reaching it at all.

**Q19.3** How can the system record who deleted the data, and when?
**Answer:** `auditlogs` — the acting staff user, the action, the record, the IP address and the moment
(camelCase `createdAt` on the audit tables, snake_case `created_at` on the record tables; the two
namings coexist on purpose). Status moves additionally get `enrollmentstatushistory`, and a return
gets its own `enrollmentreturns` row carrying the six facts of the act.

**Q19.4** How can the deleted record be restored (e.g., soft delete, backup)?
**Answer:** soft: the `dropped` status retires an enrollment without losing anything, and a draft
application defers parts of a record rather than deleting them. Hard: a dump restored into a scratch
schema, executed on 2026-11-01 by `tools/ems-backup-drill.php`, which compared 64 tables row for row
and matched three ordered digests. **There is no one-click undo**, and whether a desk should have one
is an open owner question.

**Q19.5** How will the section's enrolled count remain accurate after a deletion or restoration?
**Answer:** because the count is derived from the holders under a lock (Problem 4), removing or
restoring a row changes the number by construction, and there is no stored counter to forget to
update. `seatKey` going null on a drop releases the seat at the same instant, and the record-state
audit re-weighs holders against ceilings after any bulk change.

### Problem 20 — Reports During Active Enrollment

**Concept involved:** isolation, snapshot reads, MVCC versus locking.
**Applicability:** ☑ Applies to our system ☐ Partially applies ☐ N/A
**Design:** a report takes all of its reads inside one transaction, so InnoDB's default REPEATABLE
READ fixes a single read view for the sheet, and enrollment is never made to wait for a reader.
**Implementation:** the daily collection report was rebuilt so both of its `payments` reads sit in one
transaction; the isolation level itself is deliberately unmodified (no session-level `SET
TRANSACTION`, no global change), which is stated because the guide asks which level is used and the
answer must be the one the server actually applies. Aggregate tiles clone their builders **before**
`paginate()`, so a tile describes the whole filtered set, not the page.
**Test or demonstration:** T21 — **executed for the daily collection report**, whose two reads are now
one snapshot; the Registrar queue and the dashboard tiles are **not** covered by name, and the load
run does not answer them because it drives issues rather than reports.
**Evidence:** `tests/Feature/Accounting/PartPaymentAndRefundTest.php` and the money cases in
`tests/Feature/Assessment/`, plus `storage/app/scratch/lane172-load-out.txt` for the concurrent-write
side.
**Workflow difference:** the guide's reader is a dean; here the report opens at the Accounting desk
and for the offices that hold the right, and no student generates one.

**Q20.1** What incorrect or inconsistent results could the report show if enrollment data is changing while it is read?
**Answer:** a total taken before a payment lands beside a line list taken after it, so the sheet's
parts do not add; a tile that counts a status the rows below it do not have; a report naming a
student who has since been dropped; and a page-two number that describes page one because the
aggregate was cloned after `paginate()` — that last defect was found here in three desks and fixed.

**Q20.2** Which isolation level or read approach (e.g., a consistent snapshot) should be used for reports?
**Answer:** the engine's default, REPEATABLE READ, with every read of the report taken inside **one
transaction**. That is what makes it a snapshot: the read view is established by the first read and
held, so later commits cannot leak into the middle of the sheet.

**Q20.3** Should the report block enrollment transactions, or should enrollment be allowed to continue? Why?
**Answer:** continue. Under MVCC a consistent read takes no row locks, so a report does not stall a
writer and must not. Blocking enrollment so a dean can read a number would put the whole queue behind
a reader on the one day the queue matters, and would trade the school's actual work for a stale
guarantee it can get for free.

**Q20.4** How can the report reflect the data as of one specific point in time?
**Answer:** the read view for the transaction, plus a printed sheet that states its own date and its
Document No. minted **before** the render, so the log row and the paper match. The day boundary is
the school's civil day, not the daemon's: `App\Support\SchoolTime` brackets the day as a half-open
pair of UTC instants, which is what stops a report reading a day eight hours early.

**Q20.5** How will you test generating a report during simultaneous enrollment?
**Answer:** by driving the writes concurrently and reading the report while they land. The load run
(`php storage/app/scratch/lane172-enrollment-day-load.php`) does the write half — 1,000 issues
through ten separate processes — and the report half is pinned as a snapshot property in the
accounting cases. A reading of the two at once is **not yet** delivered, and that is the honest limit
of T21: the mechanism is built and tested per side, not simultaneously on both.

### Problem 21 — Database Recovery

**Concept involved:** crash recovery, redo and undo, backup and restore, durability configuration.
**Applicability:** ☑ Applies to our system ☐ Partially applies ☐ N/A
**Design:** let the engine do what the engine does (uncommitted work vanished, committed work
survives), and prove the part this project controls — that a dump restores and the restored data is
identical.
**Implementation:** `tools/ems-backup-drill.php` takes a fresh `mysqldump`, restores it into a scratch
schema, compares 64 tables row for row, matches three ordered digests, and reads the durability
variables from the server instead of quoting a manual. Dumps live only in
`C:\Users\ADMIN\ems-backups\` and never in the repository — a tracked dump of a live database was
found on a public remote and untracked, and the rule is positional now because nothing in a file's
name says whether its rows are real. `php artisan ems:preflight` checks the installation's own
configuration before a first run.
**Test or demonstration:** **executed 2026-11-01** — that is the demonstration, not a plan for one.
**Evidence:** `Documentation/SECURITY-AND-RECOVERY-ADS.md` carries the drill's reading, and the
register's P21 row credits the same run.
**Workflow difference:** none. Recovery is a server fact, whatever the workflow.

**Q21.1** What happens to transactions that were not completed?
**Answer:** they are rolled back and never seen. At restart InnoDB replays the redo log to get the
data files back to a consistent point and then uses the undo information to discard the work of
transactions that had not committed, so a half-written enrollment does not survive a crash.

**Q21.2** What happens to transactions that were already committed?
**Answer:** they are durable, provided the redo log reached the disk — which is what
`innodb_flush_log_at_trx_commit` controls and what the drill reads off the server rather than
assuming. A committed receipt is in the dump taken after it and in the restored copy.

**Q21.3** How does the database restore reliable data?
**Answer:** crash recovery first (redo apply, undo discard), and a restore from a known dump when the
data itself is lost. The project's contribution is that the second path is *executed and compared*:
restored into a scratch schema, table by table, with digests that either match or fail the run.

**Q21.4** What recovery mechanisms, backups, or logs should be considered?
**Answer:** scheduled dumps with a stated retention, held outside the repository; the binary log for
point-in-time replay (named as the school's option — this project's rehearsal did not enable it, so it
is not claimed as installed); InnoDB's redo and undo logs; the application's own `auditlogs` and
`enrollmentstatushistory` so a restored database can still be reconciled against what desks did; the
preflight's configuration rows; and one discipline this repository paid for in blood — never clone a
database by editing a dump with a stream editor, because `mysqldump` emits its DROP DATABASE as an
executable version comment that survives a naive rewrite, and that is how a source database gets
destroyed.

### Problem 22 — Unauthorized Database Access

**Concept involved:** authentication, authorisation at two layers, least privilege, data minimisation.
**Applicability:** ☑ Applies to our system ☐ Partially applies ☐ N/A
**Design:** the application carries the role rules (rights, policies, the college boundary); the
database carries the structural rules and one service account per application. Sensitive columns are
minimised on the way out, not just hidden on the way in.
**Implementation:** 17 process roles and 96 permissions from `RbacSeeder`, checked by
`tests/Feature/Rbac/PermissionMatrixTest.php`; Spatie `Gate::before` grants SysAdmin everything, which
the documents state rather than hide, and because of it a gate ability must never share a
permission's name (`tests/Feature/Rbac/AbilityNameCollisionTest.php`). Every desk route asks a policy;
`App\Support\CollegeScope` is the single statement of the college boundary and is asked both by the
policy and by the list, so a screen cannot offer work the save refuses. Data minimisation: student
loads name the identity columns a page paints (`tests/Feature/DeskQueue/DeskStudentLoadColumnsTest.php`,
`tests/Support/DeskStudentPayload.php`), `passwordHash` is hidden on the models and — the subtler
defect — a sort that joins a relation must pin its select or it ships the joined table's columns
(`uq`-class G-51). The served upload folder is no longer registered at all.
**Test or demonstration:** the permission matrix (107 cases), `tests/Feature/Files/StoredFileAccessTest.php`
(an applicant's paper and a student's photo, refused for a desk without the right),
`tests/Feature/SecurityHeadersTest.php`, `tests/Feature/Rbac/`, the policy tests per desk.
**Evidence:** those, plus `Documentation/SECURITY-AND-RECOVERY-ADS.md`.
**Workflow difference:** there are no student accounts, so the guide's "student role" is N/A in this
build and is explained below rather than claimed.

**Q22.1** Should every role have access to the same data?
**Answer:** no, and the code makes that measurable: rights are per module and per act, `students.view`
is held by some desks and not by bare `Staff` or `Instructor`, clinical findings require `clinic.view`,
and a college-bound account sees only its own college's records — including the badges on its own
dashboard, which were found disagreeing with the list behind them and fixed to one predicate.

**Q22.2** What operations should each role be allowed to perform?
**Answer:** the ones its register names. Signature acts sit with the approving roles and were moved out
of `OfficeHead` by ruling 7; a department may propose and evaluate but not approve; only the Registrar
adopts an examination result (`exam.verify`, and a confirming right on a screen the account cannot
open is a defect this project guards against); credits are decided by the department that holds
`evaluation.credits.process`; and reading a student outside one's own desk needs the explicit right.

**Q22.3** How can database permissions or application authorization be used?
**Answer:** both are used, and the split is honest. Application authorisation carries every *role* rule
because that is where the workflow, the college boundary and the readable refusal live. The database
carries the structural rules — foreign keys, unique keys, enums, NOT NULL — that must hold even for a
writer with no role at all. Per-role database accounts and column grants are **not** configured: a
Laragon deployment runs one service account, so a database user that is not the application could
still read any column. That is a real limitation and it is stated; `DEPLOYMENT.md` carries the
install-time settings that do exist.

**Q22.4** How can sensitive student information be protected?
**Answer:** by shipping less of it and logging it properly. Lists and print payloads name the columns
their screen paints, so a desk stops receiving birthdates it never reads; credential hashes are hidden
and never reach a joined row; the requirement documents and photos live on a private disk whose HTTP
route no longer exists, with access authorised record by record; audit rows redact sensitive keys;
sessions, CSRF and rate limiting are on, HSTS is emitted only when the app truly sees HTTPS and only
for an address named in `TRUSTED_PROXIES` (the wildcard is refused, because `*` would let any client
forge its scheme and its IP while the login throttle and the audit trail read `$request->ip()`); and
no real student appears in any evidence, because the dataset is synthetic.

### Problem 23 — Heavy Enrollment Traffic

**Concept involved:** bottleneck identification, indexing, lock contention, throughput measurement.
**Applicability:** ☑ Applies to our system ☐ Partially applies ☐ N/A
**Design:** find the contended rows and the hot tables, measure the shape at scale, and read the
integrity questions back from the server rather than from the tool that wrote them.
**Implementation:** the locks and keys of Problems 2, 3, 10 and 17 are the concurrency answer;
`idx_enrollments_term_status`, `idx_students_names`, `idx_schedules_lookup` and the foreign-key
indexes are the read answer; server-side pagination, batched children and once-per-page constants are
the page answer; queue badges come from `App\Support\DeskQueues`, the same population the desk lists
read, so a busy screen cannot show a number its own list contradicts.
**Test or demonstration:** `php storage/app/scratch/lane163-build-scale-schema.php` (100,000 students,
507,420 subject lines, 1,200 blocks) and `php storage/app/scratch/lane172-enrollment-day-load.php` —
1,000 real enrollment acts through ten separate processes against a scratch schema, not through a
thread pool in one PHP process, and not against live `ems`.
**Evidence:** `storage/app/scratch/lane172-load-out.txt` — about 69 acts per second, one contended
seat producing 1 winner and 9 refusals, five drop-versus-issue races ending with no student holding
two active seats and no orphan workflow form, every integrity question re-read from the server after
the run; `storage/app/scratch/lane172-harness-out.txt` for the falsifier's own reading;
`lane164-out.txt` for the plans.
**Workflow difference:** the "many users" are desk accounts and officers, and the load run says so.

**Q23.1** Which database operations are likely to become bottlenecks?
**Answer:** in order of contention: the block row for a popular section, the enrollment row for a
multi-line proposal, the capacity count taken under the lock, the fee-sheet write and its money, the
per-row readiness reads behind the Registrar queue, the directory's leading-wildcard search, and the
inserts that every act makes into `auditlogs`, `enrollmentstatushistory` and `notifications`.

**Q23.2** Which queries should be optimized?
**Answer:** the two the measurements named: the contains search (prefix or fulltext, which changes
what a search may stop matching, so it waits on the school) and the readiness loop (three per-row reads
left to batch, down from five). Third named defect: a desk filter that compares a datetime with
`whereDate`, which is non-sargable; the portable fix is a half-open pair of instants, which the
school-day work already uses elsewhere.

**Q23.3** Which tables may receive the most reads or writes?
**Answer:** reads — `enrollments`, `students`, `enrolledsubjects`, `academicterms`; writes —
`enrollments`, `enrolledsubjects`, `studentassessments`, `payments`, `workflowsteps`,
`auditlogs`, `notifications`, `enrollmentstatushistory`. The scale builder fills exactly that shape,
so the measurement is of the hot tables, not of a toy.

**Q23.4** How can indexing improve performance?
**Answer:** by turning the queue's filter into a range seek (`termId` plus `enrollmentStatus`), a name
into a prefix walk, an ID into a one-row `const` seek, and a child fetch into a foreign-key seek
instead of a scan. It cannot help a predicate the optimizer cannot use, which is the whole of
Problem 8's finding, and it is verified per path with `EXPLAIN` rather than assumed from the index's
existence.

**Q23.5** How can concurrency affect throughput and response time?
**Answer:** pessimistic locking serialises writers on one row, so a single hot seat is one-writer-at-a
time **by design** — the alternative is an overbooked section. The cost shows up as wait and, when two
writers hold what the other needs, as a 1213 the loser pays with a retry. Measured: 1,000 acts across
ten processes still settled at about 69 per second with the contended seat producing exactly one
winner, and the pre-fix pair of probes showed what it looked like *without* the locks and the retry —
two seats in a one-seat section and unretried deadlocks reaching the desk as 500s.

### Problem 24 — Complete Enrollment-Day Challenge

**Concept involved:** the whole system — consistency, concurrency, ACID, constraints, indexing,
recovery, security — under simultaneous load.
**Applicability:** ☑ Applies to our system ☐ Partially applies ☐ N/A
**Design:** one truth per fact (derived counts, one status writer, one scope for each academic rule),
one arbiter per race (a lock, or a unique key), and a measurement for each claim.
**Implementation:** everything in Problems 1 through 22, and specifically the enrollment act
(`EnrollmentIssuer::issue()`: lock, seat check, idempotency key, workflow form, fee sheet) plus the
assign act (block lock, count under lock, room lock second, bounded retry).
**Test or demonstration:** the load run and both two-writer probes, together, with the delivered suite
reading (whole `php artisan test`, no failure and no skip) and CI's three jobs green.
**Evidence:** `storage/app/scratch/lane172-load-out.txt`, `lane172-harness-out.txt`,
`lane190-out.txt`, `lane168-out.txt`, `lane164-out.txt`, `lane191-out.txt`,
`lane189-suite.log`, `l170-suite-junit.xml`, `l171-suite-junit.xml`, and
`tools/ems-backup-drill.php`'s executed restore.
**Workflow difference adapted:** the guide's scenario says requests may come from students; here they
come from desks, and the load run drives the desk act — which is what §II.A asks for ("adapt the
scenario to your system's actual workflow"), and the final defense question asks directly whether
registrar-processed enrollment can still be trusted.

**Q24.1** How will the database maintain data consistency?
**Answer:** by refusing to store the same fact twice. Counts are derived from their rows under a lock;
statuses are written by one state machine that validates each edge; academic verdicts come from one
method four readers call; and a record-state audit with twenty rules re-weighs the whole schema, so a
consistency rule that stops holding is reported instead of assumed.

**Q24.2** How will concurrent transactions be handled?
**Answer:** pessimistically on the short contended acts (row locks in one fixed order, sorted ids, a
bounded retry on 1213 and 1205 only) and optimistically on the long human ones (the version on
`students`), with unique keys as the last line for whatever the checks could not see — including rows
written straight to the table by a fixture or a second session.

**Q24.3** How will transactions and ACID properties be applied?
**Answer:** 38 closure transactions in `app/`, one per act, so atomicity is the framework's guarantee
rather than a convention; consistency through the constraints and the state machine; isolation
through locks for writers and MVCC read views for reports; durability through the engine's redo plus
an executed backup-and-restore drill. §IV's twelve rows carry each of those to its own instrument.

**Q24.4** How will duplicate enrollment and over-enrollment be prevented?
**Answer:** `uq_enrollment_subject_attempt` (a line cannot be written twice for one attempt),
`uq_studentassessments_enrollment` (one fee sheet), `uq_enrollments_active_seat` over the generated
`seatKey` (one active seat per student per term, released by a drop with no trigger),
`uq_enrollments_request_key` (one act per submission), the holder count against `maxStudents` taken
under the block lock, and `EnrollmentIssuer::seatHolder()` for the readable reason. The load run
ended with no student holding two active seats.

**Q24.5** How will deadlocks be prevented or handled?
**Answer:** prevented by one lock order, sorted id lists and short transactions; handled by detection
and a bounded retry that re-runs only the two conditions that mean "somebody held it first", then
refuses in words. Both were observed on MySQL 8 rather than argued: a real 1213 in the pre-fix assign
pair, four unretried 1213 deaths in ten proposal writers, and zero after the lock, the retry and the
re-read landed.

**Q24.6** Which operations should be protected by transactions?
**Answer:** the issue (enrollment, form, boxes), the proposal (delete, insert, transition, history),
the approve and the drop (lines, transition, signature), the assign (count, seats, room), the money
(receipt, balance, status, history), the print (reserved number, row, log), and the return (all six
facts plus the boxes that re-open). A notification is *not* in that list because it is made safe by a
dedupe key instead, and the pair of mechanisms is deliberate.

**Q24.7** Which fields should have indexes?
**Answer:** `students (lastName, firstName)` and unique `schoolIdNumber`; `enrollments`
`(studentId)`, `(courseId)`, `(termId)` and the composite `(termId, enrollmentStatus)`; the child keys
on `enrolledsubjects`, `enrollmentstatushistory`, `studentassessments`, `payments`, `workflowsteps`
and `grades`; `schedules (blockId, subjectId)` and `instructorId`; and the natural keys that are also
rules — the attempt triple, the seat triple, the request key, the OR number, the clearance-and-period
pair, the notification dedupe key.

**Q24.8** How will performance be maintained as the database grows?
**Answer:** by keeping reads as seeks (verified per path on a 100,000-student schema), by removing
loops rather than adding hints (5.0 repeated statements per row became 3.0, with three reads left
registered once per page), by paginating and aggregating on the server, by naming the columns each screen
actually paints, and by treating a timing as a property of the run that printed it — which is why the
claim is a plan and a row estimate instead of a millisecond.

**Q24.9** How will you prove that the database handles these situations correctly?
**Answer:** with instruments that can fail, and a reading from each. Two-process race probes whose
pass condition for the pre-fix bytes is deliberately unsafe; a 1,000-act load run across ten separate
processes with its integrity questions re-read from the server afterwards; a scale builder that
refuses the overfilled fixture shape it once produced; a restore drill that compares 64 tables and
three digests; a record-state audit with twenty rules; a delivered suite with no skip; and a
mutation harness for every one of those claims, so "the guard cannot fail" is never mistaken for
"the guard passed". What is *not* claimed is listed in the next section.

---

## IV. Required Database Concepts — status of each, in the guide's order

The guide's twelve rows, each marked with only its own vocabulary. A concept is not Implemented
because a document discusses it; the word is credited to the instrument or delivered case that earns
it. Full detail per row lives in `Documentation/DATABASE-CONCEPT-COMPLIANCE-ADS.md`.

**Row 1 — Transactions.** ☑ Implemented ☐ Demonstrated in test ☐ N/A
38 closure transactions; the enrollment act and the approve/drop acts are pinned by T01's two cases.
The forced-failure print (T02) is still owed.

**Row 2 — COMMIT / ROLLBACK.** ☑ Implemented ☐ Demonstrated in test ☐ N/A
The closure form commits on return and rolls back on throw, so no handler can leak a partial commit;
the refusal path leaves the old load in place (T01's second case). One commit print and one failure
print from the same act do not exist yet.

**Row 3 — ACID Properties.** ☐ Implemented ☑ Explained ☑ Demonstrated in test ☐ N/A
Atomicity as rows 1 and 2; Consistency by 105 foreign keys, enums, unique keys and twenty audit rules;
Isolation demonstrated with `FOR UPDATE` at three sites and observed deadlocks; Durability by the
executed restore, which is the guide's "must have a backup way or method".

**Row 4 — Concurrency Control.** ☑ Implemented ☑ Demonstrated in test ☐ N/A
Locks on the block, the room and the enrollment; a version on the person; dedupe on the notice; the
seat, attempt, sheet and request keys. Two probes met the races on the school's engine. Remainder
named: the address and guardian rows a screen writes are unversioned.

**Row 5 — Locking.** ☑ Implemented ☐ Demonstrated in test ☐ N/A
`lockForUpdate()` at three sites — the print issuance reserve, the block row as the assign
transaction's first act, the room row second — always in that order, with sorted ids.
The locks cover the assign and proposal paths; no other enrollment write takes a row lock.

**Row 6 — Deadlock Handling.** ☑ Implemented ☑ Demonstrated in test ☐ N/A
`App\Support\RetryOnDeadlock`, 1213 and 1205 only, every other exception propagated, a refusal in
words when the bound is spent. A genuine `SQLSTATE[40001]` was observed pre-fix and absent after.
SQLite never deadlocks, so four boundary cases hand the helper the driver codes instead.

**Row 7 — Database Constraints.** ☑ Implemented ☐ Demonstrated in test ☐ N/A
Primary and foreign keys with per-table delete behaviour catalogued and weighed, NOT NULL, enums, and the thirteen
unique constraints §23.6.1 lists, which `php storage/app/scratch/lane44-unique-keys.php` weighs against the server.
Gap named in the guide's own words: no `CHECK` expresses a
range rule (capacity non-negative, units non-negative), which MySQL 8 supports.

**Row 8 — Indexing.** ☑ Implemented ☑ Query plan/performance test ☐ N/A
Named composites and the name index, each verified with `EXPLAIN` on a 100,000-student schema. The
defect this row owns is stated rather than hidden: the directory's contains search cannot use the
index that exists, ~98,800 rows touched where a prefix touches ~1,111.

**Row 9 — Query Optimization.** ☐ Implemented ☑ Query plan/performance test ☐ N/A
Four paths weighed with their plans; the readiness loop measured at 5.0 repeated statements per row,
now 0 repeating per row, the three remaining reads answered once per page by `App\Support\ReadinessBatch`. The two
rewrites that remain are school
decisions, not defaults to invent.

**Row 10 — Data Integrity.** ☑ Implemented ☐ Demonstrated in test ☐ N/A
Referential and domain integrity in the schema, derived counts, one status writer, and the record-state
audit whose twentieth rule weighs holders against declared ceilings. The fixture habit the seat key
caught — eight test files holding two active seats for one student and term — was censused, repaired
and the key returned the same day.

**Row 11 — Database Security.** ☑ Implemented ☐ Demonstrated in test ☐ N/A
Rights, policies, the college boundary, column minimisation, a private disk with no served route,
redacted audit rows, headers gated on a named proxy. Limitation stated: one service account at the
database, so the role rules are enforced by the application, not by MySQL grants.

**Row 12 — Recovery.** ☑ Backup/restore or recovery evidence ☑ Explained ☐ N/A
`tools/ems-backup-drill.php` executed on 2026-11-01: fresh dump, restore into a scratch schema, 64
tables compared row for row, three ordered digests matching, durability variables read from the
server. Dumps live outside the repository, and the reason is a recorded finding, not a preference.

**N/A explanation / alternative demonstration plan:** no row is N/A. Five rows carry an owed
*demonstration print* rather than a missing mechanism — T02 (a forced rollback), T06 (a refused
cancellation), T17 (the commit-and-failure pair), T20 (a restore path the school has not asked for),
and T21's simultaneous report-during-enrollment reading — and two wait on a school figure, T14's load
ceiling and T15's faculty limit, with their designs stated above so the demonstration can be executed
the day the number arrives. Rows 8 and 9 are marked with the plan/performance vocabulary because the
instrument ran; the rewrite is the open half.

---

## V. Final Presentation Requirements — each item answered

**1. Database Design.**
- *ERD:* `Documentation/DATABASE-DESIGN-ADS.md`, generated from the live schema by
  `php storage/app/scratch/lane162-generate-erd.php` — the generator splices between its own markers
  and **refuses** to overwrite a file that holds prose but no markers, so the hand-written readings
  outside the diagram survive a re-take.
- *Tables and relationships:* 64 base tables in live `ems`, the domain-and-framework partition derived from the
  migrations and the server by `php storage/app/scratch/lane60-ratio-claims.php --run` rather than recalled (that
  instrument's own verdict today: 54 figures matching, 0 stale), 105 foreign keys, all catalogued per
  table in §23 of the authority document and weighed by `lane43-delete-guards.php` and
  `lane60-ratio-claims.php`.
- *Primary keys and foreign keys:* camelCase surrogate keys on every domain table (`studentId`,
  `enrollmentId`, and the rest), each foreign key carrying an explicit delete action; §23.6.1 lists
  every unique index and `lane44-unique-keys.php` refuses the run when the list and the server
  disagree.

**2. Transactions.**
- *At least one meaningful transaction (Can also use Try and Catch method):*
  `EnrollmentIssuer::issue()` — the enrollment row and its
  workflow form and boxes, with the seat check inside the same transaction. Also the proposal act
  (lock, delete, insert, transition) and the money act (receipt, balance, status, history).
- *COMMIT and ROLLBACK:* Laravel's closure form, commits on return and rolls back on throw; the retry
  helper decides whether a rolled-back act runs again.
- *A failure scenario and the observed result:* observed twice with two real writers —
  `storage/app/scratch/lane168-out.txt` (an overbooked one-seat section and a genuine 1213 under the
  old bytes, three refusals and never more than one seat under the new) and `lane190-out.txt` (four
  unretried deadlock deaths, then five stale-model refusals, then ten clean writes). The single-
  connection failure print (T02, T17) is still owed.

**3. ACID Properties.**
- *Atomicity:* Problem 1, with T01's two cases as the demonstration and a mutation harness that makes
  each case fail.
- *Consistency:* constraints, the state machine as sole status writer, and the twenty-rule record-state
  audit; the enums are the school's vocabulary, not a free-text field.
- *Isolation (like the use of FOR and UPDATE):* `lockForUpdate()` at three sites in one fixed order,
  the count re-taken under the lock, and MVCC read views for reports; the races were *seen* by two
  processes, which is what makes the claim a measurement.
- *Durability (must have a backup way or method):* `mysqldump` taken outside the repository and
  restored by `tools/ems-backup-drill.php`, compared row for row with three digests matching, plus the
  flush variables read from the server.

**4. Concurrency.**
- *A simultaneous enrollment scenario:* two writers on one seat (the assign act) and two on one
  student's subject line set (the proposal act), each released at one shared instant.
- *Chosen concurrency-control strategy:* pessimistic row locks with a fixed acquisition order and a
  bounded retry for the short contended acts, optimistic versioning for the long human ones, and
  unique keys as the arbiter for whatever the application checks could not see.
- *Testing evidence:* `lane168-out.txt`, `lane190-out.txt`, `lane172-load-out.txt`, and the tests
  `tests/Feature/Blocking/BlockCapacityAtomicityTest.php`,
  `tests/Feature/Enrollment/DatabaseLevelEnrollmentRulesTest.php`.

**5. Deadlock.**
- *A possible deadlock scenario:* two assign acts taking the block and the room in opposite orders,
  and two proposal acts racing the enrollment row against its child lines; the first produced a real
  `SQLSTATE[40001]` 1213 on the pre-fix bytes.
- *Prevention or handling strategy; a controlled demonstration or explanation if the application does
  not naturally produce one:* prevented by one lock order, sorted ids and short transactions, handled
  by `RetryOnDeadlock` on 1213 and 1205 only and then a refusal in words. The controlled
  demonstration is delivered, so the guide's fallback ("an explanation if the application does not
  naturally produce one") is not needed — this build does produce one.

**6. Indexing.**
- *Indexes implemented:* those named in Q24.7, plus the foreign-key indexes and the unique keys that
  are also rules; 237 index rows and 15 unique constraints on the 2026-11-01 schema, as printed by
  `php storage/app/scratch/lane154-probe-duplicate-keys.php`.
- *Reason for selecting indexed columns:* the columns the desks filter and join on, and the natural
  key of each rule the school states — with the reasoning recorded per index in
  `Documentation/DATABASE-DESIGN-ADS.md`.
- *Query plan or performance evidence where feasible:* `EXPLAIN` per path on the 100,000-student
  schema in `storage/app/scratch/lane164-out.txt`, read as plans and row estimates because timings on
  this box move between runs of the same command.

**7. Testing.** Each sub-item of the guide's list, labelled with its own words:
- *Test scenario:* proposed load refused, the same subject line handed twice, two real writers on one
  seat and on one load, a fee sheet for an enrollment that already has one, a second active seat for
  one student in one term, a duplicate Student ID, a refused cancellation.
- *Expected result:* the act either writes everything or nothing, and every duplicate or over-limit
  state is refused **by name** — the index, the capacity figure, or the version numbers that
  clashed — leaving no orphan subject line, no orphan workflow form and no second record.
- *Actual result:* as captured, not as intended: 3 assigns and 3 capacity refusals against HEAD's 5
  assigns into a one-seat section; 10 of 10 clean proposals against 4 unretried 1213 deaths; one
  winner and nine refusals on the contended seat of the 1,000-act run; 0 duplicate groups before and
  after each key.
- *Screenshot, SQL output, logs, or other evidence:* `storage/app/scratch/lane168-out.txt`,
  `lane190-out.txt`, `lane172-load-out.txt`, `lane164-out.txt`, `lane191-out.txt`,
  `lane189-suite.log`, `l170-suite-junit.xml`, `l171-suite-junit.xml`, and the twenty-two cases of
  `Documentation/TESTING-AND-VERIFICATION-ADS.md`, each carrying the ten fields the ADS prompt names.
The case-shape census is `php storage/app/scratch/lane189-count-ads-fields.php`, which reads every
two-column table in that document and reports how many rows are short; on its first run it found
three cases a closed task had credited with fields they did not have, and the rows were then written
from the artefacts each case already cites. Delivered suite readings are quoted beside the run that
printed them, never as a property of the code, and every mechanism claim above has a falsifier that
was made to fail first — `storage/app/scratch/mutate-g-t01-subject-commit.php`,
`mutate-lane190-race-probe.php`, `mutate-lane187-provenance.php` and their siblings — because a guard
that cannot fail has checked nothing.

**8. Workflow and Applicability.** Each sub-item of the guide's list, labelled with its own words:
- *Explain whether enrollment is student-initiated, registrar-assisted, walk-in, or a combination:*
  registrar-assisted and department-assisted, with a walk-in applicant entering through the Admission
  desk. No student login exists in this build; each enrollment still requires a valid student record,
  and the walkthrough in `tests/Feature/E2E/EnrollmentWalkthroughTest.php` drives that chain.
- *Identify problems marked N/A or partially applicable and justify each decision:* **P13** partial
  (the load arithmetic exists, the ceiling is a school figure and no rule is enforced); **P14**
  partial (assignments and collisions exist, no load limit is defined); **P15** partial for the
  student-side overlap across two blocks (T16); **P19** partial (protection and auditing delivered,
  restoration N/A in the application because no desk delete is offered and no undo exists — answered
  instead by the executed dump-and-restore); **P08** measured and deliberately not repaired (the
  rewrite changes what a search may stop matching); **P09** partly (two of five per-row reads
  removed, all five answered once per page); **P20** partial to the daily collection report, with the queue and dashboard
  reads uncovered by name; **P22** partial at the database layer, one service account. Everything
  else is marked Applies above, and the same verdicts are the applicability column of the P register
  in `Documentation/REQUIREMENTS-TRACEABILITY-ADS.md` — one answer, not two.
- *Describe an equivalent test or design where needed:* the comparison inside the proposal
  transaction plus a record-state audit rule for P13; an aggregate over `schedules` with a stated
  ceiling, in the shape P02 already proves at a seat, for P14; a meeting-interval comparison of the
  blocks one student holds for P15; the executed restore and an owner decision for P19; a prefix or
  fulltext rewrite for P08; a batched `whereIn` by enrollment id for P09; the same one-transaction
  read view extended to the Registrar queue and the dashboard for P20; and, for P22, per-role
  database accounts named as the deployment change that would move the rules below the application.

---

## VI. Final Defense Question

*If 1,000 enrollment requests are submitted at the same time, how can you prove that your database
will keep enrollment records accurate, consistent, secure and reliable — even if enrollment is
processed by registrar staff rather than directly by students?*

Because accuracy at that scale is not a promise about speed; it is a set of arbiters, each of which
was made to fail on purpose before it was credited. **Accuracy:** a student gets one active seat per
term because `uq_enrollments_active_seat` over a generated column is a rule the engine enforces for
every writer, desk or otherwise, and a section never exceeds its ceiling because the holder count is
taken inside the lock on the block row before any seat is written. **Consistency:** each act is one
closure transaction, so an interrupted act leaves nothing half-written; one state machine writes every
status and its history row, so the trail and the status cannot disagree; and the record-state audit's
twenty rules re-weigh the whole schema after a run instead of trusting the tool that wrote it.
**Security:** every one of those acts passes a policy that asks a named right and, for a bounded
account, the college boundary — the same predicate that filters the desk's list, so a screen cannot
offer work the save refuses, and the payload carries only the columns the screen paints. **Reliability:**
the idempotency key means a stuck desk pressing the button ten times performs one act, the retry
helper means a deadlock costs a bounded wait rather than a 500, and the executed restore means a lost
server costs a known dump.

The proof is a reading, not an argument: `storage/app/scratch/lane172-load-out.txt` holds 1,000 real
acts issued through ten separate processes against a scratch schema built to 100,000 students and
507,420 subject lines, at about 69 acts per second, with one deliberately contended seat producing
exactly one winner and nine refusals, five drop-versus-issue races, and — read back from the server
afterwards — no student holding two active seats, no orphan workflow form and no capacity violation.
The counter-proof is the pair of pre-fix readings: the same instruments on the bytes before the locks
left a one-seat section holding two students and let four writers die on unretried 1213s that reached
the desk as a generic error page. Registrar-processed is the point, not an excuse: a desk act that
must name a valid student record, must take a lock that does not care who typed it, and must satisfy a
key that refuses a duplicate whoever wrote it is *more* auditable than a self-service click, because
every one of those 1,000 acts carries the staff user, the office, the moment and the IP in
`auditlogs`. What the reading does not prove is stated with it: throughput on a single hot seat is
one-writer-at-a-time by design, the P23 measurement is of a scratch schema and not of the school's
server, and three per-row readiness reads remain unbatched.

*Be prepared to support your answer with your database design, SQL or configuration, test cases, and
evidence.* That is exactly what the four paragraphs above are made of, and the four kinds are named so
the answer can be checked rather than admired: the **design** is §23's tables and foreign keys with the
generated `seatKey` column and the thirteen unique constraints §23.6.1 lists; the **configuration** is the
engine's own REPEATABLE READ default left unmodified, `innodb_flush_log_at_trx_commit` read from the
server by the drill, and `TRUSTED_PROXIES` naming one real proxy instead of a wildcard; the **test
cases** are `BlockCapacityAtomicityTest`, `DatabaseLevelEnrollmentRulesTest`,
`EnrollmentRequestKeyTest`, `RetakeAttemptTest`'s T01 pair and the delivered whole-suite reading; the
**evidence** is the tracked captures — `lane168-out.txt`, `lane190-out.txt`, `lane172-load-out.txt`,
`lane164-out.txt`, `lane191-out.txt` — each in the repository precisely so a reader with a clone can
open the file this answer points at rather than take the figure on trust.

---

## VII. Pair and Presentation Details

- **Student 1:** Kirby A. Geagonia
- **Student 2:** Fairah C. Alang
- **Instructor:** left for the cover of the submitted copy; the guide's own file names its authors,
  not the instructor, and this build does not invent a name it has not been given.
- **Date of Presentation:** 2026-11-04, the date printed on the guide's title block.
- **Programme and section:** BSIT-BA 3, Block 13, Advanced Database Systems final project.

---

## What this file does not claim

Stated because §II.B's first rule is the one that is easiest to break under pressure:

1. **P13 and P14 have no enforced ceiling.** The arithmetic and the concurrency exist; the numbers are
   school questions and no default was invented for them.
2. **P08 is measured, not repaired.** The index exists; the query still ignores it. The rewrite changes
   what a search may stop matching.
3. **P09's batching does not remove the ceiling question.** All five repeated reads are answered once per page now,
   but no CI step enforces a query budget and only one desk page's counts are asserted by name; a later batch that
   adds a per-row read to another desk would not be caught.
4. **P15's student-side overlap across two blocks is not refused** — only the second active seat in
   one term is.
5. **P19 has no desk-facing restore path.** Prevention and auditing are delivered; restoration is an
   executed dump-and-restore, and whether a desk should hold an undo is an owner decision.
6. **P20's snapshot guarantee is delivered for the daily collection report only.**
7. **P22's role rules live in the application.** One database service account holds the data; per-role
   MySQL grants and column privileges are not configured.
8. **Five demonstrations are owed prints, not missing mechanisms:** T02, T06, T17, T20, and T21's
   both-sides-at-once reading.
9. **Two claims this file corrects as it was written:** the P register's P13 cell said a year-level
   band rule exists, and the cited lines do not hold such a rule; and T01's row had been credited to a
   test class that asserted nothing of the kind. Both were read in the file rather than copied, and
   both are corrected in the same batch as this document.
