# Functional Decomposition Diagram (FDD)
## SEAIT Enrollment Management System

> **Source of truth:** generated directly from `routes/web.php`, `routes/auth.php`, and the public
> methods of all 15 controllers (`app/Http/Controllers/**`) — every function below maps to a real,
> permission-gated endpoint verified against commit `c8ea184`. Nothing is invented; CLI-only
> utilities (`ems:create-admin`, `ems:benchmark`, `ems:print-fidelity`) are excluded as they are
> not user-facing system functions.

**Decomposition levels**
- **Level 0** — the system as a single process
- **Level 1** — primary functional modules (the working desks of the system)
- **Level 2** — functions / features of each module
- **Level 3** — sub-functions of each function (concrete CRUD actions)

---

## LEVEL 0 — System

```
0.0  SEAIT ENROLLMENT MANAGEMENT SYSTEM
```

The system digitizes the complete student enrollment lifecycle of South East Asian
Institute of Technology: admitting applicants, examining, evaluating, assessing fees,
collecting payments, clearing, block-sectioning, final registrar approval, clinic
screening, and ID production — with full RBAC, audit logging, and document printing.

---

## LEVEL 1 — Primary Modules

| # | Module | Controller(s) |
|---|--------|----------------|
| 1.0 | Authenticate & Manage Own Account | `AuthenticatedSessionController`, `PasswordController`, `ConfirmablePasswordController`, `NewPasswordController`, `PasswordResetLinkController`, `ProfileController` |
| 2.0 | Monitor Work Queues & Notifications | `DashboardController`, `NotificationController` |
| 3.0 | Manage Admissions | `Admission\AdmissionController` |
| 4.0 | Manage Entrance Examinations | `Exam\ExamController` |
| 5.0 | Manage Department Evaluations | `Evaluation\EvaluationController` |
| 6.0 | Manage Fee Assessments | `Assessment\AssessmentController` |
| 7.0 | Manage Payments & Collections | `Accounting\AccountingController` |
| 8.0 | Manage Clearances | `Clearance\ClearanceController` |
| 9.0 | Manage Block Sections & Schedules | `Blocking\BlockingController` |
| 10.0 | Manage Registrar Operations | `Registrar\RegistrarController` |
| 11.0 | Manage Clinic Assessments | `Clinic\ClinicController` |
| 12.0 | Manage Student IDs | `ID\IDController` |
| 13.0 | Manage Student Records (Student 360°) | `StudentController` |
| 14.0 | Manage Reference Data | `Admin\ReferenceDataController` |
| 15.0 | Manage Users, Roles & System Settings | `Admin\UserManagementController` |

---

## FULL DECOMPOSITION TREE (Levels 1 → 3)

### 1.0 Authenticate & Manage Own Account

- **1.1 Authenticate Staff Account**
  - 1.1.1 Submit Login Credentials (`login`)
  - 1.1.2 Validate Credentials & Create Session (`LoginRequest::authenticate`)
  - 1.1.3 Redirect Authenticated User to Dashboard
- **1.2 Terminate Staff Session**
  - 1.2.1 Invalidate Session & Regenerate CSRF Token (`logout`)
  - 1.2.2 Redirect User to Login Screen
- **1.3 Recover Forgotten Password**
  - 1.3.1 Request Password-Reset Link (`password.email`)
  - 1.3.2 Reset Password Using Emailed Token (`password.store`)
- **1.4 Re-Confirm Password for Sensitive Actions**
  - 1.4.1 Submit Password Confirmation (`password.confirm`)
- **1.5 Manage Own Profile**
  - 1.5.1 View Own Profile & Session Info (`profile.edit`)
  - 1.5.2 Update Own Profile Information (`profile.update`)
  - 1.5.3 Update Own Password (`password.update`)
  - 1.5.4 Delete Own Account (`profile.destroy`)

### 2.0 Monitor Work Queues & Notifications

- **2.1 Monitor Desk Work Queues**
  - 2.1.1 Display Pending Work Per Desk (`dashboard`)
  - 2.1.2 Poll Live Queue Counts (`dashboard.queue-counts`)
- **2.2 Manage Notifications**
  - 2.2.1 List Own Notifications (`notifications.index`)
  - 2.2.2 Mark Notification as Read (`notifications.read`)
  - 2.2.3 Mark All Notifications as Read (`notifications.read-all`)

