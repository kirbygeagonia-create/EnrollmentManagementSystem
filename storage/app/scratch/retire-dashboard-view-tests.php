<?php

/**
 * PermissionMatrixTest mentions dashboard.view six times: a docblock list of what bare Staff holds,
 * a data-provider row asserting the /dashboard route needs it, and four role lists. The route row is
 * the reason the right looked meaningful — it passed because /dashboard is reachable by anyone signed
 * in, not because dashboard.view gates it.
 *
 * Asserted: 6 mentions before, 0 after, and exactly three lines disappear (the two standalone list
 * entries and the provider row); the three inline mentions lose only their token.
 */

$path = dirname(__DIR__, 3).'/tests/Feature/Rbac/PermissionMatrixTest.php';
$apply = in_array('--apply', $argv, true);

$src = file_get_contents($path);
$original = $src;

$mentions = preg_match_all('/dashboard\.view/', $src);
if ($mentions !== 6) {
    fwrite(STDERR, "REFUSE: {$mentions} mentions, expected 6\n");
    exit(1);
}

// Whole-line removals first, so an inline pass cannot half-edit them.
$lines = [
            "            ['dashboard', 'dashboard.view', true],\n",
            "            'dashboard.view',\n",
];
$removedLines = 0;
foreach ($lines as $line) {
    $hits = substr_count($src, $line);
    if ($hits === 0) {
        fwrite(STDERR, "REFUSE: expected line not found: ".trim($line)."\n");
        exit(1);
    }
    $src = str_replace($line, '', $src);
    $removedLines += $hits;
}

// Inline mentions: inside a docblock list, and trailing a grant list.
$inline = [
    " user.view, dashboard.view\n" => " user.view\n",
    " 'audit.view', 'dashboard.view'," => " 'audit.view',",
    " 'admission.view', 'dashboard.view', 'user.view'," => " 'admission.view', 'user.view',",
];
foreach ($inline as $needle => $replacement) {
    $hits = substr_count($src, $needle);
    if ($hits !== 1) {
        fwrite(STDERR, "REFUSE: inline form seen {$hits} times, expected 1: ".trim($needle)."\n");
        exit(1);
    }
    $src = str_replace($needle, $replacement, $src);
}

if (substr_count($src, 'dashboard.view') !== 0) {
    fwrite(STDERR, "REFUSE: ".substr_count($src, 'dashboard.view')." mention(s) survive\n");
    exit(1);
}
printf("removed %d whole lines and 3 inline mentions; %d -> %d lines\n",
    $removedLines, substr_count($original, "\n"), substr_count($src, "\n"));

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}
file_put_contents($path, $src);
echo "Written\n";
