<?php

/**
 * Pass 34 — the correction to the correction. Pass 31 asserted that two rows of §22.3 carry the
 * chapter's own wording; comparing every description cell against live roles.description
 * (probe-docx-role-descriptions.php) says one: only OfficeHead is annotated.
 */

require __DIR__.'/docx-edit-engine.php';

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$textEdits = [
    ['The description column quotes roles.description except where this chapter annotates it — OfficeHead and GuidanceStaff carry the chapter’s own wording, which is why the two do not match the seeder string.',
        'The description column quotes roles.description verbatim on sixteen of the seventeen rows, compared against live ems the same day; only OfficeHead is annotated, because ruling 7’s split needed a longer note than one cell can hold.'],
];

docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-06-pass34.docx', $textEdits, [], $apply);
