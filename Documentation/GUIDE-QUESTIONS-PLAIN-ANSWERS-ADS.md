# ADS guide questions, answered in plain words

The numbering follows the guide exactly: `Q6.2` is Question 2 under Problem 6, in the guide's own "Guide Questions"
list. Each answer gives the verdict first, in ordinary language, and any technical word it still needs is explained on
the `Words:` line under it. Nothing here is a new claim: every answer is the short form of the longer one in
`GUIDE-PROBLEM-ANSWERS-ADS.md`, which carries the file names, the measurements and the evidence. The longer parts
(concept, design, implementation, evidence, applicability, workflow) are separated into their own document,
`ADS-Guide-Concepts-Design-and-Implementation.docx`, so this one stays a question-and-answer sheet.

Words used again and below: **transaction** = a group of database writes that all save together or none do.
**Commit** = save the group. **Rollback** = undo the group. **Lock** = hold a row so nobody else can change it while you
read and write it. **Index** = a sorted lookup structure that lets the database jump to rows instead of reading all of
them. **Unique key** = a rule the database enforces that no two rows may hold the same combination of values.
**Foreign key** = a rule that a value pointing at another table must name a row that exists. **Snapshot** = one fixed
view of the data for the length of a read, so writes by others do not change the numbers under your feet.

## Problem 1 — Incomplete Enrollment Transaction

**Q1.1** What should happen to the other successfully processed subjects?
Plain answer: they are undone too. A subject load is saved as one unit, so there is never a half-saved list on the record.
Words: undone = rolled back, the database puts the record back the way it was.

**Q1.2** How will you ensure the enrollment is completed as one operation when required?
Plain answer: every write the act needs goes inside one transaction, and the row is locked before the first write so a
second desk cannot slip in halfway.
Words: locked = held so only one writer works on it at a time.

**Q1.3** How will the system handle the failure?
Plain answer: the transaction is rolled back, and the desk is shown a plain reason instead of an error page. The attempt is
still recorded in the audit log.

**Q1.4** Which transaction operations are needed?
Plain answer: BEGIN, the writes, then COMMIT if all of it worked or ROLLBACK if anything failed. On top of that: a limited
retry for a clash with another writer, and a request key so a pressed-twice button is answered, not repeated.
Words: request key (idempotency key) = a label on one submission, so sending the same one again returns the first result.

## Problem 2 — Last Available Slot

**Q2.1** How will the system determine which request gets the available slot?
Plain answer: the database does, not the screen. The first writer to hold the section's lock counts, seats and saves; the
second waits, then recounts against a full section and is refused.

**Q2.2** How will you prevent the section from exceeding its capacity?
Plain answer: the count is taken while the section is locked and checked against the declared ceiling before anybody is
seated, so only one writer is inside that window at a time.

**Q2.3** How will simultaneous transactions be handled?
Plain answer: one waits, then re-reads. If both hold something the other needs, the database ends one clash and the system
re-runs that one a limited number of times.
Words: clash = deadlock, two waits that cannot both finish.

**Q2.4** What database mechanism can be used?
Plain answer: a row lock taken first (`SELECT ... FOR UPDATE`), one fixed lock order everywhere, short transactions, and
unique keys as the backstop.

## Problem 3 — Duplicate Enrollment

**Q3.1** How will you prevent the same student from being enrolled in the same section more than once?
Plain answer: two database rules: no second line for the same enrollment, subject and attempt, and no second active
enrollment for the same student in the same term.

**Q3.2** Should the application, database, or both enforce the rule?
Plain answer: both, on purpose. The database is the only one that sees every writer, including a raw insert. The
application is the only one that can explain which record already holds the seat.

**Q3.3** What happens if both requests arrive simultaneously?
Plain answer: both may pass the screen's check, then one commit wins and the other is refused by the database. The refusal
is turned into a named conflict, not a "reload and retry" page.

**Q3.4** Which database constraints can be used?
Plain answer: unique indexes on the combination the rule is about, including a generated column for a rule that only
applies while a record is active, plus the foreign keys underneath.

## Problem 4 — Incorrect Section Capacity

**Q4.1** How should the system maintain accurate section capacity?
Plain answer: by not storing a second number. The count of holders is computed from the rows when it is needed, so it
cannot go stale.
Words: derived = computed from the rows, not saved beside them.

