<?php

// Lane 51 follow-through: the demo pipeline dated its "today" rows with the UTC date, while the desk reports
// now read the school's day. Between 16:00Z and midnight the two disagree, so a freshly seeded demonstration
// receipt would not appear on the very screen the pipeline exists to demonstrate — and the E2E test that
// guards that pairing asked UTC the same wrong question. Every one of these values is a civil calendar day a
// person reads, so they all take the one declaration.
//
// Dry run unless --apply. The count is asserted, because a bulk replace that matched nothing still "succeeds".

$root = dirname(__DIR__, 3);
$apply = in_array('--apply', $argv, true);

$targets = [
    'tools/seed_full_demo_pipeline.php' => ['expected' => 14, 'import' => 'use App\\Support\\SchoolTime;'],
    'tests/Feature/E2E/DemoDatasetRecordStatesTest.php' => ['expected' => 1, 'import' => 'use App\\Support\\SchoolTime;'],
];

$fail = 0;

foreach ($targets as $rel => $spec) {
    $path = $root.'/'.$rel;
    $bytes = (string) file_get_contents($path);
    $found = substr_count($bytes, 'now()->toDateString()');

    printf("  %-46s now()->toDateString() x%d (expected %d)\n", $rel, $found, $spec['expected']);

    if ($found !== $spec['expected']) {
        $fail++;

        continue;
    }

    $next = str_replace('now()->toDateString()', 'SchoolTime::today()', $bytes);

    if (! str_contains($next, $spec['import'])) {
        // Put it after the last App\Services import so the group order survives; a tool file is not Pint's
        // problem, but it is the next reader's.
        $anchor = (string) (preg_match('/^use App\\\\Services\\\\[^;]+;$/m', $next, $m) ? $m[0] : 'use App\\Enums\\AcademicStanding;');
        $at = strpos($next, $anchor);

        if ($at === false) {
            fwrite(STDERR, "REFUSE: no import anchor in $rel\n");
            $fail++;

            continue;
        }

        $next = substr_replace($next, "\n".$spec['import'], $at + strlen($anchor), 0);
    }

    if (substr_count($next, 'SchoolTime::today()') !== $spec['expected']) {
        fwrite(STDERR, "REFUSE: the replacement count is wrong in $rel\n");
        $fail++;

        continue;
    }

    if (substr_count($next, 'now()->toDateString()') !== 0) {
        fwrite(STDERR, "REFUSE: $rel still asks the UTC date\n");
        $fail++;

        continue;
    }

    if ($apply) {
        file_put_contents($path, $next);
        echo "  wrote\n";
    } else {
        echo "  dry run\n";
    }
}

if ($fail > 0) {
    fwrite(STDERR, "$fail file(s) did not match — nothing written for them\n");

    exit(1);
}

echo $apply ? "applied\n" : "DRY RUN — pass --apply\n";
