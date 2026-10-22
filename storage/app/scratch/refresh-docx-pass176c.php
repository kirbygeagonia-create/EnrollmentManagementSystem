<?php

/**
 * Pass 176c — two wording defects inside the row pass 176 just wrote.
 *
 * "the settings table seeds and the six print views fall back to X" makes one object carry two verbs, and
 * "GuestLayout.jsx:50 and :68" is the bare continuation AGENTS names as the citation class no `File.php:NN` checker
 * can see: the number on the page is right, the file it belongs to is not stated. A row that documents a hazard
 * should not reproduce it.
 *
 * DRY RUN unless --apply.
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$textEdits = [
    [
        'the settings table seeds and the six print views fall back to “Southeast Asian Institute of Technology”',
        'the value the settings table holds and the fallback each of the six print views types into its own call both '
            .'read “Southeast Asian Institute of Technology”',
    ],
    [
        'GuestLayout.jsx:50 and :68, this document’s own title line and four Documentation markdown files use the '
            .'sheet’s spelling.',
        'GuestLayout.jsx:50 and GuestLayout.jsx:68, this document’s own title line and four Documentation markdown '
            .'files use the sheet’s spelling.',
    ],
];

docxApply($src, $root.'/storage/app/scratch/pass176c-backup.docx', $textEdits, [], $apply);
