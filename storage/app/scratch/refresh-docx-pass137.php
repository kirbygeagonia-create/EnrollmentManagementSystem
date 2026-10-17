<?php

/**
 * Pass 137 — §28.3 gains G-11: the 2026-10-15 retirement took two students' completed prior year with it,
 * and nothing in the build can bring it back.
 *
 * Owner ruling 2026-10-16: record it as an open finding rather than move data or widen the generator.
 *
 * Measured, all of it on live ems the same day. storage/app/scratch/lane40-walkthrough-claims.php resolved
 * every record id the desk-facing walkthrough names and found seven citing rows that have been `dropped`
 * since the retirement. Five were re-seated under new ids by the year-level pass — 654→1063, 655→1064,
 * 656→1065, 657→1066, 658→1067, each term 2, year 1, enrolled, regular. Two were not: Maria Reyes' 595 and
 * Rico Navarro's 597, term 10, year 1, dropped, with no replacement, because their live records 596 and 598
 * sit at year 1 and the pass only looks at year 2 and above (tools/seed_full_demo_pipeline.php:1701-1704).
 *
 * Cloned from G-10 by the same byte surgery as pass 136: five cells, one text run each, and the sibling-row
 * guard proving the clone lands between two rows of the table rather than inside a cell.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$anchor = 'A validated ID request cannot be reached from the desk that validated it';

$id = 'G-11';
$gap = 'Two students the acceptance script walks as returning no longer hold a completed prior year';
$body = 'Maria Reyes (DEMO-2026-002) and Rico Navarro (DEMO-2026-007) were the approvable returning record and '
    .'the live Registrar block because each also held a completed year — enrollments 595 and 597, term 10. '
    .'Both rows were retired through the Registrar’s drop on 2026-10-15 with the seven past-term records whose '
    .'Clinic and ID boxes the demo pipeline had signed before it wrote the acts behind them (§1.1’s revision '
    .'row for 2026-10-11–15). Five of those seven came back on the next run: the year-level pass seats a '
    .'completed year beneath any record claiming year 2 or above (tools/seed_full_demo_pipeline.php:1701-1704), '
    .'and it re-seated 1063, 1064, 1065, 1066 and 1067. These two never reach that floor, because their live '
    .'records 596 and 598 sit at year 1. So everything the desk opens is real — slip 27 approved and receipted, '
    .'slip 42 pending with no receipt, both records pending on the demo term — while the evidence that made the '
    .'student returning is gone, and the derived year level reads 1, which is what the record now supports. Open '
    .'and not a code defect: closing it is either a demo-data decision or a deliberate widening of the '
    .'generator’s year floor, and this repository’s own rule says a data decision a tool regenerates is not a '
    .'decision, so the second route would have to be the durable one. Found 2026-10-16 by the checker written '
    .'to re-read the walkthrough’s own record ids — which also surfaced five superseded ids in that table and '
    .'the coverage hole in the checker that first hid two of them.';
$actor = 'Owner — a demo-data decision, or the generator’s year floor, which the regeneration rule says must be the durable route';
$raised = '§1.1’s revision row for 2026-10-11–15; found on live ems 2026-10-16';

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
$guard($at !== false && substr_count($xml, $anchor) === 1, 'the anchor occurs exactly once');

$rowStart = $at === false ? false : strrpos(substr($xml, 0, $at), '<w:tr');
$rowEnd = $at === false ? false : strpos($xml, '</w:tr>', $at) + 7;
$guard($rowStart !== false && $rowEnd !== false, 'the row containing it is bounded');
$guard($rowStart !== false && substr($xml, (int) $rowStart - 7, 7) === '</w:tr>', 'preceded by a sibling row’s close');
$guard($rowEnd !== false && substr($xml, (int) $rowEnd, 6) === '<w:tr>', 'followed by a sibling row’s open — the clone lands as a child of the table');

$row = $rowStart === false ? '' : substr($xml, (int) $rowStart, (int) $rowEnd - (int) $rowStart);
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);
$guard(count($cm[0]) === 5, 'the row has exactly five cells');

$setText = function (string $cell, string $text): string {
    if (! preg_match('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell, $m, PREG_OFFSET_CAPTURE)) {
        throw new RuntimeException('the cell carries no text run to replace');
    }
    $escaped = str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);
    $start = $m[1][1];

    return substr($cell, 0, $start).$escaped.substr($cell, $start + strlen($m[1][0]));
};

$replacements = [$id, $gap, $body, $actor, $raised];
$cells = [];
foreach ($cm[0] as $i => $cell) {
    $guard(substr_count($cell, '</w:t>') === 1, "cell {$i} holds exactly one text run");
    $cells[] = $setText($cell, $replacements[$i]);
}

$clone = $row;
foreach ($cm[0] as $i => $cell) {
    $clone = str_replace($cell, $cells[$i], $clone);
}
$guard($clone !== $row, 'the clone carries new text');
$guard(substr_count($clone, '<w:tr') === substr_count($row, '<w:tr'), 'the clone is one row and no more');
$guard(str_contains($clone, $id), 'the clone carries its own id');

$needle = ['tr' => '</w:tr>', 'tc' => '<w:tc>', 'p' => '</w:p>', 't' => '</w:t>'];
$expect = [];
foreach ($needle as $k => $n) {
    $expect[$k] = substr_count($clone, $n);
    $guard($expect[$k] > 0, "the clone contributes at least one {$k}");
}
$before = [];
foreach ($needle as $k => $n) {
    $before[$k] = substr_count($xml, $n);
}

$new = substr_replace($xml, $clone, (int) $rowEnd, 0);

foreach ($needle as $k => $n) {
    $after = substr_count($new, $n);
    $guard($after === $before[$k] + $expect[$k], "container {$k}: {$before[$k]} → {$after} (expected +{$expect[$k]})");
}

$doc = new DOMDocument();
$guard($doc->loadXML($new) === true, 'document.xml parses');

printf("\nnew row: %d bytes | body %d chars\n", strlen($clone), strlen($body));

if ($fails > 0) {
    fwrite(STDERR, "{$fails} guard(s) failed — nothing written\n");
    exit(1);
}
if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-16-pass137.docx');

$z0 = new ZipArchive();
if ($z0->open($src) !== true) {
    fwrite(STDERR, "cannot open the docx to count its parts — nothing written\n");
    exit(1);
}
$partsBefore = count($z0);
$z0->close();

$zip = new ZipArchive();
if ($zip->open($src, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "cannot open the docx for updating — restore the backup\n");
    exit(1);
}
$zip->addFromString('word/document.xml', $new);
$zip->close();

$check = new ZipArchive();
$check->open($src);
$onDisk = $check->getFromName('word/document.xml');
$partsAfter = count($check);
$check->close();
if ($onDisk !== $new) {
    fwrite(STDERR, "the file on disk does not hold the edited document.xml — restore the backup\n");
    exit(1);
}
if ($partsAfter !== $partsBefore) {
    fwrite(STDERR, "the archive went from {$partsBefore} parts to {$partsAfter} — parts were lost, restore the backup\n");
    exit(1);
}

printf("WROTE %s — document.xml byte-verified, all %d parts still present\n", $src, $partsAfter);
exit(0);
