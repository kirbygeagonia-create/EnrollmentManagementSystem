# Use-case diagrams

Written by `node storage/app/scratch/lane208-use-case-figures.mjs` from the use-case graphs in
`Use-Case-Diagram-Guide.md`. Edit that guide, then re-run the generator; retyping a figure here would make this
page a second definition of a model the guide already owns.

Model as read by the last run: 131 nodes (41 actor declarations, 90 use cases), 97 associations, 23 «include», 13 «extend».

Both claims below are weighed by the generator before it writes this page — `--audit-actor-edges` and
`--audit-complete-figure` — so the figures are known to hold what they promise rather than assumed to:
- 19 actor figure(s); 17 carry at least one include/extend chain; 0 owed dependency edge(s) dropped.
- model 131 nodes (41 actor declarations, 90 use cases) fold to 96 distinct shapes, drawn 96; model 134 edges, drawn 121, folded as restatement 13 and as merged endpoints 0.


## Complete diagram over every office and desk (2)

### Complete use-case diagram over every desk and office

![Complete use-case diagram over every desk and office](Diagrams/use-case-complete-all-offices.png)

### Complete use-case diagram over every desk and office (wide layout)

![Complete use-case diagram over every desk and office (wide layout)](Diagrams/use-case-complete-wide.png)


## Actor-to-desk overview (the readable view) (1)

### Actor-to-desk overview: 18 roles, 12 desks, 26 lines — major actors right, minor left

![Actor-to-desk overview: 18 roles, 12 desks, 26 lines — major actors right, minor left](Diagrams/use-case-roles-at-desks.png)


## Global system boundary (1)

### 3. SEAIT EMS Global System Boundary Diagram

![3. SEAIT EMS Global System Boundary Diagram](Diagrams/use-case-global-boundary.png)


## One figure per desk (12)

### Desk 01: Admissions Desk

![Desk 01: Admissions Desk](Diagrams/use-case-desk-01-admissions-desk.png)

### Desk 02: Guidance & Entrance Examination Desk

![Desk 02: Guidance & Entrance Examination Desk](Diagrams/use-case-desk-02-guidance-entrance-examination-desk.png)

### Desk 03: Academic Evaluation & Department Desk

![Desk 03: Academic Evaluation & Department Desk](Diagrams/use-case-desk-03-academic-evaluation-department-desk.png)

### Desk 04: Financial Assessment & Scholarship Desk

![Desk 04: Financial Assessment & Scholarship Desk](Diagrams/use-case-desk-04-financial-assessment-scholarship-desk.png)

### Desk 05: Accounting, Billing & Cashier Desk

![Desk 05: Accounting, Billing & Cashier Desk](Diagrams/use-case-desk-05-accounting-billing-cashier-desk.png)

### Desk 06: Student Affairs & Campus Clearance Desk

![Desk 06: Student Affairs & Campus Clearance Desk](Diagrams/use-case-desk-06-student-affairs-campus-clearance-desk.png)

### Desk 07: Curriculum Scheduling & Block Assignment Desk

![Desk 07: Curriculum Scheduling & Block Assignment Desk](Diagrams/use-case-desk-07-curriculum-scheduling-block-assignment-desk.png)

### Desk 08: Office of the University Registrar Desk

![Desk 08: Office of the University Registrar Desk](Diagrams/use-case-desk-08-office-of-the-university-registrar-desk.png)

### Desk 09: Campus Health & Medical Clinic Desk

![Desk 09: Campus Health & Medical Clinic Desk](Diagrams/use-case-desk-09-campus-health-medical-clinic-desk.png)

### Desk 10: Student ID Validation Desk

![Desk 10: Student ID Validation Desk](Diagrams/use-case-desk-10-student-id-validation-desk.png)

### Desk 11: Student 360° Digital Dossier & Directory Desk

![Desk 11: Student 360° Digital Dossier & Directory Desk](Diagrams/use-case-desk-11-student-360-digital-dossier-directory-desk.png)

### Desk 12: System Administration, Security & Audit Desk

![Desk 12: System Administration, Security & Audit Desk](Diagrams/use-case-desk-12-system-administration-security-audit-desk.png)


## One figure per actor — its associations, and the include and extend chains hanging off them (19)

### Actor: Admission Officer

![Actor: Admission Officer](Diagrams/use-case-actor-admission-officer.png)

### Actor: Guidance Counselor

![Actor: Guidance Counselor](Diagrams/use-case-actor-guidance-counselor.png)

### Actor: Academic Evaluator

![Actor: Academic Evaluator](Diagrams/use-case-actor-academic-evaluator.png)

### Actor: College Dean

![Actor: College Dean](Diagrams/use-case-actor-college-dean.png)

### Actor: Scholarship Officer

![Actor: Scholarship Officer](Diagrams/use-case-actor-scholarship-officer.png)

### Actor: Cashier / Accounting

![Actor: Cashier / Accounting](Diagrams/use-case-actor-cashier-accounting.png)

### Actor: Clearance Officer

![Actor: Clearance Officer](Diagrams/use-case-actor-clearance-officer.png)

### Actor: Blocking Coordinator

![Actor: Blocking Coordinator](Diagrams/use-case-actor-blocking-coordinator.png)

### Actor: University Registrar

![Actor: University Registrar](Diagrams/use-case-actor-university-registrar.png)

### Actor: Medical Nurse / Clinic

![Actor: Medical Nurse / Clinic](Diagrams/use-case-actor-medical-nurse-clinic.png)

### Actor: Student ID Officer

![Actor: Student ID Officer](Diagrams/use-case-actor-student-id-officer.png)

### Actor: System Administrator

![Actor: System Administrator](Diagrams/use-case-actor-system-administrator.png)

### Actor: Institutional Staff (Any Desk)

![Actor: Institutional Staff (Any Desk)](Diagrams/use-case-actor-institutional-staff-any-desk.png)

### Actor: Student

![Actor: Student](Diagrams/use-case-actor-student.png)

### Actor: Institutional SMS / Mail Service

![Actor: Institutional SMS / Mail Service](Diagrams/use-case-actor-institutional-sms-mail-service.png)

### Actor: Office Head / Signatory

![Actor: Office Head / Signatory](Diagrams/use-case-actor-office-head-signatory.png)

### Actor: Faculty Instructor

![Actor: Faculty Instructor](Diagrams/use-case-actor-faculty-instructor.png)

### Actor: Office Head / Auditor

![Actor: Office Head / Auditor](Diagrams/use-case-actor-office-head-auditor.png)

### Actor: Compliance Officer / Auditor

![Actor: Compliance Officer / Auditor](Diagrams/use-case-actor-compliance-officer-auditor.png)
