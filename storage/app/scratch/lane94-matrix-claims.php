<?php

/**
 * Read-only: does every "Built" claim in the requirement matrix name something that exists?
 *
 * The matrix is the artifact that answers "is the development prompt fully implemented?", and 40-odd of its rows say
 * Working or Built while naming tests, commands and classes. Nothing in the repository has ever asked whether those
 * named files are there. This is the invented-vocabulary family that §28's G-28 already caught once (a register
 * naming columns no table holds) and that lane 24.14 caught for a mechanism sentence — reached now from the
 * requirement side, where it costs the most: a row that cites a test file which does not exist is a green claim
 * about an unverified requirement.
 *
 * Buckets, and they must add up: ARTIFACT-OK (a path inside backticks that resolves), ARTIFACT-MISSING (it does
 * not), NO-ARTIFACT (a Built row naming no path at all — printed, not cleared), and NOT-BUILT (rows whose verdict
 * does not claim the thing works). A row whose verdict cell is ambiguous is AMBIGUOUS, and the run refuses when it
 * read no rows at all.
 *
 * Its population is the three matrix tables that carry an `Id` column; §1's Working table and this file's
 * §10 table are headed differently and are NOT read, so "21 gap rows" is a scope, not the register's size.
 * Seven rows came back AMBIGUOUS because the verdict word sits mid-cell — printed, not cleared.
 *
 * Usage: php storage/app/scratch/lane94-matrix-claims.php [--matrix=<path>] [--all]
 */

$root = dirname(__DIR__, 3);
$matrix = $root.'/Documentation/Prompt-Requirement-Matrix-2026-10-26.md';
$showAll = false;
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with($a, '--matrix=')) {
        $matrix = substr($a, 9);
    } elseif ($a === '--all') {
        $showAll = true;
    } else {
        fwrite(STDERR, "REFUSED: unknown flag {$a} — this checker reads --matrix= and --all only.\n");

        exit(1);
    }
}
if (! is_file($matrix)) {
    fwrite(STDERR, "REFUSED: no matrix at {$matrix} — a sweep over nothing prints a pass about nothing.\n");

    exit(1);
}

// One basename index for the whole run: resolving a cited path must not depend on the reader guessing directories.
$index = [];
foreach (['app', 'resources/js', 'database', 'tests', 'routes', 'storage/app/scratch', 'tools', 'Documentation', 'config'] as $dir) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $f) {
        if ($f->isFile()) {
            $index[$f->getFilename()][] = str_replace('\\', '/', $f->getPathname());
        }
    }
}

$text = str_replace("\r", '', (string) file_get_contents($matrix));
$lines = explode("\n", $text);

/** The cells of a markdown row, or null when the line is not one. */
$cells = function (string $line): ?array {
    $line = trim($line);
    if (! str_starts_with($line, '|') || ! str_ends_with($line, '|')) {
        return null;
    }

    return array_map('trim', explode('|', trim($line, '|')));
};

$rows = [];
$idColumn = null;
foreach ($lines as $i => $line) {
    $c = $cells($line);
    $sepLine = trim($lines[$i + 1] ?? '');
    if ($c !== null && preg_match('/^\|[-\s:|]+\|$/', $sepLine) === 1) {
        $idColumn = null;
        foreach ($c as $k => $h) {
            if (preg_match('/^(id|gap)\b/iu', trim($h, '*` '))) {
                $idColumn = $k;
                break; // 'Id' holds the id and comes first; 'Gap' holds the description
            }
        }

        continue;
    }
    if ($c === null || $idColumn === null || ! isset($c[$idColumn])) {
        continue;
    }
    if (preg_match('/^G-?(\d+)$/', $c[$idColumn], $m) !== 1) {
        continue;
    }
    $rows[] = ['id' => 'G-'.$m[1], 'row' => implode(' | ', $c), 'raw' => $line];
}

if ($rows === []) {
    fwrite(STDERR, "REFUSED: not one gap row was read, so this run can say nothing about the register.\n");

    exit(1);
}

