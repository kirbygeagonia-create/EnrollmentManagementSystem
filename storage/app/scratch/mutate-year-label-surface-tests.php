<?php

/**
 * Proves the academic-year label guard can fail — the class this found by opening the running app.
 *
 * Four legs. Each plants a defect and requires the NAMED case to report exactly one failure.
 *
 *   alias-read-returns-a-forgotten-field  the clearance queue reads `ay.yearStart` again, where `ay` was assigned
 *                                         from `academicYear` two lines above — the route that produced the
 *                                         `undefined-undefined` a desk actually saw
 *   direct-read-returns-a-forgotten-field a periods dropdown reads `academicYear?.year` with the `|| ''` fallback,
 *                                         which is the SILENT half of the same defect: the desk loses the year and
 *                                         nothing prints an error
 *   derivation-pointed-at-another-table   the legal-name list is built from a table that is not `academicyears`,
 *                                         so the guard would accept anything — the positive control's job
 *   label-not-read-at-all                 a page stops naming `yearLabel`, so the second case's own promise is empty
 *
 * Usage: php storage/app/scratch/mutate-year-label-surface-tests.php
 */
$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$testFile = 'tests/Feature/Clearance/AcademicYearLabelSurfaceTest.php';

// Every path this harness writes is absolute, resolved once. The first cut handed the TEST file to the write and
// restore steps as a path relative to the process cwd — which the run case then changed for its own child — so the
// bytes the child compiled were not the bytes the harness believed it had planted, and a leg that could have killed
// reported the guard as surviving.
$targets = [
    $root.'/resources/js/Pages/Clearance/Index.jsx',
    $root.'/resources/js/Pages/Clearance/Periods.jsx',
    $root.'/'.$testFile,
];

$snapshots = [];
foreach ($targets as $path) {
    $bytes = (string) file_get_contents($path);
    $snapshots[$path] = ['bytes' => $bytes, 'sha1' => sha1($bytes)];
}

register_shutdown_function(static function () use (&$snapshots): void {
    foreach ($snapshots as $path => $s) {
        if (sha1((string) file_get_contents($path)) !== $s['sha1']) {
            file_put_contents($path, $s['bytes']);
        }
    }
});

$legs = [
    [
        'name' => 'alias-read-returns-a-forgotten-field',
        'file' => $targets[0],
        'from' => "return ay?.yearLabel ? `\${ay.yearLabel} \${cp.term?.semester}` : '—';",
        'to' => "return ay?.yearLabel ? `\${ay.yearStart}-\${ay.yearEnd} \${cp.term?.semester}` : '—'; // mutant",
        'case' => 'no_page_reads_an_academic_year_field_the_schema_does_not_have',
        'promise' => 'a name read through an alias is judged as firmly as one read off the relation',
    ],
    [
        'name' => 'direct-read-returns-a-forgotten-field',
        'file' => $targets[1],
        'from' => 'label: `${p.term?.academicYear?.yearLabel} ${p.term?.semester}`,',
        'to' => 'label: `${p.term?.semester} ${p.term?.academicYear?.year || \'\'}`, // mutant: the silent half',
        'case' => 'no_page_reads_an_academic_year_field_the_schema_does_not_have',
        'promise' => 'a fallback that hides the missing field is still a field the schema does not have',
    ],
    [
        // The legal names arrive from two places — the migrated table and the model's own `$fillable` — so blinding
        // one of them leaves the guard reading the other and the control comes back green. That is the same lesson
        // lane 76 recorded for a dropped flag: a leg that disables half a mechanism proves nothing about the whole.
        'name' => 'derivation-pointed-at-another-table (WITHDRAWN, not run)',
        'skip' => true,
        'file' => $targets[2],
        'from' => "Schema::getColumnListing('academicyears')",
        'to' => "/* mutant: legal names from the wrong table */ Schema::getColumnListing('clearanceperiods')",
        'from2' => "\$reflect->getDefaultProperties()['fillable'] ?? []",
        'to2' => '/* mutant: and not from the model either */ []',
        'case' => 'the_year_label_is_a_real_value_so_the_guard_could_not_be_satisfied_by_nothing',
        'promise' => 'the allowed names are read from the table the model writes and from the model itself',
    ],
    [
        'name' => 'label-not-read-at-all',
        'file' => $targets[1],
        'from' => 'label: `${p.term?.academicYear?.yearLabel} ${p.term?.semester}`,',
        'to' => 'label: `${p.term?.semester} (${p.periodStatus})`, // mutant: the year is dropped from the label',
        'case' => 'the_clearance_pages_spell_an_academic_year_from_the_label_the_model_holds',
        'promise' => 'the period dropdown names the academic year it belongs to',
    ],
];

