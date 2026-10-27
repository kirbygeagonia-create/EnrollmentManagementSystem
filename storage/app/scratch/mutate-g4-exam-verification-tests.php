<?php

/**
 * Prove the G-4 examination-verification tests can fail.
 *
 * Prompt §7.2's confirmation is one column, one act, four readers and one write path that has
 * to forget its own signature. Thirteen green cases in `ExamResultVerificationTest`, two in
 * `ApprovalReadinessTest` and two in `RetentionGateTest` say nothing on their own: a case that
 * would also pass against the code it is meant to guard is decoration. So each leg here plants
 * exactly one defect in the working file, runs ONLY the case that names it, and requires that
 * case to report one failed test — then restores the bytes by hash before the next measurement.
 *
 * The defects are the promises the batch makes, one per promise, spread over the seven files the
 * rule lives in: the model's one `isOfficial()`, the two admission blockers, the Stage 2
 * prerequisite, the retention gate, the confirming write (its three columns, each alone), the two
 * refusals it owes (an unmarked paper, an already-adopted row), the re-record that clears the
 * signature, the roster that must state the state, the tile that must count the view, the route
 * the act needs, the policy that grants it, the right that reaches the desk, and the migration's
 * backfill. Their number is `count($cases) + 4` legs, printed by the run itself and never typed
 * into this file or into AGENTS.md.
 *
 * Three readings carried over from the lanes this one copies:
 *   - `exec()` answers only the LAST line, so the summary is read out of a captured file;
 *   - Pest colours its summary, so the ANSI sequences between "Tests:" and the figure are
 *     stripped before the tally is matched;
 *   - the no-op guard is `$from === $to`, not "the replacement already occurs": a SHRINK mutation
 *     (dropping a right out of a role's list) necessarily leaves a remainder that already occurs,
 *     and the post-write byte hash is the precise falsification.
 *
 * The controls' expected tallies are counted out of each test file's own `#[Test]` markers, never
 * typed here — a hard-coded figure goes quietly stale the day a case is added, and then every leg
 * is judged against a number that describes nothing.
 *
 * Usage: php storage/app/scratch/mutate-g4-exam-verification-tests.php
 */
$root = dirname(__DIR__, 3);
$php = getenv('EMS_PHP') ?: 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
chdir($root);

$testFiles = [
    'exam' => 'tests/Feature/Exam/ExamResultVerificationTest.php',
    'admission' => 'tests/Feature/Admission/ApprovalReadinessTest.php',
    'retention' => 'tests/Feature/Evaluation/RetentionGateTest.php',
];

$targets = [
    'examController' => $root.'/app/Http/Controllers/Exam/ExamController.php',
    'evaluationController' => $root.'/app/Http/Controllers/Evaluation/EvaluationController.php',
    'examModel' => $root.'/app/Models/Examresults.php',
    'examPolicy' => $root.'/app/Policies/ExamPolicy.php',
    'admissionPolicy' => $root.'/app/Policies/AdmissionPolicy.php',
    'rbacSeeder' => $root.'/database/seeders/RbacSeeder.php',
    'routes' => $root.'/routes/web.php',
    'migration' => $root.'/database/migrations/2026_10_26_1000000003_add_verification_to_examresults_table.php',
];

$original = [];
$hashes = [];
foreach ($targets as $key => $path) {
    $original[$key] = (string) file_get_contents($path);
    $hashes[$key] = hash('sha256', $original[$key]);
    if ($original[$key] === '') {
        fwrite(STDERR, "REFUSED: cannot read {$path}.\n");
        exit(1);
    }
}

$caseCounts = [];
foreach ($testFiles as $key => $relative) {
    $source = (string) file_get_contents($root.'/'.$relative);
    if ($source === '') {
        fwrite(STDERR, "REFUSED: cannot read {$relative}.\n");
        exit(1);
    }
    $caseCounts[$key] = substr_count($source, '#[Test]');
    if ($caseCounts[$key] < 1) {
        fwrite(STDERR, "REFUSED: {$relative} declares no #[Test] method, so there is nothing to judge.\n");
        exit(1);
    }
    $testFiles[$key] = [$relative, $source];
}

