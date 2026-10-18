<?php

/**
 * Pass 144 — §28.3 gains G-16, and the register's two counts move with it.
 *
 * Lane 47 asked what set a summary tile describes. Twelve desk screens carry 46 tiles above a paginated list;
 * four were counting one page under a label that promised the queue, three answered nothing at all from page 2
 * onward, one could never leave 0, and one filter pick-list offered 4 of the 43 values that exist.
 *
 * The row is CLONED from G-15 so it inherits widths, borders, margins and run properties, and the insert and
 * the two count rewrites happen at distinct byte offsets — one offset cannot carry two edits.
 *
 * Both places that state §28.3's size are rewritten from a recount taken out of the edited bytes, because a
 * register row that grows never edits its neighbour: pass 138 moved one sentence and left the family table's
 * own row reading 28 while the truth was 30, and the document contradicted itself for three passes.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$anchor = 'The examination desk’s row action promised one student and delivered the whole result list';

$rows = [[
    'G-16',
    'Summary tiles described a set their labels did not name, and three of them read zero from page 2 onward',
    'Twelve desk screens carry 46 numbers above a paginated list, and the audit asked where each comes from. '
    .'Four sources were in use, unnamed: the whole filtered set, the paginator’s total, the rows on the current '
    .'page, and a predicate the list query cannot return. Fifteen tiles reduced over `props.data` — 20 rows, or '
    .'50 on the audit log — under labels that promised the queue: Blocking’s “Total Capacity / Enrolled / '
    .'Available Slots” beside a “Total Blocks” that read the paginator, so the two disagreed with each other on '
    .'one screen; Clinic’s “Assessments Completed / Pending”; Exam’s “Passed / Failed”; the audit log’s “Created '
    .'/ Updated / Deleted”; Evaluation’s “Pending / Evaluated / Transferees”; and Exam Results’ “Pass Rate”.'
    .' Three handlers were worse than a page: AdmissionController, AssessmentController and '
    .'ClearanceController each take their “full filtered set” aggregate from `(clone $query)` — but after '
    .'`$query->paginate()`, and Laravel’s paginate() calls forPage(), which sets limit and offset on the builder '
    .'it is called on (vendor/laravel/framework/src/Illuminate/Database/Query/Builder.php:3208). The clone '
    .'therefore carries “LIMIT 20 OFFSET 20”, so on page 2 the grouped counts return no rows and every tile '
    .'reads 0 — the peso aggregate returns no row at all, which `?? 0` turned into ₱0.00. Measured against live '
    .'ems by storage/app/scratch/lane47-probe-offset-inheritance.php: cloned before paginate the clearance '
    .'statuses are {approved:3, pending:1} and the assessed total is 246000.00, cloned after they are empty and '
    .'NULL on page 2 and page 3, and identical on page 1 — which is the whole reason this survived the '
    .'2026-09-25 remediation that introduced the sentence “the summary tiles must describe the full queue, not '
    .'just the current page”, and every demo, which opens on page 1. AccountingController has always cloned '
    .'first, and is the shape the three now follow. The Evaluation desk held the fourth kind: its “Evaluated” '
    .'tile compared `enrollmentStatus` to ‘evaluated’ across the rows the handler returns, while '
    .'EvaluationController::index() restricts that list to pending + returnedToEvaluation — the tile could '
    .'never leave 0 on a box where 21 records have already moved past this desk for an unbounded officer. The '
    .'audit log’s Entity dropdown was assembled from the rows on the page, so it offered 4 entity tables while '
    .'auditlogs holds 43, and 39 kinds of activity could not be filtered for at all. The pass rate divided by '
    .'every row on the page including unscored ones, though examResult is `enum(\'pass\',\'fail\')` and '
    .'nullable — a screen of unmarked papers read 0%. Closed 2026-10-17 by ruling, four questions answered: '
    .'every tile counts the whole filtered set on the server, cloned before the page LIMIT; “Evaluated” became '
    .'“Evaluated by This Desk”, counting records past pending inside the same CollegeScope as the list, with a '
    .'fifth tile for what the Registrar sent back; the rate is passers ÷ scored rows, over the set, and shows a '
    .'dash rather than 0 while nothing is scored; and the handler sends the distinct entity tables the log '
    .'holds. Re-measured through the handlers on live ems after the change: the audit desk reads 3,423 rows in '
    .'the log with 50 on the page and tiles of 3,199 created / 223 updated / 1 deleted — a page-derived count '
    .'could not have passed 50 — with 43 entity choices offered where 4 were, and the Evaluation queue answers '
    .'7 in the set with 6 pending, 1 returned and 21 already handled. Where a prop is absent the tile now '
    .'paints a dash, because a plausible zero for a number the screen does not have is the same lie in a '
    .'smaller font. Pinned by tests/Feature/DeskSummaryScopeTest.php — seven cases, 173 assertions, which reach '
    .'page 2 by writing 25 to 60 rows, because no desk queue on this box is long enough to show the defect by '
    .'itself — and every case was proved by putting the old shape back and watching it go red '
    .'(storage/app/scratch/mutate-lane47-tests.php, eight cases). The class is machine-checked by '
    .'storage/app/scratch/lane47-tile-scope.php, which reports SILENT-ZERO, PAGE-TILE, MIXED-SET, IMPOSSIBLE '
    .'and DEAD-OPTION, refuses on any tile it cannot trace, and is itself proved by eight mutants including the '
    .'two that show its scope flag narrows the scan and an empty scan refuses.',
    'Closed by the owner’s ruling 2026-10-17 — the tiles count the filtered set, and a number the screen does '
    .'not have is shown as a dash',
    'app/Http/Controllers/Evaluation/EvaluationController.php:103-121; '
    .'app/Http/Controllers/Blocking/BlockingController.php:68-79; '
    .'app/Http/Controllers/Admin/UserManagementController.php:508-527; tests/Feature/DeskSummaryScopeTest.php',
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
$guard($at !== false && substr_count($xml, $anchor) === 1, 'the G-15 anchor occurs exactly once');
$rowStart = $at === false ? false : strrpos(substr($xml, 0, $at), '<w:tr');
$rowEnd = $at === false ? false : strpos($xml, '</w:tr>', $at) + 7;
$guard($rowStart !== false && $rowEnd !== false, 'the G-15 row is bounded');
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
    $guard($clone !== $row, 'the G-16 clone carries new text');
    $guard(substr_count($clone, '<w:tr') === substr_count($row, '<w:tr'), 'the G-16 clone is one row and no more');
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

// Recount §28.3 from the bytes just produced, then write that number wherever it is stated.
$tblStart = strrpos(substr($new, 0, (int) $rowStart), '<w:tbl>');
$tblEnd = strpos($new, '</w:tbl>', (int) $rowEnd) + 7;
$tblText = substr($new, (int) $tblStart, (int) $tblEnd - (int) $tblStart);
preg_match_all('/<w:tr.*?<\/w:tr>/s', $tblText, $check);
$dataRows = count($check[0]) - 1;
printf("     §28.3 recounted from the edited bytes: 1 header + %d data rows\n", $dataRows);
$guard($dataRows === 33, 'the table now holds 33 data rows');
preg_match_all('/\bG-(\d+)\b/', $tblText, $gids);
$guard(((int) max($gids[1])) === 16 && count(array_unique($gids[0])) === 16, 'the G block now runs G-1 … G-16');

$sentenceOld = 'G-10 was followed the same day by G-11, lane 42 added G-12 and G-13, and lane 46 added G-14 and '
    .'G-15 on 2026-10-17, so §28.3 holds 32 data rows — and the sentence that reported 27 was stale within one '
    .'working pass, while the family table’s own row still read 28 two sections later because a row that grows '
    .'never edits its neighbour: the count is re-read from the rendered rows here, and both places are made to '
    .'say the same thing.';
$sentenceNew = 'G-10 was followed the same day by G-11, lane 42 added G-12 and G-13, lane 46 added G-14 and '
    .'G-15, and lane 47 added G-16 on 2026-10-17, so §28.3 holds '.$dataRows.' data rows — and the sentence '
    .'that reported 27 was stale within one working pass, while the family table’s own row still read 28 two '
    .'sections later because a row that grows never edits its neighbour: the count is re-read from the rendered '
    .'rows here, and both places are made to say the same thing.';
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
$guard($fc[0] !== [] && substr_count($fc[0][1], '>32<') === 1, 'its Rows cell reads exactly 32');
$famNew = str_replace($fc[0][1] ?? '', str_replace('>32<', '>'.$dataRows.'<', $fc[0][1] ?? ''), $famRow);
$guard($famNew !== $famRow, 'the Rows cell was rewritten');
$new = substr_replace($new, $famNew, (int) $famRowStart, strlen($famRow));

// The revision row for this issue carries the suite's measured size; it moves with every batch that adds tests.
$countsOld = 'The suite stands at 736 tests and 4629 assertions';
$countsNew = 'The suite stands at 743 tests and 4814 assertions';
$guard(substr_count($new, $countsOld) === 1, 'the suite-count sentence is present and unique');
$new = str_replace($countsOld, $countsNew, $new);

$laneOld = '§28.3 gained G-14 and G-15, and recounting the table exposed that §29.4’s family row had been left '
    .'reading 28 while the sentence beside it said 30 — the document contradicting itself for three passes, '
    .'because a row that grows never edits its neighbour.';
$laneNew = '§28.3 gained G-14 and G-15, and recounting the table exposed that §29.4’s family row had been left '
    .'reading 28 while the sentence beside it said 30 — the document contradicting itself for three passes, '
    .'because a row that grows never edits its neighbour. Lane 47 then asked which set a summary tile '
    .'describes: twelve desk screens answer 46 tiles, and fifteen of them counted the 20 rows on the page under '
    .'labels that promised the queue while three handlers took their “full filtered set” aggregate from a '
    .'builder already paginated, so page 2 read zero for a queue of twenty-five — a defect no demo can reach, '
    .'because a demo opens on page 1. One tile could never leave 0 by construction, one filter dropdown offered '
    .'4 of the 43 entity tables the audit log holds, and a pass rate divided by rows that had not been graded. '
    .'All four questions were ruled the same day, the tiles now answer for the whole filtered set, G-16 records '
    .'it, and the class is machine-checked by storage/app/scratch/lane47-tile-scope.php with eight mutants.';
$guard(substr_count($new, $laneOld) === 1, 'the revision-row lane-46 sentence is present and unique');
$new = str_replace($laneOld, $laneNew, $new);

libxml_use_internal_errors(true);
$doc = new DOMDocument;
$guard($doc->loadXML($new) === true, 'document.xml parses after the insert and the three rewrites');
libxml_clear_errors();

printf("\none row: %d bytes | counts rewritten to %d | suite %s\n", strlen($clones), $dataRows, $countsNew);
if ($fails > 0) {
    fwrite(STDERR, "{$fails} guard(s) failed — nothing written\n");
    exit(1);
}
if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-17-pass144.docx');
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
