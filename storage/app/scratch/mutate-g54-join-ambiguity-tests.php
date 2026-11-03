<?php

/**
 * Falsifier for the filter-plus-hop ambiguity class (§28.3 G-40's newest face, found by the academic term list).
 *
 * The shape: a desk action joins a table to ORDER by one of its columns, and its own filter names a column BOTH
 * sides hold. Bare, that name is ambiguous — MySQL answers 500 at the window, SQLite refuses with
 * "ambiguous column name" — and no earlier case could see it, because every case in those classes had sent the
 * filter and the order apart, or beside a header that joins nothing.
 *
 * Each leg puts the retired bare name back and requires the case that owns the promise to fail naming its own
 * refusal. The control runs that case green first, and again at the end, over every file any leg touched.
 *
 * Usage: php storage/app/scratch/mutate-g54-join-ambiguity-tests.php
 */
$root = dirname(__DIR__, 3);
chdir($root);
$php = PHP_BINARY;

$targets = [
    'app/Http/Controllers/Admin/ReferenceDataController.php',
    'app/Http/Controllers/Admin/UserManagementController.php',
    'app/Http/Controllers/Blocking/BlockingController.php',
];

$originals = [];
foreach ($targets as $f) {
    if (! is_file($f)) {
        fwrite(STDERR, "REFUSED: {$f} is not in the tree.\n");
        exit(1);
    }
    $originals[$f] = (string) file_get_contents($f);
}
$hashes = array_map('md5', $originals);
$restore = static function () use ($originals): void {
    foreach ($originals as $f => $body) {
        if (file_put_contents($f, $body) === false) {
            fwrite(STDERR, "REFUSED: the restore could not write {$f}.\n");
            exit(1);
        }
    }
};
register_shutdown_function($restore);

$bad = 0;
$legs = 0;

$runFile = static function (string $testFile, string $filter) use ($root, $php): array {
    $out = [];
    exec('cd '.$root.' && "'.$php.'" artisan test '.$testFile.' --filter '.$filter.' 2>&1', $out, $code);

    return [(int) $code, preg_replace('/\e\[[0-9;]*m/', '', implode("\n", $out))];
};

$assertRestored = static function () use ($targets, $hashes): bool {
    foreach ($targets as $f) {
        if (md5((string) file_get_contents($f)) !== $hashes[$f]) {
            echo '  TREE NOT RESTORED after '.basename($f)."\n";

            return false;
        }
    }

    return true;
};

$leg = static function (string $name, string $testFile, string $file, string $from, string $to,
    string $filter, string $why) use (&$bad, &$legs, $runFile, $restore, $assertRestored): void {
    $legs++;
    [$code, $out] = $runFile($testFile, $filter);
    if ($code !== 0 || ! str_contains($out, '1 passed')) {
        echo str_pad($name, 44).'BAD   its control is not green before planting: '.str_replace("\n", ' / ', mb_substr($out, -200))."\n";
        $bad++;

        return;
    }

    $body = (string) file_get_contents($file);
    $hits = substr_count($body, $from);
    if ($hits !== 1) {
        echo str_pad($name, 44)."BAD   needle occurs {$hits} time(s), wanted 1 — the leg's age, not a defect\n";
        $bad++;

        return;
    }
    $mutated = str_replace($from, $to, $body);
    if ($mutated === $body || file_put_contents($file, $mutated) === false
        || substr_count((string) file_get_contents($file), $to) !== 1) {
        echo str_pad($name, 44)."BAD   the plant did not land on disk\n";
        $bad++;
        $restore();

        return;
    }

    [$code, $out] = $runFile($testFile, $filter);
    $restore();

    if (str_contains($out, 'No tests found')) {
        echo str_pad($name, 44)."BAD   the filter selected no test\n";
        $bad++;
    } elseif ($code === 0 || ! str_contains($out, '1 failed')) {
        echo str_pad($name, 44).'SURVIVED — the promise is real but the case stayed green'."\n";
        $bad++;
    } elseif (! str_contains($out, 'ambiguous')) {
        echo str_pad($name, 44)."BAD   failed, but not naming the ambiguity (wanted [ambiguous]): "
            .str_replace("\n", ' / ', mb_substr($out, -220))."\n";
        $bad++;
    } elseif (! $assertRestored()) {
        $bad++;
    } else {
        echo str_pad($name, 44)."ok    killed by the case that owns it — {$why}\n";
    }
};

