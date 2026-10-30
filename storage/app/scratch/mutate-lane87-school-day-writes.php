<?php

/**
 * Proves the G43 school-day writes and the instrument that guards the whole family.
 *
 * Seven legs. Four plant the defect this batch repaired — a DATE column fed `now()`, which Eloquent formats in
 * the APPLICATION timezone (UTC here), so anything between 16:00Z and midnight files the day before the
 * school's — and require the case that owns that column to report exactly one failure. One leg plants the defect
 * at a site NO test pins (RegistrarController's `enrolledDate`) and requires `lane87 --ci` to name it anyway,
 * which is the claim the instrument exists for. The last two blind the instrument itself: a copy whose write
 * pattern cannot match its own planted file, and a copy whose plant names a column no migration declares DATE.
 * Both must REFUSE, because a sweep that cannot say found it can only ever say clean.
 *
 * Every target file is restored by hash in a shutdown handler, and the mutant copies are removed there too —
 * a scratch `.php` left behind is tracked content on this box (storage/app/.gitignore re-includes by extension).
 *
 * Usage: php storage/app/scratch/mutate-lane87-school-day-writes.php
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$tool = 'storage/app/scratch/lane87-date-write-columns.php';

/** leg => [file, needle, replacement, test file, case] */
$siteLegs = [
    'assessment-date-from-utc' => [
        'app/Http/Controllers/Assessment/AssessmentController.php',
        "'assessmentDate' => SchoolTime::today(),",
        "'assessmentDate' => now(),",
        'tests/Feature/Assessment/ComputeRequiresSignatureTest.php',
        'the_fee_sheet_is_dated_the_school_s_day_and_not_the_machine_s',
    ],
    'request-date-from-utc' => [
        'app/Http/Controllers/ID/IDController.php',
        "'requestDate' => SchoolTime::today(),",
        "'requestDate' => now(),",
        'tests/Feature/ID/IDControllerTest.php',
        'test_the_id_request_is_dated_the_school_s_day_and_not_the_machine_s',
    ],
    'approval-date-from-utc' => [
        'app/Http/Controllers/Clearance/ClearanceController.php',
        "'approvalDate' => SchoolTime::today(),",
        "'approvalDate' => now(),",
        'tests/Feature/Clearance/ClearanceRequirementRejectionTest.php',
        'an_approved_line_is_dated_the_school_s_day_and_not_the_machine_s',
    ],
    'form-signed-date-from-utc' => [
        'app/Http/Controllers/Evaluation/EvaluationController.php',
        "'formSignedDate' => SchoolTime::today(),",
        "'formSignedDate' => now(),",
        'tests/Feature/Evaluation/ProfileCaptureTest.php',
        'the_evaluation_signature_is_dated_the_school_s_day_and_not_the_machine_s',
    ],
    // No behaviour case sits on this one, so the instrument is the only thing standing between the desk and a
    // date the daemon chose. The leg's promise is coverage of an unpinned site, not a test failing.
    'enrolled-date-from-utc-unpinned-by-any-test' => [
        'app/Http/Controllers/Registrar/RegistrarController.php',
        "'enrolledDate' => SchoolTime::today(),",
        "'enrolledDate' => now(),",
        '',
        '',
    ],
];

$snapshots = [];
$mutants = [];
foreach ($siteLegs as [$rel, $needle]) {
    $path = $root.'/'.$rel;
    $bytes = (string) file_get_contents($path);
    if (substr_count($bytes, $needle) !== 1) {
        fwrite(STDERR, "REFUSED: [{$needle}] occurs ".substr_count($bytes, $needle)
            ." time(s) in {$rel}; the tree is not the batch this harness proves.\n");

        exit(1);
    }
    $snapshots[$path] = ['bytes' => $bytes, 'sha1' => sha1($bytes)];
}

