<?php

/**
 * Pass 369 — the two prose registers follow the seventeenth wired table.
 *
 * Same shape as pass 366: every claim is counted before it is written, a failure restores each file from its own
 * backup, and a re-run says ALREADY APPLIED instead of refusing on a needle it consumed.
 *
 * Usage: php storage/app/scratch/refresh-matrix-pass369.php [--apply]
 */
$root = dirname(__DIR__, 3);
chdir($root);
$apply = in_array('--apply', $argv, true);

$jobs = [
    'Documentation/Prompt-Requirement-Matrix-2026-10-26.md' => [
        ['sixteen screens use it and the other twenty', 'seventeen screens use it and the other nineteen', 1],
        ['Sixteen of the 36 screens', 'Seventeen of the 36 screens', 1],
        ['the staff directory and the courses catalogue; the other 20 pages',
            'the staff directory, the courses catalogue and the majors list; the other 19 pages', 1],
        ['That 20 counts', 'That 19 counts', 1],
        ['sixteen of the 36 pages that render the shared table use it', 'seventeen of the 36 pages that render the shared table use it', 1],
        ['20 of the 36 pages using the shared table still offer no order', '19 of the 36 pages using the shared table still offer no order', 1],
        ['the sixteen wired pages call', 'the seventeen wired pages call', 1],
    ],
    'AGENTS.md' => [
        ['still owed: 20 of the 36 pages', 'still owed: 19 of the 36 pages', 1],
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
        printf("  ok   %-56s -> %s\n", substr($from, 0, 54), substr($to, 0, 42));
    }
    if ($apply && $fail === 0) {
        $bak = $root.'/storage/app/scratch/lane138-'.basename($rel).'.bak';
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
            echo 'BAD  the new token is missing from '.basename($rel)." — restoring.\n";
            foreach ($backups as $path => $bak) {
                copy($bak, $path);
            }

            exit(1);
        }
    }
}

echo "written and read back.\n";
