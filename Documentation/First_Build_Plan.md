# Enrollment Management System — Full Build Plan

*From project setup to production deployment. Scope: **full digitalization of the on-campus enrollment process** (Online Enrollment dropped), college level, centralized data, multi-role, scalable, and user-friendly for all age ranges.*

**Stack:** Laravel 11 (PHP 8.3) · Inertia.js + React + Tailwind CSS · MySQL 8 / MariaDB 10.11 · Redis · S3-compatible file storage · server-rendered PDFs (dompdf / Browsershot)

---

## Stage 0 — Project Setup & Foundations (Week 1)

### 0.1 Repository & environment
- [ ] Create the application repo (keep `EnrollmentSystem` as the DB/docs repo, or add an `app/` folder — recommended: separate repo `ems-app`).
- [ ] Local dev: **Laragon** (already in use — ships PHP, MySQL 8.4, Composer, Node; auto virtual hosts like `ems.test`). Add Redis via Laragon's package manager, or use `database` cache/queue drivers in dev and Redis in production only.
- [ ] `laravel new ems --breeze --stack=react` (Breeze + Inertia + React + Tailwind scaffold), or manual Inertia setup.
- [ ] Configure `.env`: DB connection, Redis (`CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=redis`), filesystem disk.
- [ ] Tooling: Laravel Pint (code style), Larastan (static analysis), Pest (tests), ESLint + Prettier (frontend), GitHub Actions CI (lint + test on every PR).
- [ ] Branch protection on `main`; feature-branch + PR workflow.

### 0.2 Import the existing database
- [ ] Create `ems` database, import `Documentation/enrollment.sql` (verified to import cleanly: 54 tables, 86 FKs — fifth-round verification Aug 2026).
- [ ] Configure Laravel to use the existing schema (models will map to current table/column names; no destructive re-migration).
- [ ] From here on, **every schema change is a Laravel migration** committed to the repo — the SQL dump becomes a historical baseline, migrations become the source of truth.

---

## Stage 1 — Database Finalization (Weeks 1–2)

### 1.1 Remove Online Enrollment from the schema
Online application/enrollment is out of scope; everything is processed on campus by staff.

| Change | Migration |
|---|---|
| `admissions.applicationMode` enum(`'faceToFace','online'`) | ✅ **Dropped Aug 2026** — existing 5,954 `'online'` rows converted to `'faceToFace'` first, then column dropped. |
| `enrollments.enrollmentMode` enum(`'faceToFace','online'`) | ✅ **Dropped Aug 2026** — existing 14,973 `'online'` rows converted to `'faceToFace'` first, then column dropped. |
| `documents` table | **Keep** — but reframe: staff scan/upload requirement documents at the Admission/Evaluation desks (digitalization of paper submissions), not student self-upload. |
| Student portal login (`students.username/passwordHash`) | **Keep the columns** (cheap, future-proof) but the build has **no student-facing portal** in v1 — students are subjects of the process, staff operate the system. A read-only student kiosk/viewer can be a later phase. |
| Docs updates | ✅ **Done Aug 2026** — `Database_Representations.md` ERD/phase grid + dialogue doc updated, `applicationMode`/`enrollmentMode` references removed, scope decision noted in both changelogs. |

### 1.2 Fixes
- [x] **Extend `enrollments.enrollmentStatus`** from `enum('pending','enrolled','dropped')` to `enum('pending','evaluated','assessed','paid','enrolled','dropped')` — required by the Stage 2 state machine. **Decision: extend the enum** (done Aug 2026; existing rows left as-is — `pending`/`enrolled`/`dropped` are all valid values of the new enum).
- [x] `clinicrecords.clinicRecordId` → add AUTO_INCREMENT.
- [x] Verify all money columns are `DECIMAL(10,2)`; all timestamps that need time-of-day are `DATETIME` — `payments.paymentDate` changed `date` → `datetime` (audit precision). Money columns confirmed `DECIMAL(10,2)`.

### 1.3 New tables (additions for the digital system)

