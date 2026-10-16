<?php
/**
 * Pass 65 — the last stale examination citations, including one paragraph whose subject
 * stopped existing.
 *
 * The §20 audit paragraph used the examination module as its headline example of a bulk write
 * that skips the observer: a failed general result was written with a query builder, so a
 * decision about an applicant left no auditlogs row, while the departmental path wrote through a
 * model instance and was audited. G-8 removed the status write from both paths, so the example —
 * and the divergence it illustrated — is gone, and the paragraph now says so rather than
 * teaching a rule the code no longer breaks.
 *
 * The four remaining numbers were re-measured against the current files.
 */
require __DIR__.'/docx-edit-engine.php';

$src = 'Documentation/EMS Complete Documentation.docx';

$textEdits = [
    ['ExamController.php:158-167', 'ExamController.php:237-248'],
    ['ExamController.php:189-191', 'ExamController.php:263-266'],
    ['ExamController.php:252-260', 'ExamController.php:343-351'],
    // §9's examination-window sentence cites the rule that an application must still be pending
    // to be approvable — the pending check itself, not the whole blocker list.
    ['AdmissionPolicy.php:69-91', 'AdmissionPolicy.php:67-75',
     'before the admission decision can be approved'],
];

$cellEdits = [
    // The workflow step table's evidence cell holds nothing but the citation.
    ['result has been recorded at all', 'AdmissionPolicy.php:69-91',
     'AdmissionPolicy.php:108-141'],
];

$paraEdits = [
    ['Two such writes exist',
     'Query-builder bulk writes bypass the observer. Eloquent model events do not fire for '
     .'Model::where(...)->update(...). One such write is still on the record: '
     .'NotificationController.php:65-68 bulk-marks notifications read and no auditlogs row notes it. '
     .'The examination module used to be the second — a failed general result set admissionStatus = '
     .'rejected through a query builder, so a decision about an applicant left no audit row while the '
     .'departmental path, writing through a model instance, was audited: one field, written two ways, '
     .'with two different audit outcomes. Finding G-8 removed the status write from both examination '
     .'paths (ExamController.php:283-290 and :353-360), so the divergence went with the write that '
     .'caused it, and what is left to audit at that stage is the examresults row itself.'],
];

$apply = in_array('--apply', $argv, true);
docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-07-pass65.docx',
    $textEdits, $cellEdits, $apply, $paraEdits);
