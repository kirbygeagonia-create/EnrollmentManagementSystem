# Project Instructions — SEAIT Enrollment Management System

## The two rules that outrank everything below

1. **Never commit or push until the user says so explicitly.** This overrides the
   commit-before-dispatch habit described elsewhere in this file. Ask, then act.
2. **`Documentation/EMS Complete Documentation.docx` is the authority.** Owner ruling
   2026-09-29. Where the storyboard, the use-case guide or this file disagree with it,
   the docx wins — and when the code moves, the docx is edited to follow in the same
   batch of work, never left describing an older build.

## Gate requirements before any push

All five must pass, then watch CI to green and read `gh`'s own exit code:

- **Pest** — run `php artisan test` **whole**, never per-directory; per-directory runs hide
  cross-file breakage (the 2026-10-16 instance: allowing one more class to skip in
  `AssertNoSilentSkips::ALLOWED` broke that guard's own three fixtures, which are hand-built to be CI's shape —
  each green on its own file, three red in the full run). The SQLite suite is the default (`phpunit.xml` pins `:memory:`, and
  `AdminAccessSmokeTest` builds its own SQLite schema). The E2E walkthrough legs need a **seeded** MySQL
  database: they read courses, subjects, rooms, terms, the current curriculum and the seeded roles, so a
  migrated-but-empty one is not enough. They take the database from `E2E_DB_DATABASE`, defaulting to the
  live `ems`; host/user/password come from the ordinary `DB_*` env and are never hardcoded (CI's container
  is `root`/`root`, the repo default is `root` with no password). They skip — naming the host they tried and
  the table that is missing — when the database is unreachable, unseeded, or the credentials do not match.
  CI runs them in their own step against `ems_test` after seeding it, under `--fail-on-skipped`.
- **PHPStan** — 0 errors. **Pint** — `--test` passes. **ESLint** — `npx eslint resources/js --max-warnings=0`.
- **Vite build** — succeeds. CI runs three jobs (`.github/workflows/ci.yml`: Pest+PHPStan+Pint on
  SQLite, Pest on MySQL 8, ESLint+build). Do not merge red.
- After any gate change, also run `php artisan ems:print-fidelity` and
  `php tools/seed_full_demo_pipeline.php` and **read their warnings** — a green suite does not
  prove a desk can demonstrate the rule.
- **After any batch that edits the guides or the per-office walkthrough**, run
  `php storage/app/scratch/lane40-walkthrough-claims.php` and
  `php storage/app/scratch/lane41-doc-claims.php` against live `ems` and read their verdicts. The first
  resolves every record-id-plus-status claim the desk script makes, against the table and the student it
  names (it found seven citations to rows retired on 2026-10-15, five of them since re-seated under new ids);
  the second resolves every peso figure and aggregate (it found "the 12 catalogs" where the router answers
  13, and that "fee type 5" is a counter value — 19 on a schema the suite seeded first, which is why the
  slip fee is now addressed by name). CI runs only lane 41's `--ci` subset, because the rest of those
  claims are keyed to this box's record numbers and could never be true on a freshly seeded schema.
  `php storage/app/scratch/mutate-lane41-claims.php` proves that checker can fail — run it after touching
  either parser, and note that its first run reported five missed defect classes because the `--dir=`
  override the harness passed was never read by the code: an ignored flag makes a mutant run look like a
  clean one.
- **After any batch that touches the two third-party assessments** (`SEAIT-EMS-Round3-Current-State.md`,
  `Documentation/Audit-Remediation-2026-08-29.md`), run
  `php storage/app/scratch/lane42-assessment-claims.php` and prove it with
  `php storage/app/scratch/mutate-lane42-assessment-claims.php`. They separate *history* from *present-tense
  state*: the Round-3 file's counts (88 permissions / 54 tables / 22 controllers) and its "CSP still open"
  row were both written as of 2026-09-05 and never re-checked — `d29653e` added the CSP header **in the same
  commit that stored that report**, so the row was false as it was written and stayed unnoticed for six
  weeks because no test pinned any header. Its "13 process roles + 3 cross-cutting" also never added up:
  `RbacSeeder` declared 17 roles on that date. Both checkers resolve figures per *paragraph*, last mention
  wins, and a claim whose text cannot be found is reported `UNBOUND` and refuses the run — an unread claim
  must never look like a checked one.
- **After any migration that adds a UNIQUE index, or any edit to §23.6.1**, run
  `php storage/app/scratch/lane44-unique-keys.php` and prove it with
  `php storage/app/scratch/mutate-lane44-unique-keys.php`. §23.6.1 claims to list *every* unique index in the
  domain tables, so it is a boundary, not a summary: nine keys across seven tables is what `ems` holds, and the
  print-log issuance key (`documentprintlog` `enrollmentId, documentType, documentNumber`, added 2026-09-30)
  was absent from it for six weeks — the section still said "eight keys" while §23.3's own Total row had moved.
  The checker reads the regenerated `docx-flat.txt`, so re-dump before believing a green run; its harness
  mutates a **copy** through `--dump=` and includes a case proving that flag is read rather than accepted.
  **A mutant anchored on a generated line ordinal is a mutant that sleeps.** Its two row cases planted defects
  by replacing text prefixed `ROW 1237:` — docx pass 141b inserted one paragraph in §18.3, every ordinal after it
  moved by one, both `str_replace` calls matched nothing, and those cases reported the checker's *clean* output as
  a failure to complain. Rows are now located by their own cell content and every case refuses outright when its
  mutation leaves the dump byte-identical, which is proved by running a variant whose anchor cannot match.
- **After any batch that adds a table with foreign keys, or touches a delete path**, run
  `php storage/app/scratch/lane43-delete-guards.php` and prove it with
  `php storage/app/scratch/mutate-lane43-delete-guards.php`. Live `ems` carries 105 foreign keys: only four
  cascade (the RBAC pivots `RbacSeeder`'s prune relies on), one is RESTRICT (`shiftingrequests.requestedBy`),
  and 72 refuse a delete outright. Every desk delete answers those with a **hand-written list** of child tables
  — `blockedDeletionMessage()`, a `->exists()` chain, a private helper, or a relation check — and a list that omits
  one does not produce a 500: `bootstrap/app.php:140` renders the `QueryException` as a generic "conflicts with
  information already on file… reload and retry", which names no record and tells the desk to repeat something
  that can never work. Lane 43 found exactly that in two screens: the shift module named `courses` twice and
  `staffusers` once, and neither guard had been told. A checker that reports zero sites must refuse (it once
  matched nothing because PSR-12 puts the opening brace on its own line, and an empty verdict looked like a
  clean one); its mutants must delete the **table reference**, not a label — renaming a key leaves the guard
  intact and the mutant asleep.
- **After any batch that adds or edits a desk filter, a page's `router.get`, or a controller's `filters` echo**,
  run `php storage/app/scratch/lane46-filter-contract.php` and prove it with
  `php storage/app/scratch/mutate-lane46-filter-contract.php`. It joins page → route → handler and reports
  DEAD-SEND (a key the page sends that the handler never consults), NO-RETURN (consulted but not echoed, so the
  control resets while the list stays narrowed), FICTION (a dropdown value the column's MySQL enum cannot hold —
  that is how `graduated`/`dropped` were found on `students.status`, which is `enum('active','inactive')`), and
  NO-ROUTE for any claim it cannot bind. **Its first version judged 27 bindings and cleared the tree because it
  read only `route('x', {…})` link objects and never `router.get(route('x'), {…})`** — the shape where a desk's
  own filters actually live, so every filter submit in the repository was invisible; the corrected run judges 56
  bindings, measured 2026-10-26 — the set grew by one when the Registrar's record page gained its history filters, and
  the sentence names the day it was read, because two figures for one instrument in one sentence is
  how lane 58's multi-total defect is born.
  A checker pointed at the wrong construct is worse than one that reads nothing, because it prints a pass. Its
  scope is a flag (`EMS_LANE46_PAGES`), so the harness proves the flag changes what is scanned and that an empty
  scope *refuses* rather than reporting clean.
  **Its echo reader was blind to the two shapes the sort batch introduced** (2026-10-30). A `filters` prop written as a
  multi-line array, or as `$request->only([…]) + ['sort' => …]`, matched neither of its two one-line patterns, so the
  reader answered null — and null means "no echo to compare", which left NO-RETURN unsatisfied on exactly the four
  newest screens while a mutant that deleted an echoed key printed a clean tree. The value is now walked by its own
  brackets and ends at the first comma at bracket depth zero, because a merged array is part of the same echo. And a
  page that builds its query by spreading `sortParams(…)` sends `sort` and `direction` with neither name written in the
  object literal, so those keys are read out of the helper's own `return { … }` rather than spelled a second time
  here — two definitions of one vocabulary is the lane 69 defect. The harness surfaced both because one of its legs
  reported `MUTANT DID NOT APPLY`: its anchor had aged the day the wiring put `sort` and `direction` beside `status`,
  and re-cutting that leg to the current shape is what exposed the reader's null. **A leg that cannot apply is the
  instrument's age; a leg that applies and still prints clean is the instrument's hole. Both are read as findings,
  never as a run that passed.**
  **A harness run concurrently with the suite is not a harness run.** The first run of that pair, launched in the same
  message as `artisan test`, reported `1 case(s) failed`; run on its own afterwards it answered 8 legs `ok` and exit 0.
  That is the no-concurrent-mutating-runs rule biting an *instrument* instead of a suite: the bad verdict was fake, and
  a fake red is as costly as a fake green. Never launch a harness beside a suite run, and re-run any harness that
  reports badly while something else is writing.
- **A development-prompt requirement the build never covered is a finding, not a defect** (lane 75). The master prompt
  (`EnrollmentManagementSystem_Optimized_AI_Development_Prompt.md`, found in `Downloads/` and never in the tree) was
  audited requirement by requirement against this checkout and the result is
  `Documentation/Prompt-Requirement-Matrix-2026-10-26.md`: ~forty requirements verified **Working** with file-level
  evidence read that day, and ten gaps G-1 … G-10 with their phase, risk and whether they wait on an owner ruling.
  G-1 (§7.4 — the Registrar must read grade records **by academic year and semester**, not only the current term) is
  built: `AcademicStandingService::gradeHistory()` re-reads the same rows the standing derivation reads, deliberately
  *including* the term under review and any ungraded row, and answers null for a grade nobody entered so the desk dashes
  it instead of counting a pass; the passing line comes from `gradescale` and the page names it when the table is empty
  rather than implying the assumed 3.00 is the school's. Pinned by `tests/Feature/Registrar/RegistrarGradeHistoryTest.php`
  and proved by `php storage/app/scratch/mutate-g1-grade-history-tests.php` (a green control counted out of the test
  file's own `#[Test]` markers, then the filter ignored, a null grade called a pass, and the passing line forged to a
  constant — each must make the named case print `1 failed`, and the control must return to green afterwards).
  **The vocabulary guard caught my own new line**: a fallback written `'Year '.$id` turned
  `YearLevelVocabularyTest > no surface spells a year level by hand` red on the first whole-suite run, even though the
  new file's own seven cases were green — a guard that fires on code that passed its own tests is the guard earning
  its keep. A label for a level goes through `formatYearLevel()` or says nothing at all.
  Two measurement lessons from the same batch. **`array_unique()` keeps the original keys**, so reading `$unique[0]`
  off an array keyed by citation tokens answered null and a docx pass silently shifted every pointer by *nothing*;
  use `array_values()`/`reset()` and refuse unless the delta is a positive whole number. And a probe that locates a
  moved window with `strpos("\n".$needle."\n")` then counts newlines **reports every delta one row too high** —
  `lane75-probe-moved-cites.php` now compares whole arrays of lines. The pass itself refused to write until both were
  fixed, because its stage 2 re-reads every shifted window in *both* versions of the file and requires the same bytes:
  a shift may not be verified by arithmetic from the bug that produced it.
- **G-2 (§7.1, §14 — incomplete intake saved as a Draft and resumed without re-entering data) is built**, and five
  rules came out of it that new code must respect. **A driver guard that widens only MySQL leaves the suite unable
  to write the new value**: `SQLiteGrammar::typeEnum` compiles `enum()` to `varchar CHECK ("admissionStatus" in
  ('pending',…))`, so the constraint travels with the table — the branch for the other driver is
  `$table->enum(…)->change()`, the way `payments.paymentStatus` gained `refunded`. **A draft still has to name the
  person** (`students.schoolIdNumber` … `passwordHash` are NOT NULL without a default, and a placeholder would be
  invented data — in the password case an invented credential); what it defers is the address, guardian and
  educational-background sections. **Relaxing a section to `nullable|array` is not enough**, because the wizard
  pre-selects its pickers, pre-fills the country and posts every row it was given: an untouched row still arrives and
  its own `barangay`/`cityMunicipality`/`province` rules refuse the very draft whose purpose is to defer them, so
  `store()` drops a row only when every field the desk has to *type* is blank — a half-written address is kept, so
  validation names the desk's work instead of discarding it. **Requirement rows exist from the draft**, because
  `Admission/Show.jsx` renders the requirement *catalogue* and looks its submission up: a case with no rows still
  offers an attach button, and `submitRequirement()` answers that with a 404 at `firstOrFail()` — and the seeding
  helper is idempotent, measured on live `ems`, because `studentrequirementsubmissions` carries no unique key on
  (admissionId, requirementId). **A screen may not decide an act from a status word**: `show()` now answers
  `deskActions` from the same policy the routes ask, which retired a standing defect — Verify painted on a draft
  *and* on an approved case, offering work the save refused. Pinned by
  `tests/Feature/Admission/DraftIntakeTest.php` (11 cases, 63 assertions) and proved by
  `php storage/app/scratch/mutate-g2-draft-intake-tests.php` (12 legs; one plants the missing SQLite widen, which
  is the defect the batch actually met, and each requires its own named case to print `1 failed`).
  Three prose lessons from the docx side (passes 250 and 250b). **A one-shot pass script is an artefact of its own
  run**: pass 250's body was overwritten later in the same session by a mis-pathed Write, so its file now records
  what it did and where its verification lives rather than pretending to be the script that ran. **Collapsing a
  multi-run paragraph into one run keeps the FIRST run's spacing** — §24's headings came out `admissions  —2
  columns`, and the chapter turned out to hold two spacing families (47 `name  —  N columns`, 9 `name — N columns`),
  measured before the repair rather than assumed. **A historical `File.php:NN-NN` literal in this file is still a
  citation to the checker**, which flagged this file's two citations of `AdmissionController.php` lines 211 to 222 as
  empty lines long after the constructs moved: a record of a past layout spells its numbers as prose, and only the
  live pointer keeps the colon form.
- **After any batch that adds or edits a summary tile, a `stats` prop, or a filter pick-list**, run
  `php storage/app/scratch/lane47-tile-scope.php` and prove it with `php storage/app/scratch/mutate-lane47-tile-scope.php`
  (the checker) **and** `php storage/app/scratch/mutate-lane47-tests.php` (the tests, which revert one desk each
  and require the named case to go red). It joins each screen's tiles to its handler and asks which set each
  number describes: SILENT-ZERO (an aggregate cloned from `(clone $query)` after `paginate()` — see the fact
  below), PAGE-TILE (a tile reducing over `props.data` under a label naming no page), MIXED-SET (one screen
  where a tile reads the paginator total while its neighbour reads the page), IMPOSSIBLE (a client predicate
  the handler's `whereIn` cannot return), DEAD-OPTION (a pick-list built from the page, so a value on a later
  page cannot be chosen). **It judges a tile by its source, never by its name** — `totalCapacity` is evaluated
  as the text it is defined from, `peso(x)` is unwrapped, `stats.pending` is resolved to the line the memo
  returns for `pending`, and anything untraceable is UNBOUND and refuses the run. The first draft classified by
  name and would have passed a page-derived capacity tile as a server number. Two live traps in its parsing:
  `<StatCard … iconBg={a > 0 ? …}>` carries a `>` inside an attribute, so a regex tag match silently drops the
  tile that most needs judging, and `{ stats: serverStats = null }` must yield the alias, not the key. Live
  `ems` cannot show the page-2 defect at all (its largest desk queue is 7 rows), which is why the tests write
  25 to 60 rows.
- **After any batch that touches a tile's fallback, `StatCard`, or a money formatter**, run
  `php storage/app/scratch/lane48-tile-fallback.php` and prove it with
  `php storage/app/scratch/mutate-lane48-checker.php` (the checker) **and**
  `php storage/app/scratch/mutate-lane48-tests.php` (the tests). Lane 47 asked which set a number describes; this
  asks what the screen paints when there is no number. It refuses INVENTED-ZERO (a `?? 0` / `|| 0` in a tile
  value, or in the local the tile reads), FALLBACK-DERIVED (a fallback to a count of the rows on screen —
  `serverStats || computedStats`, `enrollments?.total ?? enrollments?.data?.length`), UNSAFE-FORMATTER (a private
  `peso()` reading `Number(n || 0)`, which turns a null amount into ₱0.00 before any default is consulted),
  DEAD-KEY (a key the bound handler never answers, so the fallback is the only value the tile will ever show),
  OBJECT-TILE and BLANK. A real zero still renders: Accounting and Assessment coalesce `sum()` over an empty set
  in PHP, and that 0 came from the server. `DeskTileNumberSourceTest` pins the other half — on an empty database
  every tile's key must arrive typed and equal to 0, so a dash on the desk always means absence. Binding needed
  three things a first pass lacked: a route-bound model's keys are its **columns plus relations and appended
  accessors** (`student.studentclearances` is a relation, and a column list called it dead), the model's class
  comes from the action's **typed parameter** (a single-quoted `'\\?'` is a *literal* `?` to PCRE, so that
  pattern matched no parameter and every record page read UNBOUND forever), and a `peso()` written across three
  lines escapes a local scanner that stops at the newline. Its harness restores every touched file
  **unconditionally**: a guard that made the restore a no-op after the first call left each later mutant in the
  tree, and the next case measured a tree already carrying the previous defect.
- **After any batch that edits the demo generator's ledger passes, its year-floor pass, or its desk report**, run
  `DB_DATABASE=ems_l54_a php storage/app/scratch/mutate-lane54-report.php` and read every case. The report prints seven
  `Ledger —` lines today: six since lane 54 and a seventh since lane 56 added the receipt it had to re-mark.
  The four this harness owns (a fee sheet with a total and no lines, a paid step this run had to
  reconstruct, a returning claim with no year behind it, receipts exceeding the assessment) are the only
  view the tool has of its own output, and before lane 54 it repaired lane 53's two impossible states in
  silence. Each line is handed exactly
  the defect it names — one case per freshly built schema, with a clean control that must read 0 on all four —
  because a report line that can only print 0 is decoration. The harness refuses unless `DB_DATABASE` names
  `ems_l54_a` or `ems_l54_b`, and a case whose anchor matches nothing, or whose run reaches no verdict, prints BAD
  rather than passing: the first run threw on a `dirname()` one level short and still left the shell reporting
  success. `storage/app/scratch/lane54-live-ledger-baseline.php` is its read-only counterpart, asking the same
  questions of live `ems` *before* the changed tool is run over the live rows. And after any change to the
  generator, run CI's own last step against the dataset it just built —
  `E2E_DB_DATABASE=<schema> vendor/bin/phpunit tests/Feature/E2E --fail-on-skipped` — because that is the only
  place the pipeline's output is read by the walkthrough's legs. On 2026-10-18 it caught what five local green
  gates could not: making the paid step generator-owned settled Juan Dela Cruz's account with a receipt dated
  *today*, so the guard beside it that asked "is any money dated today?" found an answer and never created
  `DEMO-OR-TODAY-01` — the slip the Daily Collections Report reads by its number. The weaker question was the
  bug; ask whether the named row exists. One more shape of the same trap: the dataset ages at the **school's**
  midnight, because that slip is dated `SchoolTime::today()`. Measured 2026-10-20 — a whole-suite run on live
  `ems` after Manila crossed midnight, with no code change, failed `DemoDatasetRecordStatesTest` on exactly that
  assertion while CI, which seeds immediately before its walkthrough step, cannot see it. Re-dump and re-run the
  pipeline before believing that failure, and never "fix" the test to forgive a stale date.
- **After any batch that touches `BlockingController::detectConflicts()` or the two timetable rules in
  `ems:audit:record-states`**, run `php storage/app/scratch/mutate-lane55-conflicts.php` and read all three legs.
  It swaps HEAD's controller over the working one and requires the two named tests to go red on it, then restores
  the batch's bytes by hash and re-runs them green — so the claim proved is *this batch changed the verdict*, not
  merely that a test passes. It refuses when the HEAD export is missing or byte-identical, which is the state it
  is in **after this batch is committed**: re-take the export from the previous commit before believing a run on a
  clean tree. Its third case is deliberately expected GREEN before and after — the room branch worked all along,
  and a harness that demands every test flip will report a correct expectation as a failure. What that harness
  cannot reach is the audit's own half, so `AuditRecordStatesTest` pins the two boundaries instead: a meeting that
  starts exactly as another ends is back-to-back and unflagged, and a collision across two terms is unflagged.
- **After any batch that edits a `Ledger —` line of the demo generator, or a money rule in
  `ems:audit:record-states`**, run `DB_DATABASE=<scratch schema> php storage/app/scratch/mutate-lane56-ledger.php`.
  It plants one defect — the ₱8,000 installment marked `paid` over an owing balance, which is §28.3 G-24's own
  shape — and requires the audit to refuse it (exit 1, the rule named by its own label), the generator's report to
  print 1 on its line, the generator to re-mark the row and exit 1, and the following run to read 0 and exit as the
  control did. It refuses rather than reporting a pass: on live `ems`, on a schema with no `DEMO-OR-0002`, on a
  schema where that account holds no balance, and on a mutation that did not change the row. Lane 54's rule still
  governs the rest of the lines: `DB_DATABASE=ems_l54_a php storage/app/scratch/mutate-lane54-report.php`.
- **After any batch that touches `AccountingController::record()`'s amount, or a money rule in
  `ems:audit:record-states`**, run `DB_DATABASE=<scratch schema> php storage/app/scratch/mutate-lane57-overcollect.php`.
  It plants the two states the lane added — a receipt above the amount the sheet owes, and an enrollment left
  `paid` after its money went — and requires each to fail the audit and name its own label, then swaps HEAD's
  `AccountingController.php` over the working file and drives the same POST through the HTTP kernel, requiring
  the old bytes to accept the money and these to refuse it with the ceiling in the message. The tree is
  restored by hash in a `finally`, because a half-swapped controller makes every later verdict a lie. It refuses
  rather than reporting a pass: on live `ems`, on a schema with no owing enrollment, and on a mutation that
  touched no row. The planter is `storage/app/scratch/lane57-plant-state.php`; the desk act is
  `storage/app/scratch/lane57-overcollect-state.php`, and its `revert` walks the enrollment back through the
  state machine rather than only deleting the receipt — the half-revert is what produced §28.3 G-27.
- **After any batch that adds or edits a suite-size or class-size figure in the documents**, run
  `php storage/app/scratch/lane58-suite-claims.php --junit=<a whole-run junit> --local-suite=<that run's log>` and prove it
  with `php storage/app/scratch/mutate-lane58-suite-claims.php`. §1.1's revision row stated "the suite stands at 771 tests
  and 5191 assertions" and survived three passes of being read, because 771 *is* what both CI jobs print for passed tests
  today — beside an assertion count that came from this box on one earlier day. Measured 2026-10-20, the same build answers
  780 passed / 5,220 assertions here, 9 skipped + 771 passed / 5,013 on CI's SQLite job, the same tests / 5,015 on its MySQL
  job, and 8 tests / 201 in the walkthrough step; `phpunit --list-tests` discovers 780, which is 771 + 9, so the four
  readings are one suite read four ways and **an assertion total is a property of a run, not of the code**. The checker
  classifies before it judges — SUITE, STEP (one folder's own step), CLASS (`SchoolTimeTest`'s 6/33, which the document
  states in three places) or HISTORY (a sentence carrying its own date, or one under a block that declares the day it was
  *run*: "Last Run: September 24, 2026" excuses a figure, a document's own "Last Verified" date does not) — and binds only
  the present-tense three, to `--list-tests`, to junit's per-`<testsuite>` `tests`/`assertions` attributes, and to the
  captured `Tests:` line. **Its first version read 6 claims where the documents hold 15**: one claim per line though a
  flat-dump row is one 23,074-byte line carrying several; only the first pair on a line; digits only, so "seven cases, 173
  assertions" was invisible; a connective word required, so `Pest 274/274 (1,638 assertions)` and `237 passing tests` were
  invisible; every comma figure dropped because `is_numeric("1,638")` is false; and no claim class, so it called
  SchoolTimeTest's *true* 6/33 a stale suite figure while missing three real ones. Loosening it produced the mirror defect —
  22 ratios (`8/8 rendered`, `24/7`, a `2/40` seat counter) read as run totals — so a count is a claim only with an
  assertion figure beside it, or in the words `passing tests`/`tests passed`, or with the suite named before it. What no
  text rule settles is the *surface*: a claim about this box's whole run cannot be measured on the runner, so `--ci` judges only
  what the runner can measure — its own junit and `--list-tests` — and names the rest SKIPPED rather than passing it. CI runs it as
  a step in the **SQLite** job, because that is the suite the document's figures were read from: the same code answers 5,013
  assertions on that job and 5,015 on the MySQL one. Class claims are still judged in `--ci` (a class's totals come from whichever
  junit the runner wrote); only suite totals are surface-gated. Third trap, and the reason the September guides were left alone: counting cases from the
  source (`#[Test]` lines plus `test_` methods) is not a case count — providers expand and one method carries both markers,
  so it was off by 75 on `PermissionMatrixTest` alone and matched 780 at the suite level only by coincidence; a claim that
  instrument cannot reach is reported unverifiable, not false. Fourth trap, met by pass 173 on 2026-10-21: **one surface
  per claim.** "The suite on this box answers 780 tests and 5,220 assertions, and CI answers 5,013 on its SQLite job…" is
  one claim to the checker, and a multi-total sentence is judged on its FIRST figure — green on this box, STALE on the
  runner, which compared 5,220 with its own junit's 5,013. A revision row that must outlive the run it was read from
  names one surface, or states no total at all.
  **The same rule met from the class side, and the checker's own two defects found by reading its blind list.** The matrix
  paired two classes with one figure, naming the accounting queue first and putting the assessment queue's own case count
  and assertion count beside both; the delivered run answers ten and thirty-eight for the first, nine and thirty-five for
  the second, so the sentence was false about one of them while lane 58 judged only its first figure — which is why that
  blind spot is now a row, not a number. Three readings came from opening it.
  **(a)** The footer tallied multi-total sentences and printed none, the lane 68 defect inside my own instrument, and its
  comment even claimed the blind spot was printed. **(b)** The counter was a second definition of the same thing as the
  rows: the matrix is in the live list AND matches the dated glob, so one document walked twice and inflated the tally —
  the tally is now `count()` of the de-duplicated rows, the same convention the claims themselves already use.
  **(c)** The harness's ground-truth leg read a hand-kept junit filename frozen at an earlier day's capture, so it judged
  today's sentences against a run ten days old and passed because it asserted only "three or more" class claims while
  its own name promised every one binds. It now takes the NEWEST `*-junit.xml` in scratch, refuses unless that junit's
  root `tests` equals the `Tests:` line of the log named beside it (a pair must be proved one run before it stands as
  ground truth), and requires the checker's exit code 0. Prove any change to the print path with the two new legs: a
  sentence carrying two true pairs must print the row (document, line, the figure it did not judge), and the same bytes
  split into two sentences must print no blind spot at all — a row that cannot disappear is decoration.
- **After any batch that edits a figure one of these tools prints** — a rule count, a citation total, a tile or cell count,
  a blind-spot tally — run `php storage/app/scratch/lane59-collect-readings.php` and then
  `php storage/app/scratch/lane59-instrument-counts.php --readings=storage/app/scratch/lane59-readings.json`, and prove it with
  `php storage/app/scratch/mutate-lane59-instrument-counts.php`. Nine were stale on 2026-10-20, eight of them in this file: the citation
  sweep's `559 … over 115 files` where it prints 602 over 141, lane 37's `851` and `96 finding rows` where it prints 860 and 108,
  lane 37b's `243` for 271, lane 32's `107` and `665` for 113 and 9, and a pair in §28.3's G-18 row (`18 guards … 355 lines` where
  lane 49 prints 19 and 340). What makes a figure checkable is not its noun but **the tool named in the same sentence**: a
  number-and-noun scan of these documents returns 109 hits and most are not readings at all (`term 18`, `21 DATE columns, 18 of them
  cast`, `§18.3's column list`, `documentprintlog carries eight columns`), so the checker binds a figure only where it cites a tool from
  its table and that tool's own summary line answers it, and a labelled figure whose tool printed nothing is UNBOUND and refuses.
  Labels match on the **phrase** after the number, longest pattern first — `16 school-moment cells` and `26 day cells` both end in
  "cells", and matching the last noun alone compares 16 against 26 and reports a true sentence as the defect. Readings are addressed
  **by name, not position**: lane 49 prints its non-figure count before its unread count, and one positional regex read that tool and
  lane 50's as printing nothing at all, which would then have "proved" a mutant against a null reading. Three distinctions it will not
  collapse: a tool that ran and printed nothing is UNBOUND while a tool `--only` leaves out is *named* as unmeasured; a past-tense
  sentence ("its first version judged 27 bindings") is a record of a defect, not a stale figure, so `corrected`, `then`, `on that date`
  are history markers and a mutant must flip the verdict when the same number is put into the present; and a §1.1 revision row whose
  first cell is a bare date (`2026-10-11–15 |`) is that day's record, while the row reading "(this issue)" is a living claim — lane 58's
  finding applied to this class. One trap bit twice: the row's en dash is three bytes, so the date-cell regex needs `/u`, and without it
  §1.1's historic "twelve rules" read as undated and the checker refused a figure the document never asserted about today.
- **After any batch that edits a figure about the SCHEMA or a ratio over rows**, run
  `php storage/app/scratch/lane60-ratio-claims.php --run` and prove it with
  `php storage/app/scratch/mutate-lane60-ratio-claims.php`. This is the third prose family: not the suite totals (lane 58) and
  not the counts a tool prints (lane 59), but "62 tables (54 EMS domain + 8 framework), 418 columns, 86 foreign-key
  constraints, 175 indexes" — a 2026-10-04 reading restated as the present schema while §23.8's own cross-check on the same
  subject said 63, and **one of its numbers never named what it counted**: 175 is `information_schema.TABLE_CONSTRAINTS`, the
  schema holds 129 indexes. Live `ems` answered this on 2026-10-26, before the G-3 crediting columns were added: 63 tables (55 domain + 8 framework from the bundled `0001_` migrations),
  452 columns, 100 foreign keys, 4 CASCADE / 26 SET NULL / 1 RESTRICT / 69 NO ACTION, and §24's 63 per-table "N columns"
  headings all agree with the server column for column. Two rules the measurement taught. *Derive the partition, never
  recall it*: a hardcoded list of eight framework tables answered "54", while the eight the server holds are the seven
  `0001_` migrations create plus `migrations`, which the framework writes itself — and reading only `Schema::create` also
  flags `studentids`, created 2026_08_02 and dropped 2026_09_22, as a missing table unless `dropIfExists` is read from the
  same `up()` half (split on `public function down()`, because every create migration carries a drop in its rollback).
  *A figure that matches no reading is a different finding from a stale one*: before calling 175 wrong, ask what else counts
  175, and say which noun it was. CI runs the schema half on `ems_test` (`--ci`), because those figures are properties of the
  migrations and a divergence is a real defect; row counts — `42 assignments over 17 roles`, `gradescale has 4 rows`, the
  `settings` rows — are named and not judged there, since they belong to whichever database stated them. The probe refuses a
  schema it was not told to read: `--allow-schema=<name>` is how CI says so on the command line.
  **The checker itself now refuses a flag it does not read** (repaired 2026-10-30, six weeks after it was measured and
  left as a note): `--verify` — lane 64's flag — and a made-up flag each ran the whole judgement and exited 0, because
  the argument chain had no `else`. A run that ignores its own scope prints exactly the verdict a scoped one does, so
  `mutate-lane60-ratio-claims.php` grew four legs: three refuse `--verify`, `--verify=docx` and
  `--no-such-flag-at-all` **by name**, and the fourth requires `--trace` still to run clean — a guard that refused
  everything would satisfy the first three and break the tool. CI's own shape
  (`--ci --run --allow-schema=ems_test`) was re-read against the known list before this shipped.