/** [label, target, from, to, test-file, method] */
$cases = [
    [
        'isOfficial-answers-for-every-row',
        'examModel',
        '        return $this->resultStatus === ExamStatus::Official;',
        '        return true;',
        'exam',
        'the_departmental_record_refuses_an_unconfirmed_pass_and_names_the_pending_confirmation',
    ],
    [
        'general-blocker-skips-the-confirmation',
        'admissionPolicy',
        '            } elseif (! $generalExam->isOfficial()) {',
        '            } elseif (false) {',
        'admission',
        'a_pass_the_registrar_has_not_confirmed_blocks_approval_and_names_that_desk',
    ],
    [
        'departmental-blocker-skips-the-confirmation',
        'admissionPolicy',
        '        } elseif (! $courseExam->isOfficial()) {',
        '        } elseif (false) {',
        'admission',
        'a_provisional_departmental_result_blocks_approval_although_the_general_paper_is_official',
    ],
    [
        'retention-gate-skips-the-confirmation',
        'evaluationController',
        '        if (! $retention->isOfficial()) {',
        '        if (false) {',
        'retention',
        'a_passing_retention_result_still_provisional_keeps_the_form_at_the_desk',
    ],
    [
        'confirm-leaves-the-row-provisional',
        'examController',
        "            'resultStatus' => ExamStatus::Official->value,\n            'verifiedBy' => \$request->user()->userId,",
        "            'resultStatus' => ExamStatus::Provisional->value,\n            'verifiedBy' => \$request->user()->userId,",
        'exam',
        'the_registrar_confirms_the_row_and_the_act_carries_that_desk_and_the_school_day',
    ],
    [
        'confirm-forgets-which-desk-signed',
        'examController',
        "            'verifiedBy' => \$request->user()->userId,",
        "            'verifiedBy' => null,",
        'exam',
        'the_registrar_confirms_the_row_and_the_act_carries_that_desk_and_the_school_day',
    ],
    [
        'confirm-forgets-the-day',
        'examController',
        "            'verifiedDate' => SchoolTime::today(),",
        "            'verifiedDate' => null,",
        'exam',
        'the_registrar_confirms_the_row_and_the_act_carries_that_desk_and_the_school_day',
    ],
    [
        'an_unmarked_paper_is_confirmed_anyway',
        'examController',
        '        if ($exam->examResult === null) {',
        '        if (false) {',
        'exam',
        'a_paper_nobody_scored_cannot_be_made_official',
    ],
    [
        'an_official_row_is_re_signed',
        'examController',
        '        if ($exam->isOfficial()) {',
        '        if (false) {',
        'exam',
        'a_row_already_official_is_not_re_signed_and_says_so',
    ],
    [
        'a_correction_keeps_the_old_adoption',
        'examController',
        "            'resultStatus' => ExamStatus::Provisional->value,\n            'verifiedBy' => null,\n            'verifiedDate' => null,",
        "            'resultStatus' => ExamStatus::Official->value,",
        'exam',
        're_recording_a_result_sends_it_back_to_provisional_and_drops_the_signature',
    ],
    [
        'a_correction_keeps_the_retention_signature',
        'evaluationController',
        "        \$retention->resultStatus = ExamStatus::Provisional->value;\n        \$retention->verifiedBy = null;\n        \$retention->verifiedDate = null;",
        '        // the corrected figure inherits whatever adoption the row already carried',
        'retention',
        'a_re_recorded_retention_mark_is_provisional_again_and_holds_no_signature',
    ],
    [
        'the_roster_calls_every_passer_adopted',
        'examController',
        "                'resultStatus' => \$result->resultStatus->value,",
        "                'resultStatus' => ExamStatus::Official->value,",
        'exam',
        'the_passer_roster_states_which_passers_the_registrar_has_not_adopted',
    ],
    [
        // NOT `->limit(20)`: a LIMIT on `select count(*)` is a no-op in SQL, and the first run
        // of this harness planted exactly that — the bytes changed, the hash guard passed, and
        // the case stayed green. Byte-identity is necessary and not sufficient; a mutation has
        // to change the VALUE the assertion reads. `forPage(2, 20)` is the defect lane 47 names:
        // an aggregate that inherits the page's limit and offset answers nothing.
        'the_pending_tile_inherits_the_page_limit',
        'examController',
        "        \$pendingCount = (int) (clone \$query)->reorder()",
        "        \$pendingCount = (int) (clone \$query)->reorder()->forPage(2, 20)",
        'exam',
        'the_pending_tile_counts_the_whole_view_rather_than_the_page',
    ],
    [
        'the_confirmation_act_has_no_route',
        'routes',
        "    Route::put('/exam/{exam}/verify', [ExamController::class, 'verify'])->name('exam.verify');",
        '    // (the confirm route retired by the mutant)',
        'exam',
        'the_registrar_confirms_the_row_and_the_act_carries_that_desk_and_the_school_day',
    ],
    [
        'any_desk_may_confirm',
        'examPolicy',
        "        return \$user->hasPermissionTo('exam.verify');",
        '        return true;',
        'exam',
        'the_desk_that_scored_the_paper_cannot_be_the_desk_that_adopts_it',
    ],
    [
        'the_confirming_desk_was_never_granted_the_right',
        'rbacSeeder',
        "            'exam.view', 'exam.verify',",
        "            'exam.view',",
        'exam',
        'the_confirming_desk_sees_every_row_and_is_offered_the_act',
    ],
    [
        'the_backfill_leaves_the_old_rows_unadopted',
        'migration',
        "            ->update(['resultStatus' => 'official']);",
        "            ->update(['resultStatus' => 'provisional']);",
        'exam',
        'the_migration_adopts_the_rows_already_on_file_without_inventing_a_signer',
    ],
];

