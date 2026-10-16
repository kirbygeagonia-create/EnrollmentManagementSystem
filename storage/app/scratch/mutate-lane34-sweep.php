<?php

/**
 * Mutation-proof for lane34 (negative-claim sweep).
 *
 * The tool now reports 0 defects on the real document, and "0" from a checker is worth exactly nothing until
 * the same checker is shown to catch a defect that exists. So each mutant feeds it a synthetic dump line whose
 * negative claim IS false against the live inventories, and the unmutated cases must stay clean.
 *
 * usage: php storage/app/scratch/mutate-lane34-sweep.php
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$tmp = $root.'/storage/app/scratch/mut-lane34';
@mkdir($tmp, 0777, true);
$sweep = $root.'/storage/app/scratch/lane34-negative-claim-sweep.php';

$run = function (string $name, string $text) use ($tmp, $php, $sweep): array {
    $file = $tmp.'/'.$name.'.txt';
    file_put_contents($file, $text);
    $out = [];
    $rc = 0;
    exec(escapeshellarg($php).' '.escapeshellarg($sweep).' '.escapeshellarg($file).' --verbose 2>&1', $out, $rc);

    return [implode("\n", $out), $rc];
};

// A real permission and a real route name, taken from the inventories the tool loads.
$cases = [
    // must be CAUGHT: denies a permission that the live system actually has
    'false-denial-permission' => [
        'P 1: The ID desk has no validate step and the module has no clinic.record permission on any role.',
        true,
    ],
    // must be CAUGHT: denies a route name that exists
    'false-denial-route' => [
        'P 2: There is no such route in the application: clearance.periods.extend.',
        true,
    ],
    // must stay CLEAN: an exception construction — the identifier is allowed by the sentence
    'except-construction' => [
        'P 3: No reference table has a student-type column except admissionrequirements.appliesTo.',
        false,
    ],
    // must stay CLEAN: negates one thing while naming another that exists
    'positive-half-named' => [
        'P 4: The window has no extend permission, gated instead on clearance.periods.manage.',
        false,
    ],
];

$fails = 0;
foreach ($cases as $name => [$line, $shouldFlag]) {
    [$out,] = $run($name, $line."\n");
    $flagged = (bool) preg_match('/(\d+) hit\(s\) to read/', $out, $m) && (int) $m[1] > 0;
    if ($flagged === $shouldFlag) {
        printf("%-8s %-24s flagged=%s as expected\n", $shouldFlag ? 'caught' : 'clean   ', $name, $flagged ? 'yes' : 'no');
    } else {
        $fails++;
        printf("WRONG    %-24s flagged=%s expected=%s\n%s\n", $name, $flagged ? 'yes' : 'no',
            $shouldFlag ? 'yes' : 'no', str_replace("\n", "\n  ", substr($out, 0, 400)));
    }
}

// The real document, for the record.
[$real, $rc] = $run('real', file_get_contents($root.'/storage/app/scratch/docx-flat.txt'));
preg_match('/(\d+) noun-inventory pair\(s\) tested; (\d+) hit/', $real, $mm);
printf("\nreal document: %s pair(s) tested, %s hit(s)\n", $mm[1] ?? '?', $mm[2] ?? '?');
preg_match('/(\d+) exact identifier\(s\) checked/', $real, $m3);

printf("\n%s\n", $fails === 0
    ? 'ALL MUTANTS CAUGHT — a clean verdict from lane 34 means something'
    : $fails.' case(s) wrong — lane 34 output is not trustworthy');
exit($fails === 0 ? 0 : 1);
