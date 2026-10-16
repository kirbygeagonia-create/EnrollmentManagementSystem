<?php
/**
 * Which candidate needles for pass 100 are addressable as a single <w:t> run?
 *
 * Sentences in this document are split across runs, so a long quote often exists only after the tags are
 * stripped — and an edit that "matches" a flattened string but not the XML is how a paragraph gets written
 * twice. Each candidate is counted in the RAW xml, escaped, and also reported as it would appear flattened,
 * so the choice of needle is a measurement rather than a guess.
 */
$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';

$zip = new ZipArchive();
$zip->open($src);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$esc = fn (string $s) => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);
$flat = preg_replace('/<[^>]+>/', '', $xml);

$candidates = [
    'rather than failing four desks into a pipeline on a null row.',
    'on a null row.',
    'the same subtraction of proof, in a new place.',
    'it skips by naming the table',
    'Without that flag the step reported four skipped and exited 0',
    'A leg takes its database from E2E_DB_DATABASE',
];

foreach ($candidates as $c) {
    printf(
        "raw=%d escaped=%d flattened=%d  [%s]\n",
        substr_count($xml, $c),
        substr_count($xml, $esc($c)),
        substr_count($flat, $c),
        substr($c, 0, 58)
    );
}

// Where the passage sits, so the insertion point can be verified by meaning rather than by arithmetic.
$at = strpos($xml, $esc('the same subtraction of proof, in a new place.'));
echo "\n‘…subtraction of proof, in a new place.’ found at raw offset ".var_export($at, true)."\n";
if ($isRun = strpos($xml, '</w:t>', $at) !== false) {
    echo 'the sentence ends inside a run that closes at '.(strpos($xml, '</w:t>', $at))."\n";
    echo 'next 120 raw characters: '.substr($xml, $at + strlen($esc('the same subtraction of proof, in a new place.')), 120)."\n";
}
