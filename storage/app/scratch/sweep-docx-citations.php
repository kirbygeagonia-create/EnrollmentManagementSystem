<?php

/**
 * Global citation sanity sweep over the authority docx.
 *
 * Every `File.php:NN` or `File.jsx:NN-MM` the document cites must (a) name a file that exists, (b) end
 * inside that file, and (c) land on a line with something on it. None of that proves the sentence is
 * *about* the construct there — by-meaning reading is the human half, done per pass — but a citation that
 * fails any of these three is a false claim with no argument in its favour.
 *
 * Bare continuations are judged too. `ClearanceController.php:380 and :404` is one sentence and two
 * citations, and a pattern that needs a filename before the colon sees only the first — so when P-17 grew
 * `printSlip()` and pushed `downloadSlip()` from :404 to :408, row 2216 was re-pointed, row 713 was not, and
 * the run still printed 0 problems while `:404` had become a blank line. A bare `:NN` is now attributed to
 * the nearest file named BEFORE it on the same flat-dump line, within 160 characters; anything further, or
 * with no file before it at all, is printed as UNATTRIBUTED for reading rather than silently passed, because
 * an attribution invented by distance is exactly how a checker starts clearing a pointer that belongs to the
 * cell to its left.
 *
 * Read-only; prints only problems, so a clean run prints one line.
 */
$root = dirname(__DIR__, 3);
$flat = $root.'/storage/app/scratch/docx-flat.txt';
// --dump= is read, not merely accepted, so a harness can prove the sweep judges the bytes it is handed.
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--dump=')) {
        $flat = substr($arg, 7);
    }
}
if (! is_file($flat) || filesize($flat) < 1000) {
    // exit("text") prints and returns 0, so a refusal written that way is indistinguishable from a pass
    // by the caller that only reads the status — CI included.
    echo "REFUSED: the dump at {$flat} is missing or too short — run dump-docx-flat.php first.\n";
    exit(1);
}
$text = file_get_contents($flat);

$cache = [];

/**
 * Resolve by basename, preferring a path the document itself gave (`Clearance/Index.jsx` is not
 * `Accounting/Index.jsx`) and falling into vendor/ for the few cites of framework internals. Ambiguous
 * basenames are the reason an earlier run of this sweep reported a real-looking "past end of file" for a
 * citation the document had already qualified by folder: the resolver answered with whichever Index.jsx the
 * directory walk reached first.
 *
 * The tree is walked ONCE into a basename index. Walking it per name — which is what this did first — made
 * the sweep take five and a half minutes on 152 cited files, most of it scanning vendor for a file it had
 * already found, and a tool that slow gets skipped by the very caller that needs it.
 */
$index = [];

$dirs = ['app', 'database', 'resources', 'routes', 'tests', 'bootstrap', 'config', 'tools', 'public', 'Documentation', 'vendor'];
foreach ($dirs as $dir) {
    if (! is_dir($root.'/'.$dir)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile()) {
            $index[$f->getFilename()][] = str_replace('\\', '/', $f->getPathname());
        }
    }
}

$resolve = function (string $name) use (&$cache, $index): ?string {
    $key = str_replace('\\', '/', $name);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $base = basename($key);
    $best = null;
    $bestScore = -1;

    foreach ($index[$base] ?? [] as $path) {
        // Score by how much of the document's prefix the path actually carries.
        $score = str_contains($path, $key) ? 100 : (count(array_filter(explode('/', $key), fn ($p) => str_contains($path, $p))) - 1);
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $path;
        }
    }

    return $cache[$key] = $best;
};

// Hyphens belong in the name: `clearance-slip.blade.php:44` is ONE citation, and a pattern that stops at
// the hyphen reports "slip.blade.php" as a missing file — a defect in this sweep, not in the document.
// Both forms are found in ONE scan of the whole text. A second, per-line scan of the same alternation cost
// five and a half minutes on a 2 MB dump, which is long enough that a caller starts skipping the sweep.
$filename = '(?:(?:[A-Za-z0-9_.\-]+\/)*[A-Za-z0-9_.\-]+\.(?:php|jsx|js|json|neon|xml|md))';
$pattern = '/(?<name>'.$filename.'):(?<from>\d+)(?:-(?<to>\d+))?'
    .'|(?<fileonly>'.$filename.')(?![:\w])'
    .'|(?<lead>[,(]|\bat|\band|\bto|\bis)[\s;]*:(?<bareFrom>\d+)(?:-(?<bareTo>\d+))?(?![\w\-])/';
preg_match_all($pattern, $text, $all, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL | PREG_OFFSET_CAPTURE);

