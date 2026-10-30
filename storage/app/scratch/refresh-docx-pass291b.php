<?php

/**
 * Pass 291b — two defects pass 291 wrote into the G-43 row, both found by reading the cell back.
 *
 *  (a) A duplicated claim: the row already said the demo generator is what the static gates do not analyse, and
 *      291 said it again two clauses earlier. Two answers to one question in one cell is the defect this
 *      document names for year levels and for register tallies, reached inside a single paragraph.
 *  (b) A false instrument: 291 wrote that all 38 writes moved to `SchoolTime::today()`. Five of them are the
 *      generator's deliberate ages (`now()->subMonths(9)` and `subMonths(15)`), which moved to
 *      `SchoolTime::civilDateOf(...)` and kept their age. `today()` would have re-dated a returning student's
 *      enrollment to the day the tool ran.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass291b.php [--apply]
 */

$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', array_slice($argv, 1), true);

$edits = [
    [
        '38 sites that feed them: eight on the desk paths and thirty inside the demo generator, which `phpstan` does not analyse and `pint.json` excludes.',
        '38 sites that feed them: eight on the desk paths and thirty inside the demo generator.',
        1,
    ],
    [
        'All 38 moved to ',
        'All 38 now answer the school’s day — ',
        1,
    ],
    [
        ' with this batch, four of them with a behaviour case',
        ' for the ones that mean today, and `SchoolTime::civilDateOf(…)` for the five the generator deliberately ages, which kept their age rather than becoming today. Four of the desk sites carry a behaviour case',
        1,
    ],
];

$zip = new ZipArchive;
if (! $zip->open($docx)) {
    fwrite(STDERR, "REFUSED: cannot open {$docx}.\n");

    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$failures = 0;
foreach ($edits as $i => [$from, $to, $want]) {
    $n = substr_count($xml, $from);
    printf("%s %d  want %d  got %d  [%s]\n", $n === $want ? 'ok  ' : 'MISS', $i, $want, $n, mb_substr($from, 0, 58));
    if ($n !== $want) {
        $failures++;
    }
}
if ($failures > 0) {
    fwrite(STDERR, "\nREFUSED: {$failures} needle(s) did not land. NOTHING WRITTEN.\n");

    exit(1);
}

$textEdits = array_map(fn (array $e): array => [$e[0], $e[1], null, $e[2]], $edits);
docxApply($docx, $root.'/storage/app/scratch/pass291b-backup-'.date('Ymd-His').'.docx', $textEdits, [], $apply);
