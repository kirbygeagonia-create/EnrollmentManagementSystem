<?php

/** Print the line span of each method in a file, so a citation can be re-measured by meaning. */

$file = $argv[1] ?? 'app/Console/Commands/AssertNoSilentSkips.php';
$lines = file($file);
$start = null;
$name = null;
$spans = [];

foreach ($lines as $i => $line) {
    if (preg_match('/^    (?:private|public|protected) (?:static )?function (\w+)/', $line, $m)) {
        if ($name !== null) {
            $spans[$name][1] = $i;          // previous method ended on the line before
        }
        $name = $m[1];
        $spans[$name] = [$i + 1, count($lines)];
    }
}

printf("%s  (%d lines)\n", $file, count($lines));

foreach ($spans as $m => [$from, $to]) {
    printf("  %-22s %s\n", $m.'()', $from.'-'.$to);
}

// The class constant, wherever it sits, because the docx cites it as a span too.
foreach ($lines as $i => $line) {
    if (preg_match('/private const ALLOWED/', $line)) {
        $c = $i + 1;
        $end = $c;
        foreach (array_slice($lines, $i) as $j => $l) {
            if (rtrim($l) === '    ];') {
                $end = $c + $j;

                break;
            }
        }
        printf("  %-22s %s\n", 'ALLOWED const', $c.'-'.$end);

        break;
    }
}
