<?php

/**
 * Prove the G-10 return tests can fail.
 *
 * Prompt §6.4's return is one right, three desks, two destinations, four categories, one trail row of
 * six stored facts, and a notice that has to reach the desk which owes the correction. The green cases in
 * `EnrollmentReturnTest` say nothing on their own: a case that would also pass against the code it is meant
 * to guard is decoration. So each leg here plants exactly one defect in the working file, runs ONLY the case
 * that names it, and requires that case to report one failed test — then restores the bytes by hash before
 * the next measurement.
 *
 * The legs follow the promises, one per promise: the permission and the college boundary in the
 * policy, the ability's name (which may not be the permission's), the refusal sentence the desk is
 * told, each of the four routing refusals, each column of the trail row, the two ways the offer is
 * decided per page, the inbox routing, the bell's sentence, the state machine's permission to move,
 * the enum widening that makes the new state writable at all, the route, and the right's seating.
 *
 * Three readings carried over from the lanes this one copies (mutate-g4, mutate-g3, mutate-g2):
 *   - `exec()` answers only the LAST line, so the summary is read out of a captured file;
 *   - Pest colours its summary, so ANSI sequences are stripped before the tally is matched;
 *   - the no-op guard is `$from === $to`, not "the replacement already occurs": a SHRINK mutation
 *     (dropping one permission from a role's list) necessarily leaves a remainder that already
 *     occurs, and the post-write byte hash is the precise falsification.
 *
 * A leg whose needle matches nothing reports BAD rather than passing, and a leg whose mutation leaves
 * the file byte-identical is refused before the run: on a fast box both look like a clean instrument.
 *
 * The controls' expected tallies are counted out of each test file's own `#[Test]` markers, never
 * typed here — a hard-coded figure goes quietly stale the day a case is added.
 *
 * Usage: php storage/app/scratch/mutate-g10-return-tests.php [--only=<leg label>]
 */
$root = dirname(__DIR__, 3);
$php = getenv('EMS_PHP') ?: 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
chdir($root);

$testFiles = [
    'return' => 'tests/Feature/Enrollment/EnrollmentReturnTest.php',
    'phase' => 'tests/Feature/Workflow/WorkflowPhaseIndicatorTest.php',
    'collision' => 'tests/Feature/Rbac/AbilityNameCollisionTest.php',
];

