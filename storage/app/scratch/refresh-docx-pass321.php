<?php

/**
 * Pass 321: §28.3 G-40 gains its seventh wired table, and the counts that name the wired set move with it.
 *
 * Four edits, all inside the G-40 row's verdict cell. Two are the tally — seven screens sort on the server, so the
 * unwired count goes from thirty to twenty-nine — one is the list of wired screens gaining the clinic queue, and one
 * appends what this tranche added to the mechanism's story: a column withheld because of its TYPE rather than because
 * the query cannot reach it.
 *
 * No totals are typed that a runner cannot share. The leg count and the case count are stated because the harness
 * that produced them prints them, and `lane59-instrument-counts.php` binds a figure to the tool named beside it.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass321.php [--apply]
 */

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

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

$edits = [
    [
        'on the assessment desk’s fee sheets and on the cashier’s collection queue — 30 of the 36 pages',
        'on the assessment desk’s fee sheets and on the cashier’s collection queue, and on the clinic’s health queue — 29 of the 36 pages',
        'and on the clinic’s health queue',
    ],
    [
        'the six wired pages call',
        'the seven wired pages call',
        'the seven wired pages call',
    ],
    [
        'six of the thirty-six tables that render the shared component sort on the server today, five of them queues and one a directory',
        'seven of the thirty-six tables that render the shared component sort on the server today, six of them queues and one a directory',
        'seven of the thirty-six tables',
    ],
    [
        'which is the spent-counter family reached from a column’s type',
        'which is the spent-counter family reached from a column’s type. The seventh table, the clinic’s health queue, is the'
            .' first that withholds a visible column because of its TYPE rather than because the query cannot reach it:'
            .' Student Type and Standing both sit on the row the desk already reads, both are declared ENUM, and both are'
            .' left out of the handler’s list with the reason written beside it — because the school’s server sorts an ENUM'
            .' by the index each value was declared with and the test schema’s varchar sorts the stored word, a header over'
            .' either column would answer a different sequence depending on which machine replied. The screen therefore'
            .' offers four headers where six columns are visible, and the case that pins the absence asserts the offered set'
            .' rather than trusting the comment. This queue is also the first wired screen whose population is a workflow'
            .' predicate rather than a status filter: it is `DeskQueues::clinic()` — the enrollments whose FIRST open box is'
            .' office 11 — so the badge and the list were already one call before the order was added, and the tiles are'
            .' cloned before a surname join exists. Its harness is `mutate-g40-clinic-sort-tests.php` at eleven legs, two of'
            .' which plant the join rather than the column, because a surname ordered with no join is not a wrong sequence'
            .' but a list the desk would never have rendered. What a browser could and could not show: this box’s demo data'
            .' holds a single row at the clinic box, so the sequence is proved by the suite’s fixture, which writes four.'
            .' The read that day was the contract, and it held — three headers send sort and direction, the second click'
            .' reverses, and a filter submit carries both the typed search and the arrow.',
        'withholds a visible column because of its TYPE',
    ],
];

foreach ($edits as [$from, $want, $guard]) {
    $h = substr_count($xml, $from);
    if ($h !== 1) {
        fwrite(STDERR, "REFUSED: [".mb_substr($from, 0, 60)."…] occurs {$h}, want 1.\n");

        exit(1);
    }
    // A replacement that keeps its needle as its own head cannot be detected by the needle alone: the fourth edit's
    // phrase still exists after the append, so a second run would append twice. Each edit therefore names a
    // fragment only the NEW prose carries, and the run refuses when that fragment is already on the page.
    if (substr_count($xml, $guard) !== 0) {
        fwrite(STDERR, "REFUSED: the new fragment [{$guard}] is already present (".substr_count($xml, $guard)." copy(ies)) — this pass has run.\n");

        exit(1);
    }
}

$textEdits = array_map(static fn (array $e): array => [$e[0], $e[1], null, 1], $edits);
$tmp = $root.'/storage/app/scratch/pass321-tmp-'.date('Ymd-His').'.docx';

docxApply($docx, $tmp, $textEdits, [], $apply);

if (! $apply) {
    echo "\nDRY RUN — pass 321 wrote nothing.\n";

    exit(0);
}

$again = new ZipArchive;
if ($again->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot reopen the written docx.\n");

    exit(1);
}
$after = (string) $again->getFromName('word/document.xml');
$again->close();

foreach (['and on the clinic’s health queue — 29 of the 36 pages', 'the seven wired pages call',
    'seven of the thirty-six tables', 'withholds a visible column because of its TYPE'] as $want) {
    if (substr_count($after, $want) !== 1) {
        fwrite(STDERR, "REFUSED after write: [{$want}] occurs ".substr_count($after, $want).", want 1.\n");

        exit(1);
    }
}
// Checked against the LONG needles, each proved to occur exactly once above: a short form like
// "30 of the 36 pages" could occur in a sentence this pass never touched, and demanding zero copies of it
// would refuse a correct write — the same "asserted the bare number had vanished document-wide" mistake
// lane 69 records for pass 229.
foreach ([$edits[0][0], $edits[1][0], $edits[2][0]] as $gone) {
    if (substr_count($after, $gone) !== 0) {
        fwrite(STDERR, "REFUSED after write: the retired needle [".mb_substr($gone, 0, 50)."…] still occurs ".substr_count($after, $gone)." time(s).\n");

        exit(1);
    }
}

echo "\npass 321 applied: G-40 names seven wired tables and twenty-nine that still offer no order.\n";
