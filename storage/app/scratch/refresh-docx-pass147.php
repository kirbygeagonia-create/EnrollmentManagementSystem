<?php

/**
 * Pass 147 — §28.3 gains G-17, and everything that counts the register moves with it.
 *
 * Lane 48 asked what a desk tile paints when its number is not there. The answer was a zero: 43 tiles coerced
 * absence with `?? 0` / `|| 0`, six fell back to a count of the rows on screen, six pages carried a private
 * `peso()` reading `Number(n || 0)` — which turns a null amount into ₱0.00 before any default is consulted —
 * and the shared tile had no absence state at all.
 *
 * The row is CLONED from G-16 so it inherits widths, borders, margins and run properties. The insert, the two
 * register counts, the suite's measured size, three citations this batch invalidated and one stale claim about
 * lane 47's own mutant count are all applied at distinct offsets — one offset cannot carry two edits.
 *
 * The citations were re-measured by content, not by arithmetic. Two of them (`Registrar/Index.jsx:133-134 ,
 * :92` and `Accounting/Index.jsx:9-13`) moved because this batch inserted comment lines above them; the third
 * (`Clearance/Index.jsx:528`) was already thirteen lines adrift before this batch began — the phrase it names
 * sat at 515 in HEAD — and lane 48's deletion of a nineteen-line memo moved it again, to 501. A line sweep
 * cannot see that kind of drift, because the number it checks still lands on a live line.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$anchor = 'Summary tiles described a set their labels did not name, and three of them read zero from page 2 onward';

$rows = [[
    'G-17',
    'A tile with no number invented one: 43 zeros, six screen-counts, and six money helpers that read null as ₱0.00',
    'Lane 47 settled which set a tile describes; this asks what the screen shows when that number never arrives — '
    .'a handler that stopped answering a key, a record whose field is absent, a request that failed. Measured '
    .'across the tree: 24 screens answer 101 tiles, and 43 of those values coerced absence to `0` with `?? 0` or '
    .'`|| 0`, six supplied a number the screen had counted itself (`stats = serverStats || computedStats`, '
    .'`enrollments?.total ?? enrollments?.data?.length`, `stats.blocks ?? blocks?.total`), and six pages carried a '
    .'private `peso()` built on `Number(n || 0)` — a wrapper that turns a null amount into ₱0.00 before any '
    .'call-site default is even reached, which is why fixing the tiles alone would have left the money tiles '
    .'lying. None of it was reachable by eye: a zero and an unknown render identically, and on a cashier’s '
    .'screen they send different people to different offices. Closed 2026-10-18. The shared tile now owns the '
    .'absence state — `Components/ui/StatCard.jsx:14-18` renders an em dash for null, undefined, the empty string '
    .'and NaN, with a `title` that says why — and every numeric default was taken out of the tile expressions, so '
    .'a zero that appears was answered by the server: `sum()` coalesced in PHP stays `0`, and '
    .'`tests/Feature/DeskTileNumberSourceTest.php` (four cases, 290 assertions) requires every desk’s figures to '
    .'arrive typed and equal to 0 on an empty database, which is what makes the dash mean *absence* rather than '
    .'an empty queue. One figure is deliberately left null, because it cannot be computed from nothing: an exam '
    .'pass rate with no paper marked is unknown, not 0%. The three screen-composed fallbacks and two record '
    .'pages’ `Number(column || 0)` locals were read against the schema first — `totalAssessedAmount`, '
    .'`totalScholarshipCoverage`, `totalWaived` and `remainingBalance` are all NOT NULL on live `ems`, so the '
    .'coercion was dead code whose only living effect was to hide a broken payload. A prop default on the same '
    .'page (`outstandingBalance = 0`) turned out to gate a card rather than a tile, and `awaitingSignOff` now '
    .'refuses to decide on an unknown balance instead of reading it as settled. The class is machine-checked by '
    .'storage/app/scratch/lane48-tile-fallback.php — INVENTED-ZERO, UNSAFE-FORMATTER, FALLBACK-DERIVED, DEAD-KEY, '
    .'OBJECT-TILE, BLANK, and UNBOUND which refuses the run — proved by ten cases (a clean baseline, six planted '
    .'defects, the two showing that its scope flag narrows the scan and that an empty scan refuses, and one '
    .'proving an anchor that cannot match is refused), and the tests by seven (storage/app/scratch/'
    .'mutate-lane48-tests.php). Two harness defects surfaced while proving them, and both would have produced a '
    .'silent pass: the restore was guarded so that it ran only once, leaving every case after the first to '
    .'measure a tree still carrying the previous mutant — which is what the clean-baseline check caught — and a '
    .'`peso()` rewritten onto three lines escaped a local scanner that read a definition only to the end of its '
    .'line, so the six formatters looked safe because they were unread.',
    'Closed 2026-10-18 — a tile shows the handler’s number, or it shows a dash',
    'resources/js/Components/ui/StatCard.jsx:14-18; resources/js/Pages/Clearance/Index.jsx:58-59; '
    .'resources/js/Pages/Registrar/Index.jsx:82-85; resources/js/Pages/Accounting/DailyReport.jsx:61-66; '
    .'tests/Feature/DeskTileNumberSourceTest.php',
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
$guard($at !== false && substr_count($xml, $anchor) === 1, 'the G-16 anchor occurs exactly once');
$rowStart = $at === false ? false : strrpos(substr($xml, 0, $at), '<w:tr');
$rowEnd = $at === false ? false : strpos($xml, '</w:tr>', $at) + 7;
$guard($rowStart !== false && $rowEnd !== false, 'the G-16 row is bounded');
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
    $guard($clone !== $row, 'the G-17 clone carries new text');
    $guard(substr_count($clone, '<w:tr') === substr_count($row, '<w:tr'), 'the G-17 clone is one row and no more');
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
$guard($dataRows === 34, 'the table now holds 34 data rows');
preg_match_all('/\bG-(\d+)\b/', $tblText, $gids);
$guard(((int) max($gids[1])) === 17 && count(array_unique($gids[0])) === 17, 'the G block now runs G-1 … G-17');

$sentenceOld = 'G-10 was followed the same day by G-11, lane 42 added G-12 and G-13, lane 46 added G-14 and '
    .'G-15, and lane 47 added G-16 on 2026-10-17, so §28.3 holds 33 data rows';
$sentenceNew = 'G-10 was followed the same day by G-11, lane 42 added G-12 and G-13, lane 46 added G-14 and '
    .'G-15, lane 47 added G-16, and lane 48 added G-17 on 2026-10-18, so §28.3 holds '.$dataRows.' data rows';
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
$guard($fc[0] !== [] && substr_count($fc[0][1], '>33<') === 1, 'its Rows cell reads exactly 33');
$famNew = str_replace($fc[0][1] ?? '', str_replace('>33<', '>'.$dataRows.'<', $fc[0][1] ?? ''), $famRow);
$guard($famNew !== $famRow, 'the Rows cell was rewritten');
$new = substr_replace($new, $famNew, (int) $famRowStart, strlen($famRow));

// The revision row for this issue carries the suite's measured size: 747 tests / 5,092 assertions is what the
// whole-suite run printed after the last source change, not arithmetic.
$countsOld = 'The suite stands at 743 tests and 4802 assertions';
$countsNew = 'The suite stands at 747 tests and 5092 assertions';
$guard(substr_count($new, $countsOld) === 1, 'the suite-count sentence is present and unique');
$new = str_replace($countsOld, $countsNew, $new);

// The lane-47 harness count in the narrative was stale: it has nine cases, six of which plant a defect.
$mutOld = 'the class is machine-checked by storage/app/scratch/lane47-tile-scope.php with eight mutants.';
$mutNew = 'the class is machine-checked by storage/app/scratch/lane47-tile-scope.php with nine cases, six of them '
    .'planted defects.';
$guard(substr_count($new, $mutOld) === 1, 'the lane-47 mutant claim is present and unique');
$new = str_replace($mutOld, $mutNew, $new);

$laneOld = 'and one proving an anchor that cannot match is refused.';
$laneNew = ' Lane 48 then asked what a tile paints when its number is not there: 24 screens answer 101 tiles, 43 '
    .'of them coerced an absent figure to 0, six fell back to a count of the rows on screen, six pages read a '
    .'null amount as ₱0.00 through their own money helper, and the shared tile had no absence state. The zero now '
    .'comes from the handler or the tile dashes; four cases in '
    .'tests/Feature/DeskTileNumberSourceTest.php require every desk figure to arrive typed and equal to 0 on an '
    .'empty database, and the one figure that cannot be computed from nothing — a pass rate with no paper marked '
    .'— is pinned null. G-17 records it, checked by storage/app/scratch/lane48-tile-fallback.php and proved by '
    .'ten cases on the checker and seven on the tests, two of which were harness defects that would have printed '
    .'a silent pass: a restore guarded to run only once left the next case measuring a tree that still carried '
    .'the previous mutant, and a money helper rewritten onto three lines had escaped a scanner that reads a '
    .'definition only to the end of its line.';
$guard(substr_count($new, $laneOld) === 0, 'the lane-48 sentence is not already present');
$guard(substr_count($new, 'and seven on the tests,') === 0, 'nothing from this sentence is already in the row');

// The lane-48 story is appended where the revision row's own last sentence begins.
$sweepOld = 'The document itself was swept by line, by meaning and by §-reference after every pass in this batch.';
$sweepNew = $laneNew.' '.$sweepOld;
$guard(substr_count($new, $sweepOld) === 1, 'the revision row’s closing sweep sentence is present and unique');
$new = str_replace($sweepOld, $sweepNew, $new);

// Three citations this batch invalidated, each re-pointed from the content the sentence names.
$cites = [
    ['Registrar/Index.jsx:133-134 , :92', 'Registrar/Index.jsx:134-135 , :93', 1],
    ['resources/js/Pages/Accounting/Index.jsx:9-13', 'resources/js/Pages/Accounting/Index.jsx:13-17', 1],
    ['Clearance/Index.jsx:528', 'Clearance/Index.jsx:501', 1],
];
foreach ($cites as [$old, $now, $want]) {
    $hits = substr_count($new, $old);
    $guard($hits === $want, "the citation [{$old}] occurs {$hits} time(s), expected {$want}");
    if ($hits === $want) {
        $new = str_replace($old, $now, $new);
        $guard(substr_count($new, $now) === $want, "it now reads [{$now}]");
    }
}

libxml_use_internal_errors(true);
$doc = new DOMDocument;
$guard($doc->loadXML($new) === true, 'document.xml parses after the insert and the seven rewrites');
libxml_clear_errors();

printf("\none row: %d bytes | counts rewritten to %d | %s\n", strlen($clones), $dataRows, $countsNew);
if ($fails > 0) {
    fwrite(STDERR, "{$fails} guard(s) failed — nothing written\n");
    exit(1);
}
if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-18-pass147.docx');
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