**RBAC (replaces reliance on the `staffusers.role` enum):**
```
roles              (roleId PK, roleName UK, description)            -- e.g. AdmissionOfficer, GuidanceStaff, DeptEvaluator, Dean, AccountingStaff, RegistrarDesk, RegistrarApprover, BlockingCoordinator, ClinicStaff, IdOfficer, SysAdmin
permissions        (permissionId PK, permissionName UK, module)     -- e.g. admission.approve, exam.record, evaluation.sign, payment.record, enrollment.approve, block.assign, clinic.record, id.validate, print.certificate, refdata.manage, user.manage
role_permissions   (roleId FK, permissionId FK)  PK(roleId, permissionId)
staff_roles        (userId FK -> staffusers, roleId FK)  PK(userId, roleId)   -- a staff member can hold multiple roles
```
Keep `staffusers.role` temporarily for migration; backfill `staff_roles` from it, then drop the enum column once RBAC is live.

**Audit trail:**
```
auditlogs (auditId PK, userId FK -> staffusers, action, entityTable, entityId, oldValues JSON, newValues JSON, ipAddress, createdAt)
```
Populated automatically via model observers (or `owen-it/laravel-auditing`).

**Notifications (in-app, staff-facing):**
```
notifications (Laravel's built-in notifications table: id, type, notifiable morph, data JSON, readAt, createdAt)
```
Used for: "evaluation forwarded to Registrar", "payment recorded — ready for Registrar approval", "clearance period closing in 3 days".

**Enrollment status history (state-machine backing):**
```
enrollmentstatushistory (historyId PK, enrollmentId FK, fromStatus, toStatus, changedBy FK -> staffusers, remarks, changedAt)
```
Enforces BR12–BR14/BR16 with a full trace of every transition.

**System settings:**
```
settings (settingKey PK, settingValue, description)   -- e.g. current termId, enrollment window dates, clearance replacement fee, school header info for prints
```

### 1.4 Seeders & reference data
- [x] Seed roles/permissions and map the ~40 existing `staffusers` into `staff_roles` by their office + old role enum. (✅ Aug 2026: 13 roles, 13 permissions, 26 role_permissions mappings — SysAdmin gets all 13; 47 staff_roles covering all 40 staff — 7 admins hold SysAdmin, dept evaluators/deans mapped by department, 2 staff per Registrar/Accounting/Scholarship/Guidance/Clinic office.)
- [x] Settings seeded (active term, school name/logo/address/phone for print headers). (✅ Aug 2026: 5 settings — currentTermId=18, schoolName='SEAIT', schoolAddress='', schoolPhone='', clearanceReplacementFee=100.00.)
- [ ] Keep the 1.4M-row synthetic dataset in a **staging** database for load testing; production starts clean (or with migrated real data).

**Deliverable:** migrations for all of the above, regenerated ERD in docs, updated changelog. This is the **database finalization milestone**. ✅ **Stage 1 complete Aug 2026** — DDL/DML applied to live DB, dump regenerated (`Documentation/enrollment.sql`, 54 tables, 86 FKs) and re-import verified in a scratch DB, all docs (RepMD, dialogue, both docx, report) synced. Migrations still pending as the app repo doesn't exist yet — the dump remains the schema baseline until Stage 2 scaffolding.

---

## Stage 2 — Backend Core (Weeks 2–5)

### 2.1 Domain layer
- [ ] Eloquent models for all 54 tables with relationships mirroring the 86 FKs; casts for enums (PHP 8.3 backed enums per status field).
- [ ] **Enrollment state machine** service: allowed transitions only (`pending → evaluated → assessed → paid → enrolled`; `enrolledsubjects: proposed → confirmed → dropped`), each transition writes `enrollmentstatushistory` + fires events.
- [ ] **Workflow service**: creates the 8-step `enrollmentworkflow`+`workflowsteps` on enrollment creation; signs steps in `stepOrder` only (BR13/BR14); office-scoped — a Clinic user can only sign the Clinic step.
- [ ] Business rules as Policies/Validators: BR1–BR34 from the docs (uniqueness handled by DB, process rules in services, form-completeness BR32 as validation rules).

