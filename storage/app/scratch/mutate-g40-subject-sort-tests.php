<?php

/**
 * Mutant harness for the subjects catalogue sort (§28.3 G-40's fourteenth table).
 *
 * Five legs, one per promise the screen makes, each requiring the case that owns it to print `1 failed`. The
 * catalogue default gets its own leg because it is the thing this screen added to the mechanism's usage: the
 * helper answers `desc` when no direction arrives, which is right for a queue of newest-first records and wrong
 * for a code list, so the handler states `asc` on its own behalf — and only a leg that puts it back proves a
 * case would notice.
 *
 * Usage: php storage/app/scratch/mutate-g40-subject-sort-tests.php
 */
$root = dirname(__DIR__, 3);
chdir($root);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
if (! is_file($php)) {
    fwrite(STDERR, "REFUSED: no PHP at {$php}\n");

    exit(1);
}

$controller = $root.'/app/Http/Controllers/Admin/ReferenceDataController.php';
$test = 'tests/Feature/Sorting/SubjectListSortTest.php';
if (! is_file($controller) || ! is_file($root.'/'.$test)) {
    fwrite(STDERR, "REFUSED: the handler or its case file is missing.\n");

    exit(1);
}

$original = (string) file_get_contents($controller);
$hash0 = hash('sha256', $original);

$restore = function () use ($controller, $original, $hash0): void {
    $written = false;
    foreach ([0, 400_000, 1_200_000] as $wait) {
        if ($wait > 0) {
            usleep($wait);
        }
        $written = @file_put_contents($controller, $original);
        if ($written === strlen($original)) {
            break;
        }
    }
    if ($written !== strlen($original) || hash('sha256', (string) file_get_contents($controller)) !== $hash0) {
        fwrite(STDERR, "REFUSED: could not restore the handler, so the tree may still hold a planted defect.\n"
            ."  Repair it with: git checkout -- app/Http/Controllers/Admin/ReferenceDataController.php\n");

        exit(1);
    }
};
register_shutdown_function($restore);

$legs = [
    [
        'name' => 'order-applied-to-another-builder',
        'from' => "        \$sort = ListSort::apply(\n            \$query,",
        'to' => "        \$sort = ListSort::apply(\n            Subjects::query(),",
        'case' => 'the_title_column_orders_by_the_title_and_not_by_the_record_key',
        'promise' => 'the order lands on the builder the desk is paged from',
    ],
    [
        'name' => 'catalogue-default-reverted-to-descending',
        'from' => "(string) \$request->input('direction', 'asc'),",
        'to' => "(string) \$request->input('direction', ''),",
        'case' => 'the_catalogue_opens_on_the_code_ascending_because_it_is_read_not_worked',
        'promise' => 'a catalogue states its own ascending default beside its fallback column',
    ],
    [
        'name' => 'enum-column-withheld-again',
        'from' => "            'subjectType' => ['column' => 'subjectType'],\n",
        'to' => '',
        'case' => 'an_enum_column_is_offered_and_orders_the_way_this_connection_orders_it',
        'promise' => 'an ENUM column carries a header under the owner ruling of 2026-10-31',
    ],
    [
        'name' => 'derived-column-offered',
        'from' => "            'subjectId' => ['column' => 'subjectId'],\n        ];",
        'to' => "            'subjectId' => ['column' => 'subjectId'],\n            'totalUnits' => ['column' => 'lectureUnits'],\n        ];",
        'case' => 'a_derived_column_offers_no_header_and_an_unlisted_key_falls_back_quietly',
        'promise' => 'a header appears only for a column this query can order as painted',
    ],
    [
        'name' => 'sort-never-echoed',
        'from' => "                'sort' => \$sort['column'],\n",
        'to' => '',
        'case' => 'a_filter_and_an_order_are_both_echoed_so_neither_control_resets',
        'promise' => 'the order the handler applied is reported back inside filters',
    ],
];

function runCase(string $php, string $test, string $case): array
{
    $cmd = escapeshellarg($php).' artisan test '.escapeshellarg($test)
        .($case === '' ? '' : ' --filter='.escapeshellarg($case)).' 2>&1';
    $out = (string) preg_replace('/\e\[[0-9;]*m/', '', (string) shell_exec($cmd));

    return [preg_match('/Tests:\s+\d+ failed/', $out) === 1, preg_match('/Tests:\s/', $out) === 1, $out];
}

echo "control: the class must be green before anything is planted\n";
[$failed, $selected, $out] = runCase($php, $test, '');
if (! $selected || $failed) {
    fwrite(STDERR, "REFUSED: the control is not green, so no verdict below would mean anything.\n".substr($out, 0, 400)."\n");
    exit(1);
}
echo "  ok   control green\n";

$bad = 0;
$needleCounts = [];
foreach ($legs as $leg) {
    $n = substr_count($original, $leg['from']);
    $needleCounts[$leg['name']] = $n;
    if ($n !== 1) {
        printf("  BAD  %-42s needle occurs %d time(s), wanted 1 — the handler moved and this leg proves nothing\n", $leg['name'], $n);
        $bad++;

        continue;
    }
    $mutated = str_replace($leg['from'], $leg['to'], $original);
    if ($mutated === $original) {
        printf("  BAD  %-42s MUTANT DID NOT APPLY — the replacement changed no bytes\n", $leg['name']);
        $bad++;

        continue;
    }
    $written = @file_put_contents($controller, $mutated);
    if ($written !== strlen($mutated)) {
        printf("  BAD  %-42s PLANT FAILED — the write returned %s, so this leg measured an untouched handler\n", $leg['name'], var_export($written, true));
        $bad++;
        $restore();

        continue;
    }
    exec(escapeshellarg($php).' -l '.escapeshellarg($controller).' 2>&1', $lint, $lintRc);
    if ($lintRc !== 0) {
        printf("  BAD  %-42s the planted bytes do not parse — that is not a killed defect\n", $leg['name']);
        $bad++;
        $restore();
        $lint = [];

        continue;
    }
    $lint = [];
    [$caseFailed, $caseSelected] = runCase($php, $test, $leg['case']);
    if (! $caseSelected) {
        printf("  BAD  %-42s --filter selected no test\n", $leg['name']);
        $bad++;
    } elseif ($caseFailed) {
        printf("  ok   %-42s killed by %s\n", $leg['name'], $leg['case']);
    } else {
        printf("  BAD  %-42s SURVIVED — %s stayed green, so nothing reads that promise\n", $leg['name'], $leg['case']);
        $bad++;
    }
    $restore();
}

// The tree check is the snapshot's hash, not `git status`: this handler carries the batch's own uncommitted
// work, so demanding a clean git entry refused a run that had in fact restored every byte — a guard that
// reports the tree dirty when it is not is the same lie as one that calls it clean when it is not.
if (hash('sha256', (string) file_get_contents($controller)) !== $hash0) {
    fwrite(STDERR, "REFUSED: the handler is not the snapshot's bytes after the run; restore it with git checkout and re-run.\n");
    exit(1);
}

[, $again, $after] = runCase($php, $test, '');
if (preg_match('/Tests:\s+\d+ failed/', $after) === 1) {
    fwrite(STDERR, "REFUSED: the trailing control is not green — this run left the handler or the tree wrong.\n");
    exit(1);
}
echo "  ok   trailing control green, handler restored by hash\n";

printf("%s ALL KILLED: %d leg(s), %d bad\n", $bad === 0 ? 'ok  ' : 'FAIL', count($legs), $bad);
exit($bad === 0 ? 0 : 1);
