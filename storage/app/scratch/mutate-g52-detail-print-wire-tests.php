<?php

/**
 * Harness for §28.3 G-52's detail and print wire cases (lane 111).
 *
 * Each leg plants ONE defect in the controller the case reads, runs the ONE case that owns it, and requires that case
 * to report `1 failed`. Then it restores that file by the bytes it captured before planting. A case that stays green
 * over its own eager load broken is not a guard, and a harness that only proved the whole-student direction would let
 * the SUFFIX rule and the hand-built pick-list map rot unnoticed — both directions are therefore planted.
 *
 * Legs 1-4:  the whole-student load re-appearing (the leak itself).
 * Leg 5:     one print page reverts to the whole model — the paper, not only the screen.
 * Leg 6:     the suffix DROPPED from a screen that paints it (over-narrowing: an absence-only assertion cannot see
 *            this, because `assertArrayNotHasKey('birthdate')` stays green when the name line goes missing too).
 * Leg 7:     the assign pick-list map grows a demographic key — the hand-built row on the same screen as the roster.
 * Leg 8:     the harness refuses a flag it does not read, by name.
 * Leg 9:     every touched file comes back byte-identical, judged against a snapshot taken BEFORE the first plant.
 */
$root = dirname(__DIR__, 3);
chdir($root);

foreach (array_slice($argv, 1) as $arg) {
    fwrite(STDERR, "REFUSED: unknown argument {$arg}; this harness takes no flags — it restores what it plants.\n");

    exit(1);
}

$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$self = 'storage/app/scratch/mutate-g52-detail-print-wire-tests.php';

$identity5 = 'student:studentId,schoolIdNumber,lastName,firstName,middleName';
$identity6 = $identity5.',suffix';

$legs = [
    [
        'name' => 'clinic-whole-student-returns',
        'file' => 'app/Http/Controllers/Clinic/ClinicController.php',
        'from' => "'".$identity6."'",
        'to' => "'student'",
        'filter' => 'the_clinic_record_screen_ships_only_the_student_name',
    ],
    [
        'name' => 'id-whole-student-returns',
        'file' => 'app/Http/Controllers/ID/IDController.php',
        'from' => "'".$identity6."'",
        'to' => "'student'",
        'filter' => 'the_id_request_screen_ships_only_the_student_name',
    ],
    [
        'name' => 'accounting-whole-student-returns',
        'file' => 'app/Http/Controllers/Accounting/AccountingController.php',
        // The bare column literal occurs four times in this controller (index, show, and the two receipt lists), so
        // the leg anchors on show()'s own `load([` — a needle that matches four sites would plant in the wrong one
        // and the named case would fail for a reason this leg never claimed.
        'from' => "\$assessment->load(['enrollment.".$identity5."'",
        'to' => "\$assessment->load(['enrollment.student'",
        'filter' => 'the_fee_sheet_screen_ships_only_the_student_name',
    ],
    [
        'name' => 'assessment-whole-student-returns',
        'file' => 'app/Http/Controllers/Assessment/AssessmentController.php',
        'from' => "'enrollment.".$identity5."',\n            'enrollment.course',\n            'enrollment.term',",
        'to' => "'enrollment.student',\n            'enrollment.course',\n            'enrollment.term',",
        'filter' => 'the_assessment_screen_ships_only_the_student_name',
    ],
    [
        'name' => 'print-certificate-whole-student-returns',
        'file' => 'app/Http/Controllers/Registrar/RegistrarController.php',
        // Only the certificate's own line. The print case walks three pages and one reverted action must be enough to
        // fail it — a leg that had to break all three would be measuring the fixture, not the guard.
        'from' => "'".$identity6."', 'course', 'major', 'term.academicYear',\n            'enrolledSubjects.subject', 'registrarProcessedByUser',",
        'to' => "'student', 'course', 'major', 'term.academicYear',\n            'enrolledSubjects.subject', 'registrarProcessedByUser',",
        'filter' => 'the_three_registrar_print_pages_ship_only_the_student_name',
    ],
    [
        'name' => 'clinic-loses-the-suffix-it-paints',
        'file' => 'app/Http/Controllers/Clinic/ClinicController.php',
        'from' => "'".$identity6."'",
        'to' => "'".$identity5."'",
        'filter' => 'the_clinic_record_screen_ships_only_the_student_name',
    ],
    [
        'name' => 'blocking-pick-list-grows-a-demographic-key',
        'file' => 'app/Http/Controllers/Blocking/BlockingController.php',
        'from' => "'schoolIdNumber' => \$enrollment->student->schoolIdNumber,",
        'to' => "'schoolIdNumber' => \$enrollment->student->schoolIdNumber,\n                        'birthdate' => \$enrollment->student->schoolIdNumber,",
        'filter' => 'the_blocking_screen_ships_only_the_student_name_on_both_rosters',
    ],
];

