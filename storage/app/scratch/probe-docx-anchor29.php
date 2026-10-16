<?php
$zip = new ZipArchive();
$zip->open('Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();
foreach (['Does this program require a retention examination','ExamPolicy.php:52-64','HandleInertiaRequests.php:58-61','HandleInertiaRequests.php:86-91'] as $f) {
    printf("%2d  %s\n", substr_count($xml, $f), $f);
}
