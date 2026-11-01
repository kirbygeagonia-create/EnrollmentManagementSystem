<?php

/**
 * Pass 366 — the two prose registers follow the sixteenth wired table.
 *
 * Seven claims in the requirement matrix and one in AGENTS.md said fifteen screens and 21 owed; the courses
 * catalogue joined, so every one of them moves in a single pass with an asserted count. A tally that names the work
 * while the work grows is lane 61's defect, and this family has been caught doing it in both documents before — the
 * matrix row that kept saying "eight of the 36" for six screens, and §1.1's row that kept saying 35 of 36 after two
 * queues had already been wired.
 *
 * Re-runnable: every edit knows whether its own replacement already landed, so a second run says ALREADY APPLIED
 * instead of refusing on a needle it consumed (lane 87's one-shot-patcher trap).
 *
 * Usage: php storage/app/scratch/refresh-matrix-pass366.php [--apply]
 */
$root = dirname(__DIR__, 3);
chdir($root);
$apply = in_array('--apply', $argv, true);

$jobs = [
    'Documentation/Prompt-Requirement-Matrix-2026-10-26.md' => [
        ['fifteen screens use it and the other twenty-one', 'sixteen screens use it and the other twenty', 1],
        ['Fifteen of the 36 screens', 'Sixteen of the 36 screens', 1],
        ['the subjects catalogue and the staff directory; the other 21 pages',
            'the subjects catalogue, the staff directory and the courses catalogue; the other 20 pages', 1],
        ['That 21 counts', 'That 20 counts', 1],
        ['fifteen of the 36 pages that render the shared table use it', 'sixteen of the 36 pages that render the shared table use it', 1],
        ['21 of the 36 pages using the shared table still offer no order', '20 of the 36 pages using the shared table still offer no order', 1],
        ['the fifteen wired pages call', 'the sixteen wired pages call', 1],
    ],
    'AGENTS.md' => [
        ['still owed: 21 of the 36 pages', 'still owed: 20 of the 36 pages', 1],
    ],
];

$fail = 0;
$backups = [];
foreach ($jobs as $rel => $edits) {
    $path = $root.'/'.$rel;
    $text = (string) file_get_contents($path);
    foreach ($edits as [$from, $to, $expect]) {
        $got = substr_count($text, $from);
        if ($got === 0 && substr_count($text, $to) === $expect) {
            printf("  ok   %-56s ALREADY APPLIED (%s)\n", substr($from, 0, 54), basename($rel));

            continue;
        }
        if ($got !== $expect) {
            printf("  BAD  occurs %d, wanted %d [%s] in %s\n", $got, $expect, substr($from, 0, 50), basename($rel));
            $fail++;

            continue;
        }
        $text = str_replace($from, $to, $text);
        printf("  ok   %-56s -> %s\n", substr($from, 0, 54), substr($to, 0, 40));
    }
    if ($apply && $fail === 0) {
        $bak = $root.'/storage/app/scratch/lane132-'.basename($rel).'.bak';
        copy($path, $bak);
        $backups[$path] = $bak;
        $written = @file_put_contents($path, $text);
        if ($written !== strlen($text)) {
            printf("  BAD  the write of %s returned %s\n", basename($rel), var_export($written, true));
            $fail++;
        }
    }
}

if ($fail > 0) {
    foreach ($backups as $path => $bak) {
        copy($bak, $path);
    }
    fwrite(STDERR, $backups === []
        ? "REFUSED: a count did not match, so nothing was written.\n"
        : "REFUSED: a count did not match, and each file was restored from its own backup.\n");

    exit(1);
}

if (! $apply) {
    echo "\nDRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

foreach ($jobs as $rel => $edits) {
    $after = (string) file_get_contents($root.'/'.$rel);
    foreach ($edits as [$from, $to, $expect]) {
        if (substr_count($after, $to) !== $expect) {
            echo "BAD  the new token is missing from ".basename($rel)." — restoring.\n";
            foreach ($backups as $path => $bak) {
                copy($bak, $path);
            }

            exit(1);
        }
    }
}

echo "written and read back.\n";
