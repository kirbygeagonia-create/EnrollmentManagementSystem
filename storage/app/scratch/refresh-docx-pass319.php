<?php

/**
 * Pass 319: the sixth sortable table, the three tokens `lane76` deliberately left, and the day's §1.1 row.
 *
 * Three citation rewrites are hand-made on purpose. `lane76-shift-cites.php` was run with `--skip=172-232
 * --skip=243 --skip=290` for this file, because a mapped number is not a unique token: `:243` and `:290` each also
 * occur as the TAIL of another range — `Preflight.php:86-104, the application-key rule at :228-243` and
 * `ClearanceController.php:290-304` — so a map by number would have rewritten a true pointer into a different file.
 * Each rewrite here is anchored on the words of its own sentence instead.
 *
 * Then §28.3's G-40 row moves with the work: six of thirty-six tables, thirty pages still unoffered, and the
 * screen-half clause re-taken. The class totals are read from the junit of the run that produced them.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass319.php --junit=<file> [--apply]
 */

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = false;
$junit = null;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--apply') {
        $apply = true;

        continue;
    }
    if (! str_starts_with($arg, '--junit=')) {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads --junit=<file> and --apply only.\n");

        exit(1);
    }
    $junit = substr($arg, strlen('--junit='));
}

if ($junit === null) {
    fwrite(STDERR, "REFUSED: no --junit= given, and this pass writes a class total it will not invent.\n");

    exit(1);
}
$suite = simplexml_load_file($root.'/'.$junit);
if ($suite === false) {
    fwrite(STDERR, "REFUSED: [--junit={$junit}] does not parse.\n");

    exit(1);
}
$read = static function (string $class) use ($suite, $junit): array {
    $hits = $suite->xpath('//testsuite[@name="'.htmlspecialchars($class, ENT_QUOTES).'"]');
    $hits = $hits === false ? [] : $hits;
    if (count($hits) !== 1) {
        fwrite(STDERR, "REFUSED: [--junit={$junit}] answers ".count($hits)." testsuite node(s) named {$class}; want exactly one.\n");

        exit(1);
    }

    return [(int) $hits[0]['tests'], (int) $hits[0]['assertions']];
};
[$acctTests, $acctAssertions] = $read('Tests\\Feature\\Sorting\\AccountingQueueSortTest');

$tmp = tempnam(sys_get_temp_dir(), 'p319');
file_put_contents($tmp, (string) file_get_contents($docx));
$zip = new ZipArchive;
if ($zip->open($tmp) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive answers an error CODE for a bad file, which is truthy).\n");
    unlink($tmp);

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
unlink($tmp);

// Each entry is [needle, replacement, expected occurrences]. A needle that is a tail of another range, or that the
// styled runs split, is refused here rather than half-written by the engine.
$edits = [
    [
        'AccountingController.php:243 when a payment settles the account, and again at :290 when a zero-balance',
        'AccountingController.php:283 when a payment settles the account, and again at :330 when a zero-balance',
        1,
    ],
    [
        'the signature at :290 ), and a collected payment signs the same box at :243.',
        'the signature at :330 ), and a collected payment signs the same box at :283.',
        1,
    ],
    [
        '172-232',
        '212-272',
        2,
    ],
    [
        'on the campus clearance queue and on the assessment desk’s fee sheets',
        'on the campus clearance queue, on the assessment desk’s fee sheets and on the cashier’s collection queue',
        1,
    ],
    [
        '31 of the 36 pages that use the shared table',
        '30 of the 36 pages that use the shared table',
        1,
    ],
    [
        'the five wired pages call resources',
        'the six wired pages call resources',
        1,
    ],
    [
        'five of the thirty-six tables that render the shared component sort on the server today, four of them queues and one a directory',
        'six of the thirty-six tables that render the shared component sort on the server today, five of them queues and one a directory',
        1,
    ],
    [
        'because a figure beside a class name is a property of a run, not of the code.',
        'because a figure beside a class name is a property of a run, not of the code. A sixth table joined the same'
        .' mechanism the same day — the cashier’s collection queue — and it is the first of the sortable screens that'
        .' carries a tab, a search and an order at once, so the echo contract now has three keys to return and the new'
        .' case pins all three: AccountingQueueSortTest answers '.$acctTests.' cases and '.$acctAssertions.' assertions'
        .' in the run whose junit this row read. The two money columns tell each other apart on purpose, because the'
        .' fixture was built so that the billed amount and the balance owed are different sequences, and the derived'
        .' ‘Covered By’ column is absent from the sortable set rather than offered and ignored. Three of its citations'
        .' were re-taken by hand rather than by the shift tool, because the numbers :243 and :290 are also the tails of'
        .' another file’s ranges — `Preflight.php:86-104 … :228-243` and `ClearanceController.php:290-304` — and a'
        .' mapped number that is not a unique token rewrites a true pointer into a different file.',
        1,
    ],
];

foreach ($edits as [$needle, , $want]) {
    $h = substr_count($xml, $needle);
    if ($h !== $want) {
        fwrite(STDERR, "REFUSED: needle [{$needle}] occurs {$h}, want {$want}.\n");

        exit(1);
    }
}

// The replacement spellings must not already exist elsewhere, or the post-write tally below would be read as a
// half-applied edit when it is really a collision: a number written after another file's range moved to the same
// line is the wrong-target defect this repository already names for line numbers.
foreach (['212-272', 'AccountingController.php:283', 'the six wired pages call resources'] as $fresh) {
    $h = substr_count($xml, $fresh);
    if ($h !== 0) {
        fwrite(STDERR, "REFUSED: the replacement [{$fresh}] already occurs {$h} time(s) before this pass writes anything.\n");

        exit(1);
    }
}

printf("%s: six tables wired, three pointers re-taken by reading, the day's row carries the sixth\n", $apply ? 'APPLY' : 'DRY RUN');

require __DIR__.'/docx-edit-engine.php';

docxApply(
    $docx,
    $apply ? $root.'/storage/app/scratch/pass319-backup-'.date('Ymd-His').'.docx'
        : $root.'/storage/app/scratch/pass319-dry.docx',
    array_map(fn (array $e): array => [$e[0], $e[1], null, $e[2]], $edits),
    [],
    $apply
);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$again = new ZipArchive;
if ($again->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot re-open the written archive.\n");

    exit(1);
}
$after = (string) $again->getFromName('word/document.xml');
$again->close();

foreach ([
    'AccountingController.php:283 when a payment settles the account' => 1,
    'the signature at :330 ), and a collected payment signs the same box at :283.' => 1,
    '212-272' => 2,
    'on the assessment desk’s fee sheets and on the cashier’s collection queue' => 1,
    '30 of the 36 pages that use the shared table' => 1,
    'the six wired pages call resources' => 1,
    'six of the thirty-six tables that render the shared component sort' => 1,
    'A sixth table joined the same mechanism' => 1,
    // The retired spellings must be gone, not merely joined by a second copy.
    '172-232' => 0,
    'AccountingController.php:243' => 0,
    'the signature at :290' => 0,
    '31 of the 36 pages that use the shared table' => 0,
    'the five wired pages call resources' => 0,
    'five of the thirty-six tables that render the shared component sort' => 0,
] as $needle => $want) {
    $h = substr_count($after, $needle);
    if ($h !== $want) {
        fwrite(STDERR, "REFUSED after the write: [{$needle}] occurs {$h}, want {$want}.\n");

        exit(1);
    }
}

echo "APPLIED.\n";
