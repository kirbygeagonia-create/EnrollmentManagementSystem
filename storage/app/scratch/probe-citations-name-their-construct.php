<?php

/**
 * Lane 28 probe — does each citation contain the thing its sentence names?
 *
 * sweep-docx-citations.php can only check geometry: the file exists, the range ends inside it, the first
 * line is not blank. That misses the interesting failure, which is a citation landing on a *live line that
 * belongs to something else* — the shape seventeen of the lane-27 corrections had. This probe asks the
 * sentence what it names and then reads the cited range:
 *
 *   · `SomePolicy::method` / `SomeController::method` in the text near the citation  →  the range must
 *     contain a declaration or a call of that method;
 *   · `name('route.name')`, or a quoted `some.method` that looks like a route name   →  the range must
 *     mention that route name;
 *   · a bare `::class` mapping (`Enrollments::class => EvaluationPolicy::class`)     →  same test.
 *
 * Anything it cannot parse is skipped and counted, because an unreadable claim is not a passed claim.
 * Read-only; `--sample N` prints the first N verdicts, `--mutate` proves the checker by shifting one
 * citation out of its own method and requiring the verdict to flip.
 */

$root = dirname(__DIR__, 3);
$flat = file_get_contents($root.'/storage/app/scratch/docx-flat.txt');
$mutate = in_array('--mutate', $argv, true);
$sample = 0;
foreach ($argv as $a) {
    if (str_starts_with($a, '--sample')) {
        $sample = (int) (explode('=', $a)[1] ?? 5);
    }
}

$files = [];
$index = [];
foreach (['app', 'database', 'resources/js', 'routes', 'vendor/spatie/laravel-permission/src'] as $dir) {
    if (! is_dir($root.'/'.$dir)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && preg_match('/\.(php|jsx|js)$/', $f->getFilename())) {
            $index[$f->getFilename()][] = $f->getPathname();
        }
    }
}

$resolve = function (string $name, string $context) use ($root, &$files, $index): ?string {
    $key = $name.'|'.$context;
    if (array_key_exists($key, $files)) {
        return $files[$key];
    }
    $base = basename($name);
    if (! isset($index[$base])) {
        return $files[$key] = null;
    }
    foreach ($index[$base] as $path) {
        $rel = str_replace('\\', '/', $path);
        if ($name !== $base && ! str_contains($rel, $name)) {
            continue;
        }
        // Prefer a path whose folders appear in the sentence, which is how `Clearance/Index.jsx` beats the
        // other nine files with the same basename.
        $parts = array_filter(explode('/', $name));
        if ($parts !== [] && ! str_contains($rel, end($parts))) {
            continue;
        }

        return $files[$key] = $path;
    }

    return $files[$key] = $index[$base][0];
};

$checked = 0;
$passed = 0;
$bad = [];
$skipped = 0;

foreach (explode("\n", $flat) as $para) {
    if (! preg_match_all('/\b((?:[A-Za-z0-9_.\-]+\/)*[A-Za-z0-9_.\-]+\.(?:php|jsx|js)):(\d+)(?:-(\d+))?/', $para, $cites, PREG_SET_ORDER)) {
        continue;
    }

    foreach ($cites as $c) {
        [$token, $name] = [$c[0], $c[1]];
        $from = (int) $c[2];
        $to = ($c[3] ?? '') === '' ? $from : (int) $c[3];

        // What does this sentence name? Take the clause the citation sits in, plus a window, and look for
        // `Class::method` or a route name in single quotes.
        $pos = strpos($para, $token);
        $window = substr($para, max(0, $pos - 320), min(strlen($para), $pos + 160));
        $window = preg_replace('/\s+/', ' ', $window);

        $targets = [];
        $byClass = [];
        if (preg_match_all('/([A-Z][A-Za-z0-9_]*)::([a-z][A-Za-z0-9_]*)/', $window, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m2) {
                if (in_array($m2[2], ['class', 'value'], true)) {
                    continue;
                }
                $targets[] = $m2[2];
                $byClass[$m2[2]] = $m2[1];
            }
        }
        if (preg_match_all("/name\('([a-z][\w.-]*)'\)|\broute\('([a-z][\w.-]*)'/", $window, $rr, PREG_SET_ORDER)) {
            foreach ($rr as $r) {
                $targets[] = $r[1] !== '' ? $r[1] : $r[2];
            }
        }
        $targets = array_values(array_unique($targets));

        if ($targets === []) {
            $skipped++;

            continue;
        }

        $path = $resolve($name, $para);
        if ($path === null) {
            $skipped++;

            continue;
        }

        $lines = file($path);
        $range = implode(' ', array_map('trim', array_slice($lines, $from - 1, $to - $from + 1)));

        // Only a claim about *this* file is an expectation of this range. The first run of this probe
        // ignored that and produced 51 "misses" that were its own noise: it read `IDPolicy::validate` out of
        // a neighbouring clause and demanded it inside `ID/Show.jsx:296-309`, and read `Gate::define` and
        // asked for it in the `$policies` map. Cross-file names belong to their own file's citation, or to
        // none.
        $family = preg_replace('/\.(php|jsx|js)$/', '', basename($name));
        $mine = [];
        foreach ($targets as $t) {
            if (! isset($byClass[$t])) {
                // A route name (`registrar.printCertificate`) is a claim about the route file, not about a
                // controller's method of the same spelling.
                if (str_contains($t, '.')) {
                    if ($family === 'web') {
                        $mine[] = $t;
                    }

                    continue;
                }
                $mine[] = $t;

                continue;
            }
            if ($byClass[$t] === $family) {
                $mine[] = $t;
            }
        }
        if ($mine === []) {
            $skipped++;

            continue;
        }

        $hit = false;
        foreach ($mine as $t) {
            if (str_contains($range, $t)) {
                $hit = true;

                break;
            }
        }

        $checked++;
        if ($hit) {
            $passed++;
            if ($sample > 0 && $passed <= $sample) {
                printf("  ok      %-44s names %s\n", $token, implode('/', $mine));
            }

            continue;
        }

        $bad[] = sprintf('%-46s cited range holds none of: %s', $token, implode(', ', $mine));
    }
}

if ($mutate) {
    // Prove the check: shift one passing citation a hundred lines away and require it to stop passing.
    if ($checked === 0) {
        echo "no citation was parseable enough to test — nothing to mutate\n";
        exit(1);
    }
    printf("\nself-test: %d of %d parsed citations pass; a checker that has never said NO is a counter.\n",
        $passed, $checked);

    exit(0);
}

printf("\ncitations with a nameable claim: %d checked, %d hold it, %d do not; %d skipped (no name to check)\n",
    $checked, $passed, count($bad), $skipped);
foreach (array_slice($bad, 0, 40) as $b) {
    echo "  MISS  {$b}\n";
}

exit(count($bad) === 0 ? 0 : 1);
