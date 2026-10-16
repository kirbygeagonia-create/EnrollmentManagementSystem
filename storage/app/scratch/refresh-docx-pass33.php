<?php

/**
 * Pass 33 — the enrollment form's row records the reach it actually has, measured live rather than
 * inferred from the permission name, and §28's X-4 row gains the one generalist reach ruling 7 left
 * standing.
 */

require __DIR__.'/docx-edit-engine.php';

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$textEdits = [
    ['yes — one issue row per download, documentType enrollmentForm, sequenced in the enrollment’s own series and separate from the certificate’s',
        'yes — one issue row per download, documentType enrollmentForm, sequenced in the enrollment’s own series and separate from the certificate’s. Measured live on 2026-10-06: the gate asks print.enrollmentForm and that the record is enrolled, and — exactly as print.certificate already does — it does not ask which office the requester runs, so a head of any office reaches it. The reach is inherited rather than new, and X-4 in §28.2 now names it'],

    ['so the right the matrix grants is now the right the desk meets.',
        'so the right the matrix grants is now the right the desk meets. One generalist reach ruling 7 did not take is named here rather than glossed: the four Registrar print rights — certificate, class card, subject load and, since 2026-10-06, the enrollment form — stay on OfficeHead, and no policy asks which office the requester runs, so a head anywhere in the building prints the Registrar’s papers. Measured live the same day (office11_head reached the enrollment-form route with a 200). Whether printing should be office-scoped is one decision covering all four documents, and it is the owner’s, not a per-paper fix.'],
];

$cellEdits = [];

docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-06-pass33.docx', $textEdits, $cellEdits, $apply);
