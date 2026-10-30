<?php

/**
 * Lane 96 — proves the two privacy fixes G-48 and G-49.
 *
 * Prompt §10 asks for minimum-necessary disclosure. Two defects were closed by reading the payload rather than the
 * screen: `Students` declared no `$hidden` while `Staffusers` hides its own credential hash, so a student's
 * `passwordHash` shipped in every Student 360 payload; and `StudentController::show()` eager-loaded
 * `enrollments.clinicrecords` for anybody who could open the record, while the page prints `cr.findings` — the
 * prompt's own worked example of what must not be shared.
 *
 * Each leg plants one way the promise could stop meaning anything and requires the case that owns it to come back
 * with a cross, not merely a non-zero exit. The gate runs the file with `--filter`, so the case name is the method
 * name — filtering on the sentence Pest prints selects nothing, and a harness that reads "No tests found" as a
 * green leg is a harness that sleeps.
 *
 * Run it on its own: it executes `artisan test`, and two writers in one tree fake each other's verdicts.
 *
 * Usage: php storage/app/scratch/mutate-g48-g49-privacy-tests.php [--only=LEG] [--list]
 */

$root = dirname(__DIR__, 3);
chdir($root);

$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$file = 'tests/Feature/Student/Student360PrivacyTest.php';

$flags = [];
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--list') {
        $flags['list'] = true;
    } elseif (preg_match('/^--(only|case)=(.*)$/', $arg, $m)) {
        $flags[$m[1]] = $m[2];
    } else {
        fwrite(STDERR, "REFUSED: unknown argument '$arg' — this harness reads only --only= and --list.\n");
        exit(1);
    }
}

$model = $root.'/app/Models/Students.php';
$controller = $root.'/app/Http/Controllers/StudentController.php';

$hiddenBlock = "    protected \$hidden = [\n        'passwordHash',\n    ];";
$gateBlock = "        if (request()->user()->can('clinic.view')) {\n            \$student->load('enrollments.clinicrecords');\n        }";

$legs = [
    [
        'name' => 'hidden-deleted',
        'file' => $model,
        'from' => $hiddenBlock,
        'to' => '    // (no hidden fields — the model serialises every column it holds)',
        'case' => 'the_student_credential_hash_is_never_serialised_to_a_desk',
        'why' => 'the hash is on the wire again',
    ],
    [
        'name' => 'hidden-swapped',
        'file' => $model,
        'from' => "        'passwordHash',",
        'to' => "        'contactNumber',",
        'case' => 'the_student_credential_hash_is_never_serialised_to_a_desk',
        'why' => 'a $hidden list that hides the wrong column is a $hidden list that hides nothing',
    ],
    [
        // The first version of this leg deleted the whole gate. It survived, and it was the leg that was wrong:
        // removing the load takes the relation out of the payload, which is exactly what the fix achieves, so the
        // structural case had nothing to refuse. The defect was never "no gate" — it was an UNCONDITIONAL load, so
        // that is what this leg plants.
        'name' => 'gate-unconditional',
        'file' => $controller,
        'from' => $gateBlock,
        'to' => "        \$student->load('enrollments.clinicrecords');",
        'case' => 'the_clinic_relation_is_not_even_handed_over_to_a_desk_that_may_not_read_it',
        'why' => 'the notes reach a desk that was never given the clinic right',
    ],
    [
        'name' => 'gate-inverted',
        'file' => $controller,
        'from' => "if (request()->user()->can('clinic.view')) {",
        'to' => "if (request()->user()->cannot('clinic.view')) {",
        'case' => 'the_clinic_desk_still_reads_the_record_it_is_there_to_work_on',
        'why' => 'the rule reads the permission backwards, so the clinic loses its own notes',
    ],
    [
        'name' => 'load-widened',
        'file' => $controller,
        'from' => "\$student->load('enrollments.clinicrecords');",
        'to' => "\$student->load('enrollments');",
        'case' => 'the_clinic_desk_still_reads_the_record_it_is_there_to_work_on',
        'why' => 'the holder no longer receives the findings the case names',
    ],
];

if (isset($flags['list'])) {
    foreach ($legs as $l) {
        echo $l['name'].'  ->  '.$l['case']."\n";
    }
    exit(0);
}

if (isset($flags['only'])) {
    $legs = array_values(array_filter($legs, fn (array $l): bool => $l['name'] === $flags['only']));
    if ($legs === []) {
        fwrite(STDERR, "REFUSED: --only={$flags['only']} names no leg; a run over zero legs is a green over nothing.\n");
        exit(1);
    }
}

