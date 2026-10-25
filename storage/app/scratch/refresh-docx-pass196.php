<?php

/**
 * Pass 196 — every citation into app/Services/WorkflowService.php that names a construct living elsewhere.
 *
 * Found by reading, then reproducible by `lane70-gloss-snippets.php`. The file is 346 lines today and its
 * shape changed twice without the document following: the Assessment skip stopped being an `unset($steps[2])`
 * in 29b5532 (it is an `array_filter()` inside `stepsFor()` now), and signStep() grew the already-signed
 * refusal in ruling 7, which moved its office test and its order test down by dozens of lines. Every pointer
 * into it still carries the pre-ruling-7 numbering, and none of it is caught by the bounds sweep because each
 * stale number lands on a LIVE line of the same file:
 *
 *   "the office test at :237"            x4  -> :255   (:237 is `throw new WorkflowAlreadySignedException(`,
 *                                                      the refusal — not the scope test)
 *   "the previous box … ( :114-124 )"         -> :261-272 (:114-124 is the STAGE_AFTER_BOX / PIPELINE_ORDER
 *                                                      const maps; there is no order check there)
 *   ":113-124 (order check)"             x2  -> :261-272
 *   "office and order ( …:93-124 )"           -> :249-272 (:93-124 is the tail of createWorkflow plus two
 *                                                      constants; named by pass 192 without re-reading it)
 *   BR13 row cites ":255-259"                -> :261-272 (:255-259 is the office throw, the other gate)
 *   BR13 labelled at ":78,113"               -> :203,261 (the two comments that actually say BR13)
 *   "completed ( :144 )"                      -> :292   (:144 closes the docblock above
 *                                                      recordFellBackBelowTheBox; :292 writes the status)
 *   "academic-signer exception … :95"          -> :193   (:95 is the workflowId line of createWorkflow;
 *                                                      :193 is `in_array($signer->role->value, […])`)
 *   ":32 labels box 1 'Department Evaluation'" x2 -> :33 (:32 is `return [`)
 *   ":63-66,41-43" (removal)             x3  -> :54,66-71 (:66-71 is the array_filter; :41-43 is between the
 *                                                      two methods)
 *   ":63-66" (removal)                   x4  -> :66-71    (done in pass 196b, once the longer needle is gone)
 *   ":82; office scope :93-111, order :113-124"  -> :208; :249-259; :261-272 (all three are pre-ruling-7)
 *   ":64-66 ( unset($steps[2]) )"               -> :66-71 ( array_filter() )  — the quoted call does not exist
 *                                                      in the file at all, which is what the checker proves.
 *   ", :63-66 ( stepOrder = $order + 1 )"       -> :97    (that assignment is in createWorkflow)
 *
 *   php storage/app/scratch/refresh-docx-pass196.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass196.php --apply  # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$lines = file($root.'/app/Services/WorkflowService.php');
if ($lines === false) {
    echo "REFUSED: cannot read WorkflowService.php — every target below would be an unverified number.\n";
    exit(1);
}

/** The evidence: print the old line and the new line for each pair, and refuse if the new one is not the claim. */
$checks = [
    [237, 255, 'maySignAcrossOffices'],
    [114, 261, 'Check step order'],
    [93, 249, 'Check office scope'],
    [144, 292, 'WorkflowStatus::Completed'],
    [95, 193, '$signer->role->value'],
    [32, 33, 'Department Evaluation'],
    [64, 66, '! $includesAssessment'],
    [41, 54, 'includesAssessment = in_array'],
    [78, 203, 'BR13'],
];
$bad = 0;
foreach ($checks as [$old, $new, $mustContain]) {
    $o = trim($lines[$old - 1] ?? '');
    $n = trim($lines[$new - 1] ?? '');
    $ok = str_contains($n, $mustContain);
    printf("  :%-4d [%s]\n  :%-4d [%s]  contains [%s] -> %s\n", $old, mb_substr($o, 0, 62), $new, mb_substr($n, 0, 62), $mustContain, $ok ? 'OK' : 'REFUSED');
    if (! $ok) {
        $bad++;
    }
}
$range = [261, 272];
if (! str_contains($lines[$range[0] - 1], 'Check step order')
    || ! str_contains($lines[$range[1] - 1], '}')) {
    echo "  REFUSED — :261-272 is not the order check\n";
    $bad++;
}
if (! str_contains($lines[66 - 1], 'includesAssessment')
    || ! str_contains($lines[67 - 1], 'array_values')
    || ! str_contains($lines[69 - 1], 'Scholarship')) {
    echo "  REFUSED — :66-71 is not the removal\n";
    $bad++;
}
if ($bad > 0) {
    echo "\nREFUSED: {$bad} target line(s) do not carry the claim the sentence makes.\n";

    exit(1);
}
if (str_contains(implode('', $lines), 'unset($steps')) {
    echo "REFUSED: the file does contain unset(\$steps — the 'no such call' premise is gone.\n";
    exit(1);
}

$textEdits = [
    // 1. the office test, in all four sentences that cite it
    ['WorkflowService.php:208-297 , the office test at :237',
        'WorkflowService.php:208-297 , the office test at :255', null, 4],
    // 2-3. the order check, cited under two spellings of the same stale number
    [':114-124', ':261-272'],
    [':113-124', ':261-272', null, 2],
    // 4-5. two whole ranges that name both gates, or the wrong one of them
    ['WorkflowService.php:93-124', 'WorkflowService.php:249-272'],
    ['WorkflowService.php:255-259', 'WorkflowService.php:261-272'],
    // 6. the BR13 numbering claim: which lines actually say BR13
    ['WorkflowService.php:78,113', 'WorkflowService.php:203,261'],
    // 7. where workflowStatus becomes completed
    [':144', ':292'],
    // 8. the raw role value the academic-signer exception reads
    ['WorkflowService.php:95', 'WorkflowService.php:193'],
    // 9. the Guidance-labelled row of stepLabels()
    ['WorkflowService.php:32', 'WorkflowService.php:33', null, 2],
    // 10-11. the Assessment-box removal, spelled two ways
    ['WorkflowService.php:63-66,41-43', 'WorkflowService.php:54,66-71', null, 3],
    ['WorkflowService.php:64-66', 'WorkflowService.php:66-71'],
    // 12. a quoted call that no longer exists in the file
    ['unset($steps[2])', 'array_filter()'],
    // 13-14. the three-gate parenthetical, all pre-ruling-7. `:82` occurs three times in the document, so
    // it is addressed by the sentence that owns it — and a replacement may not carry markup, which rules
    // out the `>:82<` needle that would otherwise be unique.
    [':82', ':208', 'and its two gates'],
    [':93-111', ':249-259'],
    // 15. stepOrder = $order + 1 is written in createWorkflow, not in the removal
    [':63-66', ':97', 'unset($steps[2])'],
];

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass196.docx', $textEdits, [], $apply);

echo "\nPass 196a leaves four bare `WorkflowService.php:63-66` pointers, which are only addressable once\n";
echo "the longer `:63-66,41-43` runs are gone — pass 196b takes them.\n";
