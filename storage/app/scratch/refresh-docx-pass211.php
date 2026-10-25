<?php

/**
 * Pass 211 — the returnReason row's pointer goes through the para kind, because every phrase in it is run-split.
 *
 * `:288-312` is the §28 row's only unattributed number, and its owner is the Registrar's return action
 * (RegistrarController.php:293 validates `returnReason` as required with a ten-character floor, :308 writes it).
 * No needle in that sentence is contiguous — `mandatory returnReason`, `Registrar may send a paid enrollment` and
 * `:288-312, BR31` each match zero times in document.xml, because Word split the paragraph at every code span — so
 * the edit is the whole paragraph, rewritten from the flat dump's own text with one substitution. The pass refuses
 * unless the substitution actually changed the string, and unless the anchor `correction loop` is unique.
 *
 *   php storage/app/scratch/refresh-docx-pass211.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass211.php --apply  # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

// 1. the code behind the number
$reg = (array) file($root.'/app/Http/Controllers/Registrar/RegistrarController.php');
$fails = 0;
foreach ([[293, "'returnReason' => 'required"], [308, "'returnReason' =>"]] as [$n, $needle]) {
    $t = trim($reg[$n - 1] ?? '');
    $ok = str_contains($t, $needle);
    printf("  %s reg :%-4d [%s]\n", $ok ? 'ok  ' : 'BAD ', $n, mb_substr($t, 0, 62));
    $fails += $ok ? 0 : 1;
}
if ($fails > 0) {
    echo "REFUSED: the cited lines are not the return action.\n";
    exit(1);
}

// 2. rebuild the paragraph out of the dump's own bytes, so no dash or quote is retyped by hand
$lines = explode("\n", (string) file_get_contents($root.'/storage/app/scratch/docx-flat.txt'));
$raw = $lines[574] ?? '';
if (! str_starts_with($raw, 'P 575: ')) {
    printf("REFUSED: dump line 575 is not the paragraph this pass was written against. It reads: %s\n",
        mb_substr($raw, 0, 60));
    exit(1);
}
$text = html_entity_decode(substr($raw, strlen('P 575: ')), ENT_QUOTES | ENT_XML1);
$text = preg_replace('/\s+/', ' ', $text);
$new = str_replace('(:288-312, BR31 hold)', '( RegistrarController.php:288-312, BR31 hold )', $text);
if ($new === $text) {
    echo "REFUSED: the substitution matched nothing, so the paragraph would be rewritten unchanged.\n";
    exit(1);
}
printf("  paragraph: %d chars → %d chars\n", strlen($text), strlen($new));
echo '  before: '.mb_substr($text, 0, 110)."\n  after : ".mb_substr($new, 0, 118)."\n";

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$anchor = 'correction loop';
$n = substr_count($xml, $anchor);
printf("  anchor [%s] count %d (want 1)\n", $anchor, $n);
if ($n !== 1) {
    echo "REFUSED: the anchor is not unique, so the wrong paragraph could be rewritten.\n";
    exit(1);
}

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass211.docx', [], [], $apply, [[$anchor, $new]]);
