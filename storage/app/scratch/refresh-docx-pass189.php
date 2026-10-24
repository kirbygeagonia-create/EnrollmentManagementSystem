<?php

/**
 * Pass 189 — the bare continuations the line sweep could not see.
 *
 * Extending sweep-docx-citations.php to judge `:NN` after a file named earlier on the same line turned up
 * five pointers that had drifted and one sentence whose claim the build had overtaken. All seven were read
 * against the current files, not shifted by arithmetic:
 *
 *   ClearanceController.php:404  ->  :408   downloadSlip() — printSlip() grew four lines when P-17 made the
 *                                              slip name its own term’s enrollment, and :404 is now blank.
 *   ClearanceController.php:380-406 -> 380-403  printSlip() ends at its own brace.
 *   AssessmentController.php:266-282 -> 432-443  finalize()’s DB::transaction; 266-282 is scholarship code.
 *   AssessmentController.php:161 -> 180      the commented-out auto-transition, which the lane 53 batch moved.
 *   ( :248-267 ) -> IDController.php:248-267   the numbers were right and named no file at all, so the nearest
 *                                              name on the cell was IDPolicy.php, a 135-line file.
 *   Evaluation/Index.jsx:74 -> :184-187      the tile, in two sentences; :74 is the Student Name column.
 *   §13.6’s “shows student type but not standing (:34-36)” — the page has a Proposed Standing column at
 *                                              :84-88 and a Student Type column at :79-83, so the sentence was
 *                                              describing an older build while naming lines that are blank.
 *
 *   php storage/app/scratch/refresh-docx-pass189.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass189.php --apply  # write
 */
$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$fails = 0;
$say = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'BAD ', $what);
    if (! $ok) {
        $fails++;
    }
};

$zip = new ZipArchive;
if ($zip->open($src) !== true) {
    exit("REFUSED: cannot open the docx.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

/** [needle, replacement, expected count, why the count is what it is] */
$edits = [
    [
        'ClearanceController.php:380 and :404 , BlockingController.php:587',
        'ClearanceController.php:380 and :408 , BlockingController.php:587',
        1,
        '§24’s documentprintlog row names the two clearance print actions',
    ],
    [
        'downloadSlip() serves the PDF ( :404 )',
        'downloadSlip() serves the PDF ( :408 )',
        1,
        'the print-route table’s clearance row',
    ],
    [
        'ClearanceController.php:380-406 keys the lookup',
        'ClearanceController.php:380-403 keys the lookup',
        1,
        '§25.4 P-10’s evidence clause, tightened to the action’s own brace',
    ],
    [
        ':266-282',
        ':432-443',
        1,
        'finalize()’s transaction, which is where the transition really happens',
    ],
    [
        ':161',
        ':180',
        1,
        'the commented-out auto-transition line',
    ],
    [
        ':248-267',
        'IDController.php:248-267',
        1,
        'the ID validate action, which named no file',
    ],
    [
        'Evaluation/Index.jsx:74',
        'Evaluation/Index.jsx:184-187',
        2,
        'the tile is cited in §13.6 and again in the desk-queue row, so BOTH must move',
    ],
    [
        ' counts transferee and shifter rows as one &quot;transferees&quot; figure, and the same page shows student type but not standing (',
        ' is the tile labelled “Transferees / Shifters”, which reads the server’s sum over both kinds '
        .'( EvaluationController.php:107 ), so the figure names the two categories it combines; and the same '
        .'page already shows each queued student’s proposed standing (',
        1,
        '§13.6’s queue-labelling sentence',
    ],
    [
        ':34-36',
        'Evaluation/Index.jsx:84-88',
        1,
        'the standing column the sentence claimed the page lacks',
    ],
    [
        '), so the desk cannot see which queued students are the flexible-load cases.',
        ') beside its student type ( Evaluation/Index.jsx:79-83 ), so the desk can see which queued students '
        .'are the flexible-load cases. Re-measured 2026-10-24: the label and the standing column are both on '
        .'the screen, so what is left of the original quirk is the combined count, not a missing field.',
        1,
        'the sentence’s tail',
    ],
    [
        'is the summary, which counts transferee and shifter rows as one &quot;transferees&quot; figure (',
        'is the summary, which counts transferee and shifter rows as one figure labelled “Transferees / Shifters” (',
        1,
        'the desk-queue row, whose “transferees” quotation is the stat key rather than the printed label',
    ],
];

$staged = $xml;
foreach ($edits as $i => [$from, $to, $want, $what]) {
    $n = substr_count($xml, $from);
    $say($n === $want, 'edit '.($i + 1).' found '.$n.' time(s), expected '.$want.' — '.$what);
    foreach (['<', '>', '&'] as $ch) {
        $say(! str_contains($to, $ch), 'edit '.($i + 1).' writes no raw '.$ch);
    }
    $staged = str_replace($from, $to, $staged);
}

// The `&quot;` in two needles is the stored form of a quote inside a run; nothing new may be written as an
// entity, so assert the staged text decoded from XML says what the rulings say.
$say(substr_count($staged, '&quot;transferees&quot;') === 0, 'no run still quotes the stat key as a printed label');
$say(substr_count($staged, '“Transferees / Shifters”') === 2, 'the printed label is quoted in both sentences');
$say(substr_count($staged, ':404') === 0, 'no pointer still lands on the blank line between the two clearance actions');
$say(substr_count($staged, 'Evaluation/Index.jsx:74') === 0, 'the old tile line is gone from both rows');
$say(substr_count($staged, 'shows student type but not standing') === 0, 'the overtaken claim is gone');
$say(@(new DOMDocument)->loadXML($staged) !== false, 'the staged document.xml parses');
printf("\n  staged byte delta: %+d\n", strlen($staged) - strlen($xml));
printf("%d guard(s) failed.\n", $fails);
if ($fails > 0) {
    echo "NOTHING WRITTEN.\n";
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN: seven drifted pointers and one overtaken claim would be corrected.\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass189.docx');
$z = new ZipArchive;
if ($z->open($src) !== true) {
    exit("REFUSED: the docx cannot be reopened for the write — nothing written.\n");
}
if ($z->getFromName('word/document.xml') !== $xml) {
    exit("REFUSED: document.xml changed under this pass since it was read — nothing written.\n");
}
$z->addFromString('word/document.xml', $staged);
$z->close();
echo "WROTE {$src}\n";
