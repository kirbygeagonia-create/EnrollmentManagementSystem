# Registrar window packet — the six items put in front of people, issued 2026-10-26

**Who signs what, what the window accepts, whose paper it is, what a lost slip costs, which programs
carry a board examination, and what an installer must name.**

This is not a new set of questions. It is the four rows the decision sheet of 2026-10-23 left open
(items 1, 2, 4, 5 — **all four ruled the day this packet was issued, in §A below**), the department half
of C-5, the install-time half of G-13, and the boxes the fidelity sheet of 2026-10-10 had never been
answered on — **three of those nine came back the same day (2.1–2.9, 1.6 and 1.1–1.5) and six remain
open (3.3, 3.4–3.5, 3.6, 3.7, 4.1 and §5); the sheet's own tally reads ☐6 / ☑4 because row 2.6, ruled on
2026-10-11, is ticked too** — gathered onto
pages a person can tick while
looking at today's build, with what each answer costs and how it comes back off again.

**Every figure on this page was read from a command, not from the previous sheet**, because a packet
that re-copies itself is how an answered question gets asked again:

```
php storage/app/scratch/lane73-packet-evidence.php     # offices, board flags, the fee, the proxy state
php storage/app/scratch/lane73-packet-claims.php       # weighs every figure on this page against live ems
php artisan ems:print-fidelity                          # renders the eight papers against live ems (8/8 today)
php storage/app/scratch/lane62-print-paper-probe.php    # reads the identity fields back off the paper
```

`lane73-packet-claims.php` is the guard for the days after this one: it binds the 23 claims on this page — the
course counts, each board row's two flags, every office line's exact wording, the fee's amount and its table's
column list, the proxy state — to the server, and **refuses the run rather than emitting a verdict when the page
can no longer be read**. It answered **23 claims, all matching** when this packet was issued; prove it can fail
with `php storage/app/scratch/mutate-lane73-packet-claims.php`. If it goes red, **re-issue the packet from the
evidence command — do not retype the figures by hand.**

Dataset it was read against: live `ems` on 2026-10-26 — 16 courses, 37 enrollments, 3,509 audit rows,
9 clearance requirement lines.

---

## How to answer, and how to undo

| The answer is… | It is carried by | It comes back off by |
|---|---|---|
| a wording on a printed paper | one Blade or JSX file, in its own commit | `git revert <sha>` — no data moves |
| which offices exist, what each clears, which programs are board courses, what a slip costs | **Reference Data on the desk screens** — `clearancerequirements`, `courses.requiresCourseSpecificExam`, `feetypes` | the same click reversed; no release, no migration |
| a new rule the build does not have yet | a register row, a test, and its own commit | `git revert` of that commit |
| anything that would change the schema | — nothing here does. If an answer ever does: dump live `ems` to `C:\Users\ADMIN\ems-backups\ems-pre-<what>-<date>.sql`, verify the dump landed and names the table, rehearse on a scratch schema, then land the migration alone | restore from that dump |

Nothing on this page needs a database write from the project. That is deliberate: an answer that is a
desk-side value can be tried, filed, and reversed by the office that owns it.

---

## A. The four rows decision-sheet items 1, 2, 4, 5 asked — all four ruled 2026-10-26

### 1. Where does each of the nine offices actually sign? `[§28.5 · sheet item 1 — ANSWERED 2026-10-26]`

