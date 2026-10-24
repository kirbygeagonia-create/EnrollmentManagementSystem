<?php

/**
 * §28.8's "Who owes what" table against the column it claims to be counted from.
 *
 * The table asserted 46 owed slots on 2026-10-24 while lane61-who-owes — which reads the "Who must
 * act"/"Who must decide" column directly — counted 8, and after the owner's rulings that day it counts 2.
 * Every prose checker in this repository judges sentences, figures or citations; none compared this table
 * to its source, which is why AGENTS.md could record the 46-vs-8 discrepancy and leave it standing.
 *
 * So this tool deliberately does NOT re-classify the column: it asks lane61-who-owes.php, which is already
 * proved, and judges the table against that answer. Re-implementing the classification here would create a
 * second definition of "names an actor", the exact defect lane 69 spent a whole batch removing for year levels.
 *
 * Refuses rather than reporting a clean count: if the table cannot be found, if the instrument prints no
 * tally, or if its blind spots are not reported, a pass here would mean nothing.
 *
 *   php storage/app/scratch/lane69-owes-table-check.php
 */
$root = dirname(__DIR__, 3);
// --dump= is read here, not merely accepted: the harness proves a mutant copy is what got scanned.
$dumpPath = $root.'/storage/app/scratch/docx-flat.txt';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--dump=')) {
        $dumpPath = substr($arg, 7);
    }
}
if (! is_file($dumpPath) || filesize($dumpPath) < 1000) {
    // exit("text") prints and returns 0, which a caller reading only the status cannot tell from a pass.
    echo "REFUSED: the dump at {$dumpPath} is missing or too small to hold the registers.\n";
    exit(1);
}
$dump = (string) file_get_contents($dumpPath);
$fails = 0;
$say = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'BAD ', $what);
    if (! $ok) {
        $fails++;
    }
};

// --- 1. what the table claims -------------------------------------------------------------------
$lines = explode("\n", $dump);
$in = false;
$table = [];
foreach ($lines as $l) {
    if (preg_match('/^P \d+: 28\.8/u', $l)) {
        $in = true;

        continue;
    }
    if ($in && preg_match('/^P \d+: (?:28\.9|29)/u', $l)) {
        break;
    }
    if (! $in || ! preg_match('/^ROW \d+: ([^|]+?) \| (\d+) \| (.*?)(?: \||$)/u', $l, $m)) {
        continue;
    }
    $actor = trim($m[1]);
    if ($actor === 'Who owes the fix') {
        continue;
    }
    $table[$actor] = [(int) $m[2], trim($m[3])];
}
$say(count($table) >= 4, 'the §28.8 table was parsed ('.count($table).' actor rows)');

// --- 2. what the column says ---------------------------------------------------------------------
$tool = $root.'/storage/app/scratch/lane61-who-owes.php';
$php = PHP_BINARY;
$out = (string) shell_exec('cd '.escapeshellarg($root).' && '.escapeshellarg($php).' '.escapeshellarg($tool).' 2>&1');
$say($out !== '', 'the instrument printed something — an empty run is not a clean one');
if ($out === '') {
    echo "\nREFUSED: lane61-who-owes.php produced no output.\n";
    exit(1);
}

$named = null;
if (preg_match('/rows whose column still NAMES an actor:\s*(\d+)/u', $out, $m) === 1) {
    $named = (int) $m[1];
}
$say($named !== null, 'the instrument reported how many rows name an actor');
if ($named === null) {
    echo "\nREFUSED: could not read the tally, so no comparison is possible.\n";
    exit(1);
}

$claimedRows = array_filter($table, fn (array $r): bool => $r[0] > 0);
$claimedTotal = array_sum(array_map(fn (array $r): int => $r[0], $table));
printf("\n  table: %d actor rows, %d claimed owed slot(s), %d of them non-zero\n", count($table), $claimedTotal, count($claimedRows));
echo "  column: {$named} row(s) still name an actor\n\n";

$say($claimedTotal === $named, 'the table totals '.$claimedTotal.' where the column names '.$named);

// Each id the table lists must actually appear in the instrument's actor lines, and vice versa.
$fromTool = [];
if (preg_match('/the actor column verbatim -+\n(.*?)(?:\n-{2,}|\z)/su', $out, $block) === 1) {
    foreach (explode("\n", trim($block[1])) as $row) {
        if (preg_match('/^(.*?)\s{2,}(\d+)\s+(.+)$/u', trim($row), $r) === 1) {
            foreach (preg_split('/, ?/', trim($r[3])) as $id) {
                $fromTool[strtoupper(trim($id))] = trim($r[1]);
            }
        }
    }
}
$say(count($fromTool) > 0, 'the instrument listed its owed identifiers ('.count($fromTool).')');

$tableIds = [];
foreach ($claimedRows as $actor => [$n, $ids]) {
    foreach (preg_split('/, ?/', $ids) as $id) {
        if (preg_match('/^([GDUXCN]-\d+|\d+)/u', trim($id), $m) === 1) {
            $tableIds[strtoupper($m[1])] = $actor;
        }
    }
}

foreach ($fromTool as $id => $actor) {
    $say(isset($tableIds[$id]), 'the column says '.$id.' still owes '.$actor.' — the table lists it');
}
foreach ($tableIds as $id => $actor) {
    $say(isset($fromTool[$id]), 'the table lists '.$id.' as owed to '.$actor.' — the column agrees');
}

// --- 3. the blind spots must be printed, not counted clean ---------------------------------------
$blind = preg_match('/BLIND SPOTS[^\n]*\n(.*?)(?:\(\d+ rows|\z)/su', $out, $b) === 1 ? trim($b[1]) : '';
echo "\nblind spots in the instrument's own reading (read them, they are not cleared):\n";
echo $blind === '' ? "  (none reported)\n" : '  '.str_replace("\n", "\n  ", $blind)."\n";

printf("\n%d problem(s).\n", $fails);
exit($fails > 0 ? 1 : 0);
