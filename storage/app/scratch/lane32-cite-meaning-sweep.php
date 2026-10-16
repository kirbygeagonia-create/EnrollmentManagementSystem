<?php

/**
 * Lane 32: sweep every `File.php:NN` citation in the authority docx BY MEANING, not only by line.
 *
 * `sweep-docx-citations.php` proves a citation names a file that exists, ends inside it, and lands on a line
 * with something on it. It cannot see the failure mode that actually recurs here: a range that stayed in
 * bounds and now sits inside a *different method*, because the file grew above it. That is why every pass so
 * far re-read citations by hand, and why the hand pass found 13 drifts once.
 *
 * For each citation this asks whether the construct the surrounding prose names lives in the cited range or
 * encloses it. Attribution is by NEAREST citation, because the document glosses ranges in both directions:
 * `signStepByOffice() :150-174` names the range that follows it, while `:734-740 builds one named
 * requirement line per office from ProvisionalClearanceRequirements::NAMES` names the range that precedes it.
 * A first version that read each symbol as belonging to the citation after it flagged `:62-104` for NAMES —
 * a document defect that did not exist, manufactured by the tool.
 *
 * A symbol the cited file never mentions is skipped on purpose: prose cites framework verbs (`array_values`,
 * `save`, Laravel's own methods) and a construct living in a *different* class is not this file's business.
 * What is reported is the narrow thing: the name is real, it is in this file, and the cited lines are not
 * where it is.
 *
 * It is a SHORTLIST, not a verdict, and every flag has to be MEASURED before it is called noise: the item
 * saying `OfficeId::Clinic` on a line that stores a bare `11` looked like a case-sensitivity artifact and was
 * actually the document asserting code the build stopped writing on 2026-10-01 (fixed by pass 118). Four
 * classes remain after that cleanup and are genuinely noise — two ranges glossed in prose order, a label whose
 * nearest range sits across a comma or semicolon, and a name belonging to some other class.
 *
 * usage: php storage/app/scratch/lane32-cite-meaning-sweep.php [path-to-flat-dump] [--verbose]
 */

$root = dirname(__DIR__, 3);
$verbose = in_array('--verbose', $argv, true);
$flat = $root.'/storage/app/scratch/docx-flat.txt';
foreach (array_slice($argv, 1) as $a) {
    if (! str_starts_with($a, '-')) {
        $flat = $a;
    }
}
is_file($flat) || exit("no dump at {$flat} — run dump-docx-flat.php first\n");

$text = file_get_contents($flat);

$cache = [];

/**
 * Same resolver the line sweep uses (basename, preferring a path the document itself qualified), but backed
 * by one eager walk of the project directories. The mutation harness runs this tool once per mutant, and
 * walking `app/` from scratch for every unresolved basename was what made a single run take minutes.
 * `vendor/` stays lazy: it is only reached for the few citations of framework internals, and indexing it
 * would cost more than it saves.
 */
$walk = function (string $dir) use ($root): array {
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile()) {
            $out[str_replace('\\', '/', $f->getPathname())] = true;
        }
    }

    return $out;
};

$eager = [];
foreach (['app', 'database', 'resources', 'routes', 'tests', 'bootstrap', 'config', 'tools', 'public', 'Documentation'] as $dir) {
    if (is_dir($root.'/'.$dir)) {
        $eager += $walk($dir);
    }
}
$lazy = null;

$score = function (string $path, string $key): int {
    return str_contains($path, $key) ? 100 : (count(array_filter(explode('/', $key), fn ($p) => str_contains($path, $p))) - 1);
};

/** Returns a forward-slashed absolute path; callers strip `$root/` off it to get a repository-relative name. */
$resolve = function (string $name) use (&$cache, $eager, &$lazy, $walk, $score): ?string {
    $key = str_replace('\\', '/', $name);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $base = basename($key);
    $best = null;
    $bestScore = -1;

    $pick = function (array $paths) use ($base, $key, $score, &$best, &$bestScore): void {
        foreach (array_keys($paths) as $path) {
            if (basename($path) !== $base) {
                continue;
            }
            $s = $score($path, $key);
            if ($s > $bestScore) {
                [$bestScore, $best] = [$s, $path];
            }
        }
    };

    $pick($eager);
    if ($best === null) {
        $lazy ??= $walk('vendor');
        $pick($lazy);
    }

    return $cache[$key] = $best;
};

