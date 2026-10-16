<?php

/**
 * Pass 32 — §22.3's RegistrarApprover description, quoted from the live row after the print right
 * was added. The chapter says this column is the seeder's own text, so it has to read as the
 * seeder writes it now.
 */

require __DIR__.'/docx-edit-engine.php';

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$textEdits = [];

$cellEdits = [
    [
        'assessment.view, clearance.view, dashboard.view, enrollment.approve, enrollment.drop',
        'Registrar officer for final enrollment approval',
        'Registrar officer for final enrollment approval, subject confirmation, and the certificate, class card, subject load and enrollment-form prints',
    ],
];

docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-06-pass32.docx', $textEdits, $cellEdits, $apply);