$targets = [
    'policy' => $root.'/app/Policies/EnrollmentReturnPolicy.php',
    'recorder' => $root.'/app/Services/ReturnRecorder.php',
    'controller' => $root.'/app/Http/Controllers/Enrollment/ReturnController.php',
    'machine' => $root.'/app/Services/EnrollmentStateMachine.php',
    'gates' => $root.'/app/Providers/AuthServiceProvider.php',
    'routes' => $root.'/routes/web.php',
    'rbac' => $root.'/database/seeders/RbacSeeder.php',
    'registrarCtrl' => $root.'/app/Http/Controllers/Registrar/RegistrarController.php',
    'evaluationCtrl' => $root.'/app/Http/Controllers/Evaluation/EvaluationController.php',
    'admissionCtrl' => $root.'/app/Http/Controllers/Admission/AdmissionController.php',
    'inbox' => $root.'/app/Support/WorkflowInbox.php',
    'listener' => $root.'/app/Listeners/SendEnrollmentNotification.php',
    'migration' => $root.'/database/migrations/2026_10_27_1000000000_create_enrollmentreturns_table.php',
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
        'the-permission-is-never-asked',
        'policy',
        '        if ($user === null || ! $user->hasPermissionTo(\'enrollment.return\')) {',
        '        if (false) {',
        'return',
        'a_desk_without_the_return_right_is_refused_and_the_gate_that_refuses_it_is_the_gate_the_page_asks',
    ],
    [
        'the-college-boundary-is-never-asked',
        'policy',
        '        if (! CollegeScope::holds($user, $enrollment->course?->unitId)) {',
        '        if (false) {',
        'return',
        'a_college_bound_department_may_not_return_another_college_s_record',
    ],
    [
        'the_ability_takes_the_permission_s_own_name',
        'gates',
        "        Gate::define('enrollment.returnRecord', function (\$user, \$enrollment) {",
        "        Gate::define('enrollment.return', function (\$user, \$enrollment) {",
        'collision',
        'no_gate_ability_shares_a_permission_name',
    ],
    [
        'the_refusal_is_told_without_naming_its_rule',
        'controller',
        '            EnrollmentReturnPolicy::refusalFor($user, $enrollment),',
        "            '',",
        'return',
        'the_refusal_names_the_rule_that_answered_rather_than_leaving_the_desk_a_placeholder',
    ],
    [
        'a_record_is_returned_to_the_desk_it_already_sits_at',
        'recorder',
        '        if ($enrollment->enrollmentStatus === $destination->status()) {',
        '        if (false) {',
        'return',
        'a_record_already_parked_at_a_destination_is_not_returned_there_twice',
    ],
    [
        'a_dropped_record_is_returnable',
        'recorder',
        '        if ($enrollment->enrollmentStatus === EnrollmentStatus::Dropped) {',
        '        if (false) {',
        'return',
        'a_dropped_record_is_terminal_and_says_so',
    ],
    [
        'an_unsigned_department_box_is_offered_as_a_destination',
        'recorder',
        '            if ($step->stepStatus === WorkflowStepStatus::Pending && ! self::hasAnySignature($workflowId)) {',
        '            if (false) {',
        'return',
        'a_record_the_department_has_not_signed_offers_no_destination_at_all_and_refuses_the_press',
    ],
    [
        'admission_is_offered_with_no_application_to_reopen',
        'recorder',
        '        return self::approvedAdmission($enrollment) === null',
        '        return false',
        'return',
        'admission_is_offered_only_when_an_approved_application_is_there_to_reopen',
    ],
    [
        'the_trail_forgets_who_sent_it',
        'recorder',
        "                'returnedBy' => \$actor->userId,",
        "                'returnedBy' => null,",
        'return',
        'the_trail_row_carries_every_fact_section_6_4_lists',
    ],
    [
        'the_origin_phase_is_read_after_the_boxes_are_reopened',
        'recorder',
        "            ->where('stepStatus', WorkflowStepStatus::Completed->value)\n            ->max('stepOrder');",
        "            ->max('stepOrder');",
        'return',
        'the_trail_row_carries_every_fact_section_6_4_lists',
    ],
    [
        'the_trail_forgets_which_box_reopens',
        'recorder',
        "                'destinationStepOrder' => \$destinationStep?->stepOrder,",
        "                'destinationStepOrder' => null,",
        'return',
        'the_trail_row_carries_every_fact_section_6_4_lists',
    ],
    [
        'the_expected_correction_is_not_stored',
        'recorder',
        "                'expectedCorrection' => \$expectedCorrection,",
        "                'expectedCorrection' => \$note,",
        'return',
        'the_trail_row_carries_every_fact_section_6_4_lists',
    ],
    [
        'the_box_reopens_from_the_wrong_place',
        'recorder',
        '            $reopenFrom = $destinationStep === null ? 1 : $destinationStep->stepOrder;',
        '            $reopenFrom = 1;',
        'return',
        'a_return_reopens_the_destination_box_and_every_box_after_it_and_keeps_the_earlier_ones',
    ],
    [
        'the_notice_never_leaves',
        'recorder',
        "            ->where('newStatus', \$enrollment->enrollmentStatus->value)",
        "            ->where('newStatus', EnrollmentStatus::ReturnedToEvaluation->value)",
        'return',
        'the_notice_leaves_once_the_record_moves_on_but_the_trail_stays',
    ],
    [
        'the_owing_desk_is_told_nothing',
        'evaluationCtrl',
        "            'openReturn' => ReturnRecorder::openReturn(\$enrollment),",
        "            'openReturn' => null,",
        'return',
        'the_receiving_desk_reads_who_sent_it_and_what_it_owes',
    ],
    [
        'the_evaluation_page_offers_an_act_the_policy_refuses',
        'evaluationCtrl',
        "            'returnOptions' => \$request->user()->can('enrollment.returnRecord', \$enrollment)\n                ? ReturnRecorder::optionsFor(\$enrollment)\n                : null,",
        "            'returnOptions' => ReturnRecorder::optionsFor(\$enrollment),",
        'return',
        'a_desk_that_reads_the_record_but_may_not_return_it_sees_the_notice_and_not_the_control',
    ],
    [
        'the_admission_page_offers_an_act_the_policy_refuses',
        'recorder',
        '            \'options\' => app(EnrollmentReturnPolicy::class)->returnRecord($actor, $record)',
        "            'options' => true",
        'return',
        'the_admission_desk_sees_the_notice_even_when_it_may_not_send_anything_back',
    ],
    [
        'the_admission_desk_never_reads_what_it_owes',
        'recorder',
        "            'openReturn' => self::openReturn(\$record),",
        "            'openReturn' => null,",
        'return',
        'the_admission_desk_sees_the_notice_even_when_it_may_not_send_anything_back',
    ],
    [
        'a_return_rings_the_next_forward_desk_instead_of_its_destination',
        'inbox',
        '        $returned = self::RETURNED_HANDOFF[$status] ?? null;',
        '        $returned = null;',
        'return',
        'the_bell_names_the_desk_that_actually_sent_the_record',
    ],
    [
        'the_bell_assumes_the_registrar_sent_it',
        'listener',
        '        return "{$sender} returned this record to {$destination}".($event->remarks ? ": {$event->remarks}" : \'.\');',
        '        return "the Registrar returned this record to {$destination}".($event->remarks ? ": {$event->remarks}" : \'.\');',
        'return',
        'the_bell_names_the_desk_that_actually_sent_the_record',
    ],
    [
        'the_bell_has_no_sentence_for_an_admission_return',
        'listener',
        "            'returnedToAdmission' => \$this->returnLead(\$event, 'Admission'),",
        "            'returnedToAdmission' => 'enrollment status moved',",
        'return',
        'the_bell_names_the_desk_that_actually_sent_the_record',
    ],
    [
        'the_state_machine_refuses_to_move_a_paid_record_back',
        'machine',
        "        'paid' => ['enrolled', 'assessed', 'returnedToEvaluation', 'returnedToAdmission', 'dropped'],",
        "        'paid' => ['enrolled', 'assessed', 'dropped'],",
        'return',
        'the_three_desks_holding_the_one_right_can_all_return_the_same_kind_of_record',
    ],
    [
        'the_new_state_is_not_written_into_the_column',
        'migration',
        "        'returnedToEvaluation',\n        'returnedToAdmission',",
        "        'returnedToEvaluation',",
        'return',
        'a_return_to_admission_parks_the_record_and_reopens_the_application_that_issued_it',
    ],
    // The edge this batch added to AdmissionController::approve(). A dead-end state has two ways to look alive:
    // the status exists in the enum, and a desk can still press Approve. Only the record leaving the state
    // proves the edge is wired, so the needle is the guard's own condition, not the transition below it —
    // the call stays in the file and the branch around it stops running.
    [
        'the_reopened_application_leaves_the_parked_record_parked',
        'admissionCtrl',
        '                if ($standing->enrollmentStatus === EnrollmentStatus::ReturnedToAdmission) {',
        '                if (false) {',
        'return',
        'approving_the_reopened_application_brings_a_parked_record_back_into_the_pipeline',
    ],
    [
        'the_act_has_no_route',
        'routes',
        "    Route::post('/enrollment/{enrollment}/return', [ReturnController::class, 'store'])->name('enrollment.return');",
        '    // (the return route retired by the mutant)',
        'return',
        'the_trail_row_carries_every_fact_section_6_4_lists',
    ],
    [
        'the_admission_desk_was_never_granted_the_right',
        'rbac',
        "            'enrollment.return',\n            'students.view',\n        ]);",
        "            'students.view',\n        ]);",
        'return',
        'the_three_desks_holding_the_one_right_can_all_return_the_same_kind_of_record',
    ],
    [
        'every_office_head_can_return_records',
        'rbac',
        "        \$officeHead->syncPermissions([\n            'admission.view', 'exam.view',",
        "        \$officeHead->syncPermissions([\n            'enrollment.return', 'admission.view', 'exam.view',",
        'return',
        'the_return_right_is_not_seated_with_the_office_head_of_every_office',
    ],
];

