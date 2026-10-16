<?php
$zip = new ZipArchive();
$zip->open('Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();
foreach ([
 'no code — the credit path writes passed','no code — the credit path writes','the credit path writes passed (',
 'no code &mdash; the credit path','DeptEvaluator through evaluation.credits.process','evaluation.credits.process',
 'Closed by ruling 10','counts only rows stored as passed','EvaluationController.php:445',
] as $f) { printf("%2d  %s\n", substr_count($xml, $f), $f); }
