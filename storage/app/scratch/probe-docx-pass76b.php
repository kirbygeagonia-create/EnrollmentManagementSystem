<?php
// Where does the "No retention or purge" sentence break, and what contiguous needle can carry the edit?
$z = new ZipArchive();
$z->open('Documentation/EMS Complete Documentation.docx');
$xml = $z->getFromName('word/document.xml');
$z->close();

$at = strpos($xml, 'No retention or purge');
printf("start: %s\n\n", $at === false ? 'NOT FOUND' : $at);

if ($at !== false) {
    echo substr($xml, $at - 200, 1600)."\n\n";
}

foreach ([
    'BenchmarkHeavyQueries',
    'CreateAdminUser',
    'PrintFidelitySamples',
    'contains only',
    'Nothing archives or trims',
    'did not implement those two methods.',
] as $needle) {
    printf("%-40s %d\n", $needle, substr_count($xml, $needle));
}
