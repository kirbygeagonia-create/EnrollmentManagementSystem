<?php

/**
 * Pass 146 — G-16's own mutant count, stated as the harness actually reports it.
 *
 * The register row wrote "proved by eight mutants including the two that show its scope flag narrows the scan
 * and an empty scan refuses". `mutate-lane47-tile-scope.php` prints nine cases: the clean baseline, six planted
 * defects, and the two scope cases — of which only six are mutants at all. A number describing a checker has to
 * agree with what the checker prints, or the row teaching the next reader is itself the unverified claim.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';

$textEdits = [
    ['and is itself proved by eight mutants including the two that show its scope flag narrows the scan and an '
        .'empty scan refuses.',
        'and is itself proved by nine cases — a clean baseline, six planted defects, and the two showing that '
        .'its scope flag narrows the scan and an empty scan refuses rather than reporting clean.', 1],
];

docxApply($src, $root.'/storage/app/scratch/docx-backup-2026-10-18-pass146.docx', $textEdits, [], in_array('--apply', $argv, true));
