# Database Design (ADS presentation item 1: ERD, tables and relationships, primary and foreign keys)

**This page is generated, not written.** Re-take it with
`php storage/app/scratch/lane162-generate-erd.php --out=Documentation/DATABASE-DESIGN-ADS.md`, which reads
`information_schema` on the schema `.env` names: edges come from `KEY_COLUMN_USAGE`, an edge is drawn one-to-one
only when the referencing column set is actually UNIQUE, and the domain/framework split is derived from the bundled
`0001_` migrations plus `migrations` rather than recalled from a list. The generator refuses when the server answers
no base tables or no foreign keys, because a confident empty diagram is the fake-clean verdict, not a measurement.

**Authoritative detail lives in the docx.** `Documentation/EMS Complete Documentation.docx` §24 is the data
dictionary (its per-table "N columns" headings are cross-checked column-by-column against the server by
`php storage/app/scratch/lane60-ratio-claims.php --run`) and §23.6.1 lists every unique index in the domain tables,
weighed by `php storage/app/scratch/lane44-unique-keys.php`. This file is the diagram a presenter points at; the
dictionary, the delete behaviours and the business meaning of each column are not duplicated here, because two
documents stating one schema is how the two answers to one word keep arriving.

**What a database examiner should take from the diagram.** Every enrollment fact hangs off `students` or
`enrollments`, and no child row can be written that names a parent the server does not hold. The delete behaviours
are deliberately not restated here: they are not one rule — `php storage/app/scratch/lane43-delete-guards.php`
reads each key's `ON DELETE` and every desk guard that has to answer it, and §23 of the authority document carries
the counts. A page that copied those counts would be a second answer to one question, which is the defect this
repository keeps refusing.