$cleanup = static function () use (&$snapshots, &$mutants): void {
    foreach ($snapshots as $path => $s) {
        if (sha1((string) file_get_contents($path)) !== $s['sha1']) {
            file_put_contents($path, $s['bytes']);
        }
    }
    foreach ($mutants as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
};
register_shutdown_function($cleanup);

$runCase = static function (string $testFile, string $name) use ($php, $root): array {
    chdir($root);
    $cmd = escapeshellarg($php).' artisan test '.escapeshellarg($testFile)
        .' --filter='.escapeshellarg($name).' 2>&1';
    $out = (string) preg_replace('/\e\[[0-9;]*m/', '', (string) shell_exec($cmd));
    $failed = preg_match('/(\d+) failed/', $out, $fm) ? (int) $fm[1] : 0;
    $passed = preg_match('/(\d+) passed/', $out, $pm) ? (int) $pm[1] : 0;
    if (! str_contains($out, 'Tests:') || ($failed === 0 && $passed === 0)) {
        return ['unreadable', $out];
    }

    return [$failed === 0 ? 'green' : $failed.'-failed', $out];
};

/** Runs lane87 itself; exec() hands back the child's own status, so no wrapper can swallow it. */
$runTool = static function (string $script, bool $ci) use ($php, $root): array {
    chdir($root);
    $outFile = $root.'/storage/app/scratch/lane87-mutate-out.txt';
    $cmd = escapeshellarg($php).' '.escapeshellarg($root.'/'.$script).($ci ? ' --ci' : '')
        .' > '.escapeshellarg($outFile).' 2>&1';
    $status = 0;
    exec($cmd, $ignored, $status);
    $out = (string) file_get_contents($outFile);
    if ($out === '') {
        return ['no-output', $out];
    }

    return [(string) $status, $out];
};

$bad = 0;

echo "control: every pinned case green, and the instrument clean, before anything is planted\n";
$toolControl = $runTool($tool, true);
if ($toolControl[0] !== '0' || ! str_contains($toolControl[1], 'control: 5 of 5')) {
    echo 'BAD tool control — status '.$toolControl[0]."\n".mb_substr($toolControl[1], -400)."\n";
    fwrite(STDERR, "REFUSED: the instrument's own control is not green, so nothing below can be read.\n");

    exit(1);
}
echo "ok   instrument: planted control recovered 5 of 5 and the tree answers clean\n";

foreach ($siteLegs as $name => [$rel, $needle, $mutant, $testFile, $case]) {
    $path = $root.'/'.$rel;

    if ($testFile !== '') {
        $control = $runCase($testFile, $case);
        if ($control[0] !== 'green') {
            echo "BAD  {$name}: control case is {$control[0]}\n".mb_substr($control[1], 0, 400)."\n";
            $bad++;

            continue;
        }
    }

    file_put_contents($path, str_replace($needle, $mutant, (string) file_get_contents($path)));
    if (sha1((string) file_get_contents($path)) === $snapshots[$path]['sha1']) {
        echo "BAD  {$name}: the plant changed no byte.\n";
        $bad++;

        continue;
    }

    // The instrument must name the column it was written to find, whether or not a test sits on the site.
    $verdict = $runTool($tool, true);
    if ($verdict[0] !== '1' || ! str_contains($verdict[1], 'CANDIDATE')) {
        echo "BAD  {$name}: lane87 answered status {$verdict[0]} against the planted defect\n"
            .mb_substr($verdict[1], 0, 400)."\n";
        $bad++;
    } elseif (! str_contains($verdict[1], str_replace(["'", ' => SchoolTime::today(),'], '', $needle))) {
        echo "BAD  {$name}: lane87 refused but did not name the column it found.\n";
        $bad++;
    } else {
        echo "ok   {$name}: lane87 --ci exits 1 and names the column\n";
    }

    if ($testFile !== '') {
        $failed = $runCase($testFile, $case);
        if ($failed[0] === '1-failed') {
            echo "ok   {$name}: its own case reports one failure\n";
        } else {
            echo "BAD  {$name}: case verdict {$failed[0]} (want 1-failed)\n".mb_substr($failed[1], 0, 400)."\n";
            $bad++;
        }
    } else {
        echo "ok   {$name}: no case owns this site, so the instrument alone was asked — and it answered\n";
    }

    file_put_contents($path, $snapshots[$path]['bytes']);
    if (sha1((string) file_get_contents($path)) !== $snapshots[$path]['sha1']) {
        echo "BAD  {$name}: restore failed.\n";
        $bad++;

        continue;
    }

    if ($testFile !== '') {
        $after = $runCase($testFile, $case);
        if ($after[0] !== 'green') {
            echo "BAD  {$name}: the case is {$after[0]} after the restore\n";
            $bad++;
        }
    }
}

// Now blind the instrument in a copy and require it to refuse rather than report a clean sweep.
$toolPath = $root.'/'.$tool;
$blindPath = $root.'/storage/app/scratch/lane87-mutant-blind.php';
$plantPath = $root.'/storage/app/scratch/lane87-mutant-plantname.php';
$mutants[] = $blindPath;
$mutants[] = $plantPath;

// The tool's own write pattern, taken as literal bytes: a single-quoted PHP string keeps `\s` and `\(` exactly
// as the source spells them, which is the one shape that survives both this file and Pint.
$blindFrom = '\s*=>\s*now\(\)/';
$blindTo = '\s*=>\s*now\(\)\(\)/';
$blindSrc = str_replace($blindFrom, $blindTo, (string) file_get_contents($toolPath), $blindCount);
if ($blindCount !== 1) {
    echo 'BAD reader-blind leg: the write pattern was not found exactly once (matched '.$blindCount.'), so the'
        ." mutation would plant nothing and the leg's verdict would be the harness's own failure.\n";
    $bad++;
} else {
    file_put_contents($blindPath, $blindSrc);
    $blind = $runTool('storage/app/scratch/lane87-mutant-blind.php', false);
    if ($blind[0] !== '1' || ! str_contains($blind[1], 'REFUSED')) {
        echo "BAD reader-blind: a sweep that cannot see its own planted file answered status {$blind[0]}\n"
            .mb_substr($blind[1], -400)."\n";
        $bad++;
    } elseif (str_contains($blind[1], 'CLEAN')) {
        echo "BAD reader-blind: the blinded reader printed CLEAN over a defect it could not read.\n";
        $bad++;
    } else {
        echo "ok   reader-blind: the blinded pattern loses all five planted writes and the run refuses\n";
    }
}

// A plant naming a column the schema does not call DATE must be LOST by the control, not counted as recovered:
// the mutation keeps every recovered LINE but keys them numerically, so only a control that binds the column
// NAME to the migration reader can notice.
$plantFrom = "\$plantedFound[\$p['col']] = true;";
$plantTo = "\$plantedFound[] = 'phantom-'.\$p['col'];";
$plantSrc = str_replace($plantFrom, $plantTo, (string) file_get_contents($toolPath), $plantCount);
if ($plantCount !== 1) {
    echo "BAD plant-name leg: the recovery line was found {$plantCount} time(s), not once.\n";
    $bad++;
} else {
    file_put_contents($plantPath, $plantSrc);
    $plant = $runTool('storage/app/scratch/lane87-mutant-plantname.php', false);
    if ($plant[0] !== '1' || ! str_contains($plant[1], 'REFUSED')) {
        echo "BAD plant-name: columns that no migration declares as DATE were accepted by the control"
            ." — status {$plant[0]}\n".mb_substr($plant[1], -400)."\n";
        $bad++;
    } else {
        echo "ok   plant-name: the control binds each planted column to the migration reader, not to a line count\n";
    }
}

$final = $runTool($tool, true);
if ($final[0] !== '0' || ! str_contains($final[1], 'control: 5 of 5')) {
    echo "BAD final instrument run — status {$final[0]}\n".mb_substr($final[1], -400)."\n";
    $bad++;
}
foreach ($snapshots as $path => $s) {
    if (sha1((string) file_get_contents($path)) !== $s['sha1']) {
        echo 'BAD restore: '.basename($path)." is not the bytes it was handed.\n";
        $bad++;
    }
}
printf("\n%s: %d leg(s) + 2 instrument legs, %d bad\n", $bad === 0 ? 'ALL KILLED' : 'REFUSE', count($siteLegs), $bad);

exit($bad === 0 ? 0 : 1);
