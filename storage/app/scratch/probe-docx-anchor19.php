<?php
$zip = new ZipArchive();
$zip->open('Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();
foreach (['the two jobs in app/Jobs are the only ones','the two jobs in app/Jobs','app/Jobs',
 'one page, every subject','downloadSubjectLoad() serves the PDF','downloadSlip() serves the PDF',
 'the download route answers the same screen with the PDF','attributed to the first seated student',
 'the digital row is reset to Pending so the offices sign again','yes — recordIssue() on both paths',
 'yes — one issue row for the print','PrintService::printClassCards()','PrintService::printClearanceSlip()',
 'PrintService::printSubjectLoad()','yes — recordIssue() (ClearanceController.php:312)','yes since 2026-10-01',
 'Registrar/PrintSubjectLoad','ClearancePolicy.php:112-135','ClearanceController.php:271','Registrar/PrintSlip'] as $f) {
    printf("%2d  %s\n", substr_count($xml, $f), $f);
}
