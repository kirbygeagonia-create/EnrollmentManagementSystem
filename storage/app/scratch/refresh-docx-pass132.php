<?php

/**
 * Pass 132 — the office-boundary rule was extracted out of signStep() into a predicate, and nine
 * WorkflowService citations in the document moved with it.
 *
 * Lane 38b: CI caught the demo pipeline signing two clearance lines with a fallback user id (its Pest step
 * spends staffusers' AUTO_INCREMENT before the seeders run, so the literal was no longer an account). The fix
 * routes every signer through the desk that owns the line — and for the pipeline's desk report to ask the same
 * question `signStep()` asks at write time, rather than restating BR14 in SQL, the rule became
 * `WorkflowService::maySignAcrossOffices()`. That insert, plus a shorter boundary block, moved everything from
 * the old line 182 down: the whole method, the already-signed refusal, the academic-signer exception, the
 * step-order check, the signature stamp and the completion tail.
 *
 * Every new span was measured from the file the way the prose names it — `measure-method-span.php` for the
 * method bodies, a line-numbered read for the inner blocks — not by arithmetic, because the insert and the
 * shrink do not cancel out: +26 before the boundary, +18 after it. The six copies of the whole-method range
 * are rewritten together, since one of them corrected and five left behind is the same false claim printed
 * six times.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';

$textEdits = [
    // The whole of signStep(), cited by six sentences.
    ['WorkflowService.php:182-279', 'WorkflowService.php:208-297', null, 6],
    // A closed box refuses a second signature unless the record fell back below it.
    ['WorkflowService.php:206-221', 'WorkflowService.php:232-247'],
    // The academic signers Guidance accepts — now inside the predicate the rule moved into.
    ['WorkflowService.php:224-228', 'WorkflowService.php:192-196'],
    // The refusal itself: an officer outside the box's office, without the blanket role.
    ['WorkflowService.php:237-241', 'WorkflowService.php:255-259'],
    // BR13: the order check walks the positional step order, not the office.
    ['WorkflowService.php:243-254', 'WorkflowService.php:261-272'],
    // signStep() stamps the name and date on the row it owns.
    ['WorkflowService.php:256-260', 'WorkflowService.php:275-278'],
    // The same stamp seen from its second line, beside the event it fires.
    ['WorkflowService.php:258-262', 'WorkflowService.php:277-280'],
    // When no box is left open, the workflow reads completed. Two sentences say this — the workflow's
    // "Completion." paragraph and the ID desk's account of its final signature — and both name the same block,
    // so both move together. Reading each before rewriting is the check: an identical range can name two
    // different constructs, and then one correction plus one stale pointer is worse than either alone.
    ['WorkflowService.php:268-276', 'WorkflowService.php:287-294', null, 2],
    // signStepByOffice(): a box that does not exist returns nothing and is skipped.
    ['WorkflowService.php:295-302', 'WorkflowService.php:313-320'],
];

require __DIR__.'/docx-edit-engine.php';
docxApply($src, $root.'/storage/app/scratch/docx-backup-2026-10-16-pass132.docx',
    $textEdits, [], in_array('--apply', $argv, true));
