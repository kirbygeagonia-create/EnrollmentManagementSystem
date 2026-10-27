<?php
$root = dirname(__DIR__, 3);
$z = new ZipArchive();
$z->open($root.'/Documentation/EMS Complete Documentation.docx');
$xml = (string) $z->getFromName('word/document.xml');
$z->close();
foreach ([
    ', 26 cases: the status trail',
    'Closed 2026-10-27 by docx passes 258-267: each pointer re-taken',
    'which is what makes that qualification honoured rather than silently resolved.',
    ', 27 cases: the status trail',
] as $n) {
    printf("%-72s %d\n", $n, substr_count($xml, $n));
}
