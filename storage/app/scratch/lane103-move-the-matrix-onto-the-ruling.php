<?php

/**
 * Moves the requirement matrix's ENUM sentences onto the 2026-10-31 ruling.
 *
 * Spans are CUT from the file between two short anchors rather than retyped: the matrix mixes typographic and
 * straight apostrophes depending on which pass wrote each clause, and a hand-typed long needle is a needle that
 * quietly matches nothing. Each span must occur exactly once, and the span's replacement must not already be present,
 * so a re-run refuses instead of applying twice.
 *
 * Usage: php storage/app/scratch/lane103-move-the-matrix-onto-the-ruling.php [--apply]
 */

$root = dirname(__DIR__, 3);
$file = $root.'/Documentation/Prompt-Requirement-Matrix-2026-10-26.md';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

$src = (string) file_get_contents($file);

$cut = static function (string $src, string $start, string $end): string {
    if (substr_count($src, $start) !== 1) {
        fwrite(STDERR, "REFUSED: the start anchor [{$start}] occurs ".substr_count($src, $start)." time(s), want 1.\n");

        exit(1);
    }
    $s = strpos($src, $start);
    $e = strpos($src, $end, $s);
    if ($e === false) {
        fwrite(STDERR, "REFUSED: the end anchor [{$end}] was not found after its start.\n");

        exit(1);
    }

    return substr($src, $s, $e + strlen($end) - $s);
};

$jobs = [
    [
        $cut($src, 'Student Type and Standing are ENUM columns', 'has never been asked.'),
        'Student Type and Standing are ENUM columns and now carry headers: ruled 2026-10-31, the school’s server '
        .'orders an ENUM by the declaration order the school wrote into the migration, which is the order a desk '
        .'wants, and the consequence falls on the test — the expected sequence is read from the connection by '
        .'`tests/Support/EnumColumnOrder.php` rather than typed. The ID desk adds a column withheld for a different '
        .'reason: its ID Status paints the status of that row’s FIRST request, so ordering it would decide what two '
        .'requests on one enrollment amount to, which the school has never been asked.',
        'now carry headers: ruled 2026-10-31',
    ],
    [
        $cut($src, 'is the first screen that withholds a header because of a column', 'asserts the offered set rather than trusting that comment'),
        'was built under a rule the owner reversed the next day: `enrollments.studentType` and '
        .'`enrollments.academicStanding` are both ENUM and both already on the row the desk reads, and the ruling is '
        .'that a desk MAY sort an ENUM, because on the school’s server the order is the declaration order the school '
        .'itself wrote — `firstYear, continuing, transferee, shifter` — not an accident of the driver. What moved is '
        .'the expectation: the suite compiles the same `enum()` into a varchar with a CHECK and sorts the stored word, '
        .'so the sequence is read from the connection by one shared helper, `tests/Support/EnumColumnOrder.php`, which '
        .'refuses a column that names no enum on the mysql driver rather than guessing. Clinic Status stays out for a '
        .'reason the ruling does not touch — it is painted from whether a record exists on the row, not stored in it — '
        .'and the screen now offers a header for every column its query can answer. ',
        'was built under a rule the owner reversed the next day',
    ],
];

$out = $src;
foreach ($jobs as $i => [$from, $to, $guard]) {
    if (substr_count($out, $from) !== 1) {
        fwrite(STDERR, "REFUSED: edit {$i}'s cut span occurs ".substr_count($out, $from)." time(s), want 1.\n");

        exit(1);
    }
    if (substr_count($out, $guard) !== 0) {
        fwrite(STDERR, "REFUSED: edit {$i}'s new fragment [{$guard}] is already present — this pass has run.\n");

        exit(1);
    }
    $out = str_replace($from, $to, $out);
}

printf("%s: %d span(s) rewritten in the matrix\n", $apply ? 'APPLY' : 'DRY RUN', count($jobs));
if (! $apply) {
    echo "DRY RUN — nothing written.\n";

    exit(0);
}

file_put_contents($file, $out);

$after = (string) file_get_contents($file);
foreach (['now carry headers: ruled 2026-10-31', 'was built under a rule the owner reversed the next day',
    'tests/Support/EnumColumnOrder.php'] as $want) {
    if (substr_count($after, $want) < 1) {
        fwrite(STDERR, "REFUSED after write: [{$want}] is missing.\n");

        exit(1);
    }
}
foreach (['are ENUM columns, and one header over an ENUM answers a different sequence',
    'is the first screen that withholds a header because of a column'] as $gone) {
    if (substr_count($after, $gone) !== 0) {
        fwrite(STDERR, "REFUSED after write: the retired claim [".mb_substr($gone, 0, 40)."…] is still present.\n");

        exit(1);
    }
}

echo "written; the matrix states the ruling, and the withheld set is the painted columns only.\n";
