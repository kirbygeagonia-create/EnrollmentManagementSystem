<?php

/**
 * Prove pass 105's guards can refuse.
 *
 * Each case edits ONE literal in the pass script, runs it, and requires a non-zero exit. Two rules
 * this harness enforces because both were violated in earlier passes:
 *
 *  - the find string must occur exactly once in the script, otherwise str_replace moves the payload
 *    AND the guard that checks it at the same time, and the guard "passes" having proven nothing;
 *  - the mutation must actually land — a find string that matches nothing is a silent pass.
 *
 * The mutants run against the document in dry-run mode: every case here is expected to refuse, so
 * nothing can be written even if a case were mis-scripted.
 */
$root = dirname(__DIR__, 3);
$engine = $root.'/storage/app/scratch/refresh-docx-pass105.php';
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$src = file_get_contents($engine);

$cases = [
    'A an anchor the document does not contain (the edit must be refused)' => [
        'the privacy of the documents disk, the folders Laravel must write into',
        'the privacy of the documents disk, the folders Laravel must never write into',
    ],
    'B the measured verdict is left out of the replacement' => [
        'and answers 204 on a server that never ran key:generate, while',
        'and answers 204 on a server,',
    ],
    'C a cited line that no longer says what the document claims' => [
        "    302 => 'private function uploadDiskIsPrivate(): array',",
        "    302 => 'private function notTheDiskRule(): array',",
    ],
    'D the corrected deployment claim is no longer named' => [
        'DEPLOYMENT.md §2 said of that folder that no web path reaches it',
        'DEPLOYMENT.md §2 said of that folder that every web path reaches it',
    ],
];

$bad = 0;
foreach ($cases as $name => [$from, $to]) {
    $hits = substr_count($src, $from);
    if ($hits !== 1) {
        printf("mutant %s\n  NOT USABLE: the find string occurs %d time(s); a shared literal would move the guard with it\n", $name, $hits);
        $bad++;

        continue;
    }

    $body = str_replace($from, $to, $src);
    if ($body === $src) {
        printf("mutant %s\n  NOT PLANTED (the file did not change)\n", $name);
        $bad++;

        continue;
    }

    $path = $root.'/storage/app/scratch/mutant-pass105.php';
    file_put_contents($path, $body);
    clearstatcache(true, $path);
    if (file_get_contents($path) !== $body) {
        printf("mutant %s\n  NOT PLANTED (the mutant file was not written)\n", $name);
        $bad++;
        @unlink($path);

        continue;
    }

    exec('cd "'.$root.'" && "'.$php.'" storage/app/scratch/mutant-pass105.php 2>&1', $lines, $code);
    $out = trim(preg_replace('/\s+/', ' ', implode(' ', $lines)));
    $lines = [];
    @unlink($path);

    if ($code === 0) {
        printf("mutant %s\n  *** SURVIVED — the script accepted it (exit 0): %s\n", $name, substr($out, -150));
        $bad++;
    } else {
        printf("mutant %s\n  refused (exit %d): %s\n", $name, $code, substr($out, -150));
    }
}

echo $bad === 0 ? "\nall 4 mutants refused\n" : "\n{$bad} problem(s)\n";
exit($bad === 0 ? 0 : 1);
