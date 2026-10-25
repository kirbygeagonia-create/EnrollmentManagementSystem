<?php

/**
 * Lane 70, pass 232c: pass 232 lengthened §13's step-row sentence and its bare `:796-809` fell outside the
 * sweep's 160-character attribution window, so the bounds sweep could no longer see which file it belonged to.
 * Name the file in the pointer rather than teach the checker to guess — the rule AGENTS.md already carries.
 */
$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$tmp = $root.'/storage/app/scratch/pass232c-backup.docx';
require $root.'/storage/app/scratch/docx-edit-engine.php';

if (! copy($src, $tmp)) {
    echo "REFUSED: no backup at {$tmp}\n";
    exit(1);
}

$ev = explode("\n", (string) file_get_contents($root.'/app/Http/Controllers/Evaluation/EvaluationController.php'));
foreach ([[796, 'DB::transaction'], [803, 'createWorkflow'], [808, 'signStepByOffice'], [809, '}']] as [$no, $needle]) {
    $actual = trim((string) ($ev[$no - 1] ?? ''));
    if (! str_contains($actual, $needle)) {
        echo "ASSERT FAILED  EvaluationController.php:{$no} must carry [{$needle}] — reads [{$actual}]\n";
        exit(1);
    }
}

docxApply($src, $tmp, [[':796-809', 'EvaluationController.php:796-809']], [], in_array('--apply', $argv, true));

echo in_array('--apply', $argv, true) ? "APPLIED\n" : "DRY RUN — nothing written\n";