```mermaid
erDiagram
    academicyears ||--o{ academicterms : "academicYearId"
    academicunits ||--o{ academicunits : "parentUnitId"
    students ||--o{ addresses : "studentId"
    courses ||--o{ admissions : "courseId"
    staffusers ||--o{ admissions : "evaluatedBy"
    students ||--o{ admissions : "studentId"
    academicterms ||--o{ admissions : "termId"
    staffusers ||--o{ auditlogs : "userId"
    courses ||--o{ blocks : "courseId"
    academicterms ||--o{ blocks : "termId"
    studentassessments ||--o{ charges : "assessmentId"
    feetypes ||--o{ charges : "feeTypeId"
    staffusers ||--o{ clearanceapprovals : "approvedBy"
    clearancerequirements ||--o{ clearanceapprovals : "clearanceRequirementId"
    studentclearances ||--o{ clearanceapprovals : "studentClearanceId"
    academicterms ||--o{ clearanceperiods : "termId"
    offices ||--o{ clearancerequirements : "officeId"
    staffusers ||--o{ clinicrecords : "clinicStaffId"
    enrollments ||--o{ clinicrecords : "enrollmentId"
    academicunits ||--o{ courses : "unitId"
    staffusers ||--o{ creditedsubjects : "decidedBy"
    enrollments ||--o{ creditedsubjects : "enrollmentId"
    subjects ||--o{ creditedsubjects : "creditedToSubjectId"
    transferacademicrecords ||--o{ creditedsubjects : "transferRecordId"
    courses ||--o{ curriculums : "courseId"
    majors ||--o{ curriculums : "majorId"
    curriculums ||--o{ curriculumsubjects : "curriculumId"
    subjects ||--o{ curriculumsubjects : "prerequisiteSubjectId"
    subjects ||--o{ curriculumsubjects : "subjectId"
    blocks ||--o{ documentprintlog : "blockId"
    enrollments ||--|| documentprintlog : "enrollmentId"
    staffusers ||--o{ documentprintlog : "printedBy"
    students ||--o{ documentprintlog : "studentId"
    studentrequirementsubmissions ||--o{ documents : "submissionId"
    staffusers ||--o{ documents : "verifiedBy"
    blocks ||--o{ enrolledsubjects : "blockId"
    enrollments ||--|| enrolledsubjects : "enrollmentId"
    enrolledsubjects ||--o{ enrolledsubjects : "original_enrolled_subject_id"
    schedules ||--o{ enrolledsubjects : "scheduleId"
    subjects ||--|| enrolledsubjects : "subjectId"
    enrollments ||--o{ enrollmentreturns : "enrollmentId"
    staffusers ||--o{ enrollmentreturns : "returnedBy"
    enrollmentworkflow ||--o{ enrollmentreturns : "workflowId"
    admissions ||--o{ enrollments : "admissionId"
    staffusers ||--o{ enrollments : "clearanceConfirmedBy"
    courses ||--o{ enrollments : "courseId"
    curriculums ||--o{ enrollments : "curriculumId"
    staffusers ||--o{ enrollments : "evaluatedBy"
    majors ||--o{ enrollments : "majorId"
    staffusers ||--o{ enrollments : "registrarProcessedBy"
    students ||--o{ enrollments : "studentId"
    academicterms ||--o{ enrollments : "termId"
    staffusers ||--o{ enrollmentstatushistory : "changedBy"
    enrollments ||--o{ enrollmentstatushistory : "enrollmentId"
    enrollments ||--o{ enrollmentworkflow : "enrollmentId"
    courses ||--o{ examresults : "courseId"
    students ||--o{ examresults : "studentId"
    academicterms ||--o{ examresults : "termId"
    staffusers ||--o{ examresults : "verifiedBy"
    students ||--o{ guardians : "studentId"
    enrollments ||--o{ idrequests : "enrollmentId"
    staffusers ||--o{ idrequests : "validatedBy"
    courses ||--o{ majors : "courseId"
    permissions ||--|| model_has_permissions : "permissionId"
    enrollments ||--o{ payments : "enrollmentId"
    staffusers ||--o{ payments : "processedBy"
    staffusers ||--o{ payments : "refundedBy"
    permissions ||--|| role_permissions : "permissionId"
    roles ||--|| role_permissions : "roleId"
    schedules ||--o{ schedulemeetings : "scheduleId"
    subjects ||--o{ schedules : "subjectId"
    blocks ||--o{ schedules : "blockId"
    staffusers ||--o{ schedules : "instructorId"
    rooms ||--o{ schedules : "roomId"
    courses ||--o{ shiftingrequests : "currentCourseId"
    enrollments ||--o{ shiftingrequests : "currentEnrollmentId"
    staffusers ||--o{ shiftingrequests : "decisionBy"
    staffusers ||--o{ shiftingrequests : "departmentSignedBy"
    enrollments ||--o{ shiftingrequests : "grantedEnrollmentId"
    staffusers ||--o{ shiftingrequests : "requestedBy"
    students ||--o{ shiftingrequests : "studentId"
    courses ||--o{ shiftingrequests : "targetCourseId"
    academicterms ||--o{ shiftingrequests : "termId"
    roles ||--|| staff_roles : "roleId"
    offices ||--o{ staffusers : "officeId"
    academicunits ||--o{ staffusers : "unitId"
    enrollments ||--o{ studentassessments : "enrollmentId"
    clearanceperiods ||--|| studentclearances : "clearancePeriodId"
    staffusers ||--o{ studentclearances : "receivedBy"
    students ||--|| studentclearances : "studentId"
    educationalinstitutions ||--o{ studenteducationalbackgrounds : "institutionId"
    students ||--o{ studenteducationalbackgrounds : "studentId"
    admissions ||--o{ studentrequirementsubmissions : "admissionId"
    admissionrequirements ||--o{ studentrequirementsubmissions : "requirementId"
    religions ||--o{ students : "religionId"
    staffusers ||--o{ studentscholarships : "approvedBy"
    scholarshiptypes ||--|| studentscholarships : "scholarshipTypeId"
    staffusers ||--o{ studentscholarships : "statusChangedBy"
    students ||--|| studentscholarships : "studentId"
    academicterms ||--|| studentscholarships : "termId"
    educationalinstitutions ||--o{ transferacademicrecords : "institutionId"
    students ||--o{ transferacademicrecords : "studentId"
    offices ||--o{ workflowsteps : "officeId"
    staffusers ||--o{ workflowsteps : "signedBy"
    enrollmentworkflow ||--o{ workflowsteps : "workflowId"
```

