<?php

/**
 * Pass 317: the day's §1.1 row gains the fourth sortable table and the label defect it surfaced.
 *
 * Appended to the row that already stands, for pass 306's reason. No case count is typed here —
 * `lane58-suite-claims.php --ci` binds a number beside a class name to the runner's junit, and a revision row has
 * no business stating a total the runner may stale; the file names are enough for a reader to go count them.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass317.php [--apply]
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

$from = 'and only reading can.';
$to = $from.' A fourth table joined the mechanism the same day, and it produced the other finding of the pair: the'
    .' campus clearance queue sorts now, and opening the running page to prove that showed its Academic Term column'
    .' and its period dropdown printing the literal word undefined — five pages had been building an academic-year'
    .' label from yearStart, yearEnd and year, names `academicyears` never held, with no accessor and no append'
    .' behind them. Nine sites read `yearLabel` now, and'
    .' `tests/Feature/Clearance/AcademicYearLabelSurfaceTest.php` forbids the class by deriving the legal names from'
    .' the migrated table and from the model itself, resolving page-local aliases so a read cannot hide behind a'
    .' local. Where the same field carried a fallback to the empty string a desk simply never saw which academic'
    .' year a term belonged to, which is the case for reading the JSX in a test: no gate between PHPStan and the'
    .' Vite build can see a name the schema does not have.';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive answers an error CODE for a bad file, which is truthy).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

foreach ([[$from, 1], ['A fourth table joined the mechanism', 0]] as [$needle, $want]) {
    if (($h = substr_count($xml, $needle)) !== $want) {
        fwrite(STDERR, "REFUSED: [{$needle}] occurs {$h}, want {$want}.\n");

        exit(1);
    }
}

printf("%s: the day's row gains the clearance queue and the label finding\n", $apply ? 'APPLY' : 'DRY RUN');

docxApply($docx, $apply ? $root.'/storage/app/scratch/pass317-backup-'.date('Ymd-His').'.docx'
    : $root.'/storage/app/scratch/pass317-dry.docx', [[$from, $to]], [], $apply);

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

foreach ([['A fourth table joined the mechanism', 1], ['AcademicYearLabelSurfaceTest', 1], [$from, 1]] as [$needle, $want]) {
    if (($h = substr_count($after, $needle)) !== $want) {
        fwrite(STDERR, "REFUSED after the write: [{$needle}] occurs {$h}, want {$want}.\n");

        exit(1);
    }
}

echo "APPLIED.\n";
