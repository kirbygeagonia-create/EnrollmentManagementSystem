<?php
/**
 * Refuse-a-mutant check for refresh-docx-pass102.php.
 *
 * Every mutation is compared against the engine's bytes before it runs, because a find-string that matches
 * nothing leaves the unmutated file to "pass" and reads as a vacuous checker.
 */
$root = dirname(__DIR__, 3);
$engine = $root.'/storage/app/scratch/refresh-docx-pass102.php';
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$src = file_get_contents($engine);

$cases = [
    'A the needle does not exist in the document' => [
        "'the mercy of the counter. '",
        "'the mercy of the wrong counter. '",
    ],
    'B the replacement deletes the rest of the paragraph' => [
        // Single-quoted: inside double quotes these $ names would be interpolated by THIS harness, not
        // matched in the engine, and the mutant would silently never be planted.
        '$xml = str_replace($esc($from), $esc($to), $xml);',
        '$xml = str_replace($esc($from), \'\', $xml);',
    ],
];

$bad = 0;
foreach ($cases as $name => [$from, $to]) {
    $body = str_replace($from, $to, $src);
    if ($body === $src) {
        printf("mutant %s: NOT PLANTED — nothing was changed\n", $name);
        $bad++;

        continue;
    }

    $path = $root.'/storage/app/scratch/mutant-pass102.php';
    file_put_contents($path, $body);
    exec('cd "'.$root.'" && "'.$php.'" storage/app/scratch/mutant-pass102.php 2>&1', $lines, $code);
    $out = trim(str_replace("\n", ' ', implode(' ', $lines)));
    $lines = [];

    if ($code === 0) {
        printf("mutant %s: FAIL exit 0 — %s\n", $name, substr($out, 0, 120));
        $bad++;
    } else {
        printf("mutant %s: refused (exit %d) — %s\n", $name, $code, substr($out, 0, 120));
    }
    @unlink($path);
}

echo $bad === 0 ? "\nboth mutants refused\n" : "\n{$bad} problem(s)\n";
exit($bad === 0 ? 0 : 1);
