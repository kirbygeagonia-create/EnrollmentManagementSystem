# UI/UX Refinement Task List — Analysis

**Date:** 2026-09-17
**Scope analyzed:** the proposed 12-item UI/UX and workflow-visibility refinement pass (Admin, Evaluation, Blocking, ID-validation modules), against the codebase as of commit `46c6916` (master, clean tree, CI green).
**Baseline:** SEAIT-EMS-Round3-Current-State.md — backend/security findings closed; this pass is additive on top of it.

## Verdict up front

The list is **correct and sensible in substance** — every premise checked against the code verified, and the instincts are the right ones (no parallel status concepts, reuse optimized queries, extend the state machine rather than invent a mechanism, OCR as a later phase). Four items need adjustment before execution, two of them because their premise describes the *current* state rather than the target, and there is one internal contradiction in the scope statement.

## Item-by-item verdicts

| # | Task | Verdict |
|---|---|---|
| 1 | Office branding + GZEL removal | ✅ Correct — **do it after #10** (see sequencing) |
| 2 | Admin progress workflow view | ✅ Correct & feasible — target verified |
| 3 | Admin write-boundary | ✅ Correct — pattern exists to extend |
| 4 | Guidance vs. department exams | ✅ Correct — premise verified |
| 5 | Course/program catalog | ✅ Correct — one data dependency |
| 6 | Evaluation by student type | ⚠️ Partially built already — reframe as *refine*, and it depends on #7 |
| 7 | Curriculum + prerequisites | ⚠️ Prerequisites already exist — the new work is versioning, which is **schema work** |
| 8 | Registrar hold state | ✅ Correct & well-specified — but it **is backend work** |
| 9 | Blocking + Scheduling table | ✅ Correct — good phasing |
| 10 | ID validation, drop card-making | ⚠️ Right direction — 2 corrections needed |
| 11 | Tone / disabled states | ✅ Correct — matches the established audit pattern |
| 12 | @designer/@oracle + gates | ⚠️ Not executable as written from Claude Code (opencode agents) |

## Items needing adjustment

### Item 1 — correct, but sequence it after #10

The GZEL/JZEL surface is concentrated in exactly the screens item 10 removes:
`ID/Index.jsx:107-109` (subtitle, logo, logoAlt), `ID/Show.jsx:44` (`producedByVendor: 'JZEL Printing Services'`), `:124` (gzel logo), `:337`, `:623`. Do #10 first and item 1's GZEL work shrinks to `MegaAppLauncher.jsx:169`, the storyboard docs, and deleting `public/images/logos/gzel-id-validation.jpg`.

Two cautions:
- The repo uses **two spellings** — GZEL in UI text, JZEL in the storyboard and vendor field — sweep for both.
- Logo assets exist for only some offices (`public/images/logos/`: guidance-office.jpg, clinic.jpg, scholarship.jpg, safety-and-security.jpg + 6 college/department logos); Registrar, Accounting, Admission, Blocking have none. "Avatar per OfficeId" needs either new assets or a seait-logo fallback.

### Item 2 — correct, target verified

There is no separate Admin panel page — the System Admin area is the **"Campus Operational Desks" card on the Executive Dashboard** (`resources/js/Pages/Dashboard.jsx:229-282`), whose admin-only tab (line 236) renders module link tiles genuinely redundant with the MegaAppLauncher apps grid and DeskSubNav. Replacing it with a per-applicant progress view is feasible: workflowsteps carry `officeId`, so per-office progress bars come straight from existing data.

Care point: the student list is gated behind `students.view` / `can.studentsView` — the mini-status rides along on those rows fine, but must not render for roles without that permission.

### Item 3 — correct

The pattern to extend exists and works: `AuthServiceProvider.php:202` defines the gate, `BlockingPolicy::assignStudents` scopes to office 5 with SysAdmin/Admin pass-through. Generalizing it formalizes what's currently implicit. The "logged admin override" should build on the **existing audit-log** (AuditLogObserver with write-time redaction) rather than inventing a second log — state this explicitly so the implementer doesn't create a parallel log concept.

### Item 4 — correct, premise verified

`tests/Feature/Exam/ExamControllerTest.php` exists with the two-stage entrance-exam / retention-gating coverage (SEAIT-EMS-Round3-Current-State.md line 45 confirms). Guidance = OfficeId 4; the entrance-only / retention split matches the existing permission separation (`exam.record.retention` is its own permission). This item is genuinely "verify, then refine the UI" — no data-model work expected.

### Item 5 — correct, one data dependency

The controller is at `app/Http/Controllers/Admin/ReferenceDataController.php` (not the Controllers root). Courses are DB reference data, so "admin-editable without a deploy" is already how that layer works. The "full actual program offering" half is a **data task** — it needs the institution's real catalog as input (the six college logos suggest the real offering is much larger than the seed data), which is human-dependent, not code.

### Item 6 — partially built already; reframe, and it depends on #7

The transferee credit-evaluation backend already exists: `EvaluationController::processCredits()` (line 302), and the Evaluation page already advertises "transferee crediting & dean signing." The screen also already loads curriculum subjects **with their prerequisites** (`EvaluationController.php:89-90`: `Curriculumsubjects::with('subject', 'prerequisiteSubject')`).

