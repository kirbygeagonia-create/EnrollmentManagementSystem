<?php

/**
 * Pass 14 — the §28 register rows the build closed, the §27 edge rows, and the four
 * data-dictionary cells that now state values the columns do not hold.
 *
 * Replaces passes 12 and 13, which were run with the row-relative offset bug: the writes
 * landed in the wrong cells of the same rows. The document was restored from git before
 * this pass, and the engine guard is what now protects the write.
 */

require __DIR__.'/docx-edit-engine.php';

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$textEdits = [
    ['enum(paid|partial|pending)', 'enum(paid|partial|pending|refunded)'],
    ['enum(pending|validated|released|cancelled)', 'enum(pending|validated)'],
    ['enum(active|revoked|expired)', 'enum(active|revoked|expired) — all three reachable: Assessment grants, and a named withdrawal decision revokes or expires'],
    ['Generated: no column is listed that the server does not have, and none that it has is omitted',
        'Generated: no column is listed that the server does not have. Read against information_schema on 2026-10-04, then checked back against the migrations that landed after the previous edition of this chapter: the refund trio on payments (refundedAt, refundedBy, refundedReason), the withdrawal trio on studentscholarships (statusChangedBy, statusChangedAt, statusReason), enrollments.dropReason, enrollments.clearanceConfirmedBy and clearanceConfirmedAt, the pinned enrollments.curriculumId, documentprintlog.studentId and blockId, and idrequests.mismatchRemark — plus one whole table this chapter has not yet listed, shiftingrequests. Each is described where the behaviour is documented (§12-§22) and named in §28 by the class or migration that added it, but their rows are NOT yet re-listed in these tables, so the counts above are the 2026-10-04 reading minus those additions. Rebuilding the listings is stated here rather than left for a reader to discover.'],
];

