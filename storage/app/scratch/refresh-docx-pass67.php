<?php
/**
 * Pass 67 — ruling 2026-10-08: an examination result answers for the program and term printed on
 * it, not for the student anywhere.
 *
 * Admissions::examResults() joins on studentId alone, so the approval blockers were reading a
 * general or departmental pass earned against one program as clearing an application to another.
 * examresults rows carry courseId and termId — the data always said which paper the result belongs
 * to; the blockers simply were not asking. Both lookups are scoped now, the missing-general blocker
 * names the program it is talking about, and ApprovalReadinessTest pins it: a pass under a different
 * course still refuses, the same program's row clears.
 *
 * Measured on the demo install before the change: no student holds a result for more than one
 * course, so tightening the rule moved nobody — which is why this is a correctness fix rather than a
 * policy shift, and why the UAT script says so out loud rather than leaving §18's limit standing.
 *
 * The ranges moved again because the policy grew: every AdmissionPolicy citation re-measured
 * (method 67-152, examination region 108-149, general block 111-126, fail branch 122-125,
 * departmental block 134-149). B-2's effect cell was still claiming a failed general result sets
 * the pending admission to rejected — the last surviving copy of the behaviour G-8 removed, missed
 * by the earlier sweep because that sweep keyed on the file name, not the claim.
 */
require __DIR__.'/docx-edit-engine.php';

$src = 'Documentation/EMS Complete Documentation.docx';

$textEdits = [
    ['AdmissionPolicy.php:67-142', 'AdmissionPolicy.php:67-152'],
    ['AdmissionPolicy.php:108-141', 'AdmissionPolicy.php:108-149', null, 4],
    ['AdmissionPolicy.php:111-123', 'AdmissionPolicy.php:111-126'],
    ['AdmissionPolicy.php:119-121', 'AdmissionPolicy.php:122-125'],
    ['AdmissionPolicy.php:127-140', 'AdmissionPolicy.php:134-149', null, 2],
    ['AdmissionPolicy.php:132-140', 'AdmissionPolicy.php:134-149'],

    // §10.6 — the rule stated where the blockers are described.
    ['so no live applicant could ever have been blocked by it.',
     'so no live applicant could ever have been blocked by it. Each result is read against this '
     .'admission’s own program and term (ruled 2026-10-08): an examresults row carries a courseId and '
     .'a termId, so a pass earned under another program no longer answers for the application in '
     .'front of the desk, and the blocker that names the program says so — “No General Entrance Exam '
     .'result on record for this applicant to BSCrim for this term.” Measured first, because the '
     .'change could have stranded real applicants: no student on the demo install holds a result for '
     .'more than one course, so tightening it moved nobody.'],
];

$cellEdits = [
    // §9 B-2 — the last claim of the G-8 kind.
    ['General entrance result = fail', 'Admission cannot be approved; the pending admission',
     'Admission cannot be approved — the blocker names the failed result and the program and term it '
     .'belongs to — and stage 4 is not administered, because the department’s prerequisite refuses a '
     .'non-pass general row. The pending admission is NOT set to rejected: an examination result never '
     .'decides an application (G-8), and rejecting is the Admission office’s own act, with its own '
     .'reason and its own audit row.'],
];

$apply = in_array('--apply', $argv, true);
docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-08-pass67.docx',
    $textEdits, $cellEdits, $apply);
