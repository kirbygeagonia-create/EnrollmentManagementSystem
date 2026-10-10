# Print fidelity — structural comparison, 2026-10-10

**What this is.** `php artisan ems:print-fidelity` renders all eight desk PDFs from real
database rows into `storage/app/prints/fidelity/`. Four of them have a physical counterpart:
a filled Registrar form, photographed. This document compares the two on properties that can
be measured — page size, orientation, which labels appear, in what order, with which columns
and which signatories — so the Registrar's eye is spent on judgement ("is this acceptable?")
rather than on spotting that a word is different.

**What this is not.** It does not judge look and feel, and it decides nothing. Every row under
*Ruling needed* is an institutional choice about the paper, not a code defect. Nothing here
reproduces a value from the forms: the photographs carry a student number, personal names and
handwritten signatures, so they are deliberately **not in the repository** (`.gitignore`
excludes `Documentation/Images/`) and this comparison names fields, never contents.

**Method, so a later session can repeat it.** Page size read from each PDF's own `/MediaBox`;
layout read from the Blade template in `resources/views/prints/` that produces it; the paper
read from the photograph. Orientation is set in one place:
`PrintFidelitySamples.php:127` (`class-card*` and `block-schedule` landscape, everything else
portrait), which mirrors `PrintService::generatePdf(..., $landscape)`.

| Paper | Page as printed | Page as photographed |
| --- | --- | --- |
| Clearance slip | A4 portrait, 595.92 × 841.92 pt | a wide, short perforated stub |
| Class card | A4 **landscape** | a small portrait card |
| Student subject load | A4 portrait | A4 portrait |
| Class block and schedule | A4 **landscape** | A4 landscape |

---

## 1. Clearance slip — `clearance-slip.blade.php`

| # | The paper shows | The system prints | Class |
| --- | --- | --- | --- |
| 1.1 | Title reads `CLEARANCE 2nd Semester, 2025-2026 (Student's Copy)` — semester, year and the copy designation are all in the heading | Title is `Clearance Slip`; semester and academic year are two separate labelled rows below it, and **no copy is ever named** | shape + missing |
| 1.2 | `Full Name:` | `Student Name:` | vocabulary |
| 1.3 | `Course:` holding `(<code>) <full course name> - 2nd Year` — abbreviation first, ordinal word last | `Course & Year:` holding `<full course name> - Year 2` — no abbreviation, `Year n` form | vocabulary |
| 1.4 | A `Date` field, written by hand at issue | No issue date row; the only date is inside the Registrar block below | missing |
| 1.5 | `Received by:` over `Registrar In-charge (Signature over Printed Name) & Date` | `Received by Registrar`, then the name, then the timestamp — and the caption naming the office never appears | vocabulary |
| 1.6 | Not visible on the stub: a document number, a student-id row, an overall-status row, a per-office table, a cleared/held stamp, a replacement-fee note | All six are printed | extra |

**Reading of 1.6, and the one thing this pass cannot settle.** The photograph is of a
perforated *student's copy* — dashed tear lines run above and below it. The nine requirement
rows our slip prints may well exist on the portion of the sheet that was not photographed.
So 1.6 is recorded as **unverified**, not as a mismatch: the Registrar should answer whether
the office checklist is on the same sheet or on a separate one. If it is a separate one, the
system prints two documents as one.

**Ruling needed:** 1.1–1.5 are wording and heading-shape choices. 1.6 needs the Registrar's
knowledge of their own paper.

---

## 2. Class card — `class-card.blade.php`

