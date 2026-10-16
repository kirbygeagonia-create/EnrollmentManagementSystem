<?php
/**
 * Mutate pass 101 and require a refusal each time, with the harness checking its own work.
 *
 * A mutant that silently fails to apply reads exactly like a vacuous checker — that mistake has now cost
 * this project twice — so every mutation is compared against the source bytes before it runs.
 */
$root = dirname(__DIR__, 3);
$engine = $root.'/storage/app/scratch/refresh-docx-pass101.php';
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$src = file_get_contents($engine);

$mutants = [
    'A a cited line that is not what the document claims' => [
        "    52 => 'public function handle(): int',",
        "    52 => 'public function notReallyThere(): int',",
    ],
    'B the replacement leaves the old span behind' => [
        "'(AssertNoSilentSkips.php:52-101, the allowlist at :39-50)'",
        "'(AssertNoSilentSkips.php:52-101, the allowlist at :39-48)'",
    ],
    'C the needle changed so nothing matches' => [
        "'(AssertNoSilentSkips.php:50-99, the allowlist at :39-48)'",
        "'(AssertNoSilentSkips.php:50-99, the allowlist at :39-XX)'",
    ],
];

$bad = 0;

foreach ($mutants as $name => [$from, $to]) {
    $body = str_replace($from, $to, $src);
    if ($body === $src) {
        printf("mutant %s: NOT PLANTED — the find-string matched nothing\n", $name);
        $bad++;

        continue;
    }

    $path = $root.'/storage/app/scratch/mutant-pass101.php';
    file_put_contents($path, $body);
    exec('cd "'.$root.'" && "'.$php.'" storage/app/scratch/mutant-pass101.php 2>&1', $lines, $code);
    $out = implode("\n", $lines);
    $lines = [];
    printf("mutant %s: exit=%d\n", $name, $code);
    if ($code === 0) {
        echo "   FAIL accepted — a mutant must never reach the apply branch\n";
        $bad++;
    } else {
        echo '   ok   refused: '.trim(strtok(str_replace("\n", ' ', $out), ' '))."\n";
    }
    @unlink($path);
}

echo $bad === 0 ? "\nevery mutant was refused\n" : "\n{$bad} problem(s)\n";
exit($bad === 0 ? 0 : 1);
