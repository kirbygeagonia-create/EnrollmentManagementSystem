<?php

/**
 * Pass 362 — the requirement matrix moves with the fifteenth wired screen.
 *
 * Seven claims in one document said fourteen screens and 22 owed. A tally that names the work while the work grows is
 * lane 61's defect, and this batch has already met it in the same row twice, so every one of the seven is replaced in
 * one pass with an asserted count, and the run refuses rather than half-editing a register.
 *
 * Usage: php storage/app/scratch/refresh-matrix-pass362.php [--apply]
 */
$root = dirname(__DIR__, 3);
chdir($root);
$apply = in_array('--apply', $argv, true);
$path = $root.'/Documentation/Prompt-Requirement-Matrix-2026-10-26.md';
$text = (string) file_get_contents($path);
$before = $text;

$edits = [
    ['fourteen screens use it and the other twenty-two', 'fifteen screens use it and the other twenty-one', 1],
    ['Fourteen of the 36 screens', 'Fifteen of the 36 screens', 1],
    ['the admin audit log and the subjects catalogue; the other 22 pages',
        'the admin audit log, the subjects catalogue and the staff directory; the other 21 pages', 1],
    ['That 22 counts', 'That 21 counts', 1],
    ['fourteen of the 36 pages that render the shared table use it', 'fifteen of the 36 pages that render the shared table use it', 1],
    ['22 of the 36 pages using the shared table still offer no order', '21 of the 36 pages using the shared table still offer no order', 1],
    ['the fourteen wired pages call', 'the fifteen wired pages call', 1],
    ['and on the student directory, which joined on 2026-10-30.',
        'and on the student directory, which joined on 2026-10-30; the staff directory joined on 2026-10-31 through the same mechanism, and it is the screen where the order reaches a nullable key (`offices` through `staffusers.officeId`, LEFT) and where the search had to be grouped before the office filter meant anything.', 1],
];

$fail = 0;
foreach ($edits as [$from, $to, $expect]) {
    $got = substr_count($text, $from);
    if ($got !== $expect) {
        printf("  BAD  occurs %d, wanted %d [%s]\n", $got, $expect, substr($from, 0, 60));
        $fail++;

        continue;
    }
    $text = str_replace($from, $to, $text);
    printf("  ok   %-58s -> %d\n", substr($from, 0, 56), $expect);
}
if ($fail > 0) {
    fwrite(STDERR, "REFUSED: a count did not match, so nothing was written.\n");

    exit(1);
}

if (! $apply) {
    echo "\nDRY RUN — nothing written.\n";

    exit(0);
}
$written = @file_put_contents($path, $text);
if ($written !== strlen($text)) {
    fwrite(STDERR, 'REFUSED: the write returned '.var_export($written, true).", so the matrix may be half-edited.\n");

    exit(1);
}

$after = (string) file_get_contents($path);
foreach ($edits as [$from, $to, $expect]) {
    // An append embeds its own needle, so the retired form is expected to SURVIVE as the head of the new sentence;
    // demanding its disappearance refuses a correct write (pass 341's rule, met again one batch later).
    $retiredOk = str_contains($to, $from) ? substr_count($after, $from) === $expect : substr_count($after, $from) === 0;
    if (substr_count($after, $to) !== $expect || ! $retiredOk) {
        echo "BAD  read-back failed for [".substr($to, 0, 50)."] — restoring.\n";
        file_put_contents($path, $before);

        exit(1);
    }
}
echo "written and read back.\n";
