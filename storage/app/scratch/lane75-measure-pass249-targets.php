<?php

// Throwaway measurement for pass 249: the new bounds of gradeHistory(), and how many times each
// needle the pass intends to write actually occurs in word/document.xml.

$root = dirname(__DIR__, 3);

$service = explode("\n", str_replace("\r\n", "\n", (string) file_get_contents($root.'/app/Services/AcademicStandingService.php')));
$start = $end = null;
$depth = 0;
foreach ($service as $n => $line) {
    if ($start === null && str_contains($line, 'public function gradeHistory(')) {
        $start = $n + 1;
    }
    if ($start !== null && $n + 1 >= $start) {
        $depth += substr_count($line, '{') - substr_count($line, '}');
        if ($depth === 0 && trim($line) === '}') {
            $end = $n + 1;
            break;
        }
    }
}
echo 'AcademicStandingService::gradeHistory() spans :'.(int) $start.'-'.(int) $end."\n";

$scale = explode("\n", str_replace("\r\n", "\n", (string) file_get_contents($root.'/app/Models/Gradescale.php')));
foreach ($scale as $n => $line) {
    if (str_contains($line, 'function passingCeiling')) {
        echo 'Gradescale::passingCeiling() begins at :'.($n + 1)."\n";
    }
    if (str_contains($line, 'function isConfigured')) {
        echo 'Gradescale::isConfigured() begins at :'.($n + 1)."\n";
    }
}

$z = new ZipArchive;
if (! $z->open($root.'/Documentation/EMS Complete Documentation.docx')) {
    fwrite(STDERR, "REFUSED: cannot open the docx.\n");
    exit(1);
}
$xml = (string) $z->getFromName('word/document.xml');
$z->close();

echo 'document.xml is '.strlen($xml)." bytes\n\n";

$needles = [
    'RegistrarController.php:110-129',
    'Registrar/Show.jsx:244-255',
    ':450',
    'Year {n} ({standing})',
    'Standing and year level are rendered at',
    'this is currently the only desk detail page that shows both.',
    'its page badge reads',
    '17. Stage 10 — Blocking and Scheduling',
    'Gate 4 is the only clearance enforcement anywhere',
];
foreach ($needles as $needle) {
    printf("%-62s %d\n", $needle, substr_count($xml, $needle));
}

echo "\nfile-prefixed number tokens into RegistrarController.php: ".preg_match_all('/RegistrarController\.php:\d+(?:-\d+)?/', $xml)."\n";
echo "tokens with a start below 113 (must not be shifted +15): ";
preg_match_all('/RegistrarController\.php:(\d+)(?:-(\d+))?/', $xml, $m);
$below = 0;
foreach ($m[1] as $n => $from) {
    if ((int) $from < 113) {
        $below++;
        echo ' '.$from.'-'.($m[2][$n] ?? $from);
    }
}
echo "\n";
