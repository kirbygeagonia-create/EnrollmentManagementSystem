<?php

/**
 * Pass 19 — the last two rows that still describe the build as it was: the standing Approval
 * used to stamp, and the clearance extension that no code could reach.
 */

require __DIR__.'/docx-edit-engine.php';

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$cellEdits = [
    // §13 stage 3 — academicStanding is no longer written by approval.
    ['A placeholder, not a decision', 'Hard-coded',
        'Not stamped. Approval deliberately leaves the column unset (AdmissionController::approve), because whether a student is regular or irregular is an academic judgement the department makes from the grades on file — writing "regular" here pre-decided it on an admission officer’s say-so.'],
    ['A placeholder, not a decision', 'A placeholder, not a decision',
        'The standing a document prints is therefore always one a desk chose: the department records it at stage 6 (EvaluationController::decideStanding, which also says out loud when the call differs from what the grades suggest), and the Registrar states it again at approval — the standing is official only once this office has said it.'],

    // §27.5 — the extended clearance window.
    ['nobody — every test asks', 'nobody — every test asks',
        'Clearanceperiods::accepting() — a window that is open OR extended — is now the only way to ask whether clearance season is live. It is read by the slip generation and its policy, by both Registrar lookups and the approval policy, and by the seed tools, so an extension visibly extends acceptance instead of being a value nothing consults.'],
    ['nobody — every test asks', 'An “extended” period behaves',
        'Closed by ruling 16. The value has exactly one writer — ClearanceController::extendPeriod, gated on clearance.periods.extend — and the status radio on the period form still refuses to set it, so an extension is always a named act rather than a typed one. Who and when are auditable through the widened auditlogs.entityId, and closing a window is refused while any clearance in it is still pending, with the count named.'],
];

docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-04-pass19.docx', [], $cellEdits, $apply);
