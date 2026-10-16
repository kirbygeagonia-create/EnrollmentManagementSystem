<?php
$zip = new ZipArchive();
$zip->open('Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();
foreach (['Use App\Enums\OfficeId in new code','no code — the credit path writes passed','Failed subjects from a previous school are never recorded',
 'EvaluationController.php:750','written through the enum at','the credit path writes passed'] as $f) {
    printf("%2d  %s\n", substr_count($xml, $f), $f);
}
