<?php

/**
 * For a list of HEAD line numbers, print the live line(s) whose bytes match HEAD's.
 *
 * The citation gate and the shift tool both read only the FIRST number of a comma list, so a tail can age while the
 * head is repaired (lane 98). This resolves every number a reader names, one at a time, and prints each candidate
 * with the text it lands on, so a re-point is a reading rather than arithmetic.
 *
 * usage: php storage/app/scratch/lane127-resolve-lines.php <relative/file> <old> [<old> ...]
 */
$root = dirname(__DIR__, 3);
chdir($root);
$args = array_slice($argv ?? [], 1);
if (count($args) < 2) {
    fwrite(STDERR, "REFUSED: name a file and at least one HEAD line number.\n");

    exit(1);
}
$rel = $args[0];
$nums = array_slice($args, 1);

$out = 'storage/app/scratch/lane127-head-'.preg_replace('/[^\w.]/', '_', basename($rel));
$lold = [];
exec('git show HEAD:'.escapeshellarg($rel).' > '.escapeshellarg($out).' 2>&1', $lold, $rc);
if ($rc !== 0 || ! is_file($out)) {
    fwrite(STDERR, "REFUSED: no HEAD export for {$rel}.\n");

    exit(1);
}
$head = explode("\n", (string) file_get_contents($out));
$live = explode("\n", (string) file_get_contents($root.'/'.$rel));

foreach ($nums as $n) {
    $n = (int) $n;
    $needle = trim($head[$n - 1] ?? '');
    if ($needle === '') {
        printf("%-5d HEAD BLANK  — nothing to match; the pointer was already off before this batch\n", $n);

        continue;
    }
    $hits = [];
    foreach ($live as $i => $line) {
        if (trim($line) === $needle) {
            $hits[] = $i + 1;
        }
    }
    printf("%-5d %-14s %s\n", $n, $hits === [] ? 'GONE' : (count($hits) === 1 ? implode(',', $hits) : count($hits).' HITS'), substr($needle, 0, 88));
    if (count($hits) > 1) {
        printf("        candidates: %s\n", implode(', ', $hits));
    }
}

exit(0);