$run = static function (string $filter) use ($php): array {
    // No pipe, no redirect: `| tail` makes the shell's exit status belong to tail, and this harness reads the
    // tally out of the answer either way.
    $out = (string) shell_exec(escapeshellarg($php).' artisan test --filter '.escapeshellarg($filter).' 2>&1');
    // Pest styles the tally words, so `1 passed` arrives as `1 ` + an escape sequence + `passed`. Matching the raw
    // bytes makes every control read as a failure and a green tree look broken — strip the colour first.
    $plain = (string) preg_replace('/\e\[[0-9;]*m/', '', $out);

    return ['out' => $plain,
        'failed' => preg_match('/Tests:\s+[^\n]*\b1 failed\b/', $plain) === 1,
        'passed' => preg_match('/Tests:\s+[^\n]*\b1 passed\b/', $plain) === 1];
};

$bad = 0;
$legsSeen = 0;

// --- 0. the tree snapshot every later verdict is measured against -------------------------------------------
$snapshot = [];
foreach ($legs as $leg) {
    $snapshot[$root.'/'.$leg['file']] = (string) file_get_contents($root.'/'.$leg['file']);
}

$live = [];
register_shutdown_function(static function () use (&$live, $snapshot): void {
    foreach ($live as $path => $bytes) {
        if ((string) file_get_contents($path) !== $bytes) {
            file_put_contents($path, $bytes);
        }
    }
    foreach ($snapshot as $path => $bytes) {
        if ((string) file_get_contents($path) !== $bytes) {
            file_put_contents($path, $bytes);
        }
    }
});

echo 'control'.PHP_EOL;
foreach ($legs as $leg) {
    $legsSeen++;
    $r = $run($leg['filter']);
    if (! $r['passed']) {
        echo '  BAD (control '.$leg['name'].'): the case is not green before anything is planted, so a later'
            .' "1 failed" would not prove the plant did it.'.PHP_EOL
            .substr($r['out'], 0, 400).PHP_EOL;
        $bad++;
    } else {
        echo '  ok   control '.$leg['name'].PHP_EOL;
    }
}
if ($bad > 0) {
    fwrite(STDERR, "REFUSED: the control is not green, so no verdict below means anything.\n");

    exit(1);
}

foreach ($legs as $leg) {
    $path = $root.'/'.$leg['file'];
    $before = $snapshot[$path];
    $live[$path] = $before;

    $hits = substr_count($before, $leg['from']);
    if ($hits !== 1) {
        echo '  BAD ('.$leg['name'].'): needle matched '.$hits.' time(s), expected exactly 1 — the tree moved since'
            ." this harness was written; re-cut the leg rather than plant blind.\n";
        $bad++;

        continue;
    }
    $mutated = str_replace($leg['from'], $leg['to'], $before);
    if ($mutated === $before) {
        echo '  BAD ('.$leg['name'].'): the plant left the bytes identical, which cannot make any case move.'.PHP_EOL;
        $bad++;

        continue;
    }
    file_put_contents($path, $mutated);

    $r = $run($leg['filter']);
    if (! $r['failed']) {
        echo '  SURVIVED ('.$leg['name'].'): the case stayed green over its own defect — that case is decoration.'
            .PHP_EOL.substr($r['out'], 0, 500).PHP_EOL;
        $bad++;
    } else {
        echo '  ok   '.$leg['name'].' → the named case reports 1 failed'.PHP_EOL;
    }

    // Restore between legs: a leg that measures a tree still carrying the previous defect measures the wrong tree.
    file_put_contents($path, $before);
    unset($live[$path]);
}

// --- the harness's own refusals -----------------------------------------------------------------------------
$legsSeen++;
$refusal = (string) shell_exec(escapeshellarg($php).' '.escapeshellarg($self).' --apply 2>&1');
if (str_contains($refusal, 'REFUSED: unknown argument --apply')) {
    echo '  ok   unknown-flag → refused by name, nothing planted'.PHP_EOL;
} else {
    echo '  BAD (unknown-flag): the harness accepted a flag it does not read: '.substr($refusal, 0, 200).PHP_EOL;
    $bad++;
}

$legsSeen++;
$moved = [];
foreach ($snapshot as $path => $bytes) {
    if ((string) file_get_contents($path) !== $bytes) {
        $moved[] = basename($path);
    }
}
if ($moved !== []) {
    echo '  BAD (tree-restored): '.implode(', ', $moved).' do not carry their pre-plant bytes.'.PHP_EOL;
    $bad++;
} else {
    echo '  ok   tree-restored → all '.count($snapshot).' touched files equal the bytes taken before the first plant'
        .PHP_EOL;
}

// The run must be judged on its own terms: a leg that silently selected no test would report neither pass nor fail.
$legsSeen++;
$noTest = $run('the_case_named_that_nobody_wrote_ever');
if ($noTest['failed'] || $noTest['passed']) {
    echo '  BAD (filter-is-live): a filter naming no case answered a tally, so the legs above could be reading'
        .' a run that selected nothing.'.PHP_EOL;
    $bad++;
} else {
    echo '  ok   filter-is-live → a filter naming no case answers neither 1 passed nor 1 failed'.PHP_EOL;
}

printf('%s: %d leg(s), %d bad%s', $bad === 0 ? 'ALL KILLED' : 'FAILURES', $legsSeen, $bad, PHP_EOL);

exit($bad === 0 ? 0 : 1);
