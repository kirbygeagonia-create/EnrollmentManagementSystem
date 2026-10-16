<?php
$zip = new ZipArchive();
$zip->open('Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();
foreach (['n/a — a computed field','continuing and shifter become old','is derived, never entered'] as $f) {
    printf("%2d  %s\n", substr_count($xml, $f), $f);
}
