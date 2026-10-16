<?php

/**
 * One mutant, one process: apply $argv[1], run the file, restore, print which test died.
 *
 * Driven from the shell one mutant at a time because a single process that applied five mutants in
 * a row reported the FIRST test as failing under every one of them — including mutants that cannot
 * reach it — while the same mutant applied in its own process failed exactly the one test that
 * claims it. Which of the two is lying had to be settled, and the answer is that attribution from a
 * reused process is not evidence.
 *
 * Restoration is from an in-memory baseline in a finally, and the script refuses to report anything
 * unless the mutant was planted (the file actually differs) and the source is verifiably back.
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';

$mutants = [
    'M1' => [
        'what' => 'courses() asks the Majors ability',
        'file' => 'app/Http/Controllers/Admin/ReferenceDataController.php',
        'find' => "\$this->authorize('manageCourses', Courses::class);",
        'fix' => "\$this->authorize('manageMajors', Courses::class);",
        'occurrence' => 0,
        'expect_test' => 'every catalog screen opens',
    ],
    'M2' => [
        'what' => 'the map loses the Blocks screen',
        'file' => 'app/Support/ReferenceDataSections.php',
        'find' => "        'admin.reference-data.blocks' => 'refdata.blocks.manage',\n",
        'fix' => '',
        'occurrence' => 0,
        'expect_test' => 'every route the hub map names',
    ],
    'M3' => [
        'what' => 'the map names a route the router does not have',
        'file' => 'app/Support/ReferenceDataSections.php',
        'find' => "'admin.reference-data.rooms' => 'refdata.rooms.manage',",
        'fix' => "'admin.reference-data.roomies' => 'refdata.rooms.manage',",
        'occurrence' => 0,
        'expect_test' => 'every route the hub map names',
    ],
    'M4' => [
        'what' => 'the hub is offered to a desk with nothing to maintain',
        'file' => 'app/Support/ReferenceDataSections.php',
        'find' => '            && static::manageable($user) !== [];',
        'fix' => '            && true;',
        'occurrence' => 0,
        'expect_test' => 'the hub is offered only where',
    ],
    'M5' => [
        'what' => 'Staff stops holding refdata.view',
        'file' => 'database/seeders/RbacSeeder.php',
        // The first attempt used the Dean's grant line, which Staff does not read: the mutant ran, the
        // suite stayed green at 5 passed, and it was reported as "caught" by a parser that had matched
        // nothing. This is the Staff block, anchored on its closing bracket because two roles' lines
        // otherwise share the same text.
        'find' => "            'id.view', 'refdata.view', 'user.view',\n        ]);",
        'fix' => "            'id.view', 'user.view',\n        ]);",
        'occurrence' => 0,
        'expect_test' => 'the hub is offered only where',
    ],
];

$id = $argv[1] ?? '';
if (! isset($mutants[$id])) {
    fwrite(STDERR, "usage: php ".basename(__FILE__)." M1..M5\n");
    exit(2);
}

$m = $mutants[$id];
$path = $root.'/'.$m['file'];
$baseline = file_get_contents($path);

$code = 1;
$failing = [];
$summary = '';

try {
    $needle = strpos($baseline, $m['find']);
    for ($i = 0; $i < $m['occurrence']; $i++) {
        $needle = strpos($baseline, $m['find'], $needle + 1);
    }
    if ($needle === false) {
        fwrite(STDERR, sprintf("%s NOT PLANTED: find string absent\n", $id));
        exit(3);
    }

    $mutated = substr_replace($baseline, $m['fix'], $needle, strlen($m['find']));
    if ($mutated === $baseline) {
        fwrite(STDERR, sprintf("%s NOT PLANTED: the file is unchanged\n", $id));
        exit(3);
    }

    file_put_contents($path, $mutated);
    clearstatcache(true, $path);
    if (file_get_contents($path) === $baseline) {
        fwrite(STDERR, sprintf("%s NOT PLANTED: disk refused the write\n", $id));
        exit(3);
    }

    exec(sprintf('cd "%s" && "%s" artisan test tests/Feature/Admin/ReferenceDataScreenAccessTest.php 2>&1',
        $root, $php), $lines, $code);

    // The log goes to a file and is read by eye, not by a regex in this script: two attempts at
    // parsing Pest's own glyphs and summary line here both silently matched nothing, and a checker
    // whose "no failures named" is indistinguishable from "no failures happened" is worse than none.
    file_put_contents($root."/storage/app/scratch/mut-{$id}.log", implode("\n", $lines));

    $summary = '';
    foreach ($lines as $line) {
        if (str_contains($line, 'Tests:')) {
            $summary = trim(preg_replace('/\s+/', ' ', $line));
        }
    }
} finally {
    file_put_contents($path, $baseline);
    clearstatcache(true, $path);
    $restored = file_get_contents($path) === $baseline;
}

if (! $restored) {
    fwrite(STDERR, "THE SOURCE WAS NOT RESTORED — fix {$path} by hand before anything else\n");
    exit(1);
}

printf("%s  %-52s exit=%d  failed: %s\n",
    $id,
    $m['what'],
    $code,
    $failing === [] ? '(none named)' : implode(' | ', $failing));
printf("    summary: %s | expected to break: %s | source restored: yes\n",
    $summary === '' ? '(no summary line)' : $summary, $m['expect_test']);

$hit = false;
foreach ($failing as $name) {
    if (str_contains($name, $m['expect_test'])) {
        $hit = true;

        break;
    }
}

// A mutant that breaks the wrong test, or breaks nothing, proves nothing about this claim.
$ok = $code !== 0 && $hit;
printf("    %s\n", $ok ? 'ATTRIBUTED' : '*** NOT ATTRIBUTED to the claim it mutates ***');
exit($ok ? 0 : 1);
