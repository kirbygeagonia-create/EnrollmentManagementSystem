<?php

/**
 * Re-take the one instrument figure this batch moved, and say why it moved instead of retyping a digit.
 *
 * `lane59-instrument-counts.php` binds a figure to the tool named in its own sentence and refuses when the tool
 * prints something else. Three docx passes rewrote §28.3's G-40 row and today's §1.1 row, and four `§` tokens left
 * with them, so the sweep's census fell — a stale claim of a true reading, which is the defect this file keeps
 * refusing to leave standing.
 *
 * Usage: php storage/app/scratch/lane101-re-take-the-reference-census.php [--apply]
 */

$root = dirname(__DIR__, 3);
$file = $root.'/AGENTS.md';
$apply = in_array('--apply', $argv, true);
$php = PHP_BINARY;

$out = (string) shell_exec(escapeshellarg($php).' '.escapeshellarg($root.'/storage/app/scratch/lane37-section-reference-sweep.php').' 2>&1');
if (preg_match('/finding rows indexed: (\d+) \| \x{00A7}-references checked: (\d+)/u', $out, $m) !== 1) {
    fwrite(STDERR, "REFUSED: the sweep printed no census line to read.\n".mb_substr($out, 0, 300)."\n");

    exit(1);
}
[$rows, $refs] = [(int) $m[1], (int) $m[2]];

$from = 'judges the 1031 references it resolves against';
$to = 'judges the '.$refs.' references it resolves against (read off the sweep on 2026-10-30, when three docx passes '
    .'rewrote §28.3\'s G-40 row and the day\'s §1.1 row and four pointers sat inside the sentences that were replaced; '
    .'that line prints '.$rows.' finding rows and '.$refs.' references today, and this clause was generated from it '
    .'rather than retyped)';

$text = (string) file_get_contents($file);
$n = substr_count($text, $from);
if ($n !== 1) {
    fwrite(STDERR, "REFUSED: the clause occurs {$n} time(s), want 1.\n");

    exit(1);
}
$text = str_replace($from, $to, $text);

printf("%s: the reference census re-taken from the tool (%d)\n", $apply ? 'APPLY' : 'DRY RUN', $refs);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

file_put_contents($file, $text);
echo "APPLIED.\n";
