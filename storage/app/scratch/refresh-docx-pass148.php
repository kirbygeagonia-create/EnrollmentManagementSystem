<?php

/**
 * Pass 148 — §28.3 gains G-18, the lane-49 mirror of the row before it.
 *
 * Lane 48 made absence render a dash instead of an invented zero. Lane 49 found the opposite error: three
 * places guarded a money or percent figure with its own truthiness (`totalPaid > 0 ? ₱… : '—'`,
 * `coveragePercent ? `${n}%` : '—'`), so a genuine 0 — an account with nothing paid, a grant covering 0% —
 * printed the same dash as a missing figure.
 *
 * The suite's measured size is deliberately NOT touched here: this batch changed no PHP test, so the number
 * in the revision row is still the one the whole-suite run printed. The row is CLONED from G-17 so it
 * inherits widths, borders, margins and run properties, and both places that state the register's size are
 * rewritten from a recount taken out of the edited bytes.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$anchor = 'A tile with no number invented one: 43 zeros, six screen-counts, and six money helpers that read null as';

$rows = [[
    'G-18',
    'A real zero painted itself as absence: a money figure that was its own guard',
    'The mirror of G-17, and found by asking the same question from the other side: when the number is 0, what '
    .'does the desk see? Three places tested a figure for truthiness or magnitude, so its own 0 fell to the '
    .'absence branch — `Students/Show.jsx:82` rendered the Paid cell as `totalPaid > 0 ? ₱… : \'—\'` (an account '
    .'with no receipts says nothing), `Students/Show.jsx:254` did the same for the Total Assessed tile, and '
    .'`Assessment/Show.jsx:100` printed a scholarship covering 0% as `—`. On live `ems` the first case is not '
    .'hypothetical: `min(studentassessments.remainingBalance)` is 0.00 today, and `scholarshiptypes.coveragePercent` '
    .'is a NOT NULL decimal the Admin desk can put 0 into. The fix is a rule about what each branch means: the '
    .'guard tests the *record or list* the figure came from (`totalAssessment == null`, `assessed.length > 0`), '
    .'never the amount, so a 0 renders as 0 and the dash keeps its new meaning — the server said nothing. The '
    .'three cells on the student’s money panel now ask the same question, which is why the Balance cell (already '
    .'written correctly, printing `₱0` for a settled account) is the shape the other two were brought to. The '
    .'class is machine-checked by storage/app/scratch/lane49-zero-shown-as-absence.php, which judges only what '
    .'text alone can settle — a money or percent figure rendered by the very branch its guard selects — and '
    .'prints its own blind spots: 18 guards whose dash sits on a line this file cannot join, 355 lines where '
    .'the tested operand is a name, code, date or error message. It is proved by '
    .'storage/app/scratch/mutate-lane49-checker.php: the three fixed shapes put back one at a time, each '
    .'required to be seen again, compared against a baseline the harness measures in the same run rather than '
    .'a number carried over from the last. One guard was examined and deliberately left alone: the class card’s '
    .'`total > 0 ? "N (x lec / y lab)" : \'—\'` (PrintClassCards.jsx:145), where 0 units is not a quantity the '
    .'school awards — no subject on this database holds lecture 0 and lab 0 — and the branch prints a '
    .'description, not a figure, so the checker classifies it as not-a-figure rather than fixing it.',
    'Closed 2026-10-18 — a zero the server answered renders as a zero; only silence renders as a dash',
    'resources/js/Pages/Students/Show.jsx:37-42; resources/js/Pages/Students/Show.jsx:82; '
    .'resources/js/Pages/Students/Show.jsx:139; resources/js/Pages/Students/Show.jsx:254; '
    .'resources/js/Pages/Assessment/Show.jsx:100; storage/app/scratch/lane49-zero-shown-as-absence.php',
]];

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open docx\n");
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    if (! $ok) {
        $fails++;
    }
};

$at = strpos($xml, $anchor);
$guard($at !== false && substr_count($xml, $anchor) === 1, 'the G-17 anchor occurs exactly once');
$rowStart = $at === false ? false : strrpos(substr($xml, 0, $at), '<w:tr');
$rowEnd = $at === false ? false : strpos($xml, '</w:tr>', $at) + 7;
$guard($rowStart !== false && $rowEnd !== false, 'the G-17 row is bounded');
$guard($rowStart !== false && substr($xml, (int) $rowStart - 7, 7) === '</w:tr>', 'a sibling row precedes it');
$guard(substr($xml, (int) $rowEnd, 6) === '<w:tr>', 'a sibling row follows it — the clone lands inside the table');

$row = substr($xml, (int) $rowStart, (int) $rowEnd - (int) $rowStart);
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);
$guard(count($cm[0]) === 5, 'the template row has five cells');

$setText = function (string $cell, string $text): string {
    if (! preg_match('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell, $m, PREG_OFFSET_CAPTURE)) {
        throw new RuntimeException('the cell carries no text run to replace');
    }
    $escaped = str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);

    return substr($cell, 0, $m[1][1]).$escaped.substr($cell, $m[1][1] + strlen($m[1][0]));
};

$clones = '';
foreach ($rows as $r) {
    $clone = $row;
    foreach ($cm[0] as $i => $cell) {
        $guard(substr_count($cell, '</w:t>') === 1, "cell {$i} of the template holds exactly one text run");
        $clone = str_replace($cell, $setText($cell, $r[$i]), $clone);
    }
    $guard($clone !== $row, 'the G-18 clone carries new text');
    $guard(substr_count($clone, '<w:tr') === substr_count($row, '<w:tr'), 'the G-18 clone is one row and no more');
    $clones .= $clone;
}

$needle = ['tr' => '</w:tr>', 'tc' => '<w:tc>', 'p' => '</w:p>', 't' => '</w:t>'];
$before = [];
$expect = [];
foreach ($needle as $k => $n) {
    $before[$k] = substr_count($xml, $n);
    $expect[$k] = substr_count($clones, $n);
    $guard($expect[$k] === substr_count($row, $n), "the clone contributes exactly one row's worth of {$k}");
}

$new = substr_replace($xml, $clones, (int) $rowEnd, 0);
foreach ($needle as $k => $n) {
    $after = substr_count($new, $n);
    $guard($after === $before[$k] + $expect[$k], "container {$k}: {$before[$k]} → {$after} (expected +{$expect[$k]})");
}

$tblStart = strrpos(substr($new, 0, (int) $rowStart), '<w:tbl>');
$tblEnd = strpos($new, '</w:tbl>', (int) $rowEnd) + 7;
$tblText = substr($new, (int) $tblStart, (int) $tblEnd - (int) $tblStart);
preg_match_all('/<w:tr.*?<\/w:tr>/s', $tblText, $check);
$dataRows = count($check[0]) - 1;
printf("     §28.3 recounted from the edited bytes: 1 header + %d data rows\n", $dataRows);
$guard($dataRows === 35, 'the table now holds 35 data rows');
preg_match_all('/\bG-(\d+)\b/', $tblText, $gids);
$guard(((int) max($gids[1])) === 18 && count(array_unique($gids[0])) === 18, 'the G block now runs G-1 … G-18');

$sentenceOld = 'lane 47 added G-16, and lane 48 added G-17 on 2026-10-18, so §28.3 holds 34 data rows';
$sentenceNew = 'lane 47 added G-16, lane 48 added G-17, and lane 49 added G-18 the same day, so §28.3 holds '.$dataRows.' data rows';
$guard(substr_count($new, $sentenceOld) === 1, 'the arithmetic sentence is present and unique');
$new = str_replace($sentenceOld, $sentenceNew, $new);
$guard(substr_count($new, 'holds '.$dataRows.' data rows') === 1, 'the paragraph now reads '.$dataRows);

$famAt = strpos($new, 'G / D / U / X / N');
$guard($famAt !== false && substr_count($new, 'G / D / U / X / N') === 1, 'the family row is present and unique');
$famRowStart = $famAt === false ? false : strrpos(substr($new, 0, $famAt), '<w:tr');
$famRowEnd = $famAt === false ? false : strpos($new, '</w:tr>', $famAt) + 7;
$famRow = $famRowStart === false ? '' : substr($new, (int) $famRowStart, (int) $famRowEnd - (int) $famRowStart);
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $famRow, $fc);
$guard(count($fc[0]) === 4, 'the family row has four cells');
$guard($fc[0] !== [] && substr_count($fc[0][1], '>34<') === 1, 'its Rows cell reads exactly 34');
$famNew = str_replace($fc[0][1] ?? '', str_replace('>34<', '>'.$dataRows.'<', $fc[0][1] ?? ''), $famRow);
$guard($famNew !== $famRow, 'the Rows cell was rewritten');
$new = substr_replace($new, $famNew, (int) $famRowStart, strlen($famRow));

$laneOld = 'and a money helper rewritten onto three lines had escaped a scanner that reads a definition only to the end of its line.';
$laneNew = $laneOld.' Lane 49 then turned the same question around: when the number really is 0, what does the desk '
    .'see? Three cells guarded a money or percent figure with its own truthiness, so an account with nothing '
    .'paid printed the same dash as a figure nobody computed — and on this box `remainingBalance` holds 0.00 '
    .'today, so it is not a hypothetical. The rule each fix states is that a guard tests the record or list '
    .'the figure came from, never the amount, and the class is now checked by '
    .'storage/app/scratch/lane49-zero-shown-as-absence.php with its blind spots printed; G-18 records it.';
$guard(substr_count($new, $laneOld) === 1, 'the revision row’s lane-48 sentence is present and unique');
$new = str_replace($laneOld, $laneNew, $new);

libxml_use_internal_errors(true);
$doc = new DOMDocument;
$guard($doc->loadXML($new) === true, 'document.xml parses after the insert and the three rewrites');
libxml_clear_errors();

printf("\none row: %d bytes | counts rewritten to %d\n", strlen($clones), $dataRows);
if ($fails > 0) {
    fwrite(STDERR, "{$fails} guard(s) failed — nothing written\n");
    exit(1);
}
if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-18-pass148.docx');
$z0 = new ZipArchive();
if ($z0->open($src) !== true) {
    fwrite(STDERR, "cannot count parts\n");
    exit(1);
}
$partsBefore = count($z0);
$z0->close();

$z1 = new ZipArchive();
if ($z1->open($src, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "cannot open the docx for updating — restore the backup\n");
    exit(1);
}
$z1->addFromString('word/document.xml', $new);
$z1->close();

$check2 = new ZipArchive();
$check2->open($src);
$onDisk = $check2->getFromName('word/document.xml');
$partsAfter = count($check2);
$check2->close();
if ($onDisk !== $new) {
    fwrite(STDERR, "the file on disk does not hold the edited document.xml — restore the backup\n");
    exit(1);
}
if ($partsAfter !== $partsBefore) {
    fwrite(STDERR, "the archive went from {$partsBefore} parts to {$partsAfter} — restore the backup\n");
    exit(1);
}
printf("WROTE %s — byte-verified, all %d parts present\n", $src, $partsAfter);
