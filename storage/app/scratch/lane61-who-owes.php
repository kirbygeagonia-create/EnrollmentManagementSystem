<?php
/**
 * Lane 61 measurement (read-only): what does each register row's own "Who must act / Who must
 * decide" column say, and does it agree with the row's verdict?
 *
 * §28.8 counts six actors' owed items (4 / 19 / 13 / 3 / 4 / 3) and says it is "counted from the
 * 'Who must act' column of 28.2 and 28.3". This script prints that column verbatim for every row
 * so the count can be re-derived instead of remembered, and separates three states a reader needs
 * apart: the column names an actor, the column says it is closed, or the column does neither.
 *
 * Cell boundaries cannot be trusted — §28.3's G-26 splits into seven cells because
 * `required|numeric|min:0.01` carries two pipes inside a code span — so the actor is read by
 * looking for a cell that BEGINS with one of the document's own actor names, and the verdict by a
 * cell that begins with a verdict verb.
 *
 * Usage: php storage/app/scratch/lane61-who-owes.php [--flat=path] [--all]
 */

$flat = 'storage/app/scratch/docx-flat.txt';
$all = in_array('--all', $argv, true);
foreach ($argv as $a) {
    if (str_starts_with((string) $a, '--flat=')) {
        $flat = substr((string) $a, 7);
    }
}
if (! is_file($flat)) {
    exit("REFUSED: no dump at {$flat}. Run dump-docx-flat.php first.\n");
}
$lines = file($flat, FILE_IGNORE_NEW_LINES);

// The registers, located by their own header rows, not by a remembered line number. Each ends where the next
// §28 heading starts: a range that runs to the end of the file would swallow §28.4's situations and §28.6's
// implied-features rows, which carry ids of their own and answer to neither column.
$ranges = [];
foreach ($lines as $i => $l) {
    if (preg_match('/^ROW \d+: ID \| (?:The question only the institution can answer|The gap, stated once) \|/', $l)) {
        $ranges[] = $i + 1;
    }
}
$headings = [];
foreach ($lines as $i => $l) {
    if (preg_match('/^P \d+: 28\.\d\s/u', $l)) {
        $headings[] = $i + 1;
    }
}
if (count($ranges) !== 2) {
    printf("REFUSED: found %d register headers, expected 2 (§28.2 and §28.3).\n", count($ranges));
    exit(1);
}
$ends = [];
foreach ($ranges as $start) {
    $end = count($lines);
    foreach ($headings as $h) {
        if ($h > $start) {
            $end = $h - 1;
            break;
        }
    }
    $ends[] = $end;
}

// Derived, not remembered: the first run of this script left G-13 out because its column reads
// "Owner / whoever provisions the server", and "Owner" was not in a list written from memory.
$ACTORS = '/^(Front-end developer|Developer|Registrar|SysAdmin|Project owner|Department|School|College|Owner)\b/u';
$VERDICT = '/^(Closed|Answered|Ruled|Contained|Done|Corrected|Built|Partly closed|Deferred|None|Not applicable|No decision)/iu';

$rows = [];
foreach ($ranges as $h => $start) {
    $end = $ends[$h];
    for ($n = $start + 1; $n <= $end; $n++) {
        $l = $lines[$n - 1] ?? null;
        if ($l === null || ! preg_match('/^ROW \d+: ([A-Z]{1,3}-\d{1,2}) \|/', $l, $m)) {
            if ($l !== null && preg_match('/^ROW \d+: [A-Z]{1,3}-\d{1,2} \|/', $l)) {
                continue;
            }
            continue;
        }
        $cells = preg_split('/\s*\|\s*/', $l);
        $id = trim(preg_replace('/^ROW \d+:\s*/u', '', $cells[0]));
        // A row is only comparable when its own text carries an answer verb; reading a verb from
        // any cell would hang "Closed" on a row that merely cross-references a closed finding.
        $answered = preg_match('/\b(?:Closed|Ruled|Answered|Contained|Done|Corrected|Partly closed)\b/u', $cells[1] ?? '');
        $actor = null;
        $verdict = null;
        foreach (array_slice($cells, 1) as $cell) {
            if ($actor === null && preg_match($ACTORS, $cell)) {
                $actor = $cell;
            }
            if ($verdict === null && preg_match($VERDICT, $cell)) {
                $verdict = mb_substr($cell, 0, 60);
            }
        }
        $rows[$id] = ['line' => $n, 'actor' => $actor, 'verdict' => $verdict, 'answered' => $answered];
    }
}

