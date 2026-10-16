<?php
$zip = new ZipArchive();
$zip->open('Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();
foreach ([':470-476','min–max ( :470-476','EvaluationController.php:192-282','EvaluationController.php:289-330',
 'EvaluationController.php:163-164','EvaluationController.php:57-64','EvaluationController.php:417-460',
 'EvaluationController.php:440-456','EvaluationController.php:445','EvaluationController.php:462-482',
 'EvaluationController.php:491-518','EvaluationController.php:690-740','EvaluationController.php:388-389',
 'EvaluationController.php:295-299','EvaluationController.php:320-333','EvaluationController.php:336-342',
 'EvaluationController.php:336-343','EvaluationController.php:336-357','EvaluationController.php:359-370',
 'EvaluationController.php:344-356','EvaluationController.php:360-385','EvaluationController.php:294',
 'EvaluationController.php:324','EvaluationController.php:298','EvaluationController.php:229-252',
 'via a bare integer at','Standing changes what may be proposed'] as $f) {
    printf("%2d  %s\n", substr_count($xml, $f), $f);
}
