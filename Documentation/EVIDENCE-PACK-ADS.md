# Demonstration evidence pack — Advanced Database Systems

Requirement: `Enrollment_Management_System_Master_Implementation.md` prompt part 42 (academic demonstration
evidence). Written 2026-11-01. This is an index of artefacts that already exist on disk or can be re-run in one
command, not a transcript: every row names the thing to open and what it shows.

**What is not here.** No screenshots were captured in this batch, and none are described as if they were. The
screens a demonstration needs are listed with the exact route and office account, so the capture is a mechanical
step; the reason they are absent is that a screenshot is a claim about a rendered page and this batch's evidence is
server-side. The same rule applies to the load run: designed in `DATABASE-DESIGN-ADS.md`, not executed.

---

## 1. Commands that reproduce a result on screen

| Claim to demonstrate | Command | What the output shows |
|---|---|---|
| One seat, two desks, one winner | `php storage/app/scratch/lane168-probe-last-seat-race.php --pairs=3 --keep` | three per-pair lines with each writer's status and its seats-after, then HEAD's pass printing `*** OVER CAPACITY ***` and a `1213`, then the restored working file answering `clean 3/3` |
| The pre-fix code really raced | same command, second pass | it swaps in `storage/app/scratch/lane168-head-BlockingController.php` by hash and restores in a `finally` |
| Every promise has a guard that bites | `php storage/app/scratch/mutate-g-ads-capacity-tests.php` | 10 legs each printing `KILLED` with the case that caught it, plus a green control before and after and `tree verified by hash` |
| Capacity is reconciled, not assumed | `php artisan ems:audit:record-states` | 20 rules, each with its count, and the new `A block holding more students than it declares` reading 0 |
| The keys refuse at the server | `php artisan test tests/Feature/Enrollment/DatabaseLevelEnrollmentRulesTest.php` then the same with `DB_CONNECTION=mysql DB_DATABASE=ems_l170_a` | the fee-sheet case passes on both drivers; the seat case passes on MySQL and states what SQLite cannot judge |
| A thousand enrollment acts keep the database honest | `php storage/app/scratch/lane172-enrollment-day-load.php --requests=1000 --workers=10 --keep` | ten processes issuing through the act every desk uses, then ten workers against one seat, then five drop-versus-issue races; the table prints 0 duplicate seats, 0 orphan forms, 0 records with two forms, one winner on the contended seat, and the server's own isolation and thread counters |
| The load tool's questions can fail | `php storage/app/scratch/mutate-lane172-load-integrity.php --source=ems_l172_a --term=11 --contended=<studentId>` | each integrity question is planted in a damaged copy and the same tool must name it; the control reads clean before and after |
| Search cost is measured, not asserted | `php storage/app/scratch/lane164-measure-scale-queries.php` | timings with the `EXPLAIN` plan beside each, over 100,000 students — and the honest note that the millisecond moves between runs while the plan does not |
| A dump restores and matches | `php tools/ems-backup-drill.php --target=ems_drill` | 64 tables compared by `COUNT(*)`, three ordered digests, and the durability variables read from the server |
| The scale fixture is honest | `php storage/app/scratch/lane163-build-scale-schema.php --students=100000 --target=ems_l163_a` | it refuses a block over its own capacity and prints the fullest partition (`40 of 40`), so a probe cannot measure an impossible state |

## 2. Artefacts on disk

| File | What it is |
|---|---|
| `storage/app/scratch/l171-suite.log`, `l171-suite-junit.xml` | the green whole-suite run this batch's figures come from; `--list-tests` discovers the same number it reports |
| `storage/app/scratch/l170-suite.log` | the run before it, which answered two failures and is kept because each was a finding: the rule-count guard aging to twenty, and the seat key catching a fixture that minted a second enrollment |
| `storage/app/scratch/lane164-out.txt`, `lane168-out.txt`, `lane172-load-out.txt`, `lane172-harness-out.txt` | the captured stdout of the scale measurement, the two-writer race, the thousand-request load run and its falsification harness — the files the *Logs / query output* field of each test case quotes, so no case rests on a line nobody saved |
| `ems_l172_a` (scratch schema, left in place by `--keep`) | the schema the load run wrote: 1,000 `LOAD-` students, one thousand enrollments each with exactly one workflow form, and the harness's source of truth |
| `storage/app/scratch/lane155-ads-guide-flat.txt` | the guide's own text, extracted from its docx with the markup-leak refusal that found 34,679 bytes of it were tags |
| `storage/app/scratch/lane155-ads-population.json` | the measured population behind the matrix: 24 problems, 106 guide questions, 12 concepts, 8 presentation areas |
| `C:\Users\ADMIN\ems-backups\ems-pre-db-enforcement-2026-11-01.sql` | the pre-migration dump of live `ems` (never in the repository, per its rule) |
| `pass380-backup-*.docx` … `pass383-backup-*.docx` | the authority document before each edit in this batch |
| `storage/app/scratch/word-open-report.txt` | Word opened a hash-identical copy: 254 pages, 186 tables |

