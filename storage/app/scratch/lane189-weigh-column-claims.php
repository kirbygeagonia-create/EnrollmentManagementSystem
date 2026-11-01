<?php

/**
 * Lane 189 — weigh §24's column claims against each other, not only against the server.
 *
 * Three readings of one fact, and the point is the pair no other checker asks:
 *   (a) each per-table heading `X — N columns`,
 *   (b) the number of column rows physically listed under that heading,
 *   (c) what `information_schema` answers for that table.
 * `lane60-ratio-claims.php` binds (a) to (c), so a heading that matches the server reads clean even when the table
 * beneath it lists fewer columns — exactly what this found on 2026-11-01: `students`, `admissions`, `notifications`
 * and `enrollments` each carried the heading the batch that added a column had re-taken, and none gained the row,
 * because nothing compared a heading with the rows under it. §24's own census row promises "a column listed here
 * exists, and a column the server holds is not omitted", so (b) ≠ (c) is a claim the document contradicts.
 *
 * A table's span ends at its own `Referenced by` / `Change since` note, at the next table heading, or at the next
 * numbered section heading. Without that last bound any `name | value` row further in the document (the key register,
 * the §25 matrix) is counted into whichever table was still open, and an inflated census is worse than an under-read
 * one because it prints agreement.
 *
 * Refusals, each its own message and exit 1: a missing dump, a dump older than the docx, an unknown flag, an empty
 * population, and a schema that answers no tables. Usage:
 *   php storage/app/scratch/lane189-weigh-column-claims.php [--dump=<file>] [--schema=ems]
 */
$root = dirname(__DIR__, 3);
chdir($root);

$dumpPath = 'storage/app/scratch/docx-flat.txt';
$schema = 'ems';
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with($a, '--dump=')) {
        $dumpPath = substr($a, 7);
    } elseif (str_starts_with($a, '--schema=')) {
        $schema = substr($a, 9);
    } else {
        fwrite(STDERR, "REFUSED: flag not read by this tool: {$a}\n");
        exit(1);
    }
}
if (! str_starts_with($dumpPath, '/') && ! preg_match('/^[A-Za-z]:[\\\\\/]/', $dumpPath)) {
    $dumpPath = $root.'/'.$dumpPath;
}
if (! is_file($dumpPath)) {
    fwrite(STDERR, "REFUSED: no flat dump at {$dumpPath} — run dump-docx-flat.php first.\n");
    exit(1);
}
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
if (is_file($docx) && filemtime($dumpPath) < filemtime($docx)) {
    fwrite(STDERR, "REFUSED: the dump predates the docx, so any verdict here is a reading of a document that no longer exists.\n");
    exit(1);
}

$lines = file($dumpPath);
if ($lines === false || $lines === []) {
    fwrite(STDERR, "REFUSED: {$dumpPath} read no lines.\n");
    exit(1);
}

$headSum = 0;
$heads = 0;
$rows = 0;
$perTable = [];
$current = null;
foreach ($lines as $i => $raw) {
    $s = trim((string) $raw);
    if ($s === '') {
        continue;
    }
    if (preg_match('/^P \d+: ([a-z0-9_]+) — (\d+) columns$/ui', $s, $m)) {
        $heads++;
        $headSum += (int) $m[2];
        $current = $m[1];
        $perTable[$current] = ['heading' => (int) $m[2], 'rows' => 0, 'names' => [], 'line' => $i + 1];
        continue;
    }
    // A new numbered section closes whatever table was open, so a later `x | y` row cannot join this census.
    if (preg_match('/^P \d+: \d+\.\d+/u', $s)) {
        $current = null;
        continue;
    }
    if (preg_match('/^P \d+: (Referenced by|Change since)/ui', $s)) {
        // The per-table block ends at its own notes; the Role / Correction / Model paragraphs above the table
        // must not end it before it starts.
        $current = null;
        continue;
    }
    if ($current === null) {
        continue;
    }
    if (preg_match('/^ROW \d+: Column \| Type/ui', $s)) {
        continue;
    }
    if (preg_match('/^ROW \d+: ([A-Za-z0-9_]+) \| /u', $s, $m)) {
        $perTable[$current]['rows']++;
        $perTable[$current]['names'][] = $m[1];
        $rows++;
    }
}

