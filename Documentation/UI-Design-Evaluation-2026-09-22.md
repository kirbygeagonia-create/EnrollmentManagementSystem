# SEAIT EMS — Frontend Design Evaluation

**Date:** 2026-09-22
**Method:** `frontend-design` skill review (plan → evaluate against the brief → critique), plus a functional error sweep
**Scope:** every surface in `resources/js` — tokens, components, layout, navigation, copy — and the backend paths the UI depends on

---

## Verdict

**The system is coherent, disciplined, and demo-ready.** It has a real identity — the SEAIT orange + navy institutional palette, self-hosted Figtree, a phase-vocabulary navigation that mirrors the actual enrollment pipeline — and it avoids most of the generated-design tells. What weaknesses remain are at the token layer (raw Tailwind palette dominance, over-granular radius/shadow scales) and in two navigation colour collisions. Every functional defect surfaced by the three review agents is now fixed and regression-tested.

**Gates against the final tree: Pest 274/274 · PHPStan 0 errors · Pint clean · ESLint clean · Vite build ✓**

---

## 1. Token layer

### Color — strong where it matters, noisy at the edges

| Signal | Count | Read |
|---|---|---|
| `slate` (raw Tailwind) | 607 | The de-facto neutral — used everywhere, including where the `brand` token was the intent |
| `emerald` / `amber` / `indigo` / `blue` / `rose` / `red` | 110 / 95 / 56 / 44 / 41 / 22 | Mostly semantic-adjacent, but raw |
| Semantic tokens (`danger` / `success` / `warning` / `info`) | 100 / 48 / 25 / 23 | Used where it counts: errors, holds, banners |
| `seait` (the accent) | well-represented | The brand carries: buttons, active states, focus rings |

The balance is acceptable for a working admin system: the semantic tokens carry the meaning that matters (danger 100 uses is right for a system whose error states must never be missed), and the raw palettes are doing texture work. **Not a defect — but the single largest future win would be a `slate → brand` consolidation sweep**, since the navy token already exists and 607 raw slate utilities dilute it.

### DeskSubNav — two exact colour collisions

12 desks are colour-differentiated by 9 hues, and two pairs collide exactly:

- **Admissions Desk = Cashier Desk** — both `bg-emerald-500/10`
- **Registrar = Student 360 Trail** — both `bg-seait-500/10`
- Near-duplicate: **ID Office** (`slate-500`) vs **System Admin** (`slate-700`)

The phase vocabulary itself is correct: Phase 0 → Phase 8 tracks the real pipeline order (Admissions → Guidance Exam → Clearance → Evaluation → Assessment → Accounting → Registrar → Blocking → Clinic → ID), so the numbered markers are justified — content that is genuinely a sequence.

### Radius & shadow — over-granular

- Radius: `xl` 93, `lg` 37, `2xl` 36, `card` 20, `md` 3 — five near-neighbours where three would do.
- Shadow: 9 distinct steps.

