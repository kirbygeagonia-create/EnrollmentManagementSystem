<?php

/**
 * Pass 85 pre-flight — print every <w:t> run that holds the claim being corrected, so each
 * replacement can name a run by its exact current text instead of guessing at run boundaries.
 */

$root = dirname(__DIR__, 3);

$zip = new ZipArchive();
$zip->open($root.'/Documentation/EMS Complete Documentation.docx');
$x = $zip->getFromName('word/document.xml');
$zip->close();

$from = strpos($x, 'One defect');
$to = strpos($x, 'Two things the probe surfaced', $from);

printf("from %d  to %d  window %d bytes\n", $from, $to, $to - $from);

$window = substr($x, $from - 60, ($to - $from) + 200);

preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $window, $m, PREG_SET_ORDER);

printf("%d run fragment(s)\n\n", count($m));

foreach ($m as $i => $d) {
    echo '#'.$i.' ['.strlen($d[1]).'] '.html_entity_decode($d[1], ENT_QUOTES | ENT_XML1)."\n\n";
}
