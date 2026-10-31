<?php

/**
 * Re-point every markdown pointer this batch aged, one asserted replacement at a time.
 *
 * `lane76-shift-cites.php` moves the docx and nothing else; the `.md` files are outside its reach, and the gate that
 * does read them refuses only a blank line or a number past the end, so a pointer left thirty-eight rows short by
 * the block inserted into `index()` lands on live code in the right file and reports clean (lane 88's defect, met
 * again the same week). Every new number here came from `lane127-resolve-lines.php`, which matches HEAD's own bytes
 * against the working file; the two ranges were then proved by comparing the whole window in both versions.
 *
 * Usage: php storage/app/scratch/lane127-repoint-matrix.php [--apply]
 */
$root = dirname(__DIR__, 3);
chdir($root);
$apply = in_array('--apply', $argv, true);

$matrixRel = 'Documentation/Prompt-Requirement-Matrix-2026-10-26.md';
$agentsRel = 'AGENTS.md';

$jobs = [
    $matrixRel => [
        ['UserManagementController.php:237,245,306', 'UserManagementController.php:275,283,344', 1],
        ['UserManagementController.php:237,245', 'UserManagementController.php:275,283', 1],
        ['UserManagementController.php:78,144', 'UserManagementController.php:116,182', 1],
        ['UserManagementController.php:88,152', 'UserManagementController.php:126,190', 1],
        ['UserManagementController.php:182,280', 'UserManagementController.php:220,318', 1],
        ['UserManagementController.php:239,247', 'UserManagementController.php:277,285', 1],
        ['UserManagementController.php:80,115', 'UserManagementController.php:118,153', 1],
        ['UserManagementController.php:46-54,173-190', 'UserManagementController.php:78-86,211-228', 1],
        ['Students/Show.jsx:518-519', 'Students/Show.jsx:511-512', 2],
        ['Students/Show.jsx:506', 'Students/Show.jsx:499', 1],
    ],
    $agentsRel => [
        ['Students/Show.jsx:506-519', 'Students/Show.jsx:499-512', 1],
    ],
];

$fail = 0;
$backups = [];
/**
 * A citation token is counted as a WHOLE token. `UserManagementController.php:275,283` is a prefix of
 * `UserManagementController.php:275,283,344`, so a plain substring count of the shorter one found two and refused a
 * pass that had written every row correctly — lane 69's "a short citation is a prefix of a long one", reached this
 * time from the read-back side rather than the write side.
 */
$countToken = function (string $haystack, string $token): int {
    return preg_match_all('/'.preg_quote($token, '/').'(?![\d,\-])/', $haystack);
};
foreach ($jobs as $rel => $edits) {
    $path = $root.'/'.$rel;
    $before = (string) file_get_contents($path);
    $text = $before;
    foreach ($edits as [$from, $to, $expect]) {
        $got = $countToken($text, $from);
        if ($got === 0 && $countToken($text, $to) === $expect) {
            // A re-run of a pass whose needles this batch consumed is not a broken tool: say so plainly.
            printf("  ok   %-46s ALREADY APPLIED (%d of the new token in %s)\n", $from, $expect, basename($rel));

            continue;
        }
        if ($got !== $expect) {
            printf("  BAD  %-46s occurs %d time(s), wanted %d in %s\n", $from, $got, $expect, basename($rel));
            $fail++;

            continue;
        }
        $text = str_replace($from, $to, $text);
        printf("  ok   %-46s -> %s  (%d in %s)\n", $from, $to, $expect, basename($rel));
    }
    if ($fail > 0) {
        continue;
    }
    if ($apply) {
        $backups[$path] = $root.'/storage/app/scratch/lane127-'.basename($rel).'.bak';
        copy($path, $backups[$path]);
        $written = @file_put_contents($path, $text);
        if ($written !== strlen($text)) {
            printf("  BAD  write of %s returned %s\n", basename($rel), var_export($written, true));
            $fail++;

            continue;
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

// Read back: no retired token may survive where it was replaced, and the new ones must be present. Counted as whole
// tokens, for the prefix reason above — and a failure here really does restore, because the first version of this
// guard printed the word "restoring" and then only exited, leaving the file in the state it had just refused.
foreach ($jobs as $rel => $edits) {
    $path = $root.'/'.$rel;
    $after = (string) file_get_contents($path);
    foreach ($edits as [$from, $to, $expect]) {
        $old = $countToken($after, $from);
        $new = $countToken($after, $to);
        if ($new !== $expect || $old !== 0) {
            printf("  BAD  %s : [%s] occurs %d (wanted %d), retired [%s] occurs %d\n", basename($rel), $to, $new, $expect, $from, $old);
            if (isset($backups[$path]) && is_file($backups[$path])) {
                copy($backups[$path], $path);
                echo "       restored from ".$backups[$path]."\n";
            }

            exit(1);
        }
    }
}

echo "written and read back: every pointer in the matrix and this file names the line that carries its claim today.\n";
