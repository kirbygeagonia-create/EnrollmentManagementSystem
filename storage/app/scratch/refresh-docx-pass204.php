<?php

/**
 * Pass 204 — §1.1 records pass 203, and the figure pass 202 wrote is brought forward in the same write.
 *
 *   php storage/app/scratch/refresh-docx-pass204.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass204.php --apply  # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$figFrom = '684 over 153 files and the blind queue from 70 to 51,';
$figTo = '689 over 154 files and the blind queue from 70 to 43,';
$tailFrom = 'because a blank line is only ever a problem once the reader knows which file to open.';
$tailTo = $tailFrom.' Pass 203 found the same shape four times over in one sentence about the printed clearance '
    .'slip: it lists the lines that print the status on the desk page and the paper, and every desk number had '
    .'drifted past the line it describes — the footer pointer quoted the document-number row, the card pointer a '
    .'closing tag, the badge pointer the received-on line, and the HELD — NOT YET CLEARED pointer the Registrar’s '
    .'signature line. The same pass corrected a §12 sentence that still described the pre-ruling-4 build as '
    .'current: it said the clearance gate treats a missing window as satisfied and called that deliberate, which '
    .'is exactly the state ruling 4 retired — the check now refuses when no period is accepting '
    .'(EnrollmentReadiness.php:61-63). A present-tense sentence is aged by the next ruling, not by its own date.';

$zip = new ZipArchive;
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$fails = 0;
$say = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'BAD ', $what);
    if (! $ok) {
        $fails++;
    }
};
$say(substr_count($xml, $figFrom) === 1, 'the stale figure clause is present exactly once');
$say(substr_count($xml, $tailFrom) === 1, 'the tail of pass 201’s record is present exactly once');
foreach (['<', '>', '&'] as $ch) {
    $say(! str_contains($tailTo, $ch) && ! str_contains($figTo, $ch), 'neither replacement carries raw '.$ch);
}
$say(substr_count($xml, 'Pass 203 found') === 0, 'nothing yet claims pass 203 ran');
$say(substr_count($xml, 'PrintSlip.jsx') >= 1, 'the desk file pass 203 names is already cited elsewhere in the document');
$staged = str_replace([$figFrom, $tailFrom], [$figTo, $tailTo], $xml);
$say(substr_count($staged, 'Pass 203 found') === 1, 'the record is stated once');
$say(substr_count($staged, '689 over 154 files') === 1, 'the corrected figure is stated once');
$say(@(new DOMDocument)->loadXML($staged) !== false, 'the staged document.xml parses');
printf("\n  staged byte delta: %+d\n", strlen($staged) - strlen($xml));
printf("%d guard(s) failed.\n", $fails);
if ($fails > 0) {
    echo "NOTHING WRITTEN.\n";
    exit(1);
}

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass204.docx', [
    [$figFrom, $figTo],
    [$tailFrom, $tailTo],
], [], $apply);
