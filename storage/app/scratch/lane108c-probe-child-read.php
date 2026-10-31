<?php

/**
 * Does a child PHP process see bytes the parent wrote one moment earlier?
 *
 * `mutate-lane108c-narrowed-audit.php` plants a student-column read into `Registrar/Index.jsx`, then runs the
 * auditor as a child process. The parent's own `file_get_contents()` shows the planted line, and the parent's own
 * call to `page_student_column_reads()` returns `suffix` — yet the child reports MISSING 0, i.e. it read a file
 * without the plant. This tree lives under OneDrive, so "the write is not visible to the next process" is a real
 * candidate rather than a guess, and if it is true it reaches EVERY harness here, because each one plants then
 * shells out to `artisan test`.
 *
 * The probe prints, in order: the parent's read, a cmd-level `findstr`, and a child PHP read of the same path, with
 * and without `clearstatcache()`. It restores the file before printing its verdict.
 *
 * Usage: php storage/app/scratch/lane108c-probe-child-read.php
 */
$root = dirname(__DIR__, 3);
$page = $root.'/resources/js/Pages/Registrar/Index.jsx';
$snap = (string) file_get_contents($page);
$probe = 'const lane108cVisibilityProbe = (row) => row?.student?.suffix ?? 0;';

if (str_contains($snap, 'lane108cVisibilityProbe')) {
    fwrite(STDERR, "REFUSED: the target already carries this probe's marker, so the control cannot be measured.\n");

    exit(1);
}
$planted = str_replace('export default function', $probe."\n".'export default function', $snap);
if ($planted === $snap) {
    fwrite(STDERR, "REFUSED: the needle matched nothing, so nothing was planted.\n");

    exit(1);
}
file_put_contents($page, $planted);

$parentSees = str_contains((string) file_get_contents($page), 'lane108cVisibilityProbe');
printf("1. parent re-read after write: %s\n", $parentSees ? 'SEES the plant' : 'does NOT see it');

$child = <<<'PHPE'
$p = (string) file_get_contents(getcwd().'/resources/js/Pages/Registrar/Index.jsx');
echo str_contains($p, 'lane108cVisibilityProbe') ? 'SEES the plant' : 'does NOT see it', PHP_EOL;
PHPE;
file_put_contents($root.'/storage/app/scratch/lane108c-child-read.php', "<\x3Fphp\n".$child."\n");
chdir($root);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';

$out = [];
exec(escapeshellarg($php).' '.escapeshellarg($root.'/storage/app/scratch/lane108c-child-read.php').' 2>&1', $out);
printf("2. child process, no clearstatcache: %s\n", trim(implode(' ', $out)));

$out2 = [];
$cmd2 = escapeshellarg($php).' -r '.escapeshellarg('clearstatcache(true, getcwd()."/resources/js/Pages/Registrar/Index.jsx"); $p=(string)file_get_contents(getcwd()."/resources/js/Pages/Registrar/Index.jsx"); echo str_contains($p,"lane108cVisibilityProbe") ? "SEES the plant" : "does NOT see it";');
exec($cmd2.' 2>&1', $out2);
printf("3. child process, clearstatcache first: %s\n", trim(implode(' ', $out2)));

// The decisive question is the auditor itself: does ITS walk see the planted column?
$auditOut = [];
exec(escapeshellarg($php).' '.escapeshellarg($root.'/storage/app/scratch/lane108c-check-the-narrowed-lists.php').' 2>&1', $auditOut, $auditCode);
$missing = 0;
foreach ($auditOut as $l) {
    if (preg_match('/^\s+MISSING\s+(\d+)$/', $l, $m) === 1) {
        $missing = (int) $m[1];
    }
}
printf("4. auditor child process: MISSING %d (want 1 while the plant stands), exit %d\n", $missing, $auditCode);

file_put_contents($page, $snap);
clearstatcache();
printf("5. restored: %s\n", str_contains((string) file_get_contents($page), 'lane108cVisibilityProbe') ? 'NO — plant remains' : 'yes');

if (! $parentSees) {
    echo "\nVERDICT: the write is not even visible to the writer; the harness must use a different mechanism.\n";
} elseif ($missing === 1) {
    echo "\nVERDICT: the child DOES see the plant, so the harness's own reading is the defect, not the filesystem.\n";
} else {
    echo "\nVERDICT: the parent sees the bytes and the child does not — a same-directory write is not reliable\n"
        ."across a process boundary here, which reaches every plant-then-exec harness in this folder.\n";
}