// `--only=<leg>` re-runs one leg without the whole walk. The controls still run, because a leg
// measured against a suite nobody re-checked green is not a measurement. A label that names no
// collected leg is refused: an ignored flag is how a mutating run looks clean.
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
    $log = sys_get_temp_dir().'/g10mut-'.uniqid().'.txt';
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

    // Pest writes ANSI colour codes into captured output; without stripping them the tally never
    // matches and every leg reads as a broken instrument.
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

// The runner takes a PATH, so the short key is resolved here and nowhere else: handing it the key is
// what made an earlier run answer every leg with `Test file "x" not found`.
$expect = function (string $label, string $fileKey, ?string $filter, string $wantWord, int $wantCount) use ($run, $testFiles, &$failures): void {
    $r = $run($filter, $testFiles[$fileKey][0]);
    if ($r['word'] !== $wantWord || $r['count'] !== $wantCount) {
        echo "BAD  {$label}: expected {$wantCount} {$wantWord}, the run answered {$r['count']} {$r['word']} ({$r['state']}) — {$r['line']}\n";
        $failures++;

        return;
    }

    echo "ok   {$label}  {$r['line']}\n";
};

// A tree left carrying one of THIS harness's own mutant shapes is a tree that a previous run damaged, and
// measuring it would grade a defect while reporting it as the build. On 2026-10-27 two copies of this harness
// overlapped: the second captured the first's planted bytes as its "original" and wrote them back at exit, so
// `ReturnRecorder::refusalFor()` kept `if (false)` permanently and only a later control going red said so.
// Refuse at the door instead — the check costs one pass over the targets and names what it found.
//
// The test is decidable only where the mutant text is NOT a substring of the clean text: two legs here replace
// a two-line run by its own first line (`'returnedToEvaluation',` inside the pair that also names
// `returnedToAdmission`), so a bare "does the file contain the mutant form" would accuse a healthy tree of
// carrying a defect. That is lane 69's needle rule met in my own instrument: a short needle is a prefix of a
// longer one, and the guard has to say which legs it could read.
$scanLeftovers = static function (array $snapshot) use ($cases, $targets): array {
    $leftovers = [];
    $unreadable = 0;
    foreach ($cases as [$label, $target, $from, $to]) {
        if (str_contains($from, $to)) {
            $unreadable++;

            continue;
        }
        // Line-exact, never a substring: four legs here replace a guard with `if (false) {` at different
        // indents, and the 8-space form is a substring of the 12-space one — so a single planted defect would
        // be reported as three, and the containment test my own positive control exists to catch.
        $lines = array_map(static fn (string $l): string => rtrim($l), explode("\n", $snapshot[$target]));
        if (in_array(rtrim($to), $lines, true)) {
            $leftovers[] = "{$label} — ".basename($targets[$target]).' already carries its mutant form';
        }
    }

    return [$leftovers, $unreadable];
};

