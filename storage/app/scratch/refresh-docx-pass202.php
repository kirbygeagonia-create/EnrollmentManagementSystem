<?php

/**
 * Pass 202 — §1.1's living row records pass 201, and the figure the same row had just learned is corrected in
 * the same write.
 *
 * Pass 200's record said the sweep "moved from 666 citations to 681 … with the blind queue at 54". Pass 201
 * renamed three more pointers, so that sentence would have become the document's own stale figure — the defect
 * lane 61 recorded when a register row's own justification sentence is left standing after the row is added.
 * Both numbers are restated here, measured after the last edit rather than recalled from the previous pass.
 *
 *   php storage/app/scratch/refresh-docx-pass202.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass202.php --apply  # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive;
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$stale = 'The sweep’s count moved from 666 citations to 681 over 153 files with the blind queue at 54.';
$fresh = 'The sweep’s count moved from 666 citations to 684 over 153 files and the blind queue from 70 to 51, '
    .'and pass 201 took three more of those blind numbers in the same table: the issuance paragraph named no file '
    .'for its scope chooser, its row lock or its reserved-number skip, and the lock pointer quoted a blank line — '
    .'which is the shape a bounds sweep cannot call wrong, because a blank line is only ever a problem once the '
    .'reader knows which file to open.';

$fails = 0;
$say = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'BAD ', $what);
    if (! $ok) {
        $fails++;
    }
};
$say(substr_count($xml, $stale) === 1, 'the stale figure is present exactly once, as pass 200 wrote it');
$say(substr_count($xml, 'pass 201 took three more') === 0, 'nothing yet claims pass 201 ran');
foreach (['<', '>', '&'] as $ch) {
    $say(! str_contains($fresh, $ch), 'the replacement carries no raw '.$ch);
}
$say(preg_match('/[A-Za-z][A-Za-z0-9_.\-]*\.(?:php|jsx|js|md|json):\d/', $fresh) === 0,
    'the record writes no citation-shaped example that no file could answer');

$textEdits = [[$stale, $fresh]];
docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass202.docx', $textEdits, [], $apply);
