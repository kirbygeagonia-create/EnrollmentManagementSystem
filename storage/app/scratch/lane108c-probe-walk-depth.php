<?php

/**
 * Is the graph walker's depth bound tight enough? (a coverage probe, not a gate)
 *
 * `lane108c-check-the-narrowed-lists.php` answered `MISSING 0` with every page walking 37-38 files, and the walker
 * expands to depth 3 (page → component → component → component, then reads without expanding). A column painted
 * four components deep would therefore be invisible — and `Evaluation/Show` is already the repository's proof that
 * a desk read can live one level below the page file. So the bound is measured, not assumed: walk the SAME pages at
 * the bound and at depth 8, and print what the deeper walk sees that the bound does not.
 *
 * The `maxFiles` cap is reported too, because a truncated walk looks exactly like a page that reads nothing. The
 * population is taken from the audit's own `--json=`, so the probe cannot drift from the tool it checks.
 *
 * Usage: php storage/app/scratch/lane108c-probe-walk-depth.php
 */
$root = dirname(__DIR__, 3);
require __DIR__.'/page-read-graph.php';

// The pages the audit actually judges come from the AUDIT, through its own --json=, rather than from a second
// binding written here. The probe's first cut re-derived "which page does this action render" by taking the first
// render after each literal, picked nine DETAIL pages that are not under a narrowed load at all, printed NO-PAGE for
// every one of them, and still reported "depth bound inadequate on 0 page(s)" — a clean verdict over rows it never
// read, which is the failure this repository names in lane 46, 50 and 71.
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$json = $root.'/storage/app/scratch/lane108c-audit.json';
$tool = $root.'/storage/app/scratch/lane108c-check-the-narrowed-lists.php';
chdir($root);
exec(escapeshellarg($php).' '.escapeshellarg($tool).' '.escapeshellarg('--json='.$json).' 2>&1', $auditOut, $auditCode);
if (! in_array($auditCode, [0, 1], true)) {
    fwrite(STDERR, "REFUSED: the audit exited {$auditCode} — that is a refusal, not a verdict.\n"
        .implode("\n", array_slice($auditOut, 0, 6))."\n");

    exit(1);
}
$rows = json_decode((string) @file_get_contents($json), true);
if (! is_array($rows) || $rows === []) {
    fwrite(STDERR, "REFUSED: the audit wrote no JSON, so there is no population to probe.\n");

    exit(1);
}
$judged = [];
foreach ($rows as $r) {
    if ($r['component'] !== null) {
        $judged[$r['component']] = true;
    }
}
if ($judged === []) {
    fwrite(STDERR, "REFUSED: no judged row carries a component, which contradicts the fourteen the audit reads.\n");

    exit(1);
}

echo 'pages under a narrowed load, from the audit: '.count($judged)."\n\n";
$differences = 0;
$unread = 0;
foreach (array_keys($judged) as $component) {
    $page = $root.'/resources/js/Pages/'.$component.'.jsx';
    if (! is_file($page)) {
        // A page the probe cannot open is an unread row, and an unread row may not sit inside a "0 inadequate"
        // verdict — that is exactly how this probe's first run reported agreement over nine NO-PAGE rows.
        $unread++;
        printf("%-34s NO-PAGE (unread)\n", $component);

        continue;
    }
    $shallow = page_reachable_files($root, $page, 3, 400);
    $deep = page_reachable_files($root, $page, 8, 2000);
    $rs = page_student_column_reads($shallow['files']);
    $rd = page_student_column_reads($deep['files']);
    $extraFiles = array_diff(array_keys($deep['files']), array_keys($shallow['files']));
    $extraCols = array_values(array_diff(array_merge($rd['nondemographic'], $rd['names']),
        array_merge($rs['nondemographic'], $rs['names'])));
    $capped = count($deep['files']) >= 2000 ? ' CAP-HIT' : '';
    printf("%-34s depth3 %3d file(s) reads [%s] | depth8 %3d file(s)%s reads [%s] | extra files %d%s\n",
        $component, count($shallow['files']), implode(',', array_merge($rs['nondemographic'], $rs['names'])) ?: '-',
        count($deep['files']), $capped, implode(',', array_merge($rd['nondemographic'], $rd['names'])) ?: '-',
        count($extraFiles), $extraCols === [] ? '' : '  DIFFERS: '.implode(',', $extraCols));
    if ($extraCols !== []) {
        $differences++;
        foreach ($extraFiles as $ef) {
            echo '    newly reached: '.str_replace($root.'/resources/js/', '', $ef)."\n";
        }
    }
}

$read = count($judged) - $unread;
echo "\nbound tested on {$read} of ".count($judged).' page(s); depth bound inadequate on '.$differences
    ." page(s): the deeper walk names a student column the bound does not see.\n";
if ($unread > 0) {
    fwrite(STDERR, "REFUSED: {$unread} page(s) could not be opened, so the verdict above covers fewer pages than the"
        ." audit judges — an unread row is never reported as an agreeing one.\n");

    exit(1);
}
if ($differences === 0) {
    echo "On THIS corpus the bound reads everything a deeper walk would. The claim is about the {$read} pages\n"
        ."imported today; a component chain one level deeper than any of them holds is not covered by it.\n";
}

exit($differences > 0 ? 1 : 0);