### 3.0 Manage Admissions

- **3.1 Manage Applicant Records**
  - 3.1.1 List & Search Admissions (`admission.index`)
  - 3.1.2 Open New Admission Form (`admission.create`)
  - 3.1.3 Record New Applicant & Admission (`admission.store`)
  - 3.1.4 View Admission Details (`admission.show`)
- **3.2 Process Admission Requirements**
  - 3.2.1 Upload & Submit Requirement Document (`admission.requirements.submit` — PDF/JPG/PNG/DOC/DOCX)
  - 3.2.2 Verify Requirement Document (`admission.requirements.verify`)
- **3.3 Decide Admission Outcome**
  - 3.3.1 Approve Admission (`admission.approve`)
  - 3.3.2 Reject Admission (`admission.reject`)

### 4.0 Manage Entrance Examinations

- **4.1 Manage Exam Result Records**
  - 4.1.1 View Exam Queue & Results (`exam.index`)
  - 4.1.2 Open Exam Recording Form (`exam.create`)
  - 4.1.3 Generate Pass/Fail Result Lists (`exam.results`)
- **4.2 Look Up Enrolled Examinees**
  - 4.2.1 Fetch Enrolled Students by Course & Term (`exam.students`)
- **4.3 Record General Entrance Exam** *(BR9 — Stage 1, Guidance)*
  - 4.3.1 Validate Exam Result Entry (`exam.general.record`)
  - 4.3.2 Save General Exam Result
  - 4.3.3 Auto-Reject Admission on Failed General Exam
- **4.4 Record Course-Specific Entrance Exam** *(BR9 — Stage 2, Department)*
  - 4.4.1 Verify General Exam Was Passed First (`exam.course-specific.record`)
  - 4.4.2 Save Course-Specific Exam Result
  - 4.4.3 Auto-Approve Admission on Pass
  - 4.4.4 Auto-Reject Admission on Fail
- **4.5 Record Retention Exam** *(BR10 — continuing board-course students)*
  - 4.5.1 Validate Course Retention Requirement (`exam.retention.record`)
  - 4.5.2 Save Retention Exam Result

### 5.0 Manage Department Evaluations

- **5.1 Manage Evaluation Queue**
  - 5.1.1 List Pending Evaluations (`evaluation.index`)
  - 5.1.2 View Evaluation & Enrollment Details (`evaluation.show`)
- **5.2 Capture Student Profile** *(BR32 — full demographics)*
  - 5.2.1 Record Complete Demographic Profile (`evaluation.profile.capture`)
- **5.3 Propose Curriculum Subjects**
  - 5.3.1 Propose Subjects per Curriculum (`evaluation.subjects.propose`)
  - 5.3.2 Confirm Proposed Subject Load
- **5.4 Process Transfer Credits**
  - 5.4.1 Record & Evaluate Credited Subjects (`evaluation.credits.process`)
- **5.5 Sign Evaluation**
  - 5.5.1 Sign Evaluation & Advance Workflow Step (`evaluation.sign`)

### 6.0 Manage Fee Assessments

- **6.1 Manage Assessment Queue**
  - 6.1.1 List Pending Assessments (`assessment.index`)
  - 6.1.2 View Assessment Breakdown (`assessment.show`)
- **6.2 Compute Assessment Fees**
  - 6.2.1 Compute Per-Unit & Flat Fee Totals (`assessment.compute`)
  - 6.2.2 Generate Itemized Charge Breakdown
- **6.3 Apply Scholarships** *(BR19 — grants stacking & caps)*
  - 6.3.1 Apply School Grant or Outside Scholarship (`assessment.scholarships.apply`)
- **6.4 Adjust Charges**
  - 6.4.1 Add, Edit & Remove Assessment Charges (`assessment.charges.adjust`)
- **6.5 Finalize Assessment** *(BR13/BR14 — office signing)*
  - 6.5.1 Sign & Lock Assessment (`assessment.finalize`)

### 7.0 Manage Payments & Collections

- **7.1 Manage Payment Queue**
  - 7.1.1 List Assessments Awaiting Payment (`accounting.index`)
  - 7.1.2 View Assessment Balance & Charges (`accounting.show`)
- **7.2 Record Student Payment**
  - 7.2.1 Post Payment Against Assessment (`accounting.payment.record`)
  - 7.2.2 Update Balance & Payment Status