$copies = [];
register_shutdown_function(function () use (&$copies): void {
    foreach ($copies as [$path, $bytes]) {
        file_put_contents($path, $bytes);
    }
});

/** @return array{ran:bool,marked:bool,passed:bool,tail:string} */
$runCase = function (string $case) use ($php, $file): array {
    // shell_exec runs cmd.exe here, and cmd.exe does not treat single quotes as quoting — escapeshellarg()'s
    // wrapper would arrive inside the pattern and select nothing. The name is filtered to identifier characters
    // instead, and the FQCN prefix is dropped because PHPUnit's --filter is a regex over the method name and the
    // bare name is unique in this file.
    if (preg_match('/^[A-Za-z0-9_]+$/', $case) !== 1) {
        fwrite(STDERR, "REFUSED: case name {$case} is not a plain identifier, so it will not be interpolated into a command.\n");
        exit(1);
    }

    // artisan colours its summary, and those escape codes sit between the mark and the name.
    $out = (string) preg_replace('/\e\[[0-9;]*m/', '', (string) shell_exec(
        $php.' artisan test tests/Feature/Student/Student360PrivacyTest.php --filter='.$case.' 2>&1'
    ));

    return [
        'ran' => preg_match('/Tests:\s+\d+ (passed|failed)/', $out) === 1,
        'marked' => preg_match('/[✕xF]\s*'.preg_quote($case, '/').'/u', $out) === 1,
        'passed' => preg_match('/Tests:\s+1 passed/', $out) === 1,
        'tail' => trim((string) preg_replace('/\s+/', ' ', substr($out, -300))),
    ];
};

echo "CONTROL\n";
foreach ($legs as $leg) {
    $r = $runCase($leg['case']);
    if (! $r['ran']) {
        fwrite(STDERR, "BAD: the control for {$leg['case']} printed no Tests: line — the filter selected nothing.".$r['tail']."\n");
        exit(1);
    }
    if (! $r['passed']) {
        fwrite(STDERR, "BAD: the control is not green for {$leg['name']}, so no planted defect can be read as a kill.\n");
        exit(1);
    }
    echo '  ok   '.str_pad($leg['name'], 16).' control green: '.$leg['case']."\n";
}

$bad = 0;
$restored = 0;
foreach ($legs as $leg) {
    $before = (string) file_get_contents($leg['file']);
    $copies = [[$leg['file'], $before]];

    $hits = substr_count($before, $leg['from']);
    if ($hits !== 1) {
        fwrite(STDERR, "BAD: {$leg['name']}'s needle matched {$hits} time(s), expected exactly 1 — the leg's age, not a surviving defect.\n");
        $bad++;

        continue;
    }

    $after = str_replace($leg['from'], $leg['to'], $before);

    file_put_contents($leg['file'], $after);
    if ((string) file_get_contents($leg['file']) === $before) {
        fwrite(STDERR, "BAD: {$leg['name']} wrote bytes identical to the file it read.\n");
        $bad++;
        file_put_contents($leg['file'], $before);
        $copies = [];

        continue;
    }

    $r = $runCase($leg['case']);
    if (! $r['ran']) {
        fwrite(STDERR, "BAD: the mutant run for {$leg['name']} printed no Tests: line.\n");
        $bad++;
    } elseif ($r['marked'] || ! $r['passed']) {
        echo '  killed '.str_pad($leg['name'], 16)." — {$leg['why']}\n";
    } else {
        echo '  SURVIVED '.str_pad($leg['name'], 16)." — the case stayed green over: {$leg['why']}\n";
        echo '    last reading: '.$r['tail']."\n";
        $bad++;
    }

    file_put_contents($leg['file'], $before);
    if ((string) file_get_contents($leg['file']) === $before) {
        $restored++;
    }
    $copies = [];
}

$after_control = 0;
foreach ($legs as $leg) {
    $r = $runCase($leg['case']);
    if (! $r['passed']) {
        fwrite(STDERR, "BAD: the tree did not return to green after {$leg['name']}.\n");
        $after_control++;
    }
}

printf(
    "\n%s: %d leg(s), %d bad, %d/%d restored, %d case(s) red after the run\n",
    $bad + $after_control === 0 ? 'ALL KILLED' : 'FAILURES',
    count($legs),
    $bad,
    $restored,
    count($legs),
    $after_control
);

exit($bad + $after_control === 0 ? 0 : 1);
