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
- **Pest** (backend tests): 216/216 passing
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

## Continuation State (updated Aug 29, 2026)

**Stack:** Laravel + Inertia/React (resources/js/Pages), MySQL 8 via Laragon.
PHP binary: `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`.
Env: OneDrive path, `EMS_DATABASE=ems` (E2E tests run against real MySQL DB).

**Full code audit remediated (commit `4315a69`, Aug 29 2026) — see
`Documentation/Audit-Remediation-2026-08-29.md` and `DEPLOYMENT.md`. Key
facts new code MUST respect:**
- Public `/register` is REMOVED. Staff accounts only via Admin → User
  Management or `php artisan ems:create-admin` (first-deploy bootstrap).
- Student 360 / quick-search require the seeded `students.view` permission
  (StudentPolicy). Bare `Staff` and `Instructor` roles do NOT have it.
  Frontend must consult shared Inertia prop `can.studentsView`.
- Office IDs: use the `App\Enums\OfficeId` backed enum — never bare ints —
  in policies/gates/seeders (Registrar=1, Accounting=2, Scholarship=3,
  Guidance=4, Blocking=5, Admission=6, Clinic=11, IdOffice=22).
- `blocking.assignStudents` is office-scoped: only Blocking office (5)
  users (BlockingCoordinator/OfficeHead) or SysAdmin/Admin pass.
- `submitRequirement` enforces `mimes:pdf,jpg,jpeg,png,doc,docx`.
- SecurityHeaders middleware runs on every web response.
- Record timestamps (`created_at/updated_at`, nullable) exist on students,
  admissions, enrollments, payments, studentassessments, studentclearances,
  clinicrecords, idrequests; `$timestamps = true` on those models only.
- CI: `.github/workflows/ci.yml` (Pest+PHPStan+Pint on SQLite; Pest on
  MySQL 8; ESLint+build). Do not merge red.
- Migrations with raw MySQL SQL MUST guard on `DB::connection()
  ->getDriverName() !== 'mysql'` (pattern used by realign/add-auto-increment/
  widen-enum migrations) — the SQLite suite depends on it.

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

**Deployment note:** production needs `npm install puppeteer` (Browsershot
PDFs) plus the full checklist in `DEPLOYMENT.md` (APP_DEBUG=false,
SESSION_SECURE_COOKIE=true, queue worker under Supervisor, HTTPS).

**Next workstream (user direction): UI/UX and frontend polish.** Screens are
Inertia React pages under `resources/js/Pages/` (Registrar, Blocking, Evaluation,
Clearance, Admission, Assessment, Clinic, ID, Exam, Accounting, Student, Admin).
Use @designer for visual/interaction work; @oracle only for review gates.
Route UI/UX validation to @designer, not orchestrator.

---

*This file is loaded via `opencode.json` → `instructions: ["AGENTS.md"]`*