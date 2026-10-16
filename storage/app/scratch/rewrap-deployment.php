<?php
/**
 * Re-wrap the DEPLOYMENT.md line the previous edit made too long, and report every long line in the file.
 *
 * §7 is hard-wrapped near 118 columns; the inline insertion pushed one line past that. The anchor is
 * asserted to match exactly once before writing, and the report afterwards is what says whether the
 * paragraph now matches its neighbours or whether other lines were already long.
 */
$path = 'DEPLOYMENT.md';
$crlf = "\r\n";
$bytes = file_get_contents($path);

$from = 'a religion and the seeded roles — so they need a *seeded* database, and';
$to = 'a religion and the seeded roles — so they need a *seeded*'.$crlf.'database, and';

$hits = substr_count($bytes, $from);
if ($hits !== 1) {
    fwrite(STDERR, "ABORT: wrap anchor matched {$hits} times, expected exactly 1.\n");
    exit(1);
}

file_put_contents($path, str_replace($from, $to, $bytes));
echo "rewrapped\n";

$lines = explode($crlf, file_get_contents($path));
echo 'CRLF lines: '.count($lines).', bare LF: '.(substr_count(implode('', $lines), "\n"))."\n";
foreach ($lines as $n => $line) {
    if (strlen($line) > 120) {
        echo '  long line '.($n + 1).': '.strlen($line)." chars: ".substr($line, 0, 70)."…\n";
    }
}
