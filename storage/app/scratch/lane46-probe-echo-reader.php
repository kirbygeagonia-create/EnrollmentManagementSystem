<?php

/**
 * Diagnostic: run lane46's own `$echoedKeys` closure — lifted verbatim out of the tool's bytes, not retyped —
 * against a handler file, and print what it reads. Exists because the debug branch reported
 * "(could not read the filters prop)" after the multi-line fix, and a checker cannot be argued with by hand.
 *
 * Usage: php storage/app/scratch/lane46-probe-echo-reader.php <controller path>
 */
$root = dirname(__DIR__, 3);
$target = $argv[1] ?? null;
if ($target === null) {
    fwrite(STDERR, "REFUSED: pass the controller path whose filters prop you want read.\n");

    exit(1);
}

$src = file_get_contents($root.'/storage/app/scratch/lane46-filter-contract.php');
$start = strpos($src, '$echoedKeys = function (string $body): ?array {');
if ($start === false) {
    fwrite(STDERR, "REFUSED: the closure's opener is not where this probe reads it.\n");

    exit(1);
}
$end = strpos($src, "\n};", $start);
if ($end === false) {
    fwrite(STDERR, "REFUSED: no closing `};` after the opener.\n");

    exit(1);
}
$closureText = substr($src, $start, $end + 3 - $start);

eval($closureText);

$body = file_get_contents($root.'/'.$target);
$keys = $echoedKeys($body);
printf("%s\n  echoes: %s\n", $target, $keys === null ? '(null — no echo read)' : implode(', ', $keys));

// And the same question on the method body alone, which is what the checker actually hands the reader.
if (preg_match('/public function index\(.*?\n    \}/s', $body, $m)) {
    $keys2 = $echoedKeys($m[0]);
    printf("  index() body only, %d bytes: %s\n", strlen($m[0]), $keys2 === null ? '(null)' : implode(', ', $keys2));
}
