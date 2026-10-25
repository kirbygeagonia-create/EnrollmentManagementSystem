<?php

/**
 * Pass 213 — §1.1's living row records passes 211 and 212, and the trap in rewriting a cell from the flat dump.
 *
 * The finding worth keeping: the workflow table's Assessment row cited three lines of the fee controller for a
 * rule that does not live there at all. The skip is `WorkflowService.php:52-71` (`$includesAssessment`, then the
 * box list filtered before the labels are mapped), while `:266` is `applyScholarship()`'s balance recompute and
 * `:280-285` is `withdrawScholarship()`'s docblock — the fourth wrong target this lane found by reading, and the
 * first one whose cited file the row never even named.
 *
 *   php storage/app/scratch/refresh-docx-pass213.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass213.php --apply  # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$from = 'a positional anchor is used only when the anchor is unique.';
$to = $from.' Passes 211 and 212 took the two rows no text needle could reach. The first is the Registrar’s '
    .'return action, whose every phrase is split at the code spans — `mandatory returnReason` and '
    .'`Registrar may send a paid enrollment` each match zero times in the file — so the whole paragraph was '
    .'rewritten and the pointer now names its action. The second is worse than unattributed: the workflow table’s '
    .'Assessment row cited three lines of the fee controller for the rule that the second box is signed for a '
    .'first-year and skipped for a continuing student, but those lines are a balance recompute and the docblock of '
    .'the grant withdrawal, and the skip itself is WorkflowService.php:52-71, in a file the row never named. That '
    .'is the fourth citation this lane found pointing at live code belonging to something else. One instrument '
    .'lesson came out of the same write: the flat dump joins text runs with a space, which is what stops two '
    .'adjacent filenames welding into one, so a cell rebuilt from the dump invents spacing the document never had '
    .'— it read `pending →…→ assessed ; box 2` where the file holds `pending→…→ assessed; box 2`. Both rewrites '
    .'were rebuilt from document.xml’s own runs, and the sweep answered 710 citations over 154 files with 0 line '
    .'problems and 16 blind numbers left.';

$n = substr_count($xml, $from);
printf("  anchor count %d (want 1)\n", $n);
if ($n !== 1) {
    echo "REFUSED: the living row's closing sentence is not addressable as one needle.\n";
    exit(1);
}
foreach (['<', '>'] as $ch) {
    if (str_contains($to, $ch)) {
        echo "REFUSED: the replacement carries markup.\n";
        exit(1);
    }
}

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass213.docx', [[$from, $to]], [], $apply);
