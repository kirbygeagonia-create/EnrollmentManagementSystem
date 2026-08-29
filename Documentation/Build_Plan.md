# SEAIT Enrollment Management System — Master Build Plan

> [!NOTE]
> **Institutional Status & Completion Note**:
> Stages 1 through 5 of this system are **fully engineered, validated, and operational** (encompassing the 54-table relational architecture, 8-phase enrollment workflow, Spatie RBAC desk roles, reactive Inertia/React UI, and cause-effect safety confirmation models).
> 
> *The remaining advanced stages (Stage 6 and beyond) will be rolled out systematically as soon as the core system achieves full production readiness and satisfies all operational requirements of SEAIT.*

---

## Stage Progress Overview

| Stage | Title | Status | Description |
|---|---|:---:|---|
| **Stage 1** | **Database Schema & Architecture Foundation** | ✅ **COMPLETED** | 54 tables, 86 foreign-key constraints, strict enum validation, audit tables, indexes, and full relational model. |
| **Stage 2** | **Spatie RBAC & Security Infrastructure** | ✅ **COMPLETED** | 13 functional desk roles, 86 granular permissions, multi-guard session authentication, and audit trails. |
| **Stage 3** | **Core Enrollment Lifecycle Engine (Phases 0–8)** | ✅ **COMPLETED** | Complete 8-step workflow (Admissions, SCAT/Guidance, Clearance, Evaluation, Scholarships, Cashier/Payments, Registrar Official Gate, Blocking, Clinic, ID Hub). |
| **Stage 4** | **Enterprise UI/UX Design & App Launcher** | ✅ **COMPLETED** | Glassmorphism, Tailwind design system, MegaAppLauncher command center, phase-specific badging, responsive tables, and filters. |
| **Stage 5** | **Enterprise Safety, Modals & Print Services** | ✅ **COMPLETED** | Universal `CauseEffectModal.jsx`, destructive action guards, sign-out safety, high-fidelity Certificate of Registration (COR), Class Cards, and Subject Load printing. |
| **Stage 6** | **Institutional Org Structure Realignment** | 🚀 **IN PROGRESS** | College-exclusive Deans & Program Heads, 10+ dedicated staff per administrative office, Instructor/Faculty role, removal of mock offices. |
| **Stage 7** | **Advanced Institutional Analytics & Reporting** | ⏳ **PLANNED** | Real-time CHED UniFAST billing export, daily cashier collection summaries, enrollment pacing dashboards, retention rate metrics. |
| **Stage 8** | **Public Student Portal & Self-Service Expansion** | ⏳ **PLANNED** | Student portal for digital COR downloads, clearance tracking, grade viewing, and online pre-registration integration. |
| **Stage 9** | **Basic Ed (K-12) & TESDA Modular Integration** | ⏳ **FUTURE** | Extensible plug-and-play enrollment tracks for Senior High School strands and TESDA NC-II vocational competencies. |

---

## Detailed Breakdown of Stages

### Stage 1: Database Architecture (COMPLETED)
- Strict MySQL 8 engine with 54 interconnected tables and 86 foreign keys.
- Complete data dictionary covering `students`, `admissions`, `examresults`, `clearances`, `enrollments`, `assessments`, `payments`, `blockschedules`, `clinicrecords`, and `idrequests`.
- Zero nullable foreign key violations, cascade constraints where appropriate, and soft deletions on master catalogs.

### Stage 2: RBAC Security Infrastructure (COMPLETED)
- 13 functional desk roles: `SysAdmin`, `Admin`, `AdmissionOfficer`, `GuidanceStaff`, `DeptEvaluator`, `Dean`, `ProgramHead`, `ScholarshipOfficer`, `AccountingStaff`, `RegistrarDesk`, `RegistrarApprover`, `BlockingCoordinator`, `ClinicStaff`, `IdOfficer`.
- Module permissions scoped across intake, exam scoring, clearance sign-offs, load evaluation, fee computation, OR recording, blocking, health checks, and card releasing.

### Stage 3: Core Enrollment Engine (COMPLETED)
- **Phase 0 (Admission)**: Applicant intake, document checklist validation, transferee past-record capture.
- **Phase 0.5 (Exam & Guidance)**: SCAT entrance exam and board course retention exams.
- **Phase 1 (Clearance)**: Multi-office clearance periods, lost clearance slip payment tracking (₱100).
- **Phase 2 (Evaluation)**: Curriculum subject load builder, prerequisite checks, Dean evaluation signing.
- **Phase 3 (Scholarships & Assessment)**: Automated tuition fee computation, stacked scholarship grants, and charge adjustments.
- **Phase 4 (Cashier / Accounting)**: POS receipting, unique Official Receipt (OR) numbering, partial payments, and void handling.
- **Phase 5 (Registrar Gate)**: Pre-enrollment verification, final enrollment confirmation, and student data recording.
- **Phase 6 (Blocking & Scheduling)**: Visual block assignment, classroom matrix, conflict detection.
- **Phase 7 (School Clinic)**: Physical assessment, vital signs, PhilHealth registration verification.
- **Phase 8 (Student ID Hub)**: Photo validation, PVC mockup generation, QR security encoding, card release.

### Stage 4: Enterprise UI/UX & Design Tokens (COMPLETED)
- Unified Tailwind CSS theme incorporating SEAIT branding (`#FF6B35` / `#EA580C` orange palette).
- `MegaAppLauncher.jsx` for rapid workflow navigation across 13 desks.
- Filter bars, data tables with pagination, responsive action drawers, and role-based badges.

### Stage 5: Safety Guardrails & Print Services (COMPLETED)
- Universal `CauseEffectModal.jsx` integrated across all critical actions.
- Official printable documents conforming to CHED/Registrar standards (`PrintCertificate.jsx`, `PrintClassCards.jsx`, `PrintSubjectLoad.jsx`).

### Stage 6: Organizational Realignment & Faculty Roles (CURRENT FOCUS)
- Cleanse `offices` table of academic departments (Offices 17–21).
- Unlink Deans and Program Heads from administrative offices (`officeId = NULL`), binding them solely to `unitId`.
- Add `instructor` role to `StaffRole` enum and database.
- Seed at least 10 dedicated staff accounts per enrollment office.
- Verify BS Electrical Engineering and DCE college status.

### Stage 7: Analytics & Reporting (PLANNED)
- CHED UniFAST billing submission automation.
- Daily Cashier Summary with denomination breakdown.
- College load capacity utilization and enrollment pacing curves.

### Stage 8: Student Portal & Self-Service (PLANNED)
- Student self-service dashboard for viewing real-time enrollment status, COR downloads, and clearance progress.

### Stage 9: TESDA & K-12 Expansion (FUTURE SCOPE)
- Modular adapter to expand EMS beyond collegiate programs into K-12 (SHS/JHS) and TESDA NC-II certifications when mandated by institutional management.
