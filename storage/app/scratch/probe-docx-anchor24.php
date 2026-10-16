<?php
$zip = new ZipArchive();
$zip->open('Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();
foreach (['WorkflowService.php:21-24','WorkflowService.php:29','WorkflowService.php:93-111',
 'WorkflowService.php:93-107','WorkflowService.php:59','WorkflowService.php:41-45','WorkflowService.php:41-46',
 'EnrollmentStateMachine.php:56-77','WorkflowService.php:27-46','WorkflowService.php:78'] as $f) {
    printf("%2d  %s\n", substr_count($xml, $f), $f);
}
