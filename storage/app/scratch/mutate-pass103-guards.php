<?php
/**
 * Mutate pass 103 and require a refusal each time, with the harness checking that each mutation landed.
 */
$root = dirname(__DIR__, 3);
$engine = $root.'/storage/app/scratch/refresh-docx-pass103.php';
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$src = file_get_contents($engine);

$cases = [
    'A a citation count the document cannot satisfy' => [
        "        3,\n        'the three citations to the flash block",
        "        2,\n        'the three citations to the flash block",
    ],
    'B the new claim removed from the replacement' => [
        "'can.evaluationDesk and can.blockingDesk, shared from the same rights the desk routes ask, and '",
        "'shared from the same rights the desk routes ask, and '",
    ],
    'C the middleware moved after this pass was written' => [
        "if (\$first !== 94 || \$last !== 100) {",
        "if (\$first !== 1 || \$last !== 2) {",
    ],
];

$bad = 0;
foreach ($cases as $name => [$from, $to]) {
    $body = str_replace($from, $to, $src);
    if ($body === $src) {
        printf("mutant %s: NOT PLANTED — nothing changed\n", $name);
        $bad++;

        continue;
    }

    $path = $root.'/storage/app/scratch/mutant-pass103.php';
    file_put_contents($path, $body);
    exec('cd "'.$root.'" && "'.$php.'" storage/app/scratch/mutant-pass103.php 2>&1', $lines, $code);
    $out = trim(str_replace("\n", ' ', implode(' ', $lines)));
    $lines = [];
    @unlink($path);

    if ($code === 0) {
        printf("mutant %s: FAIL accepted (exit 0) — %s\n", $name, substr($out, 0, 110));
        $bad++;
    } else {
        printf("mutant %s: refused (exit %d) — %s\n", $name, $code, substr($out, 0, 110));
    }
}

echo $bad === 0 ? "\nevery mutant refused\n" : "\n{$bad} problem(s)\n";
exit($bad === 0 ? 0 : 1);
