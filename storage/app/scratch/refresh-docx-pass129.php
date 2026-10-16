<?php

/**
 * Pass 129 — pass 128 wrote a number it had not measured.
 *
 * Its new revision row opened with "Two per-office UAT walks in the running system". The record supports
 * *that the script was walked* — commits `17a7e3a` (the ID desk, and §15.1 found unperformable) and `2986427`
 * (the pass criterion counting eleven sections) — but nothing in the repository states a count of walks. So
 * the count becomes the claim that is actually supported. Same failure as pass 121's "42 permissions across
 * nine modules": a figure written because the sentence wanted one, not because it was measured.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';

$textEdits = [
    ['Two per-office UAT walks in the running system, and what they found became commands, gates and data decisions rather than notes.',
        'The per-office UAT script was walked in the running system before it was handed to the offices, and what the walk found became commands, gates and data decisions rather than notes.'],
];

require __DIR__.'/docx-edit-engine.php';
docxApply($src, $root.'/storage/app/scratch/docx-backup-2026-10-15-pass129.docx',
    $textEdits, [], in_array('--apply', $argv, true));
