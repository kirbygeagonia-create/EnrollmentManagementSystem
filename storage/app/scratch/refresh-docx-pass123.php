<?php

/**
 * Pass 123 — pass 121 left the role-matrix sentence listing the same three rights twice.
 *
 * Its needle began AFTER "admission.approve, enrollment.approve, evaluation.sign" and replaced what followed
 * with a sentence that named those three again, so the joined text read "no longer granted admission.approve,
 * enrollment.approve, evaluation.sign or the five signature acts ruling 7 split out — admission.approve, …".
 * Nothing in the verification set can see that: the XML is well-formed, run and paragraph counts are perfect,
 * the document parses and Word opens it. Only reading the sentence says it aloud twice.
 *
 * Same class as pass 119's stray semicolon, and the reason the standing rule is "re-dump, then read the whole
 * joined sentence" rather than "re-dump, then trust the guards".
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';

$textEdits = [
    [
        'OfficeHead is no longer granted admission.approve, enrollment.approve, evaluation.sign or the five signature acts ruling 7 split out — ',
        'OfficeHead is no longer granted the five signature acts ruling 7 split out — ',
    ],
];

require __DIR__.'/docx-edit-engine.php';
docxApply($src, $root.'/storage/app/scratch/docx-backup-2026-10-15-pass123.docx',
    $textEdits, [], in_array('--apply', $argv, true));