if ($rows === []) {
    exit("REFUSED: not one register row parsed.\n");
}

$byActor = [];
$noActor = [];
$answeredCells = 0;
foreach ($rows as $id => $r) {
    $cells = preg_split('/\s*\|\s*/', (string) ($lines[$r['line'] - 1] ?? ''));
    if ($r['actor'] !== null) {
        $key = preg_split('/\s*(?:with|and|,|;)\s*/u', $r['actor'])[0];
        $byActor[$key][] = $id;
        continue;
    }
    $noActor[$id] = $r;
    // A row whose split yields other than five cells carries a pipe inside a code span, so which cell is the
    // actor column cannot be answered. Counted separately, never folded into the answer tally.
    if (count($cells) === 5 && $r['verdict'] !== null) {
        $answeredCells++;
    }
}

printf("register rows parsed: %d (§28.2 header at %d, §28.3 header at %d)\n", count($rows), $ranges[0], $ranges[1]);
printf("rows whose column still NAMES an actor: %d\n", array_sum(array_map('count', $byActor)));
printf("rows whose column states an answer:     %d\n", $answeredCells);
printf("rows whose column names none:           %d\n\n", count($noActor));

echo "--- the actor column verbatim ---\n";
ksort($byActor);
foreach ($byActor as $who => $ids) {
    sort($ids, SORT_NATURAL);
    printf("%-22s %2d  %s\n", $who, count($ids), implode(', ', $ids));
}

echo "\n--- rows naming no actor (first 60 chars of their verdict cell) ---\n";
ksort($noActor, SORT_NATURAL);
foreach ($noActor as $id => $r) {
    if (!$all && preg_match('/^(Closed|Answered|Ruled|Contained|Done|Corrected|Partly)/i', (string) $r['verdict'])) {
        continue;
    }
    printf("%-6s line %-5d %s\n", $id, $r['line'], $r['verdict'] ?? '(no verdict cell either)');
}

// The blind spot, printed rather than smoothed over. Three register rows carry a pipe INSIDE a code span —
// `required|numeric|min:0.01`, `rejected) and three signature pairs`, a backticked `0` — so splitting on the
// pipe gives them more cells than the table has columns and their actor cell cannot be read positionally.
echo "\n--- BLIND SPOTS: rows whose cell count is not five, so no column index is trustworthy ---\n";
$blind = 0;
foreach ($rows as $id => $r) {
    $line = $lines[$r['line'] - 1] ?? '';
    $cells = preg_split('/\s*\|\s*/', (string) $line);
    if (count($cells) === 5) {
        continue;
    }
    printf("%-6s line %-5d %d cells\n", $id, $r['line'], count($cells));
    $blind++;
}
printf("(%d rows — counted among the %d that name no actor, never judged)\n\n", $blind, count($noActor));

// The load-bearing question: does any row name an actor while its own text says it is answered?
echo "\n--- names an actor AND already states an answer ---\n";
$both = 0;
foreach ($byActor as $who => $ids) {
    foreach ($ids as $id) {
        $r = $rows[$id];
        if ($r['answered']) {
            printf("%-6s line %-5d actor [%s]  verdict cell [%s]\n", $id, $r['line'],
                mb_substr((string) $r['actor'], 0, 54), $r['verdict'] ?? '—');
            $both++;
        }
    }
}
printf("(%d rows)\n", $both);
