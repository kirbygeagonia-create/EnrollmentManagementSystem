<?php

/**
 * Pass 135b — the sentence pass 135 wrote still called Kevin "the record the desk can show".
 *
 * Reading the desk's own query said otherwise. IDController::index() builds its queue from
 * Enrollments whose *earliest pending* workflow step is the ID office's (app/Http/Controllers/ID/IDController.php
 * — the whereHas at the top of index()), so an enrollment whose ID box is already signed cannot appear on that
 * screen at all, and Index.jsx links to id.show only from that list. Kevin Santos's box is signed, which is why
 * the guide can show his `validated` status on his record page and not on the hub — and the same reason the
 * guide's old "Juan's validated ID at the hub" step was never walkable either, independent of the request row
 * that was deleted under the owner's ruling of 2026-10-11.
 *
 * Measured on live ems by reproducing the predicate in SQL: the hub holds exactly two rows today, DEMO-2026-016
 * Danica Sotto (enrollment 91) and DEMO-2026-026 Nena Alcantara (enrollment 876), each with one pending request.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';

$textEdits = [
    ['while the record the desk can show is Kevin Santos (DEMO-2026-017), whose validated request and photo '
        .'this tool itself writes.',
        'while the dataset’s finished example is Kevin Santos (DEMO-2026-017), whose validated request and '
        .'photo this tool itself writes — and whom the hub cannot list, because index() queues only the '
        .'enrollments whose earliest pending step is the ID office’s and his box is signed. His validated '
        .'status therefore reads on his own record page; the screen that shows a completed request’s face is '
        .'reached by address, not by click. The queue this build does show, measured on live ems, is two '
        .'rows: DEMO-2026-016 and DEMO-2026-026, each with one pending request.'],
];

require __DIR__.'/docx-edit-engine.php';
docxApply($src, $root.'/storage/app/scratch/docx-backup-2026-10-16-pass135b.docx',
    $textEdits, [], in_array('--apply', $argv, true));
