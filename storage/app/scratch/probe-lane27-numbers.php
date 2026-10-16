<?php

/**
 * The remaining numbers lane 27 needs, read off the structure rather than guessed.
 *
 * Each citation the sweep flagged names a construct in its sentence; this prints the construct's actual
 * bounds in the file as it stands, so the new number is chosen because the statement is inside it.
 */

$root = dirname(__DIR__, 3);

$show = function (string $rel, int $from, int $to) use ($root): void {
    $lines = file($root.'/'.$rel);
    echo "\n---- {$rel}  {$from}-{$to}\n";
    foreach (range($from, min($to, count($lines))) as $n) {
        printf("%4d  %s\n", $n, rtrim($lines[$n - 1]));
    }
};

$p = file($root.'/app/Providers/AuthServiceProvider.php');
$start = null;
$end = null;
foreach ($p as $i => $line) {
    if (str_contains($line, 'protected $policies')) {
        $start = $i + 1;
    }
    if ($start !== null && $end === null && $i + 1 > $start && preg_match('/^\s*\];/', $line)) {
        $end = $i + 1;
    }
}
printf("policies array: %s-%s   (file %d lines)\n", $start, $end, count($p));

$show('app/Providers/AuthServiceProvider.php', 124, 136);
$show('app/Providers/AuthServiceProvider.php', 183, 216);
$show('app/Policies/PaymentPolicy.php', 30, 55);
$show('app/Policies/AssessmentPolicy.php', 30, 50);
$show('app/Http/Controllers/Registrar/RegistrarController.php', 176, 186);
$show('app/Http/Controllers/Assessment/AssessmentController.php', 240, 250);
$show('app/Http/Controllers/Assessment/AssessmentController.php', 412, 440);
