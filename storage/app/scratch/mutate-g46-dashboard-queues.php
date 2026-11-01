<?php

/**
 * Lane 96 — proves `tests/Feature/Dashboard/DashboardQueueCountMatchesDeskTest.php`.
 *
 * Prompt §8 says a dashboard count must not contradict the list underneath it, and the register's answer (G-46)
 * is now one file: `App\Support\DeskQueues` builds the eight queue populations the badges count. A test that only
 * asserted "a number came back" would pass on every one of the retired bugs, so each leg here plants one retired
 * or newly-invented filter and requires the case that owns that promise to print `1 failed`.
 *
 * Four legs, and they are the two directions of the same rule:
 *
 *  1. BADGE-BOUNDS-LIST-DOESNT — the defect this batch *created and then removed*. The first draft applied
 *     `CollegeScope` to the assessment and accounting badges because the desk's *policies* bound the record, but
 *     `AssessmentController` and `AccountingController` carry no `CollegeScope` call at all, so a college-bound
 *     desk got a badge of 1 over a list of 2. This leg plants that bound inside the helper (no signature churn)
 *     and expects the college-free case to go red.
 *  2. EVALUATION-PENDING-ONLY — the original drift: the badge counted `Pending` while the desk also lists what
 *     the Registrar sent back.
 *  3. ADMISSION-UNBOUNDED — the mirror: the badge counted the whole school while `AdmissionController` bounds its
 *     own list by college.
 *  4. ACCOUNTING-ALL-ACCOUNTS — the badge counted every assessed sheet, including the settled ones the page puts
 *     on its other tab.
 *
 * Run it on its own, never beside a suite run: the legs execute `artisan test`, and two writers in one tree fake
 * each other's verdicts.
 *
 * Usage: php storage/app/scratch/mutate-g46-dashboard-queues.php [--only=LEG] [--list]
 */

$root = dirname(__DIR__, 3);
chdir($root);

$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';

$flags = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--(only|list)$/', $a) || str_starts_with($a, '--only=')) {
        $flags[substr($a, 2, strpos($a.'=', '=') - 2)] = substr(strstr($a, '='), 1);
    } else {
        fwrite(STDERR, "REFUSED: unknown flag '$a' — this harness reads only --only= and --list.\n");
        exit(1);
    }
}

/** Each leg: the case that must go red, and the exact bytes it is planted into. */
$legs = [
    [
        'name' => 'badge-bounds-list-doesnt',
        'case' => 'the_six_desks_without_a_college_filter_count_the_school_as_their_lists_do',
        'file' => 'app/Support/DeskQueues.php',
        'from' => "        return Studentassessments::query()\n            ->whereHas('enrollment', fn (\$q) => \$q->where('enrollmentStatus', \$status->value));",
        'to' => "        \$unit = auth()->user()?->unitId;\n\n        return Studentassessments::query()\n            ->whereHas('enrollment', fn (\$q) => \$q->where('enrollmentStatus', \$status->value))\n            ->when(\$unit, fn (\$q) => \$q->whereHas('enrollment.course', fn (\$c) => \$c->where('unitId', \$unit)));",
    ],
    [
        'name' => 'evaluation-pending-only',
        'case' => 'the_evaluation_badge_counts_what_the_department_actually_lists',
        'file' => 'app/Support/DeskQueues.php',
        'from' => "        )->whereIn('enrollmentStatus', [\n            EnrollmentStatus::Pending->value,\n            EnrollmentStatus::ReturnedToEvaluation->value,\n        ]);",
        'to' => "        )->where('enrollmentStatus', EnrollmentStatus::Pending->value);",
    ],
    [
        'name' => 'admission-unbounded',
        'case' => 'a_college_desk_is_counted_inside_its_own_college',
        'file' => 'app/Support/DeskQueues.php',
        'from' => "        return CollegeScope::ofAdmissions(\n            Admissions::query(),\n            \$desk\n        )->where('admissionStatus', AdmissionStatus::Pending->value);",
        'to' => "        return Admissions::query()->where('admissionStatus', AdmissionStatus::Pending->value);",
    ],
    [
        'name' => 'accounting-all-accounts',
        'case' => 'the_accounting_badge_equals_the_due_queue_the_desk_opens_on',
        'file' => 'app/Support/DeskQueues.php',
        'from' => "        return self::assessmentsFor(EnrollmentStatus::Assessed)\n            ->where('remainingBalance', '>', 0);",
        'to' => "        return self::assessmentsFor(EnrollmentStatus::Assessed);",
    ],
    // Lane 114's own promise. Putting the department's predicate back into its controller keeps every NUMBER equal —
    // the badge case stays green — so the only thing that can see the two copies returning is the structural case
    // that reads the action's body. A leg on the equality case would have slept.
    [
        'name' => 'evaluation-list-re-inlined',
        'case' => 'four_desk_lists_call_the_helper_for_their_rows_and_the_clearance_desk_still_lists_more_than_its_badge',
        'file' => 'app/Http/Controllers/Evaluation/EvaluationController.php',
        'from' => '        $query = DeskQueues::evaluation($desk)',
        'to' => "        \$query = CollegeScope::ofEnrollments(Enrollments::query(), \$desk)->whereIn('enrollmentStatus', [EnrollmentStatus::Pending, EnrollmentStatus::ReturnedToEvaluation])",
    ],
];