$linesOf = function (string $rel) use ($root): array {
    static $held = [];
    if (! isset($held[$rel])) {
        $held[$rel] = file($root.'/'.$rel) ?: [];
    }

    return $held[$rel];
};

/** The construct a line sits inside: nearest `function x(` above it, else nearest `class X`. */
$enclosing = function (array $lines, int $at): string {
    $class = '';
    for ($i = $at - 1; $i >= 0; $i--) {
        $l = trim($lines[$i]);
        if ($class === '' && preg_match('/^\s*(?:final\s+|abstract\s+)?class\s+([A-Za-z0-9_]+)/', $l, $m)) {
            $class = $m[1];
        }
        if (preg_match('/\bfunction\s+([A-Za-z0-9_]+)\s*[({\n]/', $l, $m)) {
            return $m[1].'()';
        }
        if (preg_match('/^const\s+([A-Za-z0-9_]+)/', $l, $m)) {
            return $m[1].' (const)';
        }
        if (preg_match('/\b(?:const|let|var)\s+([A-Z][A-Za-z0-9_]*)\s*=\s*(?:\(\s*\)|[A-Za-z0-9_$]+\s*=>|function)/', $l, $m)) {
            return $m[1].' (component)';
        }
    }

    return $class !== '' ? $class.' (file scope)' : '';
};

$fileInner = '(?:[A-Za-z0-9_.\-]+\/)*[A-Za-z0-9_.\-]+\.(?:php|jsx|js|json|neon|xml|md)';
// One pattern, two alternatives: a citation that names its file, and a bare range that inherits the file the
// same unit named a moment earlier — `(Preflight.php:83-99, the two new rules at :117-132 and :147-174)`.
$citeRe = '/\b('.$fileInner.':)(\d+)(?:-(\d+))?|:(\d+)(?:-(\d+))?/';
// A construct named by prose: `foo()`, or `Foo::bar` / `Foo::$bar` (the member is the thing to look for).
$symRe = '/\b([A-Za-z_][A-Za-z0-9_]*)\(\)|\b[A-Za-z_][A-Za-z0-9_]*::\$?([A-Za-z_][A-Za-z0-9_]*)/';

// Units: a line first (a table row is one line in the generated dump, cells separated by ` | `), then a
// sentence inside it. Whole-paragraph units made one cell's gloss bleed across the table.
$units = [];
foreach (preg_split('/\r?\n/', $text) ?: [] as $line) {
    foreach (preg_split('/(?<=[.。;]) (?=[A-Z(])| \| /u', $line) ?: [] as $u) {
        if (trim($u) !== '') {
            $units[] = $u;
        }
    }
}

$flags = [];
$checked = 0;
$attributed = 0;