| # | The paper shows | The system prints | Class |
| --- | --- | --- | --- |
| 2.1 | Portrait card | A4 landscape | shape |
| 2.2 | Name split into three **boxed cells**, the value typed above and the label (`Last Name` / `First Name` / `Middle Name`) printed beneath | `Label: value` rows with an underline | shape |
| 2.3 | `Course & Year` as `Bachelor of Science in Information Technology - First Year` — the level spelled as a word | `Course & Year: … - Year 1` (plus the major, which the paper's line does not carry) | vocabulary |
| 2.4 | Subject block is a table row: `Subject Code` \| `Descriptive Title` \| `Units`, labels beneath the values | Three `Label: value` rows, and the middle label is `Description:` not `Descriptive Title` | vocabulary + shape |
| 2.5 | `Units` holds one number (`3`) | `3 (Lec: 2, Lab: 1)` | extra |
| 2.6 | `Set` \| `Time` \| `Day` \| `Grade` as four cells; `Time` holds the two ranges, `Day` holds `MON, TUE, SAT` once | Same four boxes, **but the day is printed twice** — the `Time` box writes `Mon 11:30-13:00` per meeting and the `Day` box then lists `Mon` again | defect (see note) |
| 2.7 | `Name and Signature of Instructor:` with the name written by hand | `Name & Signature of Instructor` pre-filled from the schedule's instructor, no signature space of its own | vocabulary |
| 2.8 | Two `Date:` fields, one under the instructor and one under `Issued by:` | One `Date` box and one `Date:` in the footer, both auto-filled with today | shape |
| 2.9 | Semester line is `2nd Semester \| 2024-2025` | `1st Semester, 2026-2027` — comma, no pipe | vocabulary |

**2.6 is the only row in this document that is not a taste question.** The day genuinely
appears twice on our card because two boxes are fed from the same meeting list. Whether the
fix is to drop the day from the `Time` box or to drop the `Day` box depends on which shape
the Registrar wants (2.1–2.2), so it is left unfixed and named here.

---

## 3. Student subject load — `subject-load.blade.php`

| # | The paper shows | The system prints | Class |
| --- | --- | --- | --- |
| 3.1 | Title `Student Subject Load` | Title `Subject Load`, with a subtitle sentence the paper has no equivalent of | vocabulary |
| 3.2 | `Year Level: 3rd` | `Course & Year: … - Year 3`, folded into the course line | vocabulary |
| 3.3 | `Type: Old` | `Student Type: Continuing` — the app's vocabulary is `firstYear / continuing / transferee / shifter` (`app/Enums/StudentType.php`) and **no value of it reads "Old"** | vocabulary, needs a ruling |
| 3.4 | Table is six columns: `#` \| `Subject Code` \| `Subject Description` \| `Lecture Units` \| `Laboratory Units` \| `Total` | Nine columns: the same six (`Description`, `Lec`, `Lab`) plus `Schedule`, `Room`, `Instructor` | extra |
| 3.5 | `Total Unit(s): 21` sits at the foot of the `Total` column | A `TOTAL UNITS` row spanning the first three columns, then the three unit figures | shape |
| 3.6 | Three signing lines: `Date Enrolled`, `Evaluated by`, `Processed by` — each a name over a line | Three signing lines: `Student`, `Class Adviser`, `Registrar` | **different actors** |
| 3.7 | `Student Copy` bottom-left, and a red `SEAIT INC. ENROLLED` stamp bottom-right | Neither. The nearest thing is a footer sentence, "Valid only with the Registrar's signature" | missing |
| 3.8 | Letterhead set in a blackletter/serif face with the institutional seal | Letterhead set in the system sans (`DejaVu Sans`, Arial), logo embedded from `public/images/logos/` | brand — not decided here |

**3.6 is the substantive one.** The paper is signed by the people who *produced* the load (the
evaluator and the processing clerk, whose names the system already stores as
`evaluatedByUser` and `registrarProcessedByUser`); our print asks the student and an adviser to
sign. Those are different documents wearing the same title, and only the Registrar can say
which the office files.

**3.3 also matters beyond the paper:** if the office's word is "Old", the system's word is
"Continuing", and the two will keep disagreeing on every load until one of them changes.

---

## 4. Class block and schedule — `block-schedule.blade.php`

| # | The paper shows | The system prints | Class |
| --- | --- | --- | --- |
| 4.1 | A **weekly grid**: rows are half-hour slots from 07:00 down the page, columns are `Mon`…`Sun`, and each occupied cell holds the subject, section and room | A **flat list**: `#` \| `Subject` \| `Day` \| `Time` \| `Room` \| `Instructor`, one row per meeting, sorted by day | **shape — the largest difference in this comparison** |
| 4.2 | Heading `Course and Year: BSIT / BSIT-BA 3` — one block can carry two program codes, and the year is a bare digit | `Course:` (one program) and `Year Level: Year 3` as separate fields | shape + vocabulary |
| 4.3 | `Block 13` in the top-right corner | `Block:` in the field list | shape |
| 4.4 | No instructor names anywhere on the grid | An `Instructor` column | extra |
| 4.5 | No capacity, no generation timestamp | Footer `Block Capacity: n/max` (distinct non-dropped students, the same reading the desk uses) and `Generated on …` | extra |

**Reading of 4.1.** Both renderings carry the same meetings; neither is wrong. The grid answers
"what is happening at 10:30 on Wednesday", the list answers "when does this subject meet". A
desk that posts the sheet on a wall wants the grid; a desk that files it per block may prefer
the list. This is a ruling about the paper's job, and it is the only one of the four where the
layout difference is big enough that no wording fix closes it.

---

## 5. The two papers with nothing to compare against

`enrollment-certificate.blade.php` and `enrollment-form.blade.php` render, write their own
issue rows and are reachable from the Registrar desk, but **no physical counterpart was ever
photographed** — `Documentation/First_Build_Plan.md` recorded this for the form ("no reference
image exists; photograph a blank physical form") and the same is still true of the certificate.
Their fidelity is therefore unverified, not verified-and-passing. A blank copy of each,
photographed at the Registrar's desk, is what would let the same comparison run on them.

---

## 6. What the Registrar is being asked to sign

| Row | Question | Answer | Initial |
| --- | --- | --- | --- |
| 1.6 | Is the per-office requirement checklist on the same sheet as the student's copy, or a separate document? | | ☐ |
| 1.1–1.5 | Do the slip's labels follow the paper (`Full Name`, `Date`, `Registrar In-charge (Signature over Printed Name) & Date`, semester-in-title, `2nd Year`) or does the paper follow the system? | | ☐ |
| 2.1–2.9 | Same question for the card, including whether it is a portrait card at all | | ☐ |
| 2.6 | The day prints twice on our card — drop it from the `Time` box, or drop the `Day` box? | | ☐ |
| 3.6 | Is the subject load signed by Student / Class Adviser / Registrar, or by Evaluated by / Processed by as the paper shows? | | ☐ |
| 3.3 | Is the student-type word on the paper ("Old") the one to print, or is the system's ("Continuing") correct? | | ☐ |
| 3.4–3.5 | May the load print the extra three columns and the totals row the paper does not have? | | ☐ |
| 3.7 | Should the load carry a `Student Copy` marker and an `ENROLLED` stamp? | | ☐ |
| 4.1 | Does the block schedule need to be a weekly grid, or is the meeting list the document the office files? | | ☐ |
| §5 | Provide a blank photograph of the enrollment certificate and the enrollment form so those two can be compared too | | ☐ |

Nothing in this file was changed in the templates. Every row above is a paper decision first and
a code change second, and the code follows the ruling — not the other way round.