if (isset($flags['list'])) {
    foreach ($legs as $l) {
        echo $l['name']."\n";
    }
    exit(0);
}

if (isset($flags['only'])) {
    $legs = array_values(array_filter($legs, fn (array $l): bool => $l['name'] === $flags['only']));
    if ($legs === []) {
        fwrite(STDERR, "REFUSED: --only={$flags['only']} names no leg. (a run over zero legs is a green over nothing)\n");
        exit(1);
    }
}

$copies = [];

register_shutdown_function(function () use (&$copies): void {
    foreach ($copies as [$path, $bytes]) {
        file_put_contents($path, $bytes);
    }
});

function run_case(string $php, string $case): array
{
    global $root;
    // artisan colours its summary, and shell_exec captures those escape codes: "Tests:" is followed by an ANSI
    // sequence, not by the space the tally needs, so a run that really passed reads as no verdict at all.
    $out = (string) preg_replace('/\e\[[0-9;]*m/', '', (string) shell_exec($php.' artisan test --filter='.$case.' 2>&1'));
    $failed = preg_match('/Tests:\s+1 failed/', $out) === 1;
    $passed = preg_match('/Tests:\s+1 passed/', $out) === 1;
    $ran = preg_match('/Tests:/', $out) === 1;

    return ['failed' => $failed, 'passed' => $passed, 'ran' => $ran, 'tail' => trim((string) preg_replace('/\s+/', ' ', substr($out, -320)))];
}

echo "CONTROL (the tree as it stands)\n";
$bad = 0;
foreach ($legs as $leg) {
    $r = run_case($php, $leg['case']);
    if (! $r['ran']) {
        fwrite(STDERR, "BAD: control run for {$leg['name']} printed no Tests: line — the harness executed nothing.\n");
        exit(1);
    }
    if (! $r['passed']) {
        fwrite(STDERR, "BAD: control is not green for {$leg['name']}, so no planted defect can be read as a kill.\n".$r['tail']."\n");
        exit(1);
    }
    echo "  ok   {$leg['name']} — control green\n";
}

foreach ($legs as $leg) {
    $path = $root.'/'.$leg['file'];
    $before = (string) file_get_contents($path);
    $copies = [[$path, $before]];

    $hits = substr_count($before, $leg['from']);
    if ($hits !== 1) {
        fwrite(STDERR, "BAD: {$leg['name']}'s needle matched {$hits} time(s), expected exactly 1 — the leg's age, not a surviving defect.\n");
        $bad++;

        continue;
    }

    file_put_contents($path, str_replace($leg['from'], $leg['to'], $before));
    if ((string) file_get_contents($path) === $before) {
        fwrite(STDERR, "BAD: {$leg['name']} wrote bytes identical to the file it read.\n");
        $bad++;

        continue;
    }

    $r = run_case($php, $leg['case']);
    $kill = $r['failed'];
    echo ($kill ? '  killed ' : '  SURVIVED ').$leg['name'].' — '.($kill ? 'the named case went red' : 'the case stayed green over the planted defect')."\n";
    if (! $kill) {
        echo '    last reading: '.$r['tail']."\n";
        $bad++;
    }

    file_put_contents($path, $before);
    $copies = [];
}

$again = 0;
foreach ($legs as $leg) {
    $r = run_case($php, $leg['case']);
    if (! $r['passed']) {
        fwrite(STDERR, "BAD: the tree did not return to green after {$leg['name']}.\n");
        $again++;
    }
}

printf("\n%s: %d leg(s), %d bad, %d not restored\n", $bad + $again === 0 ? 'ALL KILLED' : 'FAILURES', count($legs), $bad, $again);

exit($bad + $again === 0 ? 0 : 1);
