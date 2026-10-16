<?php
/**
 * Pass 54 — Assessment and Admission citations, and the two paragraphs that still describe G-1
 * as an open hole.
 *
 * Maps measured from the current files. AssessmentController: index :41-83, show :84-115,
 * compute :116-185 (its already-exists redirect at :123, the row written at :162-166),
 * applyScholarship :186-277, withdrawScholarship :278-367, adjustCharges :368-409,
 * finalize :410-430 — the only act that moves evaluated → assessed. AdmissionController: store
 * :96-235, submitRequirement :287-326, verifyRequirement :327-347, approve :348-413 (seat guard
 * :364, Enrollments::create :377). Across app/ there are exactly two Enrollments::create sites:
 * that approval, and EnrollmentIssuer::issue() at app/Services/EnrollmentIssuer.php:73.
 */
require __DIR__.'/docx-edit-engine.php';

$src = 'Documentation/EMS Complete Documentation.docx';

$textEdits = [
    // The status edge belongs to finalize, not to the scholarship withdrawal below it.
    ['AssessmentController.php:280', 'AssessmentController.php:410-430'],
    ['AssessmentController.php:161', 'AssessmentController.php:162-179'],

    // Re-running compute reports the existing assessment; the redirect lives in compute().
    ['AssessmentController.php:104-107', 'AssessmentController.php:123', null, 2],
    ['AssessmentController.php:104-109', 'AssessmentController.php:120-123'],

    // finalize fires only from evaluated.
    ['AssessmentController.php:274-277', 'AssessmentController.php:410-430'],

    // The charges line and the aid cap.
    ['AssessmentController.php:100', 'AssessmentController.php:162-166'],
    ['AssessmentController.php:98-190', 'AssessmentController.php:186-277'],

    // Approval creates the enrollment after the seat guard, inside approve().
    ['AdmissionController.php:308-341', 'AdmissionController.php:364-410'],
    ['AdmissionController.php:332', 'AdmissionController.php:377-410'],
    // The fee is computed from the proposed load’s units: read at :126, multiplied at :137-138.
    ['AssessmentController.php:113-117', 'AssessmentController.php:126-138', null, 2],
    ['AssessmentController.php:113-123', 'AssessmentController.php:126-138', null, 2],
    ['AdmissionController.php:364-374', 'AdmissionController.php:364-410', 'No enrollment history for direct status assignment'],
];

$cellEdits = [
    // §29.2 — the count this test performs, now that there are two create sites by design.
    ['An enrollment row comes into existence only when admission approves',
     'Admission\\AdmissionController.php:364-374',
     'Admission\\AdmissionController.php:377 and app/Services/EnrollmentIssuer.php:73 — the two Enrollments::create sites in app/', 1],
    ['An enrollment row comes into existence only when admission approves',
     'An enrollment row comes into existence only when admission approves',
     'Closed 2026-10-03. An applicant’s first enrollment is created when Admission approves; every later one is issued by EnrollmentIssuer — by Department Evaluation for a returning student (G-1, ruling 2) and by the granted shift request for a program change (G-7, ruling 11). A returning student no longer needs a row made by hand, and an approved application with no enrollment is not a state a desk can reach (G-8).', 1],
    ['An enrollment row comes into existence only when admission approves',
     'Count Enrollments::create across app/',
     'Count Enrollments::create across app/ — two, and each is named above. Any THIRD site is the smell: it would re-open G-4 unless it asks EnrollmentIssuer::seatHolder() first, and it would change the branch table in §7.4.', 1],
];

$paraEdits = [
    // §10 — the gap paragraph, rewritten now that the gap is closed.
    ['Implementation gap G-1',
     'Enrollment rows are created in exactly two places, and both are desks reopening a record. An applicant’s first enrollment comes from the admission approval ( AdmissionController.php:364-410 — the seat guard at :364, the create at :377). Every other enrollment — a returning student’s next term, and the receiving record of a granted program change — is issued through App\\Services\\EnrollmentIssuer::issue() ( app/Services/EnrollmentIssuer.php:73 ), called by evaluation.store (G-1, closed 2026-10-03 by ruling 2) and by the shift grant (G-7, ruling 11). So the “continue to next term” action this paragraph said did not exist anywhere in app/ exists, it belongs to Department Evaluation, and the demo dataset no longer creates its returning records by hand (§28.3, G-1; the ladder above is now the path a first-year takes, not the only path there is).'],
];

$apply = in_array('--apply', $argv, true);
docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-07-pass54.docx', $textEdits, $cellEdits, $apply, $paraEdits);
