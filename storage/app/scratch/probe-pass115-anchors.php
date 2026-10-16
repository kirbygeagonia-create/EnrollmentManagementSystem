<?php
$z = new ZipArchive;
$z->open(dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx');
$x = $z->getFromName('word/document.xml'); $z->close();
foreach ([
    'keep doing so until the owner rules on amending stored records',
    'What this does not do is rewrite history: the 29 enrollments that already credit',
    'the older rows stayed where they were.',
    'a Guidance account with no evaluation.sign',
    '15 of them still in progress in three different colleges',
    'while 29 rows name',
] as $a) { printf("%2d  %s\n", substr_count($x, $a), $a); }