$cellEdits = [
    // ================= §28.3 gap register: G-1 .. G-9, D-1, X-4, C-11
    // G-1 — the returning student's enrollment has a desk that issues it.
    ['No code path creates an enrollment for a returning student', 'No code path creates an enrollment',
        'No code path created an enrollment for a returning student'],
    ['No code path creates an enrollment for a returning student', 'Still open. AdmissionController',
        'Closed 2026-10-03 by ruling 2. Department Evaluation issues the returning student’s enrollment (evaluation.store, on the evaluation.create right that had been written and unused for months), and every desk-created enrollment now goes through one service — App\Services\EnrollmentIssuer — so the seat rule and the workflow form are built the same way whether the paper starts at Admission, at Evaluation, or as a granted shift request. Stages 1-3 remain the path for a first-year or transferee applicant; a continuing student no longer needs a row made by hand.'],
    ['No code path creates an enrollment for a returning student', 'Developer (functional gap',
        'Closed in code'],

    // G-2 — the level is derived from the record at issue.
    ['yearLevel is captured by hand and still never promoted', 'yearLevel is captured by hand',
        'yearLevel was asserted at creation and promoted by nothing'],
    ['yearLevel is captured by hand and still never promoted', 'Nothing promotes the level from semesters',
        'Closed 2026-10-04. Enrollments::derivedYearLevel() answers the question from the student’s own history: one plus the number of academic years that CLOSED before the year being entered opened, counting only enrollments that reached enrolled, because a term still being worked is not a year finished and a dropped one never happened. Admission approval stamps it, the issue form opens on it, and the semester half of the question was already automatic — a term row carries its own semester, so no screen ever asked a person for it. Two things the rule deliberately does not do: it does not advance a student through the 2nd semester of the year they are already in (measured on the live dataset, that misread two records), and it does not overrule a desk — decideStanding still takes a placement, because a transferee’s or a retake’s year is an academic judgement the record does not hold. The demo tool now refuses to seat a year it cannot evidence, holds one level per academic year, and prints “Evaluation — level the record does not support” on every run.'],
    ['yearLevel is captured by hand and still never promoted', 'Developer (automatic promotion)',
        'Closed in code; the desk may still place a student elsewhere'],

    // G-3 — clearance is one verdict, read by every desk that acts on it.
    ['The clearance prerequisite is a controller check, not a schema rule', 'The clearance prerequisite is a controller check',
        'The clearance prerequisite was a controller check, not a schema rule'],
    ['The clearance prerequisite is a controller check, not a schema rule', 'Only the Registrar tests clearance',
        'Closed 2026-10-03 by rulings 4 and 5. One method answers the question — EnrollmentReadiness::clearanceVerdict() — and it is read by the Registrar checklist, by RegistrarPolicy (which therefore refuses with the reason on the record page, not a session error), and by the Department Evaluation desk that confirms the pass slip the student hands it. Clearance season is asked of Clearanceperiods::accepting(), so an extension counts and “no window open” is a refusal rather than a silent pass; the Registrar gate now stops when no window is accepting at all.'],
    ['The clearance prerequisite is a controller check, not a schema rule', 'Developer; the Registrar sets the policy',
        'Closed in code'],

    // G-4 — one seat, one active enrollment, guarded at every creation point.
    ['One active enrollment per student per term is not enforced', 'One active enrollment per student per term is not enforced',
        'One active enrollment per student per term was not enforced'],
    ['One active enrollment per student per term is not enforced', 'No unique index on',
        'Closed 2026-10-03 by ruling 3, in code and not in the schema: EnrollmentIssuer::seatHolder() is consulted at every creation point (the desk issue, the shift grant, and Admission approval, which used to look up by admissionId only), so a second active record for one seat is refused by name. dropped releases the seat, which is what lets a student the Registrar dropped be enrolled in the same term again. The Registrar chose the guard over a unique index, so nothing here rejects a row written directly to the table.'],
    ['One active enrollment per student per term is not enforced', 'Developer, after the Registrar confirms',
        'Closed in code; the Registrar ruled against a unique index'],

    // G-7 — the program change is a paper with three signatures.
    ['There is no program-change action anywhere', 'There is no program-change action anywhere',
        'There was no program-change action anywhere'],
    ['There is no program-change action anywhere', 'A shift appears only as a',
        'Closed 2026-10-03 by ruling 11. Shiftingrequests is an application-shaped paper with its own status (pending → endorsed → granted | rejected) and three signature pairs: the department that endorses it, the Guidance councillor who grants it, and the enrollment the grant opens. Order is enforced by the status, not by trust — decide requires endorsed, endorse requires pending. The grant issues the receiving enrollment through the same issuer, retires the record being left when it sits in the same term (so a block seat is not held twice), and refuses when any other active enrollment already holds that seat. The paper carrying both enrollment ids is the history row; and because a program change is now a named act, an ordinary issue that simply moves to a different course is refused with the shift flow named.'],
    ['There is no program-change action anywhere', 'Developer with the Registrar',
        'Closed in code'],

    // G-8 — the examination no longer decides the application.
    ['An approved application with no enrollment row is invisible to the next desk', 'The Evaluation queue selects on enrollmentStatus',
        'Closed 2026-10-03. Recording a course-specific result no longer writes admissionStatus = approved: the examination records the department’s opinion and stops, and approval by the Admission office is the single act that creates the enrollment — the same boundary the general stage keeps. An approved application with no enrollment row is therefore not a state the desks can reach; a fail at either stage rejects the paper, and ApprovalReadinessTest pins both readings.'],
    ['An approved application with no enrollment row is invisible to the next desk', 'Developer',
        'Closed in code'],

    // G-9 — the demo sits a term the curriculum covers, with curriculum loads.
    ['The demo term offers no curriculum subject at the level and semester most active enrollments sit', 'The demo term offers no curriculum subject',
        'The demo term offered no curriculum subject at the level and semester most active enrollments sat'],
    ['The demo term offers no curriculum subject at the level and semester most active enrollments sit', 'Measured against ems on 2026-10-02',
        'Closed 2026-10-04 by ruling 1. The dataset moved off the Summer term onto AY 2025-2026 2nd semester (termId 11), which the pinned curriculum does cover, and the clearance window moved with it; every load the demo tool seats is now resolved through the desk’s own lookup, so the subjects printed are subjects that program offers at that level and semester. Measured, not claimed: the counter that printed 17 prints 0 and the five “offers no subject at that level and semester” warnings are gone, while the placeholder rows the earlier editions carried are retired and reseeded. Two counter-checks stay in the report so the condition cannot silently return: “enrolled at a level/term the curriculum offers nothing for” and “level the record does not support”. Which subjects a genuine special term offers is still the Registrar’s to state — the calendar now reaches AY 2026-2027, and the demo simply no longer lives in a Summer term.'],
    ['The demo term offers no curriculum subject at the level and semester most active enrollments sit', 'Registrar (which subjects a special term offers)',
        'Closed by the demo owner’s ruling; the Registrar still owns any real special term'],

    // D-1 — the legacy Clearance office is retired in data.
    ['Registrar (naming) and developer (data)', 'A seeded office named',
        'A seeded office named “Clearance” existed as data only'],
    ['Registrar (naming) and developer (data)', 'offices id 8 and its clearancerequirements row',
        'Closed 2026-10-04 by ruling 12. The seeder retires office 8 in foreign-key order — its staff, then the approval rows pointing at its requirement, then the requirement, then the office — and the one account seated there moves under the Registrar (office 1) and is retitled. Live ems after the change: 9 offices, 9 clearance requirement lines each carrying its own name, no orphaned approvals, and every clearance still carries a full set of lines. There is no office 8 left for ClearancePolicy to honor; the Clearance counter’s acts are the Registrar’s, which is what §12 has always said. The account keeps its username — renaming a login is a separate decision from retiring an office.'],
    ['Registrar (naming) and developer (data)', 'Registrar (naming) and developer (data)',
        'Closed in data and code'],

    // X-4 — OfficeHead is a counter role, not a signature role.
    ['An OfficeHead of any office holds', 'Whether OfficeHead is meant to be a generalist',
        'Whether OfficeHead is meant to be a generalist — ruled, and it is not'],
    ['An OfficeHead of any office holds', 'An OfficeHead of any office holds',
        'Ruled 2026-10-02 (#7) and built 2026-10-03. The five signature acts — enrollment.approve, admission.approve, evaluation.sign, clinic.sign, id.sign — are gone from OfficeHead and sit with the desk that owns each box (RegistrarApprover, AdmissionOfficer, DeptEvaluator with Dean and ProgramHead, ClinicStaff, IdOfficer). Everything a head does at their own counter stayed: blocking, the clearance window and slip and receipt, recording a payment, computing and finalizing an assessment, capturing and proposing and crediting at Evaluation, admission intake and verification, the three print rights, and the student-data record. Live proof that this removed cross-desk reach and nothing else: the eleven accounts holding OfficeHead each still hold the sign right of their own office, because the seeder pairs an office head with their desk role. One deliberate exception, confirmed by the owner rather than assumed: clearance.approve stayed on OfficeHead, because it is a requirement-line signature scoped to the owning office and no desk role holds clearance.view — moving it would leave the act reachable by nobody.'],
    ['An OfficeHead of any office holds', 'Project owner with the Registrar',
        'Closed by ruling 7; the clearance.approve exception is the owner’s confirmed choice'],

    // C-11 — the dead status values were ruled item by item.
    ['Should the status values no code produces be given behaviour or removed from the schema', 'Eleven values are accepted',
        'Ruled 2026-10-02 (#13, #15, #16) and built. Given behaviour: payments.partial (a receipt below the balance records partial and holds the account up, and Payments::held() is the one way to ask whether money is still in the drawer), studentscholarships.revoked and .expired (a named withdrawal decision with a reason, and coverage recomputed from the grants still standing at their own percentage), clearanceperiods.extended (reachable only through the extend action, never the status radio, and it accepts slips the way an open window does). Removed from the schema because no rule could reach them: idrequests.cancelled and idrequests.released, together with the release action itself. Kept after being challenged: clinicrecords.reopened — it is reachable, tested and use case 11.4.1, and zero live rows only means the demo never used it.'],
    ['Should the status values no code produces be given behaviour or removed from the schema', 'Project owner with the developer',
        'Closed by the owner’s rulings; built'],

    // ================= §27.2 edge rows E-1 / E-4: the zero-balance deadlock
    ['An assessment settles to a zero balance', 'No payment can be recorded',
        'Closed. Accounting may settle an assessed load that owes nothing (settleNoBalance, gated on payment.settle and a balance of zero or less) without inventing a receipt, and the fee sheet states on the paper that the account was settled with no receipt, naming the hand and the hour off the Accounting workflow step. A recorded payment still cannot be ₱0, so the only path to paid with no money is the explicit settlement — ruling 9, and the reason the note is printed rather than inferred.'],
    ['An assessment settles to a zero balance', 'PaymentPolicy.php:46-49 requires',
        'AccountingController::settleNoBalance(), PaymentPolicy::settle(), and Studentassessments::outstandingBalance() read through Payments::held(); the settlement note is settlementWithoutReceipt on the fee sheet'],
    ['That returned enrollment has to come back through the pipeline', 'finalize only fires from evaluated',
        'Closed with E-1: settlement is available at assessed, which is exactly where a returned or refunded load arrives once the department re-costs it, so a re-evaluated zero-balance account no longer needs a receipt it cannot have. A refund that reopens the account moves paid|enrolled back to assessed for the same reason.'],
    ['That returned enrollment has to come back through the pipeline', 'AssessmentController.php:274-276',
        'AccountingController::settleNoBalance(), AccountingController::refund() (paid|enrolled → assessed), EnrollmentStateMachine'],

    // ================= §25 / P-12: the printed fee and the charged fee are one number
    ['The slip footer states a ₱100.00 replacement fee from a config default', 'The slip footer states',
        'Closed 2026-10-03. Reference Data owns the figure: Feetypes::clearanceSlipReplacementFee() returns the fee type row or null, with no config default and no hard-coded 100 anywhere. The desk refuses to replace a slip while the fee row does not exist and names which row to create; the printed footer prints the fee type’s amount and prints nothing until it does — so paper and payments cannot disagree, and a school that charges ₱150 says ₱150.'],
    ['The slip footer states a ₱100.00 replacement fee from a config default', 'clearance-slip.blade.php:95',
        'Feetypes::clearanceSlipReplacementFee(); clearance-slip.blade.php prints the fee type’s defaultAmount and prints nothing when the row is absent'],
    ['The slip footer states a ₱100.00 replacement fee from a config default', 'Policy question',
        'Closed in code; the amount is maintained in Reference Data'],
];

docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-04-pass14.docx', $textEdits, $cellEdits, $apply);