### 2.2 Auth & RBAC
- [ ] Staff auth (session-based, Breeze) with `spatie/laravel-permission` backed by the new tables.
- [ ] Middleware per module (`can:admission.approve` etc.); every controller action permission-gated.
- [ ] Password policy + bcrypt/argon2id hashing; account deactivation ties to `staffusers.status`.
- [ ] Login throttling, session timeout for shared desk computers, optional PIN re-confirm on signature actions (a "sign" = digital equivalent of the physical signature).

### 2.3 Phase modules (one Laravel module/controller group per phase)
| Module | Key operations | Guarded by |
|---|---|---|
| **Admission (Ph 0)** | Register applicant (students/addresses/guardians/backgrounds), create admission, record requirement submissions, staff scan-upload of documents, approve/reject | `admission.*` |
| **Exams (Ph 0.5 & retention)** | Record general (Guidance) + course-specific results; retention gate check for board-course continuing students | `exam.*` |
| **Clearance (Ph 1)** | Open/close clearance periods, generate slips (PDF), per-office approvals, desk receipt (receivedBy/receivedDate), lost-slip ₱100 replacement flow | `clearance.*` |
| **Dept Evaluation (Ph 2)** | Issue enrollment form, capture full profile (BR32 all-fields validation), credit transfer (transferee/shifter), regular/irregular standing, propose subject load from `curriculumsubjects` with prerequisite checks, evaluator + dean sign | `evaluation.*` |
| **Assessment (Ph 3)** | Auto-compute charges from `feetypes` (perUnit × units / flat), apply scholarships — refined BR19: **full (100%) scholarships are exclusive** (one full grant per student per term; School Grant is the default), **partial scholarships stack** up to the remaining balance/100% cap, produce `studentassessments` | `assessment.*` |
| **Accounting (Ph 4)** | Record payment (unique OR number), recalc balance, sign workflow step, daily collection report | `payment.*` |
| **Registrar (Ph 5)** | Validation gate (clearance copy present, payment done, evaluation signed), approve subjects (proposed→confirmed), enroll (new/old record-vs-update), print Certificate + Class Cards → `documentprintlog` | `enrollment.approve`, `print.*` |
| **Blocking & Scheduling (Ph 6)** | Manage blocks/schedules/meetings/rooms, capacity checks (`maxStudents`, room capacity), conflict detection (instructor/room/time overlap), assign students, print Block & Schedule | `block.*` |
| **Clinic (Ph 7)** | Record physical exam + PhilHealth, sign workflow step | `clinic.*` |
| **ID Office (Ph 8)** | ID request, photo upload, vendor tracking, QR generation (unique), release + validation | `id.*` |
| **Reference Data (admin)** | Courses, majors, curricula, subjects, prerequisites, terms/years, fee types, scholarship types, offices, rooms, requirement catalogs | `refdata.manage` |
| **User Management (admin)** | Staff accounts, role assignment, deactivation | `user.manage` |

### 2.4 Document generation (the print engine)
- [ ] HTML/Blade templates for: Clearance Slip, Enrollment Certificate ("Student Subject Load", with SEAIT ENROLLED stamp block), Class Card (one per subject), and Block & Schedule — matched against the 4 reference images in `Documentation/Images/` — plus the **Enrollment Form**, built from its field-by-field description in `Enrollment_System_Dialogue.md` Phase 2 (no reference image exists; photograph a blank physical form and add it to `Documentation/Images/` before building this template).
- [ ] **PDF engine: Browsershot (headless Chrome) as the primary** — needed for faithful complex layouts (logos, stamp block, precise certificate/class-card grids); dompdf only as a lightweight fallback for simple prints. One engine standard, fidelity-tested against the reference images in Stage 4.
- [ ] Rendered to PDF server-side; every print inserts a `documentprintlog` row (type, printedBy, documentNumber). Reprints allowed with log entries — full audit.
- [ ] Data pulled live at print time (no Excel in the loop). Excel/CSV exists only as **exports** (enrollment lists, collection reports, block rosters) via `maatwebsite/excel`.

