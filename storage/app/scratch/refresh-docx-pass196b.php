<?php

/**
 * Pass 196b — the four remaining `WorkflowService.php:63-66` pointers.
 *
 * 196a could not address them: the needle was also a prefix of the three `:63-66,41-43` runs, so a count of
 * four was unreachable on the bytes as they stood. With those rewritten, this needle is exactly the four rows
 * that claim the Assessment box is dropped at :63-66 — the box list ends at :64, and the removal is the
 * `if (! $includesAssessment)` block at :66-71.
 *
 *   php storage/app/scratch/refresh-docx-pass196b.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass196b.php --apply  # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$lines = file($root.'/app/Services/WorkflowService.php');
if ($lines === false) {
    echo "REFUSED: cannot read WorkflowService.php.\n";

    exit(1);
}
foreach ([[66, 'includesAssessment'], [67, 'array_values'], [69, 'Scholarship']] as [$n, $needle]) {
    $t = trim($lines[$n - 1] ?? '');
    if (! str_contains($t, $needle)) {
        echo sprintf("REFUSED: :%d is [%s], which does not carry [%s].\n", $n, $t, $needle);

        exit(1);
    }
}

$textEdits = [
    ['WorkflowService.php:63-66', 'WorkflowService.php:66-71', null, 4],
    // §27.5's row describes the removal as "the unset() that drops the Assessment box". The method named there
    // is gone from the file; what remains is the filter. The three other `unset` words in the document are
    // prose about a column being left unset, and they do not carry the parentheses.
    ['unset()', 'array_filter()'],
];

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass196b.docx', $textEdits, [], $apply);
