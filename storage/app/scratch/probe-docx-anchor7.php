<?php
$zip = new ZipArchive();
$zip->open('Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();
foreach ([
    'the daily report and the refund gate',
    'Payments::held() — paid OR partial',
    'The Registrar’s payment gate deliberately stays paid-only',
    'single definition of money still in the drawer',
    'is read by the outstanding balance',
] as $f) {
    printf("%2d  %s\n", substr_count($xml, $f), $f);
}
