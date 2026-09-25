# SEAIT EMS — Forms UX Audit (Input-Control Fit)

**Date:** 2026-09-22
**Method:** full sweep of every `<Select>` / `<select>` / `RadioCards` usage under `resources/js/Pages`, classified per the control-fit test
**Scope:** control choice only — "does the input widget fit the data" (`UI-Workflow-Conformance-Audit-2026-09-17.md` scoped Exams and input controls out; this is new ground)

---

## The principle

Cardinality is the test, not "dropdown vs. not":

- A **2–4 option, mutually-exclusive, short-label** field (Pass/Fail, Active/Inactive, Male/Female) forces an open-scroll-select cycle for no benefit — wrong on forms built for rapid keyboard-driven data entry. The right control is the reusable **`RadioCards`** primitive (`Components/ui/RadioCards.jsx`) — a segmented radio pair/row, not literal checkboxes (pass/fail is mutually exclusive, so combinable checkboxes would mis-state it).
- A **large or dynamic list** (courses, terms, subjects, staff, offices) is correctly a searchable `<Select>` — keep.
- **Filters** keep `<Select>` even at 2–4 options: a filter needs an "All" (empty) option that a radio group can't express cleanly.

---

## Verdict

**The system is conformant on the main-prompt B2 list — all six confirmed instances are resolved** (five converted to `RadioCards`, one — Terms status — resolved by the field not existing as an editable dropdown at all: status is computed from term dates, so there is no inappropriate control to replace; only the filter remains, which correctly keeps its "All Statuses" option).

The sweep found **one leftover** from the B2 pass — the Clearance Periods *create* form's Open/Closed status was still a `<Select>` while the *edit modal* for the same field had been converted — now fixed. It also found four same-class instances on the Admission form and a consistency cluster of native unstyled `<select>` tags, documented below as the remediation roadmap. Every filter and every dynamic-list Select is correctly left alone.

**Gates after the fix: Pest 274/274 · ESLint clean · Vite build ✓**

---

## Critical (P0)

| # | Finding | Evidence | Status |
|---|---|---|---|
| C1 | **Same field, two controls in one file.** The Clearance Periods create form's `periodStatus` (Open/Closed — 2-option, mutually exclusive, data entry) was a `<Select>` while the edit modal for the same field had been converted to `RadioCards` in the B2 pass. A user creating a period cycles a dropdown, then editing the same period uses a radio pair. | `resources/js/Pages/Clearance/Periods.jsx:344` (create — was `<Select>`) vs `:409` (edit — `RadioCards`, "Item 13" comment) | **Fixed this pass** — create form now `RadioCards` with the same `tone: 'success'` on Open, matching the edit modal exactly |

---

## Medium (P1)

| # | Finding | Evidence | Recommendation |
|---|---|---|---|
| M1 | **Admission form 2–4-option fields still `<Select>`.** Civil Status (4), Applicant Type (4) are mutually-exclusive short-label fields on the system's longest rapid-data-entry form — exactly the cycle the principle names. | `resources/js/Pages/Admission/Create.jsx:349` (Civil Status: single/married/widowed/separated), `:778` (Applicant Type: firstYear/transferee/continuing/shifter) | Convert to `RadioCards` (2-col), same pattern as the already-converted Gender field at `:291` |
| M2 | **Native unstyled `<select>` in the Generate Clearance form.** Two native selects for dynamic lists (student, clearance period) break visual consistency with the styled `Select` component used everywhere else — different borders, radii, and focus rings on the same desk. | `resources/js/Pages/Clearance/Index.jsx:435`, `:450` | Swap to the styled `Select` component (cardinality is fine; the control styling is the finding) |
| M3 | **Native `<select>` in ID issuance and Assessment modals.** Blood Type (ID) and Scholarship Type (Assessment) render as raw native selects. Scholarship Type is a dynamic list (cardinality fine); Blood Type is 8 options (above the 2–4 threshold, also fine) — both are styling inconsistencies, not control-fit defects. | `resources/js/Pages/ID/Show.jsx:487` (blood type), `resources/js/Pages/Assessment/Show.jsx:355` (scholarship type) | Swap to styled `Select` for consistency |

---

## Minor (P2)

| # | Finding | Evidence | Recommendation |
|---|---|---|---|
| m1 | **Admission form 3-option fields still `<Select>`** — Address Type (3, repeated per address row), Guardian Relationship (4, repeated per guardian row). Same class as M1 but lower frequency per form load (repeated rows make full conversion more visual space). | `resources/js/Pages/Admission/Create.jsx:449`, `:581` | Convert to `RadioCards` alongside M1, or keep if row-space wins — one decision for both |
| m2 | **Payment Method (3-option) is a native `<select>` on the Accounting payment modal** — a data-entry field at exactly the threshold, in a workflow action modal rather than a rapid-entry grid. | `resources/js/Pages/Accounting/Show.jsx:401` | Convert to `RadioCards` with the styled Select's polish, or keep — low traffic either way |
| m3 | **5-option fields kept as `<Select>` (correct per cardinality, noted for the record).** Institution Type and Level Completed on the Admission form (5 each, repeated per background row), Year Level on the Blocking form (5), Staff Role (6 roles with conditional N/A placeholders). | `resources/js/Pages/Admission/Create.jsx:678`, `:705`; `resources/js/Pages/Blocking/Index.jsx:319`; `resources/js/Pages/Admin/UserManagement/Index.jsx:489` | Keep — above the 2–4 threshold |

