<?php

/**
 * Pass 119 — two defects pass 118 introduced into §18.6's last item, both found by the tools rather than by me.
 *
 * 1. `clinicrecords.php:17` — the model is `Clinicrecords.php`, capital C, like every other model in
 *    `app/Models`. `sweep-docx-citations.php` exited non-zero with "1 name(s) not in the repository:
 *    clinicrecords.php", which is the citation sweep doing exactly its job: a document that cites a file the
 *    repository has not is asserting something nobody can check. (My earlier measurements read the file through
 *    a path that Windows resolved case-insensitively, so the lowercase name looked fine on this machine.)
 * 2. "while idrequests.bloodType; typed in by the student" — a semicolon where the sentence needs a verb.
 *    The run I replaced had begun "; the latter is re-declared", and I carried its punctuation over without
 *    rereading the joined paragraph. The docx rule is explicit: after any splice, re-dump and read the WHOLE
 *    sentence, because run and paragraph counts stay perfect while the prose reads as two fragments glued.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';

$textEdits = [
    ['clinicrecords.php:17), while ', 'Clinicrecords.php:17), while '],
    ['; typed in by the student at the desk and checked only against',
        ' is typed in by the student at the desk and checked only against'],
];

require __DIR__.'/docx-edit-engine.php';
docxApply($src, $root.'/storage/app/scratch/docx-backup-2026-10-15-pass119.docx',
    $textEdits, [], in_array('--apply', $argv, true));
