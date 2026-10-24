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
  `php storage/app/scratch/mutate-lane43-delete-guards.php`. Live `ems` carries 100 foreign keys: only four
  cascade (the RBAC pivots `RbacSeeder`'s prune relies on), one is RESTRICT (`shiftingrequests.requestedBy`),
  and 70 refuse a delete outright. Every desk delete answers those with a **hand-written list** of child tables
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
  own filters actually live, so every filter submit in the repository was invisible; the corrected run judges 55.
  A checker pointed at the wrong construct is worse than one that reads nothing, because it prints a pass. Its
  scope is a flag (`EMS_LANE46_PAGES`), so the harness proves the flag changes what is scanned and that an empty
  scope *refuses* rather than reporting clean.
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
  schema holds 129 indexes. Live `ems` today answers 63 tables (55 domain + 8 framework from the bundled `0001_` migrations),
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
  record. First run: 12 contradictions over 11 ids; the harness has 11 legs and four of them require silence.
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
  clause that put `Payments::held()` immediately before `( AssessmentController.php:123-127 )`, a
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
  count**: lane 61's one remaining blind spot is pre-existing and now explained — §6.1's `R-1 … R-3`
  rows hold their verdict but their id cell reads `R-2 Admission dependency`, so `>R-2<` is never a
  run and the sweep cannot index it. The sentence that tripped it describes candidate-list logic, not
  an open question. **A closed register row can still carry an open branch.** P-17 came from reading a
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
- **After any batch that changes a guard around a money or percent figure**, run
  `php storage/app/scratch/lane49-zero-shown-as-absence.php` and prove it with
  `php storage/app/scratch/mutate-lane49-checker.php`. Lane 48's mirror: `totalPaid > 0 ? ₱… : '—'` and
  `coveragePercent ? `${n}%` : '—'` made a **real 0** print the same dash as a missing figure, on a box where
  `min(studentassessments.remainingBalance)` is 0.00 today. The rule is that a guard tests the record or list
  the figure came from (`totalAssessment == null`, `assessed.length > 0`), never the amount. The checker judges
  only what text alone settles — the operand rendered by the very branch its guard selects, signed by `₱` or
  `%` — and **prints its own blind spots** (19 guards whose dash sits on a line it cannot join, 340 lines where
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
- **Split lines on `\n`, never `preg_split('/\R/')`, when a script reports line numbers.** `\R` also matches
  the byte 0x85, which occurs as a UTF-8 continuation byte inside emoji: it read a 55-line report as 71
  lines and printed line numbers no editor would show. Every `File.php:NN` citation this repository writes
  comes from a line counter, so the counter has to agree with the reader's screen.
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
- `php artisan db:seed --class=RbacSeeder` prunes permissions the seeder no longer declares, so
  retiring a right is a seeder edit plus a reseed, not a migration.
- RBAC pivots are renamed: `staff_roles`, `role_permissions` with columns `roleId` / `permissionId`;
  there is **no** `model_has_roles`. `staff_roles.model_type` is stored double-escaped
  (`App\\Models\\Staffusers`), so a single-backslash SQL literal matches nothing and every account
  looks role-less.
- Never delete rows found during probing. Deleting probe residue is its own decision for the owner.

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
- Nothing *analyses* `tools/`: `phpstan.neon` covers `app` only and `pint.json` excludes `tools`, so a defect
  there is invisible to the static gates — those files are proven by running them and reading their output.
  Since 2026-10-16 `tools/seed_full_demo_pipeline.php` is also *executed* by CI (the MySQL job's step between
  the seeders and the walkthrough legs) and it exits 1 when its own desk report lists a finding, so a fatal or
  a broken record there is red there — but the other files in that directory still answer only to a hand run.
- Re-measuring only the files a batch touched is how the document accumulated wrong line numbers: 14
  citations had drifted in files no recent batch edited. Run the whole-document sweep after any batch that
  moves lines — `php storage/app/scratch/dump-docx-flat.php` then
  `php storage/app/scratch/sweep-docx-citations.php` (631 citations over 150 files; it exits non-zero on a
  citation past the end of a file or landing on a blank line, and it was proved by planting one). It checks
  lines, not meaning: a citation can land on a live line that belongs to something else, and only reading
  the sentence catches that.
- `storage/app/scratch/lane32-cite-meaning-sweep.php` automates the first pass of that reading (~2 s over the
  122 symbol/range pairs it checks, 9 pairs flagged for reading). It takes the construct the prose *names* and asks whether
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
- The `§` vocabulary has its own sweeps now, because `File.php:NN` checks say nothing about it.
  `storage/app/scratch/lane37-section-reference-sweep.php` judges the document's own 898 references against
  `lane37-docx-inventory.php` (196 numbered headings, 114 finding rows with the section each physically sits
  under); `lane37b-repo-citation-sweep.php` judges the 346 references PHP comments and the markdown files
  point into the document, the acceptance script and the audit file — three separate numbering systems; and
  `repo-file-line-citations.php` reads the `File.php:NN` citations in this file, DEPLOYMENT.md and the
  walkthrough, which is how a false pointer *inside this very warning* was found (and how the placeholder
  `…:945-993` written while describing it was flagged a minute later — a citation-shaped token is a
  citation, even in prose about citations). Three citation forms mean
  three different things and the rules were written from the forms measured in the text: "§28 G-9" claims
  where the row lives, "(§12.5, G-3)" is the behaviour-then-finding convention and may cross chapters, and
  §19.3 is titled after its own finding. All three were proved by planted mutants — `mutate-lane37-sweep.php`
  on a scratch copy, `mutate-lane37b-sweep.php` which plants defects in the real tree and restores it
  byte-for-byte — before their silence was believed. What none of them can check is whether a citation is
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
  seat guard is `tools/seed_full_demo_pipeline.php:1691-1693` and the record that guard seats is created at
  `tools/seed_full_demo_pipeline.php:1716-1729`. The pair the 2026-10-16 pass wrote — `:1765` and `:1771` — had
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
- **HSTS is emitted only when this app sees HTTPS itself.** `SecurityHeaders` gates the header on
  `$request->isSecure()`, and because `bootstrap/app.php` never calls `trustProxies()` and
  `config('trustedproxy.proxies')` is unset, `Illuminate\Http\Middleware\TrustProxies` keeps `$proxies` null,
  trusts no address, and discards `X-Forwarded-Proto` — measured 2026-10-17, pinned by
  `tests/Feature/SecurityHeadersTest.php`. Behind a proxy that terminates TLS, an operator must trust it
  explicitly or the header never appears. Do not "fix" this by trusting `*`: that lets any client forge its
  own scheme and address, and `LoginRequest::throttleKey()` and `auditlogs.ipAddress` both read `$request->ip()`.
  Ruled 2026-10-17 — code unchanged, `DEPLOYMENT.md` §2 carries the instruction.
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

## Agent dispatch

Scope agents to non-overlapping paths, run independent lanes in background, reconcile before
advancing dependent work, and never poll a background task — wait for its notification.