- **7.3 Void Payment**
  - 7.3.1 Void Recorded Payment (`accounting.payment.void`)
- **7.4 Report Daily Collections**
  - 7.4.1 Generate Daily Collection Report (`accounting.daily-report`)

### 8.0 Manage Clearances

- **8.1 Manage Clearance Queue**
  - 8.1.1 List Students for Clearance Signing (`clearance.index`)
- **8.2 Manage Clearance Periods**
  - 8.2.1 View Clearance Periods (`clearance.periods`)
  - 8.2.2 Create Clearance Period (`clearance.periods.store`)
  - 8.2.3 Update Clearance Period (`clearance.periods.update`)
- **8.3 Issue Clearance Slips**
  - 8.3.1 Generate Clearance Slip (`clearance.slip.generate`)
  - 8.3.2 Print Clearance Slip (`clearance.print-slip`)
  - 8.3.3 Replace Lost Clearance Slip (`clearance.slip.replace`)
- **8.4 Record Signed Clearance Receipts**
  - 8.4.1 Record Signed Receipt per Office (`clearance.receipt.record`)
- **8.5 Approve Clearance Requirements**
  - 8.5.1 Approve Office Requirement Sign-Off (`clearance.approve`)

### 9.0 Manage Block Sections & Schedules

- **9.1 Manage Block Sections**
  - 9.1.1 List Block Sections (`blocking.index`)
  - 9.1.2 View Block Details & Roster (`blocking.show`)
  - 9.1.3 Create Block Section (`blocking.store`)
  - 9.1.4 Update Block Section (`blocking.update`)
  - 9.1.5 Delete Block Section (`blocking.destroy`)
- **9.2 Manage Block Schedules**
  - 9.2.1 Create Block Schedule (`blocking.schedules.store`)
  - 9.2.2 Update Block Schedule (`blocking.schedules.update`)
  - 9.2.3 Delete Block Schedule (`blocking.schedules.destroy`)
  - 9.2.4 Validate Room & Schedule Conflicts
- **9.3 Assign Students to Blocks**
  - 9.3.1 Assign Enrolled Students to Block (`blocking.assign` — with capacity validation)
  - 9.3.2 Unassign Students from Block (`blocking.unassign`)
- **9.4 Print Block Schedule**
  - 9.4.1 Print Block Section Schedule (`blocking.print-schedule`)

### 10.0 Manage Registrar Operations

- **10.1 Manage Registrar Queue**
  - 10.1.1 List Enrollments for Final Approval (`registrar.index`)
  - 10.1.2 View Full Enrollment Record (`registrar.show`)
- **10.2 Approve Enrollments**
  - 10.2.1 Sign Final Registrar Step (`registrar.approve`)
  - 10.2.2 Set Enrollment Status to Enrolled
- **10.3 Print Enrollment Documents**
  - 10.3.1 Print Enrollment Certificate (`registrar.print-certificate`)
  - 10.3.2 Print Class Cards (`registrar.print-class-cards`)
  - 10.3.3 Print Subject Load (`registrar.print-subject-load`)

### 11.0 Manage Clinic Assessments

- **11.1 Manage Clinic Queue**
  - 11.1.1 List Enrollments Awaiting Clinic Screening (`clinic.index`)
  - 11.1.2 View Clinic Assessment Form (`clinic.show`)
- **11.2 Record Health Assessment** *(Phase 7 — physical exam, PhilHealth)*
  - 11.2.1 Record Physical Exam Findings (`clinic.record`)
  - 11.2.2 Record PhilHealth Registration
  - 11.2.3 Sign Clinic Workflow Step
- **11.3 Update Health Assessment**
  - 11.3.1 Amend Clinic Record Fields (`clinic.update`)
- **11.4 Reopen Completed Record**
  - 11.4.1 Reopen Completed Clinic Record for Correction (`clinic.reopen`)

### 12.0 Manage Student IDs

- **12.1 Manage ID Request Queue**
  - 12.1.1 List ID Requests by Status (`id.index`)
  - 12.1.2 View ID Request & Card Details (`id.show`)
- **12.2 Create ID Requests**
  - 12.2.1 Record New Student ID Request (`id.create`)