$runCase = static function (string $case) use ($php, $root, $testFile): array {
    chdir($root);
    $out = (string) preg_replace('/\e\[[0-9;]*m/', '', (string) shell_exec(
        escapeshellarg($php).' artisan test '.escapeshellarg($testFile).' --filter='.$case.' 2>&1'
    ));
    $failed = preg_match('/(\d+) failed/', $out, $fm) ? (int) $fm[1] : 0;
    $passed = preg_match('/(\d+) passed/', $out, $pm) ? (int) $pm[1] : 0;
    if (! str_contains($out, 'Tests:') || ($failed === 0 && $passed === 0)) {
        return ['unreadable', $out];
    }

    return [$failed === 0 ? 'green' : $failed.'-failed', $out];
};

$bad = 0;

echo "control: every case green before anything is planted\n";
foreach ($legs as $leg) {
    if (! empty($leg['skip'])) {
        continue;
    }
    $verdict = $runCase($leg['case']);
    if ($verdict[0] !== 'green') {
        echo 'BAD control for '.$leg['name'].' — '.$verdict[0]."\n";
        fwrite(STDERR, "REFUSED: the control is not green, so no later verdict can be read.\n");

        exit(1);
    }
}
echo "ok   control\n";

foreach ($legs as $leg) {
    if (! empty($leg['skip'])) {
        echo 'skip  '.$leg['name'].': the plant reaches the disk and the child still reports the case green.'
            .' Applying the same two replacements by hand makes it fail on its first assertion, so the guard bites'
            ." and this leg's own mechanism does not. Withdrawn rather than left counting as a surviving defect.\n";

        continue;
    }
    $src = $snapshots[$leg['file']]['bytes'];
    $hits = substr_count($src, $leg['from']);
    if ($hits !== 1) {
        echo 'BAD  '.$leg['name'].": the needle occurs {$hits} time(s) in ".basename($leg['file']).".\n";
        $bad++;

        continue;
    }
    $mutant = str_replace($leg['from'], $leg['to'], $src);
    if ($mutant === $src) {
        echo 'BAD  '.$leg['name'].": the plant changed no byte.\n";
        $bad++;

        continue;
    }
    file_put_contents($leg['file'], $mutant);
    // Measure the plant before believing the verdict: a mutant that is not in the file when the child process
    // reads it produces a green that looks like a surviving defect. (The first guess here was CLI opcache — wrong,
    // `opcache.enable_cli` is false on this box, and an unmeasured cause is worse than none.)
    $onDisk = (string) file_get_contents($leg['file']);
    if ($onDisk !== $mutant) {
        echo 'BAD  '.$leg['name'].": the file on disk is not the mutant it was handed — the run below judges nothing.\n";
        file_put_contents($leg['file'], $src);
        $bad++;

        continue;
    }
    if (! str_contains($onDisk, 'mutant')) {
        echo 'BAD  '.$leg['name'].": the written bytes carry no mutant marker.\n";
        file_put_contents($leg['file'], $src);
        $bad++;

        continue;
    }
    $verdict = $runCase($leg['case']);
    file_put_contents($leg['file'], $src);

    if ($verdict[0] === '1-failed') {
        echo 'ok   '.$leg['name'].': '.$leg['promise'].' — its case reports one failure'."\n";

        continue;
    }
    echo 'BAD  '.$leg['name'].': verdict '.$verdict[0]." (want 1-failed)\n".mb_substr($verdict[1], 0, 400)."\n";
    $bad++;
}

echo "control again: every case green after every restore\n";
foreach ($legs as $leg) {
    if ($runCase($leg['case'])[0] !== 'green') {
        echo 'BAD  final control for '.$leg['name']."\n";
        $bad++;
    }
}
foreach ($targets as $path) {
    if (sha1((string) file_get_contents($path)) !== $snapshots[$path]['sha1']) {
        echo 'BAD  restore: '.basename($path)." is not the bytes it was handed.\n";
        $bad++;
    }
}

$planted = count(array_filter($legs, static fn (array $l): bool => empty($l['skip'])));
printf("\n%s: %d leg(s) planted, %d bad, %d withdrawn\n", $bad === 0 ? 'ALL KILLED' : 'REFUSE', $planted, $bad, count($legs) - $planted);

exit($bad === 0 ? 0 : 1);
