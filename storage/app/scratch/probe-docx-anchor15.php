<?php
$zip = new ZipArchive();
$zip->open('Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();
foreach (['honouring elective groups, prerequisites, and credited subjects',
 'Enrollments.yearLevel is written once','Which document was produced for whom',
 'a shifter whose credits were never keyed in','while production keeps the worker (C-10)',
 'AdmissionController.php:327','PrintService.php:59'] as $f) {
    printf("%2d  %s\n", substr_count($xml, $f), $f);
}
