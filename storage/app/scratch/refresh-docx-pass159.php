<?php

/**
 * Pass 159 — the four citations pass 158 wrote into the demo tool moved again (this batch's own fix removed
 * three lines above them), and the issue row gains the finding CI caught: a guard that asked "is any money
 * dated today" instead of "does the report's named receipt exist".
 *
 * Every number here is read out of the tree at run time, and each needle is asserted unique before it is
 * replaced. The shift is proved per citation: for the two rows pass 158 newly wrote there is no byte-identity
 * argument to make, so the ranges are re-derived from the constructs' own braces.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$tool = 'tools/seed_full_demo_pipeline.php';

$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    if (! $ok) {
        $fails++;
    }
};

$lines = file($root.'/'.$tool);
$span = function (string $name) use ($lines): array {
    foreach ($lines as $i => $line) {
        if (preg_match('/^\s*function '.preg_quote($name, '/').'\(/', $line) === 1) {
            $depth = 0;
            $opened = false;
            for ($j = $i; $j < count($lines); $j++) {
                $depth += substr_count($lines[$j], '{') - substr_count($lines[$j], '}');
                if ($depth > 0) {
                    $opened = true;
                }
                if ($opened && $depth === 0) {
                    return [$i + 1, $j + 1];
                }
            }
        }
    }

    throw new RuntimeException("function {$name} not found in {$GLOBALS['tool']}");
};

$population = [0, 0];
foreach ($lines as $i => $line) {
    if (str_contains($line, 'foreach (Enrollments::where(fn ($q) => $q')) {
        $population[0] = $i + 1;
    }
    if ($population[0] > 0 && str_contains($line, '->get() as $claim) {')) {
        $population[1] = $i + 1;
        break;
    }
}
$guard($population[0] > 0 && $population[1] > $population[0], 'the G-2 population is measurable (:'.implode('-', $population).')');

$feeLines = $span('charge_demo_fees');
$heldSpan = $span('demo_held');
$paySpan = $span('record_demo_payment');
printf("     charge_demo_fees :%d-%d | demo_held :%d-%d | record_demo_payment :%d-%d\n",
    $feeLines[0], $feeLines[1], $heldSpan[0], $heldSpan[1], $paySpan[0], $paySpan[1]);

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$partCount = $zip->numFiles;
$xml = $zip->getFromName('word/document.xml');
$zip->close();
$new = (string) $xml;

$edits = [
    'seed_full_demo_pipeline.php:1831-1846' => 'seed_full_demo_pipeline.php:'.implode('-', $population),
    'seed_full_demo_pipeline.php:1377-1385' => 'seed_full_demo_pipeline.php:'.implode('-', $feeLines),
    'seed_full_demo_pipeline.php:1399-1402' => 'seed_full_demo_pipeline.php:'.implode('-', $heldSpan),
    'seed_full_demo_pipeline.php:1432-1450' => 'seed_full_demo_pipeline.php:'.implode('-', $paySpan),
];

foreach ($edits as $from => $to) {
    $guard(substr_count($new, $from) === 1, "the citation {$from} is present exactly once → ".explode(':', $to)[1]);
    $new = str_replace($from, $to, $new);
}

// the finding CI caught, appended to the lane-54 sentences in the revision row
$laneOld = 'And the host: ems:preflight’s new row reports the machine’s zone against the school’s declaration, '
    .'with the readings that looked suitable and are not written down beside it.';
$laneNew = 'And the host: ems:preflight’s new row reports the machine’s zone against the school’s declaration, '
    .'with the readings that looked suitable and are not written down beside it. CI then caught one more, which '
    .'no local run could have: making the paid step generator-owned meant Juan Dela Cruz’s account was settled '
    .'by the code that creates it, dated today — so the guard beside it that asked “is any money dated today?” '
    .'found an answer and never created DEMO-OR-TODAY-01, the receipt the Daily Collections Report reads by its '
    .'number and the end-to-end leg asserts. The question was the weaker one; it now asks whether the named slip '
    .'exists and re-dates it when the calendar has moved. Measured as CI measures it — a schema built from empty, '
    .'the seeders, the pipeline, then `vendor/bin/phpunit tests/Feature/E2E --fail-on-skipped`: OK, 8 tests, 196 '
    .'assertions, the two receipts summing to the ₱17,500 sheet it settled. Live `ems` was never in that state: '
    .'its named slip already stood, so the regression belonged to a fresh install and to CI, which is what the '
    .'step is for.';
$guard(substr_count($new, $laneOld) === 1, 'the revision row’s host-zone sentence is present and unique');
$new = str_replace($laneOld, $laneNew, $new);

$guard(substr_count($new, 'seed_full_demo_pipeline.php:1831-1846') === 0, 'no stale population citation remains');
$guard(strlen($new) > strlen($xml), 'the edited bytes grew by '.(strlen($new) - strlen($xml)));

if ($fails > 0) {
    printf("\n%d guard(s) failed — NOTHING WRITTEN.\n", $fails);
    exit(1);
}
if (! $apply) {
    printf("\nDRY RUN. Four citations re-pointed, one clause added. Re-run with --apply.\n");
    exit(0);
}

$backup = $root.'/storage/app/scratch/ems-docx-pre-pass159.docx';
copy($src, $backup);
$out = $root.'/Documentation/.ems-docx-pass159.tmp';
$write = new ZipArchive();
if ($write->open($out, ZipArchive::CREATE) !== true) {
    echo "REFUSED: cannot build the replacement archive.\n";
    exit(1);
}
$read = new ZipArchive();
$read->open($src);
for ($i = 0; $i < $read->numFiles; $i++) {
    $name = $read->getNameIndex($i);
    $write->addFromString($name, $name === 'word/document.xml' ? $new : $read->getFromIndex($i));
}
$write->close();
$read->close();
if (! rename($out, $src)) {
    echo "REFUSED: could not move the new archive; backup at {$backup}.\n";
    exit(1);
}
$check = new ZipArchive();
$check->open($src);
$guard($check->numFiles === $partCount, 'the archive still holds '.$partCount.' parts');
$round = $check->getFromName('word/document.xml');
$check->close();
$guard(hash('sha256', (string) $round) === hash('sha256', $new), 'word/document.xml on disk is the bytes this pass built');

printf("\nPass 159 written; backup at %s\n", $backup);
exit($fails === 0 ? 0 : 1);
