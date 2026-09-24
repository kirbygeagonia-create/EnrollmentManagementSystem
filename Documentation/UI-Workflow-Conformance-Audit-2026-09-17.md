# UI ↔ Workflow Conformance Audit — 2026-09-17

**Scope:** Does the React/Inertia UI properly show and enforce the intended enrollment workflow (phase badges, status gating of buttons, queue filters, workflow visualization, cross-page consistency)?

**Companion audit:** input-control fit across every form (`Forms-UX-Audit-2026-09-22.md`) — this audit scoped input-control choice out; the companion sweep covers it.

**Method:** Read every module action page (Show/Index), the shared workflow components, the state machine, all desk controllers, Dashboard queue logic, routes, enums, models, and seeders. Every finding below cites file:line evidence verified in this session. Nothing is speculative unless marked otherwise.

---

## Verdict Summary

**The workflow logic is enforced correctly at every phase gate — but the UI misrepresents state in four places, is missing the workflow's own visualization everywhere except one page, and has one live-operation dead-end (clearance receiving).**

| Workflow Phase | Logic Enforcement | UI State Representation | Conformance |
|---|---|---|---|
| Phase 0 — Admission | ✅ approve/reject gated to `pending` | ✅ Correct | PASS |
| Phase 0.5 — Exams | ✅ (out of scope this pass) | ✅ | PASS |
| Phase 1 — Clearance | ✅ stamp actions gated to `pending` | ❌ **Slip generation + Registrar receiving have NO UI (dead-end)** | **FAIL** |
| Phase 2 — Evaluation | ✅ | ✅ | PASS |
| Phase 3 — Assessment | ✅ compute idempotent | ❌ **Banner reads non-existent `status` column; course name always '—'; Total Paid always ₱0** | **FAIL** |
| Phase 4 — Accounting | ⚠️ **no status guard on `record()`** | ⚠️ **Collect form always active; overpayment absorbed** | **PARTIAL** |
| Phase 5 — Registrar | ✅ Approve gated + hidden when enrolled | ⚠️ 5-item UI checklist vs 4-item backend validation | PASS (with gap) |
| Phase 6 — Blocking | ✅ Enrolled + capacity enforced in UI | ✅ Correct | PASS |
| Phase 7 — Clinic | ✅ strict "first pending step is mine" queue | ❌ **workflow never rendered on Show page** | PARTIAL |
| Phase 8 — ID | ✅ strict queue | ❌ **workflow never rendered; `validationStatus === 'valid'` wrong enum value** | PARTIAL |
| Student 360 page | — | ❌ **4 confirmed display bugs** | **FAIL** |
| Dashboard | — | ⚠️ **queue counts ≠ actual desk queues; magic officeIds** | PARTIAL |

---

## CRITICAL Findings

### C1. Clearance slip generation + Registrar receiving have NO UI — continuing-student enrollment dead-ends at Phase 5

**Evidence:**
- `clearance.slip.generate` (routes/web.php:94) and `clearance.receipt.record` (routes/web.php:95) exist as backend routes with proper gates (AuthServiceProvider:228-234) — but **zero usages anywhere in `resources/js/`** (verified by grep across all js/jsx).
- `ClearanceController::recordReceipt` (ClearanceController.php:165-171) is the *only* place `receivedBy`/`receivedDate` are set — and nothing calls it.
- `RegistrarController::checkClearance` (RegistrarController.php:109-128) requires `overallStatus === Approved && receivedBy && receivedDate` for continuing/shifter students whenever a clearance period is open.

**Failure chain in live operation:**
1. Office staff stamp all 10 clearance requirements in the matrix (✅ works via UI).
2. Nobody can generate the clearance slip (BR33) — no button exists.
3. Nobody can submit the completed slip for Registrar receiving (BR34) — no button exists.
4. `receivedBy`/`receivedDate` stay null → `checkClearance()` returns false.
5. `RegistrarController::approve` (line 151-153) blocks: *"Not all prerequisites are met."*
6. The Registrar/Show checklist shows ✗ "Clearance Verified" with **no way to resolve it from the UI**.

**Result:** Continuing/shifter students can never complete enrollment through the UI while a clearance period is open. The Demo-Day script works only because the demo seeder sets `receivedBy`/`receivedDate` directly in the database.

**Fix (P0):** Add a "Receive Completed Slip" action (calls `clearance.receipt.record`) on Clearance/Index for fully-stamped clearances, and a "Generate Slip" action (calls `clearance.slip.generate`) — both routes and gates already exist.

### C2. The 8-step workflow form — the "digital twin of the paper workflow form" — renders in exactly ONE page

