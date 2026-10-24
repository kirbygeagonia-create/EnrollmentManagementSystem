<?php

/**
 * Move the remaining print views onto App\Support\YearLevel. Asserts its own match count: every file it
 * names must change exactly once, and no print view may still build "Year " by hand afterwards.
 */
$root = dirname(__DIR__, 3);

$targets = [
    'clearance-slip' => [
        "{{ \$termEnrollment?->yearLevel ? 'Year '.\$termEnrollment->yearLevel : 'Year N/A' }}",
        "{{ \App\Support\YearLevel::format(\$termEnrollment?->yearLevel) ?? 'Year N/A' }}",
    ],
    'enrollment-certificate' => [
        "{{ \$enrollment->yearLevel ? 'Year '.\$enrollment->yearLevel : 'Year N/A' }}",
        "{{ \App\Support\YearLevel::format(\$enrollment->yearLevel) ?? 'Year N/A' }}",
    ],
    'enrollment-form' => [
        "{{ \$enrollment->yearLevel ? 'Year '.\$enrollment->yearLevel : 'Year N/A' }}",
        "{{ \App\Support\YearLevel::format(\$enrollment->yearLevel) ?? 'Year N/A' }}",
    ],
    'subject-load' => [
        "{{ \$enrollment->yearLevel ? 'Year '.\$enrollment->yearLevel : 'Year N/A' }}",
        "{{ \App\Support\YearLevel::format(\$enrollment->yearLevel) ?? 'Year N/A' }}",
    ],
];

$fails = 0;
foreach ($targets as $name => [$from, $to]) {
    $path = $root.'/resources/views/prints/'.$name.'.blade.php';
    $bytes = (string) file_get_contents($path);
    $hits = substr_count($bytes, $from);
    if ($hits !== 1) {
        printf("  BAD   %-24s needle occurred %d times, expected 1 — nothing written\n", $name, $hits);
        $fails++;

        continue;
    }
    file_put_contents($path, str_replace($from, $to, $bytes));
    $after = (string) file_get_contents($path);
    printf("  ok    %-24s moved onto YearLevel::format\n", $name);
    if (substr_count($after, $to) !== 1 || str_contains($after, $from)) {
        printf("  BAD   %-24s the replacement did not land cleanly\n", $name);
        $fails++;
    }
}

$left = [];
foreach (glob($root.'/resources/views/prints/*.blade.php') as $f) {
    if (preg_match("/'Year '\\.\\\$/", (string) file_get_contents($f))) {
        $left[] = basename($f);
    }
}
if ($left !== []) {
    printf("  BAD   still building the level by hand: %s\n", implode(', ', $left));
    $fails++;
} else {
    echo "  ok    no print view builds \"Year \" by hand any more\n";
}

printf("\n%s\n", $fails === 0 ? 'DONE — every blade reads the one helper.' : "{$fails} problem(s) — check before continuing.");
exit($fails === 0 ? 0 : 1);
