<?php

/**
 * Pass 131 probe: where does the sentence that says the pipeline "still presumes its named demo students"
 * actually live in word/document.xml, and can the edit engine's text kind reach it?
 *
 * The engine replaces a needle inside ONE <w:t> run, and this document's long paragraphs are split across
 * runs by Word's own editing history. So the needle has to be chosen at a length that survives the split —
 * measured here rather than assumed: every candidate is counted in the raw XML, and the run boundaries
 * around the hit are printed so the edit can be planned against the markup, not against the flat dump.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';

$zip = new ZipArchive();
$zip->open($src);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

echo 'document.xml: '.strlen($xml)." bytes\n\n";

$candidates = [
    'What this batch did not fix is stated where it belongs',
    'the tool still presumes its named demo students already exist',
    'aborting on DEMO-2026-004 when they do not',
    'it prints that failure while still exiting 0',
    'so anything gating on its exit code reads a pass',
    'AssertNoSilentSkips.php:52-101',
    'the allowlist at :39-50',
    'seed_full_demo_pipeline.php',
];

foreach ($candidates as $needle) {
    printf("%-58s %d\n", $needle, substr_count($xml, $needle));
}

$at = strpos($xml, 'What this batch did not fix');

if ($at !== false) {
    echo "\n---- raw markup from the sentence's start, 900 bytes ----\n";
    echo substr($xml, $at, 900)."\n";
    echo "\n---- the run it sits in ----\n";
    $runStart = strrpos(substr($xml, 0, $at), '<w:t');
    $runEnd = strpos($xml, '</w:t>', $at);
    echo 'run opens at '.$runStart.', closes at '.$runEnd.', text length '.($runEnd - $runStart)."\n";
    echo substr($xml, $runStart, 80)."…\n";
    echo '…'.substr($xml, max($runEnd - 200, 0), 200)."\n";
}
