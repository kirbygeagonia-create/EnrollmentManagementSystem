# School decisions still outstanding — sheet for one meeting

**Issued 2026-10-29 · six items · 0 answered, 6 left for the school**

Six questions the registers cannot settle, all of them produced by the completion audit of the
development prompt on this date (matrix §7 and §8, gaps G33 to G42). Nothing here is owed by the
software: each item says what the answer costs to build and how it comes back off. Where a mechanism
is cheap and only the vocabulary is the school's, that is said plainly, because a question that sounds
like a migration will be deferred for a reason it does not deserve.

How to read a row: **As built** is what the system does today. **What the answer changes** is the work
the school's decision starts. **Undo** is how the answer is taken back if it turns out wrong.

The previous sheets — `School-Decision-Sheet-2026-10-23.md` (sixteen items, all answered) and
`School-Decision-Sheet-2026-10-27.md` (four items, still open) — keep their own answers and are not
re-asked here. In particular item 1 of the 27th sheet, the return reason categories, is still the open
question it was; this sheet does not duplicate it.

---

**1. Where should an in-app notice take the desk that receives it?** `[prompt §8, matrix G35 — Open]`

**As built.** The bell shows one sentence and a timestamp, and nothing is clickable. The payload already
carries `enrollmentId` and `workflowId`, and the `notifications` table has no title column, so the two
things prompt §8 lists — an intelligible title and a protected deep link — are absent, not merely
hidden.

**What the answer changes.** A title line and a link. The recommendation is that the link land on the
record's page **on the recipient's own desk** — the same predicate that decided who was notified decides
who may open it — and never on the Student 360, because that page requires `students.view`, which a bare
`OfficeHead` or `Instructor` does not hold; a link there is a 403 for the desk the notice was written for.

**Cost and undo.** One payload key plus one anchor in `resources/js/Layouts/AuthenticatedLayout.jsx`; a
title is a column or a computed line, your preference. No migration is needed for the link. Nothing
stored moves, so the undo is one revert.

---

**2. May a staff member who is already signed in keep acting after their account is deactivated?**
`[prompt §9, §14, matrix G42 — partly answered]`

**As built.** A deactivated account cannot start a new session — `LoginRequest` refuses it with the cause
named, and that is now pinned by two tests, one of which proves the refusal is the status and not the
password by authenticating the same account after it is reactivated. An **already-open** session is not
re-checked: the person continues to act until they sign out, and navigation hiding is not a control.

**What the answer changes.** If the school wants the session cut, that is a middleware rule applied to
every request for staff, and it must answer a second question at the same time: what happens to a record
the desk left half-written.

**Cost and undo.** Small either way — a guard in the staff middleware, or a written statement that the
current behaviour is intended and the register stops calling it a gap. The rule is reversible in one
commit; the session cut is invisible to users until it fires.

---

**3. Who may read clinical notes, as distinct from the clinic's clearance outcome?**
`[prompt §10, matrix G38 — Open]`

**As built.** The Student 360 shows the clinic line's `findings` free text, height, weight and blood
pressure to any account holding `students.view`. The full assessment narrative (`assessmentNotes`) is
already withheld. Prompt §10 asks that most desks know the *outcome*, and assigns the disclosure question
to the school and its Data Protection Officer, so this repository has ruled nothing.

**What the answer changes.** Either the 360's clinical section becomes Clinic-and-named-offices only, or
the school states that `students.view` entitles the notes and the finding closes as intended design.

**Cost and undo.** A policy question with a one-section answer either way: the data is already behind a
flagged block in the page and one policy method. Reversible in a single commit; nothing stored changes.

---

**4. Should the audit trail name the act, not just the row change?** `[prompt §9, matrix G37 — Open]`

**As built.** `auditlogs.action` records `created`, `updated` or `deleted` for everything, because one
observer watches every model. The seven families prompt §9 lists — requirement verification, the
provisional-to-official transition, a crediting decision, a Registrar return or override, a receipt void
or refund, a step signing or skip, a role change — are therefore not answerable as a query on the audit
table. Each is fully recorded **in its own domain table** with actor, date and (mostly) reason, so the
history exists; what does not exist is one place to ask.

**Cost, stated precisely because it is the cheapest item on this sheet.** `auditlogs.action` is
`varchar(100)`, **not** an enum, so naming the act needs **no migration** — the act writes its own verb.
A `reason` column would need one, and may be unnecessary where the domain row already carries the reason.

**What the answer changes.** A vocabulary list (the school's), then a write at each act. Or the document
states that per-act trails are the design and closes the gap as answered-otherwise. The alternative is a
reconciliation report, not a rewrite.

---

**5. Is a rejection reason for an application a fixed list, free text, or both?**
`[prompt §6.5, matrix G33 — Open, but the mechanism is not waiting]`

**As built.** A rejected admission carries **no reason at all**: the desk posts an empty body, the
controller validates nothing, and `admissions` has no column that could hold one. The case page tells the
reader "Contact the admission office for details". Separately, a refused requirement line currently
substitutes an invented sentence — `'Requirement rejected / needs resubmission'` — when the desk types
nothing, which is a placeholder passing as a reason.

**What is being built regardless.** A nullable reason column, a mandatory reason on a rejection, and the
reason rendered wherever the case is read: prompt §6.5 states the rule twice, so the mechanism needs no
ruling. What the school owns is only whether the reason is chosen from a list.

**Cost and undo.** One additive migration, one validation rule, one display line. The column is nullable,
so nothing existing is rewritten and the undo is a revert plus a reseed-free drop.

---

**6. Does a browser check belong in the pipeline?** `[prompt §14, matrix G41 — Open]`

**As built.** Prompt §14 asks that no console errors occur in key workflows and that empty, loading and
failure states be checked. Nothing in CI can currently answer that: there is no JavaScript test runner,
and the only Chrome use is PDF rendering. Those clauses are therefore **unverified, not passing** — this
sheet records that rather than letting the green suite imply them.

**What the answer changes.** Either a CI job that drives the real screens for a handful of desk walks
(this box has the tooling), or a named human QA pass in the document with a date and initials, or the
explicit statement that the clause is out of scope for a walk-in build.

**Cost and undo.** Additive and revertible: a workflow file and a leg, at the price of run time on every
push. That cost is the actual decision.

---

**None of these six is blocked on the software.** Items 4 and 5 are already proceeding on the parts that
need no ruling; items 1, 2, 3 and 6 change only once the school says which behaviour is intended. Each
answer comes back off the same way it went in: one commit, plus a reseed for anything that touches
reference data.
