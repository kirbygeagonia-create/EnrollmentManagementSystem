<?php
$zip = new ZipArchive();
$zip->open(dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();
file_put_contents(dirname(__DIR__, 3).'/storage/app/scratch/document-pass25.xml', $xml);
$needles = [
    'ClinicPolicy.php:58',
    'Signing the Clinic box happens in the same transaction.',
    'id.validateRequest',
    'the office’s own permission',
    "the office's own permission",
    'clearance.approve stayed deliberately',
    'ID Office staff for ID requests, face-photo validation, and card release',
    'and §24 states which column listings are not yet re-generated.',
    'Live proof that this removed cross-desk reach',
    'only SysAdmin may sign across offices',
];
foreach ($needles as $n) {
    printf("%20d  %s\n", substr_count($xml, $n), $n);
}
