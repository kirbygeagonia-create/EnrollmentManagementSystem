# School decisions still outstanding — sheet for one meeting

**Issued 2026-10-27 · four items · 0 answered, 4 left for the school**

Four questions the registers cannot settle, gathered so they can be answered in one sitting. Nothing
here is owed by the software: each item says what the answer costs to build and how it comes back off,
and every one of the four is a question about what SEAIT recognises, not about what Laravel can do.

How to read a row: **As built** is what the system does today. **What the answer changes** is the
work the school's decision starts. **Undo** is how the answer is taken back if it turns out wrong.

The previous sheet (`Documentation/School-Decision-Sheet-2026-10-23.md`) carries its own sixteen
answered items and is not repeated here; a question answered on that sheet stays answered, and this
sheet does not re-ask it.

---

**1. Which reason categories does the school recognise for a returned record?** `[prompt §6.4, matrix G-10 — Open]`

**Open.** Development prompt §6.4 requires every return to carry a "reason category and mandatory
explanatory note", but it never enumerates the categories. The build had to pick a vocabulary, and
`App\Enums\ReturnCategory` now holds four values, chosen as the prompt's own examples named in the
document's words:

| Value a return row stores | What the desk reads on screen |
|---|---|
| `missingDocuments` | Missing documents |
| `examinationFailure` | Examination failure |
| `assessmentOrFeeHold` | Assessment or fee hold |
| `curriculumSectionMismatch` | Curriculum or section mismatch |

**What the answer changes.** The list is an enum column, so a fifth category (or a different spelling
of one of these four) is a migration plus a one-line change to the enum, and every return row already
stored keeps its value. The vocabulary is also what a term's return statistics would be counted by:
if the Registrar has to write "transferee paper incomplete" into `missingDocuments` because there is
no better label, the count of "missing documents" is the count of several different problems.

**Undo.** Retire a category by dropping it from the enum; a stored row that names a retired value is
read as an unknown category rather than rewritten, so nothing is lost.

**Asked of:** the Registrar, with the Admission office for the documents half.

---

**2. May Department Evaluation return a record to Admission?** `[prompt §6.4, matrix G-10 — Open]`

**Open.** The prompt names Admission, Evaluation and Registrar as offices that all return, and asks
that the record be routed to the phase that caused the problem. The build therefore offers both
destinations from every desk, and refuses one thing only: a return to Department Evaluation when that
desk has not signed the record yet, because sending a paper "back" to the desk that has not acted asks
it to act twice. A return to Admission is offered whenever an approved application exists to re-open
(§6.4's "correction expected and the office that must act next" has to name a real desk).

**What the answer changes.** If the school's process is that a department never writes to Admission
but records the defect on the same application instead, then `ReturnDestination` should stop offering
that destination to `DeptEvaluator` — one predicate in
`App\Services\ReturnRecorder::refusalFor()`, asked by the screen and the save together, so the desk
is never shown the choice the server would refuse.

**Undo.** Re-offering it is the same one predicate. No stored row is affected: a return already
recorded keeps its destination whatever the offer rule becomes.

**Asked of:** the Registrar and the Admission office jointly, because the answer decides which desk
holds the paper.

---

**3. What are the mandatory phases, if a workflow can be configured?** `[prompt §6.3, matrix G-6 — Open]`

**Open.** This is gap G-6 in `Documentation/Prompt-Requirement-Matrix-2026-10-26.md`. The seven boxes
today (Department Evaluation, Assessment, Accounting Payment, Registrar Approval, Blocking and
Scheduling, Clinic, ID Validation) are an owner ruling of 2026-09-29 recorded in the authority
document, and the order is written in `App\Services\WorkflowService::stepsFor()` — including the rule
that Assessment is skipped for a continuing or shifter student.

The prompt asks for an admin-configurable workflow versioned by academic year, semester and student
type. Building that means deciding what "mandatory" survives configuration: if a future year's form
may drop a box, then a signed instance keeps its snapshot rows (it already does) but the *rule* the
Registrar approves against — the readiness checklist, the clearance gate, the standing derivation —
has to say which boxes are load-bearing in a way that does not change when a definition is edited.

**What the answer changes.** A definitions table (year, semester, student type, ordered offices),
validation, a preview, and activate/retire, with `stepsFor()` reading the active version. This is the
largest of the four items and it is Phase 3 in the prompt's own plan.

**Undo.** `stepsFor()` keeps its current list as the fallback when no definition is active, so the
feature can be installed and left inert on purpose.

**Asked of:** the school administration, through the Registrar. It is not answerable by reading the
code: the code has no opinion about which phases the institution considers mandatory.

---

**4. What does a "Failed" enrollment mean for a walk-in student?** `[prompt §6.2, matrix G-8 — Open]`

**Open.** This is gap G-8 in the same matrix. `workflowsteps.stepStatus` is
`enum('pending','completed','skipped')`, so a box can wait, be signed, or be declared not part of
this student's type — it cannot say **on hold** or **failed** at the step level. The record-level
status now says a return is open (`returnedToEvaluation`, `returnedToAdmission`) and the item 8 hold
was ruled and built, so the half that is genuinely missing is a *step* that blocks the next office
and names why, without moving the whole record back.

The prompt's §6.2 asks for step-level On Hold / Failed / Returned. `onHold` is expressible as soon as
the school says which condition puts a box on hold (a pending clearance? an uncollected receipt? a
document that has arrived but not been verified?). `failed` is not a software question at all:
nowhere in the storyboard, the use-case guide, the authority document or the prompt does SEAIT state
that an enrollment fails, or who declares it.

**What the answer changes.** Widening `stepStatus` with `onHold` is a migration on both drivers (the
SQLite suite compiles the enum into a table check, so the widen has to travel with it) plus a policy
rule that a held box refuses the next office and prints the reason. `failed` additionally needs the
institution's definition and a decision about whether a failed step ends the record or parks it.

**Undo.** A value that no row holds can be dropped from the enum without touching data; a value that
rows hold needs those rows re-stated first, so the widen and the write path should ship together and
be retired together.

**Asked of:** the Registrar for `onHold`; `failed` needs the school's own definition before the desk
that would use it can be identified.

---

## What is not on this sheet

- The four items ruled on 2026-10-26 (clearance requirement lines, the slip as it prints, the desk
  print as the paper, a lost slip paid at the catalogue amount) are closed and answered in place on
  the earlier sheet, and re-asking them is how an answered question gets asked again.
- Gap G-9, returnee classification, is not a build question either: it waits on the same kind of
  institutional definition as item 4 and is recorded in the matrix with its phase and risk. It is
  left off this sheet's count because the prompt itself says to expose it from SEAIT policy or not at
  all, and nobody has read the policy back.
- The timetable import (G-5) is a build gap with a decided format, not a question for the school: it
  is on the engineering list, not here.
