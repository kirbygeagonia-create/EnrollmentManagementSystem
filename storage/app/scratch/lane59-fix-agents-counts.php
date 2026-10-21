<?php

/**
 * Lane 59 — AGENTS.md's eight instrument figures, corrected to what each tool printed today.
 *
 * Each pair is asserted to match exactly once, in the file's own bytes: a needle that silently matches nothing is how a
 * prose fix reports success while the wrong number stays, and the same file has been repaired twice by scripts that
 * guessed the wrap. Nothing here rewrites a sentence — only the figures, so the surrounding prose about what each
 * checker caught stays where the author put it.
 *
 *   php storage/app/scratch/lane59-fix-agents-counts.php [--check]
 */

$root = dirname(__DIR__, 3);
$path = $root.'/AGENTS.md';
$bytes = (string) file_get_contents($path);

// Measured 2026-10-20 by lane59-collect-readings.php: the sweep answers 602 citations over 141 files, lane37 860
// references with 196 headings and 108 finding rows, lane37b 271 references, lane32 113 ranges with 9 flagged, and
// lane49 19 unread guards with 340 non-figure lines.
$FIXES = [
    ['(559 citations over 115 files; it exits non-zero on a',
        '(602 citations over 141 files; it exits non-zero on a'],
    ['107 symbol/range pairs inside 665 cited ranges)',
        '113 symbol/range pairs it checks, 9 pairs flagged for reading)'],
    ['judges the document\'s own 851 references against',
        'judges the document\'s own 860 references against'],
    ['(196 numbered headings, 96 finding rows',
        '(196 numbered headings, 108 finding rows'],
    ['judges the 243 references PHP comments',
        'judges the 271 references PHP comments'],
    ['(18 guards whose dash sits on a line it cannot join, 355 lines where',
        '(19 guards whose dash sits on a line it cannot join, 340 lines where'],
];

$bad = 0;
foreach ($FIXES as [$old, $new]) {
    $hits = substr_count($bytes, $old);
    if ($hits !== 1) {
        printf("FAIL %d match(es) for: %s\n", $hits, substr($old, 0, 72));
        $bad++;

        continue;
    }
    printf("ok   once: %s\n", substr($old, 0, 72));
    $bytes = str_replace($old, $new, $bytes);
}
if ($bad > 0) {
    printf("\n%d needle(s) did not match exactly once — NOTHING WRITTEN.\n", $bad);
    exit(1);
}
foreach (['559', '115 files', '851', '96 finding', '243 references', '18 guards', '355 lines', '665 cited'] as $gone) {
    if (str_contains($bytes, $gone)) {
        printf("FAIL %s is still in the file.\n", $gone);
        exit(1);
    }
}
if (in_array('--check', $argv, true)) {
    echo "\n--check: every needle matched once and nothing was written.\n";
    exit(0);
}
$before = (string) file_get_contents($path);
if ($bytes === $before) {
    echo "REFUSED: the bytes did not change.\n";
    exit(1);
}
file_put_contents($path, $bytes);
$after = (string) file_get_contents($path);
printf("\nwritten: %+d bytes; %d line(s) (was %d)\n",
    strlen($after) - strlen($before), count(preg_split('/\n/', $after) ?: []), count(preg_split('/\n/', $before) ?: []));
exit(0);
