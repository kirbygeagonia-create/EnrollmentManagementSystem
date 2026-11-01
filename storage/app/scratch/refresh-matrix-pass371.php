<?php

/**
 * Pass 371 — the two prose registers follow the eighteenth wired table, and one of them had fallen four behind.
 *
 * The matrix's G40 row carries its verdict in a Status column (task #43 added it, and lane94 reads that cell), and
 * that cell still said "fourteen of thirty-six tables use it" while the same row's own prose had been re-taken three
 * times since and reached seventeen. That is lane 64's membership defect and lane 61's "a ruling word must reach the
 * document's own tally" met from the column side: every batch moved the sentence and none moved the cell, because no
 * instrument compares a cell's number with the sentence beside it. This pass writes eighteen in both and asserts,
 * after the write, that no retired figure survives anywhere in the row.
 *
 * Every claim is counted before it is written, a failure restores each file from its own backup, and a re-run says
 * ALREADY APPLIED instead of refusing on a needle it consumed.
 *
 * Usage: php storage/app/scratch/refresh-matrix-pass371.php [--apply]
 */
$root = dirname(__DIR__, 3);
chdir($root);
$apply = in_array('--apply', $argv, true);

$matrix = 'Documentation/Prompt-Requirement-Matrix-2026-10-26.md';

$jobs = [
    $matrix => [
        ['seventeen screens use it and the other nineteen', 'eighteen screens use it and the other eighteen', 1],
        ['Seventeen of the 36 screens', 'Eighteen of the 36 screens', 1],
        ['the staff directory, the courses catalogue and the majors list; the other 19 pages',
            'the staff directory, the courses catalogue, the majors list and the grade-scale bands; the other 18 pages', 1],
        ['That 19 counts', 'That 18 counts', 1],
        ['seventeen of the 36 pages that render the shared table use it',
            'eighteen of the 36 pages that render the shared table use it', 1],
        ['19 of the 36 pages using the shared table still offer no order',
            '18 of the 36 pages using the shared table still offer no order', 1],
        ['the seventeen wired pages call', 'the eighteen wired pages call', 1],
        // The cell that had stopped being re-taken.
        ['fourteen of thirty-six tables use it', 'eighteen of thirty-six tables use it', 1],
    ],
    'AGENTS.md' => [
        ['still owed: 19 of the 36 pages', 'still owed: 18 of the 36 pages', 1],
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
        $bak = $root.'/storage/app/scratch/lane140-'.basename($rel).'.bak';
        copy($path, $bak);
        $backups[$path] = $bak;
        $written = @file_put_contents($path, $text);
        if ($written !== strlen($text)) {
            printf("  BAD  the write of %s returned %s\n", basename($rel), var_export($written, true));
            $fail++;
        }
    }
}

if (! $apply) {
    echo "\nDRY RUN — nothing written. Re-run with --apply.\n";

    exit($fail === 0 ? 0 : 1);
}

if ($fail > 0) {
    foreach ($backups as $path => $bak) {
        copy($bak, $path);
    }
    fwrite(STDERR, "REFUSED: {$fail} claim(s) did not match; every file restored from its own backup.\n");

    exit(1);
}

// Read back. The row is one line, so the retired figures are searched inside that line alone — a `nineteen`
// elsewhere in the matrix belongs to another claim entirely and must not make this pass refuse.
foreach ($jobs as $rel => $edits) {
    $text = (string) file_get_contents($root.'/'.$rel);
    if ($rel !== $matrix) {
        continue;
    }
    $lines = explode("\n", $text);
    $row = '';
    foreach ($lines as $line) {
        if (str_starts_with($line, '| G40 |')) {
            $row = $line;

            break;
        }
    }
    if ($row === '') {
        fwrite(STDERR, "REFUSED: the G40 row is not in the matrix any more — the table lost a row.\n");

        exit(1);
    }
    // One row, one claim: if the row's own byte length changed in a way that suggests a wrap, the walk below would
    // silently shrink the table (task #43's lesson).
    if (! str_contains($row, 'eighteen of thirty-six tables use it')) {
        fwrite(STDERR, "REFUSED: the Status cell of the G40 row does not carry the new figure.\n");

        exit(1);
    }
    foreach (['fourteen of thirty-six', 'seventeen of the 36', 'the seventeen wired', '19 of the 36',
        'Seventeen of the 36', 'the other nineteen', 'That 19 counts'] as $retired) {
        if (str_contains($row, $retired)) {
            fwrite(STDERR, "REFUSED: the retired claim [{$retired}] still occurs in the G40 row.\n");

            exit(1);
        }
    }
    $eighteens = substr_count($row, 'eighteen');
    printf("  ok   the G40 row carries %d spelling(s) of eighteen and none of the retired figures\n", $eighteens);
}

echo "written and read back: eighteen wired, eighteen owed, and the Status cell now agrees with its own sentence.\n";
