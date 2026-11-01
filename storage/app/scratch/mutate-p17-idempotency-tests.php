<?php

/**
 * Falsifier for ADS guide problem 17 (the enrollment request key).
 *
 * Every leg plants one way the guarantee could stop meaning anything, and requires the case that NAMES that promise
 * to go red. A leg that cannot fail is decoration, so each plant is verified against the exact bytes the leg claims
 * to have written, and a needle that matches nothing is reported BAD rather than passed.
 *
 * The five promises, and why each needs its own plant:
 *   - a replay answers the record that exists          → blind the read that finds it
 *   - the INDEX is the guard, not the php pre-check    → make the migration build a plain index
 *   - the key actually reaches the column               → take `requestKey` out of $fillable (mass assignment drops
 *                                                        it silently: no error, no key, no dedupe — the defect this
 *                                                        repository has met from three other directions)
 *   - one key names one act                            → make the mismatched replay return the other student's row
 *   - the desk's form carries a key at all              → remove it from the page's payload
 *
 * Usage: php storage/app/scratch/mutate-p17-idempotency-tests.php
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
chdir($root);

$testFile = 'tests/Feature/Enrollment/EnrollmentRequestKeyTest.php';
$issuer = 'app/Services/EnrollmentIssuer.php';
$migration = 'database/migrations/2026_11_01_1800000000_add_request_key_to_enrollments_table.php';
$model = 'app/Models/Enrollments.php';
$page = 'resources/js/Pages/Evaluation/Index.jsx';

$targets = [$issuer, $migration, $model, $page];
$original = [];
foreach ($targets as $t) {
    $bytes = @file_get_contents($root.'/'.$t);
    if ($bytes === false) {
        fwrite(STDERR, "REFUSED: cannot read {$t}.\n");
        exit(1);
    }
    $original[$t] = $bytes;
}

register_shutdown_function(function () use ($root, $original) {
    foreach ($original as $rel => $bytes) {
        $path = $root.'/'.$rel;
        if ((string) @file_get_contents($path) !== $bytes) {
            file_put_contents($path, $bytes);
        }
    }
});

/** Run one case of the class; return [rc, flatOutput, passedTally]. */
$run = function (string $method) use ($root, $php, $testFile): array {
    // cmd.exe does not strip the single quotes escapeshellarg() adds, so `--filter ''` selects nothing and a leg
    // that reads "No tests found" as a kill is a leg that cannot fail. The control runs the whole file.
    $cmd = sprintf('cd /d %s && %s artisan test %s%s 2>&1',
        escapeshellarg($root), escapeshellarg($php), escapeshellarg($testFile),
        $method === '' ? '' : ' --filter '.escapeshellarg($method));

    // The first run of this file silently built `artisan test ''` because $testFile was not captured by the
    // closure: the harness then spent twenty minutes on the WHOLE suite and printed a tally that looked like a
    // delivered run. A command whose target vanished is a broken instrument, so the target is asserted here.
    if (! str_contains($cmd, 'EnrollmentRequestKeyTest.php')) {
        throw new RuntimeException('the run lost its target file; refusing to measure something broader by accident');
    }

    $out = preg_replace('/\e\[[0-9;]*m/', '', (string) shell_exec($cmd));
    if (! preg_match('/Tests:\s+([^\r\n]+)/', $out, $m)) {
        return [-1, $out, 'no-tally'];
    }

    return [str_contains($m[1], 'failed') ? 1 : 0, $out, trim($m[1])];
};

/** Write a plant, asserting the needle matched exactly once before and the replacement is really on disk. */
$plant = function (string $rel, string $from, string $to) use ($root, $original): void {
    if (substr_count($original[$rel], $from) !== 1) {
        throw new RuntimeException("anchor `".substr($from, 0, 60)."…` occurs "
            .substr_count($original[$rel], $from).' time(s) in '.$rel.', expected exactly 1');
    }
    $written = file_put_contents($root.'/'.$rel, str_replace($from, $to, $original[$rel]));
    if ($written === false) {
        throw new RuntimeException('the plant could not be written to '.$rel);
    }
    if (! str_contains((string) file_get_contents($root.'/'.$rel), $to)) {
        throw new RuntimeException('the plant is not on disk in '.$rel.' after the write');
    }
};

$legs = [];
$say = function (string $name, bool $ok, string $detail) use (&$legs): void {
    $legs[] = [$name, $ok, $detail];
    printf("  %s %-46s %s\n", $ok ? 'ok  ' : 'BAD ', $name, $detail);
};

/** Write a second plant on top of one already applied, for a leg that needs two defects at once. */
$plantMore = function (string $rel, string $from, string $to) use ($root): void {
    $current = (string) file_get_contents($root.'/'.$rel);
    if (substr_count($current, $from) !== 1) {
        throw new RuntimeException('second anchor `'.substr($from, 0, 50).'…` occurs '
            .substr_count($current, $from).' time(s) in the current '.$rel.', expected 1');
    }
    if (@file_put_contents($root.'/'.$rel, str_replace($from, $to, $current)) === false) {
        throw new RuntimeException('the second plant could not be written to '.$rel);
    }
};