- **After any batch that edits a sentence about whether a finding is still open**, run
  `php storage/app/scratch/lane61-open-claims.php` and prove it with
  `php storage/app/scratch/mutate-lane61-open-claims.php`. This is the fourth prose family — not the suite totals
  (lane 58), not the counts a tool prints (lane 59), not the schema census (lane 60), but the **status word**:
  §28.7 put nine items in "the order these should be taken in" while every one of those ids' own register row began
  `Closed 2026-10-0…`; §28.8 counted 46 owed slots over six actors from a "Who must act" column that 8 of 57 rows
  still fills with an actor; §30.6 named C-11, D-1, X-3 and G-2 as four open items, all four ruled, closed or
  contained in their own rows; §30.5 called the settings table empty while it holds 3 rows; and §25.5 said that table
  "has no effect on any printed document" twenty days after `29b5532` registered `SettingsServiceProvider.php:20-24`,
  which is the thing that carries `settings` into `config('settings.*')`. The falsification for that one was to boot
  the app and read `config('settings.schoolName')`: it answers the table's title-case value, not the upper-case
  literal the template passes as its fallback — a discriminator the case spelling makes unmissable. No existing
  sweep could see any of it, because each pointer lands on a live line, each §-reference resolves, and lane 60 had
  just corrected the figures beside those very sentences. Three rules keep the checker honest, each learned in an
  earlier lane: the verdict is the cell that LEADS with a verb and, when any cell also carries a date, that one wins
  (a gap's TITLE can begin "Open a second signature…"); an id is judged only inside the sentence holding the
  open-words, so "a request still open under a box its own office signed" cannot be charged to G-2 eleven sentences
  later; and a sentence that states its own answer, or sits in a §1.1 row whose first cell is a bare date, is a
  record. First run: 12 contradictions over 11 ids; the harness has 13 legs and five of them require silence.
  Two traps this lane paid for. **A register row you add moves every count of the register, including the sentence
  that justifies adding it**: pass 173 wrote "56 register rows" into §28.8 and into G-29, then appended row 57, and
  pass 173c had to correct both — the lane's own defect, reproduced inside its fix. And **a claim whose anchor
  sentence you rewrite goes unscheduled, not stale**: lane 60 answered `UNBOUND settings-table` because pass 172 had
  reworded the sentence its regex read, which is exactly why that checker refuses instead of reporting a clean count.
- **After any batch that edits a sentence saying a printed document was verified**, run
  `php storage/app/scratch/lane62-print-paper-probe.php` and read its table. This is the fifth prose family: not a
  figure, not a status word, but a **claim about an artefact** — §28.6's row 5 said the clearance slip and the
  enrollment form "both print the schoolIdNumber they already hold, verified by reprinting and reading the paper".
  Extracting the text of the PDFs `ems:print-fidelity` renders from live `ems` shows the slip prints its own subject's
  ID (`DEMO-2026-018`) and the certificate and subject-load print `DEMO-2026-017`, while the enrollment form carries
  **no ID token at all** and no school year either — so P-2, the register's only live engineering item, was true and
  the sentence claiming it had been checked on paper was not. Two traps in the first ten minutes of using it:
  `exec()` runs cmd.exe, so a Git-Bash path like `/mingw64/bin/pdftotext` is invisible and the probe must name the
  `.exe`; and the fidelity set renders a **different student per document**, so "this record's ID is absent" is not
  the same claim as "no ID reaches this paper" — the probe prints both columns because conflating them invents a
  defect. The record is read out of the paper (the certificate states its own subject) rather than guessed from the
  database, and a probe that cannot find a subject refuses instead of reporting an empty table.

  The same lane closed P-7, and the closure says something about how a finding ages: the row waited on the Registrar
  for twenty days because `SettingsSeeder.php` declared the campus address and the landline to be "institutional facts
  this repository does not record" — while `Documentation/SEAIT_Official_Information.md` §1 has carried both since it
  entered the tree on 2026-08-28, **34 days before that seeder was written**. The blocker was a belief in a comment, so
  the first action was to read the repository, not to ask the school. **After any batch that touches `SettingsSeeder`,
  the three letterhead keys, or a print view's `config('settings.…')` calls**, run
  `php storage/app/scratch/mutate-lane62-letterhead-tests.php` — a green control plus four planted
  defects (a blank address, an invented address, a renamed key, a template reading a key no row supplies), each of
  which must kill the named test, and which refuses its whole run when the control is not green or a needle matches
  nothing. `Tests\Feature\Print\PrintTemplateSelfContainmentTest` needs `RefreshDatabase` for this: the class asserted
  nothing about the database before, and `settings` does not exist in an un-migrated `:memory:` schema. And its
  `--filter` is the **method name**, not the sentence Pest prints for it — filtering on
  `the letterhead settings are seeded` selects no test, and a harness that reads "No tests found" as a green leg is
  the lane 44 mutant that sleeps.
  **After any batch that touches `PrintService::printEnrollmentForm()`, `ems:print-fidelity`'s render closures, or
  the enrollment form's identification block, Part 2 table or address block**, run
  `php artisan test tests/Feature/Print/PrintEnrollmentFormPayloadTest.php` and prove it with
  `php storage/app/scratch/mutate-p2-payload-tests.php`. The desk path and the fidelity command each write their own
  eager-load list — nothing shares them — so a relation dropped from one is a line the Registrar's paper loses while
  the other keeps reporting 8/8 rendered. P-2 was closed on 2026-10-23 by building that block (Student ID, Course,
  Year Level, School Year, Semester) from the school's own Subject Load wording rather than from a blank form, which
  the owner says will not be supplied; the class now pins the printed body, not only the payload. **A rendered
  document's `<title>` element is not paper** — it has carried the student number the whole time, so the case reads
  the HTML from `<body>` onward, and the harness's fifth mutant deletes the Student ID field while the title still
  prints it, which is the only way to prove that distinction.
  **P-18 is what a line-by-line review of that sheet found the next hour** — three defects, each of which survived a
  green suite because nothing renders the paper: Part 2 looped `$enrollment->enrolledSubjects` whole and totalled it,
  while the Subject Load has always filtered to `Confirmed` (live `ems` holds 33 proposed and 21 dropped rows across
  18 enrollments, and **every one of those 18 holds no confirmed row at all**, so each printed a whole table for a
  load nobody confirmed); the "Same as above (Current Address)" line compared `addressType` — an enum cast — with
  `===` to a string, so it was false for every address and **had never printed once**; and the issuance number was
  minted by `recordIssue()` *after* `generatePdf()` and printed nowhere, so the log row had no sheet to match it —
  `printClearanceSlip` mints first and says why. All three fixed, six new cases, the harness grown to eleven legs.
  **A dead `@if` is the quietest defect on a print surface**: an `@isset` or an enum-to-string `===` renders nothing
  and fails nothing, so the check for a conditional block is a test that asserts the block's *label* reaches the body.
  Two things the review recorded rather than fixed, both letterhead questions for the school: the form is the only
  paper printing `students.semestersCompleted` and `yearsInInstitution`, which intake zeroes and one controller
  maintains (§30.4), and it carries no OFFICE OF THE REGISTRAR line where the class card and the Subject Load do.
  Two findings came out of reading that sheet rather than probing the code. **P-16**: the paper printed
  "Southeast Asian Institute of Technology" while the sheet, `GuestLayout.jsx:50`, `GuestLayout.jsx:68`, four
  Documentation markdown files and this document's own title line carried "South East Asian Institute of Technology,
  Inc." — so the login screen and the letterhead named the school differently, and no instrument could see it because
  nothing in the repository asked whether the two spellings agree. Ruled 2026-10-23 in the sheet's favour, and the
  school's own stationery in the reference scans carries the same two-word form, so the ruling's evidence is the
  paper rather than a comment. **The scans moved: they are at the repository root in `Images/`, not
  `Documentation/Images`, and `.gitignore` now names both** — a folder of real signed Registrar forms sat untracked
  and un-ignored on a public remote, which is the only kind of finding that cannot be recovered by a later pass.
  One letterhead value still has two answers in the sheet it comes from: `SEAIT_Official_Information.md` §1 lists the
  Registrar landline as "(083) 226-1602 / 226-1203", the settings row carries the first and the stationery the second.
  **The mirror defect of lane 61**: closing P-7 made §30.6's opening sentence ("Nothing in this section waits on an
  institutional
  answer") false, because P-16 does. `lane61-open-claims.php` catches a sentence presenting an *answered* id as
  undecided; it cannot catch a sentence presenting an *open* id as settled, because there is no contradiction with the
  register — the paragraph has to be re-read whenever a row is opened, which pass 176b did, and whenever one is
  *answered*, which pass 181 did for §30.6 and §28.4's group row.
  And the print register now behaves like §28's: **one §25.4 row moved nine places** — the four that state the range
  `P-1 … P-15` (§25's intro, §28.4's index row, §28.4's lead sentence, §29.4's family row), §29.4's count cell, the
  two cells of the §28.4 group row it is filed under, §28.8's tally of print findings outside the register, and
  §30.5's cross-reference — plus G-29's record of the pass that corrected the letterhead, which had to be reworded to
  "the two rows that were blank when it was written". Pass 176's clone guard reads the ids out of the staged bytes
  (`P-1 … P-16`, once each, in order) because §25.4 is written as two adjacent `<w:tbl>` elements, so one table's row
  count is not the register's size.
- **After any batch that drops a column, or edits a §24 per-table column count**, run
  `php storage/app/scratch/lane60-ratio-claims.php --run` and read every line, then re-count the
  migrations the September demo snapshot predates: `ls database/migrations | awk '$0 > "2026_09_20_1000000000_add_schedule_status_to_blocks_table.php"' | wc -l`.
  Lane 63 ruled four register items on 2026-10-23 and one of them was a schema change:
  `studentclearances.extendedDeadline` was nullable, `$fillable` and `date`-cast, and named by no code
  outside the model — so §27.2's E-8 row carried it as the *remainder* of an answered question for
  twenty days while §27.5 listed the column as vocabulary the schema carries and nobody uses. The
  owner ruled DROP rather than wire it, because an extension is the window's state and a per-student
  date would have needed a rule the school has not stated. Three consequences travelled together and
  only the checkers would have caught two of them: the schema census moved 452 → 451, §24's
  `studentclearances — 9 columns` heading moved to 8 and its column-aggregate cross-check row from 410
  to 409, and both demo-day guides' "the 19 migrations the snapshot predates" became 20. **The empty
  claim was measured off the pre-drop dump, not off the live schema** — after `artisan migrate` the
  column cannot answer `COUNT(extendedDeadline)` at all, and a guard that asks a dropped column a
  question gets an exception, not a verdict.
  Two prose lessons from the same batch. **A dated row must not be re-aged to a newer figure:**
  pass 179 moved "(this issue)" onto a new §1.1 row and lane 60 still judged the 2026-10-21 row's 452
  as a living claim, because that checker has no "(this issue)" rule. Rewriting the number inside a
  dated row would make the row state a figure that was not true on its own day, so the sentence was
  given its own date instead — `as of 2026-10-21` is in `lane60-ratio-claims.php`'s `$DATED` list and
  the figure becomes HISTORY, judged by nothing and false about nothing. **One name per range:** a
  clause that put `Payments::held()` immediately before the document's `AssessmentController.php` lines 123 to
  127 (quoted as prose: that pointer has since moved, and this sentence records the wording, not a live line), a
  range belonging to the method before it, added one lane 32 flag purely from its own wording, and
  re-spacing the clause cleared it again.
  Fourteen items that the registers genuinely cannot settle were packaged for one meeting in
  `Documentation/School-Decision-Sheet-2026-10-23.md`, read out of the eight §28.2/§28.3 rows whose
  "Who must act" column still names an office, the five §28.5 rows whose answer ends in a question,
  and the two §25.4 print items (the name's spelling, and the identity the enrollment form did not
  print). Four of the fifteen came back the day it was issued — the three print items and the proxy row,
  answered "not yet deployed" — and the sheet now marks each ANSWERED where it was asked, because a
  packet that only ever grows a tick box reads as still outstanding. Two of the
  sheet's own claims were measured before they were written rather than copied from the register:
  live `ems` answers 0 of 16 programs with `entranceExamExemptsTransferee` set and 5 with
  `requiresCourseSpecificExam`. **Read the rows a checker prints as blind spots, don't clear them by
  count**: lane 61's one remaining blind spot was pre-existing and explained — §6.1's `R-1 … R-3`
  rows hold their verdict but their id cell reads `R-2 Admission dependency`, so a run whose row pattern
  demands the id alone never indexes them, and their status cell says `Enforced`, a word the checker did not
  treat as settling. Lane 70 closed it rather than leaving it explained: the row pattern now accepts a short
  label after the id, `Enforced` joined the settling verbs (89 rows indexed, up from 86), and the pairing rule
  requires the open-words to PREDICATE the id — within 90 characters — because that examination paragraph says a
  *record* is undecided and signs itself `(R-2)` two hundred characters later, which is candidate-list logic, not
  a question the register owes. `mutate-lane61-open-claims.php` carries two new legs for exactly that boundary:
  one plants the far id and requires silence, the other pads a long sentence and requires the same id to still be
  caught. The queue is now empty of blind spots: 0 contradictions, 0 unverifiable ids.
  **A closed register row can still carry an open branch.** P-17 came from reading a
  model method, not from a probe: the print register's P-10 row states as settled the rule that a
  clearance slip names the enrollment of its period's own term, and its consequence cell said nothing
  remained for the derivation — while `Studentclearances::termEnrollment()` fell back to the student's
  newest enrollment by id when that term held none, under a comment asking the Registrar which
  enrollment the paper should name. No checker could see it: lane 61 catches a sentence presenting an
  *answered* id as undecided, and this is an open branch inside a closed row; the citation sweeps pass
  because every pointer lands on a live line; and live `ems` answers four clearances, every one of
  which does hold an enrollment in its period's term, so no rehearsal on this box could reach the
  branch. Ruled 2026-10-23: a clearance is issued when the semester ends, for the enrollment of that
  term, so the slip names **nothing** when the period's term holds none — the three print paths already
  rendered the absence (`clearance-slip.blade.php:69` prints `N/A`, `PrintSlip.jsx:33` an em dash, both
  print-log calls key the issuance on the student), and the remedy for such a slip is the right period.
  The ruling's second half — the system keeps every student who has completed a clearance — was
  **measured before it was written down**: `uq_student_clearanceperiod` on (studentId,
  clearancePeriodId), four `NO ACTION` foreign keys around the table, no delete path anywhere in `app/`
  or `routes/`, and `generateSlip` refusing to re-issue over a clearance a desk has answered.
  Both sides are now pinned by `tests/Feature/Print/ClearanceSlipTermEnrollmentTest.php`, and
  **after any batch that touches `termEnrollment()` or the three slip print paths** run that file and
  prove it with `php storage/app/scratch/mutate-p17-term-enrollment-tests.php`, which kills seven defects
  (the ruled-against fallback re-appearing, the term filter dropped, the template re-deriving the answer
  inline, a delete written across two lines, a delete through a loaded model, the refusal that protects an
  answered clearance widened, and the ruling marker renamed) and refuses when the control is not green.
  **A guard that keys on one spelling of a defect sleeps through the other three:** the delete scan first
  matched a single-line `Studentclearances::where(...)->delete()` only, so the harness plants the
  multi-line statement and the `$clearance->delete()` variable form as separate cases. And when a ruling
  turns a `PROVISIONAL` marker into a `Ruled …` marker, every mutant that anchored on the old marker
  becomes a mutant that cannot apply — the harness was rewritten the same day, not extended.
  Adding the row moved ten places — four
  range statements, the family count cell, the group row's ids and its settled tally, the
  outside-the-register tally, the cross-reference, both copies of the open-item sentence, and the
  living revision row — and its finding moved the instrument figures in this file again, because the
  register is what those tools count. **Closing a row moves fewer places than opening one**: pass 181
  changed no range, no family count and no register size, because the seventeen rows stayed seventeen —
  only verdict words, the §28.4 group row's settled tally, §28.8's four-findings sentence and §30's two
  cross-references moved. §28.8's sentence had been left stating four and naming three when P-17 was
  opened: the figure grew and the list did not, which is lane 64's membership defect in prose.
- **A section's MEMBERSHIP goes stale even while its rows are being re-measured** (lane 64). The data
  dictionary's list of tables that are empty in the demo database carried five wrong rows on live
  `ems`: `gradescale` (4 rows), `scholarshiptypes` (2), `studentscholarships` (1), `schedulemeetings`
  (18 — its own cell said 18 while the row sat in the empty list) and `settings` (3, the very rows P-7
  closed). The `gradescale` row contradicted §24's own entry two lines above it, which states "Rows in
  the demo database: 4". No instrument covers the shape: lane 60 binds the per-table row counts and
  every one of those was correct, the line sweep checks pointers, lane 37 checks §-references, and a
  contradiction between a listing and an entry is not a contradiction any of them can see. Pass 179g
  rewrote the five rows to say what the dataset does not *exercise* rather than that the table is
  empty — and the sentence it wrote carried two more membership claims that were themselves wrong: it
  said "the last six of them framework tables" of an alphabetical list whose last six include two
  domain tables, §24.18's population row still counted 8 domain empties where `COUNT(*)` answers 4, and
  §28.1's identifier index still said the G family runs `G-1 … G-8`, eleven rows short of its own end.
  **A stated range that stopped growing is the same defect as a stale row**: nothing re-opens the index
  row when a register gains a row, so pass 180 named the framework six instead of pointing at their
  position, moved §24.18 to 4, gave `shiftingrequests` the row §24.17's own title promised, and opened
  §28.3 G-30 to record the family. The durable fix is the checker:
  `php storage/app/scratch/lane64-empty-table-inventory.php --verify` exits 1 unless the intro's three
  sets (all-`COUNT(*)`-zero, the framework subset it now NAMES, the domain subset), the section's rows
  and every other domain-empty count in the document equal what the server answers — it refuses a
  `--dump=` without `--verify`, refuses a dump older than the docx, refuses a schema it does not
  recognise, and locates the section by its heading because a checker anchored on a generated line
  ordinal is a checker that sleeps. Prove any change to it with
  `php storage/app/scratch/mutate-lane64-empty-tables.php`: a green control, seven planted set-defects
  (drop a member, add a table that holds rows, un-name the parenthetical, move the count, drop a domain
  member, move §24.18's figure, rename a row's *table reference* not its label), each of which must make
  the named complaint appear, and a case that reports BAD when its needle matches nothing. It is not a CI
  step: an emptiness set belongs to whichever demo dataset stated it — lane 60's rule for row counts —
  and CI's `ems_test` is seeded differently. **`information_schema.TABLES.TABLE_ROWS` is
  not the measure**: it is an InnoDB estimate and reports `clearanceperiods` as 0 while every
  clearance row joins to it, so emptiness is `COUNT(*)` or nothing.
- **After any batch that states or edits a peso figure in the documents**, run
  `php storage/app/scratch/lane65-peso-claims.php --verify` and prove it with
  `php storage/app/scratch/mutate-lane65-peso-claims.php`. This is the sixth prose family: the money the
  document writes down. 50 `₱` tokens sit in the flat dump — 15 bind to a `feetypes` row, to an account the
  server holds, or to an **arithmetic triple read out of the sentence itself** ("₱8,000.00 held against a
  ₱18,500.00 sheet leaves ₱10,500.00" must both add up and match a real assessment's assessed/held/owing),
  20 are carried by a past-tense or dated sentence, 13 are hypothetical ("a school that charges
  ₱150"), and 2 are printed as blind spots rather than cleared: §18's "₱100 default" gloss beside a code range,
  and the `₱0.01` validation minimum, which is a rule in a request, not a row. Existence alone would not catch
  a broken account sentence — ₱12,000.00 is a receipt amount the server really holds — so the harness mutates
  "leaves ₱10,500.00" into it and requires the arithmetic, not the lookup, to refuse. It found no defect — every bound
  figure equals the catalogue (Tuition 1,250 perUnit, Miscellaneous 1,500 flat, Laboratory 500 perUnit, Library
  250 flat, Clearance Slip Replacement 100.00, which `StarterReferenceDataSeeder.php:68-71` and
  `DevReferenceDataSeeder.php:715` each create) — and that is the outcome to expect from a document whose money
  has already been weighed by lane 53; the instrument exists so the next edit cannot silently drift. Three bugs
  it paid for, each of which produced a *clean-looking* verdict before it was fixed: `preg_match_all`
  answers the **count**, so guarding with `!== 1` skipped every table row carrying more than one figure and left
  the run binding 3 of 50 — the `bound === 0` refusal fired only after the fix, which is the guard earning its
  keep; and pairing a fee name to "the next ₱ within 6 characters" accused the document of writing
  "Miscellaneous ₱1,250", because the row lists four fees in order and each name grabbed its neighbour's amount.
  A fee claim is bound only when the name is adjacent to *that* figure: `name\s*₱amount`. Third trap: **Pint
  broke a working script and `pint --test` approved it** — `fully_qualified_strict_types` shortened the bootstrap
  `Illuminate\Contracts\Console\Kernel::class` to `Kernel::class` and filed its own `use` block *below* the call,
  so the file parsed, formatted clean, and fatalled at runtime with "Class Kernel does not exist". The imports now
  sit above the bootstrap with a comment saying why, and the harness's rule that a red control refuses the whole
  run is what turned a silent breakage into an error message. Run the script, not just `pint --test`, after any
  formatting pass over a scratch file that boots the framework.
- **That trap is now swept, not remembered.** `php storage/app/scratch/lane73b-probe-import-order.php` walks every
  file under `storage/app/scratch` and `tools` with `token_get_all` and names each one whose import sits **below** the
  first line of code that uses its short name — the shape Pint's `fully_qualified_strict_types` creates, because PHP
  resolves an unqualified name at compile time, in statement order. Two tracked files were dying and nothing knew:
  `lane73-packet-evidence.php` (it is named in a gate bullet below, so it had been *formatted* since it broke but not
  *run*) and `curriculum_facts.php`, broken the day it was written; `probe-use-ordering.php` is the file that
  established the rule and places an alias late on purpose, so the sweep prints it in its own `BY-DESIGN` bucket
  rather than skipping it silently. Its first two drafts are recorded in its header, and both are this file's known
  class: a raw-line grep answered 16 files, fifteen of them **prose in a docblock**; and `[$a, $b] = $sweep(…)` over
  an **associative** array answered null for both halves, warned on every file it read, and still printed a clean
  verdict over 867 — caught only because the run was checked against a **positive control** (HEAD's copy of the
  broken file, which must come back named). A sweep with no planted positive is a sweep that can only say "clean".
  **It caught a third victim the same day.** A batch that only meant to add a sentence to
  `lane72-probe-school-day.php` ran Pint over it, Pint applied `fully_qualified_strict_types`, and the file fataled
  "Class Kernel does not exist" on its next run — the class is not rare, it is recurring, so the sweep is now proved
  rather than remembered: `php storage/app/scratch/mutate-lane73b-import-order.php` (7 legs) plants the late import in
  a copy and requires `LATE-IMPORT` plus **exit 1 in `--ci` and exit 0 in hand mode** (the two modes are different
  claims and a leg that accepted either would pass on a broken instrument), requires silence on the sweep's own two
  first-draft errors — docblock prose and a closure's `use ($a, $b)` binding list — and refuses on a missing `--file=`.
  **Two defects in the sweep came out of that harness, both this file has named before:** its `--file=` refusal used
  `exit("REFUSED: …")`, which returns **status 0**, so CI would have read a refusal as a pass; and `--dir=` was
  accepted and ignored — a flag that looks like it scopes the walk while the walk never sees it. The tool now refuses
  any flag it does not read, by name. Third harness lesson: the copies live in `storage/app/scratch`, which is the
  directory every later leg walks, so the clean control must run **before** anything is planted — a harness whose
  planted defect poisons its own next measurement reported five failures before the ordering was fixed.
- **After any batch that re-issues a sheet an office ticks, or touches the preflight's forwarded-scheme row,
  `config/trustedproxy.php`, or the board-program flags**, run
  `php storage/app/scratch/lane73-packet-evidence.php` and read every line, then
  `php storage/app/scratch/mutate-lane73-preflight-proxy.php` (14 legs). Lane 73 is the lane that finishes the items
  the registers cannot: `Documentation/Registrar-Window-Packet-2026-10-26.md` gathers decision-sheet items 1, 2, 4, 5,
  the department half of C-5, the install-time half of G-13, and the fidelity sheet's unticked boxes, **each with what
  the answer costs and how it comes back off** — office lines, board flags and fee
  amounts are Reference Data on the desk screens (the undo is the same click), a wording change is one template file
  in its own commit (`git revert`), and nothing in the packet asks for a schema change. Three rules it had to honour.
  *The packet quotes a command, never the previous sheet* — a packet that re-copies itself is how an answered question
  gets asked again, so it prints `9 clearancerequirements lines`, `16 courses / 5 flagged / 2 entrance / 0 exempting`,
  `Clearance Slip Replacement ₱100.00 flat (feeTypeId 5)` and `trustedproxy.proxies = null`, all from
  `lane73-packet-evidence.php`, whose column names come from `information_schema` rather than from memory, and which
  **refuses the run when a surface answers nothing**. *A new sheet is unjudged until a checker is told it exists*:
  `repo-file-line-citations.php` reads an explicit list of the markdown files an office holds, so the packet's four
  `File.php:NN` pointers sat outside every gate until it was added; the C-5 department page added on 2026-10-26 is in
  that list too, and this file states no citation total for it because the tool reads this file.
  *G-13 got a check, then got a setting*: `ems:preflight` prints a `Forwarded scheme` row that says OK when nothing is
  trusted and `APP_URL` is http, **WARN when an https `APP_URL` sits beside no trusted address** (that is the silent
  HSTS shape, and its sentence now carries the second half of the act — set the value, then re-run
  `php artisan config:cache`, because a cached install never reads `.env`), OK with a count when addresses are named,
  and WARN for the five spellings that look filled in and install nothing. Ruled 2026-10-26 by the owner — *an env
  setting, not a code edit*: `config/trustedproxy.php` answers `env('TRUSTED_PROXIES') ?: null`, and no
  `bootstrap/app.php` change is needed, because `Illuminate\Http\Middleware\TrustProxies` is already in the global
  stack (`vendor/laravel/framework/src/Illuminate/Foundation/Configuration/Middleware.php:457`) and falls back to that
  key at request time (`vendor/laravel/framework/src/Illuminate/Http/Middleware/TrustProxies.php:69`), splitting a
  comma string at `vendor/laravel/framework/src/Illuminate/Http/Middleware/TrustProxies.php:87-88`. The row normalises the value the
  way the middleware does — reading only an array called a correct one-line `TRUSTED_PROXIES=10.0.0.5` "nothing is
  trusted" — and refuses `*` (the catchall, so any client forges its scheme and address while
  `LoginRequest::throttleKey()` and `auditlogs.ipAddress` read `$request->ip()`), a `*` inside a list, a hostname, a
  CIDR mask past its family, and the range keywords `PRIVATE_SUBNETS` / `REMOTE_ADDR`. **Every one of those verdicts
  was measured through the real middleware before the row was written to describe it**
  (`php storage/app/scratch/lane73b-probe-proxy-shapes.php`, which prints effect and promise side by side for twelve
  spellings and refuses its own run if a named address does not reach the request). Two findings it produced: a blank
  `TRUSTED_PROXIES=` arrives as `''`, not null (so the config file's `?: null` is load-bearing), and Symfony's
  `PRIVATE_SUBNETS` list includes the three documentation ranges, so a probe must use a genuinely public address as
  its stranger or the instrument hides the defect. It is WARN, never FAIL: a preflight cannot see a proxy that has not
  been built, and nothing stored moves. Pinned by `tests/Feature/Console/PreflightTest.php`
  (`the_forwarded_scheme_row_decides_the_proxy_question_instead_of_asking_the_installer_to_remember_it`, 23 assertions,
  plus `the_trusted_proxy_config_file_answers_the_env_var_and_a_blank_one_means_no_proxy`, which re-reads the shipped
  file the way the zone case does) and by the pair now in `tests/Feature/SecurityHeadersTest.php` — naming the peer
  address makes HSTS appear, naming a different address does not, which is what turns the older
  "a proxy forwarding https does not by itself enable HSTS" case from an absence into a measurement. Proved by the
  harness, which plants eleven ways the setting could stop meaning anything — a comma string no longer read, a bare
  star counted as an address, an unmatchable value counted, a range keyword believed, any CIDR mask accepted, the
  cached-config sentence dropped, the env var named nowhere, the row renamed, the row never printed, the file reading
  a differently-named variable, and a blank becoming the empty pattern — restores **both** files by hash in a
  `finally`, requires the case that owns each defect to print `1 failed` rather than merely "not passed" (a mutant
  that does not compile is not a killed defect), and **reports BAD rather than passing when a planted needle matches
  nothing**: that is what two of its first legs were in the previous version, because `\\$proxies` inside a
  double-quoted needle is a literal backslash plus `$proxies`, which no byte of the target contains. Two harness
  lessons from rebuilding it: `--filter` cannot carry a `|` alternation through cmd.exe (the run answered nothing and
  the tool read that as a failed control), and Pest writes ANSI colour codes into captured output, which breaks the
  tally regex and any needle spanning a styled word — strip `/\e\[[0-9;]*m/` before matching, or the harness's verdict
  depends on whether the caller's shell had a `TERM`.
  **The packet's own numbers need a guard, because a dated page rots silently.**
  `php storage/app/scratch/lane73-packet-claims.php` binds all **23** claims on the page to live `ems` — the four
  course counts, each board row's two flags, every office line's exact `requirementName`, the fee's amount, each
  `(columns \`…\`)` list against its own table, and the proxy state — and
  `php storage/app/scratch/mutate-lane73-packet-claims.php` (15 legs) plants one defect per family. Three of its own
  bugs came out of that harness, each of them a class this file already names: `(int) $r[2] === 'yes'` is **always
  false** (the cast happens first, so `0 === 'yes'`), which reported three true board rows as stale claims; a
  `columns \`…\`` pattern with no table anchor grabbed the **first** list on the page and judged the clearance
  columns against `feetypes` — the wrong-target defect reached from my own instrument, now fixed by binding each
  list to the table named before it and asserting the stale line names that table; and a shape the parser could no
  longer read printed a `STALE` row, which let the run reach a verdict while having read nothing and made the
  "judged nothing" refusal unreachable — an unreadable page is now a **REFUSED**, never a claim. The guard is not a
  CI step for lane 60's reason: those figures belong to the dataset the packet was read against. If it goes red,
  re-issue the packet from `lane73-packet-evidence.php`; do not retype figures by hand.
  **Writing the packet found a wrong statement in the packet, from the two classes this file already names.** The
  fidelity sheet asks nine boxes and one of them is `2.1–2.9` — a *group*, whose row 2.6 (the weekday printed in
  both boxes) was ruled and pinned on 2026-10-11. Copying the group forward therefore presented a closed row as
  owed work, which is lane 64's membership defect in a new document; the packet now splits it (2.1–2.5, 2.6 CLOSED,
  2.7–2.9) and says so in its own header. And two cells of that 2026-10-10 sheet state the system's wording the
  build has since moved — the card and the load both print `App\Support\YearLevel::format()` now, so they read
  `- 1st Year` / `- 3rd Year` where the sheet recorded `- Year 1` / `- Year 3`. The sheet is **not** re-aged; the
  packet states today's wording beside the row. So: when a dated document is used as a checklist, read its rows
  against the build first, and split every grouped row — a range in a tick box is a membership claim.
  **Ruling the four rows left one false sentence behind, and it was the sheet's claim about itself.** The answers came
  back on 2026-10-26 (item 1: all nine lines stay on the student's page; 2: the slip is accepted exactly as it prints;
  4: the desk print is the paper, because it is the render that mints its Document No. and writes its own
  `documentprintlog` row; 5: as built — a lost slip is paid, at the `feetypes` amount). Every item heading gained its
  `ANSWERED` marker and each struck-through tick line records the answer, but the header still read
  `twelve answered, four left for the Registrar's window` — and `lane69-open-inventory.php`'s own cross-check refused:
  `open on the sheet: 0` against a header claiming four. That header is the sentence a reader takes to the meeting, and
  the classifier had already learned to read it (`0 OPEN` is what it prints) while the prose had not followed. It now
  reads `16 answered, 0 left`, and the cross-check says AGREES. Two rules from the same cascade: *a ruling word must
  reach the document's own tally, not only the row it decides* (lane 61's register-row lesson, met one document
  sideways), and *the sheet's header must be spelled so the classifier can read it* — the tool maps words
  (`twelve`, `four`) and digits alike, so `sixteen answered, none left` would have silently switched the cross-check
  off, which is the lane 50 defect: an instrument that stops judging looks like the cleanest line in the report.
  **Adding a test method above another one can steal that test's `#[Test]` attribute, and the suite stays green while
  a pinned case stops running.** Pass 245's new `PreflightTest` case was inserted by anchoring on the *declaration
  line* of the method below it — which in this file is preceded by `#[Test]`, so the attribute ended up on the new
  method and `the_application_zone_is_still_utc…` became invisible. The whole suite then printed **806 passed**
  exactly as it had before the edit, with 8 more assertions, and that reading is what exposed it: `artisan
  test --list-tests` counted 806 while the class declares 26 test methods, and after restoring the attribute the
  discovery count is **807** and the class runs 26/103. So after any batch that adds or moves a test method,
  compare the discovery count before and after (`artisan test --list-tests | grep -c "Tests\\"`) with the run's own
  `Tests:` tally, and grep the attribute count (`grep -c '#\[Test\]' <file>`) against `git show HEAD:<file>`; insert
  anchored on the *attribute and declaration together*, never on the declaration alone. A green suite that lost a
  test is the same failure as a checker that read the wrong construct — it reports success about a thing it stopped
  looking at.
- **After any batch that states or edits a row count in the documents** ("Live ems holds nine offices",
  "the live directory holds 24 students", "42 assignments over 17 roles"), run
  `php storage/app/scratch/lane72-row-count-claims.php --verify` against live `ems` and prove it with
  `php storage/app/scratch/mutate-lane72-row-counts.php` (its summary prints its own verdict and no leg total, so
  this file states none). Lane 60 deliberately only *names* row counts on
  CI because they belong to the dataset that stated them; this is the hand command that weighs them on this box.
  **It carries lane 60's dated-row rule since 2026-10-27**: a §1.1 revision row whose first cell is a bare date is
  that day's record, so the row that opens "Measured before it was written" and states the permission count as it
  stood then is HISTORY rather than a stale figure — the document's own §22.3 row says the earlier permission
  counts are dated records and are left as written, and re-typing them would state a figure that was not true on
  their own day. The exemption is proved by falsification and not by the clean run: re-spelling that row's leading
  date as `(this issue)` through `--dump=` makes the same claim fire STALE again, which is what shows the rule is
  read rather than accepted.
  Measured 2026-10-26 (re-run after pass 248 wrote the clock convention into §1.2): **12 bound claims, every
  one equal to `COUNT(*)`, 0 stale, over 3,167 number-and-noun pairs** — and the queue the first run
  printed was five rows, of which **all five were the instrument's own breadth**. That is why there are seven
  buckets and not three: NARRATIVE (a singular instance — "seated a second active enrollment on *one student*" —
  and any sentence naming no row-word at all: "One receipt number is *one payment*, never two" is a rule about
  identity, not a census of `payments`), COMPARISON (one number repeated beside several tables — "They are two
  stages, two offices, two permissions" counts the flows being compared, while `offices` answers 9), CODEISH (a
  number on a class member: "the two `Enrollments::create` sites in app/" counts call sites, which a grep answers —
  and it answered exactly two, `AdmissionController.php:394` and `EnrollmentIssuer.php:76`, so the sentence is true
  and simply not this tool's question), and PER-RECORD (a *lone* number inside an enumeration of eleven table
  names: §3's walkthrough trail "…payments, enrollmentstatushistory, 7 workflowsteps, clinicrecords, idrequests"
  is one enrollment's own trail, not the 199 `workflowsteps` holds). **A census list and a trail list look alike and
  are opposite** — "154 subjects, 16 curriculums, 9 clearance requirements and the seeded roles" is a real table
  count, and the first draft of the enumeration rule swallowed it; the discriminator is whether the list's *other*
  items carry numbers too. Three instrument bugs came out of the harness's first run, each of which had produced a
  verdict that looked like a measurement: `$a[strlen('--dump=')]` is a **character offset**, so it returned the
  single letter `s` and every mutant leg ran against the real document (an ignored flag is how a mutant run looks
  clean — here it at least refused loudly); `mb_substr` fed a `strlen` offset reads the wrong pair in a document
  full of em dashes, which is why the `::` test reported CODEISH 0 while a false call-site count sailed into a
  table claim; and a "nothing to judge" fixture that struck a *remembered* list of seven table names left
  `permissions` standing, so the run answered a genuine STALE where the leg wanted the refusal — strike every
  lowercase plural, or the leg tests the fixture and not the tool. Two legs also had wrong premises, and their BAD
  was the lesson rather than the checker's: re-spelling only one half of "two offices, two permissions" to eight
  correctly produced a STALE, because COMPARISON keys on the repeated *shape*, not on the value. NOT a CI step,
  for lane 60's reason; `--allow-schema=` is how another dataset is named, and `--limit=` prints an
  `INCOMPLETE` notice because the queue is a queue.
  **Its neighbour class was measured and deliberately not instrumented.** The figure pass 244 corrected — the ID
  desk's Issued tab, "seven" where the controller's predicate answers nine — is not a table count, so `lane72`
  cannot bind it; `storage/app/scratch/lane72-probe-screen-counts.php` sizes that family at **811 sentences**
  carrying a number beside a screen word (`rows?|lists?|pages?|tabs?|desks?…`), and the overwhelming majority are
  ordinary English or a citation ("29.2 row 6"), not a claim about a query. A checker for it would need to drive
  the handler's predicate per sentence, which is lane 40's and lane 47's method applied to prose, so it is a reader
  with a probe rather than a gate. What the probe did establish: **a sentence stating two counts must be measured on
  both halves** — pass 244 re-certified "nine" from the server and left "against the two the queue holds" standing
  because it had been written on 2026-10-17, so `lane72-probe-id-tabs.php` reproduces `IDController::index()`'s two
  predicates and reads queue 2 / issued 9, i.e. both halves are true today, and the register row's "seven … against
  the two still queued" stays as the dated record of the day it was ruled (a dated row is never re-aged).
  **The lane 65 Pint trap bit this probe on its first formatting pass**, which is why the rule is a gate and not a
  note: Pint shortened the bootstrap's `Illuminate\Contracts\Console\Kernel::class` to `Kernel::class`, filed its
  own `use` *below* the call without importing the alias (fatal: "Class Kernel does not exist"), and after the
  import was hoisted by hand the file carried **two** copies ("Cannot use … because the name is already in use").
  `pint --test` called the file clean through every stage of it. Run the script, and read the import block, after
  any format over a scratch file that boots the framework.
  **Before reading a suite run as a defect, ask which school-day the dataset carries**:
  `php storage/app/scratch/lane72-probe-school-day.php` prints the UTC instant, `SchoolTime::today()`, the date on
  `DEMO-OR-TODAY-01`, and how many held receipts fall inside the school's current day, then says whether a run now
  measures the build or a dataset one day behind it (lane 54's midnight trap, turned into a pre-check). Measured at
  2026-10-25 22:45Z = **the school's 2026-10-26**: the slip carries 2026-10-26 and 5 held receipts sit inside that
  day, so the whole suite could be read as a measurement — it answered 806 passed / 5,458 assertions.
- **After any batch that changes a guard around a money or percent figure**, run
  `php storage/app/scratch/lane49-zero-shown-as-absence.php` and prove it with
  `php storage/app/scratch/mutate-lane49-checker.php`. Lane 48's mirror: `totalPaid > 0 ? ₱… : '—'` and
  `coveragePercent ? `${n}%` : '—'` made a **real 0** print the same dash as a missing figure, on a box where
  `min(studentassessments.remainingBalance)` is 0.00 today. The rule is that a guard tests the record or list
  the figure came from (`totalAssessment == null`, `assessed.length > 0`), never the amount. The checker judges
  only what text alone settles — the operand rendered by the very branch its guard selects, signed by `₱` or
  `%` — and **prints its own blind spots** (20 guards whose dash sits on a line it cannot join, 345 lines where
  the operand is a name, code, date or error string); its harness compares against a baseline it measures in
  the same run, because an earlier harness hard-coded "5 presence-tests" and failed harmlessly-but-loudly the
  moment the checker learned a new shape.
- **After any batch that adds or edits a cell showing a date**, run `php storage/app/scratch/lane50-date-day-shift.php`
  and prove it with `php storage/app/scratch/mutate-lane50-checker.php`. A calendar day reaches the browser as
  `2026-05-12` (uncast) or `2026-05-12T00:00:00.000000Z` (a `date` cast), and JavaScript parses **both** as an
  instant at UTC midnight — so `new Date(v).toLocaleDateString()` re-projects the day into the *viewer's* zone and
  paints the day before the record anywhere west of UTC. Measured at this box's zone (`Etc/GMT+11`): all 21 DATE
  columns shifted, `/students/92` read `5/11/2004` for a stored `2004-05-12`, and the Registrar's certificate page
  had the same bug bound for paper. In the Philippines this all reads correctly, which is why it survived. The rule
  is that a day is read from its own text by `resources/js/Components/ui/calendarDay.js`, and an instant keeps
  viewer-zone rendering. The checker binds each call to a column type read from the migrations and a cast read from
  the models, so it runs on CI with no database. **Its first version judged only `new Date(x)` written on the line,
  and passed every `fmtDate(row.startDate)` call — a whole class invisible while the summary printed zero
  problems**; a fixed cell no longer contains `new Date(` at all, so the run must also count what the fix produced
  (`DAY-FORMATTER`, currently 26) and the harness plants exactly that helper-call shape. Anything unbound is UNBOUND
  and refuses; the 9 blind spots are printed, not counted clean. Adjacent trap: `shell_exec` cannot reach `git` from
  PHP on this box, so a probe that wants HEAD content must read exported copies and **refuse when one is missing** —
  twice in this lane a probe globbed zero files or got an empty string and printed the same clean verdict a real
  measurement would have.
- **After any batch that draws a day/month boundary or renders an institutional moment**, run
  `php storage/app/scratch/lane50-date-day-shift.php` (it owns both classes now) and prove the behaviour with
  `php storage/app/scratch/mutate-lane51-tests.php`. The application's clock is UTC and `config/database.php`
  forces `+00:00` on every session — keep it that way; **the school's civil time is a separate declaration**,
  `config/school.php`, read only through `App\Support\SchoolTime` (`startOfSchoolDay`, `startOfNextSchoolDay`,
  `civilDateOf`, `today`, `dayOrNull`). Comparing a UTC instant to a `DATE` column, or `whereDate` on a
  `DATETIME`, cuts the school's day eight hours early — measured: 1,459 of 3,426 audit rows, 56 of 187 signed
  boxes and 11 of 15 payments land on another date depending on the midnight used. Desk-facing fixes are
  half-open intervals on two instants (no DB date function, so SQLite agrees), and the browser holds the same
  line: `calendarDay.js` declares `SCHOOL_TIMEZONE` once and `SchoolTimeTest` pins it against the config, so a
  signature reads the same in Manila, on this box and to a visitor. Two traps this lane paid for: a shape-only
  date test lets `2026-13-45` through and **Carbon rolls it into a real date in another year** — consult
  `checkdate` and ignore a bound you cannot read rather than answering it; and **a shift probe that keeps the
  first byte-identical offset mis-moves its own findings** — lane 51's first probe scanned −14..+14 and stopped at
  the first match, so a one-line citation that coincided with a `});` twelve rows earlier reported a confident
  wrong move while the rest reported "unchanged". The pass built from it moved every PHP pointer and left eight
  JSX pointers and eight bare continuations (`the signature at :273`, which names no file) behind, and nothing
  downstream complained, because a pointer one row short still lands on a live line and the bounds sweep only
  refuses a line past the end or a blank one. `lane51-reconcile-cites.php` replaces it: it joins the document as
  it stood in HEAD with `git diff -U0 HEAD` and compares the **multiset** of cited ranges per file — a
  per-citation test cannot see the 186→187 / 187→188 crossover, where both numbers are on the page and both are
  correct — then pairs rows by their masked words to judge the bare continuations. Prove it with
  `php storage/app/scratch/mutate-lane51-cites.php`. And a harness that shells out to `artisan` must `chdir` to
  the project first: from a drifted cwd it executed nothing and reported all six mutants as surviving at
  "0 passed" — a broken instrument, not a result, which is why the harness now refuses when the suite prints no
  `Tests:` line.
- **When an edit moves lines in a file the document cites, verify the shift against both versions of that file,
  never against arithmetic.** `php storage/app/scratch/lane46-probe-exam-cites.php` compares
  `git show HEAD:<file>` with the working copy and, for each cited range, accepts only a shift under which
  *every* cited line is byte-identical — the 32 citations into `ExamController.php` all resolved at +2 and none
  needed re-reading, which is a stronger claim than "the file grew by two rows". It also surfaces the bare
  continuations (`ExamController.php:179-245 , :239 , :251-262`) that every `File.php:NN` checker misses — and
  one of those bare numbers belonged to a *different* file's citation, so a global replace would have corrupted it.
- **A register row added to the docx must move every place that counts the register.** §28.3's size was stated
  twice, in the §29.4 family table (28) and in the arithmetic sentence beside it (30, the truth), and pass 138
  rewrote only the sentence: the document contradicted itself for three passes until pass 142 recounted the table
  from its own bytes and rewrote both.
- **A school question belongs in every register that asks school questions** (lane 68). Working the decision sheet
  meant reading the school's own paper for the first time: a real clearance slip — the student's copy — carries
  Course, Full Name, Date and Received by over "Registrar In-charge", and **no requirements table, no student
  number, no status word, no fee line and no document number**, under a title that marks itself `(Student's Copy)`.
  The system prints the office table and never marks a copy, so §25.4 P-19 was opened as the register's first
  *open* print item in nine passes. §1.1's own record of pass 42 states the convention the new row had to honour:
  an answered question "exists twice — in §25.11, §26.7 or §27.7 and again here [§28.5]" — so pass 183 wrote
  **three** rows, not one, and moved `§29.4`'s two count cells (18→19 print findings, 19→20 open decisions) plus
  the four `P-1 … P-18` range statements, §28.4's group row and its id list, §28.8's "five print findings" tally,
  §28.5's own "the fifteen that wait on an office" sentence, §30.6's opening (which pass 181 had just declared
  owed nothing) and §25.6's identity paragraph. **Closing a row moves fewer places than opening one, and opening a
  *question* moves the registers that list questions** — §28.8's actor table did not move, because it counts only
  §28.2 and §28.3. Two figure defects came from the same batch and both are this file's own recurring class:
  §1.1 attributed the sheet's item count to §29.4 (which counts the register — twenty, not sixteen), and P-19
  stated "seven identity rows" from the rendered inspection PDF while the same row said the system prints a
  document number. The cause is measurable and now recorded: **`ems:print-fidelity` hands each view only a record
  (`PrintFidelitySamples.php:115`) and passes no `documentNumber` at all, while every `PrintService` desk path
  mints and passes one (`PrintService.php:85`) — so the inspection PDF is not the paper a window receives, and a
  row count read off it is a property of the render.** The sheet grew item 16, and its item 8 had copied the
  register's phrase "Item 16" into a document whose own rows are numbered items — a pointer to nothing; it now
  says what the register literally holds (§7.4 and §13.4 cited, no numbered list anywhere is headed "Item 16")
  rather than guessing which decision was meant.
- **Re-read every citation into a file you changed; the range check cannot see a wrong target** (lane 68,
  same batch). The owner then ruled P-19: keep the office table, add the copy designation and the school's
  course-code wording to the face. Built on both surfaces (`clearance-slip.blade.php:38-82`,
  `PrintSlip.jsx:37-44`), pinned by `tests/Feature/Print/ClearanceSlipCopyDesignationTest.php` and proved by
  `storage/app/scratch/mutate-p19-copy-designation-tests.php` (seven defects, all killed, control green, tree
  restored), and read back off the re-rendered PDF: the title now prints CLEARANCE SLIP (STUDENT'S COPY) and
  the program reads `(BSIT) Bachelor of Science in Information Technology - Year 2`. **One half of the school's
  paper was deliberately not adopted and the register says so:** it writes the level "2nd Year", and
  `statusLabel.js:55-58` records that six pages once printed four vocabularies for one number, so the build
  kept "Year 2" rather than restart that. Adding six and eight lines to the two files invalidated four
  pointers into them. The line sweep caught one (it landed on a blank line); **reading caught a second the
  sweep could never see** — §25.4 P-10 cited `clearance-slip.blade.php:38-55` for "the enrollment that matches
  clearancePeriod.termId", but those lines print Document No., Semester, Academic Year and Student Name and
  none of them reads an enrollment. That pointer was one construct short *before this batch touched the file*,
  which is what AGENTS.md means when it says a citation can land on a live line that belongs to something
  else. `storage/app/scratch/probe-moved-cites.php` now prints, for every citation into a file, the lines it
  actually lands on — run it after changing any file the document cites more than once.
- **A checker that tallies its own blind spots must print them** (lane 68, same batch). `lane61-open-claims.php`
  printed `BLIND SPOTS: 1 sentence(s) … read them, they are not cleared` and then printed nothing, because the
  rows sat behind a `--verbose` flag no caller passed: the tally asked the reader to trust evidence the run had
  withheld. Printing is now unconditional and `mutate-lane61-open-claims.php`'s `blind-spot-printed` leg requires
  the blind **id** in the output, not the header — the old leg passed against the gated version, which is a mutant
  that sleeps. Prove any such change by re-adding the gate and watching that leg go BAD (done: it did, and the
  file was restored by sha1).
- **A ruling about how a value is *spelled* is a vocabulary change, and one number must have one definition
  per runtime** (lane 69). The owner ruled 2026-10-24 that "the proper format is 2nd year, not year 2", which
  reversed the one half of §25.4 P-19 the previous pass had deliberately not adopted — the school's own student
  copy writes `- 2nd Year`, and `App\Enums\Semester` already spells the term `1st` / `2nd` on the same paper.
  A browser cannot call PHP, so the vocabulary necessarily lives in two places (`App\Support\YearLevel::format()`
  for the Browsershot papers, `formatYearLevel()` + `yearLevelChoices()`/`YEAR_LEVELS` in
  `resources/js/Components/ui/statusLabel.js` for every desk screen) and `tests/Feature/YearLevelVocabularyTest.php`
  pins the pair **by reading the JavaScript source**: it parses the suffix map and the `level % 100 >= 11 && <= 13`
  bounds out of `statusLabel.js`, re-derives levels 1-125 with them and requires each to equal the PHP helper's
  answer — that is the only way to test a cross-runtime pairing in a repository with no JS test suite. The same
  file refuses any surface that spells a level by hand, which found **five pick-lists offering "Year 2" while
  three sibling tables already read the formatter**, three cells printing a bare integer under a "Year Level"
  heading, and two `EvaluationController.php` messages building `'Year '.$level`.
  **Reading a rule is not running it**, so `node storage/app/scratch/lane69-prove-js-formatter.mjs` imports
  the actual module and compares its output with the PHP helper for twelve levels including 11th/12th/13th/
  21st/111th, then checks the three absence spellings, `yearLevelChoices()` and `YEAR_LEVELS`. Two of its
  first three runs failed on the tool's own bugs — a static `../resources` import resolves against the
  module's directory, which from `storage/app/scratch` is a path that does not exist, and `php -r` takes code
  **without** the `<?php` tag — which is why the tool is kept rather than the one-off command that happened
  to work.
  **After any batch that touches a level's spelling, a print view's year line, or either formatter**, run
  `php artisan test tests/Feature/YearLevelVocabularyTest.php` and prove it with
  `php storage/app/scratch/mutate-lane69-yearlevel-tests.php` (eleven planted defects — the ordinal re-spelled,
  the JS vocabulary drifted, the suffix map and the exception bounds edited, year 0 invented as a level, a
  pick-list hand-written again, a print view bypassing the helper, a print view hand-spelling it, the slip
  paper reverting, the form payload reverting — each killed by the case that names it, green control before
  and after, tree restored by hash). **The same ruling blinded one of my own checkers:**
  `lane62-print-paper-probe.php` built the token it searches the PDF for as
  `'Year '.$enrollment->yearLevel`, so the morning every paper became an ordinal it matched nothing and
  printed `year level: NO` on all eight documents — a table that reads exactly like a finding. The probe now
  asks `App\Support\YearLevel::format()`, folds the PDF's whitespace before searching (a two-column layout
  wraps `1st` and `Year` onto separate lines, which is not an absence), and **refuses the run when a needle it
  searched for appears in no document**, because a busy instrument may not report a negative. Prove it with
  `php storage/app/scratch/mutate-lane62-probe-blind.php` (green control, then the hand-built token and an
  impossible program name planted in throwaway copies, each of which must make the run refuse).
- **A needle list of shared tokens corrupts the tree; rewrite by byte offset and attribute by reading**
  (lane 69, three defects caught only by reading the dry run's own counts). Two desk messages needed the
  helper, Pint will not let a static call keep a fully-qualified name in `app/` (it files the import, proved:
  it rewrote an inline `\App\Support\YearLevel::format(...)` in a test into `use` + short call), and one line
  inserted at :46 moved every line below it — so ~47 explicit citations into `EvaluationController.php` had to
  move +1. (a) **A short citation is a prefix of a long one**: replacing `EvaluationController.php:57` hit three
  places, two of them inside `:577-583`, and wrote `:588-584`. Replace with one regex pass over the
  file-name-prefixed pattern, never a needle list. (b) **`strip_tags()` welds adjacent runs together**, so the
  sentence read as a file named `curriculumsubjects` + `EvaluationController.php` and refused to move pointers
  that did belong to the moved file; join runs with a space (`preg_replace('/<[^>]*>/', ' ', …)`). (c) **A bare
  `:NNN` must be rewritten by offset**: `:94` occurs seven times in the document and only one belongs to the
  moved file — the other six are `PrintCertificate.jsx`, `HandleInertiaRequests.php`,
  `EnrollmentReadiness.php` and `SendWorkflowNotification.php` pointers that a global replace would have
  corrupted; and an empty negative lookahead `(?! )` matches nothing, which had all seven "named by no file".
  One bare number (`queue at :55`) was already pointing at a **blank** line in HEAD, and this batch's inserted
  line slid live code onto that number — a bounds sweep would have started clearing a pointer that never was
  true. It was re-pointed by reading, with both endpoints verified against the lines they land on; the other
  ten unverifiable numbers were reported, not shifted. **A shift that makes a stale pointer look valid is worse
  than leaving it flagged.**
- **Split lines on `\n`, never `preg_split('/\R/')`, when a script reports line numbers.** `\R` also matches
  the byte 0x85, which occurs as a UTF-8 continuation byte inside emoji: it read a 55-line report as 71
  lines and printed line numbers no editor would show. Every `File.php:NN` citation this repository writes
  comes from a line counter, so the counter has to agree with the reader's screen.
- **A checker's *checked* count is its own classification, not a census of the text.** Pass 183c added one
  `§25.4` reference to a §28.7 row and `lane37-section-reference-sweep.php` went from 906 checked to 905 while
  the raw `§`-plus-digit token count in the same two files went 1,012 → 1,013 — measured against the
  pre-pass backup, because the only honest way to tell "the document lost a pointer" from "the sweep
  re-classified one" is to count both. Neither number is wrong; AGENTS.md now says what the tool resolves
  rather than what the document holds. **Do not chase such a delta as a document defect until the raw count
  has been read off both versions.**
  **The same figure moved the other way on 2026-10-26, and this time the mechanism is named.** `lane37` went
  911 → 910 while the raw `§` multiset of the two versions went **1,031 → 1,038** — seven pointers added, none lost.
  The sweep has two counting paths: in a block where no finding id sits beside a `§NN` it counts every bare token, and
  in a block where one does it counts **only** the id-bearing matches. Pass 247 rewrote one §28.5 row so that its new
  `§25.4 P-19` clause moved that whole block onto the second path, and the two bare `§25.1` / `§25.11` tokens in the
  same block stopped being counted. `storage/app/scratch/lane73b-measure-sectionrefs.php <head.docx>` prints the raw
  multisets of two versions and `storage/app/scratch/lane73b-dump-blockrefs.php <docx>` prints each block's bare / id /
  counted numbers; diffing those two files between HEAD's docx and the working one is what turns "the document lost a
  pointer" into one named sentence. Export the old copy first: `git show HEAD:"Documentation/EMS Complete
  Documentation.docx" > %TEMP%/head-ems.docx` — `shell_exec` cannot reach `git` from PHP on this box.
  **Pass 248 showed the same mechanism in the other direction:** it added a `§1.2` reference to the living §1.1 row,
  the raw tokens went 1,038 → 1,039, and the sweep still printed 910 — that row's block already carries finding ids, so
  its bare tokens are not counted either. A figure that does *not* move when a pointer is added is the same warning as
  one that moves when no pointer was lost: both mean the number describes the classifier.
- **A docx edit's third slot is the ANCHOR and its fourth is the copy count — put an integer in slot three and the
  engine silently resolves one target** (found 2026-10-26). `docxApply`'s text edits are `[from, to, anchor, expect]`.
  Pass 247 wrote `[from, to, 2]` four times meaning "this sentence lives in §25.11 and again in §28.5", and the
  positional resolver read the int as the anchor string `"2"` — a character `document.xml` is full of — so it landed on
  one occurrence and printed `1 target resolved uniquely`. A half-applied register is the worst docx defect: one
  section states the ruling while its twin still asks the question, both sentences are live prose, and no checker is
  dissatisfied. The engine now refuses a non-string slot three outright (`FAIL text N: slot 3 is the anchor…`), and
  `php storage/app/scratch/lane74-probe-engine-slot3.php --case=3` proves that refusal while `--case=4` proves the
  correct shape resolves both copies — a dry run against a throwaway copy, never the real file. Two facts the probe
  settled on the way: pass 247 did reach both copies (measured — each new phrase twice, each old needle zero times),
  and **the engine ends a failed pass by exiting the process, so a caller's `finally` does not run**; clean temporary
  docx copies up with `register_shutdown_function`, not `try/finally`.
- **A commit is not the working tree: list what you staged, against what was dirty.** Lane 69 staged
  `resources/js` and `tests` but never `resources/views`, so CI ran the tests asserting the new year-level
  spelling against six print blades still building `'Year '.$level` — three failures on a tree that had
  passed the whole suite locally 806/806 minutes earlier. The guard is two lines, not care:
  `git status --porcelain` before the commit, and again after it, and the second one must be empty of `M `
  entries. `git commit` in fact prints the files it did not commit under `no changes added to commit:` —
  the warning was in the output I did not read, which is the same failure as not running the check at all. (What caught
  it was `YearLevelVocabularyTest`'s own scan for a hand-spelled level — a guard that fails on CI is worth
  the red it caused.)
- **Never let a wrapper command swallow the exit code you are watching for.** The same lane's first CI
  watch was `gh run watch … > log 2>&1; echo "EXIT=$?" >> log`, so the command's own status was the
  trailing `echo`'s — success — while the run was red, and I reported the jobs as if they had passed. The
  exit code must be the last thing the pipeline evaluates; capture it in its own file if it must be kept.
- **`gh run list` has no `--sha` flag, and `2>/dev/null` turned that into "CI has not started."** On
  2026-10-27 a poll began `RUN=$(gh run list --sha "$SHA" … 2>/dev/null)`, which answered
  `unknown flag: --sha` on stderr; the discard hid it, the substitution came back empty, `&&`
  short-circuited, and the loop printed its own honest refusal — "no Actions run appeared for this SHA
  within three minutes" — about a run that had existed the whole time. A refusal is only as good as the
  question it was asked with. Filter a run by `gh run list --branch master --json headSha,databaseId`
  and match the SHA client-side, and never send a command's stderr into the void when its silence is
  being read as a measurement; this is the accepted-but-inert flag defect arriving in a shell wrapper
  rather than in a PHP tool.
  **That client-side matcher bit me the same day, in the opposite direction.** `storage/app/scratch/watch-ci.sh`
  did `WANT=$SHA` and then read `getenv("WANT")` from a PHP child — a plain shell assignment is **not** exported, so
  `getenv` answered `false`, `str_starts_with($headSha, "")` is true for the first element of the list, and the
  poller resolved *the newest run* and printed `RUN=40462260904 for SHA=34888b5…` for a run whose `headSha` was
  `771dc8a`. The verdict it quoted was real, just about a different commit — the worst kind of green, because
  `status == completed / success` was then re-read from that run and reported as this one's. The wrapper now passes
  the value explicitly (`php -r … ` with `WANT="$WANT"` on the command), refuses when the needle is empty or shorter
  than seven characters, and after resolving an id asks that run for its own `headSha` and refuses unless it equals
  the SHA it was asked about. Two rules follow: **a prefix match needs a non-empty needle** (an empty needle matches
  everything, so the guard is on the needle, not on the result), and **the object a poller resolved is a claim it has
  to re-read from the server**, not a fact inherited from the command line that started it. (The wrapper itself is
  `storage/app/scratch/watch-ci.sh`, and `storage/app/.gitignore` re-includes scratch only by extension — `.php`,
  `.ps1`, `.mjs` — so a `.sh` lives on this box and nowhere else; that is precisely why the three guards are written
  here rather than left to be inferred from a file a later clone will not have.)
- **The mirror defect: a watcher's non-zero exit is not a red run.** On 2026-10-25 `gh run watch <id> --exit-status`
  returned 1 while its own final poll showed one job still `in_progress` with an empty conclusion, and the run the
  API reports now is `completed / success` on all three jobs. A watcher can terminate on a partial read, so the
  exit code alone cannot say which verdict the run reached. After the watch, ask the run itself —
  `gh run view <id> --json status,conclusion`, then the per-job list — and quote that, never the watcher's status.
  Reporting a green pipeline as red costs the same trust as the reverse, and both are the same failure: quoting an
  instrument's exit code as if it were the fact it observes. **The opposite face was met on 2026-10-26:** on run
  40195587267 the same watcher returned **0** while the MySQL job was still `in_progress` and the run's own
  `updatedAt` was four seconds after `createdAt` — a read that early is not a verdict in either direction. So the
  guard is not "trust a zero exit", it is **poll until `status == completed`** and only then quote `conclusion` and
  the per-job list; run 40195587267 answered `completed / success` on all three jobs about eight minutes later.
- **A register built from a gap list cannot find an unvisited section** (lane 86). The prompt carries **357
  enumerated requirement lines across 55 headings**; `Documentation/Prompt-Requirement-Matrix-2026-10-26.md`
  had rows for 14, because its first pass walked the gaps it had found rather than the document's own
  structure. Counting the population from the file (a script over headings and `- `/`1. ` lines) is what
  revealed the hole, and it produced G33 … G42. So the sweep instruction is: walk every heading and ask how
  many things it enumerates, then ask which of those the checkout answers — a section with no gap id is
  exactly what a gap-derived register reports as silence. **A population you did not measure is a
  denominator that drifts**, and a clean register over 14 of 55 headings looked complete.
- **A hand-kept list of documents is a blind spot that prints a clean verdict.** Both
  `repo-file-line-citations.php` and `lane69-open-inventory.php` named each dated sheet as a literal, so the
  sheet issued with this lane would have been read by neither — and the inventory would have answered
  "4 sheet items" about a register holding 10. Both are bound to a glob now, each **refuses on an empty
  match** (an unmatchable pattern is the failure lane 79 met with `glob()` and no recursive `**`), and the
  refusal is proved by planting a throwaway `School-Decision-Sheet-2999-01-01.md`, watching both react, then
  removing it and watching the counts return. Prove a list-with-glob the same way whenever you replace a
  literal list: name what must appear, and check that its absence is *loud*.
- **Re-open every `file:line` a subagent hands you before it becomes a register row.** Three of this lane's
  arrived wrong or overstated: no bulk `->query()->update()` exists in `app/` (so a return does not reopen
  boxes outside the observer); an override act does exist, in `EvaluationController` with
  `auditlogs.adminOverride` carrying it (so "Registrar override is absent" was false); and only
  `PrintCertificate.jsx` re-declares a status tone map, not "the print views". A search pass is a lead, not
  evidence — the same rule the registers already follow for a citation whose file is right and whose line
  belongs to something else.
- **Check a column's type before treating a finding as a schema change.** `auditlogs.action` is
  `varchar(100)`, so naming the act the audit records is a write, not a migration — and a question that
  sounds expensive gets deferred for a reason it does not have. `students.religionId` is the mirror case: a
  real foreign key, so a test fixture must read the id back rather than assume 1.
- **After any batch that adds or edits a desk filter, a page's `router.get`, or a controller's `filters` echo,**
  unchanged below, and **after any batch that issues a dated owner-facing document**, run
  `php storage/app/scratch/repo-file-line-citations.php` — it now refuses an unlisted document rather than
  reporting a clean sweep over files it never opened.
- **A figure you wrote about a tool is the last thing to re-measure, and better left unwritten.** This lane
  typed "159 citations over 14 files" into its own report, then added a document, and the denominator moved
  under the sentence. The fix states the property (0 problems; the gate refuses what it cannot scan) instead
  of the count. Lane 58's rule for a revision row applies to prose about a checker: name one surface or state
  no total.
- **A verdict word must be read from where a verdict is WRITTEN, never from the block around it** (lane 69).
  Answering "is anything left?" found a committed sentence the register had already overtaken —
  `Documentation/School-Decision-Sheet-2026-10-23.md` introduced §25.4 as "the printed-paper items … three of
  which were answered on 2026-10-23 and one of which is not", while that fourth row (`P-19`) was closed the
  same day it was opened and §25.4 now leads `Closed` on all 19 of its rows. No instrument could see it:
  lane 61 reads the docx dump, and the sheet is markdown. **A classifier that greps a whole item block is the
  same defect as a checker that reads the wrong construct** — splitting on the *next* item's marker makes each
  block run past its own answer and swallow the following `##` heading, so item 11 inherited section D's "all
  three answered 2026-10-23" and reported itself closed, while item 7's narrative "Answered as *indefinite* and
  measured" (a reading of the box, not a tick) read as a ruling. Judge on the title's `ANSWERED` marker, the
  item's own `Answer:` line, and `☑` — and read every printed row before quoting the tally, because §17's
  worked-example step "Accounting | Open the queue" is an instruction to a desk, not an open finding.
  `php storage/app/scratch/lane69-open-inventory.php` is the read-only answer to "what is left": OPEN and OFFICE
  rows out of the registers, then the sheet item by item with its open count.
  **An open word is a verdict only in a column that can hold one** (same lane, 2026-10-25). The inventory reported
  `ROW 2432` and `ROW 2699` as owed work for passes on end, and both were labels: the first is cell 3 of the
  walkthrough table headed `# | Desk | Action | What the record becomes` — "Open the queue" is a step a desk performs
  — and the second is the §29.4 family label "Open decisions" in the column headed `Family`. The tool now reads the
  heading its own cell sits under: a table in the flat dump is a maximal run of consecutive `ROW` lines and the run's
  first line is the header, so the column name is a fact about the document, not a guess about the words. Counted on
  2026-10-25 over all 184 table runs, two rows led a cell with an open word and both were labels — the reading that
  the 2026-10-29 note below supersedes.
  The list is inverted on purpose — an unknown column heading still counts OPEN, because an inventory that hides owed
  work is worse than one that prints a label, and **a "leading cell must be a finding id" rule would have been the
  worse bug**: §28.5's rows lead with the *question text*, so that rule would silently stop reporting every school
  question that is genuinely open. **That 2026-10-25 census was superseded on 2026-10-29 by the same batch that
  repaired the cell counting — the answer is five**, because until the cell count came from `document.xml` the rows
  whose value column
  is an enum list were mis-split and never reached the heading test. Today the LABELS list reads `provisional` under
  `Default`, `open, closed, extended` and `provisional, official` under `Values in the database today`, `Open the
  queue` under `Action`, and `Open decisions` under `Family` — five, all schema values or desk instructions, none a
  verdict. A row whose cell count differs from its header's cannot be resolved from the dump,
  so it is printed as UNRESOLVED rather than cleared. **That rule has now been repaired three times over, and the
  §24 row it blamed was innocent.** Measured 2026-10-29 and deliberately dated rather than kept current (lane 72's
  rule for a queue): **6 OPEN rows — G-35, G-36, G-37, G-38, G-40, G-41 — 5 LABELS, 0 UNRESOLVED, 10 sheet items
  across the three dated sheets.** Three defects came out of the one row the tool had been printing as UNRESOLVED.
  (a) *The separator*: `dump-docx-flat.php` joins cells with `' | '`, while the inventory split on a bare `'|'`, so
  every `enum(provisional|official)` cell cut itself in two. (b) *The lossy artefact*: the dumper also **drops empty
  cells** before joining, so no separator can recover a column position — the §24 `resultStatus` row really holds
  six `<w:tc>`, exactly like its six siblings (measured in `document.xml`, which is what proved the document
  innocent), and 377 rows needed their cell count taken from the archive instead of the dump. The checker now reads
  `document.xml` and locates a row by its text with whitespace and pipes stripped from **both** sides; the first
  version stripped pipes only on the lookup side, so an enum cell could never be found and the repair looked like it
  had not fired. `--no-archive` keeps the pure-dump path addressable so a harness can prove the tiebreak is read.
  (c) *The verdict word*: a cell is judged by the verb it **leads** with, but the tool also cancelled a row when the
  closed words appeared anywhere inside it — so §28.3 G-41, whose verdict cell begins `Open 2026-10-29.` and whose
  own sentence later uses the word "answered", went unreported as owed work while `lane61-open-claims.php`, which
  reads the leading cell, named it all along. Two tools answering "what is left" disagreed and only one of them was
  read back. The harness was re-cut the same batch: its control was a frozen tally ("0 OPEN, 2 LABELS"), which was
  true the day it was written and became a failing control once §28.3 held open rows — a verifier whose expectation
  is a remembered reading is indistinguishable from a broken tool. It now asserts a **relation**: the ids lane 69
  reports must equal the ids lane 61 names, and every planted leg is a delta against the control measured moments
  earlier — and the leg that demands the `--no-archive` path print its own refusal is what caught
  `$archiveCells = []` compared with `> 0`, which is true for an array in PHP 8. Prove
  any change to it with `php storage/app/scratch/mutate-lane69-open-labels.php` (13 legs: the
  same words planted under `Verdict` must be OPEN and under `Action` must be a LABEL, a row the archive cannot match
  and that is short of its header must surface as UNRESOLVED, a cell LEADING `Open` whose own prose says "answered"
  must still be OPEN while a cell leading `Closed` that later says `Open` must stay out, a copy with one more verdict
  row must answer *more* — which is what proves `--dump=` reaches the walk, `--no-archive` must answer MORE
  UNRESOLVED than the archive-bound run (the leg that proves the tiebreak is read, and the one that caught the
  array-compared-with-zero bug above), an unknown flag and both missing-dump paths must refuse by name, a
  byte-identical copy must change no verdict, and the control must equal lane 61's ids rather than a remembered
  tally. A prose cross-reference inside an OPEN row is NOT a second open item: §28.3's G-52 row says the contrast is
  with G-51, and the collector that swept the printed row for any id token handed the inventory an id the register
  had closed — so ids now come from each row's LEADING CELL, and one leg plants a `G-907` named only in prose and
  requires it absent. The first draft of that leg planted a `Z-62` and would have passed on the very parser it was
  written to catch, because the old character class was `[PXCESG]` and could never have harvested a Z; it was
  re-cut, then run against a copy carrying the old parser, where it failed and the control failed with it. This file
  states no leg total: the harness prints its own.
  **The same audit found the older sheet carrying the mirror defect:** `Registrar-Print-Decision-Sheet-2026-10-11.md`
  introduces its comparison table by promising the quoted wording "is what a PDF says today" — true when written,
  and the newer 2026-10-23 sheet closes by telling the Registrar that this one "still stands". Two of its cells
  therefore stated the build wrongly (the heading "never naming whose copy it is", a program line "with no code")
  and one of its pointers had drifted onto a comment, because a dated document re-cited as live reference is read
  as present tense by whoever holds it. Its own date does not save it once another document points at it. Now
  carries an explicit supersession note; **do not re-age its cells** — the reading of that day stays, labelled.
  Nothing reads these sheets' *meaning*, but their pointers are now judged: `repo-file-line-citations.php` was
  widened on 2026-10-24 to cover the three sheets an office holds, after three citations in the 2026-10-11 print
  sheet were found to have drifted onto other blocks when the P-19 ruling was built (`:78-100` → `:84-107` for the
  office table, `:72` → `:80` for the date row, `:103-105` → `:111-113` for the receipt). Same day, the live sheet
  was carrying two present-tense claims the build had overtaken — that no surface marks whose copy the slip is —
  which is the lane 69 defect class one document later. **Re-take a pointer; never re-age a reading.**
- Local green is not enough, twice proven: tests that pass on this machine because Chrome, a
  `storage/app/prints` folder, or a live-`ems` row exists will fail on the runner. A test that
  binds a fake service must have **every** method its route reaches — an un-overridden method
  quietly calls the real one; poison the real method once and confirm the test still passes.

## Working with the live `ems` database

- **Never clone a database by editing a mysqldump with `sed`.** With `--databases --add-drop-database`,
  mysqldump emits the drop as an *executable version comment* — `/*!40000 DROP DATABASE IF EXISTS `ems`*/;` —
  so `grep '^DROP DATABASE'` finds nothing and it still runs. Rewriting only the `CREATE DATABASE` and `USE`
  lines to retarget a dump leaves a live drop of the **source** database in the file you are about to pipe into
  `mysql`. This destroyed live `ems` on 2026-10-14; it was restored from the dump taken seconds earlier and
  verified row-by-row (30 enrollments, the #89 credit split, 157 boxes, 3,348 audit rows), so nothing was
  lost — but only because the dump existed. Clone instead by creating the target first and piping
  (`mysqldump --no-create-info --no-create-db src | mysql target`), or hand-write the `CREATE`/`USE` preamble.
  And after any clone, verify counts **in the target**, then confirm the source still exists.
- Dump before every live write: `mysqldump -u root ems > C:\Users\ADMIN\ems-backups\ems-pre-<what>-<date>.sql`,
  then verify the file landed and contains the table you are about to change.
- **A database dump never goes in the repository — `C:\Users\ADMIN\ems-backups\` is its only home.** Found
  2026-10-26 while answering two unrelated questions: `Documentation/ems-demo-backup.sql` — a mysqldump whose header
  says `Database: ems` — had been tracked on a **public** remote since `5a733bd` (2026-09-12) and re-committed twice
  since, carrying `INSERT`s for `students`, `staffusers`, `studentids`, `studenteducationalbackgrounds`, `payments`
  and `password_reset_tokens` (live `ems`: 24 students, 21 staff, 4 admissions, 37 enrollments). The owner confirmed
  those rows are the **synthetic demo cast**, so this was hygiene and not a disclosure — which is exactly why it sat
  unnoticed for six weeks: nothing in the tree distinguishes a synthetic snapshot from a real one, so the rule has to
  be positional. It is now untracked, `.gitignore` carries `/Documentation/*.sql` with the reason in its own comment,
  and the file stays on this box so the demo-day fallback still works here. Three places had told a person to load
  it and were re-pointed the same batch — `Demo-Day-Guide.md` §5, `Offline-Presentation-Guide.md` "If the demo data
  gets lost", and the `SCOPE` banner in `tools/seed_full_demo_pipeline.php` — because untracking without that would
  have left a documented procedure asking for a file a clone cannot have. Nothing read it at run time: the CI step
  and `DemoDatasetRecordStatesTest` mention it only in comments, so no gate depends on it, and `lane39-dump-contents.php`
  and `lane39-guide-preconditions.php` still re-measure it when a local copy exists.
  **History is the part a new commit cannot undo.** The rewrite below is planned and deliberately **not executed**
  (owner's word, 2026-10-26; not needed while the rows are synthetic): keep a reference clone
  (`git clone <url> reference-clone && cp -r reference-clone reference-backup`), install `git filter-repo`, run
  `git filter-repo --invert-paths --path Documentation/ems-demo-backup.sql` on a fresh mirror clone, then
  `git push --force-with-lease` — and say out loud that the old objects remain reachable on GitHub until the GC and any
  fork or mirror is gone, which is why "make the repository private" is the containment step if the data ever stops
  being synthetic and the rewrite is the repair. Every collaborator must re-clone afterwards; a rework of history
  invalidates their existing checkouts silently, which is the same class of half-applied state this file keeps
  refusing elsewhere.
- `php artisan db:seed --class=RbacSeeder` prunes permissions the seeder no longer declares, so
  retiring a right is a seeder edit plus a reseed, not a migration.
- RBAC pivots are renamed: `staff_roles`, `role_permissions` with columns `roleId` / `permissionId`;
  there is **no** `model_has_roles`. `staff_roles.model_type` was measured on live `ems` (2026-10-25) as
  `App\Models\Staffusers` — 21 characters, no doubled separator — so this file's earlier instruction that it is
  "stored double-escaped, so a single-backslash SQL literal matches nothing" is false as written; it was a reading of
  the PHP-source spelling, and a literal `where model_type = 'App\\\\Models\\\\Staffusers'` now matches nothing for the
  opposite reason. The robust rule, which is what `lane34-probe-negative-hits.php` uses: match the class WORD
  (`like '%Staffusers%'`) and print the distinct `model_type` values, so an empty answer can never be confused with a
  filtered one. Same probe, same lesson: `staffusers`'s primary key is **`userId`**, not `id` — a join on `u.id`
  dies mid-claim and proves nothing about the claim it was testing.
- Never delete rows found during probing. Deleting probe residue is its own decision for the owner.
- Scratch schemas accumulate one per rehearsal lane, and some are load-bearing: `mutate-lane54-report.php`
  refuses to run unless `DB_DATABASE` names `ems_l54_a` or `ems_l54_b`, and the lane 55/56/57 and lane 38/40
  harnesses clone `ems` into theirs. So a schema is retired only when **no tracked file names it** —
  `php storage/app/scratch/drop-retired-scratch-databases.php` reads every `.php`/`.md`/`.yml` under
  `storage/`, `tools/`, `.github/`, `tests/`, `app/`, `database/` and `Documentation/` for the literal name,
  prints KEEP for any hit and only drops the rest, and refuses on any collision with `ems` or the four
  foreign project schemas. (`git grep` is not an option: shell_exec cannot reach git from PHP on this box.)
  It ran on 2026-10-24 and dropped seven schemas while live `ems` was verified unchanged before and after —
  that before/after comparison is why the tool does not assert a frozen count like its predecessor
  `drop-scratch-databases.php`, whose expected figures were the 2026-10-14 reading and are now marked as such.
- **A HEAD export is an artefact, and is now ignored.** The lane 53/55/56/57 harnesses swap a pre-fix copy of a file over
  the working one to prove *this batch* changed the verdict, and because `shell_exec` cannot reach `git` from PHP on this
  box the copy is taken by hand — `git show HEAD:<path> > storage/app/scratch/laneNN-head-<name>.php` — which left a
  second copy of a controller, of an audit command, and a 174 KB copy of the demo pipeline sitting permanently untracked in
  `git status`. `storage/app/.gitignore` re-includes `scratch/` **by extension**, so only a rule written *after*
  `!scratch/*.php` hides them again: eleven matched the `lane*-head-*.php` wildcard and one needed its own literal
  (`lane54-pipeline-no-year-widening.php`, written before the naming convention), measured 2026-10-26 with
  `git check-ignore -v` — the same command is the negative control, because every live tool still resolves to the
  re-include and `git ls-files -i -c --exclude-standard` answers empty, so nothing tracked became invisible. Ignoring is
  safe here for the reason the dump rule above is not: each harness prints its own export command and **refuses with exit 1
  when the copy is missing or byte-identical to the working file** (`mutate-lane55-conflicts.php:29-33`), so an absent
  export is a loud refusal and never a green run. The cost is a naming rule: a real *tool* must never be called
  `lane*-head-*`, or this pattern hides it from the remote. **The mirror defect bit on 2026-10-28:** an export that
  does *not* follow the convention is invisible to nothing — it simply becomes tracked content. A HEAD snapshot of
  the admin smoke sweep, written to compare the pre-route table against the new one, was committed under a name
  outside the pattern and pushed; the only thing that caught it was opening each staged file and diffing it against
  the tracked test it duplicated, which nothing in the gate list asks for. So the check when staging is *what the
  bytes are*, not what the filename says: `git diff --cached --name-only` and open anything whose name you cannot
  justify from the work it belongs to.

## Facts new code must respect

- **Stack:** Laravel + Inertia/React (`resources/js/Pages`), MySQL 8 via Laragon, PHP at
  `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`. `localhost:8080` is Laragon Apache;
  `php artisan serve` silently shadows it on loopback, so check which server answered a probe.
- Public `/register` is removed. Staff accounts come from Admin → User Management or
  `php artisan ems:create-admin`.
- Student 360 and quick-search require the seeded `students.view` permission; bare `Staff` and
  `Instructor` do not hold it. The frontend reads shared prop `can.studentsView`.
- Office ids: use `App\Enums\OfficeId` (Registrar=1, Accounting=2, Scholarship=3, Guidance=4,
  Blocking=5, Admission=6, Academic=7, Clinic=11, IdOffice=22) — never a bare integer in PHP, and
  never a hand-kept office list in JSX. Descriptors that gate a card by number went stale when
  ruling 12 folded legacy office 8 into Registrar; navigation now answers to a right the desk's own
  route asks (`DashboardController::QUEUES`, `HandleInertiaRequests`' `can` map).
- Spatie registers `Gate::before`, so **a gate ability must never share a permission's name** — the
  permission would grant it and the policy body would never run (`AbilityNameCollisionTest` pins it).
  Note `Gate::before` also grants SysAdmin everything before any policy runs.
- Staff relations are named `*User` (`evaluatedByUser`, `registrarProcessedByUser`, `approvedByUser`,
  `receivedByUser`); the bare FK columns return ints. `Schedulemeetings.startTime/endTime` are plain
  strings — casting them breaks Blocking's conflict detection.
- **A timetable collision is judged inside one term, and a block is never in two places** (lane 55, owner rulings
  of 2026-10-19). `detectConflicts()` asks three questions of one builder — officer, room, and the block's own hour
  (`blockId`) — because a group that meets in two rooms at one time is the defect Blocking exists to prevent, and
  the old code only compared what two *other* schedules shared. `schedules` carries no term: the term lives on the
  **block**, so the scope is `whereHas('schedule.block', … termId …)`. Rooms and officers are deliberately reused
  every year, so an unscoped query reads a retired term's meeting as a conflict for the term being built. A
  schedule whose block is missing has no term to scope by and the filter is dropped rather than answered "no
  conflict" — widening is the honest failure, silence is not. `ems:audit:record-states` carries the same rule as
  two of its nineteen rules (`collidingSchedules('block')` and `('resource')`), self-joined through both blocks so
  the two sides are term-equal, and half-open so **back-to-back is not a collision**. There is deliberately no
  `meetingId` exclusion on that path: Laravel rewrites `where('meetingId', '!=', null)` into `IS NOT NULL`, so the
  create-form guard that used to depend on it passed by an accident of the query builder, not by intent.
- **A receipt states money the school keeps, and a status states a moment** (lane 57). `record()` bounded
  `amount` only from below, and `outstandingBalance()` answers `max(0, …)`, so one POST of ₱19,000 against a
  ₱17,500 sheet wrote the receipt, marked it `paid` (the honest word — nothing was left owing), stored a
  balance of ₱0.00, and left ₱1,500 in the drawer against nothing. Ruled 2026-10-20: the OR may not state more
  than the account owes, so the guard refuses above the balance and names the figure, and the screen that used
  to compute change due now caps at the same number — a promise and a server must not disagree (§17).
  `ems:audit:record-states` carries the state as its eighteenth rule, measured against *what is owed*
  (assessed less coverage less waived), not the assessed total, so a full receipt over a covered account is
  named too. The lane's second rule is its mirror: the probe that planted the first un-planted it by deleting
  the row and recomputing the balance, never walking the enrollment's status back — leaving a schema saying
  `paid` over the whole fee while all eighteen other rules read 0. So the nineteenth rule asks whether a
  `paid` status still matches its money, scoped to `paid` alone: the machine walks `paid → enrolled` on
  purpose, and a rule that swept every later status in would report normal progress as broken.
- **A receipt's status word is a claim about the account, not about the money** (lane 56). `held()` counts both
  `paid` and `partial`, so every balance and collection figure is indifferent to which word a row carries — and
  that is exactly why the wrong word survives: `RegistrarController::blockingReasons()` asks whether a receipt
  *settled*, and the Accounting screen prints the status under the amount. `AccountingController::record()` writes
  `partial` for every peso taken while a balance remains and reserves `paid` for the money that clears it, so
  `paid` over a owing sheet states a settlement the desk refused to state. The demo generator minted that shape for
  ₱8,000 on Bea Alonzo's account (§28.3 G-24) and its own report could not see it; both are now guarded —
  `receiptPaidWhileOwing()` in the audit and a `Ledger —` line that re-marks the row and refuses the run.
- Money in the drawer is `Payments::held()` (`paid` or `partial`) — **and that includes the demo generator**. Five
  sites in `tools/seed_full_demo_pipeline.php` summed `paymentStatus = 'paid'` by hand to decide what an account
  had been given, while `Studentassessments::outstandingBalance()` sums `held()`; on any account holding an
  installment the generator's ledger pass would therefore rewrite `remainingBalance` back over what the cashier
  already collected, and the shortfall loop beside it would take a second receipt for money nobody handed over.
  All five now call `demo_held()` (`tools/seed_full_demo_pipeline.php:1370-1373`), which asks the model's own
  scope rather than restating the status list. Measured: an ₱2,000 installment leaves ₱15,500 stored, and the
  paid-only version of the helper put it back to ₱17,500 — proved both ways by
  `storage/app/scratch/lane54e-installment-survives.php`, which plants the defect in a copy of the tool and
  requires the erasure to reappear. Neither side was visible to any test, because the generator wrote no `partial`
  row at all until §28.3 G-24 was closed on 2026-10-20, and live `ems` held none on 2026-10-18: a rehearsal that
  only *reads* the dataset the tool builds cannot find a state the tool refuses to create. Measured on
  two schemas built from empty in CI's own order (`storage/app/scratch/lane56f-probe-claims.php`): HEAD's
  tool leaves 15 `paid` and zero `partial`, and one receipt — `DEMO-OR-0002`, ₱8,000 on an ₱18,500 sheet
  owing ₱10,500 — says `paid` over that balance, while its report prints six `Ledger —` lines, none about
  the status, and exits 0. This batch's tool on the same recipe leaves 14 `paid` and 1 `partial`, with no
  lying row. One installment is enough to make the paid-only reading demonstrably wrong rather than
  theoretically so: it is the row the ledger pass and the shortfall loop disagree about.
  "Is clearance season?" is answered only by `Clearanceperiods::accepting()` (open **or** extended). Year level is
  derived from the student's own record, never asserted as a constant. **A slip names the enrollment of its own
  period's term, or nothing** (P-17, ruled 2026-10-23): `Studentclearances::termEnrollment()` has one branch, and the
  newest-enrollment-by-id fallback that used to sit behind it printed a course and year level from a term the
  clearance was not about. The same ruling keeps a completed clearance forever — nothing deletes a
  `studentclearances` row, and the guard for that is a test, not a convention.
- Migrations containing raw MySQL must guard on the driver (`getDriverName() !== 'mysql'`) — the
  SQLite suite depends on that escape hatch.
- Record timestamps exist only on students, admissions, enrollments, payments, studentassessments,
  studentclearances, clinicrecords and idrequests — and on those eight the columns are snake_case
  `created_at`/`updated_at`, measured on live `ems` and in the migrations. The audit-type tables
  (`auditlogs`, `enrollmentstatushistory`) use camelCase `createdAt`/`changedAt`. Writing `createdAt`
  into an `admissions` insert fails with "no column named createdAt"; the two namings coexist on purpose.
- A staff account with a non-null `unitId` is **college-bound**, and `App\Support\CollegeScope` is the only
  place that boundary is expressed: the policies ask `holds()` / `holdsStudent()`, every list the desk reads
  goes through an `of*()` method, and a null `unitId` means bounded by nothing. The null is a decision, not
  an oversight, and its reason has now moved: it used to be that bounding `office4_head` would strand the
  29 enrollments it was credited with across three colleges — those credits were repointed to each row's own
  college desk on 2026-10-13 (#89), so that argument is gone and the account is still unbounded, because an
  office head's work belongs to the office rather than to one college (it is credited with 0 enrollments and
  has 3 open Department Evaluation boxes, all CTIC, plus 20 closed ones). `office7_head` and the seeded
  instructors stay unbounded because §7 of the walkthrough walks two colleges in one login. Note for
  whoever re-measures this: `shiftingrequests` has 0 rows on live `ems`, so the cross-college shift docket
  is not currently evidence either way. A record whose course names no college is *not*
  the desk's, and the dropdown must be filtered by the same rule the policy asks, or the screen offers work
  the save refuses.
- **`paginate()` mutates the builder it is called on** — `forPage()` sets limit and offset on `$this`
  (`vendor/laravel/framework/src/Illuminate/Database/Query/Builder.php:3208`) — so a summary taken from
  `(clone $query)` **after** it inherits the page's `LIMIT 20 OFFSET 20` and answers with nothing from page 2
  on. Three desks carried a comment promising "the full queue, not just the current page" while doing exactly
  that, and page 1 — the only page a demo opens — proved it right. Clone **before** the paginate, the way
  `AccountingController::index()` always has (Admission, Assessment and Clearance joined it in lane 47); the
  same ordering applies to any summary over a filtered set. Page 1 proves nothing about this, and no queue on
  live `ems` is long enough to reach page 2 — a test has to write 25–60 rows to see it.
- A desk tile describes the **whole filtered set**, on the server, cloned before that paginate; a number that
  reduces over `props.data` is a page total and its label has to say "on this page" (the honest precedents:
  "Students in Queue", "Total (this page)", "Validated, on the Issued tab"). **And a tile may not invent the
  number when the server did not send one** (lane 48): no `?? 0`, no `|| 0`, no fallback to a count of the rows
  on screen, and no private `peso()` built on `Number(n || 0)` — that chain turns an absent amount into ₱0.00
  before any call-site default is reached. Absence belongs to the shared tile:
  `Components/ui/StatCard.jsx` renders `—` for null, undefined, `''` and NaN, and a `title` says why. A zero
  that the *handler* computed for an empty set stays `0` — the rule is about the screen's arithmetic, not the
  server's. Where a figure genuinely cannot be known, the handler answers null and the tile dashes: an exam pass
  rate over nothing marked is unknown, not 0%. A rate's denominator is the rows that have been scored —
  `examresults.examResult` is `enum('pass','fail')` **nullable**, so an unmarked paper is neither a passer nor a
  failure. **And the dash must not steal a real zero** (lane 49): a guard written on the amount itself
  (`totalPaid > 0 ? ₱… : '—'`, `coveragePercent ? `${n}%` : '—'`) makes a 0 indistinguishable from silence, so
  the test belongs on the record or list the figure came from (`totalAssessment == null`, `assessed.length > 0`)
  and never on the amount — `min(studentassessments.remainingBalance)` is 0.00 on live `ems`, so this is not a
  hypothetical, and the student panel's three money cells now all ask the same question.
- **A calendar day is painted from its own text, never from the viewer's zone** (lane 50). A `date` column arrives
  as `2026-05-12T00:00:00.000000Z` and an uncast one as `2026-05-12`; both are parsed as UTC midnight, so
  `new Date(value).toLocaleDateString()` shows the previous day to any client west of UTC — which includes this
  machine (`Etc/GMT+11`) but no Philippine office, and that is why every earlier pass saw correct dates. Day cells
  use `formatCalendarDay()` / `calendarYear()` from `resources/js/Components/ui/calendarDay.js`, which read Y-M-D
  out of the string and format with `timeZone: 'UTC'`. Instants keep local rendering on purpose: `receivedDate`,
  `validatedDate`, `printedDate`, `refundedAt`, `signedDate`, `created_at` — there the viewer's zone is the right
  answer, and what is wrong about them here is the clock, not the code. Two exceptions the sweep found and
  recorded rather than "fixed": `payments.paymentDate` is a `datetime` whose every row is exactly midnight (a
  date-only input writes it), so it is displayed as a day and the schema mismatch is §28.3 G-19; and
  `curriculums.effectiveYear` is a DATE used as a year, so `getFullYear()` on it read 2023 for a 2024 row.
  `tests/Feature/CalendarDayPayloadTest.php` pins the payload spelling the module depends on — it goes red if
  `APP_TIMEZONE` stops being UTC, proved by planting that default as `Asia/Manila`.
- **The school's zone is not the application's zone** (lane 51). Instants are stored and compared in UTC —
  `config/app.php`, and `+00:00` forced on every MySQL session since lane 31 — and that stays. What a person is
  told is computed in `config/school.php` (`SCHOOL_TIMEZONE`, default `Asia/Manila`) and nowhere else:
  `App\Support\SchoolTime::startOfSchoolDay()` / `startOfNextSchoolDay()` give the UTC instants bracketing a
  school day, `civilDateOf()` says which day an instant is at the school, and `dayOrNull()` returns null for a
  value that is not a real calendar date (it checks `checkdate`, because `2026-13-45` otherwise arrives at
  2027-02-13). Never write `whereDate('createdAt'|'refundedAt'|…, $deskDate)` or compare `now()` against a DATE
  column on a desk-facing screen: use the pair of instants, which both MySQL and the SQLite suite answer the same
  way. `Academicterms::covering()` and the dashboard's `activeTerms` / monthly-revenue tiles already follow this.
  In the JSX, day cells use `formatCalendarDay`; a date-only or date-and-time view of an instant uses
  `formatSchoolDay` / `formatSchoolMoment`, never `toLocaleDateString()` unqualified, and `Dashboard`'s
  greeting / `max=` "today" values may keep the browser's own clock because they are chrome, not a record. The **demo data follows the desk, not the daemon**: `tools/seed_full_demo_pipeline.php` and its end-to-end test date every `today` row with `SchoolTime::today()`, because between 16:00Z and midnight `now()->toDateString()` writes a receipt onto a day the Accounting drawer will never ask for — the seed then reports success while the screen it exists to demonstrate sits empty. Adding that import moved one line in each file, and with it four document citations, two of them bare continuations inside a single sentence.
- **A day written or filtered in PHP is the same defect as a day painted in JSX** (lane 85). Lane 51's rule
  names the shape — never compare `now()` against a DATE column — but the only instrument that enforced it read
  JavaScript: `lane50-date-day-shift.php` walked the pages under `resources/js/Pages` and opened no PHP file at
  all. Three server-side sites had
  been answering the daemon: `EnrollmentIssuer::issue()` stamped `formIssuedDate` from
  `now()->toDateString()` (so a form issued at 02:00 Manila carries the day before), `RegistrarController`'s
  certificate fallback printed that same day on paper, and `ReferenceDataController::terms()` filtered
  Active/Inactive terms by comparing it to `startDate`/`endDate`. All three now call `SchoolTime::today()`, the
  way `Academicterms::covering()` and the dashboard always have. Pinned by three cases —
  `php artisan test tests/Feature/Print/PrintControllerTest.php` (two: the stamp, and the printed fallback with
  `formIssuedDate` nulled) and `php artisan test tests/Feature/Admin/ReferenceDataCrudTest.php` (the filter, read
  at 18:00Z and at 15:00Z on the same date) — each freezes the clock with `CarbonImmutable::setTestNow` and
  asserts the UTC reading is *different*, so a case cannot pass by both clocks agreeing. Prove them with
  `php storage/app/scratch/mutate-lane85-school-day-tests.php` (one defect per promise, each requiring its named
  case to print `1 failed`, byte-exact restores, and a refusal when a needle does not match exactly once).
  **That harness died on this file's own documented trap on its first run: `str_replace()` has no limit
  parameter — its 4th argument is the by-reference `$count`** — and it fataled before planting anything, so the
  tree was never at risk; the count check moved to `substr_count()` first, then one exact replace.
  Lane 50 gained a PHP half (it scans `app/` **and** `tools/`, because PHPStan covers `app` only and `pint.json`
  excludes `tools`), reporting `UTC-DAY-STAMP`, `UTC-DAY-COMPARE`, `SCHOOL-DAY` and `PHP-UNBOUND`; prove it with
  `php storage/app/scratch/mutate-lane50-checker.php` (13 legs). Two bugs the new legs found in the checker
  itself, both of which had produced a *clean* verdict over an unread defect: the hoist pattern was built by
  concatenation and lost its closing `/` (so `preg_match` warned and answered nothing), and the bound between a
  `where('col'` and the hoisted variable was `[^;]*` — inside a fluent chain there is no semicolon until the
  last line, so it ran forward and accused `semester`, a real column that is not a day. The bound is now the
  call's own parentheses, `[^()]*`. **A leg that cannot fail is the only honest test of a new pattern.**
  Two more readings from the same batch. `mutate-lane50-checker.php` had been **red for weeks while the checker
  it proves was green**: when lane 50 refuses it prints no `N problem(s)` line, so the baseline's
  `$final['problems'] === 0` compared `null` to `0`; the harness now treats a refusal as "no counted problems"
  and separately requires every unbound cell to be *itemised* (count equals printed rows), which is the
  expectations-age class met from the exit-code side. And a pointer the sweep had cleared for months was found
  by reading: §24's `academicyears` row cites `ReferenceDataController` for "read only as a dropdown source", and
  the number it carried pointed at the **opening brace of `terms()`** — the claim lives on the
  `$years = Academicyears::all(...)` read, which is where the row points now. That number is spelled in words on
  purpose: the colon form in this file is a citation the checker reads, and this batch moved the line, so a
  pointer to the old layout would have been a false claim written into the warning about false claims. Shifting
  this batch's three files moved 45, 3 and 4 citation tokens, one of them by `--map` after reading both endpoints.
- **A refusal that names no reason is not a decision, and the screen may not supply one** (lane 86, prompt §6.5).
  Three gaps from the sweep closed in this batch. G34: `ClearanceController`'s decide action now asks
  `required_if:status,rejected|nullable|string|min:10|max:500`, and a reasonless refusal moves neither the line
  nor the clearance's overall status — pinned by `tests/Feature/Clearance/ClearanceRequirementRejectionTest.php`,
  whose honest-direction case is the one showing an **approval** with nothing typed still passes, because
  requiring text on every line would make the desk write something about a document that was simply fine.
  G42: a deactivated account is refused at the login screen, pinned by a pair in `AuthenticationTest` that
  authenticates the *same* account once it is active again, so the case cannot pass by the password failing.
  G33: `admissions` gained `rejectionReason` (varchar 500, nullable — every rejection filed before it stays
  valid and simply shows no reason, which is the truth about it), `reject()` refuses without ten characters, and
  `Admission/Show.jsx` both asks for the reason and prints the stored one on the rejected panel. The shared
  `CauseEffectModal` gained an **opt-in** `requireReason` box rather than a per-page invention: defaults off, so
  every existing call site keeps its markup and its confirm rule, and the confirm button cannot be pressed on a
  blank reason. Pin: `tests/Feature/Admission/AdmissionRejectionReasonTest.php`.
  Two traps this batch met. *`required_unless:approved,1` does not survive a JSON boolean* — the desk posts
  `approved: false` and the rule fired on the approval too, so the admission path uses
  `Rule::requiredIf(fn () => ! $request->boolean('approved'))`, which is the shape the `status`-word rule was
  chosen for in G34. *The screen's canned sentence was the defect*: `Show.jsx` wrote
  `'Requirement rejected / needs resubmission.'` when the officer typed nothing, which reads as a reason on the
  file while being nobody's words; the controller now refuses the blank and the fallback is gone, not reworded.
  **Two of six mutants survived this batch's own harness** (`storage/app/scratch/mutate-g33-rejection-reason-tests.php`,
  which ends `ALL KILLED: 6 leg(s), 0 bad` with a green control before and after). The day case compared the stored
  date with `SchoolTime::today()` — and for most hours the two clocks *agree*, so putting `now()` back changed
  nothing: the case now freezes at 18:00Z and asserts the disagreement first, which is lane 85's shape and was not
  carried into the new file the first time. The canned-sentence leg pointed at the rejection case and could never
  fire there, because a desk that typed words never reaches a `??`; the fallback only bites when remarks arrive
  null, and the only act that sends null is an approval — so the leg points at the approval case now. **A leg that
  cannot fail is not a test of the promise, it is decoration**, and the direction to check is which input actually
  reaches the branch. Third, from the whole-suite run rather than the file: two fixtures elsewhere in `tests/`
  (`ExamControllerTest`) post `admission.reject` with no body at all and went red on the new rule. The repair was
  the fixture's state, not the gate's rule — and the grep for every caller of an act across the whole tree is the
  step this file already tells you to take when a batch adds a gate.
- **A mapped citation endpoint is a claim, and must be read like a needle** (lane 86). This batch inserted lines
  into two files the authority document cites heavily. In `ClearanceController.php` the change aged nineteen
  pointers and the bounds sweep noticed **two**, because a pointer three lines short still lands on live code of
  the right file; `lane76-shift-cites.php` resolved eighteen by byte windows and named two it could not. One was
  the bare `:408` whose file sits outside its XML-byte window — taken by `refresh-docx-pass279b.php` after
  asserting live line 411 really is `downloadSlip()`'s signature, and its own first guard refused the run because
  it asserted `and :411` would already exist: the shift had written `( :411 )` in a different cell, so the premise
  was recalled, not measured. The other was `519-586`, and my first map read **605** — the method's closing brace
  — because I assumed the cited window was the whole method. HEAD's line 586 is the transaction's `});`, whose
  live twin is 600, so a map written from arithmetic extends a citation past the construct its own sentence names.
  Read both endpoints in both files; the tool will happily write a wrong one.
- **A DATE column fed `now()` files the machine's day, and no shape-matcher sees it** (lane 86 found it, lane 87
  closed it as §28.3 G-43).
  Lane 50's PHP half reads the *shapes* `now()->toDateString()` and `whereDate(...)` and answers **0** for every
  category while thirty-eight sites wrote `'someDate' => now()` into columns the migrations declare `date`:
  `admissions.evaluatedDate`, `studentrequirementsubmissions.submittedDate`, `studentassessments.assessmentDate`,
  `clearanceapprovals.approvalDate`, `idrequests.requestDate`, `enrollments.enrolledDate` and
  `enrollments.formSignedDate`. Eloquent formats a Carbon for a `date` cast in the **application's** timezone, so
  anything between 16:00Z and midnight files the day before the school's, which is lane 51's rule reached from the
  write side instead of the read side. `storage/app/scratch/lane86-probe-date-columns.php` measures the schema
  (it asks `information_schema`, not the code, because the code is the thing in question) and
  `storage/app/scratch/lane87-date-write-columns.php` binds each write to the **migrations**, so it runs wherever
  the suite runs and needs no database. It enumerated eight column names over forty-eight write sites;
  thirty-eight feed a DATE column (eight on desk paths, thirty inside `tools/seed_full_demo_pipeline.php`, which
  PHPStan does not analyse and `pint.json` excludes) and ten are instants that §1.2 says keep the viewer's zone,
  printed UNBOUND rather than cleared. All thirty-eight moved: thirty-three to `SchoolTime::today()`, and five to
  `SchoolTime::civilDateOf(...)` because the generator *ages* those rows deliberately through `now()->subMonths()`
  and an age must stay an age — `today()` would have re-dated a returning student's enrollment to the day the tool
  ran. Four desk sites carry a behaviour case that freezes the clock at 18:00Z and asserts first that the two
  clocks disagree (`AssessmentController::compute`, `IDController::create`,
  `ClearanceController::approveRequirement`, `EvaluationController::sign`);
  `php storage/app/scratch/mutate-lane87-school-day-writes.php` plants the retired write at each and requires
  BOTH its case to print `1 failed` and `lane87 --ci` to exit 1 naming that column, asks the same question of
  `RegistrarController::approve`'s `enrolledDate` (no test pins it — that site is covered only because an
  instrument reads the shape), then blinds the instrument in two copies, each of which must refuse rather than
  print CLEAN. Three findings travel with it. **The figure a tool prints is a property of how it prints**: the
  first version of lane 87 answered one row per column name for the columns it treated as its control, so it
  reported the generator's class at three writes when it held twenty-seven — a count read off a summary line is
  not a census, and a row that closes on that number closes on the print path. **A positive control that requires
  the defect to survive fights the fix**: lane 87 originally refused unless the five columns the tree carried on
  the day it was written still came back as candidates, so the first repair turned a green sweep into REFUSED;
  it now plants a throwaway file holding one write per DATE column the reader found. **Rehearsal errors can be
  the harness's**: the E2E legs take the schema from `E2E_DB_DATABASE` but the *connection* from `DB_CONNECTION`,
  and `phpunit.xml` pins sqlite, so naming a MySQL schema without pinning the connection makes Laravel open a
  SQLite file called `ems_l87_a` — eight errors that looked like a code regression. Run in CI's own order with the
  connection pinned, that rehearsal prints no finding in the generator's own desk report and a green walkthrough
  folder on the dataset it built — and states no totals, because a scratch-schema rehearsal is a surface the
  runner cannot read (lane 58's one-surface rule, which `lane58-suite-claims.php` enforces by refusing what it
  cannot bind — this batch learned it from the refusal).
- **The shift tool re-points the docx and nothing else, so markdown citations age in silence** (lane 88).
  `lane76-shift-cites.php` edits `word/document.xml`; the `.md` files are outside its reach, and the gate that does
  read them — `repo-file-line-citations.php` — only refuses a line past the end or a blank one, so a pointer left one
  row short by an inserted `use` statement keeps landing on live code in the right file and reports clean. Measured
  the same day: after three controllers gained one line, two pointers in the requirement matrix (the ID photo door,
  and its `mimes` rule) and one in the 2026-10-23 school sheet (the lost-slip handler, whose method runs three lines
  further than its range said) were all bounds-clean and all wrong by one. This file deliberately does not quote their
  old numbers in the colon form — a historical literal here is a live citation to the checker, which is how this
  bullet's own subject once flagged the file. So the re-point list
  for any batch that moves lines is: the docx through `lane76`, then **every `.md` file that cites those files**,
  each read at both endpoints rather than shifted by arithmetic — and a number that records what an engine reported
  on a past day is spelled in words instead, which is how it stops being a live pointer at all.
- **A pagination census must join the page's rows expression to the action that renders it** (lane 90, §28.3 G-40's
  second half). `storage/app/scratch/lane90-probe-pagination.php` reads `rows={…}` in every page that uses the shared
  table, finds each `Inertia::render('<that component>')` in `app/Http/Controllers`, attributes it to its enclosing
  action by byte offset, and resolves the prop's value to the assignment inside THAT method. **The probe prints one
  row per judged rows-expression, so its two readings are different units and both are true.** Re-read 2026-10-30,
  the footer answers 41 judged rows over the 36 tables, 0 unread: PAGINATED 29, COLLECTION 4, CLIENT-DERIVED 8 —
  which sums to 41 because one page can contribute several rows (`Accounting/Show` maps three arrays,
  `Assessment/Show` three, `Blocking/Show` two, and `Accounting/DailyReport` builds two collections from one
  action). The **page-level** reading, which is what the register's claims are about, is 29 pages handed a paginator
  by their own index action, 3 a plain collection (Accounting's daily report, one curriculum's subject list, the
  settings table) and 4 mapping the rows in the browser over the prop they were given. Quote the unit with the
  number: a reader who expects pages and meets rows will report a census that never drifted. No work queue is among
  the three collection pages, which retires the register's "two of those carry no pagination at all". Three
  drafts printed verdicts before any of that was measured: backslash page paths against forward-slash render names
  resolved nothing; a whole-file `paginate(` match condemned the ID hub, the student directory and the audit log while
  all three paginate; and even the action-bound reader failed on its own syntax twice — a bracket inside a `//` comment
  made a prop array "unreachable", and `[^;]*` cut a chained assignment at the semicolon inside a closure body, hiding
  the `->paginate(20)` four lines below. Both are now one comment- and string-aware balanced scan, and `through()` /
  `map()` trace back to the set they reshape. **A token `lane76-shift-cites.php` defers is a reading queue, not a
  clean skip:** this batch it deferred three bare numbers; two belonged honestly to another file (the clearance slip's
  print page and its download action), and one was a pointer to
  `registrarApprovalPending` that had gone 23 lines stale, sitting beside a *different* file's bare 157 that had
  gone one line stale in the same batch. Pass 298 re-took both by run, anchored on the prose phrase that precedes
  them, after asserting the lines it names carry the claim (`205` is `return [`, `210` is the key, `158` is
  `$totalScholarshipCoverage = 0`) — and note that the bare 157 occurred twice document-wide, so a needle list would have
  moved the wrong sentence. `sweep-docx-citations.php` reads `docx-flat.txt`, so a shift that rewrites 43 tokens shows
  its true verdict only after a re-dump. Prove the instrument with `php storage/app/scratch/mutate-lane90-pagination.php`
  (12 legs): the backslash join and the anchored-variable reading must both make the run **REFUSE**, the comment-blind
  scanner and the `[^;]*` reading must move the census by name (`props spelled as`, `UNBOUND`), the file-not-action join
  must misclassify three pages as COLLECTION, `--row=` must shrink the population it prints, an unknown flag must be
  refused by name, and a byte-identical copy must change nothing. Two legs were **replaced rather than kept**:
  "strings are not skipped" and "a prop value cut at its first comma" were measured as not load-bearing on this corpus
  (the census did not move, so the leg could never fail), and in their place sits the leg that proves the unread guard
  is the thing that stopped a fake census — with the guard disabled and the join broken, the probe exits 0 and prints
  `PAGINATED 1; UNBOUND 32`, a clean-looking verdict over rows it never read.
- **A second queue wired to the same mechanism is where the contract's other half bit** (lane 91, prompt §14). The
  Admission queue now sorts through `ListSort` exactly as the Registrar one does — five allowed columns, four
  sortable headers, `tests/Feature/Admission/AdmissionQueueSortTest.php` (6 cases / 23 assertions) — and
  `lane46-filter-contract.php` answered **NO-RETURN** on it: the handler consulted `direction` and never echoed it,
  so the control resets while the list stays narrowed. Both queues now echo `sort` and `direction` in `filters`
  beside the `sort` prop, which is the honest shape: a key the desk sent must come back. Three consequences travelled
  with the edit. The sort block added twenty-one lines to `AdmissionController.php`, so **21 citation tokens in the
  authority document aged** and the sweep found two of them landing on blank lines — the shift tool resolved
  nineteen and refused a twentieth (`GONE 305-322`) whose HEAD window no longer exists byte-for-byte; it was
  re-taken by reading (the requirement-selection `orWhere('appliesTo', 'all')` and the insert loop now sit at
  `:326-343`) and passed through `--map`, never guessed at from arithmetic. The figure the register carries moved
  with it — §1.1 and §28.3 both said "35 of the 36 pages … still offer no order", and a tally that names the work
  while the work grows is lane 61's defect in a new place, so pass 300 moved both copies and the row's own
  "one of those twenty-nine queues" to two. And the fallback case's first expectation was **written from my own
  mental arithmetic**: it asked `sort=passwordHash` *with* `direction=asc` and then asserted the newest-first order,
  so the case was wrong while the code was right. It now compares the fallback request with the unsorted one and
  asserts they answer identically — an equality against the queue's own default cannot agree with a retired order by
  accident, which is the same trap this file has recorded three times.
- **A coverage census must read a claim from the place claims are made** (lane 92, prompt §8/§10/§2).
  `php storage/app/scratch/lane92-prompt-coverage.php` walks the development prompt's own headings, counts the
  enumerated lines each carries, and asks which of them the requirement matrix scores. Measured 2026-10-29:
  **73 headings, 39 numbered, 48 carrying enumerated lines, 357 lines total — 28 covered (229 lines), 0 PARENT-ONLY,
  0 UNVISITED, 20 UNNUMBERED (128 lines)**, buckets asserted equal to the population, and the run refuses when they
  do not add. Three of its own defects came out before that figure was trustworthy, and each is a class this file
  already names. **(a)** It read *itself* as the prompt: `$argv[0]` is the script's path, not an argument, and a probe
  that finds no `#` headings in its own source prints "no headings" — the refusal was the only thing that surfaced it.
  **(b)** `^§?\s*(\d+)` **without `/u`** binds the `?` to the second byte of the two-byte `§`, so the pattern silently
  *required* the sigil and rejected all 140 bare `5.1` cells — the census credited zero headings while tracing them.
  **(c)** A prose `§NN` is NOT a coverage claim: the section that lists the unvisited headings names each one with its
  sigil, and a prose-reading census reported them visited. Coverage is now read only from a markdown column whose
  header *is* the section column (`§`, `Section(s)`, `Prompt §`, or a per-line `§4 line` header), because a header rule
  that accepted anything starting with `§` or containing "prompt s" claimed 46 of 48 visited. An open word is a verdict
  only in a column that can hold one, and a number is a claim only in a row that scores it.
  **The census found real gaps, and they are now G-44 … G-50** in `Documentation/Prompt-Requirement-Matrix-2026-10-26.md`
  §10, each verified by opening the named file rather than trusting the sweep: **G-48** `Students` declares no
  `$hidden` where `Staffusers.php:30-33` hides `passwordHash`, and `StudentController::show()` passes the whole model,
  so a student's credential hash ships in every Student-360 payload; **G-49** the same action eager-loads
  `enrollments.clinicrecords` and `resources/js/Pages/Students/Show.jsx:506-519` prints `cr.findings`, with no field-level gate — the
  prompt's own worked example of what must not be shared; **G-45** `afterCommit` appears 0 times while both
  notification listeners are `ShouldQueue` and `notifications` has only a non-unique index (`:20`); **G-46**
  `DashboardController::queueCounts()` counted institution-wide `Pending` while `EvaluationController.php:91-97` is
  `CollegeScope`-filtered over `Pending` *and* `ReturnedToEvaluation` (the lane-47 family, live, and closed by lane 96); **G-44** declines and
  rejections notify nobody; **G-47** the bell row is message-plus-time, no deep link, and a non-recipient's mark-read
  returns `{ok:true}`; **G-50** `documents` shares the private root with the served `local` disk. One sweep claim was
  disproved on the way: `'clinic' => ['clinic.view', …]` in `RbacSeeder.php` is a module declaration, not a role
  grant. §10's privacy-programme rows (notice, lawful basis, retention) went onto the decision sheet as items 7 and 8
  with the header tally moved — grep for `Data Privacy|RA 10173|consent|lawful basis` across the tree answers nothing,
  which is the correct engineering outcome and not a code gap. **And §2's own claims verified, correcting a belief
  this project carried: the checkout is Laravel 13.23.0 (`composer.json` `^13.8`, `artisan --version`), not 11** —
  a version note repeated across working memory was inherited, never re-read.
- **A register that states its verdict in prose gets a column, and the checker reads the column** (task #43).
  The requirement matrix carries its gap rows in prose, so a verdict word sat in the middle of a cell and
  `lane94-matrix-claims.php` answered AMBIGUOUS for six of twenty-eight rows — a census of the register was not
  computable by anything but a reader. Four tables gained a `Status` column, every value read from its own row and
  cross-checked against the authority docx’s §28.3 verdict cell. The instrument’s first reaction was instructive:
  adding the column moved AMBIGUOUS from 6 to 12, because the classifier knew only built-side verbs and a row that
  began "Not built" now matched the row-wide search it had never been asked to make. So the classifier was taught
  the column — a table whose header names a `Status` cell is judged by THAT cell alone — and three legs prove it is
  honoured rather than tolerated: blunting one cell raises the ambiguous count and names the row, renaming one
  header raises it for the whole table, and planting a Built claim in the prose of a row whose cell says "Not built"
  changes nothing. Two rows then stood out as `NO-ARTIFACT` — a Built claim naming no file — and both were made
  resolvable by naming the files a grep confirmed, which is where the column earns its keep: nine rows now read
  Not built, none is ambiguous, and every built claim points at a path the repository holds. **A markdown table row is
  ONE line, and an edit that wraps it mid-cell does not fail — it shrinks the table.** Re-typing this row with a hard
  break inside a cell made the next run read 27 of 28 gap rows and 33 artifacts instead of 47, a quiet loss of a whole
  row and fourteen pointers; the check is the tool's own row count, so re-run it after any edit to a table cell, and
  never trust a green exit as proof the table still has the rows it had.
- **A dashboard badge and the list under it must be one predicate** (lane 96, prompt §8, §28.3 G-46).
  `DashboardController::queueCounts()` restated each desk's filters as its own query and drifted in three ways:
  `admission` and `evaluation` counted the whole school while the pages behind them are bounded by the desk's
  college, `evaluation` counted `Pending` while its desk also lists what the Registrar returned, and `assessment`
  counted **enrollments** where its page lists **fee sheets** (`accounting` and `id` had the same subject slip
  mirrored). Now the badges are built from `App\Support\DeskQueues`, which carries the population only — no eager
  load, no search, no order — and `ClinicController::index()` / `IDController::index()` build their own lists from
  that same class, so those two pairs are one call. **Then the fix produced its own mirror.** `CollegeScope::ofAssessments()`
  was added because handing a `Studentassessments` builder to `ofEnrollments()` asked that model for a `course`
  relation it does not have — and scoping the two assessment badges by college was wrong too, because
  `AssessmentController` and `AccountingController` apply no `CollegeScope` to their own lists: a bounded desk would
  have read a badge of 1 over a page of 2, which is §8 with the sign flipped. The scope came **out of the badge** and
  the helper went with it. Before deciding that, the live table was asked rather than reasoned about: the only
  college-bound accounts are six office-7 teachers (`dept.caf` … `dept.cte`) holding evaluation and admission rights
  alone, so no bounded account can open any of the six unbounded desks today — a dormant question, recorded in the
  matrix rather than answered by a signature implying a scope nobody applies.
  **The bounded branch is only reachable with a bounded account.** Three of five
  new cases passed while that relation bug was in the code, because they ran as a staff account whose `unitId` is null and
  every `of*()` method returns the query untouched in that case; only the college-bound fixture executed the line.
  So a count-equality test written solely for an unbounded desk certifies nothing about scoping — pair them.
  `tests/Feature/Dashboard/DashboardQueueCountMatchesDeskTest.php` (6 cases, 35 assertions) compares each badge to
  the number the desk itself prints in **both** directions, and `storage/app/scratch/mutate-g46-dashboard-queues.php`
  (4 legs) is what showed the second direction was missing: its `admission` leg **survived** on the first run,
  because the college case asserted only the `evaluation` badge — the admission half of G-46 had no test until that
  leg refused to die. A second leg answered `needle matched 0 times`, which is the harness's own age and not a
  surviving defect; both were repaired before the run answered `ALL KILLED: 4 leg(s), 0 bad`. `blocking` is
  deliberately **not** unified: its badge counts enrollments whose
  first open box is Blocking while the page lists timetable blocks, and picking one silently is a school decision —
  it is item 9 on the 2026-10-29 sheet. Four desk lists (assessment, accounting, registrar, clearance) still build
  their queries inline, so for those the agreement is *tested*, and `DeskQueues`' own docblock says which are shared
  calls and which are only measured to agree; moving the rest onto it is
  the next step. `lane76-shift-cites.php` refused line 44 of `DashboardController.php` as AMBIGUOUS because the `$user =
  $request->user();` line I added made a second identical line exist — resolve by reading, pass `--map=44=42`, never
  by letting the tool guess. The same batch moved the ID and Clinic controllers, and a pointer in *this* file's own
  pass-244 paragraph aged with them: the Issued tab's predicate it quotes is now lines 66 to 71 of that controller.
  A markdown pointer is invisible to `lane76` (it edits `word/document.xml` only), so the re-point list for a moved
  file is the docx **and** every `.md` that cites it — `repo-file-line-citations.php` refuses only a line past the
  end or a blank one, and a comment that replaced a query reads clean.
- **A list table sorts in the query, and the header may offer only what the handler obeys** (lane 88, prompt §14,
  §28.3 G-40 contained not closed). The Registrar queue had one order since it was written. Sorting now has one
  mechanism — `App\Support\ListSort::apply($query, $request->input('sort'), $request->input('direction'), $allowed, $fallback, $stable)`,
  with the two request values read in the ACTION rather than inside the helper: `lane46-filter-contract.php` judges
  whether a handler obeys the keys its page sends, and a key consumed one call deeper was reported DEAD-SEND until
  the binding sat on the controller's own line (the checker was right to be suspicious; the fix was to make the
  contract visible, not to teach the checker to look elsewhere) — and one control, in
  `resources/js/Components/ui/DataTable.jsx`. Three rules came out of building it. **Server-side, always:** these
  lists paginate on the server, so a client sort orders the twenty rows already on screen and presents that as the
  queue — the lane-47 page-tile defect in another costume. **A column name cannot be bound as a parameter**, so the
  allowed set is a PHP array held by the handler and an unlisted request key falls back rather than passing through;
  the direction is reduced to `asc`/`desc` for the same reason. **The handler echoes what it applied** plus the
  set it would obey (`sort.column`, `sort.direction`, `sort.available`), because a sort the server obeyed but never
  reported is a header that resets while the list stays narrowed — the exact defect lane 46 owns for filters. The
  header button renders only when `sort.available` contains its key, so a screen cannot offer an order the save
  ignores. `$stable` (the row's own key) is a tie-break, not decoration: without it two rows equal on Year Level can
  shuffle between pages, which reads to a desk as a record duplicated or lost. Pin:
  `tests/Feature/Registrar/RegistrarQueueSortTest.php` (8 cases, 31 assertions), proved by
  `php storage/app/scratch/mutate-g40-list-sort-tests.php` (4 legs: allow-list bypassed, direction passed through,
  sort never echoed, sort never applied — each must make its named case report `1 failed`). **One leg survived on
  its first run and the fixture was the defect**: the three students were created Zulueta, Magsaysay, Bautista, so
  `enrollmentId desc` — the old hard-coded order the leg restores — returned exactly the surname-ascending list the
  case asserted, and the mutant passed. Re-pick the fixture so the target order differs from BOTH id orders; the
  same lesson lane 79 recorded as "a mutant that survived proved the fixture was wrong, not the guard". What is
  still owed: 28 of the 36 pages that render `DataTable` offer no order, while the pagination half has been weighed
  and needs no work, so §28.3 keeps G-40 leading `Open` on purpose — closing a register row on one wired screen is the overclaim this
  file exists to refuse. The stable tie-break has no test, and that is stated rather than papered over: on SQLite
  two tied rows usually return in insertion order anyway, so a leg asserting a failure there would measure the
  database's mood, not a defect.
- **A sort default is the list's own decision, and the screen half of a mechanism needs one definition too** (this
  batch, prompt §14, G-40's third wired screen). `ListSort` answers `desc` when a request carries no direction —
  right for a queue of newest-first records, wrong for a directory of surnames, where opening at Z is a defect a desk
  sees on arrival. The helper was not widened: the handler asks for `asc` on its own behalf, so each list states its
  default beside its fallback column, and `StudentsDirectorySortTest.php`'s first case (7 cases / 22 assertions,
  proved by `mutate-g40-directory-sort-tests.php` at six legs) goes red if the two are swapped, as does its fallback
  case, which compares an unlisted key against the unsorted request rather than against arithmetic done here. The screen
  side had already been copied twice — Registrar and Admission each spelled the toggle and the send-nothing-for-my-own-
  default query inline — so the third copy went into `resources/js/Components/ui/listSort.js` and the two earlier
  pages now call it. Refactoring a working screen with no JavaScript suite is proven in the browser, not by the
  backend test: the directory header sent `?direction=asc&sort=schoolId`, reordered to it, reversed on the second
  click, and kept both the search term and the order through a filter submit; the same two clicks were then re-taken
  on the two refactored queues, because a page that stopped sorting after a shared-module swap would still leave every
  Pest case green.
- **A JSX field name no model supplies is invisible to every gate, and a page can print the word `undefined` at a
  desk for as long as nobody looks** (this batch, found by opening the running app). `Academicyears` holds
  `yearLabel`, `startDate` and `endDate` — there is no `yearStart`, `yearEnd` or `year`, and no accessor or `$appends`
  anywhere in `app/` (measured by reflection, not recalled). Nine sites in five pages built an academic-year label
  from those names: the clearance queue's term column, its period dropdown, the detail card's caption and three more
  on the periods screen interpolated `undefined` into what a Registrar reads, while the Admission intake and case
  pages and the Examination form used `|| ''`, so a term choice read "1st" with nothing to say *which* year's first
  semester it was and a case page printed a bare semester beside the dash it reserves for absence. PHPStan does not
  read JSX, ESLint checks syntax not data, Vite builds it happily, and no test asserts a rendered string — this is
  lane 71's "a sentence states how the code behaves today" class reached from the screen side. All nine now read
  `yearLabel`. The guard is `tests/Feature/Clearance/AcademicYearLabelSurfaceTest.php`: the legal names are DERIVED
  from the migrated table plus the model's own `$fillable`, casts and `get…Attribute` methods; `const ay =
  …academicYear` aliases are resolved, so a read cannot hide behind a local; and a `|| ''` fallback fails as firmly
  as a bare interpolation. Two instrument defects came out of writing it: the scanner read only the FIRST
  `receiver.NAME` on a line, so a guard clause like `ay?.yearLabel ?` masked the planted field two tokens later on
  the same line, and a page promise phrased as "the file contains `yearLabel`" survived deleting one of its two
  sites — so the name is matched everywhere on a line and its occurrences are counted.
  `mutate-year-label-surface-tests.php` plants three defects, each killed by the case that names it. Its fourth leg
  is WITHDRAWN and the withdrawal is the record: the planted bytes were verified on disk, yet the harness's own child
  process reported the case green, while the identical mutation applied from a shell fails it on its first
  assertion. A leg that cannot fail is decoration; a leg that reports a real guard as surviving is worse, so it is
  stated rather than folded into the tally (task #47 carries the revisit). The same session wired the campus
  clearance queue as the fourth sortable table — `ClearanceQueueSortTest.php`, whose case and assertion totals are
  re-taken beside its class name by `lane107-retake-class-figures.php` rather than carried here in prose, because a
  figure in a sentence no instrument reads is the one that rots (the case itself was re-cut when CI's MySQL job
  refused the sequence it
  had been remembering, so the class grew three assertions), five legs
  all killed — which added two shapes the other three do not have: the tiles are cloned from the builder BEFORE a
  name sort can introduce a join, and Academic Term, Student Type and Standing are derived per page, so their keys
  stay out of `sort.available` rather than offering an order the query cannot answer. That clone-before-the-join
  ordering has no mutant leg and says so: the join is one-to-one through a NOT NULL key, so no fixture can move a
  tile count, and a leg asserting one would measure a state that cannot exist.
- **An ENUM is not a string, and a sort sequence typed into a test is a claim about whichever server was open**
  (this batch, prompt §14, G-40's fifth wired table). The Assessment queue's sheet carries neither a surname nor a
  school number — it points at an `enrollments` row, which points at a `students` row — so ordering it by the
  student's name is a **two-hop join**. `ListSort` now takes either spelling (`join`/`foreign`/`local`, or `joins` as
  a list of the same triples, each `local` naming the base table or an earlier hop's alias `srt0`, `srt1`, …) through
  one private method, so a screen cannot order by a join its handler never wrote; the bare column name is qualified
  with the hop that holds it. Proved by `mutate-g40-assessment-sort-tests.php` (eight legs, all killed), two of which
  live in the helper itself: `hop-qualified-from-first` and `chain-reversed`. A sixth table joined the same day — the
  cashier's collection queue, the first wired screen whose echo contract carries three keys at once (a queue TAB, a
  search and an order) and the first whose two money columns were built to disagree with each other, so a handler
  that read the wrong entry out of its own list cannot satisfy both — proved by
  `mutate-g40-accounting-sort-tests.php` at ten legs. Three of its document pointers were re-taken **by hand, not by
  `lane76-shift-cites.php`**: the numbers 243 and 290 are also the tails of another file's ranges (`Preflight.php:86-104`
  whose application-key rule runs from 228 to 243, and `ClearanceController.php:290-304`), so a map by number would
  have rewritten a true pointer into a different file — the prefix-of-a-longer-citation defect met from the tail side,
  and the reason `--skip=N` is a queue handed to a reader rather than a clean run.
  **The seventh table turned that server-dependent ordering into a rule about what a screen may OFFER, and the owner
  reversed that rule the same week.** The clinic queue's `studentType` and `academicStanding` are both ENUM and both
  already sit on the enrollment row the desk reads, so the usual reason to leave a column unsortable — the query cannot
  reach it — did not apply, and they were left out anyway. **Ruled 2026-10-31: an ENUM column may carry a header,**
  because the order the school's server applies is the declaration order the school itself wrote into the migration
  (`firstYear, continuing, transferee, shifter`) — which is the order a desk wants, not an accident of the driver. What
  the ruling moves is the expectation: the suite compiles the same `enum()` into a varchar with a CHECK and sorts the
  stored word, so a case asks the connection through `tests/Support/EnumColumnOrder.php` — one parse, four screens, and
  it refuses a column that names no enum on the mysql driver rather than guessing at an order. The rule for the
  remaining 28 tables is therefore: read the column's MySQL type (`information_schema.COLUMNS.DATA_TYPE`, not the
  model), offer the ENUM, and never type its order. The cases the ruling added, each named on the same line as the
  figures its delivered run reported: `ClinicQueueSortTest.php` (12 cases / 42 assertions) for type and standing,
  `IDQueueSortTest.php` (12 cases / 47 assertions) for the same two on the ID desk, and
  `RegistrarQueueSortTest.php`, whose figure was re-taken from the delivered run at 8 cases / 31 assertions and now
  carries three things rather than one — the Status header that had been offered since the first wiring and answered
  by nothing, that queue's own Student Type and Final Standing headers under the same ruling, and the payload case
  for its one-hop join (§28.3 G-51). Two of the eleven legs of
  `mutate-g40-clinic-sort-tests.php` plant the JOIN rather than a column (`one-hop-spec-read-as-no-join`,
  `surname-spec-loses-its-join`), which is the shape a wrong-column-only harness never sees: a surname ordered with no
  join does not print a wrong sequence, it fails to build a list at all. **And the browser read was bounded rather
  than padded:** `lane102-probe-clinic-queue.php` answers that live `ems` holds ONE row waiting at the clinic box, so
  no click there can show a sequence change — what the running app proved was the contract (three headers send `sort`
  and `direction`, the second click reverses, and a filter submit keeps both the typed search and the arrow) while the
  ordering is pinned by a fixture that writes four. Ask how long the queue is before claiming a desk demonstrated an
  order; lane 47's rule for a page-two defect is the same rule from the sequence side. The browser read found one
  wording defect in the shared control while it was at it: the active header's tooltip printed
  `Sorted schoolId descending` — the internal column key, on a screen a person reads. It now names the column the way
  the heading does (`Sorted School ID descending — click for ascending`), because prompt §11 asks for labelled controls
  and a key is not a label. `aria-sort` was read from the same DOM: `descending` on the active header, `none` on the
  two other sortable ones, absent on the three that are not controls — which is the a11y evidence one row cannot give
  for a sequence.
  **The eighth table, the ID desk, made a TAB the third control an order must survive.** Its queue tab is not a filter:
  it chooses which population the list is built from — the enrollments whose first open box is office 22 against the
  ones whose ID box is already signed — so `ListSort` runs on the clone of the base the tab chose, and the two tiles
  and both tab counts still clone the bases themselves. Pinned by `IDQueueSortTest.php` (12 cases / 47 assertions), and a
  harness can now plant the swap directly
  (`order-applied-to-the-other-base` in `mutate-g40-id-sort-tests.php`, thirteen legs, all killed) and require the
  tab's own case to go red while the order stays perfectly formed. The tab must also come BACK: the filter echo carries
  four keys now, and an un-echoed tab is lane 46's NO-RETURN seen from the side a desk switches rather than types. Its
  withheld column is painted rather than stored: ID Status
  is the status of the row's FIRST request, and ordering by it would decide what two requests on one enrollment
  amount to — a question the school has never been asked, so no header is painted rather than an invented one. (The
  ENUM columns it withholds at the same time were given headers by the 2026-10-31 ruling below, and the case that
  asserts `sort.available` was re-cut to match.) This
  queue is also the first wired screen whose browser read could show a sequence at all: live `ems` holds two
  enrollments waiting at the ID office, and the running screen swapped them (Alcantara at rest, Sotto ascending, back
  again on the second click). Two instrument readings from the same batch. **A comma-list citation is one token and the
  shift tool defers its tail:** the document pairs the blood-type validation rule with the write of the same value in
  one citation — the rule's own line, then a comma, then a bare continuation. `lane76-shift-cites.php` resolved the
  head and deferred the bare tail as BARE-DEFERRED, so writing the head alone would have left the pair half-moved, and
  a half-moved pointer looks valid in a way a missing one never does. Both numbers were skipped and the whole pair
  re-taken by hand, each endpoint asserted byte-for-byte in HEAD and in the working file before the write. **And the
  obvious needle for that tail belongs to another file:** the run-tail of the retired number matches a live pointer in
  the service provider, so the pass guards that neighbour's own count on read-back — lane 69's "a needle can be
  another citation's tail", met again one batch later.
  **The same batch went red on CI's MySQL job while this box was green, and the defect was in a test's memory.**
  `studentclearances.overallStatus` is declared `enum('pending','approved','rejected','waived','incomplete')`, and
  MySQL sorts an ENUM by that **declaration index**, while the suite's `enum()` compiles to a varchar with a CHECK
  (`SQLiteGrammar::typeEnum`) and sorts the stored **word** — so `sort=status&direction=asc` really does answer
  `pending, approved, rejected` on the school's server and `approved, pending, rejected` here. The clearance case had
  asserted the second sequence as though it were the only one. It now asks the connection which order its own column
  has (`SHOW COLUMNS … LIKE 'overallStatus'`, parsed; alphabetical elsewhere) and its fixture was re-picked to
  Pending, Rejected, Approved so that **four orders give four different sequences** — id up, id down, the enum's
  declaration order, the string's alphabetical order — which is what lets the assertion fail on either surface. Same
  family as lane 79's spent counters and lane 63's rehearsal schema: a literal sequence in a test is a reading of one
  database, not a property of the code. The two drivers now agree because the expectation is derived: re-run on a
  scratch MySQL schema with `DB_CONNECTION` pinned, the way lane 79 records, every sort class passes on MySQL 8 as
  well as here — and no total is quoted for that rehearsal, because it is a surface the runner cannot read (lane 58's
  one-surface rule, which this file has been caught by before).
- **A sort that joins a relation widens the row it lists, unless the select is pinned** (lane 107, §28.3 G-51).
  `ListSort` joins the table that holds an ordered column so the ORDER can be taken from it — and a builder with no
  select list emits `select *`, which then answers the joined table's columns too. On the Assessment and Accounting
  queues the surname order joins `enrollments` and `students`, so every fee-sheet row shipped the student's
  `passwordHash`, `birthdate` and `contactNumber` to the desk. `Students::$hidden` hides the hash on its own model,
  and that is precisely why G-48 read as closed while this stayed invisible: the value was not riding on a `Students`
  model, it was an attribute of the row being listed. The helper now pins the select to the base model's own columns
  whenever it adds a hop, so a desk row is one shape whatever header was clicked. Three readings worth keeping.
  **(a)** Two of this batch's own ENUM sequence cases had been GREEN because they read the leaked top-level column;
  they went red the moment the pin landed and were re-cut to read `enrollment.studentType` — where the screen already
  reads it — so the leak, not the order, was what had made them pass. **(b)** The claim was in a docblock: "the
  select list stays the model's own (`table.*`)" was written as a property of the mechanism and nothing made it true,
  which is lane 71's class reached from the helper side. **(c)** A column on a hopped screen is spelled BARE, because
  a hop is joined under a private alias (`srt0`) and `ListSort` obeys an already-qualified name as written —
  `'column' => 'enrollments.studentType'` on those screens asks the query for a table it never joined and answers a
  500 at the desk, not a wrong sequence; `mutate-g40-enum-offer-and-select-pin.php` plants that spelling too. Pinned
  by a case on each of the three queues whose order can join, and by the same harness's legs that withhold the ENUM
  key again on each. **A harness must restore between legs**: the first cut planted a mutation, ran the next leg
  against the mutated file, reported "needle matched 0" on its own second pin leg, and came back with a red final
  control over a clean tree — one plant poisoning its sibling's measurement, in the instrument rather than the suite.
  **The class is bounded, and that was measured rather than assumed:** no other join in `app/` feeds a *model payload*
  without naming its columns — the ID desk's first-request counts, the years-completed scope on `Enrollments` and the
  standing service's grade-history read each state their own select, and every other `->join(` in the tree sits
  inside an aggregate or a `whereHas` subquery — so pinning the select in the shared helper is the whole repair, not
  one instance of a family. **Prove a payload claim on the wire, not only in the suite**:
  `storage/app/scratch/lane107-probe-live-queue-payload.php` asks the three queues through the HTTP stack on live
  `ems` and reports WHERE each sensitive field sits, because the first version searched `json_encode($rows)` and
  called the queue's own eager-loaded `enrollment.student.birthdate` a G-51-shaped leak — it is not, and the two
  claims must be printed apart (lane 62's "no ID on this paper" is not "this record's ID is absent"). That read
  confirmed `passwordHash` absent everywhere and one column set under every order, and it is what found **G-52**,
  registered open: the queues load the whole student to paint a name, so a cashier receives birthdate and contact
  number that no screen of theirs reads. It was deferred out of that batch because each handler moved ages its
  citations again; the next bullet records what the following batch actually narrowed, and why the rest stayed open.
- **A column list is a claim about a table, so read the table before writing it — and a case that compares
  requests must assert each one answered** (lane 108, §28.3 G-52 — narrowed on the three queues the live read named,
  and the rest is measured, not guessed). Narrowing those loads to the columns a desk actually paints was written from
  the screen, not from `students`, and the first draft named `id`: that table keys on `studentId` and has never held
  `id`, so all three queues answered **500** — and every sort class stayed green, because an order case reads row
  sequences out of the payload and never asks the response for its status. The repair is the column list; the durable
  changes are two. **(a)** Each comparison case now calls `assertOk()` on every request it makes, because an error
  page and an unsorted list both arrive as "the two lists differ"; `lane108-pin-the-nested-student-payload.php`
  counts those calls per class and refuses on a copy where one was deleted, which is how the reader was proved rather
  than trusted. **(b)** `lane108-narrow-the-queue-student-loads.php` reads every name it is about to write out of the
  `students` **migrations** and refuses by name — `--cols=id` is its positive control, and the block binder excludes
  a column that belongs to another table declared in the same migration file, or the guard accepts a wrong target.
  **(c)** The class is wider than the three handlers and that was counted, not assumed:
  `lane108-census-whole-student-loads.php` binds each whole-student eager load to the action that runs it and to the
  `Inertia::render` inside that same action, then asks the page which columns it reads. Measured 2026-10-30: **25 load
  sites over 25 controllers — 24 answer a page that reads nothing beyond the name, 1 (Admission's case screen)
  legitimately edits them.** The 24 split, in the tool's own buckets, into **12 desk list sites, 8 detail screens and
  4 print paths**. A suffix is not a demographic field, it is half of a NAME, so it sits in the identity set: sixteen
  of those rows belong to seven pages that also paint it, and a five-column list on any of them loses "Jr." — that
  mis-classification was caught by reading the buckets, not by the count. What is narrowed: the Registrar, Accounting
  and Assessment queues (four sites, including Assessment's pending-evaluation list). What is not: the list, detail
  and print sites above, because a print view is judged on paper rather than source and a queue page is judged in the
  browser — so the register row leads `Open`, and the census is the instrument that will show it falling.
  **(d)** That reading was the page file alone, and both halves of its claim were the instrument's. A desk paints
  through shared components, so `Evaluation/Show` — which renders the profile form that edits gender, birthplace,
  birthdate, citizenship, religion, civil status, contact number, telephone, semesters completed and years in
  institution — was filed as name-only, and narrowing from that reading would have emptied a form on the screen while
  every payload test stayed green, because those cases assert the keys the SERVER sent, not the keys the screen reads.
  The graph reader then showed its own hole: it recorded only the FIRST student column named on a line, so the
  Registrar queue's row (surname, given name and middle initial in one expression) reported a surname alone, and the
  figure that moved was the census's suffix line, which it now prints beside its split. So a page's read set is
  walk-the-import-graph work, and `lane108c-check-the-narrowed-lists.php` re-audits the narrowing the first reading
  already applied: it binds each narrowed column list to the page its own action renders, refuses a page whose walk
  reached a single file, and exits 1 naming any column the page still paints. Its first cut filled
  `WHOLE-LOAD-IN-SAME-ACTION` by matching any quoted `student` string — `whereHas('student', …)` is a search clause and
  `withErrors(['student' => …])` a validation key, and neither loads a row — which swallowed twelve of the fifteen
  actions unread while the run still printed `MISSING 0`; the bucket is anchored on the load call now, and prints the
  literal it decided on. `lane108c-probe-walk-depth.php` then measured the walker's depth bound instead of trusting
  it, taking its population from the audit's own `--json=` rather than re-deriving the binding (its first cut did
  re-derive it, walked nine wrong pages, printed NO-PAGE for every one, and still reported "inadequate on 0 page(s)" —
  a clean verdict over rows it never read); it now refuses when it opened fewer pages than the audit judges. A green
  answer from a new reader is not evidence: prove it with `mutate-lane108c-narrowed-audit.php`, which plants a
  one-column list, a planted suffix read, a planted non-demographic read, an `email` read that must NOT classify, and
  a real whole load that must reach its own bucket.
  **(e)** Two defects came out of that harness's first three runs, and neither was in the tool it was written to
  prove. *The mutant's own bytes:* the plant was built as `"row?.student?->{$col}"`, and PHP's interpolation put a
  **property arrow** into JavaScript — `student?->suffix` — which the classifier is correct to ignore, so two legs
  reported "the tool cannot see my plant" across three runs while the tool was right and the mutant was unreadable.
  A plant must now be verified against the exact text the leg claims to have written: `lane108c-child-sees.php` is
  run by a fresh child BEFORE the measurement, and a leg that cannot make that child see the read is reported as the
  harness's failure, never as a surviving defect. The same reflexion tested and **disproved** the other suspicion —
  a parent-then-child file-visibility problem on this OneDrive-backed tree: `lane108c-probe-child-read.php` prints
  the parent's read, a child's read and the auditor's own verdict together, and the child sees the plant.
  *The no-op that is not an error:* a docx pass whose replacement **embeds its own needle** (an append) can never
  make the needle disappear, so a read-back demanding `old count == before − 1` refuses a correct write and restores
  its own backup — pass 335 did exactly that on its first run, and the fix is a per-edit residual expectation plus a
  marker for the clause that was added. `lane107-retake-class-figures.php` learned the sibling rule the same day: a
  class figure the delivered run already equals is a SKIP, and a batch that moves four classes while three are
  already true must still write the four — refusing the whole run on a byte-identical pair made the tool unusable on
  exactly the case it exists for, and what remains guarded is an empty plan with no skips at all, which means the
  needle list has aged.
- **A builder's pinned select travels into every clone of it, and `selectRaw()` appends** (lane 108, found only by
  CI's MySQL job). The G-51 pin put `admissions.*` on the admission queue's builder, so the status tile's
  `(clone $query)->reorder()->selectRaw('admissionStatus, count(*) as aggregate')` was no longer a two-column
  aggregate: it selected the star *and* the count, and `ONLY_FULL_GROUP_BY` answered **1055** — a 500 at the desk for
  clicking a header. SQLite has no equivalent mode, so the case was green here, the tile was green here, and the
  whole suite was green here; the fix reached the document through the runner. `select()` **replaces** the column
  list and `selectRaw()` appends to it, so an aggregate cloned from a builder the order already touched must name its
  own columns. The other shape — clone before the order, which is what the clearance queue does — was not taken here,
  and this clone sits safely after it for one measurable reason: the hop is many-to-one through
  `admissions.studentId`, which the migration declares NOT NULL with a foreign key, so no row the unsorted queue
  counts can drop out. Say that reason where the clone is written, or the next reader cannot tell a safe late clone
  from a leak. `lane108-tile-replaces-its-columns.php` applies the change line-neutrally and now reports ALREADY
  APPLIED on a re-run, because a one-shot patcher whose needles this batch consumed refuses with "needle matched 0"
  and reads like a broken tool. `lane47` now carries the durable rule as its own verdict: **PINNED-SELECT** fires
  when an aggregate is cloned off a builder that `ListSort::apply()` already ran and appends with `selectRaw()`
  without naming its columns first, and the tree today answers `PINNED-SELECT 0, plus 1 that name their own columns
  first`. Its harness plants both halves — the admission pre-fix bytes for the new verdict, and a true permutation
  of the clearance handler's lines for SILENT-ZERO, whose old two-statement anchor had silently stopped matching
  the day that desk moved its tile ahead of the order; the case reported itself asleep rather than pretending to
  kill anything. `lane47b-probe-the-pinned-verdict.php` is the no-write version: it refuses unless the three
  expressions it mirrors are still verbatim in the checker, then judges the real chain and the same chain with the
  `select()` removed in memory — the separation a harness leg would otherwise only claim.
  The reproducible surface for the original defect is a scratch MySQL schema —
  `E2E_DB_DATABASE` **plus** a pinned `DB_CONNECTION`, lane 87's rule, or Laravel opens a SQLite file named after the
  MySQL schema — driven against the header that joins, not a reasoning about which driver is stricter.
- **A desk list is narrowed by byte offset, and the evidence that it is safe lives past the page's own file**
  (lane 108b, §28.3 G-52's continuation). Thirteen more queue sites came down the same way, and three things made
  that possible rather than reckless. **(a) One reader decides which sites are next.**
  `lane108-census-whole-student-loads.php --json=` emits every whole-student load with the byte offset of its own
  literal and the bucket the page falls in (`LIST` / `DETAIL` / `PRINT`);
  `lane108b-narrow-the-list-loads.php` consumes that JSON and rewrites at those offsets. It refuses if the bytes at
  an offset are not exactly the quoted relation path — which is what happened first, because the census recorded the
  capture-group offset (one byte past the opening quote) while its comment claimed it had added one: every row was
  refused and nothing was written. A needle list for this job would have matched `whereHas('student', …)` and
  `'student.aliases'` too, which is lane 69's rule arriving in a rewriter. **(b) The census counts one row per CALL
  SITE, not per action** — Accounting's daily report loads the student twice — so the figures it had just written
  into the register moved (26 sites over 25 controllers, 13 list, 8 detail, 4 print) and docx pass 331 re-took them
  from the instrument rather than patching the prose. **(c) "This page reads nothing beyond the name" cannot be
  decided by reading the page.** `lane108b-page-read-set.php` walks the page's import graph and prints every line
  that names a `students` column; its first run resolved exactly one file per page — the separator style differed
  between `dirname()`, `realpath()` and the hand-built root — and printed a clean-looking verdict over a tree it
  had not read, which is why the tool now REFUSES when any page resolves no local import at all. What that reader
  found: no desk list paints a student-only column, and a suffix is half of a NAME, so a five-column list on the
  four detail screens that paint one would have dropped "Jr." — the rewriter refuses any site whose page reads a
  suffix instead of widening the set on its own. **Prove the offset formula against the bytes, not against the
  plan:** applying highest-offset-first means each literal you already wrote is later shifted by every edit below
  it, so the first read-back reported three FAILs on a diff that was perfectly correct.
  `lane108b-check-the-offset-shift.php` recomputes plan-plus-shift for each literal and additionally requires that
  the *unshifted* offset does NOT hold the new text — a shift that cannot be falsified is a story, not a
  measurement. **(d) The column guard is one module now** (`students-column-guard.php`, read off the `students`
  migrations and bound per `Schema::` block so another table created in the same file cannot lend it a name);
  `lane108-narrow-the-queue-student-loads.php` and the new rewriter both require it, and two answers to "is this a
  students column" is the defect this file has refused in lanes 6, 46, 69 and 90.
- **A bad column shows a different face on each driver, so one case must carry both guards** (lane 108b, measured
  on both surfaces rather than inferred). With `id` in a student column list — a column `students` has never held —
  MySQL 8 throws and the feature case dies on `The response is not a view`, while SQLite answers 200 and puts an
  EXTRA KEY on the nested student, so only the exact key-set assertion refuses. The pass 330 row said "all three
  queues answered 500", which was the MySQL half only; pass 331 states both. Pinning is now: the four queue classes
  that already had fixtures gained a case each (`the_queue_loads_the_student_for_the_identity_columns_and_nothing_else`)
  and the three money queues carry the same assertions inside their existing join case. The debt is held in
  `tests/Feature/DeskQueue/DeskStudentLoadColumnsTest.php`, which lists the twelve still-open detail and print
  actions and FAILS both ways — a new bare load anywhere, or a listed site that has quietly been narrowed — the
  `AssertNoSilentSkips::ALLOWED` shape applied to a payload rule. `mutate-g52-desk-payload-tests.php` kills the leak
  (Clinic reverted to a bare load: the queue case AND the source guard both go red, naming
  `ClinicController::index`) and the bad column (Accounting widened with `id`), and it refused itself three times
  before it was honest: its filter named a case the money queues do not have (they were extended in place, and
  "No tests found" must be BAD, never a kill), its tally regex could not read a failures-only `Tests:` line, and
  its tree-clean check demanded no bare `'student'` remain in a controller whose detail screen legitimately still
  has one.
- **A presentation vocabulary has one map, and a page-local copy is a defect even when it renders fine** (lane 86,
  G-39 closed). `resources/js/Components/ui/statusLabel.js` owns the enrollment-tone map (`enrollmentStatusTone`,
  lines 13 to 26); the certificate screen was the last surface carrying its own six-key copy, which had never
  learned `returnedToEvaluation` or `returnedToAdmission` — so a returned record printed a neutral badge where
  every desk showed a hold. Fixed by reading the shared map (`resources/js/Pages/Registrar/PrintCertificate.jsx:94`)
  and **deleting** the local helper, then pinned by `tests/Feature/Enrollment/EnrollmentToneVocabularyTest.php`,
  which reads the `.jsx` sources because there is no JavaScript test suite — the method lane 69 established for
  the year-level pairing. Two lessons travel with it. *The sweep's own claim was too wide*: it said every print
  view re-declared the map, and the scan that found the one hold-out cleared the others, so the matrix row was
  re-taken whole rather than left stating a superseded census. *Deleting a construct ages every pointer written
  against it*: the citation gate went red on the certificate page's tone-map range — lines 139 to 148 of a file
  that is now 137 lines — and
  re-pointing the number would have left the sentence still promising a re-declared map. Re-take the cell, not
  the digit. `proposed`/`confirmed`/`dropped` belong to the enrolled-subject vocabulary and are deliberately NOT
  matched by the guard — one checker judging two vocabularies is the two-answers-for-one-word defect.
- **The machine's hour is read from MySQL, never from PHP** (lane 54c). Inside a booted application
  `date_default_timezone_get()` answers `app.timezone`, because the framework pins it, so a preflight row built
  on that prints agreement on every server and can never fail. The other candidates are also not the operating
  system: `php_uname('a')` carries no zone at all (`Windows NT DESKTOP-IIUKRIB 10.0 build 19045 (Windows 10)
  AMD64` on this box), php.ini's `date.timezone` names what *PHP* was told (`UTC` here, even in a bare `php -r`),
  and `localtime()` answers no `tm_zone` and no `tm_gmtoff` on Windows. The one binary that quotes the machine is
  the database: `SELECT @@system_time_zone` is `UTC-11` on this box, which is the Windows zone name.
  `ems:preflight`'s `Host time zone` row compares that offset with `SchoolTime::zone()`'s and is **WARN, never
  FAIL** — nothing stored moves, because the session is forced and every civil day comes from `SchoolTime` — and
  when the source answers nothing usable (a zone written as a name rather than an hour, or SQLite, which has no
  `@@system_time_zone`) the row says it could not read the machine's zone instead of guessing that the clocks
  agree. `PreflightTest` pins the two shapes *driver-conditionally*: it calls `Preflight::hostUtcOffset()` in the
  test and requires null on SQLite and a `±HH:MM` string attributed to the database on MySQL, because the first
  version of that assertion assumed the suite always runs on SQLite and went red the moment the class ran against
  MySQL (measured locally; CI's MySQL job runs the same class, so the local run is not a special case). The gap
  in hours is computed from the two offsets, never written into the sentence, so a changed `SCHOOL_TIMEZONE` and
  a half-hour zone both read true. `storage/app/scratch/mutate-lane54-host-zone.php` plants six ways the row
  could stop measuring — flip the comparison, call a refusal an agreement, answer when the source is silent,
  hard-code the gap, rename the refusal, resolve an unknown zone — and each is killed by the test that names it.
- **A date in this repository is a BOX date: ask an outside host what UTC is before calling it the world's** (lane 74).
  Measured 2026-10-26: PHP's `gmdate` read `2026-10-26 07:23`, MySQL's `UTC_TIMESTAMP()` read `2026-10-26 07:23:39`,
  and the application's `now()` agreed — so the disagreement is the Windows clock, not one program. The same minute,
  `https://api.github.com/meta` answered `date: Sun, 25 Oct 2026 12:26:48 GMT`, the probe's own run printed the host
  clock as `2026-10-25 12:28:09` against the box's `2026-10-26 07:28:24`, and the Actions record for a push made
  then read `created_at = 2026-10-25T12:12:46Z`: **the box is 19 h 00 m ahead**, which makes the real civil day in
  Manila the day *before* the one written into every dated claim here — `SchoolTime::today()`, the packet filename,
  §1.1's revision rows, the `DEMO-OR-TODAY-01` slip. Ruled by the owner 2026-10-26: **record it, leave the clock.**
  So do not re-age anything (a batch consistently one day off beats a half re-aged batch, and commits already pushed
  carry these dates) — say which clock you mean. No instrument could see it because the guard that asks "does the
  dataset carry the school's current day" reads *both* sides from the same machine: it agrees with itself. That is
  lane 54c's and lane 72's lesson one level up — after "check which server answered the probe" comes **check which
  clock stamped the answer**. `php storage/app/scratch/lane74-probe-clock-skew.php` prints the box epoch, the host's,
  the skew, and the school's civil day under each; it refuses (exit 1, cause named) when the host answered nothing,
  when the HTTP status is 400+ (an error page is not an answer about time), and when no `date:` header arrived —
  never an "agrees" bought by not asking. Prove it with `php storage/app/scratch/mutate-lane74-clock-skew.php`
  (7 legs), one of which **plants the agreement** (`$before = $external`) because on a fast box the "same minute"
  branch can otherwise never execute — an unreachable branch in a measuring tool is the same liability as an unread
  surface. Five bugs came out of the probe's own first three runs, each a class this file already names: `use
  DateTimeImmutable;` in a non-namespaced script warns "has no effect"; `date('@'.$epoch)->setTimezone()` fatals
  because `date()` returns a string; **all four refusals exited 0** until they were moved to `fwrite(STDERR)` +
  `exit(1)` (`exit("text")` is status 0, third time); GitHub **403s a HEAD with no `User-Agent`** and the 403 page
  carries no `date:` header, so "the host sent no Date header" was a true sentence about a failed request printed as
  if it were an answer; and `CURLOPT_TIMEOUT` casts a float to whole seconds, so `--timeout=0.05` became 0 = no
  timeout and the refusal leg measured instead of refusing — an accepted-and-inert option, which is why the probe now
  asks in milliseconds and prints the value it used. Run the argument-bearing legs from the harness, not from
  Git-Bash, which rewrites `/this-path-404s` into a Windows path before PHP ever sees it.
- **A credit counts only when its own row says so, and one scope answers that question** (lane 76, prompt §7.3).
  Ruled by the owner 2026-10-26: **the department decides** — the account that records a credit already holds
  `evaluation.credits.process`, so deciding it needs no new permission and no second signature; and the three
  evidence columns join the batch as **nullable**, because a school that has not produced the paper must not be
  made to invent one. `creditedsubjects` therefore grew `creditDecision` (approved | rejected, defaulting to
  approved so a row filed before the column keeps its effect), `decidedBy`, `decidedDate` (written with
  `SchoolTime::today()`, never `now()`), and `decisionReason` (required on a refusal, **cleared** on an approval —
  a reason beside `approved` reads as a refusal nobody withdrew). Four rules came out of building it. *One
  definition of "counts"*: `Creditedsubjects::approved()` is read by the prerequisite gate, the mandatory-block
  diff and `EnrollmentReadiness::satisfiedSubjectIds()` — three readers that must not disagree — while
  `ReferenceDataController::destroySubject()`'s guard deliberately counts **every** row: a declined equivalency
  still names the subject, so narrowing the census there would let a delete orphan a decision (pinned as its own
  case, and killed by a mutant that adds the filter). *A screen may not promise what the server now refuses*: two
  Evaluation banners said credited subjects are exempt, which stopped being true on arrival until the row is
  approved. *Some rules are unreachable by a desk act*: the mandatory-block exemption only bites on a regular
  student, while `processCredits` allows only transferee and shifter records — so that case plants the row and
  says why, and a first-draft of it "passed" because a policy **403** satisfies `assertSessionHasNoErrors()` just
  as a green run does. *MySQL resolves `AFTER` in a multi-add ALTER against the column list as the statement
  began*: the three evidence columns landed in the reverse of the declaration order, measured — so §24 lists the
  server's order and the migration says so instead of claiming what was intended. The instruments:
  `php artisan test tests/Feature/Evaluation/CreditEquivalencyDecisionTest.php` and
  `php storage/app/scratch/mutate-g3-crediting-decision-tests.php`, which plants one defect per promise across five
  files and requires the named case to report `1 failed`; its no-op guard had to be narrowed from "the replacement
  text is already in the file" to "the needle and its replacement are the same text", because a **shrink** mutation
  (dropping three names from a `$fillable`) necessarily leaves a remainder that already occurs — the byte-hash
  after writing is the precise falsification.
- **An examination result is the recording office's claim until the Registrar adopts it** (lane 77, prompt §7.2).
  Ruled by the owner 2026-10-26: the desk that marks a paper does not make it official. `examresults` therefore grew
  `resultStatus` (`provisional | official`, defaulting to `provisional`, because that is what a counter entry *is*),
  `verifiedBy` (FK to `staffusers`, `SET NULL`) and `verifiedDate` (written with `SchoolTime::today()`, never `now()`).
  Four rules came out of building it. *The adoption is a separate column from the mark*: `examResult` is already
  nullable, and "nobody scored this paper" and "somebody scored it and the Registrar has not adopted the score" are
  two states a desk must be able to tell apart — so a confirmation is never inferred from a non-null mark. *One
  definition of "official"*: `Examresults::isOfficial()`, a method and not a query scope, because every reader holds
  the loaded row — the two Admission approval blockers, the Evaluation retention blocker, the departmental-exam
  prerequisite, and the three screens that paint the state. *A signature may not survive the result it was given*:
  re-recording a mark resets the row to `provisional` and clears `verifiedBy` and `verifiedDate` in the same write,
  and confirming refuses an unmarked paper because there is nothing yet to adopt. *A new right must be reachable*:
  `exam.verify` was added to the Exam module (4 permissions → 5, the catalogue 93 → 94) and seated with
  `RegistrarApprover`, which also needed `exam.view` — a confirming right on a screen the account cannot open is the
  retired `exam.verify.general` defect reborn, and `PermissionMatrixTest` now mutates the route both ways.
  The migration **backfills the rows already on file to `official` with `verifiedBy` left null**, which is a decision
  about what null means: the papers were recorded before the column existed, so the null verifier marks the backfill
  and the Exam screen says "adopted before this desk signed each row" rather than naming a person who never acted.
  Without that, an applicant already approved would wake up unexamined, and approval is refused on a provisional
  result. The demo generator writes the confirmation itself — `resultStatus` official and the Registrar desk signer
  from `desk_signer_for()`, only when a retention seat exists — because the departmental prerequisite now asks the
  *confirmed* pass, and in the E2E walkthrough the confirmation therefore sits **between** the two exam stages, not
  after them. The instruments: `php artisan test tests/Feature/Exam/ExamResultVerificationTest.php` (13 cases) and
  `php storage/app/scratch/mutate-g4-exam-verification-tests.php` (17 legs over seven files). Two defects came out of
  that harness and both are this file's own classes. **A mutant that cannot fail sleeps**: the page-tile leg first
  planted `->limit(20)` on a `SELECT count(*)`, which is a SQL no-op — the bytes changed, the verdict did not — and had
  to be re-planted as `->forPage(2, 20)` before it proved anything, so byte-identity is necessary and not sufficient.
  **And a pipe swallows the exit code you are watching**: the first harness run was read through `tail`, so 23 failed
  legs reported status 0 and the shell claimed the run clean (the third time in this project). Three smaller readings:
  the compact `StatCard` has no `title` in its signature, so a `title` written on one is an attribute the component
  never reads; `Staffusers` displays `name`, not `fullName`; and `assertSessionHasNoErrors()` cannot tell a policy 403
  from a green save, so the "the scoring desk may not confirm" case asserts `assertForbidden()`. **The whole-suite
  run caught a seventh fixture the narrow ones could not**: `HiddenBugsRemediationTest::fix1` sits at `tests/Feature/`
  root, creates an `Examresults` row with no `resultStatus` (so the schema hands it `provisional`) and asserts the
  policy approves — which is precisely what the new blocker now refuses. The repair was the fixture's state, not the
  gate's rule, exactly as with the other six. Two lessons in one failure: a batch that adds a gate must grep every
  `Model::create` of that table across the *whole* `tests/` tree, not only the files it edited; and editing a test
  file while a run is in flight voids that run's verdict, so the run must be restarted rather than patched mid-air. **Which version a
  citation is keyed to cannot be measured by comparing HEAD's bytes with the working bytes at the same numbers** —
  that pair differs for *every* window in any file the batch edited, shifted or not, so it answers nothing. The only
  discriminator is the one lane 70 costs a session to learn: print the WORKING span and read it against the claim in
  the document's own sentence. Doing that for G-4's seventeen `ExamController.php` windows showed fifteen already
  carried their claim (the shift had run), and two that never had: §1.1's example pointer cited `:308` for "the
  Course-Specific examination refusal" while that line sits inside `entranceApplicants()`, and §26's repeat-proofing
  row cited `:242` — `AdmissionStatus::Approved->value,` in the transferee filter — for a promise the `firstOrNew`
  upsert keeps. Pass 252e re-pointed both, each asserted against the line it now names before the write.
- **A return is one act at three desks, and its notice must be addressed from the destination** (lane 78, prompt
  §6.4, gap G-10). Ruled by the owner 2026-10-26 and built 2026-10-27: one permission (`enrollment.return`,
  seated with `RegistrarApprover`, `AdmissionOfficer` and `DeptEvaluator` and deliberately **not** with
  `OfficeHead` — ruling 7's reason), one route (`enrollment.return` → `ReturnController::store`), one service
  (`App\Services\ReturnRecorder`) as the only statement of what a return does, and an `enrollmentreturns` row that
  stores all six §6.4 facts in columns instead of prose. Ruling 14's `RegistrarController::returnToEvaluation()` was
  deleted rather than left beside it, and `tests/Feature/Registrar/RegistrarReturnTest.php` went with it (its five
  cases are ported into `tests/Feature/Enrollment/EnrollmentReturnTest.php`, 25 cases). Five rules came out of it.
  *A permission is not an authorization*: `authorize('enrollment.return')` checked only the right, so a
  college-bound department could have returned another college's record — the ability is
  `enrollment.returnRecord` (a name no permission may take, per the `Gate::before` collision rule) and it asks
  `CollegeScope` too, with the refusal sentence passed to `abort_unless` so the 403 page says which rule answered.
  *An affordance must be decided by the predicate that decides the act*: the Evaluation page first guarded its
  button on `can.enrollmentReturn`, but that page's `can` prop is the controller's record-scoped map and answers no
  such key — the control could never paint. The offer is now `returnOptions` (null when the policy or the record's
  state refuses), the global flag was deleted rather than left half-wired, and the destination desk still reads the
  notice through `ReturnRecorder::openReturn()`, because `expectedCorrection` is otherwise write-only data.
  *A notice addressed from a form that has not been updated yet goes to the wrong desk*: the recorder re-opens
  boxes **after** `transition()` fires the event, so `WorkflowInbox::nextOffice()` read the pre-return box list and
  sent a return-to-Evaluation bell to office 3 (Assessment), which has no active account on the demo dataset — the
  record came back and rang nobody. One `RETURNED_HANDOFF` map answers both return states before the boxes are
  consulted, and the two return entries were removed from `STATUS_HANDOFF` rather than left as a second answer.
  *Check the nullability of the source column before writing NOT NULL from the model*: `originOfficeId` was NOT NULL
  while `staffusers.officeId` is nullable on the server (measured `IS_NULLABLE = YES`), so a desk-less
  administrator met the generic "conflicts with information already on file" page; the column is nullable and the
  desk reading it is told the return came from an account with no office of its own. *Two destinations are not two
  desks with a box*: Admission holds none, so `returnedToAdmission` is its own enrollment state, the application it
  re-opens flips to `pending`, every box re-opens, and `WorkflowInbox::nextOffice()` must answer Admission rather
  than the freshly-pending department. Instruments:
  `php artisan test tests/Feature/Enrollment/EnrollmentReturnTest.php` and
  `php storage/app/scratch/mutate-g10-return-tests.php` (one leg per promise over thirteen files, plus a green
  control of each test file before and after — the run prints its own leg total, and this file states none, because
  lane 58's rule for a revision row applies to a tool's own census; the `enrollment.returnRecord` ability is also
  pinned by `AbilityNameCollisionTest`). **That harness now refuses at the door when a target file already
  carries one of its own mutant shapes**, because two copies of it overlapped on 2026-10-27: the second
  snapshotted `ReturnRecorder.php` while the first had `if (false)` planted, called those bytes its "original",
  and its shutdown restore wrote the dead refusal back permanently — into a file the repository does not track,
  so no `git checkout` could have undone it and only the next run's control going red said so. A log with no
  final footer line is a job still running; relaunching the same mutating harness to confirm a partial reading
  is what creates the overlap. Live `ems` after the reseed: 95 permissions, 305 grants, 17 roles,
  64 tables, 105 foreign keys — 71 NO ACTION / 29 SET NULL / 4 CASCADE / 1 RESTRICT — and `enrollmentreturns`
  empty, which makes it a new member of §24.17's domain-empty set that
  `lane64-empty-table-inventory.php --verify` will refuse until the listing says so. Naming trap for anyone citing
  the authority docx: **§28.3's own `G-10` is a different, closed finding** (a validated ID request unreachable from
  the desk that validated it), so the return is registered as **G-32** and "gap G-10" is only ever written beside
  "prompt §6.4". Measured 2026-10-30, when the matrix grew a machine-read `Status` column: the collision is not one id but the first nine or so, since §28.3’s G-6 and G-8 are closed findings that share those numbers with two open rows of the matrix. So a gap id is written with its register named, and a count of open gaps is taken from one table, never from a grep. Two of the four §6.4 vocabulary questions are on `Documentation/School-Decision-Sheet-2026-10-27.md`
  (the reason-category list, and whether a department may write to Admission), and the §27.2 E-4 register row —
  "a returned record cannot get back to `paid`" — is re-stated only after the round trip is driven, not re-worded.
- **The pipeline is one list, and only an overseer may read it that way** (lane 82, prompt §6.1/§6.3).
  The completion audit that asked "is every instruction built" found a requirement the matrix had never scored:
  §6.1 names four places workflow progress must be visible, and the build served three — the stepper sits on seven
  desk detail pages, so "where is the year" was answerable only one student at a time, by whoever owned the box.
  `GET /admin/workflow-monitor` → `Admin\WorkflowMonitorController::index()` is the fourth, plus §6.3's admin view:
  one row per record still in the pipeline with phases signed against phases total, which desk is next, when a desk
  last acted, and for a returned record the category, the sending desk and the destination read from the same
  `enrollmentreturns` row the receiving desk reads. Six rules came out of it. *A new right must be reachable and a
  gate ability must not wear its name*: `workflow.view` is declared in a fifteenth seeder module and the
  read-everywhere `Admin` role takes it by its own `.view` rule, while the route asks the ability
  `workflow.monitor`, because Spatie's `Gate::before` would answer a same-named ability from the permission alone.
  **The whole-suite run caught a seat that was missing**: `PermissionMatrixTest` asserts that every `.view` right is
  held by `OfficeHead` — the invariant ruling 7 left standing when it moved the *signature* acts out of that role — so
  the seeder was corrected rather than the test exempted. A new read right either joins that list or is not named
  `.view`; an exception written to let one green screen through is the same failure as the hand-spelled year label the
  vocabulary guard refused.
  *One definition of "whose turn is it"*: the row calls `WorkflowInbox::nextOffice()` and `progress()` — the same
  helpers that address the bell — so the list and the notification cannot disagree; both now honour an
  eager-loaded `workflowsteps` collection, which is what keeps a 25-row page from issuing three queries per record
  (lane 12's N+1 rule, met inside a shared helper rather than by re-deriving it in the screen). *A desk filter
  selects the NEXT open box, not any open box*: `whereHas(pending at office)` would hand the Accounting desk a
  record that Department Evaluation is still ahead of, so the clause carries a `whereNotExists` on a lower
  `stepOrder`, written in portable SQL because the suite runs it on SQLite too. *A tile may not be a page* and
  *a dash may not be a zero*: five tiles come from clones taken before `paginate()`, and "no workflow form" is
  counted — §4's missing-tracker line turned from a reading into a number. *The payload is not the model*: passing
  `$page` straight to Inertia serialises bare `enrollments` records, so every derived key vanishes and the screen
  paints a status column with no phase; the paginator's meta now travels beside the mapped rows. Measured the same
  day: **16 cases / 69 assertions** in `tests/Feature/Admin/WorkflowMonitorTest.php`, `PermissionMatrixTest`'s view
  table grew the `admin.workflow-monitor → workflow.view` row (Staff is refused), PHPStan 0, Pint PASS over 414
  files and ESLint silent at `--max-warnings=0` — both re-read on 2026-10-28 after this file's own reseed sentence
  was found stale, and the whole-suite run then answered **929 passed / 6,234 assertions** with `--list-tests`
  discovering the same 929, so no test was stolen by an insertion.
- **Two surfaces, two sentences: a seeder figure is not a live figure.** This batch moved the catalogue from
  ninety-five rights in fourteen modules to ninety-six in fifteen. The seeder sentences were re-typed first, from
  `probe-seeder-rbac-counts.php`, while live `ems` still held ninety-five — and the bullet written at that moment
  said so. The reseed then ran, so **both** surfaces moved and §22's live sentences were re-typed from the server:
  96 permissions, 308 grants, 17 roles, 0 orphan grants, 96 distinct permissions granted (re-measured 2026-10-28,
  which is the day that stale sentence was caught by asking the database instead of the note). The §22.3 row of
  2026-10-06 is a dated record and stays as written. Re-measure the live sentences after a reseed, and state in the
  §1.1 row which surface each figure is
  read from, because a half-moved catalogue is the two-copies-of-one-word defect arriving in a document.
- **Inserting lines ages every pointer below them, and the sweep only sees the ones that hit a blank** (lane 82,
  same batch). Four citations landed on blank lines: the gradescale route range at lines 242 to 245, `RbacSeeder.php`
  line 198 and line 223, and the `AuthServiceProvider` define range at lines 168 to 170. Their two bare
  continuations in the same sentences (the roles tally then named as 483, the last role declaration as 474) sat on
  live lines and said nothing:
  reading them back showed the role range had been one *wrong target before this batch touched the file* — 223 was
  a closing brace and the first `Role::updateOrCreate` was already at 236. `refresh-docx-pass275b.php` therefore
  asserts, for every new number, that the line it names carries the claim (`247` must contain
  `Role::updateOrCreate`), and refuses the write otherwise. Run the sweep after any insert into a cited file, then
  re-read the neighbours of each row it flags.
- **A moved file's citations are shifted by bytes or not at all** (lane 76). G-3 grew
  `EvaluationController.php` by 102 lines and `routes/web.php` by 5, and the shift tool rewrote **79** citation
  tokens in the authority docx — 60 in the controller, 18 in the routes, 1 in the Evaluation panel; a fourth run,
  over `EnrollmentReadiness.php`, re-emitted 12 tokens with every number unchanged, which is what a clean no-move
  looks like and is worth printing rather than assuming. The line deltas and the token counts are what the tool
  printed, not arithmetic. And the shift is one regex pass, never a needle
  list: `php
  storage/app/scratch/lane76-shift-cites.php --token=<cited spelling> --head=<HEAD export> --live=<file> [--apply]`
  locates every cited window in the working copy, accepts only a unique match, rewrites by absolute offset with
  `(?![\d-])` so `:844` cannot eat `:844-898`, and attributes a bare `:NNN` only when the module's own rule claims
  it for that file *and* the 160 bytes before it in `document.xml` hold no other filename. It refuses — writing
  nothing — on GONE (the construct was edited, so a human must re-read it), AMBIGUOUS, a mapped token with zero XML
  occurrences, a `--skip=` naming nothing it collected, and an unknown flag; six ranges needed `--map=OLD=NEW`
  after reading, and two of those were **false by meaning before the batch touched the file** (the BR32 row quoted
  the class brace and a ternary arm; a bare continuation offered `confirmClearance()` while its lines sit in two
  other methods). Two traps in the tool itself: repeated `--map=` flags were first overwritten so only the last
  applied — a dropped flag makes a partial cascade look complete, the fourth time this file has said that — and a
  token that is half a comma list (`:58,190`) must be `--skip`ped and rewritten as one token, because shifting only
  its head produces a number that now looks valid.
- **Docx rows are cloned by row prefix and written by cell offset** (lane 76). Twelve new rows (dictionary
  columns, the new foreign key, the SET NULL list, the enum inventory, §13.1's fifth action, §1.1's record) came out
  of `refresh-docx-pass251b.php`, and three of its guards were bought by failures: anchor on the row's WHOLE
  flattened prefix, not a cell value — `remarks` is a column of four tables and
  `creditedsubjects.transferRecordId` opens a row in two inventories; join cells with a separator when flattening a
  row, because runs concatenate directly; and replace cells by **offset**, because `str_replace()` writes every
  identical copy and the foreign-key rows repeat `NO ACTION` twice in one row. A §1.1 cell can also carry a trailing
  *empty* paragraph (a previous clone's leftover) — dropped only when it holds no text, refused otherwise. Then
  `refresh-docx-pass251.php` and its three sequels did the sentences: **a record row that quotes a citation shape
  invents a citation** — the new §1.1 row named `:58,190` and `:959-1000` while describing them, which made pass
  251's own needles ambiguous and is why that prose is spelled in words now; and `lane59-instrument-counts.php`
  caught a figure this batch moved without anyone asking it to (lane 49's blind-spot count went 339 → 342 because
  the new JSX added lines of the shape it counts).
- In a feature test, an Inertia screen's props come from `->viewData('page')['props']`, not
  `->json('props.x')`: the route answers with HTML until `X-Inertia` is sent, so the second form reports
  "Invalid JSON was returned from the route". Endpoints that return real JSON (`students.quick-search`,
  `exam.students`) are read with `->json()` as normal. Outside the test harness — a probe that drives the HTTP
  kernel itself — `Illuminate\Http\Response` has no `viewData()`, and a request carrying only `X-Inertia: true`
  is answered **409 with no props** because it lacks the server's `X-Inertia-Version`; read the payload out of
  the page's `data-page` attribute instead (`html_entity_decode()` then `json_decode()`), which is the same JSON
  the browser receives. Measured on `lane53-probe-feesheet-live.php`, whose first two runs reported a clean
  verdict from a response that had never reached the controller's props at all.
- A refusal that *said why* now reaches the desk two ways (lane 28): `resources/views/errors/403.blade.php`
  prints the reason for a desk the browser lands on, and `bootstrap/app.php` returns it as `{"message": …}`
  for a caller that asked for JSON. Both ask `App\Support\DenialReason::of()` — Laravel's placeholder
  "This action is unauthorized." counts as no reason, so a bare policy denial still prints only the page's
  own copy. Two traps when you rely on this: `shouldRenderJsonWhen` limits JSON to `api/*`, so a refused
  web endpoint needs that render callback; and a plain `fetch()` sends `Accept: */*`, which is *not*
  `expectsJson()` — a screen reading `res.json()` must send `Accept: application/json` and check `res.ok`
  or it parses the 403 page, throws, and shows its own empty state instead of the refusal. Where the desk
  was mid-form, `withErrors` still beats a dead-end page: that is the path the toast and inline list read,
  and it leaves them on the record.
- Every `fetch()` in `resources/js` checks `res.ok` before it paints, and sends `Accept: application/json`
  if it parses the answer. Lane 29 measured four that did not, and each was a *different* way of lying: a
  fallback that reads as data (`Dashboard.jsx` painted `0` with a green "Queue clear" for a count the server
  never sent), a `.catch()` that clears only the spinner so the **previous** query's rows stay on screen
  (`GlobalSearchModal.jsx`), and a success repaint driven by any body that parsed — which a 419 from the
  session-expiry handler is (`AuthenticatedLayout.jsx` mark-all-read zeroed the bell on a write that did not
  happen). No screen may show a plausible zero for a number it does not have: show a dash and say it is not
  updating. There is no JS test suite, so these are proven by reading the chain end to end — the gate that
  catches them is `eslint`, which cannot.
- Never assume an id from a seeder keyed by *name*. `StarterReferenceDataSeeder` does
  `firstOrCreate(['religionName' => …])`, so each id is whatever the table's `AUTO_INCREMENT` counter hands
  out — and a rolled-back insert does not rewind that counter, so a database the suite already ran against
  seeds `religions` at 53..60, not 1..8. Insert the row by explicit id (39 test files do exactly that) or
  read the one that exists. `DevReferenceDataSeeder` is the opposite case: it pins courses and terms by
  explicit id, so those ids stay safe as literals. Same trap, bigger consequence:
  `tools/seed_full_demo_pipeline.php` used to write `feeTypeId => 1..4`, `subjectId => 5`, `roomId => 1` and
  an office→user map of literals; on a fresh install fee type 1 is the Clearance Slip Replacement, so every
  demo charge attached to the wrong paper. Resolve by name (`subjectCode`, `roomName`, `feeName`,
  `officeN_head` usernames) instead. **A `?? 1` fallback is the same bug wearing a costume:** three clearance
  approval sites wrote `$deskSigners[$officeId] ?? 1` and a bare `1`, because that map covered seven offices
  while the checklist is seeded from `ProvisionalClearanceRequirements::NAMES` — nine. Admission and Academic
  lines were signed by whichever account was userId 1. Fixed 2026-10-16 by mapping all nine offices and routing
  every signer through `desk_signer_for()`, which refuses an unseated desk.
- **A rehearsal schema with clean counters cannot find a counter assumption.** The bug above passed on a
  schema built from the CI recipe because nothing had spent `staffusers` yet; CI caught it because its Pest
  step runs *before* the seeders and every rolled-back test insert still spends its id. When a rehearsal is
  meant to prove a path CI will take, reproduce CI's order (suite → migrate → seed → the thing under test) —
  MySQL 8 will not show a spent counter on an empty table, so bumping `AUTO_INCREMENT` is not a substitute.
  **The same assumption in a test fixture is invisible locally and red on CI.** `TimetableImportTest` asserted
  literal subject and room ids; on SQLite a rolled-back transaction rewinds the counter so those numbers were
  right, and on CI's MySQL job they had been spent, so one case that exists to prove a room collision answered
  "no conflict" — it was addressing a room nothing was booked in. A test that passes against a fixture id
  rather than the id the fixture created is true for the wrong reason. Resolve every such id from the model you
  just created, and to see the shape, run that one file against a scratch MySQL schema once: the spending
  happens *inside* a single run, as each test's rolled-back transaction leaves its ids behind, so the first
  MySQL run on a brand-new schema already failed three cases while every one of them is green on SQLite, where
  the rollback does rewind.
- Nothing *analyses* `tools/`: `phpstan.neon` covers `app` only and `pint.json` excludes `tools`, so a defect
  there is invisible to the static gates — those files are proven by running them and reading their output.
  Since 2026-10-16 `tools/seed_full_demo_pipeline.php` is also *executed* by CI (the MySQL job's step between
  the seeders and the walkthrough legs) and it exits 1 when its own desk report lists a finding, so a fatal or
  a broken record there is red there — but the other files in that directory still answer only to a hand run.
- **Re-measuring only the files a batch touched is how the document accumulated wrong line numbers:** 14
  citations had drifted in files no recent batch edited. Run the whole-document sweep after any batch that moves lines — `php storage/app/scratch/dump-docx-flat.php` then
  `php storage/app/scratch/sweep-docx-citations.php` (it reads the regenerated dump, so re-dump before believing a verdict, and its own census moves when a pointer is added — so no total is stated here; it exits non-zero on a
  citation past the end of a file or landing on a blank line, and it was proved by planting one). It checks
  lines, not meaning: a citation can land on a live line that belongs to something else, and only reading
  the sentence catches that.
- **A `File.php:NN , :MM` sentence is two citations, and a checker that needs a filename before the colon
  sees only one of them.** Found by extending that sweep on 2026-10-24: §24 names the clearance print actions
  as `ClearanceController.php` lines 380 and 404 — spelled in words on purpose, because this is a record of that
  day's layout and the colon form is a live pointer the citation gate reads — and when P-17 grew `printSlip()`
  four lines the download action
  moved to line 408, the print-route table was re-pointed, and §24's bare continuation was not — so the document
  cited a blank line while the sweep printed 0 problems, and this file carried that same sentence about bare
  continuations as a *warning* above the very defect. Extending it found five drifted pointers and one claim
  the build had overtaken (§13.6 said the Evaluation queue shows "student type but not standing"; that queue
  has had a Proposed Standing column for a while, and the tile it complains about is a different line now —
  both were re-measured by reading, not shifted). A bare `:NN` is now attributed to the file named most
  recently before it on the same dump line — a full citation *or* a file named without a number, because
  `( Students/Show.jsx ) | Name, School ID ( :152 )` names its file one cell earlier — but only within 160
  characters: past that the token is printed as a blind spot instead of judged, because an attribution
  invented by distance is how a checker clears a pointer that belongs to the neighbouring cell. That is not
  hypothetical: the first run attributed `( :248-267 )` to `IDPolicy.php`, a 135-line file, and the number was
  *right* — it was `IDController.php`'s validate action, which the document never named. So: read the printed
  blind list, and when a pointer's file is genuinely absent, name the file rather than teach the checker to
  guess. Prove the whole rule with `php storage/app/scratch/mutate-lane69-bare-cites.php` — a planted blank
  line, a planted past-end number, a token pushed past the window that must move into the blind list without
  changing the verdict, a byte-identical control, and a missing dump, which must refuse rather than answer
  "0 line problem(s)". Two things this still cannot see, both printed rather than cleared: a comma list that
  shares one colon (`:152,186,193` — only the first is a token), and a pointer whose file was named on a
  *previous* dump line. CI runs the sweep as a gate now, not just as an instrument.
- **The blind list is a queue, and a queue is not a gate** (lane 70). `storage/app/scratch/lane70-anchor-cites.php`
  takes every bare pointer and asks the file for the identifier the sentence itself names (`Class::method`, a
  column, a class word). On 2026-10-24 it judged every bare pointer in the document: most of its complaints were **its own**
  clause mis-attribution — it hung `:31` on `PrintAssets.php` when the number belonged to the
  `clearance-slip.blade.php:30` clause two words earlier, and hung `:80` on `Str::headline()` when the sentence
  had already cited that construct at `:72`. Five verdicts were read back against the code: **four were the
  instrument, one was the document**. So it prints, and exits 1 **loudly by design**, and is NOT a CI step; promoting
  a checker whose precision is one in five would teach everyone to ignore the red. **That sample is now a full read,
  and the sample was optimistic: on 2026-10-25 all 47 MOVED rows were read back and none of the 47 *pairings* is a
  claim the build contradicts.** **A clean read of a pairing is not a clean read of the file it points at** — after
  the attribution was unified and the ten surviving rows were opened one by one, ten further wrong targets came out
  of those same files (passes 224-226, recorded below). 8 name a file other than the one the sentence's own citation names — this tool takes the
  first file on the dump row, where the bounds sweep takes the nearest one *before* the token; 6 quote a clause
  inside the construct they name (the verdict's last clause, the signature line, the cap), which lane 32's encloses
  rule permits and this tool never learned; the remaining 33 charge the sentence's **first** identifier to a range
  another construct owns, and very often that identifier is the register row's own **column label** —
  `maxStudents`, `totalAssessedAmount`, `requiresEntranceExam`, `enrollmentType` — not the thing the pointer was
  offered for. Read a MOVED row as "which pairing to open", never as "this citation is false"; the tool now says so
  in its own footer, and its docblock carries the count. The instrument's three choices (file, sentence, anchor) are
  the whole defect, and all three are already solved elsewhere: the sweep's nearest-preceding-name rule, lane 32's
  direction-plus-encloses rule. **Re-measure a precision claim before repeating it** — AGENTS.md asserted this
  tool "exits 0" while its own last line reads `exit($counts['MOVED'] > 0 || $counts['NO-ANCHOR'] > 0 ? 1 : 0)`,
  and the assertion was written from the tool's intention rather than its output. The first real defect it surfaced was fixed in
  pass 190: the settlement row quoted `( :195-215 )` for a balance formula, the number named no file so no
  instrument had ever read it, and the formula is `applyScholarship()`'s grant insert followed by the balance update —
  lines 257 to 273 as that file stood when pass 190 read it, and lines 298 to 314 of the working file after the
  2026-10-30 sort insert moved the action down, the two windows checked byte for byte — which the scholarship row had
  been citing correctly all along.
  **What this repository still owes, measured rather than recalled:** the registers hold no open engineering
  finding, and `lane69-open-inventory.php` answers "what is left" — the decision sheet's own header and the
  classifier now cross-check each other (a ruling word counts only in decision position, because the sheet's
  item 2 was marked closed by a sentence that *reported another row's ruling* inside its own unticked answer),
  four rows wait on the Registrar's window (items 1, 2, 4, 5), two items wait on actors outside the software
  (C-5 with the departments, G-13 at install time), and the read-only queue is the one unattributed pointer, quoted as an example on purpose
  (`php storage/app/scratch/lane70-blind-sheet.php` prints the reading sheet: each one's whole sentence plus every
  file named on its dump line, which is how an owner is found without guessing),
  `storage/app/scratch/lane32-cite-meaning-sweep.php` checks 130 ranges and flags 16 pairings, every one of them an
  `encloses` verdict — the shape its own rule permits — and each was re-read on 2026-10-27 after passes 271 and 272
  moved the set down from 21: the pairing list is attribution noise, not a claim the build contradicts — a vendor path
  the basename index cannot see
  (`vendor/laravel/framework/src/Illuminate/Database/Query/Builder.php:3208` really is `forPage()`), a `recordIssue()` landing on the block-schedule print action while
  its own `PrintService.php:115` sits later in the sentence, `Payments::held()` on the accounting range its clause
  opened with, two prose-order glosses in the workflow listener, a migration's `:24` cited beside a clause about
  the seeder that names the same constant, and `ProvisionalClearanceRequirements::NAMES` paired with the migration
  that backfills from it — and the fidelity sheet's rows the
  Registrar has not ticked. Those are work for a reader, not for a wrapper. The anchor checker's 108 UNBOUND rows
  are **read**, not cleared: they stay UNBOUND because their sentences name no construct for a tool to pair, and
  reading them against the code is what produced passes 241 and 242 (seven more wrong targets, the 36th to 42nd).
  **A column can have one owner for the whole table, and then no row-scoped checker will ever see it.** §21.2's
  "Seeded?" column is nine cells carrying bare numbers, and the sentence introducing the table says "Seeded =
  created by `DevReferenceDataSeeder`" — so every one of those numbers belongs to the seeder and none was ever
  checked. Resolving each table to its model and finding that model's create call in the seeder
  (`storage/app/scratch/lane70-seeder-cites.php`, read-only) showed **five of them quoting a line that is not a
  create call at all** — a `];`, a comment about a dropped program, three lines of room-and-section data — plus one
  citation the resolver cannot match (`DevReferenceDataSeeder:770-773`, no `.php`). Passes 194 and 195 fixed the
  column, and the cells had to go through `docx-edit-engine.php`'s **cell** and **anchored text** kinds: a plain
  needle cannot address `:189` when another row cites the same number, which is why pass 193 refused itself rather
  than half-write. After that fix the sweep reported 666 citations over 152 files with 0 line problems and the blind
  queue at 70. The lesson for any future checker: a citation's owner may live in the prose above the table, the
  paragraph above the list, or the column heading — when the tool can only read the row, the document has to name
  the file, rather than the tool learning to guess it.
  **A matrix has one owner per COLUMN, and two sides per cell.** §25's identity matrix is one row per required
  context fact against one column per printed document, and its cells read `desk ✓ ( :87-90 ) / PDF ✗`. The intro
  sentence says only that desk means the page staff print and PDF means the Blade template, so every number in all
  six columns was unattributable — 16 of those 70 blind spots — and `lane70-matrix-cites.php` (read-only) is what
  finally read them by resolving each cell to its column's two files and taking the side from the word before it.
  Most were stale the way only a template edit causes: the student-type cell quoted a plain div, the certificate's
  *status* cell quoted its **enrollment-date** row, the slip's "date to be signed" and "per-approval date" quoted
  the student name and the course row, and the subject load's signature range opened on a blank line. Pass 199
  re-took all twelve cells — each new number asserted against the field it claims (29 such assertions gate the
  write) — and wrote the file into the cell, so the existing sweep judges them from now on: **the census that day was
  727 citations over 155 files, 0 line problems, 135 bare continuations judged, blind queue down to 1** (that figure
  is pass 199's own reading and is not re-aged; the living count is the one in the gate bullet above). Two instrument lessons in
  one session: a pattern written `(?<word>A|B|C|:NN)` puts *every* alternative inside `word`, so the classifier
  fired its first branch for each bare number and the tool reported a clean table it had never read; and a needle
  must be anchored on a RUN, not a phrase — "both print enrollmentType instead" is three `<w:t>` elements and does
  not exist contiguously in `document.xml`, while `:207</w:t>` does. A short run like `:48` is a prefix of others,
  so run-boundary anchors are checked for uniqueness by the pass itself before anything is written.
  **A pointer that lands on a live line of the right file is invisible to every gate, and fixing a whole
  paragraph may need the engine's `para` kind.** Passes 201-205 took the last three paragraph families and moved
  the blind queue 70 → 43. §25's issuance paragraph carried three numbers that name no file: `:168-181` for the
  scope chooser (`:168` is a docblock line and the range stops inside the signature, before the match arms — it
  is `PrintService.php:176-191`), `:130` for the row lock — **a blank line** — which is really `:140`, and
  `:132-142` for the reserved-number skip loop, which is `:142-152`. §25's P-11 paragraph had four of its five
  desk pointers on live but wrong lines: `:47` is the document-number row, `:93` a closing `</Card>`, `:101` the
  received-on line and `:112` the Registrar signature line. Nothing could see either case because the file was
  never named, so the bounds sweep never opened it. Both went through the `para` kind: the runs split at every
  paren, so no contiguous needle reaches more than one number and `</w:t>` may not be written into a replacement
  — and the paragraph *anchor* has to sit inside one run, which pass 203 found the hard way (the P-11
  paragraph's tail is a single run while its earlier clause is split across five).
  **The §12 Gate 4 sentence was not a stale pointer; it was a stale claim.** It read "Gate 4 treats *no open
  clearance period* as satisfied (`:116-119`). Deliberate, but it means a Registrar who has not opened the period
  silently clears everyone" — which is the build before ruling 4; the method's own docblock says "Ruling 4 closes
  the silence that used to pass this gate." The range was live, the file was right, the prose described a retired
  state in the present tense, and it sits in the section a desk reads before approving anyone. It now states what
  the check does (`EnrollmentReadiness.php:61-63`). Only reading the pointer's sentence against the method sees
  this shape — it is the same defect lane 69 found in the decision sheet, reached from the code side.
  **A §1.1 record row moves the census it reports.** Pass 204 wrote the citation count measured after pass 203,
  and its own new `( EnrollmentReadiness.php:61-63 )` pointer was one of the citations the next sweep counted, so
  pass 205 re-aged the figure it had just written (689 → 690). Lane 61's register-row lesson one level up: a
  sentence that states what an instrument prints must be measured after that instrument's own row exists.
  **A pointer can land on live code that belongs to the next field down** (pass 206). The §25 slip paragraph cited
  eight lines above the row that prints the Date to be Signed, because the copy designation had inserted the
  course-label block in between; both ranges are live, so the bounds sweep, the meaning sweep and lane 32 all
  cleared it and only opening `clearance-slip.blade.php` said which line paints the date. Run
  `php storage/app/scratch/probe-moved-cites.php` after changing any print view, and re-read a paragraph's
  pointers whenever a template grows, not only when a number goes past the end of a file.
  **The same shape lives in a register row, and the file being right is what hides it** (passes 208/208b). Two
  rows cited ranges inside the very file their own sentence names, and every instrument cleared both: the
  elective-band row quoted the standing-override message for the rule that refuses a proposal outside the min–max
  band (that check is `EvaluationController.php:664-670`, not the two blocks above it), and the staff row quoted
  the curriculum block for the seeding of `staff8` and the office heads (that is
  `DevReferenceDataSeeder.php:782-827`). A bounds sweep asks only whether the lines exist; the meaning sweep needs
  the prose to name a *method*. So the rule when draining a pointer queue is to read the cited span's own text
  against the claim in the sentence — the assertion loop in each `refresh-docx-passNNN.php` exists exactly for
  this, and it refuses the write when a claimed line does not carry the claim.
  **Probe a needle before building a pass around it**: `php storage/app/scratch/lane70-probe-needles.php '…'`
  prints how many times a phrase occurs in `word/document.xml`, because the flat dump joins `<w:t>` runs and a
  sentence that reads continuously on screen can be five runs in the file. Pass 208 refused itself on five such
  needles and 208b took four of them as bare ranges plus two positional anchors — an anchor is only safe when it
  is unique, and a range that occurs twice (the other copy already prefixed with its file) was left for the cell
  kind rather than guessed at.
  **Never rebuild a cell or paragraph from the flat dump — rebuild it from `document.xml`.**
  `dump-docx-flat.php` joins `<w:t>` runs **with a space** (the lane 69 fix that stops `strip_tags()` welding two
  filenames into one), so a string copied out of the dump invents spacing the document never had: pass 212's first
  draft read the workflow cell as `pending →…→ assessed ; box 2` while the file holds
  `pending→…→ assessed; box 2`, and writing the dump's version would have retyped the table. Flatten the way the
  engine does — `implode('', $tm[1])` over its `<w:t>` matches, then `html_entity_decode` — and substitute inside
  *that*. Two more traps in the same call: the engine compares a cell prefix in **bytes** with no whitespace
  folding, so a prefix taken from a collapsed dump string fails to match at the first multibyte arrow (use the
  cell's leading ASCII word); and the `cell`/`para` kinds rewrite the whole block as **one run**, dropping the
  styled spans inside it, so a paragraph with code formatting goes through `text` whenever any needle in it is
  contiguous.
  **A fourth wrong target, and the first whose row never named the file.** The workflow table's Assessment row said
  "box 2 signed for a first-year, skipped for a continuing student" and cited three lines of the fee controller,
  which are `applyScholarship()`'s balance recompute and `withdrawScholarship()`'s docblock; the skip is
  `WorkflowService.php:52-71` — `$includesAssessment`, then the box list filtered before the labels are mapped.
  Reading the cited span's own text against the sentence is the only check that catches this shape: a bounds sweep
  sees four live lines and says nothing.
  **Uniqueness is necessary and NOT sufficient — a needle can be another citation's tail.** Pass 215 wanted the §6
  row's `:328` and used `:328 )`, unique in `document.xml`. It matched instead inside `ClearanceController.php` +
  `:328 )`, two adjacent runs of the print paragraph's citation, and the write produced one token out of the two
  file names — `ClearanceController.php` butted against `AdmissionController.php` with `:411` after it, which names
  no file at all; this paragraph had to be re-worded for the same reason, because a citation-shaped token is a
  citation even inside prose about citations. The bounds sweep refused it as a name not in the repository and moved
  the file count 154 → 155 for a phantom; pass 215b restored the citation. Lane 69's rule was that a short needle
  can be the prefix of a longer one; this is the same failure
  one step sideways: before writing, check what the match sits *after*. Prefer a needle that includes the preceding
  word boundary, and if the citation you mean is run-split, take it through the cell kind instead of a bare tail.
  **A reading aid must use the checker's key, not its own** (pass 206, same batch). `lane70-blind-sheet.php` had
  collected the blind *numbers* from the sweep and then searched the document for each one, so the token the sweep
  flagged once printed eleven rows — `:44` matched `:44-62` and `:441` — and the queue read three times its real
  size. It now parses the sweep's own `(token, dump line)` pairs (the sweep prints the dump line for this reason)
  and refuses when its parsed rows do not equal the tally it printed. Two definitions of one word is the defect
  lane 69 removed for year levels and lane 69's owed table refused to re-implement; a helper that re-derives what
  its checker already decided will drift, and a reader sent after a phantom pointer wastes the pass.
- **After any batch that edits a method body the document quotes, or a citation whose parenthetical contains
  source code**, run `php storage/app/scratch/lane70-gloss-snippets.php` and prove it with
  `php storage/app/scratch/mutate-lane70-gloss.php`. This is the seventh prose family: **a citation that quotes a
  call**. `WorkflowService.php:64-66 ( unset($steps[2]) )` was true until `29b5532` (2026-10-01) rewrote the
  Assessment skip as an `array_filter()` over the box list. Nothing could see it: the stale range lands on live
  lines of the same file, the file is named, and lane 32 judges a pair only when the prose names a *method*, not
  when it quotes an expression. Reading that one file line by line turned up eleven more pointers that were false
  by meaning in the same shape — the office-scope test cited **four times** at the throw that refuses a second
  signature (`:237`, actually `:255`), the step-order rule twice at two private constant maps (`:114-124` and
  `:113-124`, actually `:261-272`), the completion status at the closing line of a docblock (`:144`, actually
  `:292`), the raw `role` read at the `workflowId` line (`:95`, actually `:193`), the Guidance-labelled box one
  row short (`:32`, actually `:33`), the BR13 label at a docblock opener (`:78,113`, actually `:203,261`), and a
  three-part parenthetical whose numbers all predate ruling 7. Passes 196/196b re-took them, and the pass asserts
  each *new* number carries the claim (`:255` must contain `maySignAcrossOffices`) before it will write; pass 196a
  could not address the four bare `:63-66` pointers because that needle was a prefix of three `:63-66,41-43` runs,
  so `expect=4` was unreachable on those bytes — a second pass took them once the longer runs were gone. Pass 198
  then gave two constructs the document named but never numbered their own lines (`PaymentPolicy.php:115`,
  `DevReferenceDataSeeder.php:685`). **The checker's own three bugs came out of its first run**: `index on
  (notifiable_type, …)` is an English preposition plus a column list, not a call, so candidates need a length floor
  and a stoplist; a `vendor/…` path is absent from the basename index, so a citation containing a slash must be
  resolved directly or the run says NO-FILE about a file it could read; and an argument-list-free call — `array_filter()`
  — was never captured at all, because a nested-paren pattern requiring one to forty characters between the inner
  brackets skips `()`, which is exactly the shape of the call a stale pointer hides in. Its fourth fix was semantic:
  a gloss naming the method that *owns* the cited lines is not out of range (lane 32's encloses rule), a quoted
  `$variable` needs a different lookup than a quoted `call(`, and a symbol the neighbouring citation owns
  ("`Payments::held()` ( `PaymentPolicy.php:115` )") is accounted for on the line. It exits 0 by design — only
  NOT-IN-FILE is a statement the repository can falsify, and the queue verdicts are the human half — so it is an
  instrument, not a gate — and its precision has now been measured over the whole document. Measured 2026-10-25:
  the run claimed "examined 11 / OK 6" with an empty queue, so **five rows fell through a `continue` that counted
  nothing** and the printed zero was an unexamined five, not a clean document. Every row now lands in a bucket
  (`OK`, `NOT-IN-FILE`, `queue`, `dropped`), the tool refuses when `examined ≠ the buckets added up`, and the
  identifier patterns were widened to the two shapes that were being discarded — a class member with no call
  (`Str::headline`, `ClinicRecordStatus::Completed`, `ApplicantType::class`) and a property written as an
  assignment (`appliesTo = firstYear | transferee | all`). The reconciled tally is **11 = 10 OK + 0 not-in-file +
  0 queue + 1 dropped**, and the one honest drop is `routes/web.php:269-271`'s column list, which is a schema
  description rather than a call. The reconciliation is what found the **twelfth wrong target**: §6 and the
  requirement row both cited `AdmissionController.php` lines 211 to 222 for "only rows whose `appliesTo` equals the chosen
  `applicantType` or `all` receive a submission row", but those lines were the `Admissions::create([...])` array —
  which writes `applicantType` and never reads `appliesTo` — and the selection was `:225-238` (the `where`/`orWhere`
  at :226-227, the `Studentrequirementsubmissions::create()` loop at :231-237). Pass 223 moved both copies
  together (`AdmissionController.php` lines 211 to 222 occurred exactly twice in `document.xml`, so `expect=2` and no
  guessing at a third). **Those five numbers are the layout of 2026-10-24, and G-2's draft branch moved the file by
  143 net lines:** the create array is AdmissionController.php lines 266 to 275 as the file stood that day, the selection is `:305-322` (the
  `where`/`orWhere` at :305-307, the insert loop at :314-322), and pass 250 re-pointed the document's two copies to
  `:305-322` — still exactly twice, which is what made `expect=2` the right instruction then and now. **The queue is not drained by stopping at one find: opening the 10 rows that survived the
  rewrite found ten more wrong targets** (passes 224, 225 and 226). §28's exam row cited `ExamController.php:311-314`
  for the *departmental* paper while those lines are the head of the **general** upsert (`'examType' =>
  ExamType::General` at :317) — the condition the aside describes is `recordCourseSpecific`'s own
  `if ($course->requiresEntranceExam)` at :366, under the BR9/C-4/C-5 comment at :358, so the citation is now
  `:358-366`. And §28's Accounting row made two claims about signatures that both landed on the wrong statement:
  "a collected payment signs the same box at `:227`" points at `if ($newBalance > 0)` — the *installment* branch,
  the opposite of the one that signs — while the payment's `signStepByOffice()` is **:243**; and "the signature at
  `:274`" points at the refusal redirect for an account that still owes, while `settleNoBalance()` signs at
  **:290**. The third find came from the row the anchor checker complains about loudest — a name the file never
  contains — and opening that file turned one complaint into **thirteen pointers**: `approvalBlockers()` was
  extracted out of `AdmissionPolicy::approve()`, and every number written against the old body kept its line.
  `AdmissionPolicy.php:48-66` (twice) claimed `approve()` spans :48-66 while the method ends at :55 and :57-66 is
  *the other method's docblock*; `approvalBlockers() at :67-136` (three times) stopped 16 lines short of its
  `return $blockers;` at :151 — and §10.2's own condition-4 row cites `:108-149`, which falls **outside** the range
  the same sentence calls the authoritative gate list; `:49` for "holds `admission.approve`" is the opening brace
  (the test is :50); `:54-56` for "the application is still pending" is the blockers *call* (the pending check is
  :71-75); `:58-67` three times for the BR32 document rule is a docblock plus a signature (the verification loop is
  :77-89); `:69-81` for the general-exam gate is the `$blockers = []` line, the pending check and the **document**
  query (the exam condition is :111-126); and `:39-47` twice for BR7 opens inside `update()` (:39-42), whose note is
  :44-47. **A method pulled out of another one moves every pointer written against the old body, and each new number
  still lands on a live line of the same file** — which is why the bounds sweep and lane 32 both agreed with it: the
  prose names `approve`/`approvalBlockers`, and both names really do sit inside the quoted span.
  **The ten rows that remain are the checker's own limits, and all ten have now been opened**: a name from another
  clause of the same sentence (`endorse` charged to `decide()`'s :71, `stepOrder` charged to `stepLabel`'s :64,
  `settleNoBalance` charged to the payment's `signStepByOffice()` at :243, `array_filter` charged to the `stepOrder`
  write at :97), a register row's column label (`coverageType`, `maxStudents`, `totalAssessedAmount`), and a word the
  cited file never contains (`decideStanding` on `BlockingController.php:116`, which is exactly
  `->where('yearLevel', $block->yearLevel)`; `firstYear` on an `AdmissionPolicy` range). **A row that reads like
  mis-attribution is still worth opening** — the AdmissionPolicy complaint was that shape, and it led to thirteen
  false pointers. **A queue of "instrument-side" is a claim, not a measurement, until the rows have been opened; it
  took opening them to find ten more real defects.**
  **A checker whose summary does not add up is not reporting a clean document, and an uncounted `continue` is how it looks clean.**
- **Citation attribution has ONE implementation now** (lane 70, 2026-10-25): `storage/app/scratch/docx-cite-attribution.php`
  holds the bounds sweep's rule (a bare `:NN` belongs to the file named most recently *before* it on the same dump
  line, within 160 characters), lane 32's rule (a name labels the range after it when the gap is short and clean or
  it opens the parenthesis the range sits inside, otherwise it labels the range before it), the encloses rule (a
  citation may point at a clause *inside* the construct the prose names), and the register-cell rule (a row's leading
  column label is a cell, not a construct to find). `sweep-docx-citations.php`, `lane32-cite-meaning-sweep.php` and
  `lane70-anchor-cites.php` all call it, because two answers to "which file owns this pointer" is how one sentence
  ends up contradicting itself depending on the tool that was run. The refactors are proved by parity, not by hope:
  both tools printed **byte-identical output** to `git show HEAD:` (727 citations / 155 files / 0 problems / 135
  judged / 1 blind, and 131 pairs / 8 flags), and their harnesses pass — including lane 32's parenthesis leg, which
  had silently decayed because it hardcoded `last clause ( :958-999` while the document now says `:959-1000`; the leg
  reads its needle out of the dump and refuses the run when the phrase is gone. **A rewritten checker's complaint
  count falling 47 → 9 is exactly what a broken reader looks like** (lane 46 cleared the whole tree because it read
  the wrong construct; lane 50 passed every real call because it matched only the shape it had just learned), so
  `php storage/app/scratch/mutate-lane70-anchor.php` (7 legs) plants both directions: moving a pairing the tool
  calls correct onto a line that lacks its identifier must raise MOVED by one, repairing a row it calls wrong must
  lower MOVED by one, a byte-identical copy must change no verdict, and a dump with nothing to judge must refuse —
  proved by its own message, because `judges-nothing` and `missing-dump` both exit 1 and a leg that accepts either
  passes on a broken instrument. `--show-ok` exists so the harness can find a live correct pairing instead of
  remembering one. It is NOT a CI step — ruled again on 2026-10-25 after every numbered row in its queue had been
  opened: the run judges 135 bare pointers and **108 of them are UNBOUND**, so a gate on this tool would demand red
  on rows that are legitimately a human read. **And the reason those 110 had never been read is a defect in the
  instrument, not in the document:** the footer claimed "UNBOUND and BLIND are printed as queues" while every print
  condition read `if ($all && $printed++ < $limit)` with `$limit` defaulting to **0** — false for every caller, so
  the queue existed only as a number, and one branch of 71 rows had no print statement at all. Fixed: queue rows now
  print unconditionally, `--limit=N` is an opt-in skim that appends `the queue above is INCOMPLETE` with the
  withheld count, `--all` was **deleted rather than left working** (an accepted-and-inert flag is how a mutating run
  looks clean), and the run refuses unless `printed + withheld == UNBOUND + BLIND` — proved by switching one printer
  off in a copy of the tool, which answered `tallies 111 queue row(s) … but wrote 40 and withheld 0`, exactly the 71
  rows that branch owns. Measured decomposition of the queue, over 37 files: **71** sentences name no construct at
  all, **32** name one that no pairing rule reaches, **7** pair a `Class::method` from a different clause
  (`RegistrarController.php` 16, `BlockingController.php` 8, `EvaluationController.php` 7, `ExamController.php` 6,
  `WorkflowService.php` 5, `Students/Show.jsx` 5 are the largest). Reading the rows that remain shows why the same
  ruling held from the first day — they are still the tool's own limits, a prose-order gloss
  (`:30 (PrintAssets::logoDataUri) and :31 (config(…)))`, where the second half names a call with arguments the
  identifier pattern does not capture) and a register row's camelCase column label.
  **A leg must be able to fail on its own terms:** the new cap leg first counted **6** rows under `--limit=5`,
  because `^UNBOUND ` also matches the footer's own sentence — a queue row is recognised by the padding its printf
  writes, not by the leading word. And an exit code cannot prove a refusal here: the tool already exits 1 whenever
  MOVED is non-zero, so the invariant leg asserts on the message, not the status.
  **Printing the queue is what made it readable, and it found the twenty-third wrong target immediately.** §28's
  row 9 and §27's zero-balance deadlock row both name a checklist key **`registrar_ready`** — a key that exists
  nowhere in `app/`, `resources/`, `routes/`, `tools/`, `tests/`, the guides or CI. The array at
  `RegistrarController.php:211-223` holds exactly seven keys and the one they mean is
  `registrarApprovalPending` at **:216**, false precisely when the next pending box is another office — so the
  *claim* was right and the identifier it quoted was invented, which no bounds check can see because both line
  numbers are live in the named file. Pass 227 renamed both occurrences (`expect=2`, and it asserts the seven-key
  count before writing). **That pointer was re-taken twice, and the second time says more than the first.** The
  batch that added six lines to this controller made it land on a blank line, which is the one shape
  `repo-file-line-citations.php` refuses — but a blank line is not why the number was wrong: the array had already
  moved some fifty-nine lines down through earlier batches while the document still named its old position, so every
  run between then and now cleared a pointer that sat on live code belonging to `show()`'s render array. A bounds
  check reports the accident, not the age. The reading aid that turned the row up is
  `storage/app/scratch/lane70-read-queue.php`: for a pointer the checker cannot bind, it prints the enclosing
  `public function` of the cited line next to the document's own sentence, which is the check to run when the
  prose names no identifier at all.
  **Pass 241 is the tranche that shows a one-line pointer inside the right method is still not evidence.** Two of
  the 107 UNBOUND rows were wrong targets, both in `ExamController.php`, and both landed inside the very method the
  prose names — so lane 32's encloses rule agreed with them while they carried a different claim from the one the
  sentence makes. §13's query-builder paragraph says "G-8 removed the status write from **both** examination paths
  (`ExamController.php:325-332` and `:355-362`)": the general half is right, and :355-362 is the head of
  `recordCourseSpecific()` — the "This course administers no entrance examination" refusal at :355 and the
  BR9/C-4/C-5 comment at :358-362 — while that path's G-8 closure is the comment at :397-402 with the redirect that
  writes no status at :403-404, which §13's own effect-of-the-result row already cites **with the file named**. The
  document again held the true owner twice and left one copy unattributed (pass 228's shape, reached a second time).
  The second was §28.3 G-15's citation cell, `ExamController.php:424-427 and :441`: the row's closure is two claims,
  "results() honours `search` exactly as index() does **and echoes both keys**", and :424-427 is the first while the
  echo is `'filters' => $request->only(['result', 'search'])` at **:476** — :441 is the departmental `orWhere` inside
  the ownership split. Reading a `:NNN` pointer therefore asks *which claim of the sentence does this line carry*,
  not which method it sits in; and a row whose cell cites two numbers is two citations, exactly as the
  bare-continuation rule says. The rest of that tranche read **true** and is recorded as read, so the next reader
  does not re-open it: `NotificationController.php` :20-21/:33-34/:51/:65-66/:65-68 (reader, unread count, ownership
  test, bulk mark-read), `PrintService.php` :49/:115/:254, `DevReferenceDataSeeder.php` :137/:141/:148 (the three
  `Academicyears::firstOrCreate` calls behind §21's `academicyears` row), `Studentclearances.php` :87-93,
  `AdmissionPolicy.php` :67-152 (the whole extracted method, return at :151, brace at :152), `AuditRecordStates.php`
  :305-327 — **a range deliberately straddling two rules**, "the two box rules", so the straddle is the claim — and
  its "nineteen rules" is 19 `'count' =>` entries, measured not recalled; also `RbacSeeder.php`, `IDController.php`
  :184, `StudentController.php` :49, `AuthenticatedLayout.jsx` :85-113, `Toast.jsx` :164-168,
  `SendWorkflowNotification.php` :57, `WorkflowService.php` :54-73, and the pointers passes 229-234 wrote themselves
  in `BlockingController.php` and `ExamController.php`. **The instrument-side trap in that count is mine again:**
  grouping the queue by `grep '^UNBOUND '` made the footer's own sentence a file named `and` — a queue row is the
  padded `printf` shape, not the leading word (the same lesson the blind-sheet harness already carries).
  **Pass 243 is the one that never ran, and it is kept because the refusal is the finding.** I came to
  `resources/js/Pages/Registrar/Index.jsx:93` believing the page badge the sentence quotes was :92 and that :93 was
  the office badge — read off `sed -n '92p;93p'`, which prints two lines in file order with **no numbers attached**. A
  real line-numbered reader says otherwise: :92 is the `subtitle`, :93 is `phaseBadge="Phase 5 · Registrar Central Desk"`
  — the citation was always right. The pass asserts the false hypothesis in its stage 1, so it fails three guards and
  exits `NOTHING WRITTEN` every time it is run, and the file stays as the proof: a unique needle, a correct
  pair-form, a bounds-clean document and a confident reader still cannot make an edit true. **When the claim is "this
  pointer is one line off", read it with a numbered reader** (`lane70-read-span.php` prints the number), never with a
  bare `sed -n 'Ap;Bp'`.
  **Pass 244 worked a different figure family: a count over demo rows, written in the present tense.** §20's ID-desk
  paragraph said the Issued tab holds "seven on this box against the two the queue holds". Replicating the controller's
  own predicate (`IDController.php:67-72` — `enrolled` enrollments having a `completed` step for office 22) on live
  `ems` answers **nine** (53, 92, 1063, 1064, 1065, 1066, 1067, 1284, 1285) while the second half is still exactly two
  pending rows, so the count had drifted with the dataset and the sentence's structure was sound. The pass wrote
  `nine` and left the number's twin alone: §28's register row that also reads "seven … against the two still queued"
  **carries its own date, 2026-10-17**, and a dated row records the day it was written — re-aging it would state a
  figure that was not true on that day (lane 60's rule, met from the other side). Two mechanics: the pass refuses to
  write unless the server answers the number it is about to type, and its first draft was refused by the engine because
  `$edits` was built as `[needle => replacement]` where `docxApply` takes a LIST of `[from, to, anchor?, expect?]` —
  `[$from, $to] = $edit` over a string yields single characters, and the "replacement is identical to the needle"
  guard is what stopped a corrupt write. My first measurement of the same claim counted **16**, because I asked
  `signedBy is not null` across every workflow instead of the tab's `stepStatus = completed` + `enrolled` predicate:
  a number that disagrees with a document is worth re-asking only after checking it was the same question.

  **A queue that has been read must say so, or the next session reads it again.**
  `storage/app/scratch/lane70-queue-reads.json` records the rows this lane has opened, keyed by
  `lane70-anchor-cites.php`'s OWN identity — the file its sentence names, the range, and a hash of the sentence; never
  a dump line, because one inserted paragraph above would age every later record to make one unrelated edit. The
  checker printed `queue reads on record: 107 of 107 UNBOUND row(s) carry a recorded read; 0 record(s) match no current
  row` with `104 record(s) answer 104 distinct key(s); 3 key(s) carry more than one row` beneath it — one sentence
  written in two places in the document is ONE read covering TWO rows — and every row of the queue has been opened
  once (every record names its own evidence). **Re-run on 2026-10-27, after docx passes 252 and 252e lengthened
  sentences and inserted register rows, the same line answers 38 of 106 rows recorded with 69 records matching no
  current row** — the third way a key disappears, met again and at scale, because the hash covers the sentence and the
  sentences moved. **That same day the queue was drained, and the answer is now `108 of 108 … 0 record(s) match no
  current row`.** Two defects in the instrument came out of doing it, and both are classes this file already names:
  `lane70-read-unread.php` reported eight keys as `NOT FOUND` because it resolved only basenames, while the document
  spells some keys with a path (`app/Http/Controllers/Exam/ExamController.php:524-524`) — an unresolved key is the
  reader's age, never a missing pointer, so the locator now honours the path part and refuses to guess; and
  `lane70-make-reads.php` matched its verdict map by prefix on the *whole* key, so an aged key silently inherited the
  2026-10-25 prose and today's date while no reading of the moved pointer had happened. Verdicts are now keyed by
  `File.php:NN-NN` with an explicit read date, consulted before the older maps, and the generator prints
  `no verdict on file for N key(s)` with each head named — a queue it cannot answer is printed, never folded into the
  covered count. **The fallback still fires, and only a dated block stops it.** When lane 85 aged 22 keys, the
  generator answered `no verdict on file for 0 key(s)` and `105 of 105`, although ten of those ranges had only just
  moved: `$byFile` matches on the FILE, so prose written against the previous layout attached itself to lines nobody
  had opened since. The rule is therefore not just "key verdicts by range" — it is that **every batch that shifts a
  cited file appends its own dated block before that fallback is reached**, and the test of honesty is a record's date
  against the day the line moved, not the coverage tally. Sixteen records now read 2026-10-28 and no
  RegistrarController record still wears the older date; the ten spans were opened in the file first, each carrying
  the action or field its own sentence names. **The two instruments this drain produced each got a harness the same day, and each found a defect on
  its first run, which is the whole argument for writing them.** Honouring the path part was only the first half of
  the locator's story: its fallback was a list of depth-limited `glob()` patterns, and **PHP's glob has no recursive
  double-star**, so a "tests" + double-star + filename path answers zero and every key under a two-deep directory
  printed NOT FOUND while the drained queue showed none — the locator now walks the tree once into a basename index
  and returns the candidate count, so "no such file" and "two files share this basename" are refused separately
  rather than guessed.
  `pass273-count-moves.php` opened its arguments with `if (! $z->open($file))`, and **`ZipArchive::open()` answers an
  error CODE for a file that is not an archive, which is truthy**, so the guard never fired and the run died on
  "Uncaught ValueError: Invalid or uninitialized Zip object" instead of refusing; the form is `!== true`, and
  `mutate-pass273-count-moves.php` plants both bad files (a text file named `.docx`, and a well-formed zip holding a
  105-byte `document.xml`) to prove each refusal. A negated `open()` guard is the same mistake as trusting
  `filesize()` for byte-identity: the function answers something truthy and the refusal you wrote never happens.
  Re-take a STALE row by reading it, then regenerate with `lane70-make-reads.php` — never re-key by
  hand, and never quote 107-of-107 as though it were the
  instrument's permanent verdict rather than one day's reading. A record whose key has
  gone is printed as **STALE**, not dropped: the two ways a key disappears are "a pass fixed the row" and "a pass
  re-pointed the pointer", and the second means the saved verdict says nothing about the new citation. **A third way
  was measured on 2026-10-26, and it is neither: the pointer and the file both stayed put and the sentence around them
  was lengthened.** Pass 246 added a clause to §18's `ems:preflight` sentence, the key's sentence-hash moved
  `636f8e39` → `31ade585`, and four `Preflight.php` records went STALE at once (103 of 107). The ranges were untouched
  and still true — each was re-read with `lane70-read-span.php` and starts and ends inside the construct its own clause
  names: `Preflight.php:228-243` is `applicationKeyIsReal`, `Preflight.php:258-285` is `servedFolderStaysPrivate`,
  `Preflight.php:605-628` is `uploadDiskIsPrivate`, and `Preflight.php:408-484` is the host-zone family — the file
  having grown by exactly 64 lines above them, measured against the same commit's copy of the file. So the fix was to
  re-read, then regenerate with `lane70-make-reads.php`, never to re-key by hand. **Re-read before you re-record: a
  STALE row asks *why* the key moved, and only one of the three answers means the verdict still holds.** Nothing is
  recorded that was not opened. **The 2026-10-27 drain moved 32 citation numbers out of the document and 33 in,
  across ten places.** `storage/app/scratch/pass273-count-moves.php` answers that by diffing the multiset of
  citation-shaped tokens between two copies of the docx, and it exists because the two totals I first wrote down
  were arithmetic from memory and both were wrong. Reading the queue file by file turned up §25.1's print-route
  table: its Staff-route column cited nine web.php numbers — lines 146 to 151, plus 124, 138 and 139 — which today
  are the Blocking chapter banner, five blocking CRUD routes, the Accounting banner and two clearance acts; and its
  "what the browser gets" column named RegistrarController
  lines 388, 421 and 444 for the certificate, class-card and subject-load pages, which are printClassCards' signature,
  a line inside printSubjectLoad and the brace of downloadCertificate, with the three PDF cells beside them landing on
  a closing brace and two other documents' downloads. Every one of those numbers is a live line in
  the right file, so the bounds sweep, lane 32 and the anchor checker all agreed with them; only opening
  `routes/web.php` said which route is which. Two lists of the same five print acts (§25.1's reachability paragraph
  and §28's P-3 row) both carried the three-number run 388, 405, 428. Pass 272 re-took all of it, and its
  own assertion loop is the reason the two lists do not now agree on the wrong thing: the paragraph's claim is *every
  `Inertia::render` whose component contains Print*, so it got the three render lines, while the P-3 row's claim is
  that each desk print writes its log row, so it got the three action signatures — spelled as §23's
  `documentprintlog` row spells them after pass 271. **One range cannot carry two claims, and a list has to be cited
  on the line its own sentence names.** Pass 271 took the other three places: §23's print-action row and §28's E-13
  row (the state machine's enrolled edge had moved seventeen lines down when the two return states were inserted,
  and E-13's
  second claim — the subject's own `proposed → confirmed → dropped` line — had no pointer at all until the
  `SUBJECT_TRANSITIONS` block was cited beside it), and §6's Registrar/Show cell, whose second number was a closing
  `</div>` while the year level and standing print one div lower. Both passes print their own assertion count and
  refuse the write when any new number fails the line it is offered for, which is the only thing that separates a
  re-point from a re-guess. The historical numbers above are spelled as prose on purpose: a colon in this file makes
  a token a citation, and a citation to a retired layout is the defect the paragraph exists to warn about.
  **Then pass 273 inserted one §1.1 row and 59 honest records went stale, which is a fourth way a key disappears and
  it is the instrument's, not the document's.** The checker's identity is `file:range#md5(sentence)`, and the sentence
  is a 240-character window of the flat dump — which `dump-docx-flat.php` labels with a *generated* ordinal
  (`ROW 2466:`, `P 656:`). A pointer sitting near the start of its row therefore hashes that ordinal, so adding a row
  anywhere above it renumbers every later row and ages its record: the very defect lane 44 names for a mutant anchored
  on a generated line ordinal, arriving in a read's key. The window is now folded to `ROW:`/`P:` before hashing, and
  `mutate-lane70-queue-reads.php` grew two legs — one renumbers the whole dump through `--dump=` and requires coverage
  to hold at 108 of 108, the other plants the old keying back in a copy and **requires coverage to fall**, which it
  does (the mutant keeps 49 of 108). A generated label is never an identity: check what the dumper adds to the text
  you hash, not only what the document says. **What a record does not claim:** it says *this span carried this sentence's claim on
  this date* — a later code move leaves the key untouched, so a record is no substitute for re-running
  `probe-moved-cites.php` or lane 51's reconcile after editing a cited file, and the same pointer can be keyed under
  two spellings (`ExamController.php:476` and `app/Http/Controllers/Exam/ExamController.php:476`), only one of which a
  record covers unless both are written. **That hole was measured and it opens one way only:** grouping the 104 keys by
  `basename + range + sentence-hash` answers **0 collisions**, because the file component always resolves to one real
  file — so an alias can make a read row look *unread* (the conservative direction, and what STALE is for) but can
  never make an unread row look read. A record set may under-report coverage; it cannot falsely clear it.
  `lane70-make-reads.php` regenerates the file
  from `--show-keys` output so the keys can never be re-derived by a second definition of "pointer" (the blind sheet's
  eleven-fold phantom), and `mutate-lane70-queue-reads.php` (13 legs) proves the mechanism: a ghost record must be
  STALE and must lower the covered count **by that key's own row count**, an empty file must leave every row unread,
  muting the collector's four sites must make the run refuse because `tally ≠ collected`, a byte-identical copy must
  answer like the control, and the coverage line must add up to the queue.
  **A coverage figure must count the same thing as its denominator.** That harness once asserted "−1" for every
  removal and the tool printed `104 of 107` — records over rows — for a queue in which **every** row had been opened:
  three sentences are written in two places, so 104 keys answer 107 rows, and the number sent the next reader after
  three phantom reads while making the document's own claim ("the whole queue has been opened once") look
  contradicted by its instrument. Coverage is now `array_sum` over the matched keys' tallies, the run refuses unless
  `covered + uncovered == tallied`, and leg 6 plants the old shape back (a record-count numerator) and requires it to
  report read rows as still unread — which is what makes the two "−2 / −1" legs bite instead of being decoration.
  Two bugs in that leg came from reading rather than trusting it: its anchor was built by concatenation and put
  `"UNBOUND"` where the file writes `'UNBOUND'`, so it matched nothing and **refused the whole run** (the guard
  earning its keep), and its array was indexed `[1] < [2]` when `preg_match` had filled `[0]` and `[1]` — a leg whose
  detail line printed the right numbers while its assertion compared the wrong pair.
  **A record that is not tracked is not a record.** `storage/app/.gitignore` re-includes `scratch/` by extension
  (`.php`, `.ps1`, `.mjs`) and ignores everything else, so the reads file was invisible to `git` and the mechanism
  would have lived on this machine only; it needs its own `!scratch/lane70-queue-reads.json`, and the check that
  proves a negation is targeted is `git add --dry-run <file>` plus `git check-ignore -v` on a file that must stay out
  (`lane59-readings.json` still answers `yes`).
  Three of its own traps: `2>/dev/null` is no device for `cmd.exe`, so the generator's first run produced a
  well-formed file with **zero** records; a copy of the tool cannot live in a temp subdirectory, because the tool
  finds the project root by `dirname(__DIR__, 3)` and dies on the require, which a leg that greps for `REFUSED`
  reads as a mutation; and muting ONE collector site mutates nothing when that branch carries no rows today, so the
  leg mutes all four.
  **Pass 242 then took five pointers out of two desk-display rows, and the shape is different from every one above:
  the numbers had slid off a field onto its neighbour, inside a JSX file the row names correctly.** The Student 360
  row cited `Students/Show.jsx` `:152,186,193` for "Name, School ID"; those lines are `officeBadge=`, the
  `{/* Identity fields */}` comment and a `</FormSection>` — the name is the `PageHeader` title at :147, the School ID
  its subtitle at :148 and the identity field's value at :189. The same row's next pointer, 68-69 in that file
  ("Student Type and Enrollment Type"), is a closing `</div>` and a blank line while the two types print at 60-61,
  and its 73-74 ("enrollment status") is the money grid's wrapper divs while the badge is 65-66. The Evaluation
  queue row cited lines 84 to 91 of `resources/js/Pages/Evaluation/Index.jsx` for "Student Type as a badge column,
  with Year Level and Proposed Standing beside it" and the pair 84/90 for "Year Level and Academic Standing": 78 is Year Level, :79-83 is the Student Type badge,
  :84 is Proposed Standing and **:90 is the Status column** — a number that belongs to neither claim. Now
  `:78-91` and `(:78, :84)`, with the row's third citation re-read and left alone because `:186` really is
  `label="Transferees / Shifters"`. Two rules this adds. **A JSX pointer needs the render line, not the block's
  opening tag** — a component's props and its closing tags are live lines too, so the bounds sweep says nothing when
  a number lands on `</FormSection>`. And **a re-taken number that already exists elsewhere cannot be post-checked
  by document-wide absence**: `:60-61` and `:65-66` were live citations before this pass (2 and 3 copies), so the
  only honest after-check is *the replaced token is gone* plus *the new one occurs one more time than it did*,
  which is what the pass asserts; my first draft instead compared run-boundaries old-to-new and refused a write
  that had already landed correctly.
  **The queue's second row was a different finding, and it is the twenty-fourth wrong target.** A bare `:62-104` in
  §2's clearance paragraph claims that ruling 12 retired office 8 in foreign-key order, and the nearest name before
  it on that dump line is `ClearancePolicy.php:68-94` — in that policy :62 and :104 are both bare `}`. The span that
  actually deletes, in FK order, is `DevReferenceDataSeeder.php:62-104` (:62 is the ruling-12 comment, :104 the
  `offices` delete), and §27's own office-8 row already cites it **with the file named** — so the document held the
  true owner twice and one of them unattributed. Pass 228 names the file rather than teaching any checker to guess
  (`( :62-104 :` occurs exactly once in `document.xml`, so the edit is a single unique `text` replacement), the
  attribution dispute the reader raised is thereby settled by the document rather than by a tool, and the census
  moved the way a repair should: **728 citations / 155 files / 0 line problems, 134 bare judged + 1 blind**, and the
  anchor's UNBOUND queue down from 110 to 108.
  **The next row read was the twenty-fifth wrong target, and it took two passes because one sentence is spelled two
  ways.** §26's room-capacity row says "Filing or editing a schedule that names a room too small for the block warns
  and proceeds (BlockingController.php lines 264 to 271 and 311 to 317, as the file stood on 2026-10-25 — after this batch moved the conflict rule into `App\Support\TimetableConflicts`, the same two warnings are at lines 270 to 272 and 331 to 333)" and then quotes both warning strings. The filing half
  was right — :264-271 is `storeSchedule()`'s warning block (`maxStudents > $room->capacity` at :269, "Schedule was
  still added" at :270). The editing half was not: `:311-317` is `updateSchedule()`'s *temp-schedule build for
  conflict detection* (`$schedule->replicate()` at :310, `->fill(` at :311), while the capacity warning whose words
  the same sentence quotes is **:327-333** (condition :330, "Schedule was still updated" at :331, returned
  warn-and-proceed at :345-347). the range 311 to 317 then occurred three times in `document.xml` and 264 to 271 four times, so
  neither is a usable needle alone; the *pairs* are, and there are two spellings — wide comma (1 copy, pass 229) and
  tight comma (2 copies, pass 230 with `expect=2`). **A first draft of pass 229 refused its own correct edit** because
  its post-condition asserted the bare number had vanished document-wide; two of the three copies belong to other
  citations, so that assertion could only ever fail. *A uniqueness check on a needle is not yet a check on the
  consequence:* scope the after-check to the replaced pair. The document now carries `:327-333` three times and
  `:311-317` zero, with the sweep unchanged at 728 citations / 0 line problems.
  **Pass 231 is the twenty-sixth, and it shows what a *range* citation can hide at a method boundary.** §10.4's
  board-course row claims "non-board programs are never examined at either stage" and cites
  `ExamController.php:181-247 and :311-343`. Neither half of that property lives in `:311-343`: it is the tail of
  `recordGeneral()`'s upsert (`firstOrNew` at :312) and the *first lines* of `recordCourseSpecific()`'s signature
  (:339-343). What keeps both stages from running is the pair of guards — `if (! $course->requiresEntranceExam)` at
  :306 returning "This course does not require an entrance exam" at :307, and `if ($course->requiresEntranceExam)` at
  :366 under the BR9/C-4/C-5 comment at :358 that pass 224 established — so the row now reads
  `…:181-247, :306-308 and :358-366`. **A range that straddles `} … public function … {` looks alive to every bounds
  check and says nothing about either method**; check which function each endpoint is inside. The pass also refused
  twice before it wrote, because its own assertion put `recordGeneral()`'s closing brace at :334 when it is :333 —
  which is the point of asserting the lines rather than trusting the arithmetic. After the write the queue is
  136 judged = 17 OK + 7 MOVED + 2 NO-ANCHOR + **109 UNBOUND** + 1 BLIND: one pointer became two, so the human-read
  queue grew by one and that is the honest accounting, not a regression.
  **Passes 232-232c are the twenty-seventh, and they closed a claim, not only a pointer.** Reading the WorkflowService
  cluster found three shapes at once. (a) Two ranges for `stepsFor()` — `:49-80` (two copies) and `:52-83` — both walk
  off the construct's closing brace (:76) into `createWorkflow()`'s docblock; every bounds check calls them live.
  (b) The §29.2 T-1 row cited `WorkflowService.php:208-297 (box list)`, but 208-297 is `signStep()`, which holds no box
  list — the seven entries and the `array_filter()` that makes six are `stepsFor()` at `:56-71`, while the row's *other*
  pointer (`:261-272 (order check)`) was correct. Sixth wrong target of this lane. (c) §13's "One workflow form per
  enrollment" row said `createWorkflow()` "is called once, from the Evaluation signature", and §18.6's workflow row said
  the row is "created later — when the evaluator signs, not at enrollment creation — and `createWorkflow()` has exactly
  one caller", citing lines 532 to 535 of `EvaluationController.php` as the only createWorkflow call. A call-site census of `app/`
  answers **two**: `EnrollmentIssuer.php:78`, inside `issue()` directly after `Enrollments::create` at :76, and
  `EvaluationController.php:803`, behind `if (! $enrollment->enrollmentworkflow)` at :802. So the one-form rule is held
  by that existence check, not by a single call site — the row's conclusion stands, its stated reason does not — and
  neither cited span contained the call at all (those 532-to-535 lines are `decideStanding()`'s validation, and 466 to 485 is
  `captureProfile()`'s address loop). **When a register row explains a rule by a count, measure the count**: the script
  refuses unless `app/` answers exactly the number the new sentence asserts.
  **Then my own repair made a blind spot, and only the sweep's blind list saw it.** Lengthening §13's step-row sentence
  pushed its bare `:796-809` past the sweep's 160-character attribution window: the run went from 0 blind to 1 blind
  while still printing **0 line problem(s)**. Pass 232c named the file in the pointer. *After any pass that lengthens a
  sentence, read the blind-list delta, not just the problem count* — a citation can become unreadable to the gate purely
  because prose grew. Second instrument lesson in the same pair of edits: `the only createWorkflow call` is **count 0**
  in `document.xml` because the styled `createWorkflow` run splits it, so the label was rewritten around the split with
  needle `the only ` + anchor `:801-804` (first needle occurrence after a unique anchor) rather than a cell rewrite,
  which would have flattened the cell's styled runs to one. After all three writes: **729 citations / 155 files /
  0 line problems, 134 bare judged + 1 blind** (the by-design example in §1.1), the anchor run 135 judged = 17 OK +
  7 MOVED + 2 NO-ANCHOR + **108 UNBOUND** + 1 BLIND, `lane37` 908 references / 0 problems, `lane32` 5 distinct pairs /
  8 flags, OOXML CLEAN, `verify-docx` CONSISTENT.
  **Passes 233-234 took the §13 examination cluster, and a refusal is what found the third shape.** Six copies of
  `ExamController.php:181-247` all named the *passer transfer* while the branch closes at :241 and `} else {` is :244 —
  so each range reached six lines into the comment that explains the **opposite** case (:245-249) — now `:181-241`.
  The row's two bare continuations were loose the same way: `:225` (one line inside the waived query) became the whole
  guarded block `:210-240`, and `:239-250` (opened on the waived row's closing bracket) became the else arm
  `:244-251`. §13's paragraph said **`entranceApplicants at :239-250`** — the *call site* at :250 re-used as if it were
  the definition; the method is `:279-288`. And two rows cited the method as `:279-290`, which walks past its brace at
  :288, over the blank line after it, into `recordGeneral()`'s docblock at :290. **A token's count is not its owner list:** a
  first draft asked `expect=3` for the range 279 to 290 and the engine answered **3 occurrences** — the third is
  `IDController.php` lines 279 to 290 in §20's ID-remark row (both spelled in words on purpose: this records what
  the engine reported that day, and the colon form is a live pointer the citation gate reads — the ID controller has
  since moved a line), a different file whose span is a legitimate method. Both
  ExamController copies were then taken by anchor instead, after measuring each anchor phrase unique in
  `document.xml`. Same batch, same shape at `WorkflowService.php:52-83` and `:49-80` → `:52-76`.
  Readings after 234: **729 citations / 155 files / 0 line problems, 134 bare judged + 1 blind**, anchor 135 judged =
  17 OK + 7 MOVED + 2 NO-ANCHOR + **108 UNBOUND** + 1 BLIND, `lane37` 908 references / 0 problems, `lane32` 5 distinct
  pairs / 8 flags, `repo-file-line-citations` 78 / 0, `lane59` 6 judged all match, `lane61` silent, whole Pest suite
  **806 passed / 5,458 assertions**, Pint 391 PASS, PHPStan 0, ESLint 0, Vite ✓, OOXML CLEAN, `verify-docx` CONSISTENT,
  Word 224 pp / 185 tables. **No test in `tests/` reads the authority docx** (measured by grep), so a docx pass never
  invalidates a suite run — but the docx has its own gate, the citation sweep, which every pass must leave at 0.
  **Passes 235-238 drained the next tranche of the human queue, and the tranche produced a new defect class.** Fifteen
  rows were read against their files: all fifteen `RegistrarController.php` pointers are *true* — each cites an action by
  its effect (the three `print*` pages at :360/:382/:413, the four `download*` PDFs at :437/:452/:464/:490, the seven
  boxes and their caps at :483-493/:495-507), which is precisely why no identifier rule can bind them — and the rest
  gave five real defects. §28's E-11 row cited `:288-289` for "a warning string is returned": :288 is the ternary's
  **false** branch (the return when no warning exists) and :289 closes the transaction; the warn-and-proceed whose words
  the row quotes is **:286-287**. §13's step row cited `EvaluationController.php:273` for "evaluatedBy written by
  profile capture" — :273 is a *comment* inside `returningStudents()`; the write is **:496**, inside `captureProfile()`
  (416-522). §28's T-5 row cited `:259-293` for `satisfiedPrerequisites`, which is **:316-340** (:334 is the
  `Creditedsubjects` read the sentence names; :293 is the *next* method's signature); §21's proposal row cited
  `:586-605` for the "prerequisite auto-gate" (**:643-667** — 586-605 is the curriculum load plus elective grouping) and
  `:236-257` for "curriculum pinning" (**:184-186** — 236-257 is `show()`'s capability props).
  **Then came something no instrument in this repository can see.** Four sections narrate gap **N-1** in the present
  tense: §19.4's writer table (`notifiable_type = "App\Models\Students", notifiable_id = studentId`), §19.4's
  consequence ("no real notification is ever displayed. The writers address Students; the only reader filters on
  Staffusers"), §20's FK-purpose row ("**Broken today**: writers store `App\Models\Students` rows that no reader
  queries"), and §24.14 ("Correction established in this edition: … both listeners write `notifiable_type =
  App\Models\Students`"). §28.3 closed N-1 on **2026-10-01** and the code agrees: both listeners write
  `Staffusers::class` / `$userId` (`SendEnrollmentNotification.php:62-63`, `SendWorkflowNotification.php:88-89`),
  `WorkflowInbox::actors()` plucks staff userIds (`app/Support/WorkflowInbox.php:87-99`), and the listener's own
  docblock states that students are not notifiable. **The pass measures live `ems` instead of recalling it** — it
  refuses unless the table answers exactly one `notifiable_type`, `App\Models\Staffusers` (14 rows). The instrument
  gap: `lane61` judges *status words*, `lane60` *figures*, `lane59` *instrument counts*, the sweeps *line numbers* —
  none binds "the document states how this code behaves today" to the code, so a mechanism sentence can be overtaken
  while every gate stays green. This is the §12 Gate-4 shape (a stale claim under a live pointer) reached from the
  architecture side, and the operating rule follows: **when you correct one section, grep the whole document for the
  same mechanism sentence** — the false claim appeared four times and three of them were outside the section that owns
  the finding. Three mechanics paid for: `App\Models\Students` occurs **7 times** in `document.xml`, so no blanket
  replace was allowed (the two writer rows went through the `cell` kind, §24.14 through `para`, because its class name
  sits in a styled run and no text needle spans it); a guard that counted
  `substr_count(file_get_contents($docx), …)` measured the **zip bytes**, not `document.xml`, so it "found" 0 copies of a
  plaintext needle and refused a correct edit — read the part out of the archive first; and a shell wrapper that applied
  on `grep -q "DRY RUN"` matched the banner printed *before* its own refusal, so decide from the **last line**.
  Readings after 238 (which supersede the 234 line above): **730 citations / 156 files / 0 line problems, 133 bare
  judged + 1 blind** — naming the file in §19.4's pointer took a row *out* of the human queue, UNBOUND 108 → **107**,
  the first net reduction of that queue in this lane; anchor 134 judged = 17 OK + 7 MOVED + 2 NO-ANCHOR + 107 UNBOUND +
  1 BLIND; `lane37` 909 / 0; `lane32` 5 pairs / 8 flags; `lane61` silent; `lane69-open-inventory` 0 OPEN, 4 sheet items;
  OOXML CLEAN; `verify-docx` CONSISTENT; Pint 391 PASS; PHPStan 0; Word 224 pp / 185 tables, 104,733 words.
  **Two of the 47 were real, and they are the tenth and eleventh wrong targets the bounds sweep cannot see.** The
  notification row's `stepLabel (:45)` had been left behind when pass 219 moved the field beside it — line 45 is
  `['completed' => true]`, the completion branch's payload, while the label is written at **:64** from the map read
  at :31. And the WorkflowStepSigned row claimed **two** things of one range, `( :52-63 )` — "one row per signature"
  *and* "plus one workflow_completed row when the last box closes" — while the signature notify is `:54-70` and the
  completion notify is `:38-48`; the number named no file, so what the sweep had been "judging" was whichever
  filename happened to sit nearest, `WorkflowService.php`, whose :52-63 is `stepsFor()`'s office-box list and writes
  no notification at all. Pass 221 fixed both (`refresh-docx-pass221.php`, ten line assertions gate the write, the
  `:52-63` needle unique in `document.xml` so it travels as a text edit rather than a cell rewrite that would
  flatten the styled runs). The rule for anyone citing a register row: **one range cannot carry two claims, and a
  pointer that names no file is judged by accident.**
  **The same queue's third find was not a citation at all, and it is the reason `--show-ok` and a re-read are not
  optional.** §24's `scholarshiptypes` row asserted that "two types exist on live `ems`, both seeded and both still
  **labelled provisional**". `describe scholarshiptypes` answers four columns — `scholarshipTypeId`,
  `scholarshipName`, `coverageType enum('full','partial')`, `coveragePercent decimal(10,2)` — and no code anywhere
  writes a provisional label on a type, so the row named a field the schema has never held. That is G-28's family
  (§21 naming columns no table has) reached from the other side: not a stale number, an **invented vocabulary**.
  Pass 222 replaced the clause with the measured rows (`StarterReferenceDataSeeder.php:52-53`, full at 100.00 and
  partial at 50.00), and pass 222b repaired a doubled conjunction my own replacement introduced — "both seeded and
  **and** neither" — which only reading the re-dumped row could show, because every checker passed it. **Read the
  sentence back after a `text` edit; a needle that starts mid-clause inherits the clause it replaced.**
  The row also exposed a classifier defect: `lane69-open-inventory.php` charged it as work owed by an office,
  because its OFFICE half matched the office words against a row's **leading cell**, which for a §24 row is the
  table name — `scholarshiptypes` contains "Scholarship". The predicate is gone; the inventory now delegates to
  `lane61-who-owes.php`, the one tool that indexes the actual "Who must act" column, and its answer is 2 rows —
  **C-5** (Department) and **G-13** (Owner / whoever provisions the server). A `--owes=` override exists so that
  refusal can be proved: pointed at a stub that prints no tally, the inventory exits 1 quoting the stub instead of
  printing an empty owed list, which is the shape a clean register fakes.
- **After any batch that changes `lane70-blind-sheet.php` or the shape of the sweep's UNATTRIBUTED line**, run
  `php storage/app/scratch/lane70-blind-sheet.php` and prove it with
  `php storage/app/scratch/mutate-lane70-blind-sheet.php` (10 legs). The sheet is the reader's only route to the
  blind list, so every guard it carries is a refusal the repository has already paid for, and each one now has a
  leg: a row the sweep tallies but the sheet cannot parse, a run that printed no tally at all, a sweep that exited
  2 (neither clean nor a reported problem, so its output is not a measurement), a dump line number past the end of
  the file, a named line that does not carry the token it names, a missing dump, a byte-identical copy that must
  change nothing, and a copy in which the blind token is given its file — which must answer **0 blind pointer(s)**,
  proving `--dump=` is forwarded to the sweep rather than accepted by the sheet alone. Two of those legs are only
  reachable through `--sweep=`, a stub-output override, because the sheet and the sweep deliberately read the *same*
  bytes: a divergence between them cannot be planted in a dump, and that divergence is exactly what the per-row
  check exists to catch. **The check itself replaced a blank with a refusal**: a wrong line number used to render an
  empty sentence, which reads exactly like a pointer written with no context and sends the reader to the wrong
  paragraph. **Prove a harness against the tool's own previous bytes.** `git show HEAD:` gave back the committed
  sheet, and fed the out-of-range stub it answered `1 blind pointer(s)` and **exit 0** — the flag was inert and the
  guard did not exist. Had I run only the new harness and watched ten PASSes, I would have recorded a green suite
  over code that cannot fail; a harness whose legs all pass on the first run is the shape that deserves the extra
  probe, not the extra trust. And a refusal must relay the cause it was handed: `--dump=<nonexistent>` first
  printed only "no blind-spot tally" while the sweep had already said *"the dump … is missing or too short"*, so
  the sheet now quotes the sweep's own lines — a reader pointed at the wrong tool fixes the wrong tool. The dump is
  2,766 lines and the one remaining blind row is the by-design example inside §1.1's own `2026-10-16–20` revision
  row — lane 51's record of the re-measure, which quotes `the signature at :273` to show a bare continuation that
  names no file; the harness reads that phrase's line number out of the dump rather than
  hardcoding it, and refuses the whole run if it does not occur exactly once — a needle that cannot land reports
  BAD, never a pass.
- **§28.8's "Who owes what" table must agree with the column it claims to be counted from** (lane 69). It
  asserted 46 owed slots while `lane61-who-owes.php` — which reads the "Who must act"/"Who must decide"
  column itself — counted 8, and 2 after the owner's rulings of 2026-10-24. This file recorded that exact
  discrepancy in a pass note and left the table standing, because every checker in the repository judges
  *sentences*, and none compared a table to its source. `php storage/app/scratch/lane69-owes-table-check.php`
  now does, and deliberately does NOT re-classify the column: re-implementing "names an actor" would create a
  second definition of it, which is the defect lane 69 spent a batch removing for year levels. It cross-checks
  the identifiers in both directions, prints the instrument's own blind spots, and refuses when the dump, the
  tally or the table is missing. Prove it with
  `php storage/app/scratch/mutate-lane69-owes-table.php`: a re-grown actor count, an identifier the column no
  longer charges to anyone, an owed item dropped in favour of a closed one, and a byte-identical control.
  Its three standing blind spots are now read and explained: rows whose verdict cell carries a **prose pipe**
  (`granted | rejected` in a status trail) split into 6, 13 and 7 cells, and all three lead with a closed verb —
  they are shape noise, not missed actors, so the run is clean at 2 owed slots while still printing them.
  **Its first run refused all three mutants for the wrong reason:** it judged "byte-identical" by `filesize()`,
  so a one-digit count and a `C-5`→`C-6` swap — same length by construction — were reported as mutations that
  changed nothing, and its exit-capture leg redirected to `/dev/null`, which cmd.exe cannot find, so cmd
  exited 1 before the checker ran and the *control* failed. Compare bytes, not sizes; `2>&1`, never a
  POSIX device.
- `storage/app/scratch/lane32-cite-meaning-sweep.php` automates the first pass of that reading (~2 s over the
  135 symbol/range pairs it checks, 12 pairs flagged for reading across 9 distinct constructs). It takes the
  construct the prose *names* and asks whether
  that name lives in the cited span or encloses it. It found the five drifted citations pass 117 fixed, and
  then found six more problems that the line sweep had cleared: five stale claims in §18.6 "Verification
  Against the Current Build" (pass 118/119/120) and one citation copied into two sections that both pointed at
  the middle of another method — the range in both places opened on a blank line of that controller and ended
  six lines short of `confirmClearance()`, which is 958-999; both were re-pointed. (Quoting the broken range as
  Quoting the broken range as a live citation here would have made this file carry the false pointer it warns
  about — and did, until
  `repo-file-line-citations.php` read this paragraph and flagged it.)
  **A flag that looks like noise must be measured before it is dismissed** — the Clinic item looked like a
  case-sensitivity artifact and was actually the document asserting a bare integer `11` that the code stopped
  writing on 2026-10-01. Four flag classes remain after that cleanup, all verified by hand: two ranges glossed
  in prose order (`:30 and :49 (ChromiumLocator::locate, then setChromePath)`); a label whose nearest range is
  on the other side of a comma or semicolon (`Payments::held()` landing on the accounting range its clause
  opened with; `recordIssue()` landing on a controller action, while its own `PrintService.php:115` sits later
  in the same sentence); and a name that is not this file's construct at all.
- The attribution rule is the delicate part, and it took three attempts. A name labels the range AFTER it when
  the gap is short and clean (`signStepByOffice() :289-305`, `recordIssue() at :115`) **or** when the name
  opens a parenthesis that the range sits inside (`confirmClearance() records the act ( :958-999 , permission
  … )`); otherwise it labels the range BEFORE it (`:748-756 builds each line from
  ProvisionalClearanceRequirements::NAMES`). Plain nearest-distance hung `NAMES` on `:62-104` across a comma;
  "short gap only" missed the parenthetical and kept flagging a citation that was correct. Run the sweep after
  the line sweep on any batch that moves lines, and re-prove it with planted moved-ranges
  (`mutate-lane32-sweep.php`, which must also show the *unmutated* document unflagged) before trusting its
  silence: a checker that cannot fail has checked nothing.
- **After any batch that touches a model's `$primaryKey` / `$timestamps`, or a §24 "Model …" row**, run
  `php storage/app/scratch/dump-docx-flat.php` then `php storage/app/scratch/lane71-model-decls.php` and prove it with
  `php storage/app/scratch/mutate-lane71-model-decls.php` (8 legs). **And after any batch that touches a
  notification's addressee — a listener's `notifiable_type`, `WorkflowInbox::actors()`, `NotificationSeeder`, or a
  sentence naming who the bell reads** — run `php storage/app/scratch/lane71-morph-targets.php` (and `--ci` for the
  two portable surfaces) and prove it with
  `php storage/app/scratch/mutate-lane71-morph-targets.php` (13 legs). That is the second member of the G-31 class
  turned into an instrument: one value read from three independent surfaces — what the document asserts, what the
  code passes, what the rows hold — measured 2026-10-25 as **8 document sentences, 5 code sites, 14 rows, all
  `App\Models\Staffusers`**, so the sentence §19.4 used to carry is now answerable rather than arguable. Two things
  it paid for immediately: `parse_ini_file()` on `.env` throws a syntax warning on this file's line 3 and hands back
  nothing, which the probe read as "the environment names no database" — parse `.env` line by line, as every other
  probe here does; and the probe first counted two classes because the document writes `Staffusers::class` and the
  rows hold `App\Models\Staffusers`, which is lane 69's one-definition rule applied to my own instrument, so the
  class identity is resolved once and shared by all three readers. **Two of its three surfaces are now a CI step.**
  `--ci` judges what a runner can reach — the document's claim and the code's write sites — and **names** the row
  surface instead of asking it, because a row count belongs to whichever database stated it (lane 60's rule) and the
  SQLite job has no persistent MySQL; the full three-surface run stays a hand command against live `ems`. The flag
  refuses `--allow-schema=…` rather than accepting it inertly, and the step prints
  `rows surface: NOT JUDGED` so an unread surface can never be read as an agreeing one. Proved by legs 8-10 of the
  harness: `--ci` must answer in its own shape, must quote no schema it never opened, and must still `DISAGREE` about
  a document claim the code disproves — a step that stopped judging would otherwise look like the cleanest line in
  the job. `--selftest` was **deleted rather than left working**: it set a variable whose only use printed a note,
  while its own docblock promised it would plant a wrong class in a copy of the dump (planting is the harness's job),
  and a flag the tool does not read is now refused by name — leg 13. **A sweep for further inert flags is reported
  withdrawn, not clean:** three passes produced 65 hits, then 312, then 0, and the 0 fell the moment the known positive
  was planted and walked straight through — the flag's use sits behind an `if` whose body prints, which a line-shape
  classifier cannot see. "This flag changes nothing" is a behavioural claim; the honest test is run-with, run-without,
  compare bytes. And `str_replace()` has **no limit parameter**: its 4th argument is the by-reference `$count`, so
  `preg_replace`'s shape throws "Argument #4 could not be passed by reference".
  **The behavioural version was tried and also failed, twice, before it measured anything.** Pass 4 ran each tool with
  and without its flags and compared bytes — and reported **12 behaviourally inert flags**, all of them truncated by
  one character (`--verbos`, `--appl`, `--show-key`, `--c`): the pattern `(--[a-z0-9-]*)(?![=\x27"])` makes the greedy
  class **backtrack one character so the lookahead can pass on the closing quote**, so every run passed an unknown flag
  that a tool correctly ignores — identical bytes, invented verdicts. Pass 5, which captures the delimiter instead,
  then found **0 candidates** in the same walk, i.e. its own population was empty: a census whose population is zero
  and whose previous population was wrong is not a clean result either. The one genuine signal out of both passes is
  the accidental one: `lane71-morph-targets.php` **refused the truncated `--c` by name** rather than ignoring it, which
  is the strict-flag guard proving itself on an input nobody planned.
  **Pass 6 ran the behavioural test properly and retired it, because identical bytes is the wrong predicate.** It
  walked 38 pairs and printed its population first: 19 flags changed the output, and every "did not" turned out to be
  the census measuring nothing rather than the flag doing nothing — `$opt('--readings')` and `$opt('--only')` are
  **value** flags whose bare names appear quoted, so passing them with no value is correctly ignored;
  `lane59-instrument-counts --trace` compared two 155-byte refusals; `lane70-read-span --functions` compared two usage
  lines because no file argument had been supplied. And the deepest: the very flag that started this — `--selftest` —
  **printed a note**, so a byte-diff grades it as read, while its real defect was that it changed nothing *judged*.
  So a flag is proven read only per tool, by a harness leg that asserts a **named difference in the verdict**
  (`--ci` must answer in its own shape and still `DISAGREE` about a planted claim; `--limit=N` must withhold rows the
  queue tally counts and then say the queue is INCOMPLETE; `EMS_LANE46_PAGES` must change what is scanned and refuse
  an empty scope). The family-wide inert-flag sweep is therefore closed as **not decidable by one instrument**, not as
  clean: `--selftest` stands as the one inert flag this repository found — by reading a file, not by a census.
  **The residual of the mechanism census was measured, not assumed.** Of the shapes `lane71-mechanism-shapes.php`
  prints, the two members that cite no instrument were opened and both read true: §20.3's quoted
  `SENSITIVE_KEY_PATTERN` literal is attributed by its own lead sentence, whose `AuditLogObserver.php` range really
  runs from that const to the redaction method's closing brace, and §12's no-open-period row cites the two lines
  where `accepting()` is called and the refusal sentence is returned. The class is therefore bound wherever it is
  mechanically answerable; the remainder is prose a reader judges. **And all six of the shapes tool's own
  `REFUTED` verdicts were read and are the probe, not the document** (measured with `--limit=200`: 2 VALUE + 4 NEG):
  three deny a *query result or row set* (`accepting()` returning nothing, `payments` gaining no row, a rendered page's
  row count) where a `substr_count` for an identifier's existence cannot answer; one probed the wrong token (a row
  that says the test reads `studentType`, `never academicStanding` was tested on the file's own class name); and two
  searched a literal that only exists entity-decoded (`enum(\x27active…`) or in SQL spelling (`DECIMAL(10,2)`, which
  the migrations write as `decimal(…, 10, 2)` and `information_schema` as `decimal(10,2)`, already bound by
  `lane60-ratio-claims.php`). No register row is owed for them; the lesson is that the next binding must be
  subject→predicate per shape — a SELECT, a parsed span, a schema query — never a corpus grep.
  This is the first checker for the class §28.3 G-31
  names: **a sentence that states how code behaves today carries no status word, no figure, no instrument count and no
  dead line number, so every earlier judge passes it while the build overtakes it** — §19.4, §20 and §24.14 all said
  the notification listeners write `App\Models\Students` twenty-four days after §28.3 closed N-1 and after both listeners
  began writing `Staffusers::class`. §24's model row is the one member of that class narrow enough to answer without a
  judgement call: it states `$primaryKey = 'someId'` and `$timestamps = true|false`, and the class either declares those
  properties or takes Laravel's defaults, so the probe is a comparison, not an interpretation. Measured:
  `lane71-model-decls` answers **55 claims read = 55 judged**, none of them wrong — and 55 is also what `app/Models`
  holds, which is the agreement that makes the zero mean something. The harness plants a wrong primary key and a wrong timestamps flag (each must raise exactly one named
  complaint and no other row may move), a path that is not in the repository, a byte-identical control that must change
  no verdict, and the three refusals are told apart **by their own messages** (judged-nothing, missing dump, unknown
  flag) because all three exit 1. It is a CI step in the SQLite job. Two traps already paid for: a mutant aimed at a
  token that occurs ten times (`$timestamps = true` appears in ten §24 rows) reports ten complaints and proves nothing,
  so plant on a needle unique to one row; and a leg that greps for the tool's own label must allow the `printf` padding
  (`WRONG-PK\s+line`, not `WRONG-PK line`) — that leg passed on the first run by matching nothing.
  The two exploration tools are kept with their failure documented in the file header:
  `lane71-mechanism-census.php` counts 4,590 sentences and shows a broad "verb + code token" rule yields 1,762
  candidates, which is not a queue; `lane71-mechanism-shapes.php` narrows to 73 VALUE + 16 NEG claims — a workable
  queue — but its corpus-wide `substr_count` verdict probe is noise (it "refuted" `Clearanceperiods::accepting()` for
  existing, called `studentId` consistent because it occurs 926 times, and let a §1.1 history row into the NEG bucket).
  Each shape needs its own subject→predicate binding, the way lanes 46, 47, 50, 60, 64 and 71 each needed one.
- **After any batch that edits an existential negative in the documents** ("there is no cancel action", "only allows
  firstYear and transferee"), run `php storage/app/scratch/lane34-inventories.php` and then
  `php storage/app/scratch/lane34-negative-claim-sweep.php`. The sweep existed since lane 34 but **nothing generated its
  ground truth**: it read route, column and permission lists captured by hand on 2026-10-15 and never refreshed, so it
  judged the document against a schema that had already moved (`extendedDeadline` was dropped 2026-10-23; the fresh
  capture answers 451 columns where the stale file held 452). The generator now writes a capture stamp and the sweep
  refuses a stamp older than the newest migration, or missing — an inventory with no date is indistinguishable from a
  current one, which is how this ran for ten days without saying so. **The queue is read, and the answer was the
  instrument, not the document:** all 14 hits are negation-attachment artifacts — the sentence denies a *role's*
  permission set ("the Dean role does not hold `block.view`"), a rule about enum values ("no assignment beyond
  `Blocks.maxStudents`"), or a table-vs-column distinction ("…is not a table"), never the existence of the identifier.
  The two mechanically checkable claims were probed against live `ems` by
  `lane34-probe-negative-hits.php` and both read **true**: `admissions.applicantType` is declared
  `enum('firstYear','transferee')` and holds only those two, and no dean-shaped account holds `block.view` (6 roles do,
  and the pivot's only `model_type` is `App\Models\Staffusers`). Reading the hits also disproved two standing
  instructions in this file — see the RBAC-pivot bullet.
  **The same defect was then searched for across every scratch tool, and lane 34 is the only real case.** A walk of
  every tracked PHP that reads a `.json/.txt/.csv/.sql` artifact, asking whether any tracked file writes it, answered 3
  orphans on its first run and 11 on its calibrated one — and every one of the eleven was traced to an explanation
  rather than left as a hit: `lane60-readings.json` is written by `lane60-probe-ratios.php --write-readings=…` invoked
  *indirectly*, and `lane60-ratio-claims.php` refuses when the probe does not report writing it; the
  `laneNN-suite*.txt` files are the logs that frozen `refresh-docx-passNNN` scripts consumed, which is provenance, not
  a live check; only `routes.txt` (14 days) and `routes.json` (25 days) are genuinely unmaintained snapshots, and the
  two files that read them — `probe-walkthrough-routes.php`, `route_coverage.php` — are one-off probes that nothing
  instructs anyone to re-run. **Both readers now ask the running application, so those two snapshots are retired rather than maintained:**
  `route_coverage` reports 0 of 90 mutating named routes unreached by a test against today's table, and the walkthrough
  probe reports 32 routes claimed / 0 not found while still catching a planted typo (2 MISS). Its first version did not:
  handing it bare `$r['uri']` values made every lookup miss and reported **31 of 32 walkthrough routes missing** — a
  fake finding about the guide of exactly the kind lanes 46 and 50 produced by reading the wrong construct. The walk
  parses `METHOD uri` pairs and `route:list` answers `GET|HEAD` as one method string, so each verb has to be emitted on
  its own line; sitting with a large bad number long enough to check what the instrument was handed is the whole
  discipline in one sentence. **The walk itself was wrong twice before it was right**: it required the artifact name to
  sit inside the write call (generators assign a variable first), and it matched only bare basenames, so
  `dump-docx-flat.php` — which quotes a whole path — was invisible and reported its own output as an orphan. A positive
  control (assert that `docx-flat.txt` and `lane34-routes.json` ARE attributed) is what caught both, and it is why the
  probe was deleted rather than kept: an instrument whose false positives are this cheap to produce must not be cited
  as a clean result. If this family is ever re-instrumented, the convention to establish first is a registered writer
  per artifact — not a grep.
- The `§` vocabulary has its own sweeps now, because `File.php:NN` checks say nothing about it.
  `storage/app/scratch/lane37-section-reference-sweep.php` judges every reference it resolves against
  `lane37-docx-inventory.php` (196 numbered headings, 135 finding rows with the section each physically sits
  under); `lane37b-repo-citation-sweep.php` judges the references PHP comments and the markdown files
  point into the document, the acceptance script and the audit file — three separate numbering systems. The reference
  figure was re-taken on 2026-10-30 after docx pass 321b added a §1.1 row of its own, because the row written to
  record a pass is itself one of the references the sweep counts — the reading this file carried had been taken before
  that row existed, and re-taking it moved again while this bullet was being edited. Earlier still, three docx passes
  rewrote §28.3's G-40 row and the day's §1.1 row and four pointers sat inside the sentences that were replaced. A
  census this file states has to be generated from the tool that prints it, and lane 59 refuses the run when it is not
  — which is why the honest form for a figure this file can itself move is no figure at all. **This file
  states no count for it, on purpose:** `AGENTS.md` is one of the markdown files it scans, so a sentence written
  about the sweep adds a reference for the sweep to judge and invalidates the figure in the same edit (measured
  twice on 2026-10-25 while recording pass 226: 403 → 405 → 406). Lane 58's rule for a revision row applies to a
  tool's own census — name the surface or state no total, and the reading lives in `lane59-readings.json` instead (that
  file is deliberately **untracked**: `storage/app/.gitignore` keeps `scratch/` to code by extension, because a
  measurement committed is a measurement frozen, and the collector regenerates it on any clone; the reads record
  beside it is tracked for the opposite reason — see "A record that is not tracked is not a record" above); and
  `repo-file-line-citations.php` reads the `File.php:NN` citations in this file, DEPLOYMENT.md, the
  walkthrough, both demo guides, the two third-party assessments **and the three sheets an office actually
  holds** (the school decision sheet, the Registrar print sheet, the print-fidelity comparison), which is how
  a false pointer *inside this very warning* was found (and how the placeholder
  `…:945-993` written while describing it was flagged a minute later — a citation-shaped token is a
  citation, even in prose about citations). Three citation forms mean
  three different things and the rules were written from the forms measured in the text: "§28 G-9" claims
  where the row lives, "(§12.5, G-3)" is the behaviour-then-finding convention and may cross chapters, and
  §19.3 is titled after its own finding. All three were proved by planted mutants — `mutate-lane37-sweep.php`
  on a scratch copy, `mutate-lane37b-sweep.php` which plants defects in the real tree and restores it
  byte-for-byte — before their silence was believed. **The sweep's other-document cue list gained `prompt` on
  2026-10-27**, because the gap register cites the development prompt's §6.4 and §7.9 and this document has its
  own §6.4: a collision that resolves as a *match* for any checker that only asks whether a §-number exists.
  The harness proves that qualification is honoured rather than tolerated with two legs on one paragraph —
  a section neither book holds stays silent while the word `prompt` sits in the block, and the same bytes with
  the word removed must charge it. The harness reads the cue list out of the sweep's own source, because a
  harness carrying its own copy of a checker's vocabulary drifts from it and then judges a rule that no longer
  exists; and it separates `BAD` from `SURVIVED`, since a needle that cannot land is the harness's age, not a
  rule that does not fire. Its titled-escape leg went unbuilt-able for a batch for the known reason: the §19.3
  heading's whole sentence occurs 0 times in `document.xml` while the flat dump reads it continuously, so the
  needle is a run tail. What none of them can check is whether a citation is
  about the right *subject*: §28 G-9 and §25 P-14 both exist and both resolve, and only reading caught the
  mix-up. So: a pointer into another document names that document ("§14R.2 of the acceptance script"), and a
  citation whose subject is a finding you have not opened in the register is worth reading once, not trusting.
- A `§NN <letter>-<number>` reference is a pointer into one of the document's registers, and the ids repeat
  across chapters, so **reading the row before citing it is not optional**. §28 G-9 is "the demo term offered no
  curriculum subject at the levels the students sat"; the print-log NULL limit that the record-state audit
  reports is **§25 P-14**. Three files — `AuditRecordStates.php`'s own `why`, DEPLOYMENT.md §8 step 7, and the
  UAT walkthrough — cited G-9 for the print hazard, and I added a fourth myself while writing a test comment in
  the same batch, because the phrase "§28 G-9" had been copied around as if it were the name of the condition.
  When prose moves, re-ask *which* register the sentence belongs to. Same batch, second lesson:
  `ems:audit:record-states` must count what a desk can still act on. Two of its ruled-to-keep notes could never
  reach 0 — they counted past-term demo rows that had been retired — so both predicates now exclude a dropped
  enrollment, and the retirement and the predicate ship in one batch (`AuditRecordStatesTest` pins the
  exclusion; without it the seven drops left the note at 7 and the number stopped meaning anything).
- A staff account's Spatie roles come from its `role` enum through `RbacSeeder::assignDeskRolesFromEnums()`.
  Any seeder that creates accounts must call it afterwards: §3 and CI seed `db:seed` *before*
  `DevReferenceDataSeeder`, and measured on a fresh database that order left **10 of 17 accounts with no
  roles at all** (every `officeN_head` plus `staff8`), unable to open their own desks. Live `ems` hides it
  because its pivots predate the order.
- In `tools/seed_full_demo_pipeline.php` a shared helper must be a plain function, not a closure: most call
  sites sit inside closures that capture only the variables they list, so a closure-valued helper throws
  `Undefined variable` the moment the pipeline reaches it. Rehearsing the whole chain on a copy caught this;
  no test executes `tools/`. The same trap has a silent face: a top-level block written **above** the line
  where a closure is assigned cannot reach it either, and nothing errors — the block just writes its own rows.
  That is how one demo fee sheet got a `studentassessments` total and no `charges` (lane 53): the pricing
  closure was assigned some 250 lines below the section that needed it, so three neighbouring sections each
  hand-wrote their own insert — and the third forgot its four `charges` rows entirely, which no screen,
  test or gate noticed until the accounts were weighed line by line. Hoist the helper
  (`charge_demo_fees()`, `demo_fee_lines()`, `desk_signer_for()`) and call it from every site.
- **A receipt is evidence of a state transition, not just of money** (lane 53). `AccountingController::record()`
  takes a payment only on an `assessed` enrollment and marks it `paid` when the balance clears, and
  `EnrollmentStateMachine::transition()` is the *only* writer of `enrollmentstatushistory`. So a settled
  account with receipts and no `-> paid` step is money that arrived with no desk action behind it, and the
  status-history panel the Registrar reads says so — `ems:audit:record-states` counts it. A seeder that mints
  a row already past Accounting therefore owes that row its trail: walk the states (`enrolled → assessed →
  paid → enrolled`), and say in the remark that the tool wrote it, so a reconstruction is never mistaken for a
  desk act. Two shapes that look like this defect and are not: a **part** payment on an `assessed` account (the
  desk marks nothing until the last peso) and a zero balance with **no** receipt (a grant covered it, §17). Ask
  for `Payments::held()` receipts, not for a zero balance.
- **A data decision that a seed tool regenerates is not a decision — go find the generator.** Seven
  `enrollments` whose Clinic and ID boxes were signed with no assessment and no validated request behind them
  were retired on live `ems` on 2026-10-15; `ems:audit:record-states` fell to 0, and the next
  `php tools/seed_full_demo_pipeline.php` — which is a standing gate, so it *will* run — re-created five of
  them within the hour, same students, same term. They are that tool's own year-level evidence (its G-2 step,
  `tools/seed_full_demo_pipeline.php:1686-1693`): the guard asks only whether the student still holds a record on that
  term, and a dropped one holds nothing, so the retirement looked like a missing year to be refilled, and
  `$completeWorkflow()` signed every box on the way. (Re-measured 2026-10-20, by reading rather than arithmetic: the
  seat guard is `tools/seed_full_demo_pipeline.php:1692-1694` and the record that guard seats is created at
  `tools/seed_full_demo_pipeline.php:1717-1730`. The pair the 2026-10-16 pass wrote — `:1765` and `:1771` — had
  drifted onto two comment lines beside that block, and the pair before it (`:1730`, `:1736`) landed on a blank line
  and on a `->first();` belonging to the term lookup two steps earlier; the older `:1607` sits in the
  clearance-slip seating block, which is
  not this step at all.) The fix was in the generator —
  `attest_completed_desks()` now writes the act behind each
  box the tool signs, dated into the record's own term and skipped where a desk already wrote one — not in
  dropping rows twice. When a count you closed comes back, name the code that writes those rows before you touch
  the data again.
- Two routes answer with **no login at all**: `GET|HEAD /storage/{path}` and `PUT /storage/{path}`, registered
  by Laravel because `config/filesystems.php` marks the `local` disk `serve => true`. Their only gate is an
  HMAC of the path and query keyed by `APP_KEY` — nothing in `app/` or `resources/` ever mints one, so no desk
  uses them. Never add `'visibility' => 'public'` to a served disk (that switches the check off, measured) and
  never deploy with `APP_KEY=` blank (the empty secret signs a write that `storage/app/private` accepts — and
  `local` and `documents` share that root). `ems:preflight` fails both, `ServedUploadPathTest` pins the gate.
- **HSTS is emitted only when this app sees HTTPS itself, and the address it will believe is a setting.**
  `SecurityHeaders` gates the header on `$request->isSecure()`; `bootstrap/app.php` never calls `trustProxies()`, so
  `Illuminate\Http\Middleware\TrustProxies` — which is already in the global stack — falls back to
  `config('trustedproxy.proxies')`, and since 2026-10-26 that key is answered by `config/trustedproxy.php` reading
  `TRUSTED_PROXIES`. With nothing named it is null, no address is trusted and `X-Forwarded-Proto` is discarded —
  measured 2026-10-17, pinned both ways since 2026-10-26 by `tests/Feature/SecurityHeadersTest.php`, where naming the
  peer address makes the header appear and naming another does not. Behind a proxy that terminates TLS, the operator
  sets one line in `.env` (and re-runs `php artisan config:cache` if the config is cached, because a cached install
  does not read `.env` at all). Do not "fix" this by trusting `*`: that lets any client forge its own scheme and
  address, and `LoginRequest::throttleKey()` and `auditlogs.ipAddress` both read `$request->ip()`. A `*` *inside* a
  list, or a hostname, is the quieter failure — Symfony validates nothing and matches nothing, so the setting looks
  filled in and the header still never appears; `ems:preflight`'s Forwarded scheme row refuses both.
  Ruled 2026-10-17 (the wildcard refused) and 2026-10-26 (the answer becomes `TRUSTED_PROXIES`), `DEPLOYMENT.md` §2
  carries the instruction and `php storage/app/scratch/lane73b-probe-proxy-shapes.php` is the measurement behind it.
- **Probe through the HTTP stack, not around it, and beware state that sticks.** `Request::create()` + a
  manual middleware call is not the kernel; and inside one test, an https request leaves `$_SERVER['HTTPS']='on'`
  for every later request in that same test — so three HSTS cases written in one method pass the third on the
  second case's leftover state and prove nothing. One method per case.
- **Probe through the HTTP stack, not around it, and beware state that sticks.** `Request::create()` + a
  manual middleware call is not the kernel; and inside one test, an https request leaves `$_SERVER['HTTPS']='on'`
  for every later request in that same test — so three HSTS cases written in one method pass the third on the
  second case's leftover state and prove nothing. One method per case.

## Editing the authority docx

`word/document.xml` is ~5.7M characters and its sentences are split across `<w:t>` runs, so:
edit through `storage/app/scratch/docx-edit-engine.php` with absolute offsets verified before each
write; never reflow existing tables from memory; **one offset cannot carry two edits** (an insert
and a rewrite at the same byte corrupt the document); **a `text` edit through `docx-edit-engine.php` takes plain characters, never entities** — the engine escapes `&` on the way in and refuses a replacement carrying a raw `<` or `>`, so `&amp;` in a replacement becomes `&amp;amp;` and Word prints the literal `&amp;` (measured against the pre-edit backup: pass 153 did exactly this to two runs and pass 154 undid it), and a code span containing an arrow cannot travel through a text edit at all — write those with their own guards, as `refresh-docx-pass154.php` does; read the result back through `html_entity_decode()`, not through the dump, which shows raw entities; re-measure every `File.php:NN` citation an
edit invalidates and verify it by *meaning* (which method the line sits in), not arithmetic;
before believing a clean verdict from any checker, feed it a mutant and watch it fail; re-run
`dump-docx-flat.php` before trusting `probe-docx-cite-method.php`, which reads that generated dump;
then prove the file still opens — `docx-ooxml-check.php`, `verify-docx.php`, and
`word-open-copy-test.ps1`, which opens a hash-identical copy in real Word and quits only the
Word instance it started.

**`docxApply()` opens the archive itself, so a pass that rewrites the document before calling it has written
nothing.** Pass 252 read `word/document.xml` into `$xml`, moved one §24 heading in memory, then handed the file to
the engine — which re-read the bytes from disk and applied its own edits over the *unmoved* heading. The pass
reported 18 targets resolved uniquely and its heading change was discarded silently, because the engine's guards all
balance over the document it actually wrote. Take every change through the engine's own edit arrays, or through a
second pass; never through a local `$xml` the engine will overwrite.

Run **Pint before writing the citations**, not after. On 2026-10-17 a pass re-pointed five
`IDController.php` ranges onto the line numbers read off the edited file; Pint then added one
`use` line to that same controller and moved every one of them by a row — a checker's clean verdict
hid it, because the sweep only refuses a line past the end of the file. And a sentence this document
needs to add cannot always ride on a text needle: §18.3's column list ends with the last name in a
Consolas run and its full stop in a plain one, so no string spans the end of that paragraph — such an
addition goes in as an inserted `<w:p>` (the engine's `inserts` argument), which the container guard
counts as exactly the paragraph pair it contributes.

**A cloned register row inherits every `<w:t>` run of the template cell.** A helper that writes a cell by replacing
its first run leaves the later runs in place, so cloning a multi-run cell copies the template's tail into the new
row: pass 173's §1.1 clone gave the 2026-10-21 row the previous row's closing paragraph verbatim, and it sat there
unnoticed for one batch because every container guard still balanced (the row has one more run than it should, and
one row's worth is exactly what the guard checks). Write a cloned cell by emptying **all** its runs and then require
the flattened cell to equal the supplied text — containment is not enough. `lane62-probe-p7-targets.php` prints each
target cell's run count for this reason, and pass 176's stage 1 refuses when the register's ids are not
`P-1 … P-16` in order, once each.

- **A comment that cites a register row the register does not hold is a finding, and only the sweep over repo
  comments sees it** (lane 96, same batch; §28.3 gained the seven rows on 2026-10-30 by docx pass 308). Three of my own
  new files said the blocking card's question was "registered in §28.5, sheet item 9" and one said the dashboard gap is
  "(§28.3 G-46)". Both were false as written: the sheet
  carries item 9 and the docx then did not, and §28.3's G family stopped at G-43 while G-44 … G-50 lived only in the
  matrix, which pass 308 has now written. `lane37b-repo-citation-sweep.php` printed the refusal as **NO-ID** on the test file, and the
  same run went from 1 problem to 0 once the comments named the sheet and the matrix instead of a docx row. Two
  rules follow. A `§NN <letter>-<number>` token is a promise that the row is *in that section of that document*, so
  write the row before citing it, and until then name the register that really holds it ("matrix gap G-46"). And
  when a comment claims a school question is registered somewhere, the claim is about content, not a section number:
  §28.5 exists, §28.5 resolves, and §28.5 did not contain the question — which is the wrong-target shape this file
  already documents for line numbers, arriving in a prose pointer.

- **A mutant leg must plant the defect, not the absence of the fix** (same batch). `mutate-g48-g49-privacy-tests.php`
  first removed the whole `clinic.view` gate around the clinical load. The structural case stayed green, and the leg
  was wrong rather than the guard: deleting the load is exactly what the fix achieves, so the payload had nothing for
  the case to refuse. The defect was the **unconditional** load, so the leg now plants
  `$student->load('enrollments.clinicrecords')` outside any test, which is the pre-fix bytes. Five legs, all killed,
  control green before and after, tree restored by hash — and the harness's own first run refused loudly for the
  other reason: `escapeshellarg()` wraps in single quotes and `cmd.exe` does not strip them, so `--filter` selected
  no test at all. A leg that cannot fail is decoration, and a leg that selects nothing is a green over nothing.

- **A refusal that answers success is the same lie as a zero the server never sent** (lane 97, prompt §8, G-47's
  ownership half). `NotificationController::markRead()` already declined to write another desk's notice — inside an
  `if` — and then returned `{"ok": true}` regardless. Nothing in the front end calls that route today (the bell
  fetches only `notifications.index` and `notifications.read-all`, measured by grep over `resources/js`), so the
  false success was reachable only by a direct POST; it is refused now, with the reason in `abort_unless`, and
  `tests/Feature/Notification/NotificationMarkReadOwnershipTest.php` asserts the row's `read_at` stays null rather
  than trusting the body. Prove it with `storage/app/scratch/mutate-g47-mark-read-ownership.php` (three legs: the
  silent `if` restored, the addressee test deleted, the refusal's sentence dropped). Two lessons worth keeping.
  **A line-neutral edit keeps the authority document honest**: replacing those three lines with three lines left
  `NotificationController.php:51` on the same construct the sentence about it names, so no citation aged and no
  shift run was needed — the sweep answered 0 line problems on that run, and this file states no citation total for
  it, because that figure moves with every pointer any later batch writes. And **run Pint before claiming
  neutrality**: Pint's fix pass then touched only the new test file (`no_unused_imports` on an `Enrollments` import I
  never used), which is why the check after formatting, not before it, is what makes the claim true.
- **A quoted false figure is still a figure the gate judges, and a comma list is two citations** (lane 98, CI run
  40861302826 — the batch's own SQLite job went red on both). The day's §1.1 row carried the clause
  `the report of the batch said "pinned by four cases"` to record that the count had been written from intent, and
  `lane58-suite-claims.php --ci` bound the quoted number as a present-tense class claim: the classifier cannot tell a
  disclosure from an assertion, so a retired figure is spelled in words or not written at all. Docx pass 307 re-worded
  the clause instead of re-typing the digit — the digit was the thing that was never measured. The same run staled two
  class totals in this file that I had typed from intent rather than read off the run: the sort case's assertion count
  and the dashboard case's, and the dashboard figure is repeated in the matrix, so one wrong reading sat in three
  places. **Read a class total out of the junit of the run that produced it before writing it beside a class name**,
  and grep every document for the figure, because a batch that adds assertions moves all of them at once. The matrix's
  case count beside the mark-read test was re-read from the same junit and is true; the completion report's worded
  `four cases` carries no assertion pair, so lane 58 leaves it — which is a reading, not a pass.
  Second shape, found the same day by a sweep already in the tree: the citation gate takes only the FIRST number of a
  comma list that shares one colon — the same limitation the docx sweep prints for bare continuations — so the matrix
  pointer whose second number had become a blank line passed every gate.
  `storage/app/scratch/lane97-probe-comma-cites.php` is the reader the gate cannot be: it opens both numbers of every
  comma-list citation in the three dated documents, resolves a basename the way the gate does (a HEAD export under
  `storage/app/scratch` is not a second target, or the citation is accused of an ambiguity belonging to an artefact),
  prints the whole sentence beside each pair so the reader judges which construct the line carries and not merely
  whether it is live, and refuses its own run when the population is empty or the file index read nothing. It found
  that one blank pointer and no other defect; the row now names the two cases that really carry its claim. **A gate's
  blind list is the queue its parser cannot read, and this one sat inside a document the gate opens every run** — so
  when a checker prints "0 problems", ask what it never looked at.
  Third shape, from writing the register rows themselves: a clone plan that appends after an anchor **and advances
  that anchor** must run in ASCENDING order. The first cut reversed the plan for the other recipe — the one that
  re-locates the anchor per row — and the register came out `G-43, G-50, G-49 … G-44, D-1`. Every bounds check and
  every id lookup agreed with that document, because a row's identifier lives in its own first cell and nothing
  asks the sequence: order is a claim, so it is now asserted on read-back, and a failed post-guard restores the
  pass's own backup rather than printing advice — a guard that only names the fix leaves the file broken and the
  run looking finished. The falsification is the reversed plan run against the live document with the restore
  proved twice: the pass exits 1 and `git status` reports the docx unmodified afterwards. Fourth, and the same class
  one line up: the sentence written into §1.1 to record this batch carries three of the document's own section
  pointers, so the reference census moved twice while this file was being edited to state it — 1,020 to 1,028 when
  the register grew and again to 1,031 when the revision row was written. A figure about an instrument is read
  LAST, from the instrument, after every prose edit that could move it.

## Agent dispatch

Scope agents to non-overlapping paths, run independent lanes in background, reconcile before
advancing dependent work, and never poll a background task — wait for its notification.