**Q4.2** Which operations should be processed together?
Plain answer: the count, the comparison, the seating writes and the status change — all in one transaction on the locked
section row.

**Q4.3** How can the database prevent invalid capacity information?
Plain answer: the ceiling is a required, typed column; the seat rule stops a student holding two active enrollments in one
term; the audit re-checks holders against ceilings. A cross-table rule like "holders may not exceed the ceiling" cannot
be a database constraint, so it is enforced at the act and checked afterwards.

**Q4.4** How will you test the solution?
Plain answer: three ways, all actually run: two real processes fighting for one seat, an audit sweep over the data, and a
load run whose last step re-asks every integrity question.

## Problem 5 — Simultaneous Student Updates

**Q5.1** What could happen to the changes?
Plain answer: the classic overwrite: both readers start from the same copy and the second save silently erases the first,
with no error on either screen.

**Q5.2** How should the system decide whether one change overwrites another or whether a conflict must be resolved?
Plain answer: it does not choose a winner. The saved version is compared; if it moved, the second save is refused and the
desk must re-read.

**Q5.3** How will simultaneous modifications be handled?
Plain answer: optimistically for a person's record, because a form can sit open for minutes. Locks are kept for the short
fights, like seating.
Words: optimistic = let both people work, then check nothing changed at save time.

**Q5.4** What concurrency problem could occur?
Plain answer: the lost update above. Building the fix found a second one: a database default is not handed back to the
object the code just created, so a save can post an empty version and look like a missing field.

## Problem 6 — Enrollment Cancellation

**Q6.1** Which database records need to be modified?
Plain answer: the subject lines, the enrollment's own status, one history row per move, and the workflow boxes that must
reopen.

**Q6.2** Should all cancellation operations succeed together?
Plain answer: yes where all-or-nothing is required. A half-cancelled record is one no desk can read correctly.

**Q6.3** What happens if one cancellation operation fails?
Plain answer: all of them are undone, nothing is left half-signed, and the desk is given the reason.

**Q6.4** How will database consistency be maintained?
Plain answer: one writer per kind of fact, enums so an impossible status cannot be stored, foreign keys that refuse deletes,
capacity counted from the rows, and an audit that re-weighs record states on demand.

## Problem 7 — Database Deadlock

**Q7.1** How could this happen in an enrollment system?
Plain answer: two acts that need the same two rows in opposite orders, or one act locking a list of students in a different
order than another act's list.

**Q7.2** What happens when both transactions are waiting?
Plain answer: the database notices and cancels one of them. Before the retry helper, that cancellation reached the desk as a
generic failure page.
Words: the database error for this is 1213.

**Q7.3** How can the system prevent or handle the situation?
Plain answer: prevent it with one lock order and sorted id lists; handle it by re-running the act a few times, and refusing
in plain words when the attempts run out.

**Q7.4** What strategies can reduce the possibility of deadlocks?
Plain answer: short transactions, locks taken at the top, one global order, indexes on the columns the lock walks, no
lock upgrades, and a bounded retry.

## Problem 8 — Student Search Performance

**Q8.1** Which fields are commonly used to search for students?
Plain answer: surname and given name, the school ID number, and filters for program, year level and term.

**Q8.2** How can the database improve search performance?
Plain answer: make the search match an index it can walk: a prefix like `Mags%`, a full-text index for a word inside the
name, or an exact seek on the ID.
Words: sargable = written in a form the database can answer with an index.

**Q8.3** Which fields should be indexed, and why?
Plain answer: the name pair because every desk starts with a name; the school ID because it is unique; the enrollment
foreign keys; and the term-plus-status pair the queues filter on.

**Q8.4** How will you determine whether the index improves performance?
Plain answer: compare the query plan before and after at a stated row size, and read the row estimate. A stopwatch number
moves between runs; the plan does not.

## Problem 9 — Enrollment History Performance

**Q9.1** Which tables and columns are involved?
Plain answer: `enrollments` (student, term, course, status), `enrolledsubjects`, `enrollmentstatushistory`, plus the
students and grades the page reads.

**Q9.2** Which columns should be indexed?
Plain answer: every child's foreign key back to the enrollment or the student, and the `(termId, enrollmentStatus)` pair
the queue filters on.

