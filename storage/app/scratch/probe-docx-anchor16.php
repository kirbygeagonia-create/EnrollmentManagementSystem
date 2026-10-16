<?php
$zip = new ZipArchive();
$zip->open('Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();
foreach (['RegistrarController.php:112-114','RegistrarController.php:292','RegistrarController.php:116-119',
 'RegistrarController.php:181','RegistrarController.php:146','RegistrarController.php:174','RegistrarController.php:124-128',
 'ClearanceController.php:267','the 5-gate checklist','R-3 Clearance dependency','No clearance period is currently open',
 'overallStatus = approved and non-null','The Evaluation screen states the gate lives elsewhere','Policy not enforced at that point',
 'The clearance gate is written twice','RegistrarPolicy.php:68-69','RegistrarPolicy.php:66-83','RegistrarPolicy.php:39-86',
 'the block schedule print writes none','RegistrarController.php:213-224','RegistrarController.php:145-159','the student is not blocked by an absent period'] as $f) {
    printf("%2d  %s\n", substr_count($xml, $f), $f);
}
