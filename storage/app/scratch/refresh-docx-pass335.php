<?php

/**
 * Docx pass 335 — the 2026-10-31 revision row still carried the debt figure the graph reader superseded.
 *
 * Pass 333 rewrote the G-52 register row to the census's own numbers (7 detail, 4 print, 2 legitimate editors) and
 * pass 334 removed the two stale clauses inside it. The §1.1 revision row for the same day was written before that
 * re-reading and still says "with its row carrying the remaining twelve sites as a measured debt" — so one row held
 * two answers to "how many sites are owed", which is the two-definitions defect this repository names for a tool, a
 * column vocabulary and a register tally. The digit goes; the sentence keeps its claim, because the count lives in
 * the register row where the instrument answers it.
 *
 * The second edit records what the same day's second half did, so the document does not describe only the first
 * batch: the page-read classifier is fixed, and the applied narrowing was re-audited against the pages that consume
 * it. No count is stated here, because the audit is a reading of this box's scratch tools.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass335.php [--apply]
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$backup = $root.'/storage/app/scratch/pass335-backup.docx';

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}
require __DIR__.'/docx-edit-engine.php';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive answers an error CODE for a bad file, which is truthy).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$tail = 'CI’s MySQL job named it; it was reproduced on a scratch schema, the tile now replaces its own column list, '
    .'and both drivers answer the same assertions.';
$added = $tail.' The reading that sized the debt was then re-audited, and both of its halves were the instrument’s: '
    .'a page paints through the components it imports, so deciding what it reads from the page file alone classed an '
    .'editing screen as name-only, and the reader that walks the import graph recorded only the first student column '
    .'named on a line. Every list this batch narrowed was therefore weighed again against the page its own handler '
    .'renders, by a tool that refuses a walk that reached a single file and reports any column the page still names; '
    .'the harness that proves it plants a dropped identity column, a painted suffix, a painted demographic field and a '
    .'shared name that must not classify, and requires the read it planted — not merely the probe’s name — to be on '
    .'disk before it measures anything.';

$textEdits = [
    [
        'with its row carrying the remaining twelve sites as a measured debt',
        'with its row carrying the remaining sites as a measured debt',
    ],
    [$tail, $added],
];

$failures = [];
$before = [];
foreach ($textEdits as $i => [$from, $to]) {
    $n = substr_count($xml, $from);
    $before[$i] = $n;
    printf('edit %d: %d occurrence(s) — %s%s', $i, $n, substr($from, 0, 52), PHP_EOL);
    if ($n !== 1) {
        $failures[] = "edit {$i} matched {$n} time(s), wanted exactly 1 — the sentence may be split across runs,"
            .' which means it goes through the cell or para kind, not a text needle';
    }
    if (preg_match('/\.php:\d/u', $to) === 1) {
        $failures[] = "edit {$i}'s replacement introduces a line citation the pass has not verified";
    }
    if (str_contains($to, '|')) {
        $failures[] = "edit {$i}'s replacement carries a literal pipe";
    }
}
// The row must end up stating one debt figure only: the derived split lives in the G-52 register row.
foreach (['twelve sites', '12 detail', '8 detail screens'] as $stale) {
    if (substr_count($xml, $stale) > 0 && ! str_contains(json_encode($textEdits), $stale)) {
        printf('note: the document also carries "%s" outside this pass\'s needles — read it before believing the row is clean%s', $stale, PHP_EOL);
    }
}
if ($failures !== []) {
    $already = true;
    foreach ($textEdits as [, $to]) {
        if (substr_count($xml, $to) !== 1) {
            $already = false;

            break;
        }
    }
    if ($already) {
        echo "ALREADY APPLIED — the row states one debt figure and records the re-audit.\n";

        exit(0);
    }
    fwrite(STDERR, "REFUSED — nothing written:\n".implode("\n", $failures)."\n");

    exit(1);
}

docxApply($docx, $backup, $textEdits, [], $apply);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot re-open the written archive.\n");

    exit(1);
}
$after = (string) $zip->getFromName('word/document.xml');
$zip->close();

$bad = 0;
// Edit 1's replacement is its own needle PLUS new sentences, so the needle necessarily still occurs once. A
// post-check that demands the old text be gone would refuse a correct write — pass 229's lesson that a re-taken
// number already existing elsewhere cannot be falsified by document-wide absence. So each edit states what its
// residual count means, and edit 1 is judged on the marker of the clause it added instead.
$residual = [0, 1];
$addedMarkers = [null, 'The reading that sized the debt was then re-audited'];
foreach ($textEdits as $i => [$from, $to]) {
    $g = substr_count($after, $to);
    $l = substr_count($after, $from);
    printf('  edit %d: new form %d (want 1), old form %d (want %d)%s', $i, $g, $l, $residual[$i], PHP_EOL);
    if ($g !== 1 || $l !== $residual[$i]) {
        $bad++;
    }
    if ($addedMarkers[$i] !== null && substr_count($after, $addedMarkers[$i]) !== 1) {
        printf('  edit %d: the added clause marker occurs %d time(s), want 1%s',
            $i, substr_count($after, $addedMarkers[$i]), PHP_EOL);
        $bad++;
    }
}
if (substr_count($after, 'twelve sites') !== 0) {
    echo "  the superseded debt count still occurs in the document\n";
    $bad++;
}
if ($bad > 0) {
    fwrite(STDERR, "REFUSED: {$bad} read-back check(s) failed.\n");
    if (is_file($backup) && copy($backup, $docx)) {
        fwrite(STDERR, 'restored '.basename($backup).'; nothing of the bad write is left.'."\n");
    } else {
        fwrite(STDERR, "AND no backup was kept, so the write stands — read the row back before editing anything else.\n");
    }

    exit(1);
}
echo "written, re-read: the §1.1 row states one debt figure, and records the re-audit beside the first half of the day.\n";
