# SEAIT EMS — Comprehensive UI/UX Audit & Remediation

**Date:** 2026-09-25 → 2026-09-27
**Method:** live Playwright walk of every desk (screenshots in `screenshots/audit-2026-09-25/`), code-level audit of every form/controller/modal, cross-module consistency review, full regression suite, static analysis
**Scope:** the complete product — every page, component, form, modal, workflow, and the backend paths they depend on

---

## Verdict

**The system is coherent and demo-ready.** The audit's biggest catch was not visual: a **component contract mismatch silently killed 35 select handlers across 14 files** — including the required Religion/Course/Term selects on the primary Admission form — every one of them dead without an error anywhere. A second hidden bug of the same family (**server validation feedback silently dropped on the two Create wizards**) was caught during final validation. Both families are fixed at the root, plus a 500 on `/admission`, and a set of module-boundary, aggregate, and idempotency corrections.

**Gates against the final tree: Pest 274/274 (1,638 assertions) · PHPStan 0 errors · Pint clean · ESLint clean · live sweep 43 pages, 0 non-200, 0 console errors**

**Rebuild note:** a Vite build ran 2026-09-26 13:21. Three JSX one-liners postdate it (the AuditLogs `handleFilter` adminOverride param and the two Create-form `onError` mappers) — run `npm run build` to put them live. Everything else (all PHP fixes) is live immediately.

---

## 1. Complete UI audit

Every desk walked live as its own role (admin, office6_head) with per-page screenshots; every page, form, modal, and select checked against the code behind it. Findings by area follow.

## 2. Information architecture

- **Shared current-term chip** — `HandleInertiaRequests` now computes "the term active today" once and shares it to every page, replacing per-page hardcoded term strings. Guarded with `Schema::hasTable('academicterms')` so un-migrated test environments don't 500 (found via the failing guest-redirect test).
- **Admin dashboard tab (Item 2)** — the System Admin tab swaps module tiles for a per-applicant progress view; deep-linkability verified live.
- **Frontend authorization flags (§2.2)** — `can.studentsView` shared from the middleware; `StudentPolicy` and the Desks & Apps mega-launcher gate on it, so the UI never offers a link the current user cannot actually use.

## 3. User processes / workflows

- **Module boundaries (Item 4)** — the Exam module is entrance examinations only; the retention exam is recorded and viewed in the Academic Evaluation area by the owning department (BR10). Desk-scoped index views: Guidance holds the School Entrance Exam, departments see their own course-specific/retention rows.
- **Registrar hold (Item 8)** — a paid enrollment can be returned to Evaluation; the hold is visible on the Registrar's own page; state machine tests cover paid ⇄ returnedToEvaluation ⇄ evaluated.
- **Approve ensures enrollment (SM-1)** — approving an admission guarantees an Enrollment record exists (no more applicants lost between modules).

## 4. Feature / module conflicts

The Item 4 boundary set above is enforced in policy (`EvaluationPolicy`, `ExamController` scopes) and covered by `ExamControllerTest` and `PermissionMatrixTest` — no desk can act on another desk's rows.

## 5. Interactions & feedback

- **CRITICAL — 35 broken select handlers across 14 files.** `ui/Select.jsx` calls `onChange(e.target.value)` (passes the selected VALUE string), but 35 call sites were written against the native-event contract (`(e) => form.setData('x', e.target.value)`) — each threw a TypeError on change and never updated state, with no visible error. Affected: Admission Create (religion/course/term, address type, relationship, institution type/level), Blocking Show (subject/instructor/room/day/schedule), UserManagement Index (office/unit/role), ID Show (reason/blood type), Clearance Periods (term), and all nine Reference Data forms. Fixed every call site to `(v) => form.setData('x', v)` and added a contract note on the component so the trap can't recur.
- **Dead validation feedback on the Create wizards (caught in final validation).** Both `Admission/Create` and `Exam/Create` submit via `router.post` but render `form.errors.*` — `useForm` only populates its errors through `form.post`, so every server validation message was silently dropped and the user got zero feedback on an invalid submit. Fixed with an `onError` mapper (`form.setError`); the other `router.X` pages already surface errors through their own `onError` state.
- **Finalized-guard error (Item 9)** — Blocking/Show renders the `schedule` error key instead of letting a blocked submit die silently.
- **Subject picker (Item 5)** — lives on its own page (Curriculums), not crammed into the curriculum form.
- **DataTable** null-safe cell renderer (no silent blank cell).

