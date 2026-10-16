<?php
/**
 * Prove the launcher's source pin can fail: remove the flag read and watch the test refuse.
 *
 * A guard that has never been broken is not evidence. This edits one line of the component, runs the test
 * file, restores the file from an in-memory copy, and asserts the restore is byte-identical — with the
 * mutation checked to have actually landed before the run, because an unapplied mutant reads as a pass.
 */
$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$jsx = $root.'/resources/js/Components/navigation/MegaAppLauncher.jsx';

$original = file_get_contents($jsx);
$from = 'const canEvaluationDesk = props.can?.evaluationDesk ?? false;';
$to = 'const canEvaluationDesk = false; // MUTANT: the flag is no longer read';

if (substr_count($original, $from) !== 1) {
    fwrite(STDERR, "ABORT: the line to mutate is not found exactly once.\n");
    exit(1);
}

file_put_contents($jsx, str_replace($from, $to, $original));
if (file_get_contents($jsx) === $original) {
    fwrite(STDERR, "ABORT: mutation did not change the file.\n");
    file_put_contents($jsx, $original);
    exit(1);
}
echo "mutant planted\n";

exec('cd "'.$root.'" && "'.$php.'" vendor/bin/phpunit tests/Feature/Rbac/LauncherCardVisibilityTest.php 2>&1', $lines, $code);
foreach (array_slice($lines, -6) as $l) {
    echo '  '.$l."\n";
}

file_put_contents($jsx, $original);
$restored = file_get_contents($jsx) === $original;
echo 'restored byte-identical: '.var_export($restored, true)."\n";

if ($code === 0) {
    fwrite(STDERR, "VERDICT: the source pin accepted the mutant — it is decoration.\n");
    exit(1);
}
echo "VERDICT: the pin refused the mutant (exit {$code}).\n";
exit($restored ? 0 : 1);