Both scales work, but the 1rem `rounded-card` token (the design system's own answer) is only the 4th most-used. A future pass could collapse `2xl`/`card` and prune the shadow scale.

---

## 2. Typography

Type scale collapses hard to two sizes:

| Size | Uses |
|---|---|
| `text-xs` | 219 |
| `text-sm` | 190 |
| `text-base` | 12 |
| `text-lg` | 19 |
| `text-2xl` | 10 |
| `text-xl` | 9 |
| `text-3xl` / `text-4xl` | 2 / 1 |

88.5% of all type is `text-xs` or `text-sm`. That is right for a high-density data workstation (the codebase calls itself exactly that), and the hero KPI row carries the large sizes where they matter. **Fixed this pass:** Figtree 700 and 800 are now self-hosted (`latin` + `latin-ext` woff2), so `font-bold` (179 uses) and `font-extrabold` render real weights instead of the browser faux-bolding the 600 file — offline-safe, no CDN dependency.

**Tell check — 51 `uppercase tracking-*` eyebrow labels and 105 `font-mono` uses.** Both are on the skill's default-tells list. Here they are legitimate: the eyebrows label real structural sections (card headers, table column heads — `stat-card-label` is uppercase by design), and the mono face carries data values (ID numbers, amounts, timestamps) where alignment matters. Numbered markers: 0 (correct — content is labelled by structure, not decorated with 01/02/03). No `→` appended to link text (0 uses).

---

## 3. Component layer — consolidated this pass

- **`Components/ui/statusLabel.js`** is now the one source of truth for enrollment-status presentation. Before it, four pages each carried their own tone map (three missing `returnedToEvaluation`) and three different capitalisers, so the same status rendered as `returnedToEvaluation`, `ReturnedToEvaluation`, and `Returnedtoevaluation`. All call sites now route through the shared module — including the Dashboard's hand-rolled inline map, which this evaluation caught still bypassing it.
- **Badge `accent` tone** now exists (`badge-accent`, SEAIT orange per the tailwind config) — 14 files referenced it and silently fell back to neutral before.
- **StatCard `accent` iconBg** now renders SEAIT orange, not indigo — a colour that appeared nowhere else in the system.
- **`prefers-reduced-motion`** is honoured app-wide in `@layer base` — the accessibility floor was missing before.
- **Submit vocabulary:** 12/13 ReferenceData pages branch `Update`/`Create`; `ClearanceRequirements.jsx` is create-only (no edit path exists), so its bare `Create` is correct, not an outlier. Flash messages are consistently past-tense (`X created.` / `X updated.` / `X deleted.`), matching the button that produced them.

---

## 4. Error sweep — everything green

| Check | Result |
|---|---|
| Route references: 149 `route()` calls in JSX → Laravel route list | **0 dangling** — every one resolves |
| GET routes absent from JSX | All reachable via nav configs (`route:` keys); `password.reset` / `sanctum.csrf-cookie` / `storage.local` are framework defaults |
| ESLint (`resources/js`, `--max-warnings=0`) | Clean |
| PHPStan | 0 errors |
| Pint | Clean |
| Vite production build | ✓ (the Figtree `/fonts/...` absolute-URL warnings are pre-existing and benign — Vite leaves them unresolved by design for the offline requirement) |
| Pest | **274/274** (1,474 assertions; 5 skip-guards wait on Laragon's MySQL) |

---

## 5. Functional defects found by the review agents — all fixed

1. **`WorkflowService.php` — Admin bypass of office scoping (highest severity).** The shared signing path explicitly allowed the `Admin` role to sign *another office's* workflow step, contradicting the write-boundary documented in four other places. Only `SysAdmin` retains the bypass now. Currently unreachable via routes (every HTTP path authorizes first), but it was the one place the invariant was inverted.
2. **Dashboard progress rows 403'd for a pure Admin.** Completed enrollments linked to `registrar.show`, whose policy requires `enrollment.approve` — a permission Admin lacks. Rows now link to `students.show` (Student 360), which shares the exact `students.view` permission that gates the payload — the UI gate, the payload gate, and the link target are now one permission.
3. **Dashboard payload over-shipped student PII.** The data went to anyone holding `students.view` while only admins could see the tab. Now gated to Admin **and** `students.view`.
4. **`AuditLogObserver` flagged self-service writes.** An Admin editing their own name/email produced an `adminOverride=true` row — a false positive diluting the marker. Self-service account writes (actor = subject) are now excluded.
5. **Duplicated, diverging college-logo heuristics.** `Evaluation/Show` and `Students/Show` carried different keyword sets, so the same course rendered different crests, and both hardcoded college names that the seeder guards. Consolidated into `officeBranding.js` keyed to the seeded official `Academicunits` IDs — the college *name* is now backend data (`course.unit.unitName`), so the UI can't drift from the baseline again.
6. **Stale "8-step" copy** in 6 places (comments, subtitles, launcher description) — the workflow is 6–7 steps (7 for firstYear/transferee, 6 otherwise). Corrected.
7. **Regression test added:** `test_admin_role_denied_mutating_routes` — a pure Admin (no SysAdmin) account must be denied on every mutating route. A 200 would mean the write-boundary is bypassed. 12 new assertions, all passing.

---

## 6. What remains (deliberate, not defects)

- **Admin tab has no URL state** — `selectedCategory` is component state, so the System Admin tab can't be deep-linked or survive a refresh. Promoting it to a named route + page is a real change (new route, new page, nav updates); not done in this pass.
- **`adminOverride` index is unused** — nothing filters by it in the Audit Logs UI, so the flag can't be reviewed as a set. Adding a filter is a small follow-up.
- **The mechanism is dormant in the seeded system** — every admin account carries SysAdmin + Admin, so no real account exercises the read-everywhere Admin role or ever gets flagged. The new regression test is the only thing that exercises it.
- **`laravel.log` holds 646 historical ERROR lines** from development — not a live defect.

---

*Nothing from this pass is committed. Commits remain gated on an explicit instruction.*

> **Status update (2026-09-24):** this pass was committed in `ced3b0a` and follow-ups in `163f4df` / `f52d510`; the remaining items in §6 are still open by design. The verdict and findings above reflect the 2026-09-22 tree.