// The guard is proved before it is trusted: a copy of the snapshot with one leg's defect planted in it must be
// named by the same predicate, or a guard that always says "clean" would look like the healthiest line here.
$planted = $original;
$planted['recorder'] = str_replace(
    '            if ($step->stepStatus === WorkflowStepStatus::Pending && ! self::hasAnySignature($workflowId)) {',
    '            if (false) {',
    $planted['recorder']
);
if ($planted === $original) {
    fwrite(STDERR, "REFUSED: the guard's own positive control could not be planted — the recorder's clean text moved.\n");
    exit(1);
}
[$plantedHits, ] = $scanLeftovers($planted);
if (count($plantedHits) !== 1 || ! str_contains($plantedHits[0], 'an_unsigned_department_box_is_offered_as_a_destination')) {
    fwrite(STDERR, "REFUSED: the leftover guard did not name the defect it was handed (".count($plantedHits)." hit(s)).\n");
    exit(1);
}
echo "leftover guard: positive control named its own leg\n";

[$leftovers, $unreadable] = $scanLeftovers($original);
if ($leftovers !== []) {
    fwrite(STDERR, "REFUSED: the working tree is not clean of this harness's own defects; repair before measuring.\n");
    foreach ($leftovers as $l) {
        fwrite(STDERR, '  '.$l."\n");
    }
    exit(1);
}
printf("leftover guard: %d leg(s) checked against the real tree, %d skipped because the mutant text is a substring of the clean text\n\n",
    count($cases) - $unreadable, $unreadable);


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
    echo "\n{$failures} leg(s) failed to prove the return tests can fail.\n";
    exit(1);
}

echo "\nAll ".(count($cases) + 2 * count($testFiles)).' legs passed: the one return act is guarded by tests that bite.'."\n";
exit(0);
