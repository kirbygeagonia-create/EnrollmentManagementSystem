# School decisions still outstanding — sheet for one meeting

**Issued 2026-10-23 · sixteen items · twelve answered, four left for the Registrar's window**

Every item here is a question the build cannot answer for itself. They are gathered from the
authority document's own registers — §28.2/§28.3 (the rows whose "Who must act" column still names
an office), §28.5 (the decisions that section says it cannot make), and §25.4 (the printed-paper
items that are not code decisions at all — all four that came onto this sheet were answered on
2026-10-23, the last of them the same day its own row was opened, and §25.4 now holds no open
finding) — and each line says what the system does today, so an
answer can be given without opening a file. The **id** in brackets is the register row; the packet
does not restate a ruling, it asks the questions the rulings left standing.

Two of these were settled internally on 2026-10-23 and are *not* on this sheet: which BR numbering
is authoritative (the code's, §26.2), and whether a returned enrollment keeps its payment (it does).
The `extendedDeadline` column that looked like a per-student clearance deadline was dropped the same
day, because an extension is the window's state.

Five rows came back on 2026-10-23, answered by the project owner in conversation rather than on
paper: items **12**, **13** and **14** — the three printed-paper questions — item **16**, which arrived
after the sheet was issued, and the first half of item **15**. Each is marked **ANSWERED** where it was
asked, with what the build now does, so the sheet reads as the packet was answered rather than as a list
still outstanding. **Item 16 was then answered a second time, on 2026-10-24:** the build had kept its own
spelling of the year level — "Year 2" where the school's slip writes "2nd Year" — and the owner ruled that
"the proper format is 2nd year", so that one deferred wording is now the school's on every surface too.

**Six of the eleven rows still open that day were then read against the school's own records**, and
each now carries what those records answer, so a tick can be given against evidence rather than memory.
Items **1, 2, 4 and 5** were read off a real clearance slip (the student's copy: four fields, one
signatory, no requirements table, no fee line, and a "(Student's Copy)" designation the system never
printed); items **6 and 9** were read off `SEAIT_Official_Information.md` §2, which marks exactly two
programs — BSCrim as a board course requiring the SCAT entrance and retention examinations, and BSSW as
a board program — while the database carries the departmental flag on five. **The gap between the
school's slip and the system's became §25.4 P-19 and item 16, and was ruled and built the same day.**
Items **3, 7, 8, 10 and 11** have nothing in the repository that speaks to them.

**On 2026-10-24 the owner answered seven of the eleven, and four remain.** Items **6, 7, 8 and 10**
were ticked *as they stand* — the state the build already holds is the decision, which is what the
sheet says closes a row — and items **3** and **11** were ruled: the enrollment certificate keeps the
sentences it prints today, and a returning or shifting student **is** re-determined every term, which
is the strictest reading and also the one the system already forces, since their form carries no
Assessment box and nothing copies a grant forward. Item **15**'s second half closed too, and reading
the code for it narrowed the question considerably: the only two listeners that ever queue are
`SendEnrollmentNotification` and `SendWorkflowNotification`, and `SendWorkflowNotification::notify()`
does nothing but `Notifications::create(...)` — plain inserts of the in-app bell rows for staff
accounts, with no `app/Notifications/` directory, no mail channel, and no queued PDF rendering. A
worker therefore exists in this system for one purpose: delivering the desk's bell. `QUEUE_CONNECTION=sync`
stays, and the `database` default in `.env.example` keeps `DEPLOYMENT.md` §5's worker as the instruction
for whoever provisions a real server.

**Four rows are left, and all four ask the same thing of the Registrar's window rather than of the
software:** items **1** (where each of the nine offices actually signs, since the student's copy names
one signatory and no requirements table at all), **2** (what a printed slip must contain to be accepted
at the window), **4** (whether the generated PDF or the desk screen is that paper), and **5** (whether
every lost slip is paid for before replacement, when the school's copy carries no fee line). Each is
already read against the real slip, so an answer can be given at the meeting without opening a file.
One row is owed somewhere else: item **9** was ruled on 2026-10-23 to leave all five board-program flags
standing, but the owning departments have not yet confirmed or cleared them, so §28.8 still charges that
one to a department, and its "add / remove" line is open. Together those two — C-5 and the install-time
G-13 under item **15** — are the only items the registers still owe anyone.

**To take a decision on each row:** tick, and initial. Where the answer is "as it stands", say so —
that is a decision and it closes the row.

---

## A. Registrar, with the departments

**1. Which offices must sign a clearance slip, and what does each one clear?** `[§28.5]`
Nine offices carry a requirement line today, and each line states its own obligation
(`clearancerequirements.requirementName`, §12.1). The wording in the seeder is the developer's
placeholder; the list of offices, their order, and the sentence each line prints are the school's.
**Read from the school's paper 2026-10-23:** the student's copy of a real clearance slip names **one**
signatory — "Received by: **Registrar In-charge (Signature over Printed Name) & Date**", stamped over a
printed name — and carries **no requirements table at all**. So the eight other desks either sign a
different sheet or not at all, and the question this row asks is now sharper than "is the wording
right": it is *where does each office actually sign*.
- Answer: the nine lines are correct ☐ / amended on the attached page ☐ / **the table belongs on an internal copy, not the student's (name it) ☐** / other: .............
  Initial: ..........

**2. What must a printed slip contain to be accepted at the Registrar's window?** `[§28.5, P-8, P-10, P-19]`
The field list in §15 of the revision brief is the project's requirement, not a documented school
rule. Two gaps that list assumed are already closed — each requirement line carries its own text, and
the slip reads the enrollment of its own term. **The school's own student copy now supplies the list:**
title `CLEARANCE <semester>, <school year> (Student's Copy)`, then **Course** — "(BSIT) Bachelor of
Science in Information Technology - 2nd Year", code in parentheses and the level written "2nd Year" —
**Full Name** in "SURNAME, FIRST MIDDLE" capitals, **Date**, and **Received by** with the Registrar
In-charge's signature over the printed name and its own date. Nothing else is on it: no student number,
no overall status word, no "date to be signed", no document number, no fee line. The system prints all of
those, and since the ruling recorded under item **16** its heading names the copy too — the shape of the
name is recorded here rather than the name itself, because this sheet is committed and the reference
scans, which carry it, are deliberately not.
- Answer: as printed ☐ / **match the student copy: take the number, the status word, the "date to be signed" and the document number off the student's page ☐** (the copy designation and the course code are already built, ruled 2026-10-23) / wording amended ☐ / order amended ☐ Initial: ..........

**3. In what words does the enrollment certificate state student type and enrollment type?** `[§28.5, P-13 — ANSWERED 2026-10-24]`
The certificate prints both, the student type in its own words and the enrollment kind separately, so
the two are no longer conflated on paper. The sentences now printed are the ones the developer chose;
§25 records them as the code's wording rather than SEAIT's. No certificate was among the school papers
supplied, so nothing in the repository can settle the words.
- ~~Answer: as printed ☐ / replacement wording attached ☐~~ — answered 2026-10-24:
  **as printed.** The certificate states student type in its own words and enrollment type separately, and those sentences\n  stand as the school's. §25 keeps them recorded as the build's wording now ruled in, and P-13 closes on that. Initial: ..........

**4. Is the generated PDF, or the desk screen, the paper a window accepts?** `[§28.5, P-19]`
Both paths are reachable: the enrollment form is issued only as a PDF download (`print.enrollmentForm`),
every other paper can reach either the desk page or a generated PDF, and `php artisan ems:print-fidelity`
renders either for inspection. **The school's paper answers one half of this, and the build now agrees:**
the real slip is marked "(Student's Copy)", so the school does distinguish copies of the same document,
and since the ruling under item **16** both surfaces say so on the face — `clearance-slip.blade.php:36`
titles the paper *Clearance Slip (Student's Copy)* and `PrintSlip.jsx:27` gives the desk screen the same
title. Which copy the window keeps, which copy the file holds, and whether the nine-office table belongs
on the student's page at all is still the Registrar's.
- Answer: desk print ☐ / generated PDF ☐ / either, PDF is the record ☐ / **a second copy exists; name it: ................................ ☐** Initial: ..........

**5. Must every lost clearance slip be paid for before it is replaced?** `[§28.5, P-12]`
Two acts are now two rules: a second **free** slip is allowed only while the row is `incomplete`;
a **lost** slip goes through the paid route, reads its amount from the Reference Data fee type
*Clearance Slip Replacement*, and refuses outright when no such fee is on file
(`ClearancePolicy.php:44-62`, `ClearanceController.php:328-376`). **The student's copy carries no fee
line and no amount**, so the charge — if the school charges one — is made at the Accounting window and
not stated on the slip. Whether every replacement is paid for, and whether a `pending` slip may be
reissued free, is still the school's.
- Answer: as built ☐ / free reissue allowed when: ............. Initial: ..........

**6. Which programs waive the General Entrance Examination for a transferee who arrives with credit?** `[C-4 — ANSWERED 2026-10-24]`
Built per-program, default off: `courses.entranceExamExemptsTransferee` waives the General
examination for transferees and for no other type, never the departmental examination, and **no
program ships with it set** — so today the answer is "nobody". The school's own record speaks about the
entrance examination for exactly one program — BSCrim is marked "Board Course — Requires **SCAT
Entrance** & Retention Exams" — and says nothing about waiving it for a transferee, so the default off
is the reading the record supports. Name the programs and it becomes data, not a code change.
- ~~Answer: none ☐ / these programs: .............................................~~ — answered
  2026-10-24: **none.** The default stays off; a program is exempted only when the Registrar sets the
  flag on Reference Data → Courses, which is desk-side data and needs no release. Initial: ..........

**7. How long must enrollment evidence be kept?** `[C-8 — ANSWERED 2026-10-24]`
Answered as *indefinite* and measured: the system purges nothing, no retention period is configured
anywhere in `config/`, and every audit row on the box is still reachable. That is a measurement, not a
promise. If the school names a period, a purge command has to be built to honour it — it does not
exist yet, and retention should not be guessed by code.
- ~~Answer: indefinite ☐ / period: .......... years, to be built ☐~~ — answered 2026-10-24:
  **indefinite, as it stands.** Nothing is deleted and no purge command will be written until the school
  names a period; the measured state is the decision. Initial: ..........

**8. Does a program change always make a student Irregular?** `[C-2 — ANSWERED 2026-10-24]`
Built as a rule: a program change — same department or another — makes the student Irregular, and so
does arriving from another school as a transferee (`StudentType::arrivesIrregular()`). Everything
else is left to the rule the register row calls "Item 16", which cites §7.4 (the student type × stage
matrix) and §13.4 beside it — no numbered list in this repository is headed "Item 16", so the pointer
is recorded here rather than guessed at. The rule it points at: for a first-year or returning student
the standing is what the grades
support, proposed by the department and stated by the Registrar at approval. Confirm the exception,
or name any population it should not cover.
- ~~Answer: confirmed ☐ / exception should also not cover: .............~~ — answered 2026-10-24:
  **confirmed, as built.** A program change makes the student Irregular, arriving as a transferee does the same, and no population is carved out of it. `StudentType::arrivesIrregular()` remains the single definition. Initial: ..........

**16. Which of the two clearance slips is the paper the school recognises?** `[§28.5, P-19 — ANSWERED 2026-10-23]`
Numbered 16 because it was opened after the sheet was issued; the four items it governs keep their
numbers, because the authority document now cites them by number. The system prints **one** document:
a "Clearance Slip" title, then eight identity rows on the desk paper — Document No., Semester,
Academic Year, Student Name, Student ID, Overall Status, Course & Year, Date to be Signed — over a
nine-line office table (Office, Requirement, Status, Approved By, Date per office) and a signature
block. The inspection PDF rendered by `php artisan ems:print-fidelity` shows **seven**, because that
command hands the view only a clearance and passes no document number
(`PrintFidelitySamples.php:115`, against `PrintService.php:85` for the desk download). The school's
student copy is **a different paper**: `CLEARANCE 2nd Semester, 2025-2026 (Student's Copy)`, then
Course, Full Name, Date, and Received by over "Registrar In-charge (Signature over Printed Name) &
Date". It carries no office table, no student number, no status word, no fee line and no document
number, and it says whose copy it is. Both readings are inside the build's reach, so this is a
decision and not a defect — and it is the parent of items **1, 2, 4 and 5**, because the answer decides
where each office signs, what the window accepts, whether a copy is stated on the face, and whether the
replacement fee is printed at all.
- ~~Answer: match the student copy; the office table moves to an internal sheet / keep the table and add the
  copy designation and the school's Course wording to the face~~ — answered: **keep the table and mark the
  copy.** Built the same day, on both print surfaces together: the slip titles itself
  `Clearance Slip (Student's Copy)` and its program line leads with the code, from
  `clearance-slip.blade.php:38-82` and `PrintSlip.jsx:37-44`, and the nine-office block is untouched.
  Re-read off the rendered PDF rather than off the template. **The level wording the build first kept was
  ruled against the next day:** the build printed "Year 2", the owner ruled 2026-10-24 that "the proper
  format is 2nd year", and the ordinal is now what every surface prints — `CLEARANCE SLIP (STUDENT'S COPY)`
  over `(BSIT) Bachelor of Science in Information Technology - 2nd Year`, read off the re-rendered PDF.
  The vocabulary lives in one place per side (`App\Support\YearLevel` for the papers, `formatYearLevel` and
  `yearLevelChoices` in `resources/js/Components/ui/statusLabel.js`) and
  `tests/Feature/YearLevelVocabularyTest.php` pins the two against each other and refuses any surface that
  spells a level by hand. Items **1, 2, 4 and 5 stay open on their own questions**: the ruling settled which
  copy the paper is, not where each office signs, what the window accepts, or whether the student's copy
  should carry the number, the status word and the fee.
  Initial: ..........

## B. Departments

**9. Is the list of board programs that require the Course-Specific Examination correct?** `[C-5]`
Five carry it today — BSCrim, BSCE, BSEE, BSA, BSSW — named by the project owner on the instruction
to prioritise the courses with board examinations. The set is **provisional and meant to be replaced
by the owning departments** from Reference Data → Courses, which is why the flag is desk-side data.
A failed departmental result blocks approval; an absent one blocks only where the program asks.
**Measured against the school's own record 2026-10-23:** `SEAIT_Official_Information.md` §2 marks two
programs and only two — **BSCrim** ("Board Course — Requires SCAT Entrance & Retention Exams") and
**BSSW** ("Board Program"). Those are also the only two with `requiresEntranceExam` set. BSCE, BSEE and
BSA carry `requiresCourseSpecificExam` with no basis in the repository's records, so the three are the
owner's instruction alone and the departments should confirm or clear them.
- Answer: **ruled 2026-10-23: leave all five flags exactly as they stand** until the owning departments
  confirm or clear them from Reference Data → Courses — the flag is desk-side data on purpose, and clearing
  three of them would drop a gate a department may in fact want. add: ............. remove: BSCE, BSEE, BSA ☐
  Initial: ..........

**10. Does a shifter sit a departmental examination on entry to the new program?** `[C-6 — ANSWERED 2026-10-24]`
Built as *no*: a shifter is assessed through credit evaluation at stage 6, and an enrollment granted
by a shift request is exempted from the retention examination. Confirm, because a department that
wants an entrance paper for shifters would need it as a rule and not as a workaround.
- ~~Answer: confirmed, no examination ☐ / the department requires one: .............~~ — answered 2026-10-24:
  **confirmed, no examination.** A shifter is assessed through credit evaluation at stage 6 and the shift grant exempts the\n  retention examination; `ShiftRequestFlowTest` pins both halves. A department that later wants an entrance paper asks for a\n  rule, not a workaround. Initial: ..........

## C. Scholarship and Accounting, with the Registrar

**11. Must a returning or shifting student receive a fresh scholarship determination every term?** `[C-7 — ANSWERED 2026-10-24]`
Their form carries no Assessment box by design (`WorkflowService::stepsFor` omits it for continuing
and shifter enrollments), a grant is written against the assessment of the enrollment it covers, and
**nothing copies last term's grant forward** — so such an enrollment is uncovered until the
Scholarship desk acts. Whether every term *must* be re-determined is policy, not code.
- ~~Answer: every term ☐ / until expiry of the award ☐ / other: .............~~ — answered 2026-10-24:
  **every term.** The strictest reading, which is also the state the build forces: a continuing or shifter form carries no\n  Assessment box, a grant is written against the assessment of the enrollment it covers, and nothing copies last term's grant\n  forward — so such an enrollment is uncovered until the Scholarship desk acts, and that gap is the rule rather than a defect.\n  No code change: the system already refuses to decide this for the desk. Initial: ..........

## D. The three printed-paper items that are not code decisions — all three answered 2026-10-23

**12. Which spelling of the institution's name does the paper carry?** `[P-16 — ANSWERED 2026-10-23]`
The answer given was **South East Asian Institute of Technology, Inc.** — the two-word `South East`
and the corporate suffix, which is what `Documentation/SEAIT_Official_Information.md` §1 already
records as the Official Name. Before the ruling the printed letterhead said **Southeast Asian
Institute of Technology** while the login screen, four Documentation files and the authority
document's title line carried the sheet's spelling, so the screen and the paper named the school
differently. Built the same day: the settings row, the seeder and the fallback each of the six print
views passes into its own `config('settings.schoolName', …)` call now carry the ruled name, and
`tests/Feature/Print/PrintTemplateSelfContainmentTest.php` reads the Official Name out of the sheet
and refuses any surface that disagrees with it. Re-read off the paper: all eight documents
`php artisan ems:print-fidelity` renders print the ruled form, and the school's own stationery in the
scans the owner supplied carries it too.
- ~~Answer: one word ☐ / two words ☐ / with "Inc." ☐~~ — answered: **two words, with "Inc."** Initial: ..........

**13. A blank of the Enrollment Form, so the form can be laid out to match it.** `[P-2 — ANSWERED 2026-10-23]`
The owner's answer was to build it: no blank Registrar form will be supplied, and the designs in the
`Images` folder — a Subject Load, a Class Card, a Clearance Slip and a Class Block and Schedule — are
the pattern to follow. Built the same day: the enrollment form now prints an identification block
under its title carrying **Student ID, Course, Year Level, School Year and Semester**, in the words
the Registrar's own Subject Load uses and from the relations the desk already loads, so the block
costs no extra query. Read back off the rendered PDF rather than off the template:
`enrollment-form.pdf` now carries the student number, the course, the year level and the school year
where the 2026-10-21 reading found no identity token of any kind. What remains is a layout review —
only the Registrar can say whether the enrollment form's own paper puts those five fields elsewhere.
- ~~Answer: blank attached ☐ / the form has no school paper and the system's layout stands ☐~~ —
  answered: **the system's layout stands, built from the supplied designs** Initial: ..........

**14. Which enrollment should a clearance slip name when its own term holds none?** `[P-17 — ANSWERED 2026-10-23]`
The answer given was that a clearance is issued when a semester ends, as a requirement for the
clearance pass an enrollment brings, and that the enrollment system must keep every student who has
already completed a clearance. Built the same day: the slip names the enrollment of the clearance
period's own term, and when that term holds none it names **nothing** — the paper prints `N/A` for
course and year level rather than borrowing a later enrollment's, and the remedy for such a slip is
the right clearance period. The keeping half was measured before it was written: `studentclearances`
is keyed by the unique pair (studentId, clearancePeriodId), the four foreign keys around it refuse a
delete, no path in `app/` or `routes/` deletes a clearance row, and re-issuing a slip over one a desk
has already answered is refused. `tests/Feature/Print/ClearanceSlipTermEnrollmentTest.php` pins both
branches and the keeping, and `storage/app/scratch/mutate-p17-term-enrollment-tests.php` proves it.
- ~~Answer: the last enrollment attended ☐ / the enrollment being cleared, and N/A when there is none ☐ /
  a slip may not be raised after its term closed ☐~~ — answered: **the enrollment being cleared, and
  N/A when there is none** Initial: ..........

## E. For the owner, or whoever provisions the school's server — not a school question

**15. Two items the build cannot reach from this machine.** `[G-13 — ANSWERED 2026-10-23, C-10 — ANSWERED 2026-10-24]`
- **Name the proxy** (`G-13`) — **ANSWERED 2026-10-23: not yet deployed.** The owner has no decision
  to deploy this build and may never take one, so the row stays open as a fact about an install that
  does not exist rather than as work owed. `Strict-Transport-Security` is sent only when the app sees
  HTTPS itself, and this build trusts no proxy — `bootstrap/app.php` never calls `trustProxies()`, so
  behind a TLS-terminating proxy the header never appears. Whoever provisions a server must name that
  proxy's address in `trustProxies(at: …)`. Never `*`: that lets any client forge its own scheme and
  address, and login throttling and the audit log both read the client address. `DEPLOYMENT.md` §2
  carries the instruction, and `tests/Feature/SecurityHeadersTest.php` pins the behaviour.
  Answer: proxy named: .................... ☐ / **not yet deployed ☑** Initial: ..........
- **Is a queue worker part of the installed deployment?** (`C-10`) — owed for the same reason: there
  is no installed deployment to answer for. The demo and UAT install runs
  `QUEUE_CONNECTION=sync` and delivers inside the request, which is what closed the 56-job backlog;
  only two listeners are ever queued, and PDF rendering has never been queued work. A real install
  that runs a worker changes nothing in the software; one that does not must stay on `sync`.
  Answer: **sync ☑** / a worker runs: ............. ☐ — answered 2026-10-24, closing C-10 for this install.
  Reading the code narrowed what the question was ever about: the only two queued listeners are `SendEnrollmentNotification`\n  and `SendWorkflowNotification`, and `SendWorkflowNotification::notify()` does nothing but `Notifications::create(...)` —\n  plain inserts of the in-app bell rows for staff accounts. There is no `app/Notifications/` directory, no mail channel, and\n  PDF rendering has never been queued. So a worker exists here for one purpose: delivering the desk's bell. On `sync` it\n  happens inside the request, which is why the bell shows what a desk just did; on `database` with no worker the rows simply\n  never appear — the whole story of the 56 stale jobs. `.env` runs `sync`; `.env.example:70` still ships `database`, and\n  `DEPLOYMENT.md` §5 carries the worker for whoever provisions a real server. Initial: ..........

---

## What comes back, and where it goes

A ticked row needs no code when the answer is the state the build already holds; it becomes a
register row closing. A changed wording is a template edit; a changed office list, program set or
fee rule is Reference Data, edited on the desk that owns it — the system was built so that these
answers are data rather than releases. Each row above names the register id it closes, so this
packet and `Documentation/EMS Complete Documentation.docx` finish on the same page.

For the printed papers in detail, the earlier sheet still stands: **Registrar print decisions,
issued 2026-10-11** (`Documentation/Registrar-Print-Decision-Sheet-2026-10-11.md`), nine questions
against the physical forms.
