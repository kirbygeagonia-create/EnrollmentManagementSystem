<?php
$zip = new ZipArchive();
$zip->open('Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();
foreach (['EvaluationController.php:335-357','Which subjects may be proposed, and at which year level',
 'Continuing-Irregular, Shifter, Transferee','Fixed block vs flexible load'] as $f) {
    printf("%2d  %s\n", substr_count($xml, $f), $f);
}
