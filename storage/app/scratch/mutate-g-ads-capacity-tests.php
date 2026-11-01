<?php

/**
 * Prove `tests/Feature/Blocking/BlockCapacityAtomicityTest.php` — one leg per promise the capacity fix makes.
 *
 * Each leg plants the retired shape in the real file, runs the ONE case that owns that promise, and requires it to
 * report a failure; then restores by hash before the next leg. A leg whose needle does not match exactly once is
 * BAD (the harness's own age), and a case that stays green over the planted defect is SURVIVED (a promise no guard
 * keeps). The control runs green before and after, so a red control is read as a broken run rather than a result.
 *
 * Usage: php storage/app/scratch/mutate-g-ads-capacity-tests.php [--only=leg-name]
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';

$controller = $root.'/app/Http/Controllers/Blocking/BlockingController.php';
$helper = $root.'/app/Support/RetryOnDeadlock.php';
$audit = $root.'/app/Console/Commands/AuditRecordStates.php';
$test = $root.'/tests/Feature/Blocking/BlockCapacityAtomicityTest.php';

$only = null;
foreach (array_slice($GLOBALS['argv'], 1) as $a) {
    if (preg_match('/^--only=(.+)$/', $a, $m)) {
        $only = $m[1];
    } else {
        fwrite(STDERR, 'REFUSED: this harness does not understand '.escapeshellarg($a).".\n");
        exit(1);
    }
}

/** @var array<string,array{file:string,from:string,to:string,case:string}> $legs */
$legs = [
    'block-lock-hoisted-out-of-the-transaction' => [
        'file' => $controller,
        'from' => <<<'PHP'
        RetryOnDeadlock::run(function () use ($block, $schedule, $requested, $requestedCount, &$assigned) {
            DB::transaction(function () use ($block, $schedule, $requested, $requestedCount, &$assigned) {
                Blocks::where('blockId', $block->blockId)->lockForUpdate()->firstOrFail();
PHP,
        'to' => <<<'PHP'
        Blocks::where('blockId', $block->blockId)->lockForUpdate()->firstOrFail();
        RetryOnDeadlock::run(function () use ($block, $schedule, $requested, $requestedCount, &$assigned) {
            DB::transaction(function () use ($block, $schedule, $requested, $requestedCount, &$assigned) {
PHP,
        'case' => 'the_capacity_count_is_taken_inside_the_transaction_after_the_block_is_locked',
    ],
    'room-row-not-locked' => [
        'file' => $controller,
        'from' => "Rooms::where('roomId', \$schedule->roomId)->lockForUpdate()->first();",
        'to' => "Rooms::where('roomId', \$schedule->roomId)->first();",
        'case' => 'the_capacity_count_is_taken_inside_the_transaction_after_the_block_is_locked',
    ],
    'requested-ids-not-sorted' => [
        'file' => $controller,
        'from' => "        sort(\$requested);\n",
        'to' => '',
        'case' => 'the_requested_ids_are_deduplicated_and_sorted_before_the_transaction_opens',
    ],
    'dropped-rows-consume-a-seat' => [
        'file' => $controller,
        'from' => <<<'PHP'
                $currentEnrolled = Enrolledsubjects::where('blockId', $block->blockId)
                    ->where('status', '!=', EnrolledSubjectStatus::Dropped->value)
                    ->distinct('enrollmentId')
PHP,
        'to' => <<<'PHP'
                $currentEnrolled = Enrolledsubjects::where('blockId', $block->blockId)
                    ->distinct('enrollmentId')
PHP,
        'case' => 'a_dropped_subject_line_does_not_consume_a_seat',
    ],
    'retry-gave-up-on-the-lock-wait' => [
        'file' => $helper,
        'from' => 'return $code === self::DEADLOCK || $code === self::LOCK_WAIT_TIMEOUT;',
        'to' => 'return $code === self::DEADLOCK;',
        'case' => 'the_helper_retries_a_deadlock_and_a_lock_wait_and_nothing_else',
    ],
    'retry-swallowed-every-driver-refusal' => [
        'file' => $helper,
        'from' => 'return $code === self::DEADLOCK || $code === self::LOCK_WAIT_TIMEOUT;',
        'to' => 'return true;',
        'case' => 'a_duplicate_key_is_never_retried_and_reaches_the_desk_unchanged',
    ],
    'retry-stopped-saying-nothing-was-saved' => [
        'file' => $helper,
        // Nowdoc, not a double-quoted string: the needle contains `.$attempts.` and PHP interpolates that away,
        // which is how this leg came to match zero bytes on its first run.
        'from' => <<<'PHP'
.$attempts.' attempts could not get past it. Nothing was saved — please try again.',
PHP,
        'to' => <<<'PHP'
.$attempts.' attempts could not get past it. Please try again.',
PHP,
        'case' => 'exhausting_the_attempts_refuses_with_a_message_that_says_nothing_was_saved',
    ],
    'act-stopped-retrying-at-all' => [
        'file' => $controller,
        'from' => 'RetryOnDeadlock::run(function () use ($block, $schedule, $requested, $requestedCount, &$assigned) {',
        'to' => '(function () use ($block, $schedule, $requested, $requestedCount, &$assigned) {',
        'case' => 'the_capacity_count_is_taken_inside_the_transaction_after_the_block_is_locked',
    ],
    'audit-counted-a-released-seat-as-held' => [
        'file' => $audit,
        // Only the audit's own WHERE, anchored on the join that is unique to this rule.
        'from' => "            ->join('blocks as b', 'b.blockId', '=', 'es.blockId')\n            ->where('es.status', '!=', EnrolledSubjectStatus::Dropped->value)",
        'to' => "            ->join('blocks as b', 'b.blockId', '=', 'es.blockId')",
        'case' => 'the_audit_names_a_block_holding_more_students_than_it_declares',
    ],
    'audit-compared-with-the-wrong-side-of-the-boundary' => [
        'file' => $audit,
        'from' => "->havingRaw('count(distinct es.enrollmentId) > b.maxStudents')",
        'to' => "->havingRaw('count(distinct es.enrollmentId) >= b.maxStudents')",
        'case' => 'the_audit_names_a_block_holding_more_students_than_it_declares',
    ],
];

$snapshot = [];
foreach ([$controller, $helper, $audit] as $f) {
    $snapshot[$f] = (string) file_get_contents($f);
}
$restore = static function () use ($snapshot): void {
    foreach ($snapshot as $f => $bytes) {
        if (sha1((string) file_get_contents($f)) !== sha1($bytes)) {
            file_put_contents($f, $bytes);
        }
    }
};
register_shutdown_function($restore);

/** Run one Pest case by method name and report what the run actually said. */
$runCase = static function (string $method) use ($php, $root, $test): array {
    $cmd = sprintf('cd %s && %s artisan test %s --filter=%s 2>&1',
        escapeshellarg($root), escapeshellarg($php), escapeshellarg($test), escapeshellarg($method));
    $out = (string) shell_exec($cmd);
    if (preg_match('/\e\[[0-9;]*m/', $out)) {
        $out = preg_replace('/\e\[[0-9;]*m/', '', $out) ?? $out;
    }
    // A red run prints `Tests: 1 failed` with no `passed` word at all, so the tally has to be read in both
    // directions — a regex that requires "passed" reports every killed leg as BAD.
    $failed = preg_match('/Tests:.*?(\d+)\s+failed/s', $out, $mf) ? (int) $mf[1] : 0;
    $passed = preg_match('/Tests:.*?(\d+)\s+passed/s', $out, $mp) ? (int) $mp[1] : 0;
    if (! str_contains($out, 'Tests:') && ! str_contains($out, 'No tests found')) {
        return ['unknown' => true, 'out' => $out];
    }

    return ['passed' => $passed, 'failed' => $failed,
        'none' => str_contains($out, 'No tests found'), 'out' => $out];
};

echo "=== control: every case green on the tree as it stands ===\n";
$control = 0;
foreach ($legs as $leg) {
    $r = $runCase($leg['case']);
    if (! empty($r['unknown']) || ! empty($r['none']) || ($r['failed'] ?? 1) !== 0) {
        echo "REFUSED: the control case `{$leg['case']}` is not green — ".trim(substr($r['out'], -300))."\n";
        exit(1);
    }
    $control++;
}
printf("control green: %d distinct case(s) own these %d promises.\n\n", count(array_unique(array_column($legs, 'case'))), count($legs));

$bad = 0;
$survived = 0;
foreach ($legs as $name => $leg) {
    if ($only !== null && $name !== $only) {
        echo "  SKIP  {$name}\n";

        continue;
    }
    $file = $leg['file'];
    $bytes = (string) file_get_contents($file);
    $hits = substr_count($bytes, $leg['from']);
    if ($hits !== 1) {
        echo "  BAD      {$name} — anchor matched {$hits} time(s), expected exactly 1.\n";
        $bad++;

        continue;
    }
    $planted = str_replace($leg['from'], $leg['to'], $bytes);
    if ($planted === $bytes) {
        echo "  BAD      {$name} — the plant changed no byte.\n";
        $bad++;

        continue;
    }
    file_put_contents($file, $planted);
    if (sha1_file($file) !== sha1($planted)) {
        echo "  BAD      {$name} — the plant did not land on disk.\n";
        $bad++;
        file_put_contents($file, $bytes);

        continue;
    }

    $r = $runCase($leg['case']);
    // Restore before judging so a slow leg cannot poison the next one, then check what the run said.
    file_put_contents($file, $bytes);

    if (! empty($r['unknown'])) {
        echo "  BAD      {$name} — the suite printed no Tests: line.\n";
        $bad++;
    } elseif (! empty($r['none'])) {
        echo "  BAD      {$name} — --filter selected no test; a harness that reads that as a kill is a harness that sleeps.\n";
        $bad++;
    } elseif (($r['failed'] ?? 0) > 0) {
        echo "  KILLED   {$name} ({$r['failed']} failed on `{$leg['case']}`)\n";
    } else {
        echo "  SURVIVED {$name} — the promise was planted away and `{$leg['case']}` stayed green.\n";
        $survived++;
    }
}

$after = 0;
foreach ($legs as $leg) {
    $r = $runCase($leg['case']);
    if (! empty($r['unknown']) || ($r['failed'] ?? 1) !== 0) {
        echo "REFUSED: the final control is not green (`{$leg['case']}`), so this run measured nothing.\n";
        exit(1);
    }
    $after++;
}
printf("\nfinal control green again: %d case(s).\n", $after);

foreach ([$controller, $helper, $audit] as $f) {
    if (sha1_file($f) !== sha1($snapshot[$f])) {
        echo "REFUSED: ".basename($f)." is not the bytes the harness found.\n";
        exit(1);
    }
}
echo 'tree verified by hash: all '.count($snapshot)." files back exactly as found.\n";

echo "\n".($bad + $survived === 0
    ? 'ALL KILLED: '.count($legs)." leg(s), 0 bad\n"
    : 'PROBLEMS: '.$bad.' bad, '.$survived.' survived (of '.count($legs)." legs)\n");
exit($bad + $survived === 0 ? 0 : 1);
