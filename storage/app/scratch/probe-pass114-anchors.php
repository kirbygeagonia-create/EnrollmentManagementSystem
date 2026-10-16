<?php
$z = new ZipArchive;
$z->open(dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx');
$x = $z->getFromName('word/document.xml'); $z->close();
$anchors = [
    'AuthenticatedLayout.jsx:57-69',
    'and renders notifications.length === 0 as an empty state',
    'so a Student-addressed row cannot even be marked read.',
    'markRead and markAllRead only ever match Staffusers rows',
    'Dashboard.jsx',
    'GlobalSearchModal.jsx',
    'notifications.length === 0',
];
foreach ($anchors as $a) { printf("%2d  %s\n", substr_count($x, $a), $a); }
