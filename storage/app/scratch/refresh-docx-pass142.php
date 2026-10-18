<?php

/**
 * Pass 142 — §28.3 gains G-14 and G-15, and the register's two counts are made to agree with each other.
 *
 * The two findings are lane 46's: a status filter the directory's own handler never read, offering two values
 * the column cannot hold; and an examination row link that promised one student and delivered the whole list.
 * Both were ruled and fixed on 2026-10-17, and both are pinned by tests whose mutants were run.
 *
 * The rows are CLONED from G-13 so they inherit widths, borders, margins and run properties, and both go in as
 * ONE insert at ONE byte offset — pass 74's lesson, restated in lane 44's: an insert and a rewrite at the same
 * offset corrupt the file.
 *
 * Then the arithmetic. §29.x carries the count twice: the family table's row read 28 while the paragraph beside
 * it read 30, and the table itself held 30 — two sentences in one section disagreeing by two because the row
 * that grew never moved its neighbour. Both are rewritten from a recount taken out of the edited bytes, not
 * from the arithmetic of this script, and the recount is the guard.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$anchor = 'Strict-Transport-Security is never sent when TLS ends at a proxy';

$rows = [
    [
        'G-14',
        'The student directory’s Status filter narrowed nothing, and two of its four options could not exist',
        'Students/Index.jsx put `search` and `status` into the query and repopulated both controls from `filters`, '
        .'but StudentController::index consulted only `search` and echoed only `search`: picking Active, Inactive, '
        .'Graduated or Dropped listed every student and then reset the dropdown on the next render, so the desk '
        .'could not even see that its choice had done nothing. Measured before being called a defect: '
        .'students.status is `enum(\x27active\x27,\x27inactive\x27)` in information_schema on live `ems` and in '
        .'2026_08_02_1000000005_create_students_table.php, and the live directory holds 24 students, all of them '
        .'active — so graduated and dropped were never options, only words in a JSX array that no column, cast or '
        .'write path in app/, database/ or tools/ can produce. Closed 2026-10-17 by ruling: the handler now reads '
        .'the key, accepts only the two values the vocabulary declares, and echoes the status it actually applied '
        .'rather than the one merely typed, and the dropdown lists only those two. App\\Enums\\StudentStatus names '
        .'the vocabulary the model casts with — it had borrowed StaffStatus, whose two strings happened to match, '
        .'so a person’s standing and an account’s standing were one type by coincidence. Pinned by '
        .'tests/Feature/StudentDirectoryFilterTest.php, whose first case goes red when the filter branch is '
        .'deleted, and watched by storage/app/scratch/lane46-filter-contract.php.',
        'Closed by the owner’s ruling 2026-10-17 — honour the two values the column holds',
        'app/Http/Controllers/StudentController.php:32-34 and :49; tests/Feature/StudentDirectoryFilterTest.php',
    ],
    [
        'G-15',
        'The examination desk’s row action promised one student and delivered the whole result list',
        'Every row of Exam/Index.jsx links to exam.results with `?search=` the student’s School ID, while '
        .'ExamController::results branched on `result` and on a `stage` key no screen sends any more — the stage '
        .'filter left with the item-4 ownership split — so the click opened every result under a URL advertising '
        .'one student, and Results.jsx read back `filters.result` only, so the search box could not even show the '
        .'value that had been sent. Measured before being called a defect: examresults.examStage holds entrance (2 '
        .'rows) and retention (3) on live `ems`; no page, guide or script in the repository sends `stage` to '
        .'exam.index or exam.results, though the candidate list at /exam/students still takes it and keeps it. '
        .'Closed 2026-10-17 by ruling: results() honours `search` exactly as index() does and echoes both keys, '
        .'the page gained the search box that reads it back, and the two dead `stage` branches were deleted '
        .'rather than left as decoration. Pinned by two cases in tests/Feature/Exam/ExamControllerTest.php, each '
        .'of which was proved by removing one branch and watching the case go red — the search branch alone is not '
        .'enough, because a result filter on its own can still look narrow.',
        'Closed by the owner’s ruling 2026-10-17 — make the narrowing real, and say which filters were applied',
        'app/Http/Controllers/Exam/ExamController.php:410-413 and :441; §14.1',
    ],
];

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
$guard($at !== false && substr_count($xml, $anchor) === 1, 'the G-13 anchor occurs exactly once');
$rowStart = $at === false ? false : strrpos(substr($xml, 0, $at), '<w:tr');
$rowEnd = $at === false ? false : strpos($xml, '</w:tr>', $at) + 7;
$guard($rowStart !== false && $rowEnd !== false, 'the G-13 row is bounded');
$guard($rowStart !== false && substr($xml, (int) $rowStart - 7, 7) === '</w:tr>', 'a sibling row precedes it');
$next = substr($xml, (int) $rowEnd, 6);
$guard($next === '<w:tr>', 'a sibling row follows it — the clone lands inside the table');

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
    $guard($clone !== $row, "the {$r[0]} clone carries new text");
    $guard(substr_count($clone, '<w:tr') === substr_count($row, '<w:tr'), "the {$r[0]} clone is one row and no more");
    $clones .= $clone;
}

$needle = ['tr' => '</w:tr>', 'tc' => '<w:tc>', 'p' => '</w:p>', 't' => '</w:t>'];
$before = [];
$expect = [];
foreach ($needle as $k => $n) {
    $before[$k] = substr_count($xml, $n);
    $expect[$k] = substr_count($clones, $n);
    $guard($expect[$k] === 2 * substr_count($row, $n), "the two clones contribute exactly twice the template's {$k}");
}

$new = substr_replace($xml, $clones, (int) $rowEnd, 0);
foreach ($needle as $k => $n) {
    $after = substr_count($new, $n);
    $guard($after === $before[$k] + $expect[$k], "container {$k}: {$before[$k]} → {$after} (expected +{$expect[$k]})");
}

// Recount §28.3 from the bytes that were just produced, then write that number into both places that state it.
$tblStart = strrpos(substr($new, 0, (int) $rowStart), '<w:tbl>');
$tblEnd = strpos($new, '</w:tbl>', (int) $rowEnd) + 7;
$tblText = substr($new, (int) $tblStart, (int) $tblEnd - (int) $tblStart);
preg_match_all('/<w:tr.*?<\/w:tr>/s', $tblText, $check);
$dataRows = count($check[0]) - 1;
$guard($dataRows === 32, "the table now holds 1 header + {$dataRows} data rows, recounted from the bytes");
preg_match_all('/\bG-(\d+)\b/', $tblText, $gids);
$guard(((int) max($gids[1])) === 15 && count(array_unique($gids[0])) === 15, 'the G block now runs G-1 … G-15');

$sentenceOld = 'G-10 was followed the same day by G-11, and lane 42 added G-12 and G-13 on 2026-10-17, '
    .'so §28.3 holds 30 data rows — and the sentence that reported 27 was stale within one working pass, which is '
    .'the honest reason the count has to be re-read from the table rather than inherited from the last edit.';
$sentenceNew = 'G-10 was followed the same day by G-11, lane 42 added G-12 and G-13, and lane 46 added G-14 and '
    .'G-15 on 2026-10-17, so §28.3 holds '.$dataRows.' data rows — and the sentence that reported 27 was stale '
    .'within one working pass, while the family table’s own row still read 28 two sections later because a row '
    .'that grows never edits its neighbour: the count is re-read from the rendered rows here, and both places are '
    .'made to say the same thing.';
$guard(substr_count($new, $sentenceOld) === 1, 'the arithmetic sentence is present and unique');
$new = str_replace($sentenceOld, $sentenceNew, $new);
$guard(substr_count($new, 'holds '.$dataRows.' data rows') === 1, 'the paragraph now reads '.$dataRows);

// The family table's own cell, found by the row that owns it — the value 28 is not unique in the document.
$famAt = strpos($new, 'G / D / U / X / N');
$guard($famAt !== false && substr_count($new, 'G / D / U / X / N') === 1, 'the family row is present and unique');
$famRowStart = $famAt === false ? false : strrpos(substr($new, 0, $famAt), '<w:tr');
$famRowEnd = $famAt === false ? false : strpos($new, '</w:tr>', $famAt) + 7;
$famRow = $famRowStart === false ? '' : substr($new, (int) $famRowStart, (int) $famRowEnd - (int) $famRowStart);
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $famRow, $fc);
$guard(count($fc[0]) === 4, 'the family row has four cells');
$guard($fc[0] !== [] && substr_count($fc[0][1], '>28<') === 1, 'its Rows cell reads exactly 28');
$famNew = str_replace($fc[0][1] ?? '', str_replace('>28<', '>'.$dataRows.'<', $fc[0][1] ?? ''), $famRow);
$guard($famNew !== $famRow, 'the Rows cell was rewritten');
$new = substr_replace($new, $famNew, (int) $famRowStart, strlen($famRow));
$guard(substr_count($new, '>'.$dataRows.'<') >= 1 && substr_count($new, '>28< |') === 0, 'no family row still reads 28');

libxml_use_internal_errors(true);
$doc = new DOMDocument;
$guard($doc->loadXML($new) === true, 'document.xml parses after the insert and both rewrites');
libxml_clear_errors();

printf("\ntwo rows: %d bytes | both counts rewritten to %d\n", strlen($clones), $dataRows);
if ($fails > 0) {
    fwrite(STDERR, "{$fails} guard(s) failed — nothing written\n");
    exit(1);
}
if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-17-pass142.docx');
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
