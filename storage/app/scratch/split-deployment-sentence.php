<?php
/**
 * Split one DEPLOYMENT sentence that conflated two different databases.
 *
 * "subject 5 is RH1 … and the run aborts" is true only where the reference rows were rebuilt: on a pristine
 * install id 5 exists, holds the wrong subject, and the run continues into the mislabelled fee rows. The
 * distinction is the whole finding — a wrong row that lands is worse than an abort — so it is stated.
 */
$path = 'DEPLOYMENT.md';
$crlf = "\r\n";
$bytes = file_get_contents($path);

$from = 'is the **Clearance Slip Replacement**, so every demo charge attaches to the wrong paper and the foreign'.$crlf.
    'key accepts it; subject 5 is RH1, not IT101, and the run aborts on the schedule insert. Both are fixed by';

$to = 'is the **Clearance Slip Replacement**, so every demo charge attaches to the wrong paper and the foreign'.$crlf.
    'key accepts it, and subject 5 is RH1 rather than IT101 — a wrong row that lands quietly. Where the reference'.$crlf.
    'rows had been rebuilt a second time those numbers are absent altogether and the run aborts on the schedule'.$crlf.
    'insert. Both are fixed by';

$hits = substr_count($bytes, $from);
if ($hits !== 1) {
    fwrite(STDERR, "ABORT: the sentence matched {$hits} times, expected exactly 1.\n");
    exit(1);
}

file_put_contents($path, str_replace($from, $to, $bytes));

$lines = explode($crlf, file_get_contents($path));
echo 'sentence split'.PHP_EOL;
foreach ($lines as $n => $line) {
    if (strlen($line) > 120) {
        echo '  long line '.($n + 1).' ('.strlen($line)." chars)\n";
    }
}
echo 'CRLF lines: '.count($lines).', bare LF: '.(substr_count(implode('', $lines), "\n"))."\n";
