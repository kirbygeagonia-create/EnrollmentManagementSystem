<?php
$zip = new ZipArchive();
$zip->open('Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();
foreach (['bare integer 2','voided payment as pending','No desk action signs it','a subject ordinal for class cards',
 'PrintService.php:59-193','AccountingController.php:180','the count-then-insert sequences can collide',
 'constant 1 for block schedule and enrollment form'] as $f) {
    printf("%2d  %s\n", substr_count($xml, $f), $f);
}
