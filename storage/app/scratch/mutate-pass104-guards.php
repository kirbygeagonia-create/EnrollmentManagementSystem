<?php
/**
 * Mutate pass 104 and require a refusal each time; each mutation is checked to have landed first.
 */
$root = dirname(__DIR__, 3);
$engine = $root.'/storage/app/scratch/refresh-docx-pass104.php';
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$src = file_get_contents($engine);

$cases = [
    'A a needle the document does not contain' => [
        "'Preflight.php:81-94, the disk rule at :175-198',",
        "'Preflight.php:81-94, the disk rule at :175-999',",
    ],
    // Must be a fragment unique to the replacement: the first attempt mutated a phrase that also appears in
    // the guard that checks it, so both moved together and the checker "passed" having proven nothing.
    'B the claim the guards require is left out of the replacement' => [
        'disagreed by 11.0 hours on the development box, which is what',
        'disagreed on the development box, which is what',
    ],
    'C a cited line that no longer says what the document claims' => [
        "    224 => 'private function uploadDiskIsPrivate(): array',",
        "    224 => 'private function notTheDiskRule(): array',",
    ],
];

$bad = 0;
foreach ($cases as $name => [$from, $to]) {
    $body = str_replace($from, $to, $src);
    if ($body === $src) {
        printf("mutant %s: NOT PLANTED\n", $name);
        $bad++;

        continue;
    }

    $path = $root.'/storage/app/scratch/mutant-pass104.php';
    file_put_contents($path, $body);
    exec('cd "'.$root.'" && "'.$php.'" storage/app/scratch/mutant-pass104.php 2>&1', $lines, $code);
    $out = trim(str_replace("\n", ' ', implode(' ', $lines)));
    $lines = [];
    @unlink($path);

    if ($code === 0) {
        printf("mutant %s: FAIL accepted — %s\n", $name, substr($out, -90));
        $bad++;
    } else {
        printf("mutant %s: refused (exit %d)\n", $name, $code);
    }
}

echo $bad === 0 ? "\nevery mutant refused\n" : "\n{$bad} problem(s)\n";
exit($bad === 0 ? 0 : 1);