### 2.5 Cross-cutting
- [ ] Queued jobs: PDF batch printing (e.g., all class cards for a block), report generation, notification fan-out.
- [ ] Audit observers on all transaction models → `auditlogs`.
- [ ] Nightly DB backup job + verified restore procedure.

**Deliverable:** fully permission-gated API/controllers for all 9 phases, tested with Pest (unit tests on services + feature tests per phase happy path and gate violations).

---

## Stage 3 — Frontend (Weeks 4–8, overlaps backend)

### 3.1 Design system — usable for every age range
Staff users range from young clerks to senior faculty; design for the least tech-comfortable user:
- **Large touch/click targets** (min 44px), **16px+ base font**, high-contrast palette (WCAG AA), no low-contrast gray-on-gray.
- **One task per screen** — wizard-style flows that mirror the physical process staff already know (the screens are the digital version of the paper forms).
- **Plain language labels** matching school terminology ("Clearance Slip", "Subject Load", "Block") — never technical jargon; Filipino-English mixed labels where natural.
- **Big status colors + icons**: pending (amber), completed (green), rejected (red) — never color alone (icons + text for color-blind users).
- **Search-first**: every desk screen starts with a big student search (name / school ID / QR scan).
- **Keyboard-friendly** for power clerks (Enter-to-advance forms) but fully mouse-operable.
- **Confirmation dialogs** for irreversible actions (enroll, sign, reject) with clear consequence text; undo where safe.
- **Empty states and inline help** ("No clearance period open — open one in Settings") instead of cryptic errors.
- Build as a small component library first (Button, StatusBadge, FormField, StudentSearchBar, PhaseStepper, SignatureConfirm) — every module reuses it, guaranteeing consistency.

### 3.2 Screens per role (role-scoped dashboards)
| Role | Home screen shows |
|---|---|
| Admission Officer | Applicant queue (pending/approved), new-applicant wizard, requirement checklist per applicant |
| Guidance / Dept exam staff | Exam recording screen, pass/fail lists |
| Dept Evaluator / Dean | Evaluation queue, enrollment-form wizard (profile → credits → subject load → sign) |
| Accounting | Payment desk screen (search student → assessment → record OR → print receipt), daily collections |
| Registrar (desk) | Clearance receiving screen (scan/search → mark received) |
| Registrar (approver) | Approval queue with validation checklist auto-computed (clearance ✓ payment ✓ evaluation ✓), one-click print certificate + class cards |
| Blocking Coordinator | Block manager (capacity bars, drag/assign students), schedule conflict warnings, block print |
| Clinic | Physical exam form (large numeric inputs), PhilHealth capture |
| ID Officer | Request queue, photo capture, QR generate/validate (webcam scan) |
| Admin | Reference data CRUD, user/role manager, settings, audit log viewer, dashboards |

### 3.3 Shared UX features
- **Student 360 view**: one page showing a student's entire enrollment trail (phase stepper: where they are, what's missing) — usable by any staff to answer "what do I still need?".
- **Phase stepper component** visualizing the 8-step workflow with signed/pending states — the digital twin of the paper workflow form.
- Real-time-ish queue counts (polling) so offices see incoming work.
- In-app notification bell backed by the notifications table.

**Deliverable:** all role dashboards + phase screens, responsive (desks use desktops; clinic/ID may use tablets), passing an accessibility check (axe).

---

## Stage 4 — Integration, Testing & Data (Weeks 8–10)

