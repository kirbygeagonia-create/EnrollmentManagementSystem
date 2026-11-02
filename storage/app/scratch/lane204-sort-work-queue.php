<?php

/**
 * The work queue the registers owe, written from the instruments rather than from memory.
 *
 * Task #60's page list ages with every table wired, so this file derives it from
 * `lane140-measure-wired-screens.php`'s own `owed pages` block and refuses when that block is empty. The two
 * groups are separated by a MEASUREMENT, not an opinion: `lane90-probe-pagination.php` judges each wired page's
 * rows expression, and a page whose rows come from a collection or a browser mapping is not a queue the query can
 * order — it needs either a sort inside the same action or a register sentence naming it out by decision, which is
 * what §28.3 already does for the blocking card.
 *
 * Refusals, each named: an empty owed list, a wired/owed pair that no longer adds to the denominator the register
 * carries, and any unknown flag by name.
 *
 * Usage: php storage/app/scratch/lane204-sort-work-queue.php
 */
$root = dirname(__DIR__, 3);
chdir($root);
$php = PHP_BINARY;

foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
    fwrite(STDERR, "REFUSED: this tool reads no arguments; it was given [{$arg}].\n");
    exit(1);
}
$fail = static function (string $m): never {
    fwrite(STDERR, "REFUSED: {$m}\n");
    exit(1);
};

$out = [];
$code = 0;
exec('"'.$php.'" '.escapeshellarg($root.'/storage/app/scratch/lane140-measure-wired-screens.php').' 2>&1', $out, $code);
$body = preg_replace('/\e\[[0-9;]*m/', '', implode("\n", $out));
if ($code !== 0) {
    $fail("lane140 exited {$code} — its own reading is the queue's source, so nothing is printed over a refusal:\n"
        .mb_substr($body, -400));
}

// SCREEN reading, then the two lists it partitions.
if (preg_match('/pages that render the shared table: (\d+)/', $body, $dm) !== 1) {
    $fail('lane140 printed no denominator for the shared table, so the population cannot be weighed.');
}
$denominator = (int) $dm[1];
if (preg_match('/^SCREEN reading.*?: (\d+)$/m', $body, $sm) !== 1) {
    $fail('lane140 printed no SCREEN wired reading.');
}
$wired = (int) $sm[1];

$owed = [];
if (preg_match('/owed pages \(\d+\):\n((?:[ \t]+\S.*\n?)*)/', $body, $om) === 1) {
    foreach (preg_split('/\n/', trim($om[1])) as $line) {
        $page = trim($line);
        if ($page !== '') {
            $owed[] = $page;
        }
    }
}
if ($owed === []) {
    $fail('the owed list is empty while the wired count is '.$wired.' of '.$denominator
        .' — either the reader broke or the register is already closed, and this tool may not guess which.');
}
if ($wired + count($owed) !== $denominator) {
    $fail("wired {$wired} + owed ".count($owed).' = '.($wired + count($owed)).", which is not the denominator {$denominator} — ".
        'two units are being added together.');
}

// Which owed pages does the shared table already serve, page-level? Re-read lane90 rather than recall its buckets.
$l90 = [];
exec('"'.$php.'" '.escapeshellarg($root.'/storage/app/scratch/lane90-probe-pagination.php').' 2>&1', $l90, $l90code);
$l90body = preg_replace('/\e\[[0-9;]*m/', '', implode("\n", $l90));
if ($l90code !== 0) {
    echo "NOTE: lane90 exited {$l90code}, so the collection-vs-paginator split is NOT weighed this run; ".
        "every owed page is listed as WIREABLE until it is.\n";
    $collections = [];
} else {
    preg_match('/PAGINATED (\d+), COLLECTION (\d+), CLIENT-DERIVED (\d+)/', $l90body, $lg);
    preg_match_all('/COLLECTION\s+\S+\s+(\S+\.jsx)/i', $l90body, $cm);
    preg_match_all('/CLIENT-DERIVED\s+\S+\s+(\S+\.jsx)/i', $l90body, $dm2);
    $collections = array_values(array_unique([...($cm[1] ?? []), ...($dm2[1] ?? [])]));
    // A reader that ran clean and named nothing is the fake-split this document refuses everywhere: every owed page
    // would be filed WIREABLE because the collector could not see the other bucket, not because the tree says so.
    if ($collections === []) {
        echo 'NOTE: lane90 exited 0 but no COLLECTION or CLIENT-DERIVED page was named, so the split below is NOT '.
            "weighed — treat every page as unclassified until the row pattern matches the tool's own print.\n\n";
    }
}

$base = static fn (string $p): string => str_replace('resources/js/Pages/', '', $p);
$wireable = [];
$decide = [];
foreach ($owed as $p) {
    $short = $base($p);
    $isCollection = false;
    foreach ($collections as $c) {
        if (str_contains(str_replace('\\', '/', $c), $short)) {
            $isCollection = true;
            break;
        }
    }
    if ($isCollection) {
        $decide[] = $short;
    } else {
        $wireable[] = $short;
    }
}

printf("sortable-table queue, read %s from the tree\n", gmdate('Y-m-d'));
printf("population: %d pages render the shared table; %d are wired; %d owed (the sum is the denominator, asserted)\n\n",
    $denominator, $wired, count($owed));

printf("WIREABLE with the standard set (%d)\n", count($wireable));
foreach ($wireable as $p) {
    echo '  '.$p."\n";
}
printf("\nNEEDS A DECISION FIRST — rows built in PHP over a prop, so no ORDER BY reaches them (%d)\n", count($decide));
foreach ($decide as $p) {
    echo '  '.$p."\n";
}
if ($decide === []) {
    echo "  (none named by lane90 this run — see the NOTE above if it printed one)\n";
}

echo "\nThe standard set each wireable page owes: allow-list read in the action, both keys echoed in `filters`,\n";
echo "`sort.available` asserted EXACTLY wherever a painted column is derived, one asserting case per promise, one\n";
echo "mutant harness per screen, then `lane140 --check-matrix --check-docx` to re-take the registers.\n";
