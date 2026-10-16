<?php

/**
 * Put the office number back on the Academic Evaluation card and require the tests to say so.
 *
 * This is the mutation that matters: the bug was never a missing right — office7_head held
 * evaluation.view and evaluation.sign the whole time — it was a descriptor that decided by where the
 * account sits. Restoring `officeId: 4` must break BOTH the source pin and the behavioral card test;
 * if either stays green, that test is decoration.
 *
 * Anchored on the comment line above the field rather than on `officeId: null`, because the file uses
 * that field seven times.
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$path = $root.'/resources/js/Components/navigation/MegaAppLauncher.jsx';

$find = "// right the desk's own route asks.\n                officeId: null,";
$fix = "// right the desk's own route asks.\n                officeId: 4,";

// A single-line revert does not prove the behavioural test: with `officeId: 4` restored but the hoisted
// right-check still in place, the hoist answers first and the card still shows — measured. So this
// mutant reverts the fix the way it was actually wrong: the card keyed to Guidance's office AND no
// rights check in front of it. Only then should office7_head lose the door it owns.
$hoistFind = "        if (item.route === 'evaluation.index') return canEvaluationDesk;\n";

$compound = in_array('--compound', $argv, true);

$baseline = file_get_contents($path);
$code = 0;
$lines = [];
$restored = false;

try {
    if (substr_count($baseline, $find) !== 1) {
        fwrite(STDERR, sprintf('NOT PLANTED: anchor occurs %d times\n', substr_count($baseline, $find)));
        exit(3);
    }
    if ($compound && substr_count($baseline, $hoistFind) !== 1) {
        fwrite(STDERR, sprintf("NOT PLANTED: the hoisted check occurs %d times\n", substr_count($baseline, $hoistFind)));
        exit(3);
    }

    $mutated = str_replace($find, $fix, $baseline);
    if ($compound) {
        $mutated = str_replace($hoistFind, '', $mutated);
    }

    file_put_contents($path, $mutated);
    clearstatcache(true, $path);
    if (file_get_contents($path) === $baseline) {
        fwrite(STDERR, "NOT PLANTED: the file did not change\n");
        exit(3);
    }

    exec(sprintf('cd "%s" && "%s" artisan test tests/Feature/Rbac/LauncherCardVisibilityTest.php 2>&1', $root, $php), $lines, $code);
    file_put_contents($root.'/storage/app/scratch/mut-launcher-office.log', implode("\n", $lines));
} finally {
    file_put_contents($path, $baseline);
    clearstatcache(true, $path);
    $restored = file_get_contents($path) === $baseline;
}

$failing = [];
foreach ($lines as $line) {
    if (str_contains($line, "\u{2A2F}") || str_contains($line, '⨯')) {
        $failing[] = trim(preg_replace('/\s{2,}\d[\d.]*s\s*$/u', '', trim($line)));
    }
}

$summary = '';
foreach ($lines as $line) {
    if (str_contains($line, 'Tests:')) {
        $summary = trim(preg_replace('/\s+/', ' ', $line));
    }
}

printf("mutant: the evaluation descriptor names office 4 again%s\n",
    $compound ? ' AND the hoisted right-check is removed (the full revert)' : ' (single-line revert)');
printf("  exit=%d  %s\n", $code, $summary);
foreach ($failing as $f) {
    printf("  died: %s\n", $f);
}
printf("  restored: %s\n", $restored ? 'yes' : 'NO — fix the component by hand');

$ok = $code !== 0 && count($failing) >= 1 && $restored;
printf("  %s\n", $ok ? 'ATTRIBUTED' : '*** NOT ATTRIBUTED ***');
exit($ok ? 0 : 1);
