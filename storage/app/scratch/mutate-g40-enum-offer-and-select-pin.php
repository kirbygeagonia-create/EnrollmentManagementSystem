<?php

/**
 * Proves the SELECT pin and the three newly offered ENUM headers can fail (lane 107, G-40's ruling half).
 *
 * Two promises are at stake, and only one of them was in the plan.
 *
 *   The ruling: a desk may sort an ENUM column, because on the school's own server the order is the declaration the
 *   school wrote into the migration. Withheld again, the header must stop being offered AND the sequence case must
 *   stop passing — which is why each screen's leg renames the allow-list KEY rather than deleting a line: a key the
 *   handler does not list falls back to the default order, so both the offered-set assertion and the sequence
 *   assertion move together.
 *
 *   The defect the audit found on the way: `ListSort` joined a relation to order a column, and a builder with no
 *   select list emits `select *`, which answered the hop's columns too. On the Assessment and Accounting queues a
 *   Name order joined `students` two hops away, so every fee-sheet row shipped the student's passwordHash, birthdate
 *   and contact number — fields `Students::$hidden` cannot withhold, because they were not riding on a Students
 *   model. Pinning the select to the base table is what closes it, so `pin-removed` plants the retired helper and
 *   requires each screen's own leak case to complain.
 *
 * `alias-qualified-on-a-hop` is the trap the controllers' comments name: ListSort obeys an already-qualified column
 * as written, so spelling `enrollments.studentType` on a hopped screen asks the query for a table it joined under an
 * alias — a 500 at the desk, which PHPUnit counts as an error rather than a failure. Both spellings are read.
 *
 * A leg refuses its whole run when its needle matches nothing: a mutant that cannot land is this instrument's age,
 * not a surviving defect. Every touched file is restored by sha1 in a shutdown hook, and the control is measured
 * green before anything is planted and once more at the end.
 *
 * Usage: php storage/app/scratch/mutate-g40-enum-offer-and-select-pin.php
 */
$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';

$sortFile = $root.'/app/Support/ListSort.php';
$assess = $root.'/app/Http/Controllers/Assessment/AssessmentController.php';
$acct = $root.'/app/Http/Controllers/Accounting/AccountingController.php';
$registrar = $root.'/app/Http/Controllers/Registrar/RegistrarController.php';

$targets = [$sortFile, $assess, $acct, $registrar];
$snapshots = [];
foreach ($targets as $path) {
    $bytes = (string) file_get_contents($path);
    if ($bytes === '') {
        fwrite(STDERR, "REFUSED: cannot read {$path}\n");
        exit(1);
    }
    $snapshots[$path] = ['bytes' => $bytes, 'sha1' => sha1($bytes)];
}

register_shutdown_function(static function () use ($snapshots): void {
    foreach ($snapshots as $path => $s) {
        if (sha1((string) file_get_contents($path)) !== $s['sha1']) {
            file_put_contents($path, $s['bytes']);
        }
    }
});

/** Run one method of one class and answer the verdict line PHPUnit printed. */
$run = static function (string $file, string $method) use ($root, $php): array {
    chdir($root);
    $out = (string) shell_exec(
        escapeshellarg($php).' artisan test '.escapeshellarg($file).' --filter='.$method.' 2>&1'
    );
    $out = (string) preg_replace('/\e\[[0-9;]*m/', '', $out);
    if (preg_match('/Tests:\s+([0-9]+) (passed|failed|errored|warning)/', $out, $m) !== 1) {
        return ['unread' => true, 'raw' => $out, 'line' => 'no Tests: line'];
    }

    return ['unread' => false, 'raw' => $out, 'line' => trim($m[0]), 'count' => (int) $m[1], 'word' => $m[2]];
};

$pinFrom = <<<'PHP'
            if ($query->getQuery()->getColumns() === []) {
                $query->select($query->getModel()->qualifyColumn('*'));
            }
PHP;
$pinTo = "            // mutant: the retired helper, which lets the hop's columns into the row";

$leakCase = 'an_order_that_joins_a_relation_ships_the_rows_own_columns_and_nothing_else';
$enumCaseAssess = 'the_enum_headers_reach_the_enrollment_through_the_same_hop_and_order_the_words_this_connection_holds';

