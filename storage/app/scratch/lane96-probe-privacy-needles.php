<?php

// Read-only probe: does every leg of mutate-g48-g49-privacy-tests.php find its needle exactly once?
// It exists because a stale needle costs a five-minute mutant run to discover, and the harness reports BAD
// rather than running long.

$root = dirname(__DIR__, 3);

$files = [
    $root.'/app/Models/Students.php',
    $root.'/app/Http/Controllers/StudentController.php',
];

$needles = [
    "    protected \$hidden = [\n        'passwordHash',\n    ];",
    "        'passwordHash',",
    "        if (request()->user()->can('clinic.view')) {\n            \$student->load('enrollments.clinicrecords');\n        }",
    "if (request()->user()->can('clinic.view')) {",
    "\$student->load('enrollments.clinicrecords');",
];

foreach ($needles as $n => $needle) {
    $total = 0;
    foreach ($files as $f) {
        $total += substr_count((string) file_get_contents($f), $needle);
    }
    printf("needle %d: %d occurrence(s) across the two files  [%s]\n", $n, $total, str_replace("\n", '\n', substr($needle, 0, 62)));
}
