<?php

// Probe: print the EXACT XML text of the runs pass 118 intends to edit, so its needles are copied from the
// file rather than from the flat dump, which normalises whitespace and would silently miss.

$root = dirname(__DIR__, 3);
$z = new ZipArchive;
$z->open($root.'/Documentation/EMS Complete Documentation.docx') || exit(1);
$xml = $z->getFromName('word/document.xml');
$z->close();

foreach ([
    'P1' => 'The Clinic box is signed with the bare integer',
    'P2' => 'are used as ability names that collide',
    'P3' => 'is legacy: nothing sets it, and there is no cancel action on',
    'P4' => '; the latter is re-declared by the student. Two sources for',
    'ROW' => 'PrintService.php:23 and :37-38',
] as $label => $anchor) {
    $p = strpos($xml, $anchor);
    if ($p === false) {
        echo "$label: anchor not contiguous\n";

        continue;
    }
    $s1 = strrpos(substr($xml, 0, $p), '<w:p>');
    $s2 = strrpos(substr($xml, 0, $p), '<w:p ');
    $s = max($s1, $s2);
    $e = strpos($xml, '</w:p>', $p);
    preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', substr($xml, $s, $e - $s), $m);
    echo "=== $label (", count($m[1]), " runs)\n";
    foreach ($m[1] as $i => $t) {
        printf("   [%d] uniq=%d <<%s>>\n", $i, substr_count($xml, $t), $t);
    }
}