**Q9.3** How can the query be optimized?
Plain answer: take the loops out. Fetch the page's ids, then one query for all the children; read school-wide constants
once; select only the columns the screen paints.

**Q9.4** How can performance improvement be measured?
Plain answer: by counting repeated queries per row and naming the call sites, so a later change can show its own number on
the same instrument. Timing is reported as a reading of one run, never as a property of the code.

## Problem 10 — Concurrent Subject Registration

**Q10.1** How will the database process multiple enrollment requests?
Plain answer: as separate transactions that line up on the row each one needs, so nobody reads a count another writer is
changing.

**Q10.2** How will you prevent incorrect enrollment counts?
Plain answer: count under the lock, write under the same lock, and let a unique key refuse the bad state rather than
trusting the check.

**Q10.3** What concurrency-control mechanism will you use?
Plain answer: a pessimistic row lock taken as the first statement, one fixed lock order, a bounded retry on the two clash
codes, and a re-read after the lock so the writer that waited does not replay from a stale copy.

**Q10.4** How will you test simultaneous enrollment?
Plain answer: with two real processes released at the same instant. A single-threaded local server would quietly run them in
order and the test would prove nothing.

## Problem 11 — Invalid Enrollment Data

**Q11.1** How can the database prevent invalid records?
Plain answer: by owning the rules that must survive an application bug: foreign keys, NOT NULL, enums, and unique keys.

**Q11.2** Which constraints should be used?
Plain answer: primary keys, foreign keys with a deliberate delete action per rule, NOT NULL on required facts, enums for
status words, and unique indexes on natural keys.

**Q11.3** Which rules should be enforced by the database?
Plain answer: the structural ones: identity, reference, allowed values, uniqueness. Academic rules stay in the application
because they need a locked read and a sentence a person can act on.

**Q11.4** How will you test these constraints?
Plain answer: by writing the bad row on purpose, on both database engines, and asserting the refusal names the rule.

## Problem 12 — Prerequisite Checking

**Q12.1** Where should prerequisite information be stored?
Plain answer: as a column on the curriculum's subject placement, per program, so two programs can hold two rules and
anybody can read them.

**Q12.2** How will the system check the student's academic history?
Plain answer: resolve the student's own curriculum, collect what they already satisfied (passing grades plus approved
credit transfers), then refuse the proposal when a needed subject is missing.

**Q12.3** How should the system prevent an invalid enrollment?
Plain answer: at the act that would create it, inside the same transaction, so a refusal writes nothing, and the checklist
shows the same verdict before the desk acts.

**Q12.4** How will the rule be enforced consistently across different workflows?
Plain answer: one method, several readers. The fast pre-loaded answer and the fresh read are tested to agree in both
directions, because a cached copy that disagrees is a second rule.

## Problem 13 — Maximum Academic Load

**Q13.1** How will the system calculate the student's total units?
Plain answer: it already does, subject by subject and summed, using the same arithmetic the transcript uses.

**Q13.2** When should validation occur?
Plain answer: inside the save, after the old lines are removed and before the new ones are written, and only after the row
is locked, so the total being checked is the total about to exist.

**Q13.3** How could concurrent enrollment requests affect this rule?
Plain answer: two saves on one record already line up on its lock, and two records for one student in one term are held
apart by the seat key. A ceiling across different students' records in the same term needs a school figure, not a new
mechanism.

**Q13.4** How will the system prevent the student from exceeding the allowed load?
Plain answer: the rule is built and reads a settings value; the number is the school's to state. Until a ceiling is stored,
nothing is refused and the screen says the limit is not set rather than inventing one.
Words: a blank settings row means no ceiling, which is different from a ceiling of zero.

## Problem 14 — Faculty Teaching Load

**Q14.1** How can the database track faculty teaching assignments?
Plain answer: one schedule row per subject, section, room and teacher, with the term reached through the section. Rooms and
teachers are reused each year, so scoping through the section is what keeps last year's meetings out of this year's clash
check.

**Q14.2** How can the system calculate teaching load?
Plain answer: count the sections, or sum the subject units, grouped by teacher for one term. The data is all there; what is
missing is the school's ceiling.