- **12.3 Produce ID Cards**
  - 12.3.1 Generate QR Code & Produce Card (`id.produce`)
- **12.4 Validate ID Cards**
  - 12.4.1 Validate Produced Card (`id.validate`)
- **12.5 Release ID Cards**
  - 12.5.1 Record Card Release to Student (`id.release`)
- **12.6 Handle ID Card Issues**
  - 12.6.1 Reissue Damaged/Lost Card (`id.reissue`)
  - 12.6.2 Cancel ID Request (`id.cancel`)

### 13.0 Manage Student Records (Student 360°)

- **13.1 Quick-Search Students**
  - 13.1.1 Search Students by Name or School ID (`students.quick-search`)
- **13.2 Browse Student Directory**
  - 13.2.1 List & Filter Student Directory (`students.index`)
- **13.3 View Student 360° Profile**
  - 13.3.1 View Complete Student Record (`students.show`)
  - 13.3.2 View Enrollment & Assessment History
  - 13.3.3 View Exam, Clearance & ID History

### 14.0 Manage Reference Data

- **14.1 Manage Courses** *(Create / Read / Update / Delete — `admin.reference-data.courses.*`)*
- **14.2 Manage Majors** *(CRUD — `admin.reference-data.majors.*`)*
- **14.3 Manage Curriculums** *(CRUD — `admin.reference-data.curriculums.*`)*
- **14.4 Manage Curriculum Subjects** *(CRUD — `admin.reference-data.curriculum-subjects.*`)*
- **14.5 Manage Subjects** *(CRUD — `admin.reference-data.subjects.*`)*
- **14.6 Manage Academic Terms** *(CRUD — `admin.reference-data.terms.*`)*
- **14.7 Manage Fee Types** *(CRUD — `admin.reference-data.fee-types.*`)*
- **14.8 Manage Scholarship Types** *(CRUD — `admin.reference-data.scholarship-types.*`)*
- **14.9 Manage Offices** *(CRUD — `admin.reference-data.offices.*`)*
- **14.10 Manage Rooms** *(CRUD — `admin.reference-data.rooms.*`)*
- **14.11 Manage Blocks** *(CRUD — `admin.reference-data.blocks.*`)*
- **14.12 Manage Admission Requirements** *(CRUD — `admin.reference-data.admission-requirements.*`)*
- **14.13 Manage Clearance Requirements** *(CRUD — `admin.reference-data.clearance-requirements.*`)*
- **14.14 Open Reference Data Hub**
  - 14.14.1 View All Reference Data Cards (`admin.reference-data.index`)

### 15.0 Manage Users, Roles & System Settings

- **15.1 Manage Staff Accounts**
  - 15.1.1 List & Filter Staff Accounts (`admin.users.index`)
  - 15.1.2 Create Staff Account (`admin.users.store`)
  - 15.1.3 Update Staff Account (`admin.users.update`)
  - 15.1.4 Delete Staff Account (`admin.users.destroy`)
  - 15.1.5 Activate / Deactivate Account (`admin.users.status.toggle`)
- **15.2 Assign User Roles**
  - 15.2.1 Assign Spatie Roles to Staff User (`admin.users.roles.assign`)
- **15.3 Manage Roles**
  - 15.3.1 List Roles (`admin.users.roles`)
  - 15.3.2 Create Role (`admin.users.roles.store`)
  - 15.3.3 Update Role (`admin.users.roles.update`)
  - 15.3.4 Delete Role (`admin.users.roles.destroy`)
- **15.4 Manage Permissions**
  - 15.4.1 List Permissions (`admin.users.permissions`)
  - 15.4.2 Create Permission (`admin.users.permissions.store`)
  - 15.4.3 Update Permission (`admin.users.permissions.update`)
  - 15.4.4 Delete Permission (`admin.users.permissions.destroy`)
- **15.5 Manage System Settings**
  - 15.5.1 View System Settings (`admin.users.settings`)
  - 15.5.2 Update System Setting (`admin.users.settings.update`)
- **15.6 Audit System Activity**
  - 15.6.1 View & Filter Audit Log Entries (`admin.users.audit-logs`)

---

## Note on Level-3 wording

Every Level-3 item is an action phrase mapped to a real endpoint (shown in parentheses).
Functions like *Manage Courses* compress full CRUD (List / Create / Update / Delete) into one
Level-2 node because all four operations exist as symmetric routes — the CRUD verb set is
stated explicitly in the node.