## 6. Loading / processing / transition states

- Credit-transfer intake panel (Item 6) renders existing credited subjects for transferee/shifter students.
- Schedule form submit buttons disable with progress text ("Adding…"/"Updating…") during in-flight saves.

## 7. Forms / validation / error handling

- **Backend mirrors the frontend allow-list (§3.4)** — requirement upload validation enforces the same `mimes:pdf,jpg,jpeg,png,doc,docx` the file picker accepts.
- **Real password policy** — staff creation enforces `Password::min(8)->mixedCase()->numbers()->symbols()`, not just length.
- **Idempotency (DI-1)** — assessment `compute` redirects with an info message instead of creating duplicates; `finalize` guards same-state re-clicks (previously a 422 from the state machine).
- **Prerequisite auto-gate & curriculum pinning (Item 7)** — a subject with an unmet curriculum prerequisite is flagged at proposal time; the enrollment stays pinned to the curriculum version it was evaluated against (`EvaluationPrerequisiteTest`).

## 8. Edge cases / hidden bugs

- **`/admission` 500** — `AdmissionController` referenced `AdmissionStatus` without the `use App\Enums\AdmissionStatus;` import (resolved to a non-existent class). Found by PHPStan; verified fixed live.
- **69 test failures from a ParseError** — an orphaned duplicated line in `AssessmentController` (left by an earlier edit). Removed; suite green.
- **Summary tiles described only the current page** — Admission/Assessment/Accounting index tiles now aggregate across the whole filtered set (`clone → reorder → selectRaw → groupBy`).
- **ID: one request per enrollment** — matches what the table shows.
- **Dashboard `currentTermId` setting may be unset** — null-safe.

## 9. Accessibility / responsiveness

Live walk at 1440×900 verified focus states and layout integrity across all desks; Badge tones standardized to the valid set (success/warning/danger/info/neutral + status tones). No blocking accessibility regressions found.

## 10. User convenience

- **Ctrl+K global student search** — verified live.
- **Audit-log override filter** — new filter (backend `when`-clause + frontend Select + `handleFilter` param) isolates oversight-role writes; the Admin Override badge (Item 3) marks them in the table.
- **Live DB data fixes** — 16 stray `'N/A'` suffixes cleared, two role descriptions corrected, one stale notification message fixed.

## 11. Preserved functionality

- **Staff destroy is safe (SEC-3)** — users with historical activity across 16 FK relationships are deactivated, not deleted; hard delete wrapped in try/catch fallback. Verified as correct, not changed.
- **Role/permission changes are audit-logged (SEC-4)** — the `AuditLogObserver` is glob-attached to every `app/Models/*.php` model, including Roles/Permissions/RolePermissions. Stale concern, resolved.
- **No duplicate assessments in live data (DI-2)** — verified clean.
- **Process correction (SEC-5)** — an attempted dead-route removal was **reverted**: the `evaluation.profile.capture` route is test-covered (E2E walkthrough ×4, permission matrix) and wired through controller, policy, and RbacSeeder. Restored via `git checkout` + RBAC re-seed (87 permissions × 14 modules). Lesson recorded: coverage greps must include `tests/`, and bulk edit patterns need anchors.

## 12. Final audit and validation

| Gate | Result |
|---|---|
| Pest / PHPUnit (sqlite :memory:) | **274/274 passed**, 1,638 assertions |
| PHPStan (larastan, level 5) | **0 errors** |
| Pint (PSR-12) | **clean** |
| ESLint | **clean** |
| Live route sweep (admin + office head) | **43 pages, 0 non-200, 0 console errors** |
| Select-contract live check | fixed handlers active; deep submit-feedback proof requires the rebuild above |
| Vite build | last run 2026-09-26 13:21 — **re-run to put the final 3 JSX one-liners live** |

**Remaining recommendations:** the `slate → brand` token consolidation sweep (from the 2026-09-22 design evaluation — still the largest future visual win), and the DeskSubNav colour collisions (Admissions = Cashier, Registrar = Student 360).