**Q14.3** How can conflicting or excessive assignments be detected?
Plain answer: the conflicting half ships: teacher, room and the section's own hours, inside one term, where a class ending
exactly when another starts is not a clash. The excessive half is one comparison against a stored ceiling away, waiting on
that number.

**Q14.4** How can assignment records be kept accurate?
Plain answer: foreign keys refuse a schedule naming a section or teacher that does not exist, the term is never duplicated
on the schedule, and the audit re-weighs clashes and over-filled blocks over the whole table.

## Problem 15 — Conflicting Class Schedules

**Q15.1** How will the system detect schedule conflicts?
Plain answer: for shared resources, by comparing teacher, room and time within one term. For a student, the active-seat rule
already stops a second enrollment in the same term; a per-meeting clash check for one student's own subjects is the owed
half.

**Q15.2** Which database records must be compared?
Plain answer: the schedule rows, the section (which carries the term and year level), the meeting days and times, the room
and its capacity, and the student's enrollments and subject lines.

**Q15.3** How will overlapping time intervals be identified?
Plain answer: as half-open ranges, so back-to-back classes do not count as overlapping, matched per day of week, inside one
term. Both boundaries are tested: touching times are allowed, and a clash across two terms is not a clash.

**Q15.4** How will an invalid enrollment be prevented?
Plain answer: the timetable act refuses the bad assignment, and the student act refuses a second enrollment in the same
term. What is left is comparing a student's own meeting times, which is registered as owed, not claimed.

## Problem 16 — Transaction Failure During Enrollment

**Q16.1** Which operations should belong to the same transaction?
Plain answer: the writes that make one act true, together: enrollment and its workflow form, subject lines with the status
change and its history row, a box signature with the status it implies, a receipt with the balance and status it clears.

**Q16.2** What should happen when one operation fails?
Plain answer: all of them are undone, the desk sees a reason, and the attempt is in the audit log.

**Q16.3** How will partially completed records be prevented where all-or-nothing behavior is required?
Plain answer: by the closure form of a transaction, which saves on a clean return and undoes on any error, so a stray
try/catch cannot leave half a commit.

**Q16.4** How will COMMIT and ROLLBACK be used?
Plain answer: the framework issues them for the act: BEGIN at the first statement, COMMIT when the work is done, ROLLBACK
when it throws. A separate limited retry decides whether a rolled-back act runs again at all.

## Problem 17 — Power Interruption During Enrollment

**Q17.1** What problems might occur if the enrollment process is interrupted halfway?
Plain answer: an enrollment with no subject lines, a workflow form signed twice or not at all, a fee sheet with a total and
no charges, two records for one act, and a student who believes they are enrolled when nothing saved.

**Q17.2** What should happen to enrollment changes that were not yet committed?
Plain answer: they disappear. The engine discards uncommitted work on restart using its undo log, so the next reader sees
the record exactly as it stood. No application code has to notice the crash.

**Q17.3** How will the system prevent a student from being left with a partially saved enrollment?
Plain answer: the act is one transaction, and the submission can be sent twice safely: the request key finds the record that
already exists and answers with it, and a unique key makes a second copy impossible.

**Q17.4** How can the user confirm whether the enrollment was completed once power returns?
Plain answer: three ways that do not rely on memory: send the same key again and read the answer, open the queue or the
student's record and look at the status and checklist, or read the status history, which shows what actually moved.

**Q17.5** Which database mechanisms (transactions, logs, backups, power protection) reduce the damage?
Plain answer: transactions plus the engine's redo and undo logs, the audit and status-history tables as the application's
own log, scheduled dumps kept outside the repository and a tested restore. Power protection is a building matter, named
here as the school's side of the pair.

## Problem 18 — Duplicate Student ID

**Q18.1** Why can a duplicate Student ID cause problems for enrollment records and reports?
Plain answer: the ID is how a person is recognised on paper and in a total. Two records sharing one ID merge into one student
in any report that joins on it, and split one person across two.

**Q18.2** Which database constraint should prevent two student records from sharing the same Student ID?
Plain answer: a unique index on `students.schoolIdNumber`.

**Q18.3** What happens if both staff members submit the registration at the same moment?
Plain answer: both pass the screen's uniqueness check against a table that holds neither yet, and the database decides: one
commit wins, the other is refused with a duplicate-key error.

