<?php

/**
 * Pass 210 — the slip's Date-to-be-Signed range needs its file named, not just its lines re-taken.
 *
 * Pass 206 moved the pointer from the course-label code to the row that actually prints the date
 * (`clearance-slip.blade.php:80-81`), but left it bare, so the sweep still prints it as unattributed: the nearest
 * file name on that dump line sits beyond the 160-character window. Naming it here is what lets the bounds sweep
 * judge the number from now on. `:80-81` was probed first and occurs exactly once in document.xml.
 *
 *   php storage/app/scratch/refresh-docx-pass210.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass210.php --apply  # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$slip = (array) file($root.'/resources/views/prints/clearance-slip.blade.php');
$fails = 0;
foreach ([[80, 'Date to be Signed'], [81, 'clearanceEndDate']] as [$n, $needle]) {
    $t = trim($slip[$n - 1] ?? '');
    $ok = str_contains($t, $needle);
    printf("  %s slip :%-4d [%s]\n", $ok ? 'ok  ' : 'BAD ', $n, mb_substr($t, 0, 64));
    $fails += $ok ? 0 : 1;
}
if ($fails > 0) {
    echo "\nREFUSED: the cited lines do not print the date row.\n";
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$from = ':80-81';
$to = 'clearance-slip.blade.php:80-81';
$n = substr_count($xml, $from);
printf("  needle count %d (want 1)\n", $n);
if ($n !== 1) {
    echo "REFUSED: the range is not unique, so it cannot be addressed as one needle.\n";
    exit(1);
}

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass210.docx', [[$from, $to]], [], $apply);