$built = '/\b(built|working|verified|closed|delivered|shipped)\b/i';
$counts = ['ARTIFACT-OK' => 0, 'ARTIFACT-MISSING' => 0, 'NO-ARTIFACT' => 0, 'NOT-BUILT' => 0, 'AMBIGUOUS' => 0];
$out = [];
foreach ($rows as $r) {
    // The verdict is the cell that LEADS with a verdict verb (lane 61's rule): a gap's title can begin
    // "Built around …" without claiming anything, and a later cell can state the real status.
    $cellList = array_map('trim', explode('|', $r['row']));
    $verdictIn = null;
    foreach ($cellList as $cell) {
        // A struck-through claim is the register's own convention for "this used to be the state": the retired text is
        // struck and the verdict follows it, so the struck span is removed BEFORE asking whether the cell leads with a
        // verdict verb. The rule stays positional on purpose — searching the whole row would let a gap titled
        // "Open a second signature…" report itself as a verdict, which is the lane-61 defect this file keeps refusing.
        $unstruck = preg_replace('/^\s*~~.*?~~\s*/su', '', $cell);
        if (preg_match('/^(?:\s*(?:\*\*|~~))*\s*(built|working|verified|closed|delivered|shipped)\b/iu', $unstruck)) {
            $verdictIn = $cell;

            break;
        }
    }
    $claimsBuilt = $verdictIn !== null;
    if (! $claimsBuilt && preg_match($built, $r['row'])) {
        $counts['AMBIGUOUS']++;
        $out[] = ['AMBIGUOUS', $r['id'], 'the word appears mid-cell; no cell leads with a verdict verb'];

        continue;
    }
    if (! $claimsBuilt) {
        $counts['NOT-BUILT']++;

        continue;
    }
    // Artifacts are paths inside backticks. Prose names (a class, an artisan command, a lane number) are not files
    // and must not be accused of being missing.
    preg_match_all('/`([^`]+)`/u', $r['row'], $bm);
    $paths = [];
    foreach ($bm[1] as $b) {
        if (preg_match('/\.(php|jsx|js|mjs|json|md|sql|yaml|yml|blade\.php)$/i', $b) && ! str_contains($b, ' ')) {
            $paths[] = $b;
        }
    }
    if ($paths === []) {
        $counts['NO-ARTIFACT']++;
        $out[] = ['NO-ARTIFACT', $r['id'], 'a Built claim that names no file'];

        continue;
    }
    foreach ($paths as $p) {
        $p = str_replace('\\', '/', $p);
        $base = basename($p);
        $hit = is_file($root.'/'.$p);
        if (! $hit && isset($index[$base])) {
            $hit = true;
        }
        if ($hit) {
            $counts['ARTIFACT-OK']++;
            if ($showAll) {
                $out[] = ['ARTIFACT-OK', $r['id'], $p];
            }
        } else {
            $counts['ARTIFACT-MISSING']++;
            $out[] = ['ARTIFACT-MISSING', $r['id'], $p];
        }
    }
}

foreach ($out as [$v, $id, $detail]) {
    printf("  %-17s %-7s %s\n", $v, $id, $detail);
}
printf("\n%d gap row(s) read from %s\n", count($rows), $matrix);
printf("verdict buckets: NOT-BUILT %d, AMBIGUOUS %d, NO-ARTIFACT %d; artifact buckets: OK %d, MISSING %d\n",
    $counts['NOT-BUILT'], $counts['AMBIGUOUS'], $counts['NO-ARTIFACT'], $counts['ARTIFACT-OK'], $counts['ARTIFACT-MISSING']);
$distinct = array_values(array_unique(array_column($rows, 'id')));
printf("distinct gap ids: %d of %d rows%s\n", count($distinct), count($rows),
    count($distinct) === count($rows) ? ' (each id occurs once)' : ' — REPEATED ID, the register holds one finding twice');
if ($counts['ARTIFACT-MISSING'] > 0) {
    fwrite(STDERR, "\nREFUSED: a Built claim names a file the repository does not hold.\n");

    exit(1);
}
