<?php
$zip = new ZipArchive();
$zip->open('Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();
foreach (['EvaluationController.php:111,490','EvaluationController.php:408','via a bare integer at EvaluationController.php:481',
 'EvaluationController.php:470-476','EvaluationController.php:294,','EvaluationController.php:298 ,','EvaluationController.php:324 ,',
 'Continuing-Irregular, Shifter, Transferee','Fixed block vs flexible load','Standing changes what may be proposed',
 'Which subjects may be proposed, and at which year level','is now the only way to ask whether clearance season is live'] as $f) {
    printf("%2d  %s\n", substr_count($xml, $f), $f);
}
