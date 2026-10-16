<?php
$zip = new ZipArchive();
$zip->open('Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();
foreach (['gate sits late','permits the student to proceed to Phase 2','A row exists for','and non-null receivedBy',
 'the gate queries only for a period','A closed period disables the clearance gate','returns pass when no',
 'Enforced, deliberately permissive','deliberately permissive','The clearance gate passes for every returning student',
 'the two copies must be edited together','implement the same rule independently','Hard-coded new','Later corrected by the Registrar under BR31',
 'Print audit','every desk print writes one','Enforced, and deliberately permissive','both citations are printed everywhere',
 'Both citations are printed everywhere'] as $f) {
    printf("%2d  %s\n", substr_count($xml, $f), $f);
}
