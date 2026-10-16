<?php

// Probe: what formatting does each §18.6 paragraph carry, and what would its first run's rPr give a
// collapsed replacement? Read-only.

$root = dirname(__DIR__, 3);
$z = new ZipArchive;
$z->open($root.'/Documentation/EMS Complete Documentation.docx') || exit(1);
$xml = $z->getFromName('word/document.xml');
$z->close();

$anchors = [
    'The Clinic box is signed with the bare integer',
    'are used as ability names that collide',
    'is legacy: nothing sets it, and there is no cancel action on',
    '; the latter is re-declared by the student. Two sources for',
    'PrintService.php:23 and :37-38',
];

foreach ($anchors as $a) {
    $p = strpos($xml, $a);
    if ($p === false) {
        echo "--- [$a] NOT CONTIGUOUS\n";

        continue;
    }
    $s1 = strrpos(substr($xml, 0, $p), '<w:p>');
    $s2 = strrpos(substr($xml, 0, $p), '<w:p ');
    $s = max($s1, $s2);
    $e = strpos($xml, '</w:p>', $p);
    $body = substr($xml, $s, $e - $s);

    preg_match('/<w:pPr>.*?<\/w:pPr>/s', $body, $pm);
    preg_match('/<w:r\b.*?<\/w:r>/s', $body, $rm);
    preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);

    printf("--- %s\n", substr($a, 0, 46));
    printf("    uniqueness of anchor : %d\n", substr_count($xml, $a));
    printf("    pPr  : %s\n", substr($pm[0] ?? '(none)', 0, 170));
    printf("    rPr0 : %s\n", substr($pr[0] ?? '(none)', 0, 170));
    printf("    runs : %d\n", preg_match_all('/<w:r\b/', $body));
}
