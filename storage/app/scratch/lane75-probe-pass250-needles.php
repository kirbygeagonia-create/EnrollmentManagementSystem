<?php

/**
 * How many copies of each pass-250 needle does the authority docx actually hold?
 *
 * A needle is counted two ways: in the flattened <w:t> prose (which is how a reader sees the sentence) and in
 * raw document.xml after escaping (which is how the edit engine addresses it). They disagree whenever a phrase
 * is split across runs, and the disagreement is the finding — pass 250 must take such a sentence through the
 * para/cell kind, not a text needle.
 *
 * Usage: php storage/app/scratch/lane75-probe-pass250-needles.php [--docx=<path>]
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';

foreach (array_slice($argv, 1) as $arg) {
    if (! preg_match('/^--(docx)=(.*)$/', $arg, $m)) {
        fwrite(STDERR, "REFUSED: {$arg} is not a flag this tool reads (known: --docx=).\n");
        exit(1);
    }
    $docx = $m[2];
}

if (! is_file($docx)) {
    fwrite(STDERR, "REFUSED: no docx at {$docx}.\n");
    exit(1);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open {$docx}.\n");
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $xml, $runs);
$flat = html_entity_decode(implode('', $runs[1]), ENT_QUOTES | ENT_XML1);

$needles = [
    'status vocabulary row' => 'pending · approved · rejected',
    '8.5 output sentence' => 'Output: an admissions row in pending',
    '8.6 stale dialog claim' => 'The approval dialog promises',
    '24 admissions heading' => 'admissions — 11 columns',
    '24 admissionStatus row' => 'enum(pending|approved|rejected)',
    '23.8 census sentence' => '451 columns, 100 foreign-key constraints',
    '23.8 columns-documented cell' => '451',
    '24.18 column counts cell' => '409 columns documented for 55 domain tables',
    'enum table row' => 'pending, approved, rejected',
    'controller store span' => 'AdmissionController.php:100-229',
    'controller selection span' => 'AdmissionController.php:225-238',
    'controller 381-427' => 'AdmissionController.php:381-427',
    'controller 394-427' => 'AdmissionController.php:394-427',
    'controller 394-394' => 'AdmissionController.php:394-394',
    'controller 411-411' => 'AdmissionController.php:411-411',
    'controller 412-412' => 'AdmissionController.php:412-412',
    'controller 414-414' => 'AdmissionController.php:414-414',
    'controller 407-407' => 'AdmissionController.php:407-407',
    'controller 344-360' => 'AdmissionController.php:344-360',
    'controller 168-169' => 'AdmissionController.php:168-169',
    'controller 130-130' => 'AdmissionController.php:130-130',
    'controller 117-117' => 'AdmissionController.php:117-117',
    'controller 100-100' => 'AdmissionController.php:100-100',
    'show.jsx dialog pointer' => 'Admission/Show.jsx:276',
    'web 60-61' => 'routes/web.php:60-61',
];

printf("%-28s %6s %6s  %s\n", 'needle', 'flat', 'xml', 'note');
foreach ($needles as $label => $needle) {
    $inFlat = substr_count($flat, $needle);
    $inXml = substr_count($xml, htmlspecialchars($needle, ENT_QUOTES));
    printf("%-28s %6d %6d  %s\n", $label, $inFlat, $inXml, $inFlat === $inXml ? '' : '  <-- RUN-SPLIT or entity difference');
}
