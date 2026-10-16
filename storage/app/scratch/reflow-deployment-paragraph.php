<?php
/**
 * Re-flow the "How the walkthrough gets MySQL" paragraph to one wrap width.
 *
 * Two hand edits in a row left a ragged paragraph — one 130-column line, then a two-word orphan — inside a
 * section whose neighbours break near 118 columns. This joins the paragraph back to a single logical line,
 * re-wraps it, and writes only that paragraph. The words are unchanged; a sentence-count assertion is the
 * proof of that.
 */
$path = 'DEPLOYMENT.md';
$crlf = "\r\n";
$bytes = file_get_contents($path);

$start = '**How the walkthrough gets MySQL.**';
$endMarker = $crlf.$crlf;

$from = strpos($bytes, $start);
if ($from === false || substr_count($bytes, $start) !== 1) {
    fwrite(STDERR, "ABORT: the paragraph start is not uniquely found.\n");
    exit(1);
}

$to = strpos($bytes, $endMarker, $from);
if ($to === false) {
    fwrite(STDERR, "ABORT: the paragraph has no blank-line end.\n");
    exit(1);
}

$original = substr($bytes, $from, $to - $from);
$joined = str_replace($crlf, ' ', $original);
$joined = preg_replace('/\s+/', ' ', $joined);

$wrapped = wordwrap($joined, 118, "\n", false);
$wrapped = str_replace("\n", $crlf, $wrapped);

$wordsBefore = str_word_count($joined);
$wordsAfter = str_word_count(str_replace($crlf, ' ', $wrapped));
if ($wordsBefore !== $wordsAfter) {
    fwrite(STDERR, "ABORT: re-flow changed the words ({$wordsBefore} → {$wordsAfter}).\n");
    exit(1);
}

file_put_contents($path, substr_replace($bytes, $wrapped, $from, $to - $from));

$lines = explode($crlf, file_get_contents($path));
echo 'paragraph re-flowed into '.count(explode($crlf, $wrapped))." lines, {$wordsBefore} words preserved\n";
foreach (array_slice($lines, 180, 18) as $n => $line) {
    if (strlen($line) > 120) {
        echo '  still long: line '.($n + 181).' ('.strlen($line)." chars)\n";
    }
}
