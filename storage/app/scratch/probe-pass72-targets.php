<?php
// Locate the four passages pass 72 must change, with their absolute offsets in word/document.xml
// and whether each survives as one <w:t> run or is split (which decides the engine kind to use).
$z = new ZipArchive;
$z->open('Documentation/EMS Complete Documentation.docx');
$xml = $z->getFromName('word/document.xml');
$z->close();

$targets = [
    'exam-policy-cite' => 'ExamPolicy.php:32',
    'dashboard-cite' => 'DashboardController.php:41',
    'table9-eval-queue' => 'Academic Standing and Year Level are not columns',
    'table15-b3' => 'the year level is not recalculated',
    'office8-mention' => 'office 8',
    'queue-counts' => 'queue-counts',
];

$flat = preg_replace('/<[^>]+>/', '', $xml);
foreach ($targets as $name => $needle) {
    $pos = 0;
    $found = 0;
    while (($hit = strpos($xml, $needle, $pos)) !== false) {
        $found++;
        printf("%-20s RAW-OFFSET %-9d  %s\n", $name, $hit, str_replace("\n", ' ', substr($xml, max(0, $hit - 130), 300)));
        if ($found >= 2) {
            break;
        }
        $pos = $hit + 1;
    }
    if ($found === 0) {
        // split across runs: show the flattened context instead
        $fpos = strpos($flat, $needle);
        printf("%-20s NOT a contiguous run; flat offset %s  …%s…\n", $name, $fpos === false ? 'absent' : $fpos,
            $fpos === false ? '' : str_replace("\n", ' ', substr($flat, max(0, $fpos - 90), 260)));
    }
    echo str_repeat('-', 100)."\n";
}
