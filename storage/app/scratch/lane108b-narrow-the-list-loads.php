<?php

/**
 * Lane 108b: narrow the desk LIST queues' whole-student eager loads, rewriting each literal AT ITS BYTE OFFSET.
 *
 * G-52's remaining queue sites, taken from `lane108-census-whole-student-loads.php --json=…` rather than from a
 * needle list. The reason is this file's own rule (lane 69): a short needle like `'student'` is a substring of
 * dozens of other things — `'student.aliases'`, `'enrollment.student'`, a `whereHas('student')`, a validation key —
 * so a needle list corrupts neighbours while reporting success. Offsets come from the same reader that classified
 * the site, which is the only way "which sites are these" has one definition.
 *
 * Safety, in order:
 *   1. every column named is checked against the `students` migrations (one guard module, shared with the other
 *      lane108 script) — a list naming a column the table lacks is a 500 at the counter, and the sort classes do
 *      not look at response status;
 *   2. a row whose page paints the name SUFFIX is REFUSED outright, because the five-column identity set would
 *      silently drop "Jr." — the census reads the page and its whole import graph for this;
 *   3. the bytes at each offset must be exactly the quoted relation path, or nothing is written;
 *   4. each touched file's line count is asserted identical, so no citation into these handlers ages;
 *   5. the write is applied from the highest offset down, so earlier offsets stay true.
 *
 * Usage: php storage/app/scratch/lane108b-narrow-the-list-loads.php --census=<json> [--kind=LIST] [--apply]
 */
$root = dirname(__DIR__, 3);
require __DIR__.'/students-column-guard.php';

$apply = in_array('--apply', $argv, true);
$censusPath = null;
$kind = 'LIST';

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--apply') {
        continue;
    }
    if (str_starts_with($arg, '--census=')) {
        $censusPath = substr($arg, strlen('--census='));

        continue;
    }
    if (str_starts_with($arg, '--kind=')) {
        $kind = substr($arg, strlen('--kind='));

        continue;
    }
    fwrite(STDERR, "REFUSED: unknown argument {$arg}; this script reads --census=, --kind= and --apply.\n");

    exit(1);
}

if ($censusPath === null) {
    fwrite(STDERR, "REFUSED: --census=<json> is required. This script will not decide on its own which site is a"
        ." queue — that is the census's job, and a second definition of it is the defect this file exists to avoid.\n");

    exit(1);
}
$censusFile = str_starts_with($censusPath, '/') ? $censusPath : $root.'/'.$censusPath;
if (! is_file($censusFile)) {
    fwrite(STDERR, "REFUSED: no census at {$censusFile} — run the census with --json= first.\n");

    exit(1);
}
$rows = json_decode((string) file_get_contents($censusFile), true);
if (! is_array($rows) || $rows === []) {
    fwrite(STDERR, "REFUSED: the census file did not read as a non-empty array of rows.\n");

    exit(1);
}

$cols = 'studentId,schoolIdNumber,lastName,firstName,middleName';
$unknown = students_unknown_columns($root, $cols);
if ($unknown !== []) {
    fwrite(STDERR, 'REFUSED: '.implode(', ', $unknown).' is/are not columns the students migrations declare.'."\n");

    exit(1);
}

$targets = array_values(array_filter($rows, static fn (array $r): bool => ($r['kind'] ?? '') === $kind));
if ($targets === []) {
    echo "NOTHING TO DO: the census reports no {$kind} site still loading a whole student."
        ." If this ran after a successful apply, that is the expected answer.\n";

    exit(0);
}

$failures = [];
$edits = [];
foreach ($targets as $t) {
    if (! empty($t['readsSuffix'])) {
        $failures[] = sprintf('%s %s() paints the name suffix, so the five-column list would drop "Jr." — widen'
            .' the identity set deliberately, not by accident', $t['file'], $t['action']);

        continue;
    }
    $path = $root.'/'.ltrim($t['file'], '/');
    if (! is_file($path)) {
        $failures[] = 'no file '.$t['file'];

        continue;
    }
    $bytes = (string) file_get_contents($path);
    foreach ($t['literals'] as $lit) {
        $expect = "'".$lit['path']."'";
        $seen = substr($bytes, (int) $lit['at'], strlen($expect));
        if ($seen !== $expect) {
            $failures[] = sprintf('%s @%d: expected %s, found %s — the tree moved since the census was written;'
                .' re-run the census, do not re-key by hand',
                $t['file'], (int) $lit['at'], $expect, var_export($seen, true));

            continue;
        }
        $edits[] = ['file' => $t['file'], 'at' => (int) $lit['at'], 'from' => $expect,
            'to' => "'".$lit['path'].':'.$cols."'"];
    }
}

if ($failures !== []) {
    fwrite(STDERR, "REFUSED — nothing written:\n".implode("\n", array_unique($failures))."\n");

    exit(1);
}

usort($edits, static fn ($a, $b) => $b['at'] <=> $a['at']);
printf("%d literal(s) over %d file(s)%s", count($edits), count(array_unique(array_column($edits, 'file'))), PHP_EOL);
foreach ($edits as $e) {
    printf("  %-52s @%-6d %s -> %s\n", basename($e['file']), $e['at'], $e['from'], 'student:<5 identity columns>');
}
if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$byFile = [];
foreach ($edits as $e) {
    $byFile[$e['file']][] = $e;
}
foreach ($byFile as $file => $list) {
    $path = $root.'/'.ltrim($file, '/');
    $bytes = (string) file_get_contents($path);
    $lines = substr_count($bytes, "\n");
    foreach ($list as $e) {
        $bytes = substr_replace($bytes, $e['to'], $e['at'], strlen($e['from']));
    }
    if (substr_count($bytes, "\n") !== $lines) {
        fwrite(STDERR, "REFUSED: {$file} would change its line count; citations would age.\n");

        exit(1);
    }
    file_put_contents($path, $bytes);
}

$bad = 0;
foreach ($byFile as $file => $list) {
    $after = (string) file_get_contents($root.'/'.ltrim($file, '/'));
    foreach ($list as $e) {
        $delta = strlen($e['to']) - strlen($e['from']);
        // Edits are applied highest-offset-first, so the literal this one wrote has since been pushed right by every
        // edit BELOW it in the same file — each of which added `$delta` bytes ahead of it. Reading the planned offset
        // back is therefore wrong by exactly that shift, and the first cut of this check reported three FAILs on a
        // write the diff shows to be correct. A verifier that fails on a good write is as costly as one that passes
        // on a bad one: it teaches the next reader to distrust the read-back.
        $shift = 0;
        foreach ($list as $other) {
            if ($other['at'] < $e['at']) {
                $shift += strlen($other['to']) - strlen($other['from']);
            }
        }
        $at = $e['at'] + $shift;
        if (substr($after, $at, strlen($e['to'])) !== $e['to']) {
            printf("  FAIL %s planned @%d, now expected @%d — did not read back\n", $file, $e['at'], $at);
            $bad++;
        }
    }
}
if ($bad > 0) {
    fwrite(STDERR, "REFUSED: {$bad} read-back(s) failed. The files above are written; re-run the census and read it.\n");

    exit(1);
}
echo 'written, re-read. Re-run the census with --json= to see the LIST queue fall.'."\n";