### Where each file prompt part 41 names actually lives

This repository keeps its documentation in `Documentation/`, not `docs/`, and each of these seven already has a
first-build neighbour of a similar name, so the ADS set carries the `-ADS` suffix rather than displacing the
documents the authority docx and the guides already point at. One table, in one file: a second index would be a
second answer to the same question.

| Prompt part 41 asks for | Delivered as | Also carries |
|---|---|---|
| `docs/SYSTEM_ARCHITECTURE.md` | `Documentation/SYSTEM-ARCHITECTURE-ADS.md` | its own naming note, and the nine desks with their routes |
| `docs/DATABASE_DESIGN.md` | `Documentation/DATABASE-DESIGN-ADS.md` | the generated ERD and the load plan; the data dictionary stays in the authority docx §24 rather than being copied |
| `docs/REQUIREMENTS_TRACEABILITY.md` | `Documentation/REQUIREMENTS-TRACEABILITY-ADS.md` | all 24 problems, the 12 concepts, the 8 presentation areas, the T01-T24 map |
| `docs/DATABASE_CONCEPT_COMPLIANCE.md` | `Documentation/DATABASE-CONCEPT-COMPLIANCE-ADS.md` | one section per concept row, in the guide's own status vocabulary |
| `docs/TESTING_AND_VERIFICATION.md` | `Documentation/TESTING-AND-VERIFICATION-ADS.md` | the executed cases and the ones marked not executed with the reason |
| `docs/SECURITY_AND_RECOVERY.md` | `Documentation/SECURITY-AND-RECOVERY-ADS.md` | the restore drill's own verdict line |
| `docs/IMPLEMENTATION_CHANGELOG.md` | `Documentation/IMPLEMENTATION-CHANGELOG-ADS.md` | every file, migration and docx pass in the batch |

Prompt part 44 (the implementation summary) is `Documentation/FINAL-REPORT-ADS.md`, prompt part 42 (academic
demonstration evidence) is this file, and prompt part 45 (the defence questions) is
`Documentation/DEFENCE-EVIDENCE-ADS.md`.

## 3. Queries a panelist can run live

```sql
-- the rule problem 3 asks for, visible in the schema rather than in code
SELECT TABLE_NAME, INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) cols
  FROM information_schema.STATISTICS
 WHERE TABLE_SCHEMA = 'ems' AND NON_UNIQUE = 0
 GROUP BY TABLE_NAME, INDEX_NAME ORDER BY TABLE_NAME;

-- does any block hold more seats than it declares? (the audit's twentieth rule, stated as SQL)
SELECT b.blockId, b.maxStudents, COUNT(DISTINCT es.enrollmentId) held
  FROM blocks b JOIN enrolledsubjects es ON es.blockId = b.blockId AND es.status <> 'dropped'
 GROUP BY b.blockId, b.maxStudents HAVING held > b.maxStudents;

-- the generated column, as the server defines it
SELECT COLUMN_NAME, DATA_TYPE, EXTRA, GENERATION_EXPRESSION
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = 'ems' AND TABLE_NAME = 'enrollments' AND COLUMN_NAME = 'seatKey';

-- what durability actually costs a commit today
SELECT @@innodb_flush_log_at_trx_commit, @@sync_binlog, @@transaction_isolation;
```

The last one is the honest answer to "what isolation level": the server reports its default and the application
never changes it, which is matrix P20's open item rather than a claim this pack can make.

## 4. Screen walkthrough for the demonstration day

| Step | Account | Route | What the record becomes |
|---|---|---|---|
| 1 | `office7_head` (or a `dept.*` account of the record's college) | Department Evaluation → sign | the box closes and the next desk's bell rings, addressed to that college's desks only |
| 2 | `office3_head` | Assessment → compute | a fee sheet exists, and the new key means a second one for the same enrollment cannot be created |
| 3 | `office2_head` | Accounting → record | money arrives; a `paid` status appears only when the sheet clears |
| 4 | `office5_head` | Blocking → assign students | the capacity guard answers; assign the same last seat from a second browser tab to show the refusal, then run the probe to show why the *simultaneous* case needs the lock |
| 5 | `office1_head` | Registrar → print | a paper is issued, its number is minted under a row lock and logged against the enrollment |
| 6 | any desk | the bell on a notice | the notice names the record, the action and the desk that owes it, and its link opens a queue the recipient is allowed to see |

Step 4 is the one worth rehearsing, because it is the guide's question in a click: two tabs, one seat, one refusal —
and the reason it *looks* safe in a demo is that the second tab is not simultaneous. The probe is what makes the
claim, not the browser.
