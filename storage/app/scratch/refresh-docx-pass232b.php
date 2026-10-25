<?php

/**
 * Lane 70, pass 232b: the two labels pass 232 could not reach with a contiguous needle.
 *
 * (a) `WorkflowService.php:208-297 (box list)` — 208-297 is `signStep()`, which holds no box list; the seven
 * entries and the removal that makes six live in `stepsFor()` at :56-71. The row's own prose is the anchor.
 * (b) `(the only createWorkflow call)` beside the pointer pass 232 re-took to :801-804 — the call is one of two
 * (the other is `EnrollmentIssuer.php:75`), and it is guarded by `if (! $enrollment->enrollmentworkflow)`.
 * The phrase is split at the styled `createWorkflow` run, so it is rewritten around that split.
 *
 * Both new numbers are asserted against the lines they claim before anything is written.
 */
$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$tmp = $root.'/storage/app/scratch/pass232b-backup.docx';
require $root.'/storage/app/scratch/docx-edit-engine.php';

$wf = explode("\n", (string) file_get_contents($root.'/app/Services/WorkflowService.php'));
$ev = explode("\n", (string) file_get_contents($root.'/app/Http/Controllers/Evaluation/EvaluationController.php'));

$fails = 0;
foreach ([
    ['WorkflowService.php', 56, '$boxes', $wf],
    ['WorkflowService.php', 64, ']', $wf],
    ['WorkflowService.php', 66, '! $includesAssessment', $wf],
    ['WorkflowService.php', 70, ')', $wf],
    ['WorkflowService.php', 208, 'public function signStep', $ev === [] ? $wf : $wf],
    ['EvaluationController.php', 802, '! $enrollment->enrollmentworkflow', $ev],
] as [$file, $no, $needle, $rows]) {
    $actual = trim((string) ($rows[$no - 1] ?? ''));
    if (! str_contains($actual, $needle)) {
        echo "ASSERT FAILED  {$file}:{$no} must carry [{$needle}] — reads [{$actual}]\n";
        $fails++;
    }
}
// :56-71 must not already be cited somewhere else, or a second citation would be ambiguous.
if (substr_count((string) file_get_contents($tmp), ':56-71")') > 0) {
    echo "ASSERT FAILED  :56-71 already cited — pick a different span\n";
    $fails++;
}
if ($fails > 0) {
    echo "REFUSED: {$fails} assertion(s) failed\n";
    exit(1);
}

if (! copy($src, $tmp)) {
    echo "REFUSED: no backup at {$tmp}\n";
    exit(1);
}

docxApply($src, $tmp, [
    [':208-297', ':56-71', 'the order check walks'],
    ['the only ', 'a guarded ', ':801-804'],
], [], in_array('--apply', $argv, true));

echo in_array('--apply', $argv, true) ? "APPLIED\n" : "DRY RUN — nothing written\n";
