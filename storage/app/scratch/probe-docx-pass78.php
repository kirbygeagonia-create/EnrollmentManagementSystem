<?php
// Pass 78 pre-flight: what is the exact tail of the 2026-10-10 revision row now?
$z = new ZipArchive();
$z->open('Documentation/EMS Complete Documentation.docx');
$xml = $z->getFromName('word/document.xml');
$z->close();

$at = strpos($xml, 'The five-lane audit the owner asked for');
$end = strpos($xml, '</w:t>', $at);
echo 'tail: ...'.substr(strip_tags(substr($xml, $end - 320, 320)), -300)."\n\n";

foreach ([
    'claim is not vacuous.',
    'nine tests pin the report',
    'refunded',
    'badge tone',
    'type scale',
] as $needle) {
    printf("%-28s %d\n", $needle, substr_count($xml, $needle));
}