// `--only=<leg>` re-runs one leg without the seven-minute whole walk. The controls still run,
// because a leg measured against a suite nobody re-checked green is not a measurement. A label
// that names no collected leg is refused: an ignored flag is how a mutating run looks clean.
$total = count($cases);
$only = [];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--only=')) {
        $only[] = substr($arg, strlen('--only='));

        continue;
    }

    fwrite(STDERR, "REFUSED: {$arg} is not a flag this harness reads; the only flag is --only=<leg label>.\n");
    exit(1);
}

if ($only !== []) {
    $unknown = array_diff($only, array_column($cases, 0));
    if ($unknown !== []) {
        fwrite(STDERR, 'REFUSED: --only names no leg this harness collected: '.implode(', ', $unknown).".\n");
        exit(1);
    }

    $cases = array_values(array_filter($cases, fn (array $c): bool => in_array($c[0], $only, true)));
    echo 'INCOMPLETE: '.count($cases)." of {$total} mutant leg(s) are running; the rest are withheld, not clean.\n";
}

$restore = static function () use ($targets, $hashes, $original): void {
    foreach ($targets as $key => $path) {
        if ((string) file_get_contents($path) !== $original[$key]) {
            file_put_contents($path, $original[$key]);
            if (hash('sha256', (string) file_get_contents($path)) !== $hashes[$key]) {
                fwrite(STDERR, "REFUSED: could not restore {$path}.\n");
                exit(1);
            }
        }
    }
};

register_shutdown_function($restore);