Today's nine lines, read from `clearancerequirements` (columns `clearanceRequirementId, officeId,
requirementName`) — the wording is the developer's placeholder; the list, the order and the sentences
are the school's:

| office | the line the slip prints today |
|---|---|
| 1 Registrar | No unreturned Registrar documents, credentials, or ID |
| 2 Accounting | No unsettled fees, assessments, or accounting obligations |
| 3 Scholarship | Scholarship records returned and grant conditions met |
| 4 Guidance | Guidance records complete, no pending counseling clearance |
| 5 Blocking | No unresolved section or subject blocking |
| 6 Admission | Admission requirements on file and verified |
| 7 Academic | No incomplete grades or make-up requirements with the department |
| 11 Clinic | Medical and dental examination on file |
| 22 ID Office | No unclaimed, damaged, or lost school ID |

The school's own student copy carries **no table at all** and names **one** signatory ("Registrar
In-charge (Signature over Printed Name) & Date"), so the question is not whether the wording is right —
it is where the other eight desks sign.

- ~~☐ These nine lines and this order are the school's — the table stays on the student's page.~~ **ANSWERED: this one.** The nine lines stay, in this order, on the copy the student holds, so the office that signs each line is visible on the paper.
- ~~☐ The table belongs on an **internal** copy; the student's page keeps one signature line.~~ Not chosen.
- ~~☐ Amended on the attached page ☐ Other~~ None. Initial: the owner, in conversation, 2026-10-26

*Cost:* the office list and the sentences are rows in `clearancerequirements`, edited on Reference Data →
Clearance Requirements. Adding or removing an office is that screen plus `OfficeId`; no schema change.
*Undo:* the same screen.

### 2. What must the printed slip contain to be accepted at the window? `[§28.5 · P-8 · P-10 · sheet item 2 — ANSWERED 2026-10-26]`

The desk paper prints eight identity rows over the nine-line table and a signature block: Document No.,
Semester, Academic Year, Student Name, Student ID, Overall Status, Course & Year, Date to be Signed —
and since 2026-10-23 its title marks the copy (`Clearance Slip (Student's Copy)`), with the level
spelled `2nd Year` per the ruling of 2026-10-24. The school's paper carries four fields and nothing else:
Course, Full Name, Date, Received by. No number, no status word, no "date to be signed", no document
number, no fee line.

- ~~☐ As printed — the window accepts all eight rows.~~ **ANSWERED: this one.** Eight identity rows, the nine-line table and the signature block, as the desk prints them.
- ☐ Match the student copy: take the Student ID, the Overall Status word, the "Date to be Signed" and the Document No. off the **student's** page (they stay on the internal/file copy).
- ☐ Wording amended ☐ Order amended — on the attached page.  Initial: ..........

*Cost:* `resources/views/print/clearance-slip.blade.php` and `resources/js/Components/Print/PrintSlip.jsx`
in one commit; the cases that pin today's face are `ClearanceSlipCopyDesignationTest` and
`PrintTemplateSelfContainmentTest`, so the change is deliberate and the red is informative.
*Undo:* `git revert`. The document number stays in `documentprintlog` either way — it is minted before
the render, so removing it from the paper does not remove the record.

### 4. Is the generated PDF or the desk screen the paper a window accepts? `[§28.5 · P-19 · sheet item 4 — ANSWERED 2026-10-26]`

Both are reachable. The enrollment form is PDF-only (`print.enrollmentForm`); every other paper can be
printed from the desk page or generated. The two paths are **not** the same render: the desk mints and
passes a document number, `ems:print-fidelity` passes none, which is why the inspection slip shows seven
identity rows where the desk's shows eight (`PrintFidelitySamples.php:115` against `PrintService.php:85`).
**That was re-read off the paper rendered today rather than inherited:** `clearance-slip.pdf` carries exactly
seven — Semester, Academic Year, Student Name, Student ID, Overall Status, Course & Year, Date to be Signed —
under `CLEARANCE SLIP (STUDENT'S COPY)` and `(BSIT) Bachelor of Science in Information Technology - 2nd Year`,
and no `Document No.` row appears at all, which is the visible half of the difference.

- ~~☐ The desk print is the paper.~~ **ANSWERED: the desk print** — it mints its Document No. before the render and writes its own `documentprintlog` row, so paper and log match. ☐ The generated PDF is the paper. Not chosen; it stays the inspection render.
- ☐ Either; the PDF is the record kept.
- ☐ A second copy exists and is the one filed — name it and say what marks it: .............  Initial: ..........

*Cost:* none for "as it stands". Naming a second copy is one template parameter and one label, in its own
commit. *Undo:* `git revert`.

### 5. Must every lost slip be paid for before it is replaced? `[§28.5 · P-12 · sheet item 5 — ANSWERED 2026-10-26]`

Two acts are already two rules: a second copy while the row is `incomplete` is free; a **lost** slip goes
through the paid route and reads its amount from the fee type the catalogue holds today —
**Clearance Slip Replacement, ₱100.00, flat** (`feetypes` feeTypeId 5, columns
`feeTypeId, feeName, defaultAmount, unitBasis`). If no such fee is on file the paid route refuses outright
rather than inventing an amount. The student's copy carries no fee line, so the charge is made at
Accounting and never stated on the slip.

- ~~☐ As built — a lost slip is paid, at the amount Reference Data carries.~~ **ANSWERED: this one.** A lost slip is paid before it is replaced; the free copy stays the not-yet-complete case, and no free-reissue condition was added.
- ☐ A free reissue is allowed when: ............. (e.g. same day, or once per period)
- ☐ The amount changes: .............  Initial: ..........

*Cost:* the amount is a `feetypes` row on Reference Data → Fee Types. A new free-reissue condition is a
rule in `ClearancePolicy.php:44-62` plus a case, one commit. *Undo:* the same screen / `git revert`.

---

## B. The departments' half of C-5 — five flags, two of them documented

Measured on live `ems` today: **16 courses; 5 carry `requiresCourseSpecificExam`; 2 carry
`requiresEntranceExam`; 0 exempt a transferee.**

| course | program | `requiresCourseSpecificExam` | `requiresEntranceExam` | basis in the school's own record |
|---|---|---|---|---|
| BSCrim | BS Criminology | yes | **yes** | `SEAIT_Official_Information.md` §2 — "Board Course — Requires SCAT Entrance & Retention Exams" |
| BSSW | BS Social Work | yes | **yes** | §2 — "Board Program" |
| BSCE | BS Civil Engineering | yes | no | the owner's instruction alone |
| BSEE | BS Electrical Engineering | yes | no | the owner's instruction alone |
| BSA | BS Agriculture | yes | no | the owner's instruction alone |

Ruled 2026-10-23: leave all five standing until each owning department confirms or clears its own — the
flag is desk-side data precisely so that this is a tick and not a release. A failed departmental result
blocks approval; an absent one blocks only where the program asks.

- ☐ Confirmed as flagged (all five) ☐ Cleared, and the set is only: .............
- ☐ Added by the department: .............  Initial (per department): ..........

*Cost:* Reference Data → Courses, the `requiresCourseSpecificExam` box. *Undo:* the same box. No code.

---

## C. The nine boxes the fidelity sheet has never been answered on

These are the same physical papers `Documentation/Registrar-Print-Decision-Sheet-2026-10-11.md` asks about, so a
tick here answers that sheet's matching question too — one answer, both records. That older sheet's dated readings
are **not** re-aged: they say what the build printed on the day it was read, and its own supersession note stands.

`Documentation/Print-Fidelity-Comparison-2026-10-10.md` §6 carries ten rows: the nine questions it put to the office
on 2026-10-11, plus 2.6, ruled that day. **Two of the ten are now answered outright — 2.6 (the doubled weekday,
2026-10-11) and 2.1–2.9 (the card's whole shape, ruled as the system prints on 2026-10-26) — and three more are
partly answered by later rulings (1.1–1.5, 3.7 and §5)**, so the Registrar's eye is spent only on what is genuinely
owed. Measured rather than kept track of: `lane73-packet-evidence.php` counts that sheet at **6 unticked boxes and 4\nchecked ones**. The dated cells in the older sheet are **not** re-aged — they record the day they were read; where
the build has since moved (2.3, 3.2), this page says so beside the row rather than rewriting that sheet.

| box | what it asks | state before this packet | what is still owed |
|---|---|---|---|
| 1.6 | Is the per-office checklist on the same sheet as the student's copy, or separate? | **ANSWERED 2026-10-26** | nothing owed — the table stays on the student's page, ruled as item 1 below |
| 1.1–1.5 | Do the slip's labels follow the paper, or the paper the system? | partly ruled: the copy designation and `(BSIT) … - 2nd Year` are built (P-19, 2026-10-23; level spelling 2026-10-24) | **ANSWERED 2026-10-26** — the rest is accepted as printed, ruled as item 2 below; the labels remain editable without a release |
| 2.1–2.5 | The card's shape: portrait vs A4 landscape, the three boxed name cells, the subject block as a row, `Units` holding one number | open | **ANSWERED 2026-10-26 — as the system prints.** The card stays A4 landscape with labelled name rows, `Description:` and the `(Lec: …, Lab: …)` units split; re-read off `resources/views/prints/class-card.blade.php` the same day rather than copied from the 2026-10-10 column |
| 2.6 | The weekday printed in both the `Time` and `Day` boxes | **CLOSED 2026-10-11** — ruled that the `Time` box prints ranges only and `Day` keeps the weekdays | nothing owed; pinned by `PrintControllerTest::the_class_card_prints_the_meeting_day_once_not_in_both_boxes()` |
| 2.7–2.9 | The instructor signature line, the two `Date:` fields, the semester line's punctuation | open | **ANSWERED 2026-10-26 — as the system prints.** The instructor box stays pre-filled from the schedule, the one `Date` box plus the footer line stays, and the semester line keeps its comma |
| 2.3 / 3.2 | The year level as the paper spells it | **overtaken on the system side** — both views now print `App\Support\YearLevel::format()`, so the card reads `- 1st Year` and the load `- 3rd Year`, not the `- Year 1` / `- Year 3` the 2026-10-10 sheet recorded | **answered with 2.1–2.5**: the level stays inside the course line rather than in a field of its own; the older sheet's reading is left as it was taken |
| 3.3 | Is the student-type word on the paper ("Old") the one to print, or the system's ("Continuing")? | open | **open** |
| 3.4–3.5 | May the Subject Load print the three extra columns and the totals row the paper has none of? | open | **open** |
| 3.6 | Signed by Student / Class Adviser / Registrar, or by Evaluated by / Processed by as the paper shows? | open | **open** |
| 3.7 | Should the Load carry a `Student Copy` marker and the red `SEAIT INC. ENROLLED` stamp? | principle ruled for the **slip** (its title now marks the copy); nothing marks the Load | **open** for the Load and the stamp |
| 4.1 | Does the block schedule need a weekly grid, or is the meeting list the document the office files? | open | **open** |
| §5 | Provide a blank photograph of the enrollment certificate and the enrollment form | the form half is answered (2026-10-23: none will be supplied; the layout was built from the four supplied designs) | **open** for the enrollment certificate only |

- For each row still open: ☐ as the system prints ☐ as the paper does ☐ amended on the attached page — Initial: ..........

---

## D. G-13 — closed as a fact, and now a line in `.env` rather than a paragraph

`Strict-Transport-Security` is emitted only when the application itself sees HTTPS, and this build
trusts no proxy. Measured today by `lane73-packet-evidence.php`: `config('trustedproxy.proxies')` is
**null** and `bootstrap/app.php` does **not** call `trustProxies()`. The answer recorded on 2026-10-23
stands — *not yet deployed* — and the owner ruled on 2026-10-26 that the remaining half is a **setting,
not a code edit**: `config/trustedproxy.php` reads `TRUSTED_PROXIES`, so whoever provisions the server
types one value in `.env` and touches no PHP file. The row now has a check instead of a paragraph:

```
php artisan ems:preflight        # prints "Forwarded scheme" as its own row
```

- Nothing trusted and `APP_URL` is http → **OK**, and the row names `DEPLOYMENT.md §2` so the installer is pointed at the instruction.
- `APP_URL` says https while no proxy is trusted → **WARN**: behind a TLS terminator the header never appears. The row adds the second half of the act: set `TRUSTED_PROXIES`, then re-run `php artisan config:cache` if the config is cached, because a cached install does not read `.env` at all.
- A proxy address named → **OK**, count printed. Several addresses separated by a comma are the same one line.
- A blank `TRUSTED_PROXIES=` → the same as leaving it out, measured: it arrives as `''` and the config file answers null rather than handing Symfony an empty pattern.
- `*` → **WARN**, because the wildcard is the one-line "fix" that lets any client forge its own scheme and address — and login throttling and `auditlogs.ipAddress` both read `$request->ip()`.
- A `*` inside a list, or a hostname, or a CIDR mask past its family → **WARN**, and these are the quiet ones: `Request::setTrustedProxies` validates nothing, so the value is accepted, matches no client address, and the header stays discarded while the setting looks filled in. `storage/app/scratch/lane73b-probe-proxy-shapes.php` prints that effect next to the row's own promise, for twelve spellings, and refuses its run if a named address does not actually reach the request.
- `PRIVATE_SUBNETS` or `REMOTE_ADDR` → **WARN**, because a range is not one machine; every address that fits it may forge.

It is WARN, never FAIL: nothing stored moves, and a preflight cannot see a proxy that has not been built.
Pinned by `tests/Feature/Console/PreflightTest.php` and by the two directions now in
`tests/Feature/SecurityHeadersTest.php` (naming the peer address makes the header appear; naming a
different address does not), and proved by
`storage/app/scratch/mutate-lane73-preflight-proxy.php`, which plants a defect in every branch of the
row and in both lines of the config file and requires the case that owns each one to go red, restoring
both files byte-for-byte in a `finally`.

**Rollback for this whole section:** the preflight row was one commit; the setting is another, touching
one new config file, `.env.example`, and the row. `git revert <sha>` on the setting's commit leaves the
report printing the same fourteen rows it prints today — this batch adds no check — and only takes the
env var away, so the instruction returns to being a paragraph in `DEPLOYMENT.md §2`.

---

## E. What is *not* owed here, so the meeting does not re-open settled paper

Items 3, 6, 7, 8, 10, 11, 12, 13, 14, 15 and 16 of the 2026-10-23 sheet are answered and built; §25.4
holds no open print finding; §28.8 charges only C-5 and G-13, and this packet is the instrument for both.
If an answer here changes a printed wording, the authority document is edited in the same batch, never
left describing an older build.
