<?php

/**
 * Pass 323: §28.3 G-40 gains its eighth wired table, the ID desk's validation queue.
 *
 * Four edits, all inside the G-40 row's verdict cell: the wired list gains the ID desk, the two tallies move
 * (eight tables sort, twenty-eight do not), and the appended account says what this screen added to the mechanism —
 * a TAB as a third echoed key, and the fact that the tab chooses which set the order arranges, which is a defect a
 * harness can now plant (`order-applied-to-the-other-base`) and no earlier screen could express.
 *
 * No totals are stated that a runner cannot share. The leg count is stated because the harness prints it.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass323.php [--apply]
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

$edits = [
    [
        'and on the clinic’s health queue — 29 of the 36 pages',
        'and on the clinic’s health queue, and on the ID desk’s validation queue — 28 of the 36 pages',
        'and on the ID desk’s validation queue',
    ],
    [
        'the seven wired pages call',
        'the eight wired pages call',
        'the eight wired pages call',
    ],
    [
        'seven of the thirty-six tables that render the shared component sort on the server today, six of them queues and one a directory',
        'eight of the thirty-six tables that render the shared component sort on the server today, seven of them queues and one a directory',
        'eight of the thirty-six tables',
    ],
    [
        'and a filter submit carries both the typed search and the arrow',
        'and a filter submit carries both the typed search and the arrow. The eighth table, the ID desk’s validation '
            .'queue, is the first screen that sends THREE controls an order must survive: the tab it is standing on, the '
            .'search it typed, and the order itself. A tab is not decoration — it chooses which population the list is '
            .'built from, the enrollments whose first open box is office 22 and the ones whose ID box is already signed — '
            .'so the order is applied to the base the tab chose and never to the other one, and a harness can plant the '
            .'swap (`order-applied-to-the-other-base`) and require the tab’s own case to go red. The fourth key is the '
            .'tab coming back: an echoed order beside an un-echoed tab is the same reset the register owns for filters, '
            .'seen from the side a desk switches rather than types. ID Status joins Student Type and Standing as a column '
            .'the desk sees and the handler will not order, for a third reason again: it paints the status of this row’s '
            .'FIRST request, and ordering by it would mean deciding what two requests on one enrollment amount to — a '
            .'question the school has never been asked, so the header is absent rather than invented. Thirteen legs of '
            .'`mutate-g40-id-sort-tests.php`, all killed. And unlike the clinic box, this queue is long enough to '
            .'demonstrate an order at the desk rather than only in a fixture: live `ems` holds two enrollments whose first '
            .'open box is the ID office, and the running screen swapped them — Alcantara first at rest, Sotto first under '
            .'an ascending School ID order, Alcantara first again on the second click, while the header’s own '
            .'accessibility state read none, then ascending, then descending, and the three columns the handler withholds '
            .'showed no control at all',
        'queue, is the first screen that sends THREE controls',
    ],
];

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive answers an error CODE for a bad file, which is truthy).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

foreach ($edits as [$from, $want, $guard]) {
    $h = substr_count($xml, $from);
    if ($h !== 1) {
        fwrite(STDERR, "REFUSED: [".mb_substr($from, 0, 60)."…] occurs {$h}, want 1.\n");

        exit(1);
    }
    if (substr_count($xml, $guard) !== 0) {
        fwrite(STDERR, "REFUSED: the new fragment [{$guard}] is already present (".substr_count($xml, $guard)." copy(ies)) — this pass has run.\n");

        exit(1);
    }
}

$textEdits = array_map(static fn (array $e): array => [$e[0], $e[1], null, 1], $edits);
docxApply($docx, $root.'/storage/app/scratch/pass323-tmp-'.date('Ymd-His').'.docx', $textEdits, [], $apply);

if (! $apply) {
    echo "\nDRY RUN — pass 323 wrote nothing.\n";

    exit(0);
}

$again = new ZipArchive;
if ($again->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot reopen the written docx.\n");

    exit(1);
}
$after = (string) $again->getFromName('word/document.xml');
$again->close();

foreach (['and on the ID desk’s validation queue — 28 of the 36 pages', 'the eight wired pages call',
    'eight of the thirty-six tables', 'queue, is the first screen that sends THREE controls'] as $want) {
    if (substr_count($after, $want) !== 1) {
        fwrite(STDERR, "REFUSED after write: [{$want}] occurs ".substr_count($after, $want).", want 1.\n");

        exit(1);
    }
}
foreach ([$edits[0][0], $edits[1][0], $edits[2][0]] as $gone) {
    if (substr_count($after, $gone) !== 0) {
        fwrite(STDERR, "REFUSED after write: the retired needle [".mb_substr($gone, 0, 50)."…] still occurs.\n");

        exit(1);
    }
}

echo "\npass 323 applied: G-40 names eight wired tables and twenty-eight that still offer no order.\n";
