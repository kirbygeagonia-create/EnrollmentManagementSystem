<?php

/**
 * Probe for pass 179f: what a seventeenth print finding costs in this document.
 *
 * §25.4 is stored as two adjacent tables, so a row count says nothing; the places that state the
 * range, the count cell, the group rows and the two outside-the-register sentences are what moves.
 * This prints the occurrence count of every needle the pass intends to use, the cell layout of the
 * P-16 row it will clone, and the P-10 cells it will correct — and writes nothing.
 */

$root = dirname(__DIR__, 3);
$z = new ZipArchive;
$z->open($root.'/Documentation/EMS Complete Documentation.docx');
$xml = (string) $z->getFromName('word/document.xml');
$z->close();

foreach ([
    'P-1 … P-16',
    'P-1 … P-15',
    '>P-16<',
    '>P-17<',
    '>16<',
    '>P-10<',
    'Nothing for the derivation',
    'the slip takes course, year level and the log',
    'Three print findings sit outside this table',
    'P-12, P-4, P-9, P-16',
    'The fourth is open',
    'the spelling of the name the letterhead prints (P-16)',
    'P-7, P-12 and P-16 in §25.4',
    'which spelling of the institution’s name the paper carries (P-16)',
] as $n) {
    printf("%2d  [%s]\n", substr_count($xml, $n), $n);
}

$at = strpos($xml, '>P-16<');
$start = (int) strrpos(substr($xml, 0, $at), '<w:tr');
$end = (int) strpos($xml, '</w:tr>', $at) + 7;
preg_match_all('/<w:tc>.*?<\/w:tc>/s', substr($xml, $start, $end - $start), $cm);
printf("\nP-16 row @%d..%d: %d cells\n", $start, $end, count($cm[0]));
foreach ($cm[0] as $j => $cell) {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell, $tm);
    printf("  cell %d paras=%d runs=%d head60=[%s]\n", $j + 1, substr_count($cell, '<w:p>'), count($tm[1]),
        mb_substr(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)), 0, 60));
}

$at10 = strpos($xml, '>P-10<');
$s10 = (int) strrpos(substr($xml, 0, $at10), '<w:tr');
$e10 = (int) strpos($xml, '</w:tr>', $at10) + 7;
preg_match_all('/<w:tc>.*?<\/w:tc>/s', substr($xml, $s10, $e10 - $s10), $c10);
printf("\nP-10 row @%d..%d: %d cells\n", $s10, $e10, count($c10[0]));
foreach ($c10[0] as $j => $cell) {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell, $tm);
    $flat = preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1));
    printf("  cell %d paras=%d runs=%d full=[%s]\n", $j + 1, substr_count($cell, '<w:p>'), count($tm[1]), $flat);
}
