<?php
$z = new ZipArchive;
$z->open(dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx');
$x = $z->getFromName('word/document.xml'); $z->close();
foreach ([
    '<w:t xml:space="preserve">) and renders </w:t>',
    '<w:t xml:space="preserve"> as an empty state; </w:t>',
    '<w:t xml:space="preserve">notifications.length === 0</w:t>',
    '<w:t xml:space="preserve">AuthenticatedLayout.jsx:57-69</w:t>',
    'so a Student-addressed row cannot even be marked read.',
] as $a) { printf("%2d  %s\n", substr_count($x, $a), $a); }
