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
 * usage: php storage/app/scratch/lane70-read-unread.php [--all]
 *        --all prints every UNBOUND key rather than only the ones with no record.
 */

$root = dirname(__DIR__, 3);
$php = PHP_BINARY;
$wantAll = in_array('--all', $argv, true);

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
$readsFile = $root.'/storage/app/scratch/lane70-queue-reads.json';
if (is_file($readsFile)) {
    $j = json_decode((string) file_get_contents($readsFile), true);
    if (is_array($j) && isset($j['reads']) && is_array($j['reads'])) {
        $reads = $j['reads'];
    }
}

$todo = $wantAll ? array_keys($keys) : array_keys(array_diff_key($keys, $reads));
printf("%d current key(s), %d record(s) on file, %d to read%s\n\n",
    count($keys), count($reads), count($todo), $wantAll ? ' (--all: records ignored)' : '');

/** Where a bare basename actually lives. The document spells these without a directory, so this is a search, not a guess. */
$locate = static function (string $base) use ($root): ?string {
    // Some keys carry a path of their own (`Evaluation/Index.jsx`, `app/Http/Controllers/Exam/ExamController.php`),
    // because that is how the attribution handed them back. Compare on basename, and try the key's own path first.
    $direct = [$root.'/'.$base, $root.'/resources/js/Pages/'.$base, $root.'/app/'.$base];
    foreach ($direct as $p) {
        if (is_file($p)) {
            return (string) $p;
        }
    }
    $needle = basename($base);
    $candidates = [
        $root.'/app/Http/Controllers/'.$needle,
        $root.'/app/Http/Controllers/*/'.$needle,
        $root.'/app/Http/Controllers/*/*/'.$needle,
        $root.'/app/Services/'.$needle,
        $root.'/app/Support/'.$needle,
        $root.'/app/Policies/'.$needle,
        $root.'/app/Models/'.$needle,
        $root.'/app/Providers/'.$needle,
        $root.'/app/Console/Commands/'.$needle,
        $root.'/app/Listeners/'.$needle,
        $root.'/app/Enums/'.$needle,
        $root.'/tests/**/'.$needle,
        $root.'/resources/js/Pages/**/'.$needle,
        $root.'/resources/js/Pages/*/'.$needle,
        $root.'/resources/js/Layouts/'.$needle,
        $root.'/resources/js/Components/ui/'.$needle,
        $root.'/resources/js/Components/'.$needle,
        $root.'/resources/views/**/'.$needle,
        $root.'/database/migrations/'.$needle,
        $root.'/database/seeders/'.$needle,
        $root.'/routes/'.$needle,
    ];
    foreach ($candidates as $pattern) {
        $hits = str_contains($pattern, '*') ? (glob($pattern) ?: []) : (is_file($pattern) ? [$pattern] : []);
        foreach ($hits as $h) {
            if (basename((string) $h) !== $needle) {
                continue;
            }
            // A key that says `Students/Show.jsx` must not resolve to `Registrar/Show.jsx` — the path part is a
            // claim, so honour it when it is present and only fall back to a bare-basename match otherwise.
            if (str_contains($base, '/') && ! str_ends_with(str_replace('\\', '/', (string) $h), $base)) {
                continue;
            }

            return (string) $h;
        }
    }

    return null;
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

    $path = $locate($base);
    if ($path === null) {
        echo "  FILE    NOT FOUND by search — refusing to guess a path\n\n";

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
