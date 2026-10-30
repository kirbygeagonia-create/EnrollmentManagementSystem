<?php

/** One-time clause rewrite in AGENTS.md: state the sweep's property, not its census. */

$f = dirname(__DIR__, 3).'/AGENTS.md';
$s = file_get_contents($f);
$from = '(742 citations over 162 files as of docx pass 273; it exits non-zero on a';
$to = '(it reads the regenerated dump, so re-dump before believing a verdict, and its own census moves when a pointer is added — so no total is stated here; it exits non-zero on a';
$n = substr_count($s, $from);
if ($n !== 1) {
    fwrite(STDERR, "REFUSED: the clause occurs {$n}, want 1.\n");

    exit(1);
}
file_put_contents($f, str_replace($from, $to, $s));
echo "AGENTS.md: sweep census clause re-taken as a property, not a number\n";
