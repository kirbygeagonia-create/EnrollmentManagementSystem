<?php
/**
 * Record in DEPLOYMENT.md that one CI failure read two different ways, depending on which door the leg used.
 *
 * The §7 paragraph said all four legs "died on students_religionid_foreign". Measured: the two legs that
 * insert the student through the model did, and the two that post to the intake route were stopped by its
 * `exists:religions,religionId` rule — the app refused the bad reference — while the test reported only
 * Laravel's response-context helper failing. Both halves are worth keeping, so the sentence is corrected
 * rather than dropped.
 */
$path = 'DEPLOYMENT.md';
$crlf = "\r\n";
$bytes = file_get_contents($path);

$from = 'died on `students_religionid_foreign` — four errors, exit 2 — while the SQLite and Frontend jobs stayed green.';
$to = 'died on `students_religionid_foreign` — four errors, exit 2 — while the SQLite and Frontend jobs stayed'.$crlf.
    'green. The two legs that insert the student through the model named the constraint; the two that post to the'.$crlf.
    'intake route were refused by its `exists:religions,religionId` rule and printed Laravel\'s response-context'.$crlf.
    'helper instead (`Call to a member function all() on array`), so one cause read two different ways in the'.$crlf.
    'same log. The class now runs with exception handling off, which makes the intake legs say'.$crlf.
    '`The selected religion id is invalid.` — the sentence the app had been trying to print.';

$hits = substr_count($bytes, $from);
if ($hits !== 1) {
    fwrite(STDERR, "ABORT: the sentence to correct matched {$hits} times, expected exactly 1.\n");
    exit(1);
}

file_put_contents($path, str_replace($from, $to, $bytes));

$lines = explode($crlf, file_get_contents($path));
echo 'sentence corrected'.PHP_EOL;
foreach ($lines as $n => $line) {
    if (strlen($line) > 120) {
        echo '  long line '.($n + 1).': '.strlen($line)." chars\n";
    }
}
echo 'CRLF lines: '.count($lines).', bare LF: '.(substr_count(implode('', $lines), "\n"))."\n";
