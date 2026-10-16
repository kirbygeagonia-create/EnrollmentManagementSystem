<?php

/**
 * Lane 39 — what does `Documentation/ems-demo-backup.sql` actually put back?
 *
 * Demo-Day-Guide §5 promises "All four students, all workflow states, and all 11 accounts return exactly as
 * documented above", and Offline-Presentation-Guide's restore section repeats the promise with a different
 * backup date. Both are claims about a file, so measure the file: mysqldump writes each table's rows as ONE
 * line prefixed by its column list, so the tuple count and any single column are readable from that line.
 *
 * Usage: php storage/app/scratch/lane39-dump-contents.php [path-to-dump]
 */

$dump = $argv[1] ?? 'Documentation/ems-demo-backup.sql';
$lines = file($dump);
if ($lines === false) {
    fwrite(STDERR, "Cannot read {$dump}\n");
    exit(1);
}

/** Splits a mysqldump VALUES tail into tuples, honouring quoted strings that contain ')' or ','. */
function dumpTuples(string $tail): array
{
    $rows = [];
    $depth = 0;
    $inString = false;
    $current = '';
    $len = strlen($tail);
    for ($i = 0; $i < $len; $i++) {
        $ch = $tail[$i];
        if ($inString) {
            $current .= $ch;
            if ($ch === '\\') {
                $current .= $tail[++$i] ?? '';
                continue;
            }
            if ($ch === "'") {
                $inString = false;
            }
            continue;
        }
        if ($ch === "'") {
            $inString = true;
            $current .= $ch;
            continue;
        }
        if ($ch === '(') {
            $depth++;
            if ($depth === 1) {
                $current = '';
                continue;
            }
        }
        if ($ch === ')') {
            $depth--;
            if ($depth === 0) {
                $rows[] = $current;
                $current = '';
                continue;
            }
        }
        if ($ch === ',' && $depth === 0) {
            continue;
        }
        if ($depth > 0) {
            $current .= $ch;
        }
    }

    return $rows;
}

/** Splits one tuple into fields, honouring quoted strings containing commas. */
function dumpFields(string $tuple): array
{
    $fields = [];
    $current = '';
    $inString = false;
    $len = strlen($tuple);
    for ($i = 0; $i < $len; $i++) {
        $ch = $tuple[$i];
        if ($inString) {
            if ($ch === '\\') {
                $current .= $ch.($tuple[++$i] ?? '');
                continue;
            }
            $current .= $ch;
            if ($ch === "'") {
                $inString = false;
            }
            continue;
        }
        if ($ch === "'") {
            $inString = true;
            $current .= $ch;
            continue;
        }
        if ($ch === ',') {
            $fields[] = trim($current);
            $current = '';
            continue;
        }
        $current .= $ch;
    }
    $fields[] = trim($current);

    return $fields;
}

$targets = [
    'students' => ['schoolIdNumber'],
    'staffusers' => ['username', 'officeId'],
    'enrollments' => ['enrollmentId', 'studentId', 'termId', 'enrollmentStatus'],
    'idrequests' => ['idRequestId', 'enrollmentId', 'status', 'cardPhotoPath'],
    'payments' => ['orNumber', 'paymentDate'],
    'examresults' => ['examStage'],
    'clearanceapprovals' => ['status'],
];

$counts = [];
$columns = [];
foreach ($lines as $line) {
    if (! preg_match('/^INSERT INTO `([a-zA-Z_]+)` \(([^)]*)\) VALUES (.*)$/', $line, $m)) {
        continue;
    }
    $table = $m[1];
    if (! isset($targets[$table])) {
        continue;
    }
    $colNames = array_map(fn (string $c): string => trim($c, '` '), explode(',', $m[2]));
    $rows = dumpTuples($m[3]);
    $counts[$table] = ($counts[$table] ?? 0) + count($rows);
    foreach ($targets[$table] as $col) {
        $idx = array_search($col, $colNames, true);
        if ($idx === false) {
            $columns[$table][$col] = ['MISSING COLUMN'];
            continue;
        }
        foreach ($rows as $row) {
            $fields = dumpFields($row);
            $columns[$table][$col][] = $fields[$idx] ?? null;
        }
    }
}

ksort($counts);
echo "Rows the dump writes per table (mysqldump puts them on one line each):\n";
foreach ($counts as $table => $count) {
    printf("  %-20s %d\n", $table, $count);
}

$termHistogram = array_count_values($columns['enrollments']['termId'] ?? []);
ksort($termHistogram);
echo "\n  enrollments by termId: ".json_encode($termHistogram)."\n";

$usernames = $columns['staffusers']['username'] ?? [];
echo '  staff accounts: '.count($usernames).' — '.implode(', ', array_slice(array_map(fn ($u) => trim((string) $u, "'"), $usernames), 0, 30))."\n";

$schoolIds = array_map(fn ($s) => trim((string) $s, "'"), $columns['students']['schoolIdNumber'] ?? []);
$demo = array_values(array_filter($schoolIds, fn (string $s): bool => str_starts_with($s, 'DEMO-')));
echo '  students: '.count($schoolIds).' total, '.count($demo).' with a DEMO- school id: '.implode(', ', $demo)."\n";

$ors = array_map(fn ($o) => trim((string) $o, "'"), $columns['payments']['orNumber'] ?? []);
echo '  receipts: '.count($ors).' — DEMO-OR-0001 present: '.var_export(in_array('DEMO-OR-0001', $ors, true), true)
    .', DEMO-OR-TODAY-01 present: '.var_export(in_array('DEMO-OR-TODAY-01', $ors, true), true)."\n";

if (isset($columns['idrequests']['idRequestId'])) {
    $ids = $columns['idrequests']['idRequestId'];
    $stat = $columns['idrequests']['status'];
    $photo = $columns['idrequests']['cardPhotoPath'];
    $enr = $columns['idrequests']['enrollmentId'];
    $validated = 0;
    $validatedWithPhoto = 0;
    foreach ($ids as $i => $id) {
        if (trim((string) $stat[$i], "'") !== 'validated') {
            continue;
        }
        $validated++;
        if ($photo[$i] !== 'NULL') {
            $validatedWithPhoto++;
        }
        printf(
            "  idrequest %s -> enrollment %s, status %s, photo %s\n",
            $id, $enr[$i], $stat[$i], $photo[$i] === 'NULL' ? 'NULL' : trim((string) $photo[$i], "'")
        );
    }
    echo "  idrequests: ".count($ids)." rows, {$validated} validated, {$validatedWithPhoto} validated WITH a photo\n";
}

printf(
    "\n  Guide §5 claims \"all four students … and all 11 accounts\": the dump restores %d students / %d staff accounts.\n",
    count($schoolIds),
    count($usernames)
);
