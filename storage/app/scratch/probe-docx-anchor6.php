<?php
// Batch three: the pass-44 row anchors. Each must be contiguous in document.xml and unique,
// because a cell edit resolves its row by strpos.
$src = 'Documentation/EMS Complete Documentation.docx';
$zip = new ZipArchive();
$zip->open($src);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

foreach ([
    'Nothing stops a second, simultaneously active enrollment',
    'Four of the five desk prints write the row',
    'Over-capacity room assignments persist',
    'Two spellings of the same rule',
    'BR5, BR11, payment reversal',
    'nothing updates it on promotion',
    'is never read',
    'the deadlock of E-1',
    'The per-type clearance policy the brief requires',
    'whoever the student is',
    'no code — record() always writes paid',
    'is now the only way to ask whether clearance season is live',
    'Partly closed. HandleInertiaRequests now shares the info key',
    'userId 9',
    'Clearance Head',
    'office 8 either merged or removed',
    'None of it blocks the demo database',
    'Append-only here means',
    'An extension the database stores',
    'Printed documents and panels show every returning student as first year',
] as $f) {
    printf("%2d  %s\n", substr_count($xml, $f), $f);
}
