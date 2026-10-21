<?php

/**
 * Lane 58 — the storyboard's header stops quoting a suite size it cannot keep current.
 *
 * Line 5 read "Test Suite Status: ✅ 274 tests passed, 1,638 assertions". The block two lines above declares when the
 * document was last *verified* (October 1, 2026), which is not when that figure was *measured* — the pair is the
 * September 24 run's, copied forward. A checker cannot tell a reader which of the two dates governs a number, so the
 * line states where the measured readings live instead of carrying a copy of one.
 *
 * Written as a script rather than by hand because the match count is the guard: the emoji and the two trailing spaces
 * are part of the bytes, and a needle that silently matches nothing is how a prose fix reports success while the old
 * sentence stays.
 *
 *   php storage/app/scratch/lane58-fix-storyboard.php [--check]
 */

$root = dirname(__DIR__, 3);
$path = $root.'/Documentation/System_Storyboard_Walkthrough.md';
$bytes = (string) file_get_contents($path);
$eol = str_contains($bytes, "\r\n") ? "\r\n" : "\n";

$old = '> **Test Suite Status:** ✅ 274 tests passed, 1,638 assertions (0 failures)  ';
$new = '> **Test Suite Status:** not stated here. §1.1 of `EMS Complete Documentation.docx` carries the measured readings, '
    .'each with the surface and the day it was read from; this line used to quote a September run, and a copy of a total '
    .'goes stale the moment a case is added.';

$hits = substr_count($bytes, $old);
if ($hits !== 1) {
    printf("REFUSED: the status line matched %d times, expected exactly 1 (nothing written).\n", $hits);
    exit(1);
}
foreach (['&', '<', '>'] as $c) {
    if (str_contains($new, $c) && ! str_contains($old, $c)) {
        printf("REFUSED: the replacement carries a raw %s.\n", $c);
        exit(1);
    }
}

if (in_array('--check', $argv, true)) {
    echo "ok: the needle matches exactly once, and the file is not rewritten (--check).\n";
    exit(0);
}

$out = str_replace($old, $new, $bytes);
if ($out === $bytes) {
    echo "REFUSED: the bytes did not change.\n";
    exit(1);
}
file_put_contents($path, $out);

$read = (string) file_get_contents($path);
printf("written: line 5 now reads\n  %s\n", trim(explode($eol, $read)[4] ?? '?'));
printf("eol preserved (%s), length change %+d bytes\n", $eol === "\r\n" ? 'crlf' : 'lf', strlen($read) - strlen($bytes));
exit(0);
