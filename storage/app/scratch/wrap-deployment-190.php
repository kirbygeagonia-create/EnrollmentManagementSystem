<?php
/**
 * Word-wrap the one over-long line in DEPLOYMENT.md §7 to the width its neighbours use.
 *
 * The paragraph is a single 550-character line inside a section hard-wrapped near 118 columns, and it is
 * prose this project wrote, not inherited text. Wrapping is mechanical: split on spaces at 118 columns,
 * rejoin with CRLF, and only touch the line the previous probe reported.
 */
$path = 'DEPLOYMENT.md';
$crlf = "\r\n";
$bytes = file_get_contents($path);

$lines = explode($crlf, $bytes);
$target = 189; // zero-based: the line the probe reported as 190

if (! isset($lines[$target]) || strlen($lines[$target]) <= 120) {
    fwrite(STDERR, "ABORT: line 190 is not the long line any more — refusing to guess.\n");
    exit(1);
}

$wrapped = wordwrap($lines[$target], 118, "\n", false);
$lines[$target] = str_replace("\n", $crlf, $wrapped);

file_put_contents($path, implode($crlf, $lines));

$after = explode($crlf, file_get_contents($path));
echo 'rewrote line 190 into '.count(explode($crlf, $wrapped))." physical lines\n";
foreach ($after as $n => $line) {
    if (strlen($line) > 120) {
        echo '  still long: '.($n + 1).' ('.strlen($line)." chars)\n";
    }
}
echo 'CRLF lines now: '.count($after).', bare LF: '.(substr_count(implode('', $after), "\n"))."\n";