**Evidence:**
- Build_Plan.md Stage 3.1/3.3 promised a shared **PhaseStepper** "visualizing the 8-step workflow with signed/pending states — the digital twin of the paper workflow form."
- `grep -i workflowsteps resources/js` matches **only** `Students/Show.jsx`.
- Clinic/Show.jsx (Phase 7) and ID/Show.jsx (Phase 8) never render the workflow — yet their controllers load and pass `enrollmentworkflow.workflowsteps` to those pages.

**Consequence:** Clinic and ID desk staff sign their workflow steps **without ever seeing the workflow progress** — they can't tell whether prior steps are signed, why a student is or isn't in their queue, or where the student sits in the journey. The signature UI exists; the context that the paper form provided does not.

**Fix (P1):** Extract the WorkflowStepper from Students/Show.jsx into `Components/ui` and mount it on Clinic/Show, ID/Show (and ideally Registrar/Show, Accounting/Show).

### C3. WorkflowStepper status-mapping bug — every pending step renders as "current"

**Evidence:** Students/Show.jsx:75-113 —
```js
step.stepStatus === 'completed' ? 'completed'
: step.stepStatus === 'skipped' ? 'skipped'
: 'current'
```
All `pending` steps fall through to `'current'`. A workflow with 1 signed step and 6 pending steps renders **seven "current" dots simultaneously**. The one component that IS the workflow twin misrepresents progress on the most important page.

**Fix (P0, one line):** final fallback should be `'pending'`, with a separate `current` computed from the first pending step.

### C4. Student 360 page — four confirmed display bugs

| # | Bug | Evidence | Visible Effect |
|---|---|---|---|
| C4a | Reads non-existent attribute `totalAssessment` | Students/Show.jsx:123, 213 (actual column: `totalAssessedAmount`, Studentassessments.php:18) | Assessment/Balance and "Total Assessed" StatCard **always '—'** |
| C4b | Stepper maps all pending → current | Students/Show.jsx:75-113 (C3 above) | Misrepresents workflow progress |
| C4c | No clinic records section | StudentController::show never loads `clinicrecords` (relation exists, Enrollments.php:96); page has no section | Demo-Day-Guide step 14 claims "360 page shows clinic records" — it doesn't |
| C4d | `id.validationStatus === 'valid'` — wrong enum value | Students/Show.jsx:543; enum value is `'active'` (IdValidationStatus.php:8) | Valid IDs never show the "Valid" badge |

**Fix (P0/P1):** C4a and C4d are one-line attribute-name fixes. C4c needs a relation load + section (or a doc correction to the Demo-Day script).

---

## Medium Findings

### M1. Accounting: no enrollment-status guard on `record()` + Collect form always active

**Evidence:**
- `AccountingController::record` (AccountingController.php:71-117) validates OR number/amount/mode/date but has **no check on the enrollment's status** before transitioning.
- Accounting/Show.jsx renders the Collect Payment form unconditionally — there is no "Fully Paid" state on the page.

**Failure paths:**
1. Cashier navigates back (browser back) to a now-Paid enrollment's page → records another payment → `paid → paid` transition → **InvalidStateTransitionException → 422 + rollback** + confusing error.
2. Payment on an Evaluated (not-yet-finalized) enrollment with resulting balance ≤ 0 → `evaluated → paid` → **422**.
3. Payment on an Evaluated enrollment with partial payment (balance > 0) → **no transition → payment silently accepted before assessment finalized** (workflow-order violation — the concrete form of the "payment_completed accepts any payment" divergence documented in the design review).

**Fix (P0):** Guard `record()` with the enrollment status (reject or redirect-with-info when not `Assessed`), and hide/disable the Collect form when the balance is 0 on Accounting/Show.

### M2. Accounting: overpayment silently absorbed

The POS form allows any amount (min 0.01, no max — Show.jsx:316-318; backend `min:0.01`, no max — AccountingController.php:77). A ₱5,000 payment on a ₱3,000 balance clamps to 0 and transitions to Paid — the ₱2,000 excess is invisible: no change record, no warning. **Fix (P1):** warn in the UI and/or track overpayment/change.

### M3. Dashboard queue counts ≠ actual desk queues

**Evidence (DashboardController.php:51-70):**
- `registrar` counts only status `Paid` (line 59) — but the actual Registrar queue shows **Assessed OR Paid** (RegistrarController.php:52). Dashboard undercounts.
- `blocking`/`clinic` counts use `officeId + stepStatus pending` (lines 60-65) — but the actual desk queues use the stricter **"first pending step is mine"** whereRaw logic (ClinicController:36-39, IDController:38-41). Dashboard can count enrollments whose step is pending but whose *first* pending step belongs to another office — so the dashboard number differs from what the desk actually sees.
- Magic numbers `where('officeId', 5)` / `where('officeId', 11)` (lines 61, 64) — these survived the OfficeId enum remediation; should be `OfficeId::Blocking->value` / `OfficeId::Clinic->value`. The `stats` block (lines 34-44) also uses string literals `'pending'`/`'enrolled'`/`'paid'` instead of enum constants.

