# Registrar print decisions — sheet for signature

**Issued 2026-10-11 · nine questions · nothing in the software changes until this sheet comes back**

This asks for nine decisions about printed papers, each one a difference between a form the office
already uses and the paper the system produces. Every line was read out of the templates on
2026-10-11, so the wording quoted under "the system prints" is what a PDF says today, not what it
is remembered to say. The line references are given so a decision can be carried out without
guessing which file to open.

**To take a decision on each row:** tick which side wins, and initial. Where the paper wins, the
change is to the printed layout — the data behind it does not move. Where the system wins, nothing
changes and the physical form is amended instead, which is also a valid answer.

Bring to the desk: the physical **Clearance Slip**, **Class Card**, **Student Subject Load** and
**Class Block and Schedule**, plus a blank of the **Enrollment Certificate** and the **Enrollment
Form** if one exists (row 10).

---

## The clearance slip

**1. Is the per-office requirement checklist on the same sheet as the student's copy, or a separate document?**

- The paper: the stub carries no per-office table at all.
- The system: one sheet, with the office table printed above the signature block
  (`clearance-slip.blade.php:78-100`), and the heading never naming whose copy it is (`:36`).
- If the office wants the copy split: the slip gains a `Student's Copy` / `Office Copy` designation
  in the title, and the checklist may move to its own page.
- Decision: paper ☐ / system ☐ / other: ..................................... Initial: ..........

**2. Do the slip's field labels follow the paper, or does the paper follow the system?**

| Field | The paper says | The system prints | Where |
| --- | --- | --- | --- |
| Name | `Full Name:` | `Student Name:` | `clearance-slip.blade.php:53` |
| Programme | `Course:` with `(code) full name - 2nd Year` | `Course & Year:` with `full name - Year 2`, no code | `:68` |
| Date | a `Date` field, written by hand at issue | `Date to be Signed:` filled from the period end | `:72` |
| Signature | `Received by:` over `Registrar In-charge (Signature over Printed Name) & Date` | `Received by Registrar`, then the name, then the date and time of receipt — and blank rules for both lines until someone is recorded | `:103-105` |
| Term | semester and year inside the title | two separate rows under the title | `:36`, `:45-51` |

- Decision: paper ☐ / system ☐ / mixed (write which rows): ..................................... Initial: ..........

## The class card

**3. Is the card laid out as the portrait form, or as the landscape card the system draws?**

This is the largest single choice on the sheet. Everything below sits behind it, so answer it first.

- The paper: portrait card, name split into three **boxed cells** with the label printed *underneath*
  the value, subject block as a table row, `Set | Time | Day | Grade` as four cells.
- The system: A4 landscape, `Label: value` rows with an underline
  (`class-card.blade.php:47-59`), subject fields as three labelled rows (`:74-75`), and the four
  boxes present (`:81-106`).
- Decision: portrait paper ☐ / landscape as printed ☐ Initial: ..........

**4. Wordings on the card, if the landscape form stays.**

| Item | The paper says | The system prints | Where |
| --- | --- | --- | --- |
| Programme | `BSIT - First Year` (level as a word) | `… - Year 1`, plus the major | `class-card.blade.php:59` |
| Subject title label | `Descriptive Title` | `Description:` | `:70-75` |
| Units | one number (`3`) | `3 (Lec: 2, Lab: 1)` | `:75` |
| Instructor | `Name and Signature of Instructor:`, name written by hand | `Name & Signature of Instructor`, pre-filled, no space of its own | `:110` |
| Dates | two `Date:` fields, both written | one `Date` box printed **empty** for the hand (`:114-115`) and one footer `Date:` filled with the day the PDF was made, not the day the record was signed (`:125`) | `:114`, `:121-125` |
| Term line | `2nd Semester \| 2024-2025` (pipe) | `1st Semester, 2026-2027` (comma) | `:42` |

- Decision: paper ☐ / system ☐ / mixed (list rows): ..................................... Initial: ..........

## The student subject load

**5. Who signs the load?**

- The paper: `Date Enrolled`, `Evaluated by`, `Processed by` — each a name over a line.
- The system: `Student`, `Class Adviser`, `Registrar` (`subject-load.blade.php:130`, `:133`, `:136`).
- These are different actors, not different wording, so this row decides who has to be present to
  sign. The system's version is the one the workflow actually routes: Evaluation signs Department
  Evaluation, the Registrar signs the approval box.
- Decision: paper signatories ☐ / system signatories ☐ Initial: ..........

