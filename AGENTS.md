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
  `DB_DATABASE=ems_l54_a php storage/app/scratch/mutate-lane54-report.php` and read every case. The report's four
  `Ledger —` lines (a fee sheet with a total and no lines, a paid step this run had to reconstruct, a returning
  claim with no year behind it, receipts exceeding the assessment) are the only view the tool has of its own
  output, and before lane 54 it repaired lane 53's two impossible states in silence. Each line is handed exactly
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
- **After any batch that changes a guard around a money or percent figure**, run
  `php storage/app/scratch/lane49-zero-shown-as-absence.php` and prove it with
  `php storage/app/scratch/mutate-lane49-checker.php`. Lane 48's mirror: `totalPaid > 0 ? ₱… : '—'` and
  `coveragePercent ? `${n}%` : '—'` made a **real 0** print the same dash as a missing figure, on a box where
  `min(studentassessments.remainingBalance)` is 0.00 today. The rule is that a guard tests the record or list
  the figure came from (`totalAssessment == null`, `assessed.length > 0`), never the amount. The checker judges
  only what text alone settles — the operand rendered by the very branch its guard selects, signed by `₱` or
  `%` — and **prints its own blind spots** (18 guards whose dash sits on a line it cannot join, 355 lines where
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
  two of its sixteen rules (`collidingSchedules('block')` and `('resource')`), self-joined through both blocks so
  the two sides are term-equal, and half-open so **back-to-back is not a collision**. There is deliberately no
  `meetingId` exclusion on that path: Laravel rewrites `where('meetingId', '!=', null)` into `IS NOT NULL`, so the
  create-form guard that used to depend on it passed by an accident of the query builder, not by intent.
- Money in the drawer is `Payments::held()` (`paid` or `partial`) — **and that includes the demo generator**. Five
  sites in `tools/seed_full_demo_pipeline.php` summed `paymentStatus = 'paid'` by hand to decide what an account
  had been given, while `Studentassessments::outstandingBalance()` sums `held()`; on any account holding an
  installment the generator's ledger pass would therefore rewrite `remainingBalance` back over what the cashier
  already collected, and the shortfall loop beside it would take a second receipt for money nobody handed over.
  All five now call `demo_held()` (`tools/seed_full_demo_pipeline.php:1396-1399`), which asks the model's own
  scope rather than restating the status list. Measured: an ₱2,000 installment leaves ₱15,500 stored, and the
  paid-only version of the helper put it back to ₱17,500 — proved both ways by
  `storage/app/scratch/lane54e-installment-survives.php`, which plants the defect in a copy of the tool and
  requires the erasure to reappear. Neither side was visible to any test, because the generator never writes a
  `partial` row itself (§28.3 G-24) and live `ems` held none: a rehearsal that only *reads* the dataset the tool
  builds cannot find a state the tool refuses to create.
  "Is clearance season?" is answered only by `Clearanceperiods::accepting()` (open **or** extended). Year level is
  derived from the student's own record, never asserted as a constant.
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
  `php storage/app/scratch/sweep-docx-citations.php` (559 citations over 115 files; it exits non-zero on a
  citation past the end of a file or landing on a blank line, and it was proved by planting one). It checks
  lines, not meaning: a citation can land on a live line that belongs to something else, and only reading
  the sentence catches that.
- `storage/app/scratch/lane32-cite-meaning-sweep.php` automates the first pass of that reading (~2 s over the
  107 symbol/range pairs inside 665 cited ranges). It takes the construct the prose *names* and asks whether
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
  `storage/app/scratch/lane37-section-reference-sweep.php` judges the document's own 851 references against
  `lane37-docx-inventory.php` (196 numbered headings, 96 finding rows with the section each physically sits
  under); `lane37b-repo-citation-sweep.php` judges the 243 references PHP comments and the markdown files
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
  `tools/seed_full_demo_pipeline.php:1706`): the guard asks only whether the student still holds a record on that
  term, and a dropped one holds nothing, so the retirement looked like a missing year to be refilled, and
  `$completeWorkflow()` signed every box on the way. (Measured again 2026-10-16, after a batch that moved the first
  140 lines of this file: the seat guard is `tools/seed_full_demo_pipeline.php:1765` and the record that guard
  seats is created at `tools/seed_full_demo_pipeline.php:1771` — re-measure before repeating those numbers, and
  measure by *meaning*, which is the only reason both carry the file name: the checker reads a bare `:NN` as prose
  and passes it. The pair they replaced, `:1730` and `:1736`, land on a blank line and on a `->first();` belonging
  to the term lookup two steps earlier, and the older `:1607` sits in the clearance-slip seating block, which is
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

## Agent dispatch

Scope agents to non-overlapping paths, run independent lanes in background, reconcile before
advancing dependent work, and never poll a background task — wait for its notification.