$run = static function (?string $filter, string $file) use ($php): array {
    $log = sys_get_temp_dir().'/g4mut-'.uniqid().'.txt';
    $command = sprintf(
        '%s artisan test %s%s > %s 2>&1',
        escapeshellarg($php),
        $file,
        $filter === null ? '' : ' --filter='.escapeshellarg($filter),
        escapeshellarg($log)
    );
    exec($command);

    $out = (string) file_get_contents($log);
    unlink($log);

    // Pest writes ANSI colour codes into captured output; without stripping them the
    // tally never matches and every leg reads as a broken instrument.
    $out = preg_replace('/\e\[[0-9;]*m/', '', $out);

    if (trim($out) === '') {
        return ['state' => 'NO-OUTPUT', 'word' => 'nothing', 'count' => 0, 'line' => 'the run wrote no bytes at all — the invocation is broken, not the suite'];
    }
    if (! preg_match('/Tests:\s+([0-9]+) (passed|failed|incomplete)/', $out, $m)) {
        return ['state' => 'BROKEN', 'word' => 'unread', 'count' => 0, 'line' => 'no Tests: line; tail was: '.substr(trim($out), -300)];
    }

    return [
        'state' => $m[2] === 'passed' ? 'GREEN' : ($m[2] === 'failed' ? 'RED' : 'ODD'),
        'word' => $m[2],
        'count' => (int) $m[1],
        'line' => trim($m[0]),
    ];
};

$failures = 0;

// The runner takes a PATH. The legs are written against the short key ('exam'), and handing
// that key to `artisan test` is what made the first run answer 23 legs of `Test file "exam"
// not found` with every planter already proved — a broken invocation and a broken instrument
// look identical from the tally, so the key is resolved here and nowhere else.
$expect = function (string $label, string $fileKey, ?string $filter, string $wantWord, int $wantCount) use ($run, $testFiles, &$failures): void {
    $r = $run($filter, $testFiles[$fileKey][0]);
    if ($r['word'] !== $wantWord || $r['count'] !== $wantCount) {
        echo "BAD  {$label}: expected {$wantCount} {$wantWord}, the run answered {$r['count']} {$r['word']} ({$r['state']}) — {$r['line']}\n";
        $failures++;

        return;
    }

    echo "ok   {$label}  {$r['line']}\n";
};

// Measured first, or every verdict after it is unanchored. Each control runs one whole file.
foreach ($testFiles as $key => [$relative, $source]) {
    $expect("control-{$key}", $key, null, 'passed', $caseCounts[$key]);
}

foreach ($cases as [$label, $target, $from, $to, $fileKey, $method]) {
    $clean = $original[$target];

    if ($from === $to) {
        echo "BAD  {$label}: the needle and its replacement are the same text, so this mutation would change nothing.\n";
        $failures++;

        continue;
    }

    $occurrences = substr_count($clean, $from);
    if ($occurrences !== 1) {
        echo "BAD  {$label}: the needle matched {$occurrences} place(s) in ".basename($targets[$target]).', expected exactly 1 — the defect cannot be planted.'."\n";
        $failures++;

        continue;
    }

    if (substr_count($testFiles[$fileKey][1], $method) !== 1) {
        echo "BAD  {$label}: the case name {$method} is not spelled exactly once in {$testFiles[$fileKey][0]}.\n";
        $failures++;

        continue;
    }

    file_put_contents($targets[$target], str_replace($from, $to, $clean));
    if (hash('sha256', (string) file_get_contents($targets[$target])) === $hashes[$target]) {
        echo "BAD  {$label}: the mutation left the file byte-identical, so this run would measure the clean instrument.\n";
        $failures++;
        $restore();

        continue;
    }

    try {
        $expect($label, $fileKey, $method, 'failed', 1);
    } finally {
        $restore();
    }
}

// The reverse direction: a harness that only ever reports red is as useless as one that never does.
foreach ($testFiles as $key => [$relative, $source]) {
    $expect("control-after-{$key}", $key, null, 'passed', $caseCounts[$key]);
}

if ($failures > 0) {
    echo "\n{$failures} leg(s) failed to prove the examination-verification tests can fail.\n";
    exit(1);
}

echo "\nAll ".(count($cases) + 2 * count($testFiles)).' legs passed: the G-4 confirmation is guarded by tests that bite.'."\n";
exit(0);
