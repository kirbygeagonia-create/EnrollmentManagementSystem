<?php

/**
 * Lane 70 — read every queue row that carries no recorded read.
 *
 * `lane70-anchor-cites.php` cannot bind these rows because their sentences name no identifier for a pairing rule
 * to reach, so the check runs the other way: ask the file which construct owns the cited line, and print the
 * document's own sentence beside it. The reader then decides whether the span carries the claim — which is the
 * half no instrument can do, and the reason the queue exists.
 *
 * "Unread" is computed from the anchor tool's OWN keys minus the keys present in `lane70-queue-reads.json`, so a
 * row is never called unread by one tool's idea of a pointer and read by another's.
 *
 * usage: php storage/app/scratch/lane70-read-unread.php [--all] [--reads=<file>]
 *        --all prints every UNBOUND key rather than only the ones with no record.
 *        --reads= judges a different record set, so a harness can prove the set is what makes the queue short.
 */

$root = dirname(__DIR__, 3);
$php = PHP_BINARY;
$wantAll = false;
$readsOverride = null;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--all') {
        $wantAll = true;

        continue;
    }
    if (str_starts_with($arg, '--reads=')) {
        $readsOverride = substr($arg, strlen('--reads='));

        continue;
    }
    // An accepted-and-ignored flag is how a mutant run looks clean, so anything this reader does not read is refused
    // by name rather than tolerated.
    fwrite(STDERR, "REFUSED: unknown argument {$arg}; this reader reads --all and --reads=<file>.\n");

    exit(1);
}

$out = (string) shell_exec(escapeshellarg($php).' '.escapeshellarg($root.'/storage/app/scratch/lane70-anchor-cites.php').' --show-keys 2>&1');

$keys = [];
$queueLines = [];
foreach (explode("\n", $out) as $l) {
    if (str_starts_with($l, 'KEY        ')) {
        $keys[trim(substr($l, 11))] = true;

        continue;
    }
    if (str_starts_with($l, 'UNBOUND')) {
        $queueLines[] = preg_replace('/\s+/u', ' ', $l);
    }
}

if ($keys === []) {
    fwrite(STDERR, "REFUSED: the anchor checker printed no KEY lines, so there is nothing to read.\n");

    exit(1);
}

$reads = [];
$readsFile = $readsOverride ?? $root.'/storage/app/scratch/lane70-queue-reads.json';
if ($readsOverride !== null && ! is_file($readsFile)) {
    // An explicit path that does not exist must refuse: reading it as "no records" would print the whole queue as
    // unread and look like a finding, while reading it as "the file exists and is empty" is what a silently
    // defaulted path does to a harness leg.
    fwrite(STDERR, "REFUSED: --reads={$readsOverride} is not a file, so the record set cannot be judged.\n");

    exit(1);
}
if (is_file($readsFile)) {
    $j = json_decode((string) file_get_contents($readsFile), true);
    if (! is_array($j) || ! array_key_exists('reads', $j)) {
        fwrite(STDERR, "REFUSED: {$readsFile} holds no \"reads\" object, so \"0 record(s) on file\" would be a parse failure, not a measurement.\n");

        exit(1);
    }
    $reads = is_array($j['reads']) ? $j['reads'] : [];
}

$todo = $wantAll ? array_keys($keys) : array_keys(array_diff_key($keys, $reads));
$covered = count(array_intersect_key($keys, $reads));
// The queue has two parts and they have to add up to the whole. A parser that reads only some of the records, or
// only some of the keys, makes "0 to read" and "everything is read" indistinguishable — which is exactly how this
// reader once reported a clean queue while its own key pattern matched nothing.
if (! $wantAll && $covered + count($todo) !== count($keys)) {
    fwrite(STDERR, "REFUSED: {$covered} recorded + ".count($todo).' unread does not equal '.count($keys)
        ." current key(s), so one of the two parsers read less than it tallied.\n");

    exit(1);
}
printf("%d current key(s), %d record(s) on file, %d to read%s\n\n",
    count($keys), count($reads), count($todo), $wantAll ? ' (--all: records ignored)' : '');