## Partition read from the server

- tables in `ems`: **64** — domain **56**, framework **8** (the `0001_` migrations plus `migrations`, derived from the migration files rather than recalled)
- foreign keys: **105**; edges drawn between domain tables: **105**
- domain columns: **437**

## Domain tables and their keys

| table | columns | primary key | unique keys |
|---|---|---|---|
| academicterms | 5 | termId | 1 |
| academicunits | 4 | unitId | 1 |
| academicyears | 4 | academicYearId | 1 |
| addresses | 13 | addressId | 1 |
| admissionrequirements | 4 | requirementId | 1 |
| admissions | 13 | admissionId | 1 |
| auditlogs | 10 | auditId | 1 |
| blocks | 7 | blockId | 1 |
| charges | 5 | chargeId | 1 |
| clearanceapprovals | 7 | clearanceApprovalId | 1 |
| clearanceperiods | 5 | clearancePeriodId | 1 |
| clearancerequirements | 3 | clearanceRequirementId | 1 |
| clinicrecords | 14 | clinicRecordId | 1 |
| courses | 8 | courseId | 1 |
| creditedsubjects | 11 | creditedId | 1 |
| curriculums | 5 | curriculumId | 1 |
| curriculumsubjects | 10 | curriculumSubjectId | 1 |
| documentprintlog | 8 | printLogId | 4 |
| documents | 6 | documentId | 1 |
| educationalinstitutions | 5 | institutionId | 1 |
| enrolledsubjects | 9 | enrolledSubjectId | 4 |
| enrollmentreturns | 15 | returnId | 1 |
| enrollments | 23 | enrollmentId | 1 |
| enrollmentstatushistory | 7 | historyId | 1 |
| enrollmentworkflow | 4 | workflowId | 1 |
| examresults | 11 | examId | 1 |
| feetypes | 4 | feeTypeId | 1 |
| gradescale | 5 | gradeScaleId | 1 |
| guardians | 8 | guardianId | 1 |
| idrequests | 14 | idRequestId | 1 |
| majors | 3 | majorId | 1 |
| model_has_permissions | 3 | permissionId+model_id+model_type | 3 |
| notifications | 9 | id | 2 |
| offices | 2 | officeId | 1 |
| payments | 13 | paymentId | 2 |
| permissions | 6 | id | 3 |
| religions | 2 | religionId | 1 |
| role_permissions | 2 | permissionId+roleId | 2 |
| roles | 6 | id | 3 |
| rooms | 4 | roomId | 1 |
| schedulemeetings | 5 | meetingId | 1 |
| schedules | 5 | scheduleId | 1 |
| scholarshiptypes | 4 | scholarshipTypeId | 1 |
| settings | 3 | settingKey | 1 |
| shiftingrequests | 19 | shiftingRequestId | 1 |
| staff_roles | 3 | roleId+model_id+model_type | 3 |
| staffusers | 14 | userId | 3 |
| studentassessments | 9 | assessmentId | 1 |
| studentclearances | 8 | studentClearanceId | 3 |
| studenteducationalbackgrounds | 8 | backgroundId | 1 |
| studentrequirementsubmissions | 6 | submissionId | 1 |
| students | 22 | studentId | 3 |
| studentscholarships | 10 | studentScholarshipId | 4 |
| subjects | 7 | subjectId | 1 |
| transferacademicrecords | 10 | transferRecordId | 1 |
| workflowsteps | 7 | workflowStepId | 1 |

