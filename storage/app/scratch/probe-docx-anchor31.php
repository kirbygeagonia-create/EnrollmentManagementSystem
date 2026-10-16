<?php
$zip = new ZipArchive();
$zip->open('Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();
foreach (['used to say exactly that','Evaluation/Show.jsx:252 )','The desk does ask it: approvedPassSlip()','A returning student with an unfinished clearance is now stopped'] as $f) {
    printf("%2d  %s\n", substr_count($xml, $f), $f);
}
