<?php
/**
 * Front-end citations, checked the same way the PHP ones were: does the cited line exist, and does
 * it hold the thing the sentence claims? The range probe could not see these because it indexed
 * only *.php files, and a .jsx path in the document is written with its folder
 * (resources/js/Pages/Evaluation/Show.jsx) or bare (Show.jsx) depending on when it was written.
 *
 * usage: php probe-docx-jsx-cites.php [substring-of-filename]
 */
$want = $argv[1] ?? '';
$flat = file(__DIR__.'/docx-flat.txt');

// Index every front-end file the project actually has. A bare "Create.jsx" in the document is
// ambiguous — Auth, Admission and Exam each have one — so resolution is by path suffix and an
// ambiguous name is reported instead of silently guessing (a wrong guess reads as a bad cite).
$paths = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator('resources/js')) as $f) {
    if ($f->isFile() && preg_match('/\.(jsx|js)$/', $f->getFilename())) {
        $paths[] = str_replace('\\', '/', $f->getPathname());
    }
}
$resolve = function (string $cited) use ($paths): array {
    $needle = ltrim($cited, '/');
    $hits = array_values(array_filter($paths, fn ($p) => str_ends_with($p, $needle)));
    if ($hits !== []) {
        return $hits;
    }
    // Bare filename: match on "Folder/File.jsx" then on the filename alone.
    return array_values(array_filter($paths, fn ($p) => str_contains($p, '/'.basename($cited))));
};

$pat = '#([A-Za-z0-9_/]*\.jsx):([0-9]+)(?:-([0-9]+))?#';
$seen = [];
$missing = 0;
foreach ($flat as $entry) {
    if (! preg_match('/^(ROW|P) (\d+): (.*)$/s', $entry, $m)) {
        continue;
    }
    [$all, $kind, $at, $text] = $m;
    if (! preg_match_all($pat, $text, $c, PREG_OFFSET_CAPTURE)) {
        continue;
    }
    foreach ($c[1] as $i => $name) {
        $file = $name[0];
        if ($want !== '' && ! str_contains($file, $want)) {
            continue;
        }
        $lo = (int) $c[2][$i][0];
        $hi = $c[3][$i][0] !== '' ? (int) $c[3][$i][0] : $lo;
        $key = $kind.' '.$at.' '.$file.':'.$lo.'-'.$hi;
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;

        $found = $resolve($file);
        if ($found === []) {
            printf("%-5s %-5s  MISSING FILE  %s:%d-%d\n", $kind, $at, $file, $lo, $hi);
            $missing++;
            continue;
        }
        if (count($found) > 1) {
            printf("%-5s %-5s  AMBIGUOUS     %s:%d-%d  (%s)\n", $kind, $at, $file, $lo, $hi, implode(', ', array_map('dirname', $found)));
            continue;
        }
        $path = $found[0];
        $lines = file($path);
        $total = count($lines);
        $flag = $hi > $total ? ' PAST-END('.$total.')' : '';
        $span = [];
        for ($n = $lo; $n <= min($hi, $total); $n++) {
            $span[] = trim($lines[$n - 1] ?? '');
        }
        $body = preg_replace('/\s+/', ' ', implode(' / ', $span));
        $pos = $c[0][$i][1];
        $says = mb_substr(preg_replace('/\s+/', ' ', $text), max(0, $pos - 90), 90);
        printf("%-5s %-5s %-34s %4d-%-4d%s | …%s\n     ↳ %s\n", $kind, $at, $file, $lo, $hi, $flag, $says, mb_substr($body, 0, 150));
    }
}
echo "\n", count($seen), " front-end citation sites, ", $missing, " naming a file that does not exist\n";
