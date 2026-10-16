<?php

/**
 * Every `File.php:NN` / `File.jsx:NN-MM` citation in the repository's own prose, checked against the files.
 *
 * `sweep-docx-citations.php` does this for the authority docx (554 citations), and AGENTS.md says the whole
 * document must be swept after any batch that moves lines. But AGENTS.md and DEPLOYMENT.md also carry
 * citations of their own — and the ones in AGENTS.md were written today, by me, about a file that had already
 * grown twice. Nothing read them. A checker for the document that excludes the instructions is the same shape
 * as a test suite that excludes `tools/`.
 *
 * Three claims per citation, the same three the docx sweep makes: the file exists, the range ends inside it,
 * and the first line of the range has something on it. It does NOT check that the sentence is about what the
 * lines contain — that reading is the human half, and no regex here can do it honestly.
 *
 * usage: php storage/app/scratch/repo-file-line-citations.php [file ...]
 *        (defaults to AGENTS.md, DEPLOYMENT.md and Documentation/Per-Office-UAT-Walkthrough.md)
 */

$root = dirname(__DIR__, 3);
$args = array_values(array_filter(array_slice($argv ?? [], 1), fn ($a) => ! str_starts_with($a, '--')));
$targets = $args !== [] ? $args : ['AGENTS.md', 'DEPLOYMENT.md', 'Documentation/Per-Office-UAT-Walkthrough.md'];

$index = null;
/** Every file the repository's own prose can cite, indexed by basename. */
$buildIndex = function () use ($root, &$index): array {
    if ($index !== null) {
        return $index;
    }
    $index = [];
    foreach (['app', 'resources', 'tests', 'tools', 'database', 'routes', 'config', 'bootstrap', 'public', 'storage/app/scratch', 'Documentation', '.github'] as $dir) {
        if (! is_dir($root.'/'.$dir)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (! $f->isFile()) {
                continue;
            }
            $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
            $index[$f->getFilename()][] = $rel;
        }
    }

    return $index;
};

/**
 * Resolve a cited name. A name that carries a path is taken literally — this repository's prose always knows
 * the path it means. A bare basename is looked up, and more than one hit is reported as AMBIGUOUS rather than
 * guessed at: the docx sweep once returned a real-looking "past end of file" for a citation the document had
 * already qualified, because its resolver answered with whichever `Index.jsx` the walk reached first.
 *
 * @return array{0: ?string, 1: list<string>}  the chosen path (null when unresolvable) and every candidate
 */
$resolve = function (string $name) use ($root, $buildIndex): array {
    if (str_contains($name, '/')) {
        return [is_file($root.'/'.ltrim($name, '/')) ? $name : null, []];
    }
    $hits = $buildIndex()[basename($name)] ?? [];
    if (count($hits) === 1) {
        return [$hits[0], $hits];
    }

    return [null, $hits];
};

// The name may carry slashes (resources/js/Pages/X.jsx) or none (Preflight.php); the resolver below is
// deliberately stricter than the docx sweep's basename walk, because a prose citation in this repository
// always knows the path it means — an ambiguous basename here is a defect in the citation, not in the tool.
$fileRe = '/\b((?:[A-Za-z0-9_.\-]+\/)*[A-Za-z0-9_.\-]+\.(?:php|jsx|js|json|neon|xml|md|sql|ps1)):(\d+)(?:-(\d+))?/';

$problems = 0;
$ambiguous = 0;
$checked = 0;
$lineCache = [];

foreach ($targets as $rel) {
    $path = $root.'/'.ltrim($rel, '/');
    if (! is_file($path)) {
        printf("  MISSING TARGET  %s\n", $rel);
        $problems++;

        continue;
    }
    $text = (string) file_get_contents($path);
    preg_match_all($fileRe, $text, $m, PREG_SET_ORDER);

    foreach ($m as $x) {
        [$name, $from] = [$x[1], (int) $x[2]];
        $to = ($x[3] ?? '') === '' ? $from : (int) $x[3];
        $token = $name.':'.$from.($to > $from ? '-'.$to : '');

        [$chosen, $hits] = $resolve($name);
        if ($chosen === null && $hits !== []) {
            $ambiguous++;
            printf("  AMBIGUOUS     %-46s basename matches %d files (%s) — name the path  (in %s)\n",
                $token, count($hits), implode(', ', array_slice($hits, 0, 3)), $rel);

            continue;
        }
        if ($chosen === null) {
            $problems++;
            printf("  NO SUCH FILE  %-46s cited in %s\n", $token, $rel);

            continue;
        }

        $lines = $lineCache[$chosen] ??= (file($root.'/'.$chosen) ?: []);
        $checked++;

        if ($to > count($lines)) {
            printf("  PAST END      %-46s cites %d-%d in a %d-line file  (in %s)\n", $token, $from, $to, count($lines), $rel);
            $problems++;

            continue;
        }
        if (trim((string) $lines[$from - 1]) === '') {
            printf("  EMPTY LINE    %-46s cites a blank line %d  (in %s)\n", $token, $from, $rel);
            $problems++;
        }
    }
}

printf("\n%d citation(s) resolved and checked across %d file(s); %d problem(s); %d ambiguous basename(s)\n",
    $checked, count($targets), $problems, $ambiguous);

exit($problems === 0 ? 0 : 1);
