<?php
/**
 * Feed pass 100's checker two mutants and require it to refuse both.
 *
 * A guard that has never failed is not evidence. Mutant A renames the needle so the edit cannot resolve;
 * mutant B silently drops one of the new claims from the replacement, which only the presence guard can
 * catch. The script prints each mutant's exit code and verdict, and fails if either is accepted.
 */
$root = dirname(__DIR__, 3);
$engine = $root.'/storage/app/scratch/refresh-docx-pass100.php';
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';

$src = file_get_contents($engine);

$mutants = [
    'A needle that does not exist' => [
        str_replace('subtraction of proof, in a new place. ', 'subtraction of proof, in a new PLACE. ', $src),
        ['REFUSE [', 'problem'],
    ],
    'B a claim dropped from the replacement' => [
        str_replace('religions joined the list it checks before running, and the ', 'and the ', $src),
        ['REFUSE guard: the new claims are present once'],
    ],
];

$bad = 0;

foreach ($mutants as $name => [$body, $expects]) {
    // The harness has to be able to catch itself: a mutation that does not change a single byte never
    // tested anything, and reporting "accepted" for an unmutated file is a false verdict about the checker.
    if ($body === $src) {
        printf("mutant %s: NOT PLANTED — the replacement matched nothing in the engine\n", $name);
        $bad++;

        continue;
    }

    $path = $root.'/storage/app/scratch/mutant-pass100.php';
    file_put_contents($path, $body);
    exec('cd "'.$root.'" && "'.$php.'" storage/app/scratch/mutant-pass100.php 2>&1', $lines, $code);
    $out = implode("\n", $lines);
    $lines = [];

    printf("mutant %s: exit=%d\n", $name, $code);
    foreach ($expects as $needle) {
        $seen = str_contains($out, $needle);
        printf("   %s  contains [%s]\n", $seen ? 'ok   REFUSED as expected' : 'FAIL accepted, expected refusal', $needle);
        $bad += $seen ? 0 : 1;
    }
    if ($code === 0) {
        echo "   FAIL exit 0 — a mutant must not reach the apply branch\n";
        $bad++;
    }
    @unlink($path);
}

echo $bad === 0 ? "\nboth mutants refused; the guards are live\n" : "\n{$bad} problem(s): the checker is not proving anything\n";
exit($bad === 0 ? 0 : 1);
