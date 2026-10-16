<?php
$zip = new ZipArchive();
$zip->open('Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();
foreach (['61 rows in the demo database','documentprintlog has six columns','except the block schedule',
 'app/Models/Enrollments.php:120-125','Enrollments.php:120-125','Staffusers.php:180-184'] as $f) {
    printf("%2d  %s\n", substr_count($xml, $f), $f);
}
