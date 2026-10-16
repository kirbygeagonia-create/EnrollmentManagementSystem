<?php
$zip = new ZipArchive();
$zip->open('Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();
foreach (['AssessmentController.php:280','AssessmentController.php:161','AssessmentController.php:104-107',
 'AssessmentController.php:104-109','AssessmentController.php:274-277','AssessmentController.php:100',
 'AssessmentController.php:98-190','AdmissionController.php:308-341','AdmissionController.php:332',
 'AdmissionController.php:364-374','Implementation gap G-1','the only Enrollments::create in app/',
 'A second call site anywhere other than a reopening desk','An enrollment row comes into existence only when admission approves'] as $f) {
    printf("%2d  %s\n", substr_count($xml, $f), $f);
}
