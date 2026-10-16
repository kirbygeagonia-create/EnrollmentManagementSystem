<?php

/**
 * Measure a method's line span the way a docx citation needs it: the signature line through the first line
 * after it that closes the method body at the same indent.
 *
 * Written because a citation counted by eye off `sed -n` output drifted by one on the first attempt, and a
 * range that lands one line off still points at a live line — which is the failure mode the line sweep
 * cannot see and the meaning sweep was built for.
 *
 * Usage: php storage/app/scratch/measure-method-span.php <relative/file> <methodName> [...]
 */

$root = dirname(__DIR__, 3);
array_shift($argv);
$file = array_shift($argv);
$names = $argv;

if ($file === null || $names === []) {
    fwrite(STDERR, "usage: php storage/app/scratch/measure-method-span.php <file> <method> [...]\n");
    exit(1);
}

$lines = file($root.'/'.$file);

foreach ($names as $name) {
    $start = null;

    foreach ($lines as $i => $line) {
        if (preg_match('/^\s*(public|private|protected)\s+(static\s+)?function\s+'.preg_quote($name, '/').'\b/', $line)) {
            $start = $i;

            break;
        }
    }

    if ($start === null) {
        printf("%s::%s NOT FOUND in %s\n", $file, $name, $file);

        continue;
    }

    $indent = strlen($lines[$start]) - strlen(ltrim($lines[$start]));
    $end = null;

    for ($j = $start + 1, $n = count($lines); $j < $n; $j++) {
        if (rtrim($lines[$j]) === str_repeat(' ', $indent).'}') {
            $end = $j;

            break;
        }
    }

    printf("%s : %-46s %-9s %s\n", $file, $name.'()', ($start + 1).'-'.($end === null ? '?' : $end + 1), trim($lines[$start]));
}
