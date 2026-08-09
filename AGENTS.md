# Project Instructions — SEAIT Enrollment Management System

## Mandatory Workflow Rule

**Every time a task is given to any agent (fixer, designer, explorer, librarian, oracle, etc.), the orchestrator MUST:**

1. **Commit all current changes** before dispatching the agent
2. **Push to remote** after the agent completes and gates pass
3. **Verify working tree is clean** before starting the next task

This ensures:
- No work is lost if an agent goes rogue (as happened with fix-13)
- Every change is traceable in git history
- Remote backup exists at every checkpoint
- Clean state for the next task

## Gate Requirements Before Push

All 5 gates must pass:
- **Pest** (backend tests): 214/214 passing
- **PHPStan** (static analysis): 0 errors
- **Pint** (code style): passed
- **ESLint** (frontend lint): 0 errors, 0 warnings
- **Vite build**: succeeds

## Agent Dispatch Discipline

- Use `background: true` for independent lanes
- Scope agents to specific files/folders (no overlapping write scopes)
- Reconcile results before advancing dependent work
- Never poll background tasks — wait for notification

## Stage Completion Checklist

Before marking a stage complete:
- [ ] All gates pass
- [ ] Working tree clean (committed)
- [ ] Pushed to remote
- [ ] Audit report documented

---

## Continuation State (saved Aug 09, 2026)

**Stack:** Laravel 11 + Inertia/React (resources/js/Pages), MySQL 8 via Laragon.
PHP binary: `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`.
Env: OneDrive path, `EMS_DATABASE=ems` (E2E tests run against real MySQL DB).

**Stage 4 (print fidelity + load test): COMPLETE code-side.**
- Commits: `82dad48` (print fidelity fixes), `fe261ad` (roster performance).
- Benchmarks (run: `php artisan ems:benchmark`): Blocking roster 1,133→34ms,
  Registrar queue →0.7ms, Blocking eligible 42ms (was 343ms + 1,275ms count).
- Print fidelity: `php artisan ems:print-fidelity` renders 9/9 PDFs to
  `storage/app/prints/fidelity/` from real DB data; templates in
  `resources/views/prints/`.

**Model facts new code MUST respect:**
- Staff relations are named `*User` (FK columns shadow the old relation names):
  `evaluatedByUser`, `registrarProcessedByUser`, `approvedByUser`, `receivedByUser`
  on Enrollments/Admissions/Clearanceapprovals/Studentclearances/Studentscholarships.
  Eager-load with those names. The bare FK columns (`evaluatedBy`, etc.) return ints.
- `Schedulemeetings.startTime/endTime` are plain STRINGS (no cast — casts break
  BlockingController conflict detection). Blades use
  `\Illuminate\Support\Carbon::parse(...)->format('H:i')`.

**Remaining (human-dependent, NOT code):**
1. Registrar compares fidelity PDFs vs `Documentation/Images/` references.
2. Real-data migration (needs registrar spreadsheets).
3. Per-office UAT walkthroughs.

**Deployment note:** production needs `npm install puppeteer` (Browsershot PDFs).

**Next workstream (user direction): UI/UX and frontend polish.** Screens are
Inertia React pages under `resources/js/Pages/` (Registrar, Blocking, Evaluation,
Clearance, Admission, Assessment, Clinic, ID, Exam, Accounting, Student, Admin).
Use @designer for visual/interaction work; @oracle only for review gates.
Route UI/UX validation to @designer, not orchestrator.

---

*This file is loaded via `opencode.json` → `instructions: ["AGENTS.md"]`*