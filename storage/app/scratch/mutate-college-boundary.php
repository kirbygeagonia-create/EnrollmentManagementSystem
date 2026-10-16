<?php

/**
 * Mutate the college boundary and require that the suite names the test that died, one mutant per process.
 *
 * Six tests passing is only evidence once each rule they assert has been broken and watched a test fail.
 * The cases are aimed at the six distinct decisions in `CollegeScope`, including the two that a careless
 * implementation would pass by accident: the completeness half (a filter that hides everything would keep
 * every exclusion green), and the unbounded half (an over-eager boundary would fail this too).
 *
 * Usage: php mutate-college-boundary.php A|B|C|D|E|F
 * Reads each run's own log for the failing test name and the `Tests:` line — never the wrapper's exit code.
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$scope = $root.'/app/Support/CollegeScope.php';
$baseline = file_get_contents($scope);

$cases = [
    'A' => [
        'what' => 'holds() asks nothing of the college — the policy stop is gone',
        'find' => "        return \$courseUnitId !== null && \$mine === \$courseUnitId;",
        'fix' => "        return true;",
    ],
    'B' => [
        'what' => 'ofEnrollments() never constrains — the queue shows every college',
        'find' => "        return \$mine === null\n            ? \$query\n            : \$query->whereHas('course', fn (Builder \$c) => \$c->where('unitId', \$mine));\n    }\n\n    /**\n     * Applications to the desk's own college.",
        'fix' => "        return \$query;\n    }\n\n    /**\n     * Applications to the desk's own college.",
    ],
    'C' => [
        'what' => 'a student counts only through an enrollment — applicants vanish from the desk',
        'find' => "                ->whereHas('enrollments.course', fn (Builder \$c) => \$c->where('unitId', \$mine))\n                ->orWhereHas('admissions.course', fn (Builder \$c) => \$c->where('unitId', \$mine)));",
        'fix' => "                ->whereHas('enrollments.course', fn (Builder \$c) => \$c->where('unitId', \$mine)));",
    ],
    'D' => [
        'what' => 'a shift reaches only the college the student leaves',
        'find' => "                ->whereHas('currentCourse', fn (Builder \$c) => \$c->where('unitId', \$mine))\n                ->orWhereHas('targetCourse', fn (Builder \$c) => \$c->where('unitId', \$mine)));",
        'fix' => "                ->whereHas('currentCourse', fn (Builder \$c) => \$c->where('unitId', \$mine)));",
    ],
    'E' => [
        'what' => 'an account with no college is bounded by an impossible college id — nothing at all',
        'find' => "        if (\$mine === null) {\n            return true;\n        }\n\n        return \$courseUnitId !== null",
        'fix' => "        if (\$mine === null) {\n            \$mine = 0;\n        }\n\n        return \$courseUnitId !== null",
    ],
    'F' => [
        'what' => 'ofCourses() never constrains — the dropdown offers programs the policy refuses',
        'find' => "        return \$mine === null ? \$query : \$query->where('unitId', \$mine);",
        'fix' => "        return \$query;",
    ],
];

$id = $argv[1] ?? '';
if (! isset($cases[$id])) {
    fwrite(STDERR, "usage: php ".basename(__FILE__)." A|B|C|D|E|F\n");
    exit(2);
}

$c = $cases[$id];
$hits = substr_count($baseline, $c['find']);
if ($hits !== 1) {
    printf("mutant %s  %-64s NOT USABLE: anchor occurs %d time(s)\n", $id, $c['what'], $hits);
    exit(1);
}

$restored = false;
try {
    file_put_contents($scope, str_replace($c['find'], $c['fix'], $baseline));
    clearstatcache(true, $scope);

    $lines = [];
    exec(sprintf('cd "%s" && "%s" artisan test tests/Feature/Rbac/CollegeDeskScopeTest.php 2>&1', $root, $php), $lines, $code);

    $log = implode("\n", $lines);
    file_put_contents($root."/storage/app/scratch/mutate-college-{$id}.log", $log."\n");

    // The names that died, read out of the run's own output — "non-zero exit" is not attribution.
    $died = [];
    foreach ($lines as $line) {
        $t = trim(preg_replace('/\s+/', ' ', strip_tags($line)));
        if (stripos($t, 'FAILED') !== false) {
            $died[] = $t;
        }
        if (stripos($t, 'Tests:') !== false) {
            $summary = $t;
        }
    }

    printf("mutant %s  %-64s exit=%d  %s\n", $id, $c['what'], $code,
        $code !== 0 ? 'CAUGHT' : '*** SURVIVED — the boundary is not being tested ***');
    foreach (array_slice($died, 0, 3) as $d) {
        printf("     died: %s\n", mb_substr($d, 0, 120));
    }
    printf("     %s\n", $summary ?? 'no summary line found — do not trust this verdict');
} finally {
    file_put_contents($scope, $baseline);
    clearstatcache(true, $scope);
    $restored = file_get_contents($scope) === $baseline;
}

if (! $restored) {
    fwrite(STDERR, "THE SOURCE WAS NOT RESTORED — repair app/Support/CollegeScope.php by hand\n");
    exit(1);
}

exit($code !== 0 ? 0 : 1);
