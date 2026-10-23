<?php

/**
 * Pass 178b — one word in P-2's row that counts wrongly.
 *
 * The row lists five facts the form omits (Student ID, program, year level, school year, semester) and then says the
 * record behind it "holds all four". The four is left over from the sentence before lane 62 widened it to name the
 * school year and the semester separately, and the numbers either side of it — the Consequence cell's "four identity
 * facts" — count a different set (who, which program, which term, which school year: the semester folded into the
 * period). So the fix is to stop counting in the cell that lists five: say what is true without a numeral.
 *
 * DRY RUN unless --apply.
 */

require __DIR__.'/docx-edit-engine.php';

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$textEdits = [
    [
        'where not one identity token appears although the record behind it holds all four.',
        'where not one identity token appears although the record behind it holds every one of them.',
    ],
];

docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/pass178b-backup.docx', $textEdits, [], $apply);
