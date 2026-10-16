<?php

/** Which Preflight.php lines does the authority docx point at, and does each still mean what it says? */
$docx = 'C:/Users/ADMIN/OneDrive/Documents/3rd_Year/EnrollmentManagementSystem/Documentation/EMS Complete Documentation.docx';
$src = str_replace('\\', '/', dirname(__DIR__, 3)).'/app/Console/Commands/Preflight.php';

$z = new ZipArchive();
if ($z->open($docx) !== true) {
    echo "CANNOT OPEN THE DOCX\n";
    exit(1);
}
$xml = $z->getFromName('word/document.xml');
$z->close();

preg_match_all('/Preflight\.php:(\d+)(?:-(\d+))?/', $xml, $m, PREG_OFFSET_CAPTURE);
echo 'citations found: '.count($m[0])."\n";
$lines = file($src);
foreach ($m[0] as $i => $hit) {
    [$text, $at] = $hit;
    $from = (int) $m[1][$i][0];
    $to = $m[2][$i][0] === '' || $m[2][$i][0] === null ? $from : (int) $m[2][$i][0];
    $sentence = str_replace(["\r\n", "\n"], ' ', preg_replace('/\s+/', ' ', strip_tags(substr($xml, max(0, $at - 320), 460))));
    printf("\n  %s   (lines %d..%d of %d in the file)\n", $text, $from, $to, count($lines));
    foreach (range($from, min($to, count($lines))) as $ln) {
        printf("     %4d  %s\n", $ln, rtrim($lines[$ln - 1]));
    }
    echo '     context: …'.mb_substr($sentence, 0, 300)."…\n";
}