$control = static function (string $name, string $testFile, string $filter) use (&$bad, &$legs, $runFile): void {
    $legs++;
    [$code, $out] = $runFile($testFile, $filter);
    if ($code !== 0 || ! str_contains($out, '1 passed')) {
        echo str_pad($name, 44).'BAD   control is not green: '.str_replace("\n", ' / ', mb_substr($out, -200))."\n";
        $bad++;

        return;
    }
    echo str_pad($name, 44)."ok    control green\n";
};

$case = 'the_course_filter_and_the_course_header_are_one_request_because_both_name_the_same_key';
$unitCase = 'the_unit_filter_and_the_unit_header_are_one_request_because_both_name_the_same_key';
$officeCase = 'the_office_filter_and_the_office_header_are_one_request_because_both_name_the_same_key';

$reference = $targets[0];

$control('control-courses-unit', 'tests/Feature/Sorting/CourseListSortTest.php', $unitCase);
$control('control-majors-course', 'tests/Feature/Sorting/MajorListSortTest.php', $case);
$control('control-staff-office', 'tests/Feature/Admin/StaffDirectorySortTest.php', $officeCase);
$control('control-blocking-course', 'tests/Feature/Sorting/BlockingQueueSortTest.php', $case);

$leg(
    'courses-unit-filter-lost-its-qualifier',
    'tests/Feature/Sorting/CourseListSortTest.php',
    $reference,
    "->when(\$request->unit, fn (\$q, \$unit) => \$q->where('courses.unitId', \$unit));",
    "->when(\$request->unit, fn (\$q, \$unit) => \$q->where('unitId', \$unit));",
    $unitCase,
    'the Unit header joins academicunits, whose primary key is the column the unit filter asks'
);

$leg(
    'majors-course-filter-lost-its-qualifier',
    'tests/Feature/Sorting/MajorListSortTest.php',
    $reference,
    "->when(\$request->courseId, fn (\$q, \$courseId) => \$q->where('majors.courseId', \$courseId));",
    "->when(\$request->courseId, fn (\$q, \$courseId) => \$q->where('courseId', \$courseId));",
    $case,
    'the Course header joins courses, whose primary key is the column the course filter asks'
);

$leg(
    'staff-office-filter-lost-its-qualifier',
    'tests/Feature/Admin/StaffDirectorySortTest.php',
    $targets[1],
    "->when(\$request->officeId, fn (\$q, \$id) => \$q->where('staffusers.officeId', \$id))",
    "->when(\$request->officeId, fn (\$q, \$id) => \$q->where('officeId', \$id))",
    $officeCase,
    'the Office header joins offices, whose primary key is the column the office filter asks'
);

$leg(
    'blocking-course-filter-lost-its-qualifier',
    'tests/Feature/Sorting/BlockingQueueSortTest.php',
    $targets[2],
    "->when(\$request->courseId, fn (\$q, \$id) => \$q->where('blocks.courseId', \$id))",
    "->when(\$request->courseId, fn (\$q, \$id) => \$q->where('courseId', \$id))",
    $case,
    'the Course header joins courses, and the roster filters by the same name'
);

$control('control-courses-unit-after-plants', 'tests/Feature/Sorting/CourseListSortTest.php', $unitCase);
$control('control-blocking-course-after-plants', 'tests/Feature/Sorting/BlockingQueueSortTest.php', $case);

echo "\n=== verdict ===\n";
echo $bad === 0
    ? "ALL KILLED: {$legs} leg(s), 0 bad\n"
    : 'NOT ALL KILLED: '.$legs.' leg(s), '.$bad." bad\n";
exit($bad === 0 ? 0 : 1);
