<?php

/**
 * The construct check done properly: does each cited range lie inside the method its sentence names?
 *
 * The first version asked only whether the method's *name* appeared in the range, which is wrong in the
 * common case: a document citing `EnrollmentStateMachine.php:62-76` for what `transition()` does is
 * pointing into the body, and the word "transition" is on the declaration line only. That version flagged
 * 14 candidates and most of them were its own confusion.
 *
 * So compute each method's real extent — declaration line through its closing brace, by brace counting —
 * and require the cited range to sit inside it, or to contain a call to the named method. Anything left
 * after that is a citation the document genuinely has wrong.
 *
 * Read-only. Exit non-zero when a real miss survives.
 */

$root = dirname(__DIR__, 3);
$flat = file_get_contents($root.'/storage/app/scratch/docx-flat.txt');

$index = [];
foreach (['app', 'database', 'resources/js', 'routes'] as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && preg_match('/\.(php|jsx|js)$/', $f->getFilename())) {
            $index[$f->getFilename()][] = $f->getPathname();
        }
    }
}

/** Every `function name(` in the file, with its extent found by counting braces from the declaration. */
$methodsOf = function (string $path): array {
    static $cache = [];
    if (isset($cache[$path])) {
        return $cache[$path];
    }
    $lines = file($path);
    $out = [];
    foreach ($lines as $i => $line) {
        if (preg_match('/function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $line, $m) !== 1) {
            continue;
        }
        $depth = 0;
        $started = false;
        for ($j = $i; $j < count($lines); $j++) {
            $depth += substr_count($lines[$j], '{') - substr_count($lines[$j], '}');
            if ($depth > 0) {
                $started = true;
            }
            if ($started && $depth === 0) {
                $out[$m[1]] = [$i + 1, $j + 1];

                break;
            }
        }
    }
    $cache[$path] = $out;

    return $out;
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
        $name = $c[1];
        $from = (int) $c[2];
        $to = ($c[3] ?? '') === '' ? $from : (int) $c[3];

        $pos = strpos($para, $c[0]);
        $window = preg_replace('/\s+/', ' ', substr($para, max(0, $pos - 340), min(strlen($para), $pos + 170)));

        // Only a method named as `SameClass::method` where SameClass is the cited file counts, and route
        // names count only against routes/web.php — the two associations the first version got wrong.
        $base = basename($name);
        $family = preg_replace('/\.(php|jsx|js)$/', '', $base);
        $wanted = [];
        if (preg_match_all('/([A-Z][A-Za-z0-9_]*)(?:\.jsx|\.php)?::([a-z][A-Za-z0-9_]*)/', $window, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m) {
                if (in_array($m[2], ['class', 'value'], true)) {
                    continue;
                }
                $cls = $m[1];
                // `WorkflowService::stepsFor`, `Registrar/Policy` style prefixes, or the file itself.
                if ($cls === $family || str_contains($family, $cls) || str_contains($cls, $family)) {
                    $wanted[] = $m[2];
                }
            }
        }

        if ($wanted === []) {
            $skipped++;

            continue;
        }

        $cands = $index[$base] ?? [];
        if ($cands === []) {
            $skipped++;

            continue;
        }
        $path = $cands[0];
        if (count($cands) > 1) {
            // Prefer the candidate whose folder trail appears in the document's own spelling.
            foreach ($cands as $p) {
                if (! str_contains($name, '/') || str_contains(str_replace('\\', '/', $p), $name)) {
                    $path = $p;

                    break;
                }
            }
        }

        $methods = $methodsOf($path);
        $lines = file($path);

        $ok = false;
        $why = '';
        foreach (array_unique($wanted) as $w) {
            if (isset($methods[$w])) {
                [$d, $e] = $methods[$w];
                if ($from >= $d && $to <= $e) {
                    $ok = true;
                    $why = "inside {$w}() at {$d}-{$e}";

                    break;
                }
            }
            // A call to the method also satisfies the sentence — the document often cites where a thing is
            // *invoked*, not where it is declared.
            foreach (range($from, $to) as $n) {
                if (preg_match('/(->|::|\/\*\s*)'.preg_quote($w, '/').'\s*\(/', $lines[$n - 1] ?? '')) {
                    $ok = true;
                    $why = "calls {$w}() at {$n}";

                    break 2;
                }
            }
        }

        $checked++;
        if ($ok) {
            $passed++;

            continue;
        }

        $bad[] = sprintf('%-52s names %s; the range holds neither the body nor a call%s',
            $c[0], implode('/', array_unique($wanted)),
            isset($methods[implode('/', array_unique($wanted))])
                ? ' (declared at '.$methods[array_key_first($methods)].'[0]-'.$methods[array_key_first($methods)][1].')'
                : '');
    }
}

printf("citations checked against a named method: %d   hold it: %d   miss: %d   skipped (no method named): %d\n",
    $checked, $passed, count($bad), $skipped);
foreach ($bad as $b) {
    echo "  MISS  {$b}\n";
}

exit(count($bad) === 0 ? 0 : 1);
