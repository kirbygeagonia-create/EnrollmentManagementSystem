<?php
/**
 * Does every statement in the ADS guide answers name a thing that exists?
 *
 * The owner's first ask is that the copy's statements be correct and accurate. Most of them are claims of the form
 * "file X", "class Y", "`Class::method()`" — the shape this repository already refuses elsewhere for the authority
 * docx (a citation that lands on a live line belonging to something else). This is the same weigher for the answers
 * file: it resolves every named artefact and reports the ones the repository does not hold, and it refuses its own
 * run when it read nothing, because a clean verdict from an unread population is the defect, not the result.
 *
 * Usage: php storage/app/scratch/lane235-check-guide-claims.php [--file=<md>] [--show=N]
 * Exit 1 when any claim names nothing; exit 0 with the census when every claim resolves.
 */
$root = dirname(__DIR__, 3);
chdir($root);
$file = $root.'/Documentation/GUIDE-PROBLEM-ANSWERS-ADS.md';
$show = 40;
foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
    if (preg_match('#^--file=(.*)$#s', $arg, $m)) {
        $file = $m[1];
    } elseif (preg_match('#^--show=(\d+)$#', $arg, $m)) {
        $show = (int) $m[1];
    } else {
        fwrite(STDERR, "REFUSED: this tool does not read the flag {$arg}\n");
        exit(1);
    }
}
if (! is_file($file)) {
    fwrite(STDERR, "REFUSED: {$file} is not on disk.\n");
    exit(1);
}
$text = (string) file_get_contents($file);
if ($text === '') {
    fwrite(STDERR, "REFUSED: {$file} read empty.\n");
    exit(1);
}

// One index of every present file, by basename, storing the ABSOLUTE forward-slashed path. The first cut stored
// paths with the root already in them and then joined `$root.'/'` again, so every method read died on a doubled
// `C:/…/C:/…` — a reader that cannot open the file it is judging must never be able to print a verdict.
$rootFwd = str_replace('\\', '/', $root);
$byBase = [];
foreach (['app', 'tests', 'database', 'routes', 'resources', 'tools', 'Documentation', 'storage/app/scratch', '.github', 'config'] as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $f) {
        if ($f->isFile()) {
            $byBase[$f->getFilename()][] = str_replace('\\', '/', $f->getPathname());
        }
    }
}
$relPath = static fn (string $abs): string => str_replace($rootFwd.'/', '', $abs);

$missing = [];
$exempt = [];
$counts = ['file' => 0, 'class' => 0, 'member' => 0, 'resolved' => 0];

// (a) file and artefact names.
preg_match_all('#`?([A-Za-z0-9_./\-]+\.(?:php|inc|mjs|js|jsx|md|docx|txt|log|json|xml|sql|yml|yaml|blade\.php|png|svg|csv))`?#', $text, $fm);
foreach (array_unique($fm[1]) as $rel) {
    $counts['file']++;
    $base = basename($rel);
    $ok = is_file($root.'/'.$rel) || isset($byBase[$base]);
    if ($ok) {
        $counts['resolved']++;
        continue;
    }
    // An artefact the sentence itself places outside the tree is not an invention — the development prompt is the
    // standing example, and the school's blank guide form now says so in its own line. The exemption is matched on
    // the SENTENCE that carries the claim, printed by name, and never folded into the resolved count: an invisible
    // exemption is how a gate stops reading the thing it exists to catch.
    $line = '';
    foreach (preg_split('/\r?\n/', $text) as $ln) {
        if (str_contains($ln, $base)) {
            $line = $ln;
            break;
        }
    }
    if (preg_match('/outside (this )?repository|held outside|not in (this )?the repository/i', $line)) {
        $exempt[] = $base;
        continue;
    }
    $missing[] = ['kind' => 'FILE', 'claim' => $rel];
}

// (b) test class names.
preg_match_all('#\b([A-Z][A-Za-z0-9]*Test)\b#', $text, $cm);
foreach (array_unique($cm[1]) as $cls) {
    $counts['class']++;
    if (isset($byBase[$cls.'.php'])) {
        $counts['resolved']++;
        continue;
    }
    $missing[] = ['kind' => 'CLASS', 'claim' => $cls];
}

// (c) `Class::method` — the method has to live in the class, which is the part a file-existence check cannot see.
preg_match_all('#\b([A-Z][A-Za-z0-9]*)::([a-z][A-Za-z0-9]*)\(\)#', $text, $mm, PREG_SET_ORDER);
foreach ($mm as $m2) {
    $counts['member']++;
    $cls = $m2[1];
    $meth = $m2[2];
    $where = $byBase[$cls.'.php'] ?? null;
    if ($where === null) {
        // An unresolvable class is already reported by (b) when it is a Test; a model/support class is reported here.
        $missing[] = ['kind' => 'CLASS-FOR-METHOD', 'claim' => $cls.'::'.$meth.'()'];
        continue;
    }
    $found = false;
    foreach ($where as $path) {
        $body = file_get_contents($path);
        if ($body === false) {
            continue;
        }
        // `Model::approved()` is Laravel's magic call to `scopeApproved()`, and a plain `function approved(` is only
        // one of the two shapes a scope can take. Reading just the first made this reader report a real, documented
        // scope as an invention — a claim the instrument cannot see is the instrument's limit, not the document's.
        if (preg_match('/function\s+'.preg_quote($meth, '/').'\s*\(/', $body)
            || preg_match('/function\s+scope'.ucfirst(preg_quote($meth, '/')).'\s*\(/i', $body)) {
            $found = true;
            break;
        }
    }
    if ($found) {
        $counts['resolved']++;
        continue;
    }
    $missing[] = ['kind' => 'METHOD', 'claim' => $cls.'::'.$meth.'()', 'file' => $relPath($where[0])];
}

if ($counts['file'] + $counts['class'] + $counts['member'] === 0) {
    fwrite(STDERR, "REFUSED: no claims were read from {$file}, so a clean verdict here would be a story about nothing.\n");
    exit(1);
}

printf("%d file claims, %d test-class claims, %d Class::method() claims — %d resolve, %d do not\n",
    $counts['file'], $counts['class'], $counts['member'], $counts['resolved'], count($missing));
echo 'declared outside the tree, so NOT judged (named, never folded into the resolved count): '
    .(count($exempt) === 0 ? 'none' : implode(', ', array_unique($exempt)))."\n";
foreach (array_slice($missing, 0, $show) as $m3) {
    echo '  '.str_pad($m3['kind'], 16).' '.str_pad((string) ($m3['claim'] ?? ''), 52)
        .($m3['file'] ?? '')."\n";
}
if (count($missing) > $show) {
    echo '  … '.(count($missing) - $show)." more not printed\n";
}
if ($missing !== []) {
    fwrite(STDERR, "UNRESOLVED: ".count($missing)." claim(s) name nothing the repository holds. A statement the reader "
        ."cannot open is not a claim, it is an invention — fix the sentence or name the artefact.\n");
    exit(1);
}
echo "ALL CLAIMS RESOLVE\n";
exit(0);
