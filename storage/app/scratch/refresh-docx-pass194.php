<?php

/**
 * Pass 194 — the §21.2 "Seeded?" column, rewritten through the edit engine.
 *
 * Pass 193 refused itself: four of these numbers occur twice document-wide, so a plain needle could not tell
 * this cell's `:189` from somebody else's, and the correct answer to that ambiguity is not a longer quote but
 * the cell itself. `docx-edit-engine.php` addresses a cell by (a unique string in its row, a prefix unique
 * among that row's cells) and rewrites the whole cell, which is how a citation that shares a number with
 * another row still gets addressed by position.
 *
 * Every target line was verified as the model's create call in `DevReferenceDataSeeder.php` — see
 * `lane70-seeder-cites.php`, the read-only version of this. Five cells were quoting a line that is not a
 * create call at all (a `];`, a comment about a dropped program, and three lines of room/section data); one
 * (`academicyears`) was right and simply unnamed.
 *
 *   php storage/app/scratch/refresh-docx-pass194.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass194.php --apply  # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$cellEdits = [
    ['Program catalog; the two exam flags live here', 'Yes (', 'Yes ( DevReferenceDataSeeder.php:198 )'],
    ['Belongs to a course', 'Yes (', 'Yes ( DevReferenceDataSeeder.php:271 )'],
    ['One per course is auto-created by the seeder', 'Yes (', 'Yes ( DevReferenceDataSeeder.php:674 )'],
    ['The re-run deletes and rebuilds the mapping', 'Yes (', 'Yes ( DevReferenceDataSeeder.php:690 )'],
    ['PSA Birth Certificate and Form 138', 'Yes, 4 (', 'Yes, 4 ( DevReferenceDataSeeder.php:732 )'],
    ['Read only as a dropdown source', 'Yes (', 'Yes ( DevReferenceDataSeeder.php:137, :141, :148 )'],
];

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass194.docx', [], $cellEdits, $apply);