---

## Remediation Roadmap

| Phase | Items | Effort |
|---|---|---|
| **P0** | C1 — Clearance Periods create-form status | ✅ Done (this pass) |
| **P1** | M1 (Admission Civil Status + Applicant Type), M2–M3 (native-select consistency swaps) | Small — all reuse the existing `RadioCards`/`Select` primitives |
| **P2** | m1–m3 | Optional — one design decision (space vs. consistency) then mechanical swaps |

---

## Evidence Index — every select usage under `resources/js/Pages`

### Converted to `RadioCards` (10 sites)

| File:line | Field | Shape |
|---|---|---|
| `Admission/Create.jsx:291` | Gender | radio pair (2) |
| `Admin/ReferenceData/CurriculumSubjects.jsx:228` | Year Level | segmented row (3) |
| `Admin/ReferenceData/FeeTypes.jsx:238` | Unit Basis | radio pair (2) |
| `Admin/ReferenceData/Subjects.jsx:244` | Subject Type | segmented row (3) |
| `Admin/ReferenceData/Terms.jsx:251` | Semester | segmented row (3) |
| `Admin/UserManagement/Index.jsx:496` | Status (create) | radio pair (2) |
| `Admin/UserManagement/Index.jsx:664` | Status (edit) | radio pair (2) |
| `Clearance/Periods.jsx:409` | Status (edit modal) | radio pair (2) |
| `Clearance/Periods.jsx:344` | Status (create form) | radio pair (2) — **this pass** |

### Keep as `<Select>` — filters (need the "All" option)

| File:line | Field | Options |
|---|---|---|
| `Admission/Index.jsx:6` | status | pending/approved/rejected + All |
| `Admin/ReferenceData/AdmissionRequirements.jsx:7,24` | appliesTo, status | 4–6 + All |
| `Admin/UserManagement/AuditLogs.jsx:6` | action | 5 + All |
| `Clearance/Index.jsx:331,340` | period, status | dynamic + All |
| `Courses.jsx:157` | unit | 6 + All |
| `Exam/Index.jsx:211` | exam type | types + All |
| `Exam/Results.jsx:6` | result | pass/fail + All Results |
| `FeeTypes.jsx:7` | unit basis | 2 + All |
| `Majors.jsx:141` | course | dynamic + All |
| `Subjects.jsx:7` | type | 3 + All |
| `Students/Index.jsx:6` | status | 4 + All |
| `Terms.jsx:172,181` | semester, status | 3 + All, 2 + All |
| `Admin/UserManagement/Index.jsx:323,332` | office, status | dynamic + All |
| `Blocking/Index.jsx:214,223,232` | course, term, year level | dynamic + All |

### Keep as `<Select>` — dynamic/large data-entry lists

| File:line | Field | Why |
|---|---|---|
| `Admission/Create.jsx:338,756,767` | religion, course, term | dynamic backend lists |
| `Admission/Create.jsx:678,705` | institution type, level completed | 5 options (m3) |
| `Blocking/Index.jsx:303,311,319` | course, term, year level | dynamic / 5 options |
| `Blocking/Show.jsx:704,716,727,741,849` | subject, instructor, room, day, schedule | dynamic |
| `Clearance/Periods.jsx:321` | term | dynamic |
| `Curriculums.jsx:205,216` | course, major | dynamic |
| `CurriculumSubjects.jsx:193,204` | subject, prerequisite | dynamic |
| `Admin/UserManagement/Index.jsx:396,411,489,564,579,657` | office, unit, role (create + edit) | dynamic / 6 roles |
| `Majors.jsx:199` | course | dynamic |

### Native `<select>` (consistency findings — M2/M3/m2)

| File:line | Field | Verdict |
|---|---|---|
| `Clearance/Index.jsx:435,450` | student, clearance period | swap to styled `Select` |
| `ID/Show.jsx:487` | blood type (8) | swap to styled `Select` |
| `Assessment/Show.jsx:355` | scholarship type | swap to styled `Select` |
| `Accounting/Show.jsx:401` | payment method (3) | convert or keep (m2) |
| `Evaluation/Show.jsx:581` | credited-to subject | dynamic list — swap to styled `Select` for consistency |

---

*Nothing from this pass is committed. Commits remain gated on an explicit instruction.*

> **Status update (2026-09-24):** this pass was committed in `ced3b0a`; the native `<select>` findings above (M2/M3/m2) were addressed in the same sweep. The findings reflect the 2026-09-22 tree.