foreach ($units as $unit) {
    if (! str_contains($unit, ':')) {
        continue;
    }
    if (! preg_match_all($citeRe, $unit, $toks, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        continue;
    }

    $cites = [];
    $namedFile = null;
    foreach ($toks as $x) {
        $x += [null, null, null, null, null, null];
        $token = $x[0][0];
        $at = $x[0][1];
        if (($x[1] ?? null) !== null && $x[1][1] !== -1) {
            $namedFile = ['file' => rtrim($x[1][0], ':'), 'from' => (int) $x[2][0],
                'to' => (($x[3][1] ?? -1) === -1) ? (int) $x[2][0] : (int) $x[3][0]];
        } elseif (($x[4] ?? null) !== null && $x[4][1] !== -1 && $namedFile !== null) {
            $namedFile = ['file' => $namedFile['file'], 'from' => (int) $x[4][0],
                'to' => (($x[5][1] ?? -1) === -1) ? (int) $x[4][0] : (int) $x[5][0]];
        } else {
            continue;
        }
        $cites[] = $namedFile + ['start' => $at, 'end' => $at + strlen($token)];
    }

    if ($cites === [] || ! preg_match_all($symRe, $unit, $syms, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        continue;
    }

    foreach ($syms as $s) {
        $name = ($s[1][1] ?? -1) !== -1 ? $s[1][0] : ($s[2][0] ?? '');
        if (strcasecmp($name, 'class') === 0) {
            continue;   // `Foo::class` names no construct in this file; it is PHP's own keyword
        }
        if ($name === '') {
            continue;
        }
        $sStart = $s[0][1];
        $sEnd = $sStart + strlen($s[0][0]);

        // Attribution, by the two orders this document actually uses:
        //   LABEL first — `signStepByOffice() :150-174`, `recordIssue() at :105`: nothing but spaces or the
        //     word `at` between the name and the range, so the name belongs to the range AFTER it.
        //   GLOSS after — `:734-740 builds one named requirement line per office from X::NAMES, and :62-104
        //     retires office 8`: the words following a range describe THAT range, and a comma, a closing
        //     parenthesis or a period means the run of words has already moved on to the next item.
        // Raw nearest-distance got both of these wrong: it hung `NAMES` on `:62-104` (six characters away,
        // but across a comma) and `logoDataUri` on `:31`, inventing two document defects that do not exist.
        $next = null;
        $prev = null;
        foreach ($cites as $c) {
            if ($c['start'] >= $sEnd && ($next === null || $c['start'] < $next['start'])) {
                $next = $c;
            }
            if ($c['end'] <= $sStart && ($prev === null || $c['end'] > $prev['end'])) {
                $prev = $c;
            }
        }
        $forwardGap = $next === null ? null : substr($unit, $sEnd, $next['start'] - $sEnd);
        // Two ways a name reaches forward: a short label (`signStepByOffice() :289-305`, `recordIssue() at :115`),
        // or a clause that OPENS a parenthesis the next citation sits inside — `confirmClearance() records the
        // officer's confirmation as the verdict's last clause ( :958-999 , permission … )`. Without the second,
        // the name fell back to the range before it and the sweep kept flagging a citation that was correct.
        $reachesForward = $forwardGap !== null
            && (
                (strlen(trim($forwardGap)) < 25 && ! preg_match('/[,.;)\\n]/', $forwardGap))
                || (str_contains($forwardGap, '(') && ! str_contains($forwardGap, ')'))
            );
        $best = $reachesForward ? $next : ($prev ?? $next);
        if ($best === null) {
            continue;
        }

        $path = $resolve($best['file']);
        if ($path === null) {
            continue;
        }
        $rel = str_replace('\\', '/', substr($path, strlen($root) + 1));
        $lines = $linesOf($rel);
        if ($best['to'] > count($lines)) {
            continue;
        }
        $checked++;
        $attributed++;

        $rangeText = implode('', array_slice($lines, $best['from'] - 1, $best['to'] - $best['from'] + 1));
        if (mb_stripos($rangeText, $name) !== false) {
            continue;                                   // named inside the cited lines: nothing to ask
        }
        $enc = $enclosing($lines, $best['from']);
        if ($enc !== '' && (str_starts_with($enc, $name) || $enc === $name.' (const)')) {
            continue;                                   // the citation opens on that very construct
        }
        if (! preg_match('/\b'.preg_quote($name, '/').'\b/i', implode('', $lines))) {
            continue;                                   // somebody else's construct, not this file's drift
        }

        $where = '';
        foreach ($lines as $i => $l) {
            if (preg_match('/\b'.preg_quote($name, '/').'\b/i', $l)) {
                $where = (string) ($i + 1);
                break;
            }
        }
        $flags[] = [$rel, $best, $name, $enc, $where, $unit];
    }
}

printf("%d citation range(s) checked against a named construct, %d pair(s) flagged for reading\n\n", $attributed, count($flags));

$seen = [];
foreach ($flags as [$rel, $nf, $name, $enc, $where, $unit]) {
    $key = $rel.':'.$nf['from'].'-'.$nf['to'].'|'.$name;
    if (! $verbose && isset($seen[$key])) {
        continue;
    }
    $seen[$key] = true;
    printf("  %-24s cites %s:%d-%d\n", $name, basename($rel), $nf['from'], $nf['to']);
    printf("      file      %s\n", $rel);
    printf("      encloses  %s\n", $enc === '' ? '(file scope)' : $enc);
    printf("      real      :%s in the same file\n", $where);
    if ($verbose) {
        printf("      unit      %s\n", str_replace("\n", ' ', substr(trim($unit), 0, 300)));
    }
}

printf("\n%d distinct flag(s), %d total. This is a queue to read, not a verdict.\n", count($seen), count($flags));

exit(count($flags) === 0 ? 0 : min(99, count($flags)));