**Q18.4** How should the system inform the second staff member that the Student ID is already in use?
Plain answer: normally as a validation error on the field, which keeps the form and what was typed. In the rare race it
arrives as a database duplicate error, and that is translated into the same plain message instead of a generic failure page.

**Q18.5** How will you test that the duplicate registration is rejected?
Plain answer: by submitting the duplicate on purpose and asserting the refusal and its wording, and by re-running the
before-and-after census whenever a new unique key is considered.

## Problem 19 — Accidental Deletion

**Q19.1** What data could be lost or become inconsistent after the deletion?
Plain answer: the person's whole trail: subject lines, fee sheet and receipts, clearances and their approvals, workflow
boxes, and the history that explains a status. Reports then name rows that no longer resolve, which is worse than a clean
loss.

**Q19.2** How can the system prevent accidental or unauthorized deletion?
Plain answer: three brakes. Most foreign keys refuse the delete outright. Each desk lists the children that block it and
refuses before querying. And a right the role does not hold is refused first, by the policy.

**Q19.3** How can the system record who deleted the data, and when?
Plain answer: in `auditlogs`: which account, which act, which record, the address it came from and the moment. Status moves
get their own history row as well.

**Q19.4** How can the deleted record be restored (e.g., soft delete, backup)?
Plain answer: soft first: a `dropped` status retires an enrollment and keeps everything readable. Hard second: a dump
restored into a separate schema, which was actually performed and compared row by row, not just described.

**Q19.5** How will the section's enrolled count remain accurate after a deletion or restoration?
Plain answer: because the count is derived from the rows under a lock, adding or removing one changes it by definition. There
is no stored counter that could be forgotten, and dropping a row releases its seat key.

## Problem 20 — Reports During Active Enrollment

**Q20.1** What incorrect or inconsistent results could the report show if enrollment data is changing while it is read?
Plain answer: a total taken before a payment lands beside a line list taken after it, so the parts do not add; a tile
counting a status the rows below it do not have; a name that has since been dropped.

**Q20.2** Which isolation level or read approach (e.g., a consistent snapshot) should be used for reports?
Plain answer: the engine's default (REPEATABLE READ) with every read of the report taken inside one transaction, which is
what makes it one snapshot instead of several reads stitched together.

**Q20.3** Should the report block enrollment transactions, or should enrollment be allowed to continue? Why?
Plain answer: enrollment continues. A consistent read takes no row locks, so a report does not slow a writer. Blocking the
queue so somebody can read a number would put the whole day behind a reader.

**Q20.4** How can the report reflect the data as of one specific point in time?
Plain answer: the transaction's read view does it, and the printed sheet states its own date and a document number minted
before the render so the paper and its log row match. The day boundary is the school's, not the server clock's.

**Q20.5** How will you test generating a report during simultaneous enrollment?
Plain answer: by driving the writes concurrently and reading the report while they land, on the engine that runs it, in the
order the pipeline is built.

## Problem 21 — Database Recovery

**Q21.1** What happens to transactions that were not completed?
Plain answer: they are undone and never seen. On restart the engine replays what was durable, then discards the work of
transactions that had not committed.

**Q21.2** What happens to transactions that were already committed?
Plain answer: they survive, provided the log reached the disk. That setting is read from the server, not assumed, and the
restore drill proves the second half: the committed receipt is in the dump taken after it.

**Q21.3** How does the database restore reliable data?
Plain answer: crash recovery first, a restore from a known dump when the data itself is gone. The project's part is that the
restore was executed into a separate schema and compared table by table.

