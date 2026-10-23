<?php

$root = dirname(__DIR__, 3);
$z = new ZipArchive;
$z->open($root.'/Documentation/EMS Complete Documentation.docx');
$xml = (string) $z->getFromName('word/document.xml');
$z->close();

foreach (['Lane 60 re-measured the schema and the ratios over its rows',
    'over its rows — 63 tables', '129 unique-to-nothing indexes', '410 columns documented',
    '>Column counts<', 'Status moves to returnedToEvaluation'] as $n) {
    printf("%2d  [%s]\n", substr_count($xml, $n), $n);
}

$at = strpos($xml, 'Lane 60 re-measured the schema and the ratios over its rows');
if ($at !== false) {
    $s = max((int) strrpos(substr($xml, 0, $at), '<w:p>'), (int) strrpos(substr($xml, 0, $at), '<w:p '));
    $e = (int) strpos($xml, '</w:p>', $at) + 6;
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', substr($xml, $s, $e - $s), $t);
    printf("\nthe §1.1 narrative paragraph holds %d run(s); the one that carries the clause:\n", count($t[1]));
    foreach ($t[1] as $i => $r) {
        $d = html_entity_decode($r, ENT_QUOTES | ENT_XML1);
        if (str_contains($d, 'over its rows')) {
            printf("   run %d head140=[%s]\n", $i + 1, mb_substr($d, 0, 140));
        }
    }
}

$at2 = strpos($xml, '>Column counts<');
$s2 = (int) strrpos(substr($xml, 0, $at2), '<w:tr');
$e2 = (int) strpos($xml, '</w:tr>', $at2) + 7;
preg_match_all('/<w:tc>.*?<\/w:tc>/s', substr($xml, $s2, $e2 - $s2), $cm);
printf("\nColumn-counts row: %d cells\n", count($cm[0]));
foreach ($cm[0] as $j => $c) {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $c, $tm);
    printf("  cell %d  paras=%d  head52=[%s]\n", $j + 1, substr_count($c, '<w:p>'),
        mb_substr(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)), 0, 52));
}
