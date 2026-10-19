<?php

// Lane 52 step 3: count the needles pass 155 needs — the three register rows that say a print finding closed on
// 2026-10-02, and the two §25.6 mentions pass 153 wrote with that same date. The date a finding was RECORDED
// closed and the date its code landed are different facts (29b5532 is dated 2026-10-01), and the document now
// has to say both without either sentence contradicting the other.

$root = dirname(__DIR__, 3);
$z = new ZipArchive();

if ($z->open($root.'/Documentation/EMS Complete Documentation.docx') !== true) {
    fwrite(STDERR, "REFUSE: cannot open the docx\n");

    exit(1);
}

$xml = $z->getFromName('word/document.xml');
$z->close();

$needles = [
    'Closed 2026-10-02 in the print-fidelity batch.',
    'Closed 2026-10-02: the slip takes course, year level',
    'Closed 2026-10-02: the stamp is derived from overallStatus',
    'has been on the paper since 2026-10-02, and the Overall Status row with it',
    'and, since 2026-10-02, prints HELD',
    'Closed 2026-10-02',
    '29b5532',
];

foreach ($needles as $n) {
    printf("x%-4d  %s\n", substr_count($xml, $n), mb_substr($n, 0, 84));
}
