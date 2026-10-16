<?php

/**
 * Pass 128 — §1.1's revision record still ends at "2026-10-10 (this issue)" while the body carries the build
 * through 2026-10-15.
 *
 * This adds a row by CLONING the 2026-10-10 row and replacing the text in its two single-run cells, so the new
 * row inherits the column widths, borders, margins and run properties instead of inventing them. A row is not
 * something the edit engine can insert: its `inserts` kind resolves a paragraph and lands after `</w:p>`, and a
 * `<w:tr>` written there would sit inside a cell — where every container count the engine guards still balances,
 * because it opens and closes itself. So this script states the guards that actually matter and refuses to write
 * unless all of them hold:
 *
 *   - the anchor occurs exactly once, and the row it lives in is the table's LAST row: the eight bytes after it
 *     must be `</w:tr>` -> `</w:tbl>`, which is what proves the clone lands as a child of the table;
 *   - each cell holds exactly one `<w:t>`, which is what lets "replace the cell's text" mean one thing;
 *   - after the write, `<w:tr>` +1, `<w:tc>` +2, `<w:p>` +2, `<w:t` +2, and nothing else moved;
 *   - document.xml parses, and the file on disk holds the new bytes.
 *
 * The "(this issue)" marker moves with the work: the 2026-10-10 row loses it and the new row carries it.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$labelNew = '2026-10-11–15 (this issue)';
$labelOld = '2026-10-10 (this issue)';
$summaryNew = 'Two per-office UAT walks in the running system, and what they found became commands, gates and data decisions rather than notes. Console: `ems:audit:record-states` — twelve rules over the state-bearing tables, the impossible ones failing the run and the ruled ones noted with who ruled them — and `ems:preflight`, which turns DEPLOYMENT.md into a check of the environment pair, the database, whether PHP and MySQL write the same string into a DATETIME, unapplied migrations, an unseeded RBAC, the privacy of the documents disk and of the folder Laravel serves at /storage/{path}, the application key, the writable folders, a browser for PDFs and the queue connection. Continuous integration: the end-to-end walkthrough legs run on MySQL in a step of their own against a seeded schema under --fail-on-skipped, the runner image is pinned to ubuntu-24.04 because ubuntu-latest leaves for it on 2026-10-19, and `ems:ci:no-silent-skips` refuses a skip this project has not agreed to — written after a signed-off step failed on its first run and a skipped leg still reported a pass. Front end: four client-side loads that answered for the server were corrected (a painted zero with a green “queue clear”, a catch that left the previous query’s rows on screen, a success repaint driven by the 419 the session handler returns), and a refusal that says why now reaches the desk twice — on the 403 page and as JSON for a caller that asked for it. Two rulings were carried out on live data: #89 repointed 29 Department Evaluation credits to each row’s own college desk, and #91 made the application the only process that stamps a record, dropping both CURRENT_TIMESTAMP defaults and forcing the session zone per connection, with my.cnf still owed on the school’s server. Each college now seats its own Academic Department desk and App\\Support\\CollegeScope is the only place that boundary is expressed. Records: the contradictory ID request 53 was deleted with its signature left standing by ruling, 144 unreferenced uploads were pruned behind a manifest, the demo pipeline resolves every reference id by name instead of by number, and §15.1’s ID seat was re-seated so the walkthrough can be performed. The document itself was swept by meaning rather than by line: five drifted citations, four stale claims in §18.6 and a register mis-citation that had three files calling the print-log NULL limit §28 G-9 when it is §25 P-14. Finally the record-state register was closed on 2026-10-15 — the 21 key-less print rows pruned behind a manifest, the seven past-term records the seeder had signed without the acts behind them retired through the Registrar’s drop, that print rule promoted from ruled note to impossible — and the generator was fixed in the same batch, because the run immediately after the retirement re-created five of the seven within the hour.';

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open docx\n");
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$anchor = 'The five-lane audit the owner asked for after the catalogue was closed';

$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    if (! $ok) {
        $fails++;
    }
};

$at = strpos($xml, $anchor);
$guard(substr_count($xml, $anchor) === 1, 'the anchor occurs exactly once');

$rowStart = $at === false ? false : strrpos(substr($xml, 0, $at), '<w:tr');
$rowEnd = $at === false ? false : strpos($xml, '</w:tr>', $at) + 7;
$guard($rowStart !== false && $rowEnd !== false, 'the row containing it is bounded');

// The one guard that cannot be inferred from counts: a row inserted inside a cell leaves every container count
// balanced and Word still calls the file corrupt.
$guard($rowEnd !== false && substr($xml, $rowEnd, 8) === '</w:tbl>', 'the row is the table’s last row — the clone lands as a child of the table');

$row = substr($xml, (int) $rowStart, $rowEnd - (int) $rowStart);
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);
$guard(count($cm[0]) === 2, 'the row has exactly two cells');

// Built by byte surgery, not preg_replace: the summary carries `App\Support\CollegeScope`, and a backslash in
// a preg_replace replacement string is an escape the pattern language has to interpret. And the run is located
// by its *content* — searching for the literal `<w:t` finds `<w:tc>` first, which is how the first draft of
// this file spliced prose into the middle of a cell's own structure.
$setText = function (string $cell, string $text): string {
    if (! preg_match('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell, $m, PREG_OFFSET_CAPTURE)) {
        throw new RuntimeException('the cell carries no text run to replace');
    }
    $escaped = str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);
    $start = $m[1][1];

    return substr($cell, 0, $start).$escaped.substr($cell, $start + strlen($m[1][0]));
};

$cells = [];
foreach ($cm[0] as $i => $cell) {
    $guard(substr_count($cell, '</w:t>') === 1, "cell {$i} holds exactly one text run");
    $cells[] = $setText($cell, $i === 0 ? $labelNew : $summaryNew);
}

$clone = str_replace($cm[0][0], $cells[0], $row);
$clone = str_replace($cm[0][1], $cells[1], $clone);
$guard($clone !== $row && substr_count($clone, '<w:tr>') === 1, 'the clone is one row');
$guard(str_contains($clone, $summaryNew) || str_contains($clone, str_replace('&', '&amp;', substr($summaryNew, 0, 40))), 'the clone carries the new summary');

// The old row stops claiming to be this issue.
$labelSwap = 1;
if (substr_count($xml, $labelOld) !== $labelSwap) {
    $guard(false, "the '(this issue)' marker occurs ".substr_count($xml, $labelOld).' time(s), expected 1');
}

// Counted with the closing forms so a prefix collision cannot fake a delta: `<w:tc` also matches
// `<w:tcPr>`, `<w:tr` also matches `<w:trPr>`, `<w:p` also matches `<w:pPr>`.
$needle = ['tr' => '</w:tr>', 'tc' => '<w:tc>', 'p' => '</w:p>', 't' => '</w:t>'];
$expect = ['tr' => 1, 'tc' => 2, 'p' => 2, 't' => 2];

$before = [];
foreach ($needle as $k => $n) {
    $before[$k] = substr_count($xml, $n);
}

$new = str_replace($labelOld, '2026-10-10', $xml);
$atNew = strpos($new, $anchor);
$guard($atNew !== false && substr_count($new, $anchor) === 1, 'the anchor still occurs exactly once after the label swap');
$shift = strlen($labelOld) - strlen('2026-10-10');
$guard((int) strpos($xml, $anchor) - (int) $atNew === $shift, 'the label swap moved the anchor by exactly the '.$shift.' bytes it removed');
$new = substr_replace($new, $clone, (int) strpos($new, '</w:tr>', (int) $atNew) + 7, 0);

foreach ($needle as $k => $n) {
    $after = substr_count($new, $n);
    $guard($after === $before[$k] + $expect[$k], "container {$k}: {$before[$k]} → {$after} (expected +{$expect[$k]})");
}

$doc = new DOMDocument();
$guard($doc->loadXML($new) === true, 'document.xml parses');

printf("\nnew row: %d bytes | summary %d chars\n", strlen($clone), strlen($summaryNew));

if ($fails > 0) {
    fwrite(STDERR, "{$fails} guard(s) failed — nothing written\n");
    exit(1);
}
if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-15-pass128.docx');

$z0 = new ZipArchive();
if ($z0->open($src) !== true) {
    fwrite(STDERR, "cannot open the docx to count its parts — nothing written\n");
    exit(1);
}
$partsBefore = count($z0);
$z0->close();

// `ZipArchive::OVERWRITE` does not mean "overwrite this entry" — it truncates the archive and starts a new
// one, which is how the first draft of this file left a 14-part document with exactly one part: Word's file,
// minus [Content_Types].xml, the rels, styles, numbering, settings and every docProps. The engine opens with
// CREATE alone, and the part count below is what makes that mistake impossible to commit.
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
