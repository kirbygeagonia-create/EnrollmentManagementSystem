<?php

/**
 * Pass 195 — the last two cells of the §21.2 "Seeded?" column, plus one citation that omits its extension.
 *
 * `academicunits | No CRUD screen | Yes, 6 colleges ( :70 ) | Read as a dropdown ( :76 )` is the only row left
 * in that column whose numbers were neither named nor right. Read against the code:
 *   :70 -> DevReferenceDataSeeder.php:125  `Academicunits::updateOrCreate(` — the row says "re-synced with
 *                                            updateOrCreate", and this is that call; :70 in the seeder is a
 *                                            comment line.
 *   :76 -> ReferenceDataController.php:82  `$units = Academicunits::all(['unitId','unitName'])`, which is the
 *                                            dropdown source the cell describes; :76 in that controller is a
 *                                            blank-ish brace inside courses().
 * Both go through the engine's anchor form, because the numbers repeat elsewhere in the document and only
 * position within the row settles which citation is meant.
 *
 * The third edit is a form the sweep cannot resolve at all: `DevReferenceDataSeeder:770-773` — a real file with
 * a real range, quoted without its `.php`, so no basename lookup ever checked it. The seeder's gradescale rows
 * are at 770-773, so only the name was fixed.
 *
 *   php storage/app/scratch/refresh-docx-pass195.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass195.php --apply  # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$seeder = (string) file_get_contents($root.'/database/seeders/DevReferenceDataSeeder.php');
$controller = (string) file_get_contents($root.'/app/Http/Controllers/Admin/ReferenceDataController.php');
$seederLines = explode("\n", $seeder);
$ctrlLines = explode("\n", $controller);
echo sprintf("  check seeder :125 = %s\n", trim($seederLines[124]));
echo sprintf("  check ctrl   :82  = %s\n", trim($ctrlLines[81]));
echo sprintf("  check seeder :770-%s = %s\n", '773', trim($seederLines[769]));

$textEdits = [
    [':70', 'DevReferenceDataSeeder.php:125', 'Yes, 6 colleges ('],
    [':76', 'ReferenceDataController.php:82', 'Read as a dropdown ('],
    ['DevReferenceDataSeeder:770-773', 'DevReferenceDataSeeder.php:770-773'],
];

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass195.docx', $textEdits, [], $apply);