/**
 * Where a basename actually lives. Most keys are spelled without a directory, so this is a search, not a guess.
 *
 * The tree is walked ONCE into a basename index. The depth-limited glob list this replaced was wrong two ways at
 * once: PHP's glob() has no recursive double-star pattern, so a "tests" + double-star + filename path answered zero
 * and every key sitting under a
 * two-deep directory printed NOT FOUND — which `mutate-lane70-read-unread.php` caught by reading the WHOLE queue
 * rather than the drained one — and a new source folder stayed invisible until someone added another pattern by hand.
 * The candidate count comes back by reference so the caller can tell "no such file" from "two files share this
 * basename" and refuse both: a read of `Students/Show.jsx` must not clear a pointer into `Registrar/Show.jsx`, which
 * is the one-name-two-owners defect this lane keeps paying for.
 */
$index = null;
$locate = static function (string $base, ?int &$n = null) use ($root, &$index): ?string {
    $n = 0;
    // The key's own path is a claim: try it before any search, because the document wrote some keys with the path
    // and that spelling is how the attribution handed them back.
    foreach ([$root.'/'.$base, $root.'/resources/js/Pages/'.$base, $root.'/app/'.$base] as $p) {
        if (is_file($p)) {
            $n = 1;

            return (string) $p;
        }
    }
    if ($index === null) {
        $index = [];
        foreach (['app', 'bootstrap', 'config', 'database', 'Documentation', 'resources', 'routes', 'tests', 'tools'] as $dir) {
            if (! is_dir($root.'/'.$dir)) {
                continue;
            }
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $fi) {
                if ($fi->isFile()) {
                    $index[$fi->getFilename()][] = str_replace('\\', '/', $fi->getPathname());
                }
            }
        }
    }
    $found = $index[basename($base)] ?? [];
    if (str_contains($base, '/')) {
        $want = str_replace('\\', '/', $base);
        $found = array_values(array_filter($found, static fn (string $p): bool => str_ends_with($p, $want)));
    }
    $n = count($found);

    return $n === 1 ? (string) $found[0] : null;
};
$seen = [];
foreach ($todo as $key) {
    if (! preg_match('/^(?<f>.+?):(?<a>\d+)(?:-(?<b>\d+))?#/', $key, $m)) {
        printf("SKIP  %s — the key is not file:start-end#hash\n\n", $key);

        continue;
    }
    $base = $m['f'];
    $a = (int) $m['a'];
    $b = (int) ($m['b'] ?? $a);

    echo "KEY     {$key}\n";
    $who = array_values(array_filter($queueLines, static fn (string $l): bool => str_contains($l, $base.' ')
        && str_contains($l, ':'.(string) $a) === true));
    foreach (array_slice($who, 0, 1) as $q) {
        echo '  QUEUE  '.mb_substr($q, 0, 900)."\n";
    }

    $cands = 0;
    $path = $locate($base, $cands);
    if ($path === null) {
        printf("  FILE    %s — refusing to guess a path\n\n",
            $cands === 0 ? 'NOT FOUND by the indexed walk (0 candidates)'
                : "AMBIGUOUS ({$cands} files share this basename, and the key named no directory)");

        continue;
    }
    if (! isset($seen[$path])) {
        $seen[$path] = (array) file($path);
    }
    $fl = $seen[$path];
    $count = count($fl);

    $enc = '';
    for ($i = min($a, $count); $i >= 1; $i--) {
        if (preg_match('/^\s*(?:private |protected |public |static )*function (\w+)/u', (string) ($fl[$i - 1] ?? ''), $mm) === 1) {
            $enc = $mm[1];

            break;
        }
    }
    printf("  FILE    %s (%d lines, span inside %s)\n", str_replace($root.'/', '', $path), $count, $enc === '' ? 'no function found above it' : $enc.'()');

    $show = static function (int $n) use ($fl): string {
        return sprintf('%5d: %s', $n, trim((string) ($fl[$n - 1] ?? '<past end>')));
    };
    if ($b - $a <= 13) {
        for ($n = $a; $n <= $b; $n++) {
            echo '          '.$show($n)."\n";
        }
    } else {
        for ($n = $a; $n <= $a + 3; $n++) {
            echo '          '.$show($n)."\n";
        }
        printf("          … %d line(s) elided …\n", ($b - $a) - 7);
        for ($n = $b - 3; $n <= $b; $n++) {
            echo '          '.$show($n)."\n";
        }
    }
    echo "\n";
}

printf("%d key(s) reported; %d distinct file(s) opened.\n", count($todo), count($seen));