$legs = [
    [
        'name' => 'control-green',
        'file' => 'tests/Feature/Sorting/AssessmentQueueSortTest.php',
        'case' => $enumCaseAssess,
        'expect' => 'passed',
    ],
    [
        'name' => 'pin-removed-assessment',
        'plant' => ['file' => $sortFile, 'from' => $pinFrom, 'to' => $pinTo],
        'file' => 'tests/Feature/Sorting/AssessmentQueueSortTest.php',
        'case' => $leakCase,
        'promise' => 'a joined order may not widen the row it lists',
    ],
    [
        'name' => 'pin-removed-accounting',
        'plant' => ['file' => $sortFile, 'from' => $pinFrom, 'to' => $pinTo],
        'file' => 'tests/Feature/Sorting/AccountingQueueSortTest.php',
        'case' => $leakCase,
        'promise' => 'the cashier queue is the same mechanism over the same two hops',
    ],
    [
        'name' => 'pin-removed-registrar',
        'plant' => ['file' => $sortFile, 'from' => $pinFrom, 'to' => $pinTo],
        'file' => 'tests/Feature/Registrar/RegistrarQueueSortTest.php',
        'case' => $leakCase,
        'promise' => 'even a one-hop join may not add the student\'s columns to an enrollment row',
    ],
    [
        'name' => 'alias-qualified-on-a-hop',
        'plant' => ['file' => $assess, 'from' => "'column' => 'studentType',", 'to' => "'column' => 'enrollments.studentType', // mutant: names a table the query aliased"],
        'file' => 'tests/Feature/Sorting/AssessmentQueueSortTest.php',
        'case' => $enumCaseAssess,
        'promise' => 'a bare column is qualified with the hop that holds it',
    ],
    [
        'name' => 'enum-key-withheld-assessment',
        'plant' => ['file' => $assess, 'from' => "'studentType' => [", 'to' => "'studentTypeRetired' => ["],
        'file' => 'tests/Feature/Sorting/AssessmentQueueSortTest.php',
        'case' => $enumCaseAssess,
        'promise' => 'the ruling is honoured, not tolerated: withdraw the key and the header stops ordering',
    ],
    [
        'name' => 'enum-key-withheld-accounting',
        'plant' => ['file' => $acct, 'from' => "'studentType' => [", 'to' => "'studentTypeRetired' => ["],
        'file' => 'tests/Feature/Sorting/AccountingQueueSortTest.php',
        'case' => 'the_enum_headers_order_the_words_this_connection_sorts_them_in',
        'promise' => 'the cashier queue offers the same two ENUM headers',
    ],
    [
        'name' => 'enum-key-withheld-registrar',
        'plant' => ['file' => $registrar, 'from' => "'studentType' => ['column' => 'enrollments.studentType'],", 'to' => "// mutant: the ENUM header withheld again"],
        'file' => 'tests/Feature/Registrar/RegistrarQueueSortTest.php',
        'case' => 'the_enum_headers_order_the_words_this_connection_sorts_them_in',
        'promise' => 'the Registrar queue offers Student Type over its own row',
    ],
    [
        'name' => 'control-green-again',
        'file' => 'tests/Feature/Sorting/AssessmentQueueSortTest.php',
        'case' => $enumCaseAssess,
        'expect' => 'passed',
    ],
];

$bad = 0;
foreach ($legs as $leg) {
    $planted = null;
    if (isset($leg['plant'])) {
        $bytes = (string) file_get_contents($leg['plant']['file']);
        $hits = substr_count($bytes, $leg['plant']['from']);
        if ($hits !== 1) {
            printf("BAD  %-28s needle matched %d time(s), wanted exactly 1\n", $leg['name'], $hits);
            $bad++;
            continue;
        }
        file_put_contents($leg['plant']['file'], str_replace($leg['plant']['from'], $leg['plant']['to'], $bytes));
        if (sha1((string) file_get_contents($leg['plant']['file'])) === $snapshots[$leg['plant']['file']]['sha1']) {
            printf("BAD  %-28s mutation left the file byte-identical\n", $leg['name']);
            $bad++;
            continue;
        }
        // Restore the moment the verdict is read. A plant that outlives its own leg makes the NEXT leg measure a
        // tree that already carries a defect: the first cut of this harness reported "needle matched 0" on its
        // second pin leg for exactly that reason, and its final control came back red over a clean tree.
        $planted = $leg['plant']['file'];
    }

    $r = $run($leg['file'], $leg['case']);
    unset($leg['plant']);

    if ($planted !== null) {
        file_put_contents($planted, $snapshots[$planted]['bytes']);
    }

    if ($r['unread'] === true) {
        printf("BAD  %-28s suite printed no verdict: %s\n", $leg['name'], substr($r['raw'], -160));
        $bad++;
        continue;
    }

    if (($leg['expect'] ?? 'failed') === 'passed') {
        if ($r['word'] === 'passed' && $r['count'] === 1) {
            printf("ok   %-28s %s\n", $leg['name'], $r['line']);
        } else {
            printf("BAD  %-28s control is not green: %s\n", $leg['name'], $r['line']);
            $bad++;
        }
        continue;
    }

    if ($r['word'] === 'failed' || $r['word'] === 'errored') {
        printf("ok   %-28s %s  (%s)\n", $leg['name'], $r['line'], $leg['promise']);
    } else {
        printf("SURVIVED  %-20s %s  — %s\n", $leg['name'], $r['line'], $leg['promise']);
        $bad++;
    }
}

echo $bad === 0
    ? sprintf("ALL KILLED: %d leg(s), 0 bad\n", count($legs))
    : sprintf("%d leg(s) bad over %d\n", $bad, count($legs));
exit($bad === 0 ? 0 : 1);
