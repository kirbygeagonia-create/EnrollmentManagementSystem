<?php

/** What does the E2E class assume is already in the database it points at? */

foreach (['tests/Feature/E2E/EnrollmentWalkthroughTest.php', 'tests/Feature/AdminAccessSmokeTest.php'] as $f) {
    if (! is_file($f)) {
        $hits = glob('tests/**/AdminAccessSmokeTest.php');
        if ($hits === []) {
            echo "NOT FOUND: {$f}\n";

            continue;
        }
        $f = $hits[0];
    }

    $s = file_get_contents($f);
    echo "\n=== {$f}\n";

    preg_match_all("/DB::table\('([a-zA-Z_]+)'\)/", $s, $m);
    $t = [];
    foreach ($m[1] as $x) {
        $t[$x] = ($t[$x] ?? 0) + 1;
    }
    ksort($t);
    echo '  DB::table targets: '.($t == [] ? '(none)' : '')."\n";
    foreach ($t as $k => $v) {
        printf("    %-30s %d\n", $k, $v);
    }

    preg_match_all('/(?:Courses|Offices|Academicunits|Academicyears|Academicterms|Subjects|Curriculums|Majors|feeTypes|FeeTypes|clearancerequirements|Religions|Roles|Permissions|Scholarshiptypes)::\w+/', $s, $m2);
    $u = array_count_values($m2[0]);
    ksort($u);
    echo "  model-class calls:\n";
    foreach ($u as $k => $v) {
        printf("    %-50s %d\n", $k, $v);
    }

    // Which of these are looked up (a missing row means the leg cannot run) vs created by the test?
    foreach (['first()', 'firstOrFail', 'value(', 'pluck(', 'count()', 'exists()'] as $read) {
        echo '    read-style ['.trim($read, '()').']: '.substr_count($s, $read)."\n";
    }

    echo '  factory()->make/create: '.substr_count($s, 'factory()')."\n";
}