**Fix (P1):** align count queries with the queue definitions and swap magic numbers for enum constants.

### M4. Registrar: 5-item UI checklist vs 4-item backend validation

Registrar/Show.jsx:6-12 renders five checklist items (including `registrarApprovalPending` "Registrar Ready"); `RegistrarController::approve` (lines 144-149) validates only four. The UI is *stricter* than the backend (not looser), and the state machine catches same-state transitions anyway — but the 5th gate is defense-in-depth only. Also the Show page computes `registrarApprovalPending` with a fresh `workflowsteps()` query (RegistrarController.php:94) while the backend approves without re-checking it. **Fix (P2):** validate all five items in `approve()` for parity.

### M5. Assessment/Show: banner reads non-existent `status` column

Assessment/Show.jsx:37 reads `assessment.status === 'assessed'` — Studentassessments has **no `status` column** (Studentassessments.php:18). The status banner always falls through to **"Pending — Assessment not yet finalized. Compute charges and finalize to proceed"** even immediately after finalize; the Badge beside it is always '—' (line 185). The page misleads the desk clerk at exactly the moment they finalize. **Fix (P0, small):** derive the banner from `enrollment.enrollmentStatus` (loaded via `assessment.enrollment`) instead.

### M6. Assessment/Show: `Total Paid` always ₱0.00 + wrong payment column names

`AssessmentController::show` (AssessmentController.php:72) never loads the `payments` relation — it exists on the model (Studentassessments.php:44-47, HasMany via enrollmentId) — so `assessment.payments || []` (Show.jsx:24) is always empty and "Total Paid" (line 26, 243-250) always shows ₱0.00. The payment table columns also use wrong field names: `paymentMethod`/`referenceNumber` (Show.jsx:76-77) instead of `paymentMode`/`orNumber`. **Fix (P1):** eager-load `payments` and fix the two column keys.

### M7. Assessment/Show: course name always '—'

Show.jsx:146 and 201 use `enrollment?.course?.name` — the Courses column is **`courseName`** (Courses.php:17). Accounting/Show and Blocking/Show use `courseName` correctly. **Fix (P0, one line).**

### M8. Assessment finalize: no backend idempotency → 422 on repeat

`AssessmentController::compute` has an idempotency check (lines 90-93) but `finalize` (251-267) does not. After finalize succeeds the button re-enables (Show.jsx:405 only disables while submitting) — a second click or a revisit throws `assessed → assessed` → 422. The ConfirmDialog mitigates accidental double-clicks. **Fix (P2):** early-return with info when the enrollment is already `Assessed` (mirroring compute).

---

## Minor Findings