/** Restore every target explicitly, so a final comparison measures the bytes rather than a pending shutdown hook. */
$restore = function () use ($root, $original): array {
    $drift = [];
    foreach ($original as $rel => $bytes) {
        $path = $root.'/'.$rel;
        if ((string) @file_get_contents($path) !== $bytes && @file_put_contents($path, $bytes) === false) {
            $drift[] = $rel;
        }
    }

    return $drift;
};

// ── 1. control: the whole class green before anything is planted ──────────────────────────────────────────
[$rc, $out, $tally] = $run('');
$say('control-green', $rc === 0 && $tally !== 'no-tally', "rc={$rc} Tests: {$tally}");
if ($rc !== 0) {
    foreach ($legs as [$n, $ok]) {
        if (! $ok) {
            fwrite(STDERR, "REFUSED: the control is not green, so no plant below can be read as a kill.\n");
            exit(1);
        }
    }
}

// ── 2. the php dedupe removed entirely: the server refuses, and the desk gets an error instead of its record ─
// Blinding only the read is NOT a kill: the catch then re-reads through the index and still answers correctly, so
// the first cut of this leg reported the guard as surviving when what it found was defence in depth. Both halves
// have to go before the promise is unheld.
$plant($issuer, '$already = Enrollments::where(\'requestKey\', $requestKey)->first();',
    '$already = null /* MUTANT */;');
$plantMore($issuer, '$winner = Enrollments::where(\'requestKey\', $requestKey)->first();',
    '$winner = null /* MUTANT */;');
[$rc, $out, $tally] = $run('a_repeated_request_key_answers_the_enrollment_the_first_request_created');
$say('php-dedupe-removed-goes-red', $rc === 1, "rc={$rc} Tests: {$tally}");

// ── 3. the migration builds a plain index: the server stops refusing a duplicate key ─────────────────────
$plant($migration, "\$table->unique('requestKey', self::INDEX);", "\$table->index('requestKey', self::INDEX);");
[$rc, $out, $tally] = $run('the_index_refuses_a_second_row_carrying_one_key_on_this_driver');
$say('index-not-unique-is-refused-by-the-case', $rc === 1, "rc={$rc} Tests: {$tally}");

// ── 4. the key is not mass-assignable: it never reaches the column, and nothing errors ──────────────────
$plant($model, "'formSignedDate', 'requestKey']", "'formSignedDate']");
[$rc, $out, $tally] = $run('a_repeated_request_key_answers_the_enrollment_the_first_request_created');
$say('key-dropped-by-mass-assignment-goes-red', $rc === 1, "rc={$rc} Tests: {$tally}");

// ── 5. one key may not name two acts: the mismatch returns the other student's record ───────────────────
$plant($issuer, 'if ((int) $already->studentId !== (int) ($attributes[\'studentId\'] ?? 0)',
    'if (false && (int) $already->studentId !== (int) ($attributes[\'studentId\'] ?? 0)');
[$rc, $out, $tally] = $run('one_key_may_not_name_two_different_acts');
$say('cross-act-replay-guarded', $rc === 1, "rc={$rc} Tests: {$tally}");

// ── 6. the desk page stops sending a key: the wire promise has no sender ────────────────────────────────
$plant($page, 'transform((data) => ({ ...data, requestKey: issueKey }))', 'transform((data) => ({ ...data }))');
[$rc, $out, $tally] = $run('the_desk_page_sends_a_request_key_with_the_issue_act');
$say($tally === 'no-tally' ? 'page-leg-needs-its-own-case' : 'page-omits-key-goes-red',
    $rc === 1 && $tally !== 'no-tally', "rc={$rc} Tests: {$tally}");

// ── 7. a filter that selects nothing is never read as a kill ────────────────────────────────────────────
[$rc, , $tally] = $run('this_case_does_not_exist_at_all');
$say('bogus-filter-is-not-green', $tally === 'no-tally' || str_contains($tally, 'No tests'), "Tests: {$tally}");

// ── 8. the tree is exactly the bytes this run opened, measured AFTER an explicit restore ─────────────────
// The first cut compared here and reported every target as drifted, because the restore is a shutdown hook that
// had not run yet — a leg that measures the instrument's own ordering, not the tree. Restore, then compare.
$failedRestore = $restore();
$drift = [];
foreach ($original as $rel => $bytes) {
    if ((string) file_get_contents($root.'/'.$rel) !== $bytes) {
        $drift[] = $rel;
    }
}
$say('tree-restored-by-hash', $drift === [] && $failedRestore === [], $drift === []
    ? 'all four files byte-identical after the explicit restore' : 'drift: '.implode(', ', $drift));

$bad = count(array_filter($legs, fn ($l) => ! $l[1]));
printf("\n%d leg(s), %d bad\n", count($legs), $bad);
exit($bad === 0 ? 0 : 1);
