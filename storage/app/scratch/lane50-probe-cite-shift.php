<?php

// Lane 50: which citations into the sixteen pages this batch touched now sit one row short?
// Every one of those files gained exactly one line (the formatter import) above its first edited cell, so a
// citation below that import reads +1 — but that is a claim to measure per file and per citation, not to assume.
//
//   php storage/app/scratch/lane50-probe-cite-shift.php

$root = dirname(__DIR__, 3);
$dump = $root.'/storage/app/scratch/docx-flat.txt';

if (! is_file($dump)) {
    fwrite(STDERR, "REFUSE: no flat dump. Run dump-docx-flat.php first.\n");
    exit(1);
}

$files = array_map('trim', array_filter(explode("\n", shell_exec('git diff --name-only -- resources/js') ?? '')));

if ($files === []) {
    fwrite(STDERR, "NO CONCLUSION: no changed files under resources/js — this probe is measuring nothing\n");
    exit(1);
}

$text = file_get_contents($dump);
$grandTotal = 0;
$ambiguous = 0;
$measured = 0;

foreach ($files as $rel) {
    $base = basename($rel);
    $headPath = $root.'/storage/app/scratch/lane50-head/'.str_replace('/', '__', $rel);

    // shell_exec reaches cmd.exe here, where git is not on PATH — an empty "HEAD version" must never read as
    // "nothing to re-point".
    if (! is_file($headPath)) {
        fwrite(STDERR, "REFUSE: no exported HEAD copy for $rel — run the git show loop first.\n");
        exit(1);
    }

    $head = file_get_contents($headPath);
    $work = @file_get_contents($root.'/'.$rel);

    if ($head === '' || $work === '') {
        fwrite(STDERR, "REFUSE: empty HEAD or working copy for $rel\n");
        exit(1);
    }

    $headLines = explode("\n", str_replace("\r\n", "\n", $head));
    $workLines = explode("\n", str_replace("\r\n", "\n", $work));
    $delta = count($workLines) - count($headLines);

    // Where did the batch insert? The first line that differs is the import line.
    $firstDiff = 0;

    foreach ($headLines as $i => $l) {
        if (($workLines[$i] ?? null) !== $l) {
            $firstDiff = $i + 1;
            break;
        }
    }

    // `Show.jsx` alone is seven different files, so match the parent directory with it and count any bare
    // spelling separately — an ambiguous citation has to be read, not quietly skipped.
    $parent = basename(dirname($rel));
    $needle = $parent.'/'.basename($rel);
    preg_match_all('/'.preg_quote($needle, '/').'\s*:\s*(\d+)(?:\s*-\s*\d+)?/', $text, $cites);
    $here = array_unique($cites[1] ?? []);
    sort($here);
    $bare = preg_match_all('/(?<![\w\/-])'.preg_quote(basename($rel), '/').'\s*:\s*\d+/', $text);
    $need = array_values(array_filter($here, fn ($n) => $n >= $firstDiff));
    $grandTotal += count($need);
    $ambiguous += $bare;
    $measured++;

    printf("%-34s delta=%+d  inserted line %-4d  cited %-3d  need +1: %-3d  bare-name cites left: %d  %s\n",
        $needle, $delta, $firstDiff, count($here), count($need), $bare, $need === [] ? '' : implode(',', $need));
}

printf("\n%d file(s) measured, %d citation(s) to re-point, %d bare-name citation(s) that only a reader can resolve.\n",
    $measured, $grandTotal, $ambiguous);

if ($measured !== count($files)) {
    fwrite(STDERR, "NO CONCLUSION: measured $measured of ".count($files)." changed files\n");
    exit(1);
}
