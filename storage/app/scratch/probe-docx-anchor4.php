<?php
// Shortest contiguous fragment that still identifies each target — Word splits prose runs at
// arbitrary points, so a whole sentence is often not a substring of document.xml at all.
$src = 'Documentation/EMS Complete Documentation.docx';
$zip = new ZipArchive();
$zip->open($src);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$fragments = [
    'All 13 refdata',
    'refdata.*.manage permissions are held by SysAdmin alone',
    'held by SysAdmin alone',
    '13 refdata',
    'deleting an office row',
    'orphans staffusers',
    'There are 10 rows here',
    '10 rows here',
    'names 9 cases',
    'The seeded office 8',
    'exists as data only',
    'printed as an office name',
    'because the table has no requirement text',
    'Ten rows print today',
    'Requirements block.',
    '§21.1 and gap D-1 keep the two readings apart',
    'Elective configuration is unreachable',
    'no seeder sets them',
    'therefore never fire on a fresh install',
    'Data artifact to correct',
    'cannot describe a requirement',
];
foreach ($fragments as $f) {
    printf("%2d  %s\n", substr_count($xml, $f), $f);
}
