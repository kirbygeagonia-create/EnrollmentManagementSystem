<?php

/**
 * Pass 179h — one phrase pass 179g wrote is false about the artefact it describes.
 *
 * The gradescale row says it "contradicted §24 four lines above". It does contradict §24's entry for
 * that table — which states "Rows in the demo database: 4" at docx-flat line 1510 — but that is two
 * hundred and fifty lines above in the generated dump and a whole section away in the document. A
 * reading instruction that sends a reader four lines up finds nothing, which is the lane-62 defect
 * class: a claim about where the evidence sits, unverified.
 */

require_once __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$flat = (string) file_get_contents($root.'/storage/app/scratch/docx-flat.txt');
$hit = null;
foreach (explode("\n", $flat) as $i => $line) {
    if (str_contains($line, 'Rows in the demo database: 4.') && str_contains($line, 'Gradescale')) {
        $hit = $i + 1;
    }
}
$fails = $hit === null ? 1 : 0;
printf("  %s the dictionary entry this row points at is flat-dump line %s (not four lines away)\n",
    $hit === null ? 'FAIL' : 'ok  ', (string) $hit);

docxApply($src, $root.'/storage/app/scratch/pass179h-backup.docx', [[
    'Not empty, and this row contradicted §24 four lines above:',
    'Not empty, and this row contradicted §24’s own entry for this table, which states '.
        '"Rows in the demo database: 4":',
]], [], $apply);
