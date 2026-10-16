<?php

/**
 * Pass 35 — §28.2 reconciled with the build, the way §28.3's rows were in pass 30.
 *
 * §28.2 is the section that tells a reader what the team deliberately left to the school, so a row
 * there that describes a superseded build is worse than a stale row anywhere else: it advertises a
 * gap the system no longer has. C-1 was the worst of them — it stated there is no shift operation
 * at all, three sections after §13.7 documents one.
 */

require __DIR__.'/docx-edit-engine.php';

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$cellEdits = [
    // C-1 — answered by ruling 11 and built; the row denied the module existed.
    ['There is no designed shift operation',
        'There is no designed shift operation',
        'Answered by ruling 11 (2026-10-02) and built 2026-10-03, and the answer is no — Admission is nowhere in the path. §13.7’s paper is filed at Department Evaluation under shift.request.create, endorsed by the dean or program head under shift.sign.department, and granted by the Guidance councillor under shift.grant, which issues the receiving enrollment through EnrollmentIssuer. No route in that chain calls an Admission controller.'],
    ['There is no designed shift operation',
        'Registrar with Admission',
        'Answered — no decision outstanding. Recorded so the panel sees who signs a program change: the departments and Guidance, never Admission'],

    // C-2 — the standing is left null by the grant, not inherited from intake.
    ['Is a Shifter always Irregular',
        'Nothing forces academicStanding',
        'Nothing forces academicStanding = irregular. The grant writes studentType = shifter and enrollmentType = old and leaves the standing alone — enrollments.academicStanding is nullable with no column default, measured on live ems — so the receiving record arrives with no standing and the Registrar must state one at approval (§12.4). Whether a shift should by itself make a student irregular is still the departments’ answer, not the code’s.'],

    // C-4 — still true, but the one exemption the build carries is for shifters.
    ['exempt from the General Entrance Examination',
        'The examination policy does not distinguish applicant types',
        'The examination policy still does not distinguish applicant types: AdmissionPolicy::approvalBlockers asks only whether the course sets requiresEntranceExam, so a transferee applying to an examination-required program must pass the general examination like a first-year. The single exemption the build carries is EvaluationController::retentionBlocker returning null for an enrollment issued by a shift request — a program change inside SEAIT, not an arrival from another institution.'],

    // C-6 — the exemption is now enforced and tested on both sides.
    ['departmental examination on entry to the new program',
        'No. A shifter is assessed through credit evaluation at stage 6',
        'No. A shifter is assessed through credit evaluation at stage 6, and since 2026-10-03 that is enforced rather than implied: an enrollment granted by a shift request is exempted from the retention examination, and ShiftRequestFlowTest pins both halves — the exemption for the shift, and the same course and term still blocked when no shift paper stands behind it.'],

    // C-7 — the box removal is documented; what remains is the policy, not the mystery.
    ['Must a returning or shifting student',
        'WorkflowService removes the box for continuing',
        'The removed box is documented rather than merely commented: WorkflowService::stepsFor omits Assessment for continuing and shifter enrollments, and §22’s BR13 row and §27 carry that as the workflow’s own rule. On the money side the build settles the practical half — a grant is written against the assessment of the enrollment it covers (AssessmentController.php:246) and nothing copies last term’s grant forward — so a returning or shifting enrollment is uncovered until the Scholarship desk acts. Whether every term must be re-determined as a matter of policy is the open question.'],

    // C-10 — measured rather than inferred: the queue is not being worked on this install.
    ['Is a queue worker part of',
        'DEPLOYMENT.md describes one under Supervisor',
        'The queued work is real and it is two listeners: SendEnrollmentNotification and SendWorkflowNotification both implement ShouldQueue and both are registered in EventServiceProvider. QUEUE_CONNECTION is database, and measured on live ems on 2026-10-06 the jobs table holds 56 pending jobs while the only two notification rows carry the seeder’s own timestamp of 2026-08-30 — so nothing a desk does today has reached a bell, and no failure was visible. DEPLOYMENT.md describes a worker under Supervisor; whether one is part of the installed deployment is the owner’s answer, and until it is given, every notification claim in this document and in the acceptance script is a claim about the queue, not about a screen.'],
];

docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-06-pass35.docx', [], $cellEdits, $apply);
