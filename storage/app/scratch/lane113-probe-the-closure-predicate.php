<?php

/** Lane 113: does `student_load_path_columns()` read the two closure sites and refuse the shapes that are not narrowed? */
$root = dirname(__DIR__, 3);
require __DIR__.'/students-column-guard.php';

$cases = [
    ['leaf', "'enrollment.student:studentId,schoolIdNumber',", ['studentId', 'schoolIdNumber']],
    ['closure-no-select', "'student' => fn (\$q) => \$q->with('addresses'),", null],
    ['plain-bare', "'student',", null],
    ['neighbour-select', "'student' => fn (\$q) => \$q->select('a','b'), 'course' => fn (\$q) => \$q->select('x')", ['a', 'b']],
    ['closure-array-select', "'student' => fn (\$q) => \$q->select(['a','b','c'])->with('guardians'),", ['a', 'b', 'c']],
];
$bad = 0;
foreach ($cases as [$name, $src, $want]) {
    $path = trim(explode(',', substr($src, 1))[0]);
    $path = trim(explode("'", substr($src, 1))[0]);
    $at = strlen("'".$path."'");
    $got = student_load_path_columns($path, substr($src, $at));
    $ok = $got === $want;
    printf("  %s %-22s want %-14s got %s\n", $ok ? 'ok  ' : 'BAD ', $name, json_encode($want), json_encode($got));
    $bad += $ok ? 0 : 1;
}

// the two real sites, read out of the working tree
foreach ([
    ['app/Http/Controllers/Registrar/RegistrarController.php', ['studentId', 'schoolIdNumber', 'lastName', 'firstName', 'middleName', 'suffix']],
    ['app/Http/Controllers/Clearance/ClearanceController.php', ['studentId', 'schoolIdNumber', 'lastName', 'firstName', 'middleName']],
] as [$rel, $want]) {
    $src = (string) file_get_contents($root.'/'.$rel);
    $p = strpos($src, "'student' => fn");
    if ($p === false) {
        echo '  BAD  real-site-not-found '.basename($rel)."\n";
        $bad++;

        continue;
    }
    $path = 'student';
    $tail = substr($src, $p + strlen("'student'"));
    $got = student_load_path_columns($path, $tail);
    $ok = $got === $want;
    printf("  %s %-22s %-30s got %s\n", $ok ? 'ok  ' : 'BAD ', basename($rel), json_encode($want), json_encode($got));
    $bad += $ok ? 0 : 1;
}
printf("%s: %d case(s), %d bad\n", $bad === 0 ? 'PREDICATE OK' : 'PREDICATE BROKEN', count($cases) + 2, $bad);
exit($bad === 0 ? 0 : 1);
