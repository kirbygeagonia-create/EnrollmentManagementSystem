<?php

/**
 * Prove lane 25's five tests are measuring something.
 *
 * The new file claims each catalog screen answers to the right the hub map names for it. That claim
 * is only worth having if breaking the mapping breaks the test, so each mutant edits one real source
 * file, runs only ReferenceDataScreenAccessTest, and requires a non-zero exit. Five mutants, one per
 * claim:
 *
 *   M1 a screen asks its neighbour's ability  (the copy-paste the register said nobody had tested)
 *   M2 the map loses a screen                 (a catalog the hub can never link)
 *   M3 the map names a route that is gone     (a tile that throws when clicked)
 *   M4 the hub is offered to a view-only desk (chrome promising a page with nothing on it)
 *   M5 the fixture account stops holding the right the test says it holds
 *
 * Each mutant is checked to have landed (the file must actually differ from the in-memory baseline)
 * and every file is restored from that baseline in a finally, so a crash mid-run cannot leave the
 * working tree carrying a mutant.
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';

$files = [
    'controller' => $root.'/app/Http/Controllers/Admin/ReferenceDataController.php',
    'sections' => $root.'/app/Support/ReferenceDataSections.php',
    'seeder' => $root.'/database/seeders/RbacSeeder.php',
];

$baseline = [];
foreach ($files as $key => $path) {
    if (! is_file($path)) {
        fwrite(STDERR, "MISSING SOURCE: {$path}\n");
        exit(1);
    }
    $baseline[$key] = file_get_contents($path);
}

$mutants = [
    [
        'name' => 'M1 courses() asks the Majors ability',
        'file' => 'controller',
        // Four methods carry this exact line; only the first belongs to the GET screen. Replaced by
        // position, and the harness prints the line it landed on so "inside courses()" is shown, not
        // assumed. An earlier draft tried to widen the find string with newlines and matched nothing,
        // because these files are CRLF and a \n in a find-string silently misses.
        'find' => "\$this->authorize('manageCourses', Courses::class);",
        'fix' => "\$this->authorize('manageMajors', Courses::class);",
        'occurrence' => 0,
        'must_be_in' => 'public function courses(',
    ],
    [
        'name' => 'M2 the map loses the Blocks screen',
        'file' => 'sections',
        'find' => "        'admin.reference-data.blocks' => 'refdata.blocks.manage',\n",
        'fix' => '',
    ],
    [
        'name' => 'M3 the map names a route the router does not have',
        'file' => 'sections',
        'find' => "'admin.reference-data.rooms' => 'refdata.rooms.manage',",
        'fix' => "'admin.reference-data.roomies' => 'refdata.rooms.manage',",
    ],
    [
        'name' => 'M4 the hub is offered to a desk with nothing to maintain',
        'file' => 'sections',
        'find' => "            && static::manageable(\$user) !== [];",
        'fix' => "            && true;",
    ],
    [
        'name' => 'M5 Staff stops holding refdata.view',
        'file' => 'seeder',
        'find' => "            'refdata.view', 'user.view', 'enrollment.subjects.confirm',",
        'fix' => "            'user.view', 'enrollment.subjects.confirm',",
    ],
];

$survivors = [];

try {
    foreach ($mutants as $m) {
        $src = $baseline[$m['file']];
        $hits = substr_count($src, $m['find']);

        if (! isset($m['occurrence'])) {
            if ($hits !== 1) {
                printf("%-58s NOT PLANTED (find matched %d times)\n", $m['name'], $hits);
                $survivors[] = $m['name'].' — not planted';

                continue;
            }
            $mutated = str_replace($m['find'], $m['fix'], $src);
        } else {
            // Replace one occurrence by position, then prove it is the occurrence the mutant means.
            $needlePos = $m['occurrence'] === 0
                ? strpos($src, $m['find'])
                : (function () use ($src, $m) {
                    $p = 0;
                    for ($i = 0; $i <= $m['occurrence']; $i++) {
                        $p = strpos($src, $m['find'], $p);
                        if ($p === false) {
                            return false;
                        }
                        $p++;
                    }

                    return $p - 1;
                })();

            if ($needlePos === false) {
                printf("%-58s NOT PLANTED (occurrence %d is not there)\n", $m['name'], $m['occurrence']);
                $survivors[] = $m['name'].' — not planted';

                continue;
            }

            $enclosing = strrpos(substr($src, 0, $needlePos), $m['must_be_in']);
            if ($enclosing === false) {
                printf("%-58s NOT PLANTED (no '%s' above the target line)\n", $m['name'], $m['must_be_in']);
                $survivors[] = $m['name'].' — not planted';

                continue;
            }

            $mutated = substr_replace($src, $m['fix'], $needlePos, strlen($m['find']));
            printf("%-58s planted at byte %d, inside the method starting at byte %d (%s)\n",
                $m['name'], $needlePos, $enclosing, $m['must_be_in']);
        }

        if ($mutated === $src) {
            printf("%-58s NOT PLANTED (write produced an identical file)\n", $m['name']);
            $survivors[] = $m['name'].' — not planted';

            continue;
        }

        file_put_contents($files[$m['file']], $mutated);
        clearstatcache(true, $files[$m['file']]);
        if (file_get_contents($files[$m['file']]) === $src) {
            printf("%-58s NOT PLANTED (the file on disk did not change)\n", $m['name']);
            $survivors[] = $m['name'].' — disk refused the write';

            continue;
        }

        exec(sprintf('cd "%s" && "%s" artisan test tests/Feature/Admin/ReferenceDataScreenAccessTest.php 2>&1',
            $root, $php), $lines, $code);
        $out = implode("\n", $lines);
        $lines = [];

        $flat = preg_replace('/\s+/', ' ', $out);
        $caught = $code !== 0;

        // Pest marks each failing test on its own line; reading those lines tells us WHICH claim died.
        // "the suite got angrier" is not evidence, "this test failed" is. When stdout is piped the
        // per-test cross glyph is not always emitted, so both spellings are collected.
        $failing = [];
        foreach (explode("\n", $out) as $line) {
            $t = trim(preg_replace('/\s+/', ' ', $line));
            if (preg_match('/⨯\s+(.+?)\s+[\d.]+m?s\s*$/u', $t, $m1) === 1) {
                $failing[] = $m1[1];
            } elseif (str_contains($t, 'FAILED') && str_contains($t, 'ReferenceDataScreenAccessTest')) {
                $name = trim(explode('>', $t)[1] ?? '');
                if ($name !== '' && ! in_array($name, $failing, true)) {
                    $failing[] = $name;
                }
            }
        }
        if ($failing === []) {
            preg_match('/Tests:\s+(\d+)\s+failed/', $flat, $s);
            $failing = ['. exit '.$code.(isset($s[1]) ? ', summary says '.$s[1].' failed' : '').', no per-test line'];
        }

        $detail = $caught ? implode(' | ', array_slice($failing, 0, 3)) : substr($flat, -140);

        printf("%-58s exit=%d %s  %s\n", $m['name'], $code, $caught ? 'CAUGHT  ' : '*** SURVIVED ***', $detail);

        if (! $caught) {
            $survivors[] = $m['name'];
        }
    }
} finally {
    foreach ($files as $key => $path) {
        file_put_contents($path, $baseline[$key]);
    }

    $dirty = [];
    foreach ($files as $key => $path) {
        if (file_get_contents($path) !== $baseline[$key]) {
            $dirty[] = $key;
        }
    }

    echo $dirty === []
        ? "\nall three source files restored byte-for-byte\n"
        : "\nNOT RESTORED: ".implode(', ', $dirty)." — fix by hand before anything else\n";

    if ($dirty !== []) {
        exit(1);
    }
}

echo $survivors === []
    ? "5/5 mutants caught\n"
    : 'SURVIVORS: '.implode('; ', $survivors)."\n";
exit($survivors === [] ? 0 : 1);
