<?php
$zip = new ZipArchive();
$zip->open('Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();
foreach (['which is a policy inside the evaluation permission','a policy inside the evaluation permission',
 '$isFlexibleStudent at','Continuing-Irregular','EvaluationController.php:134-136'] as $f) {
    printf("%2d  %s\n", substr_count($xml, $f), $f);
}
