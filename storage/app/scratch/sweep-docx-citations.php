<?php

/**
 * Global citation sanity sweep over the authority docx.
 *
 * Every `File.php:NN` or `File.jsx:NN-MM` the document cites must (a) name a file that exists, (b) end
 * inside that file, and (c) land on a line with something on it. None of that proves the sentence is
 * *about* the construct there — by-meaning reading is the human half, done per pass — but a citation that
 * fails any of these three is a false claim with no argument in its favour.
 *
 * Read-only; prints only problems, so a clean run prints one line.
 */

$root = dirname(__DIR__, 3);
$flat = $root.'/storage/app/scratch/docx-flat.txt';
$text = file_get_contents($flat);

$cache = [];

/**
 * Resolve by basename, preferring a path the document itself gave (`Clearance/Index.jsx` is not
 * `Accounting/Index.jsx`) and falling into vendor/ for the few cites of framework internals. Ambiguous
 * basenames are the reason an earlier run of this sweep reported a real-looking "past end of file" for a
 * citation the document had already qualified by folder: the resolver answered with whichever Index.jsx the
 * directory walk reached first.
 */
$resolve = function (string $name) use ($root, &$cache): ?string {
    $key = str_replace('\\', '/', $name);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $base = basename($key);
    $dirs = ['app', 'database', 'resources', 'routes', 'tests', 'bootstrap', 'config', 'tools', 'public', 'Documentation', 'vendor'];
    $best = null;
    $bestScore = -1;

    foreach ($dirs as $dir) {
        if (! is_dir($root.'/'.$dir)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (! $f->isFile() || $f->getFilename() !== $base) {
                continue;
            }
            $path = str_replace('\\', '/', $f->getPathname());
            // Score by how much of the document's prefix the path actually carries.
            $score = str_contains($path, $key) ? 100 : (count(array_filter(explode('/', $key), fn ($p) => str_contains($path, $p))) - 1);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $f->getPathname();
            }
        }
    }

    return $cache[$key] = $best;
};

// Hyphens belong in the name: `clearance-slip.blade.php:44` is ONE citation, and a pattern that stops at
// the hyphen reports "slip.blade.php" as a missing file — a defect in this sweep, not in the document.
preg_match_all('/\b((?:[A-Za-z0-9_.\-]+\/)*[A-Za-z0-9_.\-]+\.(?:php|jsx|js|json|neon|xml|md)):(\d+)(?:-(\d+))?/', $text, $m, PREG_SET_ORDER);

$problems = 0;
$checked = 0;
$missing = [];

foreach ($m as $x) {
    $name = $x[1];
    $from = (int) $x[2];
    $to = ($x[3] ?? '') === '' ? $from : (int) $x[3];
    $token = $name.':'.$from.($to > $from ? '-'.$to : '');

    $path = $resolve($name);
    if ($path === null) {
        $missing[$name] = ($missing[$name] ?? 0) + 1;

        continue;
    }

    $lines = file($path);
    $checked++;

    if ($to > count($lines)) {
        printf("  PAST END   %-46s cites %d-%d in a %d-line file\n", $token, $from, $to, count($lines));
        $problems++;

        continue;
    }
    if (trim($lines[$from - 1]) === '') {
        printf("  EMPTY LINE %-46s cites a blank line %d\n", $token, $from);
        $problems++;
    }
}

printf("\n%d citation(s) checked against %d file(s); %d line problem(s)%s\n",
    $checked, count(array_unique(array_keys($cache))), $problems,
    $missing === [] ? '' : '; '.count($missing).' name(s) not in the repository: '.implode(', ', array_slice(array_keys($missing), 0, 8)));

exit($problems === 0 && $missing === [] ? 0 : 1);