**6. Which word is printed for a returning student?**

- The paper: `Type: Old`.
- The system: `Student Type: Continuing` (`:71`), drawn from stored values
  `firstYear / continuing / transferee / shifter`.
- Printing "Old" is a display word for one stored value; the stored value itself would not change.
- Decision: print `Old` ☐ / print `Continuing` ☐ / other word: ................ Initial: ..........

**7. May the load print what the paper does not have?**

- The paper: six columns (`#`, `Subject Code`, `Subject Description`, `Lecture Units`,
  `Laboratory Units`, `Total`) and `Total Unit(s): 21` at the foot of the `Total` column.
- The system: nine columns — the same six (`Description`, `Lec`, `Lab`) plus `Schedule`, `Room`,
  `Instructor` (`:88-94`) — and a `TOTAL UNITS` row spanning three columns (`:118`).
- The extra three columns are what make the paper answer "which sections, which rooms"; dropping
  them leaves the load shorter but not self-explanatory.
- Decision: keep the extra columns ☐ / match the paper's six ☐ Initial: ..........

**8. Should the load carry a copy marker and an institutional stamp?**

- The paper: `Student Copy` bottom-left, red `SEAIT INC. ENROLLED` stamp bottom-right.
- The system: neither. Nearest is a footer sentence, "Valid only with the Registrar's signature".
- The slip already refuses to stamp a record that has not been cleared, so a stamp here would follow
  the same rule: it appears only on a load whose state earns it.
- Decision: add both ☐ / copy marker only ☐ / stamp only ☐ / neither ☐ Initial: ..........

## The class block and schedule

**9. Weekly grid, or meeting list?**

- The paper: a **grid** — half-hour rows from 07:00, columns `Mon`…`Sun`, each occupied cell holding
  subject, section and room.
- The system: a **flat list**, one row per meeting (`#`, `Subject`, `Day`, `Time`, `Room`,
  `Instructor`), sorted by day (`block-schedule.blade.php:81-95`), plus `Block Capacity: n/max`
  and a generation timestamp in the footer.
- Both carry the same meetings. The grid answers "what is happening at 10:30 on Wednesday"; the
  list answers "when does this subject meet". A grid is a wall document; a list is a filing
  document. This is the one row where no wording change closes the difference.
- Decision: weekly grid ☐ / meeting list as printed ☐ Initial: ..........

## The two papers that cannot be compared yet

**10. Blank forms for the Enrollment Certificate and the Enrollment Form.**

Both render, write their own issue rows and are reachable from this office, but no physical
counterpart was ever photographed, so their fidelity is **unverified** rather than checked. A blank
of each, photographed at this desk, lets the same comparison run on them.

- Provided: certificate ☐ / enrollment form ☐ Initial: ..........

---

## What each answer costs to carry out

Recorded so the office can weigh a ruling, not discover its price afterwards. All of it is layout
and wording in print files; no status value, permission or workflow rule moves.

| Row | What changes if the paper wins | Also touches |
| --- | --- | --- |
| 1 | Title wording and whose-copy designation; possibly a second page for the checklist | the Inertia slip screen, so the screen and the PDF keep agreeing |
| 2 | Five label edits in `clearance-slip.blade.php` | the same labels on the on-screen slip |
| 3 | Card geometry: landscape CSS to portrait, underlined rows to boxed cells | `class-card.blade.php` styles, `PrintClassCards.jsx`, and the fidelity note that the card is landscape today |
| 4 | Six label and format edits on the card; one more line if the printed date should be the Registrar's signing date rather than the day the PDF was made | the same fields on the card screen |
| 5 | Three signature captions replaced | nothing else — the caption is not a gate; who may sign is set by the workflow, not the paper |
| 6 | A display word for `continuing` in the load only | student-type wording appears on ten desk queues; a print-only map keeps that scope |
| 7 | Three columns dropped and the totals row reshaped | the column widths, and the schedule information would leave the load entirely |
| 8 | A copy marker and a conditional stamp added | follows the slip's rule that a stamp cannot contradict the table above it |
| 9 | The list becomes a grid: new table, meetings placed into slot rows | `PrintSchedule.jsx` for the Blocking desk, and the capacity footer's placement |
| 10 | Nothing until blanks exist; then the same comparison as rows 1-9 | two print templates currently unverified |

Returned to: the Registrar's office, or any team member holding the printed samples.
`Documentation/Print-Fidelity-Comparison-2026-10-10.md` holds the side-by-side evidence for each row.