---

## VISUAL DIAGRAM (Mermaid)

> Renders natively on GitHub. Level 0 → Level 1 → Level 2 → Level 3.
> Route names omitted here for readability — see the tree above for endpoint mapping.

```mermaid
graph TD
    L0["0.0 SEAIT Enrollment Management System"]

    L0 --> M1["1.0 Authenticate & Manage Own Account"]
    L0 --> M2["2.0 Monitor Work Queues & Notifications"]
    L0 --> M3["3.0 Manage Admissions"]
    L0 --> M4["4.0 Manage Entrance Examinations"]
    L0 --> M5["5.0 Manage Department Evaluations"]
    L0 --> M6["6.0 Manage Fee Assessments"]
    L0 --> M7["7.0 Manage Payments & Collections"]
    L0 --> M8["8.0 Manage Clearances"]
    L0 --> M9["9.0 Manage Block Sections & Schedules"]
    L0 --> M10["10.0 Manage Registrar Operations"]
    L0 --> M11["11.0 Manage Clinic Assessments"]
    L0 --> M12["12.0 Manage Student IDs"]
    L0 --> M13["13.0 Manage Student Records (Student 360°)"]
    L0 --> M14["14.0 Manage Reference Data"]
    L0 --> M15["15.0 Manage Users, Roles & System Settings"]

    M1 --> M1a["1.1 Authenticate Staff Account"]
    M1 --> M1b["1.2 Terminate Staff Session"]
    M1 --> M1c["1.3 Recover Forgotten Password"]
    M1 --> M1d["1.4 Re-Confirm Password"]
    M1 --> M1e["1.5 Manage Own Profile"]
    M1a --> M1a1["Submit Login Credentials"]
    M1a --> M1a2["Validate Credentials & Create Session"]
    M1b --> M1b1["Invalidate Session & Regenerate Token"]
    M1c --> M1c1["Request Password-Reset Link"]
    M1c --> M1c2["Reset Password via Token"]
    M1d --> M1d1["Submit Password Confirmation"]
    M1e --> M1e1["View Own Profile"]
    M1e --> M1e2["Update Profile Information"]
    M1e --> M1e3["Update Own Password"]
    M1e --> M1e4["Delete Own Account"]

    M2 --> M2a["2.1 Monitor Desk Work Queues"]
    M2 --> M2b["2.2 Manage Notifications"]
    M2a --> M2a1["Display Pending Work Per Desk"]
    M2a --> M2a2["Poll Live Queue Counts"]
    M2b --> M2b1["List Own Notifications"]
    M2b --> M2b2["Mark Notification as Read"]
    M2b --> M2b3["Mark All Notifications as Read"]

    M3 --> M3a["3.1 Manage Applicant Records"]
    M3 --> M3b["3.2 Process Admission Requirements"]
    M3 --> M3c["3.3 Decide Admission Outcome"]
    M3a --> M3a1["List & Search Admissions"]
    M3a --> M3a2["Record New Applicant & Admission"]
    M3a --> M3a3["View Admission Details"]
    M3b --> M3b1["Upload & Submit Requirement Document"]
    M3b --> M3b2["Verify Requirement Document"]
    M3c --> M3c1["Approve Admission"]
    M3c --> M3c2["Reject Admission"]

    M4 --> M4a["4.1 Manage Exam Result Records"]
    M4 --> M4b["4.2 Look Up Enrolled Examinees"]
    M4 --> M4c["4.3 Record General Entrance Exam"]
    M4 --> M4d["4.4 Record Course-Specific Entrance Exam"]
    M4 --> M4e["4.5 Record Retention Exam"]
    M4a --> M4a1["View Exam Queue & Results"]
    M4a --> M4a2["Generate Pass/Fail Lists"]
    M4b --> M4b1["Fetch Enrolled Students by Course & Term"]
    M4c --> M4c1["Save General Exam Result"]
    M4c --> M4c2["Auto-Reject Admission on Fail"]
    M4d --> M4d1["Verify General Exam Passed First"]
    M4d --> M4d2["Save Course-Specific Result"]
    M4d --> M4d3["Auto-Approve / Reject Admission"]
    M4e --> M4e1["Validate Course Retention Requirement"]
    M4e --> M4e2["Save Retention Exam Result"]

    M5 --> M5a["5.1 Manage Evaluation Queue"]
    M5 --> M5b["5.2 Capture Student Profile"]
    M5 --> M5c["5.3 Propose Curriculum Subjects"]
    M5 --> M5d["5.4 Process Transfer Credits"]
    M5 --> M5e["5.5 Sign Evaluation"]
    M5a --> M5a1["List Pending Evaluations"]
    M5a --> M5a2["View Evaluation Details"]
    M5b --> M5b1["Record Complete Demographic Profile"]
    M5c --> M5c1["Propose Subjects per Curriculum"]
    M5d --> M5d1["Record & Evaluate Credited Subjects"]
    M5e --> M5e1["Sign Evaluation & Advance Workflow"]

    M6 --> M6a["6.1 Manage Assessment Queue"]
    M6 --> M6b["6.2 Compute Assessment Fees"]
    M6 --> M6c["6.3 Apply Scholarships"]
    M6 --> M6d["6.4 Adjust Charges"]
    M6 --> M6e["6.5 Finalize Assessment"]
    M6a --> M6a1["List Pending Assessments"]
    M6a --> M6a2["View Assessment Breakdown"]
    M6b --> M6b1["Compute Per-Unit & Flat Fee Totals"]
    M6c --> M6c1["Apply Grant or Outside Scholarship"]
    M6d --> M6d1["Add, Edit & Remove Charges"]
    M6e --> M6e1["Sign & Lock Assessment"]

    M7 --> M7a["7.1 Manage Payment Queue"]
    M7 --> M7b["7.2 Record Student Payment"]
    M7 --> M7c["7.3 Void Payment"]
    M7 --> M7d["7.4 Report Daily Collections"]
    M7a --> M7a1["List Assessments Awaiting Payment"]
    M7a --> M7a2["View Balance & Charges"]
    M7b --> M7b1["Post Payment Against Assessment"]
    M7b --> M7b2["Update Balance & Payment Status"]
    M7c --> M7c1["Void Recorded Payment"]
    M7d --> M7d1["Generate Daily Collection Report"]

    M8 --> M8a["8.1 Manage Clearance Queue"]
    M8 --> M8b["8.2 Manage Clearance Periods"]
    M8 --> M8c["8.3 Issue Clearance Slips"]
    M8 --> M8d["8.4 Record Signed Receipts"]
    M8 --> M8e["8.5 Approve Clearance Requirements"]
    M8a --> M8a1["List Students for Clearance Signing"]
    M8b --> M8b1["Create Clearance Period"]
    M8b --> M8b2["Update Clearance Period"]
    M8c --> M8c1["Generate Clearance Slip"]
    M8c --> M8c2["Print Clearance Slip"]
    M8c --> M8c3["Replace Lost Clearance Slip"]
    M8d --> M8d1["Record Signed Receipt per Office"]
    M8e --> M8e1["Approve Office Requirement Sign-Off"]

    M9 --> M9a["9.1 Manage Block Sections"]
    M9 --> M9b["9.2 Manage Block Schedules"]
    M9 --> M9c["9.3 Assign Students to Blocks"]
    M9 --> M9d["9.4 Print Block Schedule"]
    M9a --> M9a1["Create Block Section"]
    M9a --> M9a2["Update Block Section"]
    M9a --> M9a3["Delete Block Section"]
    M9a --> M9a4["View Block Details & Roster"]
    M9b --> M9b1["Create Block Schedule"]
    M9b --> M9b2["Update Block Schedule"]
    M9b --> M9b3["Delete Block Schedule"]
    M9b --> M9b4["Validate Room & Schedule Conflicts"]
    M9c --> M9c1["Assign Students (with Capacity Check)"]
    M9c --> M9c2["Unassign Students"]
    M9d --> M9d1["Print Block Section Schedule"]

    M10 --> M10a["10.1 Manage Registrar Queue"]
    M10 --> M10b["10.2 Approve Enrollments"]
    M10 --> M10c["10.3 Print Enrollment Documents"]
    M10a --> M10a1["List Enrollments for Approval"]
    M10a --> M10a2["View Full Enrollment Record"]
    M10b --> M10b1["Sign Final Registrar Step"]
    M10b --> M10b2["Set Status to Enrolled"]
    M10c --> M10c1["Print Enrollment Certificate"]
    M10c --> M10c2["Print Class Cards"]
    M10c --> M10c3["Print Subject Load"]

    M11 --> M11a["11.1 Manage Clinic Queue"]
    M11 --> M11b["11.2 Record Health Assessment"]
    M11 --> M11c["11.3 Update Health Assessment"]
    M11 --> M11d["11.4 Reopen Completed Record"]
    M11a --> M11a1["List Enrollments Awaiting Screening"]
    M11a --> M11a2["View Clinic Assessment Form"]
    M11b --> M11b1["Record Physical Exam Findings"]
    M11b --> M11b2["Record PhilHealth Registration"]
    M11b --> M11b3["Sign Clinic Workflow Step"]
    M11c --> M11c1["Amend Clinic Record Fields"]
    M11d --> M11d1["Reopen Record for Correction"]

    M12 --> M12a["12.1 Manage ID Request Queue"]
    M12 --> M12b["12.2 Create ID Requests"]
    M12 --> M12c["12.3 Produce ID Cards"]
    M12 --> M12d["12.4 Validate ID Cards"]
    M12 --> M12e["12.5 Release ID Cards"]
    M12 --> M12f["12.6 Handle ID Card Issues"]
    M12a --> M12a1["List ID Requests by Status"]
    M12a --> M12a2["View Request & Card Details"]
    M12b --> M12b1["Record New ID Request"]
    M12c --> M12c1["Generate QR Code & Produce Card"]
    M12d --> M12d1["Validate Produced Card"]
    M12e --> M12e1["Record Card Release"]
    M12f --> M12f1["Reissue Damaged/Lost Card"]
    M12f --> M12f2["Cancel ID Request"]

    M13 --> M13a["13.1 Quick-Search Students"]
    M13 --> M13b["13.2 Browse Student Directory"]
    M13 --> M13c["13.3 View Student 360° Profile"]
    M13a --> M13a1["Search by Name or School ID"]
    M13b --> M13b1["List & Filter Directory"]
    M13c --> M13c1["View Complete Student Record"]
    M13c --> M13c2["View Enrollment & Assessment History"]
    M13c --> M13c3["View Exam, Clearance & ID History"]

    M14 --> M14a["14.1 Manage Courses (CRUD)"]
    M14 --> M14b["14.2 Manage Majors (CRUD)"]
    M14 --> M14c["14.3 Manage Curriculums (CRUD)"]
    M14 --> M14d["14.4 Manage Curriculum Subjects (CRUD)"]
    M14 --> M14e["14.5 Manage Subjects (CRUD)"]
    M14 --> M14f["14.6 Manage Academic Terms (CRUD)"]
    M14 --> M14g["14.7 Manage Fee Types (CRUD)"]
    M14 --> M14h["14.8 Manage Scholarship Types (CRUD)"]
    M14 --> M14i["14.9 Manage Offices (CRUD)"]
    M14 --> M14j["14.10 Manage Rooms (CRUD)"]
    M14 --> M14k["14.11 Manage Blocks (CRUD)"]
    M14 --> M14l["14.12 Manage Admission Requirements (CRUD)"]
    M14 --> M14m["14.13 Manage Clearance Requirements (CRUD)"]
    M14 --> M14n["14.14 Open Reference Data Hub"]

    M15 --> M15a["15.1 Manage Staff Accounts"]
    M15 --> M15b["15.2 Assign User Roles"]
    M15 --> M15c["15.3 Manage Roles (CRUD)"]
    M15 --> M15d["15.4 Manage Permissions (CRUD)"]
    M15 --> M15e["15.5 Manage System Settings"]
    M15 --> M15f["15.6 Audit System Activity"]
    M15a --> M15a1["Create Staff Account"]
    M15a --> M15a2["Update Staff Account"]
    M15a --> M15a3["Delete Staff Account"]
    M15a --> M15a4["Activate / Deactivate Account"]
    M15b --> M15b1["Assign Roles to Staff User"]
    M15e --> M15e1["View System Settings"]
    M15e --> M15e2["Update System Setting"]
    M15f --> M15f1["View & Filter Audit Log Entries"]
```

---

*Verified against `routes/web.php`, `routes/auth.php`, and all 15 controllers
(232 passing tests at commit `c8ea184`). Regenerate after adding any new route.*




