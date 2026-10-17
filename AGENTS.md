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
- Money in the drawer is `Payments::held()` (`paid` or `partial`). "Is clearance season?" is
  answered only by `Clearanceperiods::accepting()` (open **or** extended). Year level is derived
  from the student's own record, never asserted as a constant.
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
- In a feature test, an Inertia screen's props come from `->viewData('page')['props']`, not
  `->json('props.x')`: the route answers with HTML until `X-Inertia` is sent, so the second form reports
  "Invalid JSON was returned from the route". Endpoints that return real JSON (`students.quick-search`,
  `exam.students`) are read with `->json()` as normal.
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
  `php storage/app/scratch/sweep-docx-citations.php` (554 citations over 110 files; it exits non-zero on a
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
  `storage/app/scratch/lane37-section-reference-sweep.php` judges the document's own 838 references against
  `lane37-docx-inventory.php` (196 numbered headings, 90 finding rows with the section each physically sits
  under); `lane37b-repo-citation-sweep.php` judges the 214 references PHP comments and the markdown files
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
  no test executes `tools/`.
- **A data decision that a seed tool regenerates is not a decision — go find the generator.** Seven
  `enrollments` whose Clinic and ID boxes were signed with no assessment and no validated request behind them
  were retired on live `ems` on 2026-10-15; `ems:audit:record-states` fell to 0, and the next
  `php tools/seed_full_demo_pipeline.php` — which is a standing gate, so it *will* run — re-created five of
  them within the hour, same students, same term. They are that tool's own year-level evidence (its G-2 step,
  `tools/seed_full_demo_pipeline.php:1685`): the guard asks only whether the student still holds a record on that
  term, and a dropped one holds nothing, so the retirement looked like a missing year to be refilled, and
  `$completeWorkflow()` signed every box on the way. (Measured again 2026-10-16, after a batch that moved the first
  140 lines of this file: the seat guard is `tools/seed_full_demo_pipeline.php:1744` and the record that guard
  seats is created at `tools/seed_full_demo_pipeline.php:1750` — re-measure before repeating those numbers, and
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

## Editing the authority docx

`word/document.xml` is ~5.7M characters and its sentences are split across `<w:t>` runs, so:
edit through `storage/app/scratch/docx-edit-engine.php` with absolute offsets verified before each
write; never reflow existing tables from memory; **one offset cannot carry two edits** (an insert
and a rewrite at the same byte corrupt the document); re-measure every `File.php:NN` citation an
edit invalidates and verify it by *meaning* (which method the line sits in), not arithmetic;
before believing a clean verdict from any checker, feed it a mutant and watch it fail; re-run
`dump-docx-flat.php` before trusting `probe-docx-cite-method.php`, which reads that generated dump;
then prove the file still opens — `docx-ooxml-check.php`, `verify-docx.php`, and
`word-open-copy-test.ps1`, which opens a hash-identical copy in real Word and quits only the
Word instance it started.

## Agent dispatch

Scope agents to non-overlapping paths, run independent lanes in background, reconcile before
advancing dependent work, and never poll a background task — wait for its notification.
