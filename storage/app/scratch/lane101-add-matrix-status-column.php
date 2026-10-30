<?php

/**
 * Task #43: give the matrix's gap tables a Status column a program can read.
 *
 * `lane94-matrix-claims.php` judges a row by the cell that LEADS with a verdict verb. Twenty-two of the matrix's
 * twenty-eight gap rows do that somewhere; six (G-6, G-35, G-40, G-42, G-43, G-45) carry their verdict word in the
 * middle of a cell, so the instrument reports AMBIGUOUS and the register's own census — how many gaps are built —
 * is unreadable by anything but a human reader. The fix is a column, not a looser classifier: a checker widened to
 * find verdicts anywhere is lane 69's cancelled-row defect waiting to happen again.
 *
 * Every value below was read from its own row (its strike-through marker, its fix cell, its "Needs a ruling?" cell)
 * and cross-checked against the authority document's §28.3 verdict cell where one exists. The script refuses the
 * whole write if a row's cell count is not eight, if an id in the table is not in the map, or if a mapped id is
 * missing — a half-added column is the corrupt register this repository refuses elsewhere.
 *
 * Usage: php storage/app/scratch/lane101-add-matrix-status-column.php [--apply]
 */
$root = dirname(__DIR__, 3);
$file = $root.'/Documentation/Prompt-Requirement-Matrix-2026-10-26.md';
$apply = in_array('--apply', $argv, true);

$status = [
    'G1' => 'Closed 2026-10-26',
    'G2' => 'Closed 2026-10-26',
    'G3' => 'Closed 2026-10-26',
    'G4' => 'Closed 2026-10-26',
    'G5' => 'Closed 2026-10-27 (CSV half); the XLSX format waits on the school',
    'G6' => 'Built 2026-10-27 for the monitor and the definitions table is Not built, waiting on the owner',
    'G7' => 'Closed 2026-10-27',
    'G8' => 'Not built, waits on the school',
    'G9' => 'Not built, waits on the school',
    'G10' => 'Closed 2026-10-27 (registered as G-32); the §6.4 wording halves wait on the sheet',
    'G33' => 'Closed 2026-10-29; the fixed-list half waits on the sheet',
    'G34' => 'Closed 2026-10-29',
    'G35' => 'Not built, partly waiting on the school for the wording',
    'G36' => 'Not built',
    'G37' => 'Not built, waits on the school',
    'G38' => 'Not built, waits on the school',
    'G39' => 'Closed 2026-10-29',
    'G40' => 'Partly built: the mechanism exists and six of thirty-six tables use it',
    'G41' => 'Not built, waits on the owner',
    'G42' => 'Closed 2026-10-29 for the login half; the live-session half waits on the school',
    'G43' => 'Closed 2026-10-29',
    'G-44' => 'Not built, partly waiting on the school',
    'G-45' => 'Partly built: the deferral half closed 2026-10-29, the unique key waits on the school',
    'G-46' => 'Closed 2026-10-30 for eight of the nine cards; the blocking card waits on the school',
    'G-47' => 'Partly built: the ownership half closed 2026-10-30, the title and deep link are Not built',
    'G-48' => 'Closed 2026-10-29',
    'G-49' => 'Closed 2026-10-29; how far the gate reaches waits on the school',
    'G-50' => 'Not built',
];

$lines = file($file);

// Two rows carry raw pipes inside a code span, so the table renders them as ten cells and this pass would either
// append to a row whose shape it cannot read or half-add a column. The pipes are escaped first, and the escape is
// counted — a needle that matches nothing is this script's own age, not a defect that was fixed.
$escapes = [
    '`required_if:status,rejected|min:10|max:500`' => '`required_if:status,rejected\|min:10\|max:500`',
];
$joined = implode('', $lines);
foreach ($escapes as $from => $to) {
    $n = substr_count($joined, $from);
    if ($n < 1) {
        fwrite(STDERR, "REFUSED: the pipe-escape needle occurs {$n} time(s) — the row is not the one this pass reads:\n  {$from}\n");

        exit(1);
    }
    $joined = str_replace($from, $to, $joined);
    printf("escaped {$n} row(s) carrying raw pipes inside a code span\n");
}
$lines = explode("\n", $joined);
array_walk($lines, static function (string &$l): void {
    $l = rtrim($l, "\r\n");
});

$out = [];
$seen = [];
$inTable = false;
$errors = [];

foreach ($lines as $i => $line) {
    $t = rtrim($line, "\r\n");

    if (str_starts_with(trim($t), '| Id | Gap |')) {
        if (str_contains($t, '| Status |')) {
            $errors[] = 'line '.($i + 1).': the table already carries a Status column';

            continue;
        }
        $inTable = true;
        $out[] = $t.' Status |';

        continue;
    }

    if (! $inTable) {
        $out[] = $t;

        continue;
    }

    if (str_starts_with(trim($t), '|---')) {
        $out[] = $t.'---|';

        continue;
    }

    if (trim($t) === '' || ! preg_match('/^\|\s*(G-?\d+)\s*\|/', $t, $m)) {
        $inTable = false;
        $out[] = $t;

        continue;
    }

    $id = $m[1];
    // Split on UNESCAPED pipes: a validation rule inside a code span legitimately carries `\|`, and a counter that
    // splits on those too reports a well-formed row as malformed — which is how this pass first refused itself.
    $cells = preg_split('/(?<!\\\\)\|/', $t);
    // Leading empty piece (before the first pipe) and trailing empty piece (after the last pipe).
    if (trim((string) end($cells)) !== '') {
        $errors[] = 'line '.($i + 1).": the {$id} row does not end with a pipe";
        $out[] = $t;

        continue;
    }
    $inner = count($cells) - 2;
    if ($inner !== 8) {
        $errors[] = 'line '.($i + 1).": the {$id} row has {$inner} cells, want 8 — the table is not the shape this pass writes into";
        $out[] = $t;

        continue;
    }
    if (! isset($status[$id])) {
        $errors[] = 'line '.($i + 1).": no Status was authored for {$id}";
        $out[] = $t;

        continue;
    }
    $seen[$id] = true;
    $out[] = $t.' '.$status[$id].' |';
}

$missing = array_values(array_diff(array_keys($status), array_keys($seen)));
if ($missing !== []) {
    $errors[] = 'mapped ids that appear in no row: '.implode(', ', $missing);
}

if ($errors !== []) {
    fwrite(STDERR, "REFUSED:\n");
    foreach ($errors as $e) {
        fwrite(STDERR, '  '.$e."\n");
    }

    exit(1);
}

printf("%s: %d gap row(s) gain a Status column (%d closed, %d not built, %d partly)\n",
    $apply ? 'APPLY' : 'DRY RUN',
    count($seen),
    count(array_filter($status, static fn (string $s): bool => (bool) preg_match('/^Closed|^Built/i', $s))),
    count(array_filter($status, static fn (string $s): bool => (bool) preg_match('/^Not built/i', $s))),
    count(array_filter($status, static fn (string $s): bool => (bool) preg_match('/^Partly/i', $s)))
);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

file_put_contents($file, implode("\n", $out)."\n");
echo "APPLIED.\n";
