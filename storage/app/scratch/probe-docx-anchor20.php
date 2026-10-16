<?php
$zip = new ZipArchive();
$zip->open('Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();
foreach (['Reachability was established by listing every','no dispatch of either job anywhere',
 'A lost slip is replaced only after a payment is recorded','ems:print-fidelity renders the Blade templates itself'] as $f) {
    printf("%2d  %s\n", substr_count($xml, $f), $f);
}
