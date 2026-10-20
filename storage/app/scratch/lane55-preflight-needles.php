<?php

/**
 * Lane 55 — second pre-flight: the run-boundary-sensitive needles the pass will use for bare continuations.
 *
 * The flat dump inserts a space wherever Word split a sentence across two <w:t> runs, so a needle copied out of
 * the dump can be absent from the XML bytes it is meant to match. Each candidate is counted here in the bytes the
 * writer actually searches, and the token alone is counted too, so a global replace is only ever chosen when the
 * token appears nowhere else in the document.
 *
 * Usage: php storage/app/scratch/lane55-preflight-needles.php
 */

$root = dirname(__DIR__, 3);
$zip = new ZipArchive();
if ($zip->open($root.'/Documentation/EMS Complete Documentation.docx') !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$candidates = [
    'BlockingController.php:573-601',
    'BlockingController.php:573',
    ', room check at :467-478',
    '( :467-478 ), next to the block total ( :461 )',
    '(:467-478, :461)',
    'office 5 (:461)',
    ':467-478',
    ':461',
    'cap at :433',
    'cap at',
    ':433',
    'serves the PDF ( :588 )',
    ':588',
    'Blocking requires enrolled and the Blocking box to be the next uncompleted one (BlockingController.php:469-476)',
];

foreach ($candidates as $c) {
    printf("%2d  %s\n", substr_count($xml, $c), $c);
}

echo "\n--- byte-correct context for every bare token ---\n";
foreach ([':433', ':461', ':467-478', ':588'] as $tok) {
    $off = 0;
    $i = 0;
    while (($at = strpos($xml, $tok, $off)) !== false) {
        $win = str_replace(['<', '>'], ['[', ']'], substr($xml, max(0, $at - 320), 400));
        $flat = preg_replace('/\[[^\]]*\]/u', '', $win);
        printf("  %s #%d …%s\n", $tok, ++$i, preg_replace('/\s+/u', ' ', mb_substr($flat, -110)));
        $off = $at + 1;
    }
}
