<?php

/**
 * Proves lane69-owes-table-check.php can fail.
 *
 * Each case plants one defect in a COPY of the flat dump and requires the checker to name it, then a
 * byte-identical control copy must pass — which is what proves --dump= is actually read rather than
 * accepted and ignored (a harness that mutates a file the checker never opens reports five surviving
 * defects as a clean run).
 *
 * The instrument side is left real on purpose: the defect class is a table that stops agreeing with the
 * column it is counted from, so the column is the fixed reference and the table is what gets broken.
 *
 *   php storage/app/scratch/mutate-lane69-owes-table.php
 */
$root = dirname(__DIR__, 3);
$real = $root.'/storage/app/scratch/docx-flat.txt';
$copy = $root.'/storage/app/scratch/lane69-owes-mutant-dump.txt';
$checker = $root.'/storage/app/scratch/lane69-owes-table-check.php';
$php = PHP_BINARY;

$base = (string) file_get_contents($real);
if (strlen($base) < 1000) {
    echo "REFUSED: the real dump is missing or short — re-run dump-docx-flat.php first.\n";
    exit(1);
}

$cases = [
    'count-stale' => [
        'ROW 2638: Registrar | 0 |',
        'ROW 2638: Registrar | 7 |',
        'the table totals',
        'a table that re-grows a cleared actor slot must be refused',
    ],
    'phantom-identifier' => [
        'ROW 2640: Project owner | 1 | G-13 (install-time, DEPLOYMENT.md §5)',
        'ROW 2640: Project owner | 1 | G-13, C-2',
        'the table lists C-2',
        'an identifier the column no longer charges to anyone must be refused',
    ],
    'wrong-identifier' => [
        'ROW 2641: Department | 1 | C-5',
        'ROW 2641: Department | 1 | C-6',
        'the column says C-5',
        'dropping the one owed item while naming a closed one must be refused twice',
    ],
];

/** Run the checker once and keep both of its answers. `2>&1`, never `> /dev/null`: exec() runs cmd.exe,
 *  which cannot find a Git-Bash path and exits 1 before the checker is reached, so a redirect written for
 *  the wrong shell turns a green run into a harness failure. */
$run = function (string $path) use ($root, $checker, $php): array {
    $lines = [];
    $exit = 0;
    exec('cd '.escapeshellarg($root).' && '.escapeshellarg($php).' '.escapeshellarg($checker)
        .' --dump='.escapeshellarg($path).' 2>&1', $lines, $exit);

    return [implode("\n", $lines), $exit];
};

$fails = 0;
try {
    foreach ($cases as $name => [$from, $to, $needle, $why]) {
        $n = substr_count($base, $from);
        if ($n !== 1) {
            printf("BAD   %-20s needle matched %d times — the mutant cannot apply\n", $name, $n);
            $fails++;

            continue;
        }
        $mutated = str_replace($from, $to, $base);
        // Compare the bytes, not the length: every one-digit count and every C-5/C-6 swap is the same size,
        // so a filesize test reports an applied mutation as unchanged and skips a real case.
        if ($mutated === $base) {
            printf("BAD   %-20s mutation left the dump unchanged\n", $name);
            $fails++;

            continue;
        }
        file_put_contents($copy, $mutated);
        [$out, $exit] = $run($copy);
        $named = str_contains($out, $needle);
        if ($named && $exit === 1) {
            printf("ok    %-20s refused and said: %s\n", $name, $needle);
        } else {
            printf("BAD   %-20s named=%s exit=%d (wanted 1) — %s\n", $name, $named ? 'yes' : 'NO', $exit, $why);
            echo '      the run said: '.str_replace("\n", "\n      ", substr($out, -400))."\n";
            $fails++;
        }
    }

    // Control: a byte-identical copy must pass, which is the proof the flag is honoured.
    file_put_contents($copy, $base);
    [$out, $exit] = $run($copy);
    if ($exit === 0 && str_contains($out, '0 problem(s)')) {
        printf("ok    %-20s clean copy passes with the flag honoured\n", 'control');
    } else {
        printf("BAD   %-20s exit=%d — the control must pass, so a failure here is the harness\n", 'control', $exit);
        echo '      the run said: '.str_replace("\n", "\n      ", substr($out, -700))."\n";
        $fails++;
    }
} finally {
    if (is_file($copy)) {
        unlink($copy);
        echo "\nmutant copy removed; the real dump was never written to.\n";
    }
}

printf("%d case(s) did not behave.\n", $fails);
exit($fails > 0 ? 1 : 0);