| # | Finding | Evidence |
|---|---|---|
| m1 | State machine allows `paid → assessed` (void-revert, EnrollmentStateMachine.php:25) — the System_Storyboard claims "no reverse paths". Design nuance + doc divergence; the revert itself is correct behavior. | EnrollmentStateMachine.php:25 vs System_Storyboard_Walkthrough.md |
| m2 | Clearance stat tiles computed from the **current page only** (`rows = clearances.data`, page of 20) but presented as page-level summaries — counts understate the full dataset when paginated. | Clearance/Index.jsx:45-68 |
| m3 | Dashboard queue **visibility** lists broader than route policies: Evaluation visible to offices [4,5], Clearance to [6,8] (Dashboard.jsx:88, 94) — office-5/office-6 users can see the cards but may hit policy-denied pages on click. Verify `EvaluationPolicy`/`ClearancePolicy` viewAny scoping. | Dashboard.jsx:87-95 |
| m4 | MegaAppLauncher lists desks in FDD module order (Phase 0 → 0.5 → 1 → 2 → **6** → 3 → 4 → 5 → 7 → 8) — Blocking appears before Assessment/Accounting/Registrar, mirroring the known FDD-numbering ≠ journey-order quirk inside the UI itself. | MegaAppLauncher.jsx:10-160 |
| m5 | `Staffusers::positionTitle` match arm says office 6 ⇒ "Chief of Safety & Security" but the offices table calls office 6 "Admission" — position titles for admission-office staff describe the wrong office. | Staffusers.php:105 vs DevReferenceDataSeeder.php:47 |
| m6 | Assessment/Show.jsx `outstanding = remainingBalance − totalPaid` double-subtracts (backend's `remainingBalance` already subtracts totalPaid). Latent only — totalPaid is always 0 there because payments never load (M6). | Assessment/Show.jsx:30 |
| m7 | Assessment/Show "Compute Assessment" button is effectively dead UI — the Show page only renders for existing assessments, whose charges exist, so the button is always disabled (correctly matching the backend idempotency check, but never usable). | Assessment/Show.jsx:393 |

---

## What's Done Right (verified)

- **Registrar/Show** — Approve button properly gated (`disabled={!allValid || form.processing}`, Show.jsx:175) and **hidden when enrolled**, replaced with an "Officially Enrolled by Registrar on…" banner (lines 171-188). No same-state 422 from this desk's primary action.
- **Admission/Show** — Approve/Reject rendered only when `admissionStatus === 'pending'` (line 187); requirement Upload → Verify/Reject gated by submission status (lines 372-401). CauseEffectModal used for both decisions.
- **Clearance/Index** — Stamp Approve/Waive/Reject only when pending (line 524); differentiated CauseEffectModal copy per action; ₱100 lost-slip flow with BR33 labeling.
- **Blocking/Show** — Assign button requires a schedule first (`disabled={sortedSchedules.length === 0}`, line 469); capacity enforced in the UI exactly matching the backend (submit disabled over capacity, checkboxes disabled at capacity, line 640, 680-694); the `subjects` field the modal reads is a computed attribute the controller injects (BlockingController:111) — no crash.
- **Assessment/Show** — Compute button disabled when charges exist (line 393), matching the backend idempotency check.
- **Accounting/Show** — Void button shown only for paid/completed payments (line 236-245) with a high-friction CauseEffectModal + explicit acknowledgement (lines 428-452); "Full Balance" quick-fill presets the exact outstanding amount.
- **Phase badges** — all 10 module pages carry consistent phase badges matching the storyboard model, and office badges verified against the seeder (Office 6 = Admission ✓, Office 8 = Clearance ✓, DevReferenceDataSeeder.php:47,49).
- **Staffusers `name`** — appended attribute exists (Staffusers.php:35-38, 59-64), so Registrar certificate signature lines and the cashier "Cashier In-Charge" column resolve correctly.
- **Routes** — every `route()` helper call in the audited pages resolves to a real named route (verified against routes/web.php), including `assessment.charges.adjust` (PATCH matches PATCH route).
- **Dashboard queueItems** — labels and routes all valid (Dashboard.jsx:86-95).
- **Strict BR1 queues** — Clinic/ID queue filters enforce "first pending workflow step belongs to MY office" via whereRaw subqueries — the strongest workflow-integrity enforcement in the system (ClinicController:36-39, IDController:38-41).

---

## Remediation Roadmap

**P0 — user-facing breakage (fix before any demo beyond the seeded script):**
1. **C1** — add "Generate Slip" + "Receive Completed Slip" actions on Clearance/Index (routes/gates already exist).
2. **C3** — one-line stepper fallback fix (`'current'` → `'pending'` + compute first-pending as current).
3. **C4a, C4d, M5, M7** — four one-line attribute-name/source fixes on Students/Show and Assessment/Show.
4. **M1** — enrollment-status guard in `AccountingController::record` + hide Collect form when balance 0.

**P1 — wrong/misleading displays:**
5. **C2** — extract WorkflowStepper into `Components/ui`, mount on Clinic/ID (and Accounting/Registrar) Show pages.
6. **C4c** — load `clinicrecords` + add section on Student 360 (or correct Demo-Day-Guide step 14).
7. **M6** — eager-load `payments` in `AssessmentController::show` + fix column keys.
8. **M3** — align dashboard queue counts with actual desk-queue definitions; swap magic numbers 5/11 for `OfficeId` enum constants.

**P2 — parity/polish:**
9. **M2** — overpayment warning/change tracking.
10. **M4** — validate all five checklist items in `approve()`.
11. **M8** — idempotency early-return in `finalize()`.
12. **m2–m7** — minor items as touched.

---

## Evidence Index

- State machine: `app/Services/EnrollmentStateMachine.php`
- Controllers: `Registrar/RegistrarController.php`, `Assessment/AssessmentController.php`, `Accounting/AccountingController.php`, `Blocking/BlockingController.php`, `Clearance/ClearanceController.php`, `DashboardController.php`, `Admin/UserManagementController.php`, `StudentController.php`
- Pages: `Registrar/Show.jsx`, `Assessment/Show.jsx`, `Accounting/Show.jsx`, `Blocking/Show.jsx`, `Admission/Show.jsx`, `Clearance/Index.jsx`, `Students/Show.jsx`, `Dashboard.jsx`, `Clinic/Show.jsx`, `ID/Show.jsx`
- Components: `Components/ui/StepProgress.jsx`, `Components/navigation/MegaAppLauncher.jsx`
- Models/enums: `Enrollments.php`, `Studentassessments.php`, `Staffusers.php`, `Courses.php`, `IdValidationStatus.php`, `OfficeId.php`
- Config: `routes/web.php`, `app/Providers/AuthServiceProvider.php`, `database/seeders/DevReferenceDataSeeder.php`