$named = [];
$bare = [];
// Anchors are the things a bare `:NN` can belong to: a full citation, or a file named on its own in the cell
// before it — `( Students/Show.jsx ) | Name, School ID ( :152 )` names its file one cell earlier, and a
// checker that only reads `File:NN` calls that pointer blind and passes it.
$anchors = [];
foreach ($all as $x) {
    if (($x['name'][0] ?? null) !== null) {
        $named[] = [$x['name'][0], (int) $x['from'][0], ($x['to'][0] ?? null) === null ? null : (int) $x['to'][0],
            $x[0][1], $x[0][1] + strlen($x[0][0])];
        $anchors[] = [$x[0][1], $x[0][1] + strlen($x[0][0]), $x['name'][0]];
    } elseif (($x['fileonly'][0] ?? null) !== null) {
        $anchors[] = [$x[0][1], $x[0][1] + strlen($x['fileonly'][0]), $x['fileonly'][0]];
    } else {
        $bare[] = [(int) $x['bareFrom'][0], ($x['bareTo'][0] ?? null) === null ? null : (int) $x['bareTo'][0], $x[0][1]];
    }
}

// A bare `:NN` belongs to the line it is written on, so line starts are mapped once and both lists are
// walked in offset order to find the line each token sits in.
$lineStart = [0];
$pos = 0;
while (($nl = strpos($text, "\n", $pos)) !== false) {
    $lineStart[] = $nl + 1;
    $pos = $nl + 1;
}
$lineOf = function (int $offset) use ($lineStart): int {
    $lo = 0;
    $hi = count($lineStart) - 1;
    while ($lo < $hi) {
        $mid = intdiv($lo + $hi + 1, 2);
        if ($lineStart[$mid] <= $offset) {
            $lo = $mid;
        } else {
            $hi = $mid - 1;
        }
    }

    return $lo;
};

$problems = 0;
$checked = 0;
$missing = [];

foreach ($named as [$name, $from, $to, $offset]) {
    $to = $to ?? $from;
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

// --- bare continuations ---------------------------------------------------------------------------
// One `File.php:NN , :MM` sentence is two citations. A bare `:NN` takes the file named most recently BEFORE
// it on the same flat-dump line, provided that name is within 160 characters — beyond that, the nearest name
// is as likely to belong to the cell to its left, so the token is printed instead of judged, because an
// attribution invented by distance is how a checker starts clearing a pointer that belongs to something else.
$bareChecked = 0;
$bareProblems = 0;
$blind = [];

$ai = 0;
$current = null;
$currentEnd = -1;
$currentLine = -1;
foreach ($bare as [$from, $to, $offset]) {
    $to = $to ?? $from;
    $line = $lineOf($offset);
    while ($ai < count($anchors) && $anchors[$ai][0] < $offset) {
        if ($lineOf($anchors[$ai][0]) === $line) {
            // only reached when the anchor is on this line; the loop keeps the last such name
            $current = $anchors[$ai][2];
            $currentEnd = $anchors[$ai][1];
            $currentLine = $line;
        }
        $ai++;
    }
    if ($from < 1) {
        continue;
    }
    $token = ':'.$from.($to > $from ? '-'.$to : '');
    // A printed context is a BYTE window out of the dump, so it can cut a multi-byte character in half. That
    // half-character then breaks every `/u` regex downstream — a caller matching this tool's own summary line
    // gets no match and reports the reading as missing — and it made a `/u` whitespace collapse answer null,
    // printing an empty quote. Sanitise at the source and match in byte mode.
    $context = static function (int $back, int $len) use ($text, $offset): string {
        $window = strtr(substr($text, max(0, $offset - $back), $len), ["\n" => ' ', "\r" => ' ', "\t" => ' ']);

        return trim(preg_replace('/ +/', ' ', mb_convert_encoding($window, 'UTF-8', 'UTF-8')) ?? '');
    };
    if ($current === null || $currentLine !== $line || ($offset - $currentEnd) > 160) {
        // One line per blind spot: the dump joins table cells with tabs and newlines, and a context string
        // that wraps turns the printed evidence into an unreadable block.
        $blind[] = $token.' near "'.$context(46, 86).'"';

        continue;
    }
    $path = $resolve($current);
    if ($path === null) {
        continue;
    }
    $lines = file($path);
    $bareChecked++;
    if ($to > count($lines)) {
        printf("  PAST END   %-14s in %-42s cites %s in a %d-line file — read which file this really names\n",
            '(bare)', $current, $token, count($lines));
        $bareProblems++;

        continue;
    }
    if (trim($lines[$from - 1]) === '') {
        printf("  EMPTY LINE %-14s in %-42s cites a blank line %s — the sentence reads \"%s\"\n",
            '(bare)', $current, $token, $context(60, 130));
        $bareProblems++;
    }
}

printf("  bare continuation(s): %d judged against the file named before them, %d blind spot(s)%s\n",
    $bareChecked, count($blind), $bareProblems === 0 ? '' : ', '.$bareProblems.' problem(s)');
// Every one of them, not the first dozen. A tally that withholds its own evidence asks the reader to trust
// a count they cannot check — the defect lane 68 fixed in lane61-open-claims.php and easy to re-introduce.
foreach ($blind as $b) {
    echo '    UNATTRIBUTED '.$b."\n";
}

exit($problems === 0 && $missing === [] && $bareProblems === 0 ? 0 : 1);
