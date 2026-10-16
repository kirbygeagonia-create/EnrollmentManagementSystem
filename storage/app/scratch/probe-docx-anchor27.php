<?php
$zip = new ZipArchive();
$zip->open('Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();
foreach (['not the same shape for every student type','A skipped box is never signed','the order check walks the positional',
 'produces seven signature boxes','WorkflowService.php:27-46','61 rows in the demo database','documentprintlog has six columns',
 'except the block schedule (P-3)'] as $f) {
    printf("%2d  %s\n", substr_count($xml, $f), $f);
}
