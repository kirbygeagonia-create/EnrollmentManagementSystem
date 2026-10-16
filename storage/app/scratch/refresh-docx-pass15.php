<?php

/**
 * Pass 15 — the stage, desk and role rows that still state what the build used to do:
 * the year level written once, Admission as the only creation path, the vacuous clearance
 * gate, the term rows that point at the retired Summer demo term, and the OfficeHead
 * matrix after ruling 7.
 */

require __DIR__.'/docx-edit-engine.php';

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$textEdits = [];

$cellEdits = [
    // ---- §13 stage 3: the fields approval writes.
    ['Every admitted student — including a Transferee', 'Hard-coded',
        'Derived from the student’s own record — Enrollments::derivedYearLevel(student, term) at approval (G-2, closed 2026-10-04): one plus the academic years that CLOSED before the entered year opened. The curriculum version is pinned in the same act (Curriculums::currentFor), so the subjects, the load band and the fee sheet are all priced against a catalog that does not move when the catalog is amended.'],
    ['Every admitted student — including a Transferee', 'Every admitted student',
        'An admitted student is placed where their own record puts them. For a first-year that is still year 1 — the same answer the old constant gave, arrived at instead of asserted — and for a student returning after a year, or credited with one, it is not. The department may still place the student elsewhere when it signs; what no longer exists is a level written once and never revisited.'],

    // ---- §13 stage 3, per student type: the continuing path.
    ['no code path that creates one', 'Not Applicable — and not reachable',
        'Required — and reachable'],
    ['no code path that creates one', 'A returning student does not pass',
        'A returning student does not pass the admissions desk again, and no longer needs to: Department Evaluation issues the next term’s enrollment (evaluation.store, ruling 2), and a granted shift request issues the receiving program’s. Both go through EnrollmentIssuer, so admission approval is no longer the only place an enrollments row is created, and the one-seat rule is enforced by the same helper at all three.'],

    // ---- §13 stage 6 (Evaluation) per type: the transferee’s level.
    ['The crediting works; the year level does not follow', 'This is the stage where a transferee',
        'This is the stage where a transferee’s year level and standing are established. The crediting works, and the level now follows the record and the department in that order: approval opens the student at the derived level, and the department’s placement — captured with the standing when it signs — moves them to the year its evaluation of transferred credit means. A credited subject also counts as satisfied for the prerequisite gate, which is the practical effect the transferee feels: the subject that waits behind the one they brought is offered.'],

    // ---- §13 stage 9 (Registrar) per type: the transferee’s clearance reading.
    ['Same vacuous clearance gate', 'Same vacuous clearance gate',
        'The clearance gate is no longer vacuous. EnrollmentReadiness::clearanceVerdict() asks for an accepting window (open or extended), an approved slip with the Registrar desk’s receipt, and the Department Evaluation confirmation of the pass slip the student handed in — rulings 4 and 5 — and the verdict is read by the checklist, the policy and both desks’ screens. A transferee still has no prior SEAIT term to clear, so their first record passes because the type says so, and the level certified on it is the derived or placed one rather than an uncorrected 1. Their enrollmentType is certified new, because it is their first enrollment here.'],

    // ---- §13 stage 10 (Blocking): first-year and transferee block matching.
    ['the section their whole cohort shares', 'Receives the year-level block',
        'Receives the year-level block for their program: the section their whole cohort shares. Block matching reads Enrollments.yearLevel, which is now the level the record supports — derived at approval, adjustable by the department — rather than a constant that happened to be right for this type.'],
    ['Placed in second year by the department but stored as first year', 'Placed in second year',
        'Closed with G-2: a transferee placed in second year is offered second-year blocks, because the level the block search and the curriculum slice read is the level the record carries, and the department writes that placement before Blocking runs. Checking the confirmed subject list against the section is still the desk’s own comfort, but the mismatch this row recorded is gone.'],

    // ---- §13 stage 11b (ID): the level printed on a card.
    ['issues a new card regardless of prior attendance', 'Also newStudent',
        'Also newStudent — SEAIT issues a new card regardless of prior attendance elsewhere. The card prints the year level the record carries, which since G-2 closed is the derived or placed level rather than the uncorrected 1, so a transferee’s card no longer says first year while their subjects say second (§25).'],

    // ---- §21 reference data: the clearance window and the term list.
    ['refuses to generate a slip unless a period is open', 'ClearanceController.php:127-178 refuses',
        'The question “is clearance season?” has one answer: Clearanceperiods::accepting() — a window that is open OR extended — read by ClearanceController and ClearancePolicy when a slip is generated, by RegistrarController and RegistrarPolicy when a record is certified, and by both seed tools. No accepting window is a refusal, not a pass: the Registrar gate stops and says so, which closes the vacuous-truth reading this row used to record. Closing a window is refused while any clearance is still pending, with the count named, and an extension is reachable only through its own action.'],
    ['term 18 is the Summer term the E2E walkthrough reads', 'Yes, 6 terms',
        'Yes, 9 terms'],
    ['term 18 is the Summer term the E2E walkthrough reads', 'Terms 1-3 = 2024-2025',
        'Terms 1-3 = 2024-2025, 10/11/18 = 2025-2026, 19/20/21 = 2026-2027 on the same June-start pattern. The demo dataset sits term 11 — AY 2025-2026, 2nd semester — because that is a term the pinned curriculum actually offers subjects in at the year levels the students sit (ruling 1, closing G-9). The end-to-end walkthrough enters a first-year at term 19, the 1st semester of the year the calendar covers today, and gives its continuing student a graded year behind them, so the load it proposes is one the desk could lawfully propose.'],
    ['Yes — term 18 open', 'Yes — term 18 open',
        'Yes — the seeded window sits the demo term (termId 11) and is open'],

    // ---- §22 the role matrix: OfficeHead after ruling 7.
    ['Office Head with all view permissions', 'Office Head with all view permissions',
        'Office head of their own counter: every view permission plus the acts a counter performs. Ruling 7 (built 2026-10-03) took the five signature acts out of this role — enrollment.approve, admission.approve, evaluation.sign, clinic.sign and id.sign now sit with the desk that owns each box — and left the counter work: blocking, the clearance window, slip and receipt, payment.record, assessment.compute and finalize, Evaluation create/capture/propose/credits, admission intake and verification, the three print rights, and the student-data record. clearance.approve stayed deliberately: it signs a requirement line scoped to the owning office, and no desk role holds clearance.view.'],
    ['Office Head with all view permissions', '49',
        '43'],
    ['Office Head with all view permissions', 'admission.view, exam.view',
        'admission.create, admission.reject, admission.requirements.submit, admission.requirements.verify, admission.update, admission.view, assessment.compute, assessment.finalize, assessment.view, audit.view, block.assign, block.manage, block.schedules.manage, block.view, clearance.approve, clearance.periods.manage, clearance.receipt.record, clearance.slip.generate, clearance.view, clinic.record, clinic.reopen, clinic.update, clinic.view, dashboard.view, enrollment.studentdata.record, evaluation.create, evaluation.credits.process, evaluation.profile.capture, evaluation.subjects.propose, evaluation.view, exam.view, id.request.create, id.validate, id.view, payment.record, payment.report.daily, payment.view, print.certificate, print.classCard, print.subjectLoad, refdata.view, students.view, user.view — read from role_permissions on the live install, 2026-10-04'],

    // ---- §22 the permission that no role held.
    ['The ₱100 lost-slip replacement', 'The ₱100 lost-slip replacement',
        'The lost-slip replacement is now held by the desks that perform it: AccountingStaff and RegistrarDesk, not SysAdmin alone. ClearancePolicy::replaceLostSlip scopes the act to offices 1 and 2, and the fee it files comes from Reference Data’s fee type rather than a config default — a permission no policy path honored is a dead grant, which is what this row recorded.'],

    // ---- §22 the approval authority row.
    ['seven gates, including the clearance check', 'RegistrarController.php:140-203',
        'RegistrarController::approve() — seven gates, including the clearance verdict, the admission-document verification and prerequisite validity. The right is enrollment.approve, held by RegistrarApprover (and by SysAdmin and Admin through their blanket grants); OfficeHead no longer holds it, so a head of another office cannot certify a record — the office boundary and the desk role are now the same statement (ruling 7).'],

    // ---- §22 the student-type matrix note.
    ['every mutation an office may do for any student type', 'Same. Note that OfficeHead is granted',
        'Same. OfficeHead is no longer granted admission.approve, enrollment.approve, evaluation.sign or id.validate (ruling 7); it keeps the counter acts — blocking, the clearance window, slip and receipt, payment.record, assessment.compute and finalize, the three print rights — so what a head of one office may do at another desk is still decided by the office-scoped policies in §22.7, but the signature on a box belongs to exactly one desk.'],

    // ---- §25/§29: the print render path.
    ['Partial — code path unreachable from a desk', 'PrintService::generatePdf() exists but no route returns it',
        'Every desk print has its own route and renders a real PDF through Browsershot, writing its own documentprintlog issue row first, so the paper is traceable; php artisan ems:print-fidelity renders the set from live data and the unreferenced queue paths were removed rather than wired.'],
    ['Partial — code path unreachable from a desk', 'Partial — code path unreachable',
        'Yes — verified end to end: 8/8 documents render from the live database'],
];

docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-04-pass15.docx', $textEdits, $cellEdits, $apply);
