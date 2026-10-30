<?php

/**
 * Pass 318: the fifth sortable table, the ENUM lesson CI named, and the day's §1.1 row.
 *
 * Four moves in §28.3's G-40 row — the wired list grows by the assessment desk's fee sheets, the page tally moves
 * with it, the screen-half clause stops saying "four", and the sorting queue sentence ("two of those twenty-nine
 * queues sort on the server today") is re-taken, because a tally that names the work while the work grows is lane
 * 61's defect in a new place. One append to today's §1.1 row carries the two-hop join, the driver-conditional ENUM
 * order CI's MySQL job found, and the filter-contract reader that was blind to the shape the wiring introduced.
 *
 * The two class totals are NOT typed here: they are read out of the junit of the whole-suite run that produced
 * them, and the pass refuses without it. That is lane 98's rule — a figure beside a class name comes from the run,
 * not from intent — and lane 58's, that a claim must bind to a reading it can name.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass318.php <junit.xml> [--apply]
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
    fwrite(STDERR, "REFUSED: no --junit= given, and this pass writes two class totals it will not invent.\n");

    exit(1);
}
$docxBytes = (string) file_get_contents($docx);
$tmp = tempnam(sys_get_temp_dir(), 'p318');file_put_contents($tmp, $docxBytes);
$zip = new ZipArchive;
if ($zip->open($tmp) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive answers an error CODE for a bad file, which is truthy).\n");
    unlink($tmp);

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
unlink($tmp);

/** The per-class totals, read from the run that produced them. */
$suite = simplexml_load_file($root.'/'.$junit);
if ($suite === false) {
    fwrite(STDERR, "REFUSED: [--junit={$junit}] does not parse, so no class total can be read from it.\n");

    exit(1);
}
$read = static function (string $class) use ($suite, $junit): array {
    // Every class is its own <testsuite> node, nested under the file node and that under the run's top node, so
    // a walk of the first level alone finds no class and the pass would refuse a figure it could have read.
    $hits = $suite->xpath('//testsuite[@name="'.htmlspecialchars($class, ENT_QUOTES).'"]');
    $hits = $hits === false ? [] : $hits;
    if (count($hits) !== 1) {
        fwrite(STDERR, "REFUSED: [--junit={$junit}] answers ".count($hits)." testsuite node(s) named {$class}; want exactly one.\n");

        exit(1);
    }

    return [(int) $hits[0]['tests'], (int) $hits[0]['assertions']];
};
[$clearanceTests, $clearanceAssertions] = $read('Tests\\Feature\\Sorting\\ClearanceQueueSortTest');
[$assessTests, $assessAssertions] = $read('Tests\\Feature\\Sorting\\AssessmentQueueSortTest');
printf("junit read: ClearanceQueueSortTest %d/%d, AssessmentQueueSortTest %d/%d\n",
    $clearanceTests, $clearanceAssertions, $assessTests, $assessAssertions);