So (c) is likely "verify the existing credits section, then reshape it into the side-by-side screen with explicit sign-off" — not build-new. (a)/(b) need verification of how much freshmen-vs-continuing branching exists. **Regular/Irregular:** no dedicated field was confirmed — verify whether one exists or it's derived; if neither, that's a small addition.

Dependency: (a)'s "curriculum-prescribed Year 1/Sem 1" reads from *a* curriculum — once #7's versioning lands, which curriculum that is changes. Build #6 after #7 or it gets rebuilt twice.

### Item 7 — the prerequisite premise is already satisfied; the real work is versioning

`Curriculumsubjects` fillable already includes `'prerequisiteSubjectId'`, and the schema supports multiple rows per subject — an explicit prerequisite *list* already exists, and Evaluation already reads it. So "not just a year/semester bucket" describes today's state. What's genuinely missing:

- **Versioning** — `Enrollments` has **no** `curriculumId` (verified: zero matches in the model). "Students tied to the admitted-under version" needs a version strategy on Curriculums + a linkage on Enrollments/Students + migration + backfill + query updates. That is real schema work.
- **Auto-gate/flag failed prerequisites** — additive logic in `proposeSubjects`, fine.
- CHED CMO 25 s. 2015 as template — sensible framing.

### Item 8 — correct and well-specified, but it IS backend work

The state machine exists (EnrollmentStateMachine TRANSITIONS: pending→evaluated→assessed→paid→enrolled/dropped; void-revert paid→assessed). Adding a case + transitions + migration (widen the status enum) + tests is exactly how to do it; the required-reason is right. Design note worth adding: the return should target `evaluated`, with re-assessment following naturally (`evaluated → assessed → paid`) since changed subjects alter charges. But this item directly contradicts the scope statement — see below.

### Item 9 — correct

Blocking is office 5 with the m5-corrected "Blocking & Scheduling Officer" title, so combining the two into one screen is a consolidation of things that already belong together. 7:00–21:00 in 30-min intervals = 28 slots, fine. "Capacity editable, finalizable" should mirror the assessment finalize pattern (idempotency guard) — the precedent exists. OCR as a separate later phase keeps this pass shippable.

### Item 10 — right direction, two corrections

Verified scope: card-making routes exist (`routes/web.php:132-136`: `id.produce` → produceCard, `id.release`, `id.reissue`, `id.cancel`) + the JZEL vendor intake fields on ID/Show + the Phase 8 workflow step (office 22).

1. **The "parent Safety & Security office" premise is outdated.** There is no Safety & Security office in the offices table — office 6 is Admission; "Chief of Safety & Security" was the *retired mislabel* the audit's m5 just fixed. The `safety-and-security.jpg` asset is a leftover from that old naming. The offices table is flat — no parent/child hierarchy exists in the schema — so **IdOffice (22) standalone is correct as-is**; adding a parent office would be schema + seed churn for zero functional gain. Delete the leftover asset as part of item 1's branding sweep instead.
2. **Release/reissue ambiguity.** If card-making is removed entirely, what does `id.release` (routes/web.php:134) release, and what does `id.reissue` reissue? Recommended scope: **keep validation + release, drop produce/reissue/cancel + the JZEL vendor intake fields**. The workflow's Phase 8 ID step must **stay** (validation is kept, and it's part of the 8-step completion) — but its step description references production, so update the text.

### Item 11 — correct

Matches the established audit pattern exactly (disabled-states-with-reasons, confirm dialogs, idempotency guards). Consistent, low-risk, no corrections.

### Item 12 — not executable as written from Claude Code

- **@designer and @oracle are opencode agent names** (AGENTS.md is loaded via `opencode.json`). They are not available agent types in the Claude Code environment. If this pass runs in opencode, the reference is fine; if it runs in Claude Code, `.claude/agents/` equivalents are needed or reviews run directly.
- **AGENTS.md's gate contract is stale** — it says "Pest: 216/216"; the suite is now **244 tests**. Update it before work starts so the gate is checkable.
- **AGENTS.md's Mandatory Workflow Rule conflicts with the standing never-commit rule.** It mandates committing before dispatching agents and pushing after gates; the standing instruction is never commit/push until the user says so. The user's rule wins — decide the cadence consciously before dispatching anything.

## One contradiction in the scope statement

*"Backend/security work is already closed out… this pass is additive — do not re-open that."* — but items **6** (partially), **7** (schema: versioning), and **8** (enum + transitions + migration + tests) all require backend changes. Round 3's "closed out" means the security/operational *findings* are closed — it was never a promise that no backend work would ever happen again. Suggested rewording:

> "No re-opening of closed security findings; backend changes are in scope where an item explicitly requires them (6c, 7, 8), still gated by the 5 gates."

## Sequencing

**10 → 1** (removing the ID screens shrinks the GZEL surface) and **7 → 6** (stabilizes what "curriculum-prescribed" means). Item 8 can go anytime. Items 2, 3, 4, 5, 9, 11 are independent.

Starting point: clean tree on master, CI green, `46c6916` on top.
