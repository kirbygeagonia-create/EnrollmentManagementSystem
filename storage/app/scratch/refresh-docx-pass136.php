<?php

/**
 * Pass 136 — §28.3 gains G-10: a validated ID request cannot be reached from the desk that validated it.
 *
 * Found while doing exactly what lane 39 was asked to do — rewrite the demo-day ID step. The old step told the
 * presenter to open Juan's validated ID on the Student ID Hub. Reading IDController::index() said the hub is a
 * queue of OPEN boxes: it selects enrollments whose earliest pending workflow step is the ID office's, and
 * ID/Index.jsx builds its only link to the request screen from that list (the single `route('id.show'` call in
 * resources/js). Validating signs the box, so the act removes the record from the one screen that leads to it.
 * That makes the old step un-walkable for Juan for a second, independent reason — his box is signed — and it is
 * the same for Kevin Santos, the dataset's only validated request carrying a face photo.
 *
 * Measured on live ems 2026-10-16 by reproducing the controller's predicate in SQL: the hub holds exactly two
 * enrollments today, 91 (DEMO-2026-016) and 876 (DEMO-2026-026), each with one pending request; no validated
 * request appears in the queue at all.
 *
 * The row is CLONED from G-9 rather than written from scratch, so it inherits column widths, borders, margins
 * and run properties. The engine cannot insert a row: its `inserts` kind resolves a paragraph and lands after
 * `</w:p>`, and a `<w:tr>` written there sits inside a cell where every container count still balances because
 * each element opens and closes itself — Word calls that file corrupt anyway. The guards that matter are stated
 * below and every one must hold before a byte is written; the container deltas are read off the cloned row
 * itself rather than assumed, so a cell or paragraph added to the template cannot slip past a hardcoded +5.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$anchor = 'The demo term offered no curriculum subject at the level and semester most active enrollments sat';

$id = 'G-10';
$gap = 'A validated ID request cannot be reached from the desk that validated it';
$body = 'IDController::index() builds the hub’s queue from enrollments whose earliest pending workflow step is '
    .'the ID office’s, and ID/Index.jsx creates its only link to the request screen from that list — so the act '
    .'of validating, which signs the box, removes the record from the one screen that leads to it. Nothing is '
    .'lost from the database: the request row, its validator and its photo survive and the photo still serves. '
    .'What is lost is the path. Measured on live ems 2026-10-16 by reproducing the controller’s predicate in '
    .'SQL, the queue holds exactly two enrollments — 91 (DEMO-2026-016) and 876 (DEMO-2026-026), each with one '
    .'pending request — and no validated request appears in it, while the dataset’s only validated row with a '
    .'face photo belongs to DEMO-2026-017 and is reachable only by typing /id/92. The student’s own 360 page '
    .'still reads the status, so the record is hidden from the desk’s screen rather than from the institution. '
    .'Open: no fix has been ruled. Documentation/Demo-Day-Guide.md step 13 now walks validation as an action on '
    .'a pending request and says plainly that the hub keeps no history of finished ones.';
$actor = 'Developer, if the desk is to keep its own finished work on screen';
$raised = '§18.3 (the desk’s three steps), raised by the demo-day walk';

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

// The structural proof the container counts cannot give: the row before must end where this one starts, and the
// bytes after must open another row. That places the clone between two sibling rows of the same table. A row
// dropped inside a cell leaves every count balanced and Word still refuses the file.
$guard($rowStart !== false && substr($xml, (int) $rowStart - 7, 7) === '</w:tr>',
    'the row is preceded by a sibling row’s close');
$guard($rowEnd !== false && substr($xml, (int) $rowEnd, 6) === '<w:tr>',
    'the row is followed by a sibling row’s open — the clone lands as a child of the table');

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
$guard(str_contains($clone, htmlspecialchars($id)), 'the clone carries its own id');

// Deltas read off the cloned row, so the guard cannot be satisfied by a template that grew a cell.
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
    $guard($after === $before[$k] + $expect[$k],
        "container {$k}: {$before[$k]} → {$after} (expected +{$expect[$k]})");
}

$doc = new DOMDocument();
$guard($doc->loadXML($new) === true, 'document.xml parses');

// The register’s own count sentence must move with the row, or the document contradicts its table.
$countNeedle = 'finding rows indexed: 90';
printf("\nnew row: %d bytes | body %d chars\n", strlen($clone), strlen($body));

if ($fails > 0) {
    fwrite(STDERR, "{$fails} guard(s) failed — nothing written\n");
    exit(1);
}
if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-16-pass136.docx');

$z0 = new ZipArchive();
if ($z0->open($src) !== true) {
    fwrite(STDERR, "cannot open the docx to count its parts — nothing written\n");
    exit(1);
}
$partsBefore = count($z0);
$z0->close();

// ZipArchive::OVERWRITE truncates the whole archive rather than one entry — see pass 128's note. CREATE alone,
// and the part count below is what makes that mistake impossible to commit.
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