- [ ] **End-to-end walkthroughs** for all 4 student paths against staging (with the 30k-student synthetic dataset): First-Year (with 2-stage entrance exam), Continuing (with retention exam + clearance), Transferee (credits), Shifter (credits within school).
- [ ] Load test the heavy screens (approval queue, block rosters) at synthetic-data volume; add composite indexes where slow (e.g., `enrollments(termId, enrollmentStatus)`, `enrolledsubjects(enrollmentId, status)`).
- [ ] Print fidelity check: printed PDFs vs the physical reference forms in `Documentation/Images/`, verified by the Registrar.
- [ ] Security pass: permission matrix test (every route × every role), SQL injection/XSS scan, session handling on shared PCs.
- [ ] UAT with real staff from each office — one session per role; collect and fix usability findings (this is where the age-range-friendliness is proven, not assumed).
- [ ] Real data migration plan: import actual student/curriculum data (from registrar records/spreadsheets) via one-time seeder scripts with validation reports.

---

## Stage 5 — Deployment & Go-Live (Weeks 10–12)

- [ ] **Production server: on-campus Linux server as PRIMARY** (Ubuntu LTS, nginx + php-fpm, MySQL 8, Redis, supervised queue workers) — enrollment must keep working even if campus internet drops, so the system lives on the LAN. Off-site (VPS/cloud) is the **backup target**, not the primary. Provision via Docker Compose or scripted setup.
- [ ] HTTPS on the LAN: internal CA certificate distributed to office PCs (or Let's Encrypt on a school domain with split-horizon DNS when internet is up).
- [ ] Automated nightly backups (DB dump + uploaded files) to a second location; quarterly restore drills.
- [ ] Monitoring: uptime check, Laravel error tracking (Sentry/Flare), slow-query log.
- [ ] **Pilot**: run one department's enrollment digitally in parallel with paper for one term-start; compare, fix, then roll out school-wide.
- [ ] Training: 1-page illustrated quick guides per role (screenshots, big text) + short hands-on sessions; designate one "super user" per office.

---

## Stage 6 — Modern innovations (post-launch roadmap, in value order)

1. **QR-driven desk flow** — the student ID / a printed queue slip QR is scanned at every desk to pull up the student instantly (you already have unique QR codes in `studentids`).
2. **Queue management** — number dispenser + wall display per office during enrollment week; ties into the phase stepper.
3. **SMS/email notices** — "You are now officially enrolled", "Clearance period closes Friday" (Semaphore/Twilio for PH SMS).
4. **Analytics dashboards** — enrollment velocity per day, bottleneck office detection (avg time between workflow steps), block fill rates, collections.
5. **Read-only student kiosk/portal** — students check their own status/requirements (reuses the kept `students.username` credentials); a stepping stone if Online Enrollment ever returns to scope.
6. **Grade module integration** — `enrolledsubjects.grade` + `gradescale` are already in the schema; a term-end grade-entry module makes retention-exam eligibility and regular/irregular evaluation automatic.
7. **Archival/partitioning** — when clearance/approval tables approach ~10M rows, partition by `termId` or archive closed terms.

---

## Timeline summary

**Scope guard for a fixed (e.g., capstone) deadline** — the 12-week/2–3-dev estimate is aggressive; solo ≈ 24 weeks. Must-have cut list if time runs short: Stages 0–2 complete + frontend for the core desk flow only (Evaluation → Accounting → Registrar approval + certificate/class-card printing) + Admission and Clearance modules. Defer without breaking anything: Clinic and ID screens (record on paper, encode later), Blocking UI polish (assign via simple forms), analytics dashboards, notifications, and ALL of Stage 6. Never cut: RBAC, the state machine, `documentprintlog`, backups.

| Weeks | Stage |
|---|---|
| 1 | Setup + repo + tooling + DB import |
| 1–2 | Database finalization (online-enrollment removal, fixes, RBAC/audit/notifications/history/settings tables, seeders) |
| 2–5 | Backend core (state machine, RBAC, 9 phase modules, print engine) |
| 4–8 | Frontend (design system, role dashboards, phase screens) |
| 8–10 | Integration testing, load testing, UAT, data migration prep |
| 10–12 | Deployment, pilot, training, go-live |

Team assumption: 2–3 developers (1 backend-lean, 1 frontend-lean, 1 flex) + the registrar/office staff as domain reviewers. Solo development roughly doubles the timeline.
