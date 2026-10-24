# School decisions still outstanding — sheet for one meeting

**Issued 2026-10-23 · fifteen items · four came back the day it was issued**

Every item here is a question the build cannot answer for itself. They are gathered from the
authority document's own registers — §28.2/§28.3 (the rows whose "Who must act" column still names
an office), §28.5 (the decisions that section says it cannot make), and §25.4 (the two printed-paper
items that are not code decisions at all) — and each line says what the system does today, so an
answer can be given without opening a file. The **id** in brackets is the register row; the packet
does not restate a ruling, it asks the questions the rulings left standing.

Two of these were settled internally on 2026-10-23 and are *not* on this sheet: which BR numbering
is authoritative (the code's, §26.2), and whether a returned enrollment keeps its payment (it does).
The `extendedDeadline` column that looked like a per-student clearance deadline was dropped the same
day, because an extension is the window's state.

Four rows came back on 2026-10-23, answered by the project owner in conversation rather than on
paper: items **12**, **13** and **14** — the three printed-paper questions — and the first half of
item **15**. Each is marked **ANSWERED** where it was asked, with what the build now does, so the
sheet reads as the packet was answered rather than as a list still outstanding.

**To take a decision on each row:** tick, and initial. Where the answer is "as it stands", say so —
that is a decision and it closes the row.

---

## A. Registrar, with the departments

**1. Which offices must sign a clearance slip, and what does each one clear?** `[§28.5]`
Nine offices carry a requirement line today, and each line states its own obligation
(`clearancerequirements.requirementName`, §12.1). The wording in the seeder is the developer's
placeholder; the list of offices, their order, and the sentence each line prints are the school's.
- Answer: the nine lines are correct ☐ / amended on the attached page ☐ / other: .............
  Initial: ..........

**2. What must a printed slip contain to be accepted at the Registrar's window?** `[§28.5, P-8, P-10]`
The field list in §15 of the revision brief is the project's requirement, not a documented school
rule. Two gaps that list assumed are already closed — each requirement line carries its own text, and
the slip reads the enrollment of its own term. What is owed is the **wording of the lines and the
order of the signatures**, not a new column.
- Answer: as printed ☐ / wording amended ☐ / order amended ☐ / other: ............. Initial: ..........

**3. In what words does the enrollment certificate state student type and enrollment type?** `[§28.5, P-13]`
The certificate prints both, the student type in its own words and the enrollment kind separately, so
the two are no longer conflated on paper. The sentences now printed are the ones the developer chose;
§25 records them as the code's wording rather than SEAIT's.
- Answer: as printed ☐ / replacement wording attached ☐ Initial: ..........

**4. Is the generated PDF, or the desk screen, the paper a window accepts?** `[§28.5]`
Both paths are reachable: the enrollment form is issued only as a PDF download (`print.enrollmentForm`),
every other paper can reach either the desk page or a generated PDF, and `php artisan ems:print-fidelity`
renders either for inspection. Which one is the file of record is institutional.
- Answer: desk print ☐ / generated PDF ☐ / either, PDF is the record ☐ Initial: ..........

**5. Must every lost clearance slip be paid for before it is replaced?** `[§28.5, P-12]`
Two acts are now two rules: a second **free** slip is allowed only while the row is `incomplete`;
a **lost** slip goes through the paid route, reads its amount from the Reference Data fee type
*Clearance Slip Replacement*, and refuses outright when no such fee is on file
(`ClearancePolicy.php:44-62`, `ClearanceController.php:328-376`). The school still owns whether every
replacement must be paid for, and whether a `pending` slip may ever be reissued free.
- Answer: as built ☐ / free reissue allowed when: ............. Initial: ..........

**6. Which programs waive the General Entrance Examination for a transferee who arrives with credit?** `[C-4]`
Built per-program, default off: `courses.entranceExamExemptsTransferee` waives the General
examination for transferees and for no other type, never the departmental examination, and **no
program ships with it set** — so today the answer is "nobody". Name the programs, and the waiver is
data, not a code change.
- Answer: none ☐ / these programs: ............................................. Initial: ..........

**7. How long must enrollment evidence be kept?** `[C-8]`
Answered as *indefinite* and measured: the system purges nothing, no retention period is configured
anywhere in `config/`, and every audit row on the box is still reachable. That is a measurement, not a
promise. If the school names a period, a purge command has to be built to honour it — it does not
exist yet, and retention should not be guessed by code.
- Answer: indefinite ☐ / period: .......... years, to be built ☐ Initial: ..........

**8. Does a program change always make a student Irregular?** `[C-2]`
Built as a rule: a program change — same department or another — makes the student Irregular, and so
does arriving from another school as a transferee (`StudentType::arrivesIrregular()`). Everything
else stays as item 16 has it: for a first-year or returning student the standing is what the grades
support, proposed by the department and stated by the Registrar at approval. Confirm the exception,
or name any population it should not cover.
- Answer: confirmed ☐ / exception should also not cover: ............. Initial: ..........

## B. Departments

**9. Is the list of board programs that require the Course-Specific Examination correct?** `[C-5]`
Five carry it today — BSCrim, BSCE, BSEE, BSA, BSSW — named by the project owner on the instruction
to prioritise the courses with board examinations. The set is **provisional and meant to be replaced
by the owning departments** from Reference Data → Courses, which is why the flag is desk-side data.
A failed departmental result blocks approval; an absent one blocks only where the program asks.
- Answer: correct ☐ / add: ............. remove: ............. Initial: ..........

**10. Does a shifter sit a departmental examination on entry to the new program?** `[C-6]`
Built as *no*: a shifter is assessed through credit evaluation at stage 6, and an enrollment granted
by a shift request is exempted from the retention examination. Confirm, because a department that
wants an entrance paper for shifters would need it as a rule and not as a workaround.
- Answer: confirmed, no examination ☐ / the department requires one: ............. Initial: ..........

## C. Scholarship and Accounting, with the Registrar

**11. Must a returning or shifting student receive a fresh scholarship determination every term?** `[C-7]`
Their form carries no Assessment box by design (`WorkflowService::stepsFor` omits it for continuing
and shifter enrollments), a grant is written against the assessment of the enrollment it covers, and
**nothing copies last term's grant forward** — so such an enrollment is uncovered until the
Scholarship desk acts. Whether every term *must* be re-determined is policy, not code.
- Answer: every term ☐ / until expiry of the award ☐ / other: ............. Initial: ..........

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

**15. Two items the build cannot reach from this machine.** `[G-13, C-10]`
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
  Answer: sync ☐ / a worker runs: ............. ☐ Initial: ..........

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