$edits = [
    [
        'on the campus clearance queue',
        'on the campus clearance queue and on the assessment desk’s fee sheets',
        'on the campus clearance queue',
    ],
    [
        '32 of the 36 pages that use the shared table',
        '31 of the 36 pages that use the shared table',
        '32 of the 36 pages that use the shared table',
    ],
    [
        'the four wired pages call resources',
        'the five wired pages call resources',
        'the four wired pages call resources',
    ],
    [
        'two of those twenty-nine queues sort on the server today',
        'five of the thirty-six tables that render the shared component sort on the server today, four of them queues and one a directory',
        'two of those twenty-nine queues sort on the server today',
    ],
    [
        'and the case that pins each.',
        'and the case that pins each. The fee sheets joined on 2026-10-30 and needed a shape the first four do not'
        .' carry: a studentassessments row holds neither a surname nor a school number — it points at an enrollment,'
        .' which points at a student — so ordering that queue by the student is a two-hop join. The helper reads'
        .' either spelling through one private method (one join/foreign/local triple, or a joins list whose each'
        .' local names the base table or an earlier hop’s alias), and qualifies a bare column with the hop that'
        .' actually holds it; two of the tranche’s eight planted defects live in the helper rather than in a handler'
        .' because the mechanism, not the screen, was the new surface.'
        .' And CI’s MySQL job went red on a case this box had run green: studentclearances.overallStatus is declared'
        .' as an ENUM, which MySQL sorts by its declaration index, while the suite compiles the same enum() into a'
        .' varchar with a CHECK and sorts the stored word — so sort=status&direction=asc answers two different'
        .' sequences on the two surfaces, and the clearance case had asserted the local one as though it were the'
        .' only one. It now asks its own connection which order the column has, and its fixture is created Pending,'
        .' Rejected, Approved so that four orders — id up, id down, the enum’s order, the string’s order — give four'
        .' different sequences. A sequence typed into a test is a reading of whichever database was open when it was'
        .' written, which is the spent-counter family reached from a column’s type.',
        'and the case that pins each.',
    ],
    [
        'can see a name the schema does not have.',
        'can see a name the schema does not have. A fifth table joined the same mechanism the next day and the batch'
        .' carried two findings of its own: the fee-sheet queue reaches the student through two joins, so the helper'
        .' grew one private method that reads either a single join or a list of them and qualified the column with the'
        .' hop that holds it (eight planted defects, each killed by the case that names it — two of them inside the'
        .' helper); and CI’s MySQL job refused a case this box had passed, because an ENUM sorts by its declaration'
        .' index on the school’s server and by the stored word in the SQLite suite, so the clearance status case had'
        .' been asserting one database’s order as though it were the code’s. It derives the expected sequence from'
        .' the connection now and was re-picked so four orders give four sequences. The same wiring aged one of the'
        .' filter contract’s own harness anchors — a leg reporting MUTANT DID NOT APPLY is the harness’s age, not a'
        .' surviving defect — and re-cutting it exposed that the tool’s filters reader could not see a multi-line'
        .' array or an only([...]) + […] merge, so NO-RETURN had gone unsatisfied on every screen the sort batch had'
        .' just wired; the value is walked by its own brackets to the first comma at depth zero, a page that spreads'
        .' sortParams(…) now contributes its keys read from the helper’s own return, and the harness answers its legs'
        .' on both shapes. The two class totals in this row are read from the junit of the whole-suite run that'
        .' produced them — AssessmentQueueSortTest '
        .$assessTests.' cases / '.$assessAssertions.' assertions, and ClearanceQueueSortTest re-taken at '
        .$clearanceTests.' / '.$clearanceAssertions.' after its status case stopped remembering one database’s order —'
        .' because a figure beside a class name is a property of a run, not of the code.',
        'can see a name the schema does not have.',
    ],
];

foreach ($edits as [$from, , $needle]) {
    $h = substr_count($xml, $needle);
    if ($h !== 1) {
        fwrite(STDERR, "REFUSED: anchor [{$needle}] occurs {$h}, want 1.\n");

        exit(1);
    }
}

printf("%s: five tables wired, the enum order asked of the server, the filter reader repaired\n", $apply ? 'APPLY' : 'DRY RUN');

require __DIR__.'/docx-edit-engine.php';

docxApply(
    $docx,
    $apply ? $root.'/storage/app/scratch/pass318-backup-'.date('Ymd-His').'.docx'
        : $root.'/storage/app/scratch/pass318-dry.docx',
    array_map(fn (array $e): array => [$e[0], $e[1]], $edits),
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
    'on the campus clearance queue and on the assessment desk’s fee sheets' => 1,
    '31 of the 36 pages that use the shared table' => 1,
    'the five wired pages call resources' => 1,
    'five of the thirty-six tables that render the shared component sort on the server today' => 1,
    'A fifth table joined the same mechanism the next day' => 1,
    "assertions, and ClearanceQueueSortTest re-taken at {$clearanceTests} / {$clearanceAssertions}" => 1,
    // The retired wording must be gone, not merely accompanied: two live copies of one tally is how a register
    // contradicts itself and every checker agrees with both.
    '32 of the 36 pages that use the shared table' => 0,
    'the four wired pages call resources' => 0,
    'two of those twenty-nine queues sort on the server today' => 0,
] as $needle => $want) {
    $h = substr_count($after, $needle);
    if ($h !== $want) {
        fwrite(STDERR, "REFUSED after the write: [{$needle}] occurs {$h}, want {$want}.\n");

        exit(1);
    }
}

echo "APPLIED.\n";
