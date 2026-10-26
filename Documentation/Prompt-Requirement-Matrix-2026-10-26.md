# Requirement-to-Code Matrix — `EnrollmentManagementSystem_Optimized_AI_Development_Prompt.md`

**Audited:** 2026-10-26 (this box's clock; real UTC is one civil day earlier — see the authority docx §1.2).
**Checkout:** branch `master`, commit `fec3275`, working tree clean, `origin/master` in sync.
**Prompt audited:** `C:\Users\ADMIN\Downloads\EnrollmentManagementSystem_Optimized_AI_Development_Prompt.md` (§4 "Required Initial Audit", §13 Phase 0 step 4).
**Method:** every row below was read out of the current checkout in this pass. No row is classified `Working` from recollection, and as of the last edit no row is left `Carried` — the two items a tool cannot judge (the browser walk for console errors, and whether a print layout reads as the official paper) are named as human reads in §2 rather than parked as unverified.

**Authority note.** The project owner ruled on 2026-09-29 that `Documentation/EMS Complete Documentation.docx` is the authority. The prompt's own §3.1 puts *this prompt's explicit target behaviour* first and current code second, so where the two disagree the conflict is named in the last column rather than resolved silently — a ruling the owner has already made (the seven workflow boxes, the office list, the print wording) is not quietly re-opened by a new document.

---

## 1. Requirements verified in this pass

| § | Requirement | State | Evidence (read this pass) |
|---|---|---|---|
| 5.1 | One canonical person record; an applicant is not re-created downstream | Working | `admissions.studentId` is a FK to `students` (create_admissions migration); `AdmissionController.php` writes `admissionStatus => AdmissionStatus::Pending` against the existing student |
| 5.2 | First-Year / Transferee / Continuing / Shifter classifications | Working | `app/Enums/StudentType.php` = `firstYear, continuing, transferee, shifter`; `ApplicantType.php` = `firstYear, transferee` |
| 5.2 | **Returnee / Returning as a distinct classification** | **Conflicting → decision** | No `returnee` case in `StudentType`. The *behaviour* exists: `EvaluationController::returningStudents()`, `tests/Feature/Evaluation/ReturningEnrollmentIssuingTest.php`, and `WorkflowService::stepsFor()` treats a returning student as `continuing`. The prompt forbids renaming `continuing` → `returnee` and requires the SEAIT rule; the rule is not in the repository |
| 5.3 | Credentials kept apart from biographical intake; no public registration | Working | `AdmissionStatus` has no self-service path; `staffusers` accounts come from Admin → User Management (`app/Http/Controllers/Admin/UserManagementController.php:78,144`); project rule: public `/register` removed |
| 6.2 | Enrollment status ≠ workflow status ≠ step status | Working | three separate enums: `EnrollmentStatus` (7 cases), `WorkflowStatus` (`inProgress, completed, lost`), `WorkflowStepStatus` (`pending, completed, skipped`) |
| 6.2 | **Step-level On Hold / Failed / Returned** | **Missing (partly at another level)** | `workflowsteps.stepStatus` is `enum('pending','completed','skipped')`. Return exists one level up (`EnrollmentStatus::ReturnedToEvaluation`) and a hold exists one level up too (`add_registrar_hold_state_to_enrollments` migration). Nothing at the step can say *why* it is not moving |
| 6.3 | **Admin-configurable workflow by academic year / semester / student type** | **Missing** | No definition table (`database/migrations` holds only `create_enrollmentworkflow` and `create_workflowsteps`); the box list is a private PHP method, `WorkflowService::stepsFor()`, hard-coding seven offices with one student-type filter. `resources/js/Pages/Admin/` holds only `ReferenceData` and `UserManagement` |
| 6.3 | Existing enrollments keep a **snapshot** of the steps they began with | Working | `workflowsteps` rows are per-instance (`workflowId` FK, `stepOrder`, `signedBy`, `signedDate`), so a change to the code list cannot rewrite signed history |
| 6.4 | A return records reason, actor, timestamp, previous and new status | **Working, read this pass** | `registrar.return` → `RegistrarController::returnToEvaluation()` ( `RegistrarController.php:304` ): `returnReason` is `required\|string\|min:10\|max:500`, and `EnrollmentStateMachine::transition()` is the only writer of `enrollmentstatushistory`, whose columns are `fromStatus`, `toStatus`, `changedBy`, `remarks`, `changedAt` — so who, when, from what, to what and why are all stored. Refuses anything that is not `paid` |
| 6.4 | **A return records its reason *category*, its origin office and its destination as fields, and the same act is available from Admission and Evaluation** | **Missing → G-10** | Origin and destination are only *derivable* (the acting office is the Registrar by route, the destination is implied by the status word `returnedToEvaluation`); there is no category column, and the guard admits only `paid` enrollments, so Evaluation cannot return a case to Admission and no office but the Registrar can return at all. §6.4 asks for the return process to be valid "from Admission, Evaluation, and Registrar" |
| 7.1 | Configurable requirements catalogue, not a hard-coded UI list | Working | `admissionrequirements(requirementName, appliesTo enum(firstYear,transferee,shifter,continuing,all), isRequired)` + `studentrequirementsubmissions.submissionStatus enum(submitted,verified,rejected,incomplete,pending)`; verified/rejected carries `remarks` |
| 7.1 | **Incomplete intake savable as Draft and resumable** | **Built 2026-10-26 (was G-2)** | `admissionStatus` is `enum('draft','pending','approved','rejected')` with a nullable `draftedDate`; `store()` reads an `intakeAction` so the one wizard either submits or parks the case, `submitDraft()` moves a draft into the queue and refuses an incomplete one by naming the missing sections, `index()` counts and filters on `draft`, and `Create.jsx` offers **Save as draft** beside Register Applicant. A draft is in no desk queue: the dashboard's admission badge, the exam candidate lists and every `AdmissionPolicy` right still ask for `pending`. Pinned by `tests/Feature/Admission/DraftIntakeTest.php`, proved by `storage/app/scratch/mutate-g2-draft-intake-tests.php`, and written into the authority docx by passes 250/250b (§1.1, §1.3, §8.3–§8.6, §23.8, §24, §27) |
| 7.2 | Departmental authority belongs to a college, enforced server-side | Working | `app/Http/Controllers/Admin/UserManagementController.php:88,152` refuses a `dean`/`programHead` without a `unitId`; `StaffRole` has both `Dean` and `ProgramHead`; `App\Support\CollegeScope` is the single boundary |
| 7.2 | Guidance records the general exam; the department runs the course-specific one, gated on the general result | **Working, read this pass** | `ExamController.php:291` is "Record general entrance exam (Guidance Office)"; `:337` states BR9 "Verifies Guidance result first" and `:366` is that guard (`if ($course->requiresEntranceExam)`), with the paired refusal at `:354` when a course requires neither; `:181` applies the same split to the passer list and `:210` honours `entranceExamExemptsTransferee` (the owner's C-4 ruling); `:128` and `:429` state the ownership split once for the index and again for results, so a department cannot read another's candidates |
| 7.2 | **Registrar's final confirmation of an exam pass (provisional vs official)** | **Missing as a recorded state** | `examresults` has no official/provisional column and no verifier or timestamp; `grep verify` over `app/Http/Controllers/Exam`, `ExamPolicy` and `routes/web.php` answers only `admission.requirements.verify`. The recorded pass is final the moment it is typed |
| 7.3 | Standing derived from records, not a UI toggle | Working | `AcademicStandingService::derive()` reads prior-term graded rows, applies the `gradescale` passing ceiling, and returns derived standing + failed + retaken rows + advisories |
| 7.3 | Attempt / retake history exists | Working | `enrolledsubjects.attempt_number` + `original_enrolled_subject_id` (extend_enrolledsubjects migration) |
| 7.3 | Transferee crediting reuses the established tables | Working | `transferacademicrecords(subjectNameAtOldSchool, unitsAtOldSchool, gradeAtOldSchool, passResult, institutionId FK)` and `creditedsubjects(previousSubjectName, creditedToSubjectId, creditedUnits, remarks)` |
| 7.3 | **A per-row crediting decision, reason, and approver** | **Missing** | `creditedsubjects` has no decision-status column, no approver FK and no decided-on timestamp; `transferacademicrecords` has no previous *subject code*, no term/year taken and no syllabus reference. The gate that exists is the whole-record `passResult` (owner ruling 10) |
| 7.4 | **Registrar can view grade history by academic year and semester** | **Missing (the data, not the view)** | `enrolledsubjects.grade` joined through `enrollments.termId → academicterms → academicyears.yearLabel` answers the question in one query — and `AcademicStandingService::gradedPriorSubjects()` already runs it, deliberately excluding the current term. No surface reads it: `grep grade resources/js/Pages/Registrar/*` and `.../Students/Show.jsx` → 0 hits; the only grade-bearing props are `standingReport`'s failed/retaken subsets |
| 7.5 | No duplicate receipts, no over-collection, balance integrity | Working (instrumented) | `AccountingController::record()` caps the amount at the balance; `ems:audit:record-states` carries the over-collection rule and the `paid`-must-match-its-money rule; `Payments::held()` is the one definition of money in the drawer |
| 7.5 | **Official billing record labelled "Statement of Accounts"** | **Missing** | `grep -i "statement of accounts"` over `app`, `resources`, `routes`, `tests`, `tools`, `Documentation` → 0 hits. The surface is called the fee sheet / assessment everywhere |
| 7.6 | Seven-box readiness checklist with a reason per unmet gate | Working | `RegistrarController::checklist()` (7 keys) and `blockingReasons()` feed the same page that `approve()` refuses with |
| 7.6 | Certificate and Subject Load kept as separate documents with separate actions | Working | `routes/web.php:152-159` — print + PDF download per document, plus class cards and the enrollment form |
| 7.6 | *"Where operationally useful, offer a Print Enrollment Packet"* | Future Scope (optional by its own wording) | no packet action exists (`grep -i packet` in controllers/pages/routes → 0). Each document mints its own `documentprintlog` issuance, so a packet is a design question, not a merge |
| 7.7 | Completed clearances are retained | Working | nothing in `app/` or `routes/` deletes a `studentclearances` row; `uq_student_clearanceperiod` and four `NO ACTION` FKs; pinned by `ClearanceSlipTermEnrollmentTest` |
| 7.8 | Clinic detail restricted; other desks get the outcome | Working | `ClinicRecordStatus`, per-office clearance boxes, `AuditLogObserver` redaction pattern; the clearance slip prints a status, not notes |
| 7.9 | Table-based schedule work, block created before its timetable | Working (partly) | `resources/js/Pages/Blocking/{Index,Show,PrintSchedule}.jsx`; `blocks.scheduleStatus` (2026_09_20 migration); collision detection scoped inside one term and half-open (docx §28 lane 55 rulings) |
| 7.9 | **Timetable import (CSV/XLSX) with preview + validation** | **Missing** | `grep -i "csv\|import" app/Http/Controllers/Blocking resources/js/Pages/Blocking` → 0. A dry-run *student* importer exists (`tools/`), not a timetable one |
| 7.10 | ID desk validates only; no card production, no biometrics | Working | `IdRequestStatus`, `IdRequestReason`, photo upload on the private disk; no print/produce action |
| 8 | Notification addressee is the right model | Working (instrumented) | `lane71-morph-targets.php` binds three surfaces: the document's claim, the code's write sites, and the rows — all `App\Models\Staffusers`; a CI step judges two of the three |
| 8 | A queue count must match the list under it | Working (instrumented) | lanes 46 (filter contract), 47 (tile scope), 48 (tile fallback), 49 (zero vs absence) — each with a mutant harness |
| 9 | Deactivated staff cannot sign in | Working | `LoginRequest.php:55` `$isActive = $user && $user->status === StaffStatus::Active`; `app/Http/Controllers/Admin/UserManagementController.php:236,244,306` de/activate; `ProfileController.php:52` |
| 9 | Sensitive changes audited | **Working, read this pass** | `AppServiceProvider.php:44-52` attaches `AuditLogObserver` to **every** model in `app/Models` by glob (skipping `Auditlogs` itself to avoid recursion), so any Eloquent write to a domain table — crediting row, workflow step, assessment, payment, clearance, student edit — produces an audit row carrying `action`, `oldValues` and `newValues` (`AuditLogObserver.php:94-101`), with `SENSITIVE_KEY_PATTERN` redaction; the two places that write outside a model (`app/Http/Controllers/Admin/UserManagementController.php:181,279`) record role/deactivation changes explicitly. The docx §28 C-8 records retention as indefinite with the measured row count |
| 10 | Private storage, no public links | Working (instrumented) | `ems:preflight` fails an open served disk or a blank `APP_KEY`; `ServedUploadPathTest` pins the HMAC gate; `config/trustedproxy.php` answers `TRUSTED_PROXIES` and `ems:preflight` refuses `*` |
| 12 | New schema through a new migration, never an edit to an applied one | Working | the migration list shows the additive pattern (`add_registrar_hold_state…`, `add_student_and_block_keys…`, `make_enrolledsubjects_blockid_nullable`) |
| 12 | Driver-guarded raw SQL so the SQLite suite runs | Working | project rule, enforced by the suite that passes on `:memory:` |
| 3.2 | Online/public scope must stay dormant | Working | `ApplicationMode{FaceToFace, Online}` exists and its **only** reference in the whole tree is the cast at `Admissions.php:27` — no route, controller or page reads it, so nothing is exposed |

---

## 2. The §4 inspection checklist, and what actually covers each line

The prompt lists sixteen things to look for. Seven of them have a dedicated, mutant-proved instrument in this repository; the rest were checked by hand in earlier lanes and are named here so the reader does not re-derive them.

| §4 line | Covered by |
|---|---|
| Orphaned routes / actions with no UI control | `lane45` (validated ID requests unreachable — found and fixed), audit lane 2 (`routes without authorization`, `JSX route names that do not exist`) |
| Controls that look active but do nothing | `lane46-filter-contract.php` (DEAD-SEND / NO-RETURN / FICTION / NO-ROUTE) |
| UI validation that hides server errors | audit lane 4, `DenialReason` + `errors/403.blade.php`, the `res.ok` sweep in every `fetch()` |
| Status labels that disagree with the state machine | `statusLabel.js` single vocabulary; `YearLevelVocabularyTest` pins PHP and JS against each other by reading the module source |
| Queue counters using different filters from their queue | `lane47-tile-scope.php` (SILENT-ZERO, PAGE-TILE, MIXED-SET, IMPOSSIBLE, DEAD-OPTION) |
| Permissions enforced only by hiding controls | audit lane 1 (permissions that gate nothing), `AbilityNameCollisionTest`, `PermissionMatrixTest` |
| Duplicate students / enrollments / receipts / workflow forms | `ems:audit:record-states` (19 rules), `lane44-unique-keys.php` (§23.6.1 must equal the server's nine unique keys) |
| Invalid or impossible state transitions | `EnrollmentStateMachine` as the only writer of `enrollmentstatushistory`; `DemoDatasetRecordStatesTest`; CI runs the walkthrough legs `--fail-on-skipped` |
| Missing audit records | `AuditLogObserver` — **re-read needed**: this pass did not verify that a *workflow-definition change* or a *crediting decision* is audited, because neither action exists yet (rows G3/G7 below) |
| File links failing through a private disk | `ServedUploadPathTest`, `ems:preflight`, the orphan-upload prune |
| Notification writers targeting the wrong model | `lane71-morph-targets.php` (three surfaces, one answer) |
| Missing/inaccessible workflow tracker | `WorkflowStepper` + `enrollmentworkflow`/`workflowsteps` on the Registrar and Student 360 pages — **the tracker is reachable**; the prompt's report that it was "hard to find" predates the office-desk rebuild |
| Print layout ≠ official paper | `ems:print-fidelity` (8/8 rendered), `Print-Fidelity-Comparison-2026-10-10.md`, `lane62-print-paper-probe.php` reads the PDF's own text |
| Schema changes affecting existing records | `lane60-ratio-claims.php` (census), `lane64-empty-table-inventory.php`, `lane43-delete-guards.php` (100 FKs, 70 of them refuse) |
| Select event/value contract mismatch | **Verified in this pass, and clean.** `resources/js/Components/ui/Select.jsx` documents at its top that `onChange` receives the selected **value**, not the DOM event; the shared component is used at 82 sites and 57 of them pass `onChange={(value) => …}`. The single `e.target.value` handler the grep found — `resources/js/Pages/Assessment/Show.jsx:432` — sits on a **native `<select>`** inside the scholarship modal, where the event form is correct. (Checked before being called a defect, which is what the repo's own rule asks: read the construct, not the pattern.) |
| No console errors in key workflows | proven in the browser in the desk-flow lanes; there is no JS test suite, so this one is a human walk |

---

## 3. Gaps this audit produced, in the prompt's own phase order

Risk is the prompt's §13 vocabulary. "Needs ruling" means the prompt's §3.1 forbids inventing the rule.

| Id | Gap | § | Phase | Risk | Smallest coherent fix | Verification | Needs a ruling? |
|---|---|---|---|---|---|---|---|
| G1 | The Registrar cannot see a student's grade history by academic year and semester — only the derived standing | 7.4 | 2 | High (the desk signs a load it cannot check against what the student already passed) | Expose the rows `AcademicStandingService` already fetches, **including** the current term and including ungraded rows as *no grade recorded*, server-side filtered by `academicYearId` + `semester`; one new public method on the service so the passing definition stays single-sourced | Feature test: rows from two terms, filter changes the set and is echoed back, a null grade never renders as a pass, a non-Registrar is refused | No |
| G2 | ~~No Draft/Incomplete intake state~~ **Closed 2026-10-26** | 7.1 | 2 | Medium | Built as §7.1's row describes. **One deliberate deviation from this row's original plan**: it said a draft "cannot generate requirement submissions", and the build seeds them anyway — `Admission/Show.jsx` renders the requirement *catalogue* and looks the submission up, so a case with no rows still offers an attach button and `submitRequirement()` answers that with a 404 at `firstOrFail()`. Seeding on the draft is also what makes §7.1's "resumed later" useful: the paper handed over next week attaches to a row that exists, without the case entering a queue. The helper is idempotent, measured on live `ems`, because `studentrequirementsubmissions` holds no unique key on (admissionId, requirementId). | `DraftIntakeTest` covers both halves of the promise; `mutate-g2-draft-intake-tests.php` (12 legs) proves each refusal can stop working, including a leg that removes the SQLite `CHECK` widen — the defect this batch actually met | No |
| G3 | No per-row crediting decision, reason or approver | 7.3 | 2 | High (a credit is a grade-affecting act) | Add decision columns to `creditedsubjects` by migration (status enum, decidedBy FK → staffusers, decidedDate, reason), keep `remarks`; totals computed, not stored | Test: a credit counts only when its own row is Approved **and** its transfer record passed; the approver and timestamp survive a re-save | Partly — which office approves (department head vs Dean) is SEAIT authority |
| G4 | An exam result is final the moment it is typed; no provisional → official step | 7.2 | 2 | High (the prompt names the Registrar as the final recorder) | Add an official-confirmation action + columns (`verifiedBy`, `verifiedDate`) leaving existing rows readable; every downstream gate asks the confirmed value | Test: an unconfirmed pass cannot satisfy an eligibility gate; only the Registrar role can confirm | Partly — the docx already ruled the exam *branching* (C-4/C-5); confirmation authority is the owner's |
| G5 | No timetable import | 7.9 | 4 | Medium | CSV first (no new dependency), dry-run preview with per-row errors, commit in one transaction; the existing student importer is the precedent | Test: a malformed row refuses the whole import; a conflict is named before publication | Format only — the prompt says "practical structured format first" |
| G6 | No admin-configurable versioned workflow | 6.3 | 3 | High effort | A definitions table (year/semester/studentType/ordered offices) + validation + preview + activate/retire, with `stepsFor()` reading the active version and existing instances keeping their snapshot rows | Test: changing a definition cannot reorder a signed instance; an invalid definition cannot be activated | **Yes** — the seven boxes are an owner ruling; making them configurable re-opens what "mandatory phases" means |
| G7 | The billing record is not called *Statement of Accounts* anywhere | 7.5 | 4 | Low | Rename on the desk surface and in any printed billing paper, in one commit that is revertable on its own; keep the DB names | The vocabulary test family + reading the re-rendered paper | No (the prompt states it), but print wording is owner-sensitive |
| G8 | Step states cannot say On Hold / Failed / Returned | 6.2 | 2 | Medium | Widen `stepStatus` with `onHold` and `returned` only once a reason-carrying write path exists; `failed` needs the institution's definition of a failed enrollment | Tests both directions: a held box blocks the next office and names why | **Yes** — what "Failed" means for a walk-in enrollment is not stated anywhere |
| G9 | Returnee classification | 5.2 | 2 | Low as code, High as policy | Expose as an institutional decision, with a backfill plan drawn but not run | — | **Yes** — the prompt itself says define it from SEAIT policy or expose it |
| G10 | A return carries no reason **category**, no recorded origin/destination, and is available only from the Registrar over a `paid` record | 6.4 | 2 | High (the prompt names Admission and Evaluation as returning offices too, and asks that a return "not disappear, reset the whole workflow, or go to a generic queue with no explanation") | One return mechanism rather than one special case: a `returns` record (origin office, destination office, category, note, actor, moment, previous and next enrollment status) written inside the existing `EnrollmentStateMachine::transition()` transaction, opened to Admission and Evaluation, with the destination derived from the configured step order and refused when the target step is already signed | Test each office both ways: a valid return re-opens exactly its target box and leaves the earlier signatures intact; an unknown destination or an empty category is refused with the reason named; and `enrollmentstatushistory` gains the matching trail row | Partly — which categories the school recognises, and whether Evaluation may return to Admission, are SEAIT process questions |

---

## 4. Future scope the prompt requires to stay un-built (§3.2)

All of it is currently dormant, which is the required state. Nothing in this pass activated any of it.

| Item | Where the scaffolding sits | State |
|---|---|---|
| Public online registration / remote document submission | public `/register` removed; staff accounts only | Not exposed |
| Student self-service / grade portal | no student-authenticated route exists | Not exposed |
| Online evaluation / remote approval queues | desks are staff-only Inertia pages behind office rights | Not exposed |
| Online payment gateway / remote proof-of-payment | `PaymentMode` + `payments` columns carry an offline cashier flow; no gateway dependency in `composer.json` | Not exposed |
| Remote clinic clearance / online-only completion | `ClinicRecordStatus` requires an in-person record | Not exposed |
| Automatic official enrollment from an online submission | `AdmissionStatus` + the seven-box workflow require desk acts | Not exposed |
| Provisional online enrollment promoted by staff only | `ApplicationMode::Online` exists as a dormant enum value, referenced only by its own cast (`Admissions.php:27`) | Kept readable, not activated |
| ID card production | explicitly out of scope; the ID desk validates only | Not built |

---

## 5. What is *not* a gap, and was checked so it is not re-litigated

The prompt's §2 "likely files" and §15 commands all resolve against the current tree: `EnrollmentStateMachine`, `WorkflowService`, `EnrollmentIssuer`, `AcademicStandingService`, `WorkflowInbox`, `CollegeScope`, `EnrollmentReadiness` are present and each is exercised by the suite; `php artisan test`, `npm run build`, `vendor/bin/pint --test`, `vendor/bin/phpstan analyse` all exist and pass (the last whole run: **810 passed / 5,489 assertions**, PHPStan 0 errors, Pint 392 files PASS, ESLint 0 warnings, Vite built, CI run 40211861004 `completed / success` on all three jobs). The static-analysis reach is narrower than it looks: `phpstan.neon` covers `app/` only and `pint.json` excludes `tools/`, so files under `tools/` are proven by running them, not by a gate.

---

## 6. Continuation plan

**Written after the same batch landed: G-1 is BUILT and verified; the rest of the list is unchanged.**
G-1 (§7.4) shipped in docx pass 249 with `AcademicStandingService::gradeHistory()`, the
`Registrar/Show` grade-history panel, `tests/Feature/Registrar/RegistrarGradeHistoryTest.php` (7 cases, 41
assertions, green) and `storage/app/scratch/mutate-g1-grade-history-tests.php` (5 legs: control green →
filter-ignored, null-as-pass and ceiling-forged each kill their own named case → control green again). Two
things this batch proved about itself rather than about the code: the new `'Year '.$id` fallback was caught by
`YearLevelVocabularyTest`'s own scan on the whole-suite run, and the first shift map was one row too high
because the probe counted newlines instead of comparing line arrays — the pass refused to write until both
were fixed.

1. **G1 now** — the grade-history panel. Read-only, no schema change, no policy invented, and it is the item the prompt lists as a Registrar need with the evidence already in the database.
2. **G2 done 2026-10-26** — the draft intake state, with the migration widening the enum on *both* engines (SQLite enforces `enum()` through a table `CHECK`, so a MySQL-only guard leaves the suite unable to write the new state).
3. **G3, then G4** — crediting decisions and exam confirmation, in that order, each with the authority question put to the owner first.
4. **G9, G8, G6** — packaged as one short decision sheet, because each is blocked on a SEAIT rule the prompt explicitly forbids guessing.
5. **G5, G7** — one commit each, revertable alone.
6. Every behaviour change is followed in the same batch by a pass on the authority docx, per the owner's 2026-09-29 ruling; the docx sweeps must be left at 0 problems.
