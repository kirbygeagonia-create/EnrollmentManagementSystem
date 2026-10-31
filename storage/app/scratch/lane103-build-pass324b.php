<?php

/**
 * Builds refresh-docx-pass324b.php — the §1.1 row that records the ENUM-sort ruling.
 *
 * The machinery is pass 321b's and pass 323b's (clone the newest §1.1 row, empty every run of each cell, write by
 * cell offset, refuse unless the flattened cell equals the text handed to it). This generator exists so the new pass
 * is a self-contained file rather than one that reads its own text from an untracked scratch artefact — a record a
 * later clone cannot reproduce is not a record.
 *
 * Usage: php storage/app/scratch/lane103-build-pass324b.php
 */

$root = dirname(__DIR__, 3);
$base = (string) file_get_contents($root.'/storage/app/scratch/refresh-docx-pass323b.php');

$anchorOld = "\$anchor = '2026-10-30 The seventh sortable table, and a column withheld for its type';";
$anchorNew = "\$anchor = '2026-10-30 The eighth sortable table, and the tab that chooses which set the order arranges';";

$recordStart = strpos($base, "\$record = '");
$tailStart = strpos($base, '$row = $writeCell($template', $recordStart === false ? 0 : $recordStart);
if ($recordStart === false || $tailStart === false) {
    fwrite(STDERR, "REFUSED: the record assignment or its following writer was not found.\n");

    exit(1);
}

$recordText = 'The owner ruled on the sorting of ENUM columns, and the ruling reversed a rule this register had carried '
    .'since the clinic queue joined the day before. An ENUM column MAY be given a header: the order the school’s server '
    .'applies is the declaration order the school itself wrote into the migration, which for an enrollment status is the '
    .'pipeline itself — pending, evaluated, assessed, paid — and for a student type is the order the registrar reads. '
    .'What the ruling moves is the test. The suite compiles the same enum() declaration into a varchar with a check and '
    .'sorts the stored word, so the expected sequence is now read from the connection by one shared helper, '
    .'Tests\Support\EnumColumnOrder, which refuses a column that names no enum on the mysql driver rather than guessing; '
    .'four desk screens ask it, and a second copy of the parse would be the two-answers-for-one-word defect this '
    .'document has already removed for year levels, status tones and citation attribution. The clinic and ID queues '
    .'therefore GAIN the Student Type and Standing headers they were built without, and each gained a case that asserts '
    .'the sequence this connection answers. Two columns stay withheld, for a reason the ruling does not touch: Clinic '
    .'Status is painted from whether a record exists on the row and ID Status from the status of its FIRST request, so '
    .'neither is a column an order can be asked of — ordering ID Status would mean deciding what two requests on one '
    .'enrollment amount to, which the school has never been asked. The same sweep closed an older gap: the Registrar’s '
    .'Status header had been offered since the first wiring and answered by no case at all, and now has one, over a '
    .'fixture that interleaves Paid and Assessed so neither id order satisfies it. Both mutant harnesses had to be '
    .'re-cut the same day rather than left running, because each carried a leg that PLANTED an offered ENUM key as the '
    .'defect — under the ruling that leg asks a test to fail on correct code — so it now plants an invented key instead, '
    .'and eleven and thirteen legs kill again. The two §1.1 rows written earlier today state the old rule in their own '
    .'words and are left as written: they are records of the day they were read, and this row is the day the ruling '
    .'changed what the screen does.';

$chunks = explode("\n", wordwrap($recordText, 96, "\n", true));
$lines = ["\$record = '".addcslashes(array_shift($chunks), "'\\")."'"];
foreach ($chunks as $chunk) {
    $lines[] = "    .'".addcslashes($chunk, "'\\")."'";
}

$newRecord = implode("\n", $lines).";\n\n";
// Offsets were measured on $base, so the record is spliced in BEFORE any anchor text changes length.
$out = substr_replace($base, $newRecord, $recordStart, $tailStart - $recordStart);
$out = str_replace($anchorOld, $anchorNew, $out);

$mine = "\$mine = '2026-10-30 A ruled reversal: an ENUM column may be sorted, and the test asks the server which order'";
$mineOld = "\$mine = '2026-10-30 The eighth sortable table, and the tab that chooses which set the order arranges'";
if (substr_count($out, $mineOld) !== 1) {
    fwrite(STDERR, "REFUSED: the row title to replace occurs ".substr_count($out, $mineOld)." time(s).\n");

    exit(1);
}
$out = str_replace($mineOld, $mine, $out);

foreach ([['refresh-docx-pass323b.php', 'refresh-docx-pass324b.php'], ['Pass 323b', 'Pass 324b'],
    ['pass323b-backup', 'pass324b-backup'], ['§1.1 gains the eighth-table row', '§1.1 gains the ENUM-ruling row'],
    ['pass 323b applied: §1.1 records the eighth sortable table and the tab that chooses the set.',
        'pass 324b applied: §1.1 records the ruling and the reversal it caused.'],
    ["\$mine, 'thirteen legs, all killed', 'twenty-eight still do not'",
        "\$mine, 'two-answers-for-one-word defect', 'plants an invented key instead'"],
    ["REFUSED: the row this pass clones matched ", "REFUSED: the row this pass clones matched "],
] as [$from, $to]) {
    $h = substr_count($out, $from);
    if ($h !== 1) {
        fwrite(STDERR, "REFUSED: label needle [".mb_substr($from, 0, 50)."] occurs {$h}, want 1.\n");

        exit(1);
    }
    $out = str_replace($from, $to, $out);
}

$target = $root.'/storage/app/scratch/refresh-docx-pass324b.php';
file_put_contents($target, $out);

echo "built ".basename($target)."; now lint and dry-run it.\n";