if ($heads === 0 || $rows === 0) {
    fwrite(STDERR, "REFUSED: read {$heads} heading(s) and {$rows} column row(s) from {$dumpPath}. A census over nothing\n"
        ."          is not a clean verdict — check the dump's shape before believing a zero.\n");
    exit(1);
}

echo 'headings read: '.$heads.', their sum: '.$headSum.', column rows listed: '.$rows."\n";
// The framework eight are the tables the document files under §24.13 rather than the domain list.
$framework = ['migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens'];
$domainSum = 0;
$frameworkSum = 0;
foreach ($perTable as $t => $v) {
    if (in_array($t, $framework, true)) {
        $frameworkSum += $v['heading'];
    } else {
        $domainSum += $v['heading'];
    }
}
echo 'heading sum for domain tables: '.$domainSum.', for the framework eight: '.$frameworkSum.'; '.count($perTable)." tables read\n";

$env = [];
foreach ((file($root.'/.env') ?: []) as $line) {
    if (preg_match('/^([A-Z_]+)=(.*)$/', trim($line), $m)) {
        $env[$m[1]] = trim($m[2], " \t\"'");
    }
}
$mysqli = new mysqli('localhost', $env['DB_USERNAME'] ?? 'root', $env['DB_PASSWORD'] ?? '', $schema);
if ($mysqli->connect_error) {
    fwrite(STDERR, "REFUSED: {$schema} answered nothing (".$mysqli->connect_error."), and the third reading is the server's.\n");
    exit(1);
}
$safe = $mysqli->real_escape_string($schema);
$serverCols = [];
$res = $mysqli->query("select TABLE_NAME, COLUMN_NAME from information_schema.COLUMNS where TABLE_SCHEMA='{$safe}' "
    .'order by TABLE_NAME, ORDINAL_POSITION');
if ($res === false) {
    fwrite(STDERR, "REFUSED: the column census failed: ".$mysqli->error."\n");
    exit(1);
}
while ($r = $res?->fetch_assoc()) {
    $serverCols[strtolower($r['TABLE_NAME'])][] = $r['COLUMN_NAME'];
}
if ($serverCols === []) {
    fwrite(STDERR, "REFUSED: information_schema answered no tables for {$schema}; refusing to weigh a document against silence.\n");
    exit(1);
}

$problems = [];
foreach ($perTable as $t => $v) {
    if (! isset($serverCols[$t])) {
        $problems[] = sprintf('UNBOUND        %-28s line %-6s the document heads a table %s does not hold', $t, $v['line'], $schema);
        continue;
    }
    $server = count($serverCols[$t]);
    if ($v['heading'] !== $server) {
        $problems[] = sprintf('HEADING-SERVER %-28s line %-6s heading says %d, %s holds %d', $t, $v['line'], $v['heading'], $schema, $server);
    }
    if ($v['rows'] !== $server) {
        $omitted = array_diff($serverCols[$t], $v['names']);
        $extra = array_diff($v['names'], $serverCols[$t]);
        $problems[] = sprintf('HEADING-ROWS   %-28s line %-6s heading %d, rows listed %d, server %d — omitted by the list: %s — listed but not on the server: %s',
            $t, $v['line'], $v['heading'], $v['rows'], $server,
            implode(', ', $omitted) ?: '(none)', implode(', ', $extra) ?: '(none)');
    }
}

$diff = [];
$short = [];
foreach ($perTable as $t => $v) {
    if ($v['heading'] !== $v['rows']) {
        $diff[] = $t.' heading '.$v['heading'].' vs rows '.$v['rows'].' (dump line '.$v['line'].')';
        $short[] = $t;
    }
}
echo 'tables whose heading disagrees with its own row count: '.count($diff)."\n";
foreach ($diff as $d) {
    echo '  '.$d."\n";
}
foreach ($problems as $p) {
    echo '  '.$p."\n";
}

if ($problems !== []) {
    printf("\n  %d problem(s). §24 promises a listed column exists and an omitted one does not, so a heading, its own\n"
        ."          rows and the server are one claim and must agree three ways.\n", count($problems));
    exit(1);
}
echo "  every §24 heading equals its own server count and the rows listed beneath it (".count($perTable)." tables).\n";