**Q21.4** What recovery mechanisms, backups, or logs should be considered?
Plain answer: scheduled dumps with a stated retention kept outside the repository, the binary log for point-in-time replay
(named as the school's option; this rehearsal did not enable it, so it is not claimed as installed), the engine's own redo
and undo logs, and a restore test that anyone can run.

## Problem 22 — Unauthorized Database Access

**Q22.1** Should every role have access to the same data?
Plain answer: no. Rights are per module and per act; the student 360 needs a right some desks do not hold; clinical notes
need the clinic's own right; a college-bound account sees only its college.

**Q22.2** What operations should each role be allowed to perform?
Plain answer: what its role entry names. Signature acts belong to the approving roles, a department proposes but does not
approve, only the Registrar adopts an exam result, and the college boundary applies on top of every right.

**Q22.3** How can database permissions or application authorization be used?
Plain answer: both, split honestly. Application authorisation carries every role rule, because that is where workflow state,
the college boundary and a readable refusal live. The database carries the structural rules and would still hold if someone
connected directly.

**Q22.4** How can sensitive student information be protected?
Plain answer: send less and log it properly. Lists select only the columns their screen paints, credential hashes are
hidden and never ride in on a joined row, the requirement gate protects uploaded paper, and audit log entries are
redaction-filtered.

## Problem 23 — Heavy Enrollment Traffic

**Q23.1** Which database operations are likely to become bottlenecks?
Plain answer: in order of contention: the section row for a popular schedule, the enrollment row for a multi-subject
proposal, the capacity count under the lock, the money writes, and any per-row read repeated for every listed record.

**Q23.2** Which queries should be optimized?
Plain answer: the two the measurements named: the contains-name search, and the readiness loop, already reduced from five
repeated reads per row to three. One of them is still owed, and that is stated rather than counted as done.

**Q23.3** Which tables may receive the most reads or writes?
Plain answer: reads: enrollments, students, enrolled subjects, terms. Writes: enrollments, enrolled subjects, fee sheets,
payments, workflow boxes, audit log, notifications and status history.

**Q23.4** How can indexing improve performance?
Plain answer: by turning a filter into a jump: term plus status for the queue, a name prefix, a unique ID seek, a child
lookup by foreign key. An index cannot help a pattern that starts with a wildcard, which is why the search rewrite matters.

**Q23.5** How can concurrency affect throughput and response time?
Plain answer: locks make writers on one hot row take turns, which is the point: the alternative is an overbooked section. The
cost shows as waiting, and sometimes as a clash that is retried. Everything else reads without waiting.

## Problem 24 — Complete Enrollment-Day Challenge

**Q24.1** How will the database maintain data consistency?
Plain answer: by refusing to store the same fact twice. Counts are derived under a lock, statuses move through one state
machine, academic verdicts come from one method, and an audit command re-weighs record states on demand.

**Q24.2** How will concurrent transactions be handled?
Plain answer: pessimistically for short contended acts (row locks in one order, sorted lists, limited retry on the two clash
codes) and optimistically for long human edits (a version on the record), with unique keys as the last line.

**Q24.3** How will transactions and ACID properties be applied?
Plain answer: one transaction per act, so all-or-nothing is the engine's promise, not a habit. Consistency from constraints
and the state machine, isolation from locks for writers and snapshots for readers, durability from the log settings that
were read and a restore that was performed.

**Q24.4** How will duplicate enrollment and over-enrollment be prevented?
Plain answer: three keys: the subject line cannot repeat per attempt, one fee sheet per enrollment, and one active seat per
student per term. The capacity ceiling is enforced at the act and checked by the audit.

**Q24.5** How will deadlocks be prevented or handled?
Plain answer: prevented by one lock order, sorted ids and short transactions; handled by a bounded retry that re-runs only
the two conditions meaning "somebody held it first" and then refuses in words. Both sides were observed on the school's
engine, not theorised.

**Q24.6** Which operations should be protected by transactions?
Plain answer: issue, propose, approve, drop and cancel, seat and unseat, money in and its void, the clearance generation,
and the timetable import. Anything a person would call one act is one transaction.

**Q24.7** Which fields should have indexes?
Plain answer: the name pair and the unique school ID on students; the enrollment's student, course and term keys plus the
term-and-status pair; every child's foreign key; and the schedule's section, room and teacher columns.

**Q24.8** How will performance be maintained as the database grows?
Plain answer: keep reads as jumps rather than scans (verified on a 100,000-row schema), take the loops out instead of adding
hints, paginate what a desk lists, and re-measure with the instruments that name their own counters.

**Q24.9** How will you prove that the database handles these situations correctly?
Plain answer: with instruments that can fail, and a reading from each: two-process races whose pre-fix result is
deliberately unsafe, a thousand-act load run across ten real processes, a rollback forced from inside the transaction, a
restore compared row by row, and a suite that pins each rule.
