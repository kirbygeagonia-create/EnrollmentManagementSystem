<?php

/**
 * Pass 18 — the passages that still describe the build as it was before the rulings: the
 * examination that used to approve on its own, the gate that passed because the curriculum
 * was empty, the "code writes nothing" rows for values that are now produced, and the
 * clearance checklist's legacy office.
 */

require __DIR__.'/docx-edit-engine.php';

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$textEdits = [
    ['all ten clearance signatures', 'all nine clearance signatures', null, 2],
];

$cellEdits = [
    // §13 stage 3, per student type — the transferee.
    ['year level and standing are supposed to come from the evaluation of transferred credit',
        'Approval creates the enrollment, but the hard-coded values',
        'Approval creates the enrollment at the level the student’s own record implies — derived, not asserted (G-2 closed) — and deliberately leaves academicStanding unset, because whether the student is regular or irregular is an academic judgement the department makes from the grades on file. Standing and the placement are established at stage 6 from Transfer Credentials and the credited-subject mapping, and the level the curriculum slice and the block search then read is the one the record supports rather than the one intake guessed.'],

    // §25 P-9 — the seeded checklist’s legacy office.
    ['seeded clearance checklist includes office id 8', 'The seeded clearance checklist includes office id 8',
        'Closed 2026-10-04 by ruling 12. The seeder retires office 8 in foreign-key order (its staff, then the approvals pointing at its requirement, then the requirement, then the office) and seeds the checklist from ProvisionalClearanceRequirements::NAMES, so every line carries its own name and every line belongs to an office that exists. Live ems reads nine offices and nine named requirement lines, and no clearance carries an unnamed line.'],

    // §27.5 — the idrequests row: the values were removed rather than given behaviour.
    ['The ID desk has no cancel action', 'idrequests.status',
        'idrequests.status = “cancelled” and “released” — REMOVED from the column'],
    ['The ID desk has no cancel action', 'The ID desk has no cancel action',
        'Ruled 2026-10-02 (#13) and built: validation is terminal, and the cancel and release actions are gone end to end — route, controller, gate, policy, enum cases and the release permission with them. The live column held only pending and validated when the change was measured, so nothing needed repairing; a regression test now pins that no path writes any other value.'],

    // §27.5 — the scholarship row: a withdrawal decision exists.
    ['A scholarship cannot be withdrawn after award', 'no code — the only create writes active',
        'ScholarshipOfficer through assessment.scholarships.withdraw — AssessmentController::withdrawScholarship() is the only writer of revoked and expired, and the award path still writes active.'],
];

$paraEdits = [
    // §10.6 — the bypass the examinations used to create.
    ['When the course-specific examination auto-approves an application',
        'The bypass this creates, and how it was closed. Recording a course-specific result used to write admissionStatus = approved without running the creation step: the application read as approved, no enrollment existed, the Department Evaluation queue — which selects on enrollmentStatus — never saw the student, and AdmissionPolicy::approve only accepts a PENDING application, so the Admission office could not correct it either. Ruling 1’s boundary is now enforced at both examination stages: the desk records the result and stops, and approval by the Admission office is the single act that creates the enrollment. An approved application with no enrollment row is not a state the desks can reach; ApprovalReadinessTest pins the refusal and the pass for each stage, so this is no longer a condition staff must check for after every examination session.'],

    // §16 gate 7 — the vacuous prerequisite reading on the demo term.
    ['It also reads true when there is nothing to read',
        'Enforced at the gate, but the screen is not the proof: a load decided before the curriculum moved, a retake, or a direct proposal can clear the Evaluation screen’s lock and still miss the rule, so this desk reads the record (EnrollmentReadiness::prerequisitesMet) rather than the button. It judges against the curriculum the enrollment is pinned to, and a subject counts as satisfied by a passing grade or by a credit transfer whose transfer record passed (ruling 10) — a failed transfer line leaves the subject behind its prerequisite. The vacuous reading this row used to carry is closed with G-9: the installed dataset sits AY 2025-2026 2nd semester, where every enrolled student’s program offers subjects at their year level, so the gate has something to weigh. The demo pipeline prints “Evaluation — enrolled at a level/term the curriculum offers nothing for” on every run, which is what keeps the emptiness from coming back unnoticed.'],

    // §27.5 — payments.partial is produced now.
    ['Installments are stored as ordinary',
        'Closed by ruling 13 (built 2026-10-03). A receipt below the balance is stored as partial and the account still owes; Payments::held() — paid OR partial — is the single definition of money still in the drawer, read by the outstanding balance, the scholarship application, the charge adjustment, the withdrawal recompute, the dashboard, the daily report and the refund gate. The Registrar’s payment gate deliberately stays paid-only, because remainingBalance at or below zero already covers an account closed by installments.'],

    // §27.5 — studentscholarships revoked/expired are produced now.
    ['A scholarship cannot be withdrawn after award',
        'Closed by ruling 15. A grant is withdrawn by a named decision — revoke or expire — with a required reason of 10 to 500 characters and who/when written on the row; the grant is never deleted. Coverage is then recomputed from the grants still standing at their own percentage of the assessed total, so the answer does not depend on the order the grants were keyed, and the account owes again even after the Registrar has approved it. The trail states the reason on the scholarship row.'],

    // §27.5 — the info flash key.
    ['the front end never reads it',
        'Partly closed. HandleInertiaRequests now shares the info key alongside success, warning and error, and a screen that reads it renders an info band — Blocking/Show is the screen that does. The gap that remains is per-screen rather than global: a controller that flashes info to a page which never reads the key still loses the message silently, which is why the info-reason items stay listed in §28 as front-end work instead of being reported as fixed here.'],

    // §27.5 — the section’s own count of dead values.
    ['Eleven values are accepted by their columns',
        'The rows below are the values the columns still accept and the code still does not write, read against the build of 2026-10-04. Several entries the previous edition carried are no longer dead: payments.partial, clearanceperiods.extended, studentscholarships.revoked and .expired and transferacademicrecords.failed are each written by a real desk action now (rulings 13, 16, 15 and 10), and idrequests.cancelled and idrequests.released were removed from the column rather than given behaviour. What is left in this table is therefore a vocabulary note for whoever reads the schema — the status register in §26.5 is generated from the same enums, and a value no code writes is not a state the application can be in.'],
];

docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-04-pass18.docx', $textEdits, $cellEdits, $apply, $paraEdits);
