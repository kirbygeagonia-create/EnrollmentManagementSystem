<?php
$z = new ZipArchive(); $z->open(dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx');
$x = $z->getFromName('word/document.xml'); $z->close();
foreach ([
    'number (§26)',
    'term, and every block-schedule print',
    'position, plus both blockSchedule rows are NULL',
] as $n) {
    printf("%-45s %d\n", substr($n, 0, 42), substr_count($x, $n));
}
preg_match_all('/[^.]{0,60}§26[^.]{0,40}/', preg_replace('/<[^>]+>/', '', $x), $m);
echo "\nall §26 mentions in flat text (".count($m[0])." windows):\n";
foreach (array_slice(array_unique($m[0]), 0, 8) as $h) { echo '  - '.trim(preg_replace('/\s+/', ' ', $h))."\n"; }
