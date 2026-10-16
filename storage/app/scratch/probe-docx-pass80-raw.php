<?php

/**
 * Pass 80 raw-window probe — for every citation an edit invalidates, show the raw XML
 * around it. A citation is only replaceable as a whole-run needle if it is contiguous
 * inside one <w:t>; the document splits sentences across runs, so this decides the kind.
 */

$root = dirname(__DIR__, 3);

$zip = new ZipArchive();
$zip->open($root.'/Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$needles = [
    'AdmissionController.php:287-305',
    'AdmissionController.php:364-410',
    'AdmissionController.php:377-410',
    'AdmissionController.php:377',
    'AdmissionController.php:390',
    'AdmissionController.php:395',
    'IDController.php:202-213',
];

foreach ($needles as $n) {
    $count = substr_count($xml, $n);
    echo "\n=== {$n}  raw count {$count}\n";
    $from = 0;
    $i = 0;
    while (($pos = strpos($xml, $n, $from)) !== false && $i < 4) {
        $i++;
        $window = substr($xml, max(0, $pos - 170), 300);
        echo '  ['.($pos).'] …'.str_replace(["\n", "\t"], '', $window)."…\n";
        $from = $pos + strlen($n);
    }
}
