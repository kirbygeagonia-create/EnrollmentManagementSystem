<?php

/**
 * Lane 44 mutant harness — proves lane44-unique-keys.php can fail.
 *
 * Mutants are planted in a scratch COPY of the regenerated flat dump, never in the docx, and passed in through
 * --dump=. That flag is itself a mutant target: pass 41's harness reported five missed defect classes until it
 * was proven the override is read rather than accepted, so case 0 runs the checker against a copy that is
 * byte-identical to the real dump and requires the same green verdict — an ignored flag would still pass that,
 * which is why case 1 then plants a defect only the copy carries.
 *
 * Usage: php storage/app/scratch/mutate-lane44-unique-keys.php
 */

$root = dirname(__DIR__, 3);
$php = getenv('EMS_PHP') ?: 'C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe';
$checker = $root.'/storage/app/scratch/lane44-unique-keys.php';
$dump = $root.'/storage/app/scratch/docx-flat.txt';
$copy = $root.'/storage/app/scratch/lane44-mutant-dump.txt';

$original = (string) file_get_contents($dump);

$run = function (string $dumpPath) use ($php, $root, $checker): array {
    $out = [];
    $code = 0;
    exec('cd /d '.escapeshellarg($root).' && '.escapeshellarg($php).' '.escapeshellarg($checker)
        .' --dump='.escapeshellarg($dumpPath).' 2>&1', $out, $code);

    return [$code, implode("\n", $out)];
};

$failures = 0;
$case = function (string $name, string $mutated, string $needle, bool $expectFail = true) use ($run, $copy, &$failures): void {
    file_put_contents($copy, $mutated);
    [$code, $out] = $run($copy);
    $verdict = $expectFail ? ($code !== 0 && str_contains($out, $needle)) : ($code === 0);

    printf("  %s %-38s exit=%d  %s\n", $verdict ? 'ok  ' : 'BAD ', $name, $code,
        $expectFail ? (str_contains($out, $needle) ? 'needle found' : 'needle MISSING: '.$needle)
            : ($code === 0 ? 'clean as expected' : 'unexpectedly red'));

    if (! $verdict) {
        printf("       %s\n", trim(preg_replace('/\s+/', ' ', mb_substr($out, 0, 300))));
        $failures++;
    }
};

echo "=== case 0: the flag must be read — an unmutated copy has to look like the real file ===\n";
$case('clean copy through --dump=', $original, '', false);

echo "\n=== planted defects in the copy ===\n";

// A key the server enforces but the table no longer lists.
$case('a listed key row removed',
    str_replace('ROW 1237: uq_documentprintlog_issue', 'ROW 1237: uq_not_on_this_server', $original),
    'MISS  uq_documentprintlog_issue');

// A key the table lists that the server does not have.
$case('a phantom key added',
    str_replace('ROW 1237: uq_documentprintlog_issue', 'ROW 1237: uq_invented_for_the_mutant', $original),
    'PHANTOM');

// The count sentence moved back to the pre-pass-140 figure.
$case('the count reverted to eight',
    str_replace('Nine keys in seven tables', 'Eight keys in seven tables', $original),
    'the sentence says eight keys');

// The sentence removed entirely — a claim the checker cannot read must not look checked.
$case('the count sentence unreadable',
    str_replace('Nine keys in seven tables', 'Keys are listed below', $original),
    'UNBOUND');

@unlink($copy);

printf("\n  %d case(s) failed.\n", $failures);

exit($failures === 0 ? 0 : 1);
