<?php

// Lane 52 step 2: count every needle and anchor pass 153 intends to use, so an ambiguous one is caught here
// rather than by an engine that refuses mid-pass — and so a needle that matches nothing cannot be mistaken for
// one that was already fixed.

$root = dirname(__DIR__, 3);
$z = new ZipArchive();

if ($z->open($root.'/Documentation/EMS Complete Documentation.docx') !== true) {
    fwrite(STDERR, "REFUSE: cannot open the docx\n");

    exit(1);
}

$xml = $z->getFromName('word/document.xml');
$z->close();

$needles = [
    // para 2 — Identity
    '); the PDF prints Semester, Academic Year, Student Name, “Course &amp; Year” and Date to be Signed (',
    'clearance-slip.blade.php:38-55',
    ') with ',
    'no Student ID row',
    ' (P-1).',
    // para 3 — Academic period
    ':39,43',
    ') — which is correct and is what makes the slip term-specific (§16). The program line does not follow that path: it uses ',
    '$clearance-&gt;student-&gt;enrollments-&gt;first()',
    ' (P-10).',
    // para 5 — Clearance state
    ' twice (',
    ':41',
    ', ',
    ':84-97',
    '); the PDF never reads it and stamps “SEAIT CLEARED” unconditionally (P-11). A pending slip therefore looks cleared once converted to PDF.',
    // para 6 — Receipt
    ':83-85',
    'PrintSlip.jsx:89-94',
    // anchors under consideration
    'Identity. ',
    'Academic period. ',
    'Clearance state. ',
    'Receipt. ',
    'three-column panel (',
];

foreach ($needles as $n) {
    printf("x%-4d  %s\n", substr_count($xml, $n), mb_substr(str_replace("\n", ' ', $n), 0, 96));
}
