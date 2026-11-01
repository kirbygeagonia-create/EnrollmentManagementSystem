<?php

/**
 * Lane 59 — prove lane59-instrument-counts.php can fail, in each way the measurement pass showed it could.
 *
 * The checker's whole design came out of measuring what a naive version got wrong. A "number + countable noun" scan of
 * these documents returns 109 hits and most of them are not instrument readings at all — `term 18`, `21 DATE columns, 18
 * of them cast`, `§18.3's column list`, `documentprintlog carries eight columns`. Binding a figure to the tool named in
 * its own sentence fixed that, and every case below plants one way the binding could be quietly lost: a drifted number,
 * a figure the tool does not print, the last noun taken for the phrase (`16 school-moment cells` read as 16 day cells),
 * a comma figure, a spelled number, a historical sentence rewritten into the present, and the three flags that must
 * refuse instead of reporting clean.
 *
 * Two legs are expected CLEAN on purpose — a ratio with no tool beside it must not be read, and a dated sentence must be
 * left alone — because a harness that demands every case flip red reports a correct expectation as a failure (lane 55's
 * third leg).
 *
 * The last leg is ground truth: with this box's real readings, the checker must name the nine stale figures the
 * measurement pass counted. Before the documents are corrected it must find them; after, it must find none — and the
 * expectation moves only because a planted mutant proves the checker sees the difference.
 *
 *   php storage/app/scratch/mutate-lane59-instrument-counts.php
 */

$root = dirname(__DIR__, 3);
$php = escapeshellarg(PHP_BINARY);
$checker = escapeshellarg($root.'/storage/app/scratch/lane59-instrument-counts.php');
$dir = 'storage/app/scratch/lane59-mut';
if (! is_dir($root.'/'.$dir) && ! mkdir($root.'/'.$dir, 0644, true)) {
    echo "REFUSED: cannot build the scratch copy directory.\n";
    exit(1);
}

$fail = 0;
$say = static function (bool $ok, string $what) use (&$fail): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'BAD', $what);
    if (! $ok) {
        $fail++;
    }
};

$run = static function (string $cmd): array {
    // cmd.exe, not bash: `; echo EXIT=$?` arrives as extra argv and the marker never prints (lane 58 paid for that).
    $out = (string) shell_exec($cmd.' 2>&1 & if errorlevel 2 (echo EXIT=2) else if errorlevel 1 (echo EXIT=1) else (echo EXIT=0)');
    $code = -1;
    if (preg_match('/EXIT=(\d+)\s*$/', $out, $m) === 1) {
        $code = (int) $m[1];
    }

    return ['out' => $out, 'code' => $code];
};

/** The readings every fixture case is judged against — the shape this box's tools printed on 2026-10-20. */
$READINGS = [
    'sweep-docx-citations' => ['citations' => 602, 'files' => 141, 'problems' => 0],
    'lane37-section-reference-sweep' => ['headings' => 196, 'findingRows' => 108, 'references' => 860],
    'lane37b-repo-citation-sweep' => ['references' => 271, 'files' => 292, 'problems' => 0],
    'lane32-cite-meaning-sweep' => ['ranges' => 113, 'pairs' => 9],
    'lane43-delete-guards' => ['sites' => 19, 'problems' => 0, 'refusing' => 70, 'allowing' => 30],
    'lane44-unique-keys' => ['keys' => 9, 'tables' => 7],
    'lane46-filter-contract' => ['bindings' => 55, 'problems' => 0, 'unbound' => 0],
    'lane47-tile-scope' => ['screens' => 12, 'tiles' => 46],
    'lane48-tile-fallback' => ['screens' => 24, 'tiles' => 101],
    'lane49-zero-shown-as-absence' => ['dashGuards' => 93, 'unread' => 19, 'nonFigures' => 340, 'blind' => 15],
    'lane50-date-day-shift' => ['files' => 98, 'dayCells' => 26, 'momentCells' => 16, 'blind' => 9],
    'record-states' => ['rules' => 19],
    'demo-pipeline' => ['ledger' => 7],
];
file_put_contents($root.'/'.$dir.'/readings.json', json_encode($READINGS, JSON_PRETTY_PRINT));

/**
 * One case: the same document clean and mutated, the verdict required to move.
 *
 * Every fixture carries a control sentence that matches, so "no figure was read" can never be mistaken for a pass.
 */
$case = static function (string $name, string $clean, string $mutant, int $wantClean, int $wantMutant,
    array $wantIn, array $wantNotIn, string $why) use ($root, $dir, $php, $checker, $run, $say): void {
    $control = "Control: `php storage/app/scratch/lane46-filter-contract.php` judges 55 bindings and 0 problems.\n";
    $cleanBody = $control.$clean;
    $mutBody = $control.$mutant;
    if ($cleanBody === $mutBody) {
        $say(false, $name.' — the mutation left the bytes identical, so the case measures nothing');

        return;
    }
    $cf = $dir.'/'.$name.'.clean.md';
    $mf = $dir.'/'.$name.'.md';
    file_put_contents($root.'/'.$cf, $cleanBody);
    file_put_contents($root.'/'.$mf, $mutBody);
    // `--docs=AGENTS.md` scopes the run to the substituted copy. Without it the eight real documents are judged too, and
    // they carry the nine stale figures this lane exists to fix — so every case, clean or mutated, exits 1 and the
    // harness can no longer tell a planted defect from the state of the tree.
    $args = static fn (string $f): string => '--readings='.$dir.'/readings.json --doc=AGENTS.md='.$f.' --docs=AGENTS.md';
    $a = $run($php.' '.$checker.' '.$args($cf));
    $b = $run($php.' '.$checker.' '.$args($mf));
    $missing = array_values(array_filter($wantIn, static fn (string $n): bool => ! str_contains($b['out'], $n)));
    $leaked = array_values(array_filter($wantNotIn, static fn (string $n): bool => str_contains($b['out'], $n)));
    $say($a['code'] === $wantClean && $b['code'] === $wantMutant && $missing === [] && $leaked === [], $name.' — '.$why);
    if ($a['code'] !== $wantClean || $b['code'] !== $wantMutant || $missing !== [] || $leaked !== []) {
        printf("     exit clean wanted %d read %d; mutant wanted %d read %d%s%s\n",
            $wantClean, $a['code'], $wantMutant, $b['code'],
            $missing === [] ? '' : '; missing: '.implode(' / ', $missing),
            $leaked === [] ? '' : '; leaked: '.implode(' / ', $leaked));
        foreach (preg_split('/\n/', $b['out']) ?: [] as $l) {
            if (preg_match('/PRESENT|HISTORY|STALE|MATCHES|UNBOUND|VERDICT|REFUSED|figure\(s\)/', $l) === 1) {
                echo '       | '.substr($l, 0, 150)."\n";
            }
        }
    }
};

// ------------------------------------------------------------------ a figure that drifted

$case('drifted-citations',
    "Run `php storage/app/scratch/sweep-docx-citations.php` (602 citations over 141 files) after any batch that moves lines.\n",
    "Run `php storage/app/scratch/sweep-docx-citations.php` (559 citations over 141 files) after any batch that moves lines.\n",
    0, 1, ['STALE', 'prints 602 today'], ['MATCHES  claimed  559'],
    'the exact defect the measurement pass found in AGENTS.md: 559 stated where the sweep prints 602');

$case('comma-figure',
    "The desk log reads 1,048 audit rows and `ems:audit:record-states` runs 19 rules.\n",
    "The desk log reads 1,048 audit rows and `ems:audit:record-states` runs 21 rules.\n",
    0, 1, ['STALE'], [],
    'a comma inside a number is parsed, not skipped — lane 58 lost every comma figure to is_numeric()');

$case('spelled-number',
    "The command carries nineteen rules and `ems:audit:record-states` prints them.\n",
    "The command carries eighteen rules and `ems:audit:record-states` prints them.\n",
    0, 1, ['STALE'], [],
    'a count written as a word is a claim, and 18 is what lane 49 calls the unread guards, not the rules');

// ------------------------------------------------------------------ the binding that makes the design work

$case('phrase-not-last-noun',
    "The checker reports 16 school-moment cells and 26 day cells, per `lane50-date-day-shift.php`.\n",
    "The checker reports 16 day cells and 26 day cells, per `lane50-date-day-shift.php`.\n",
    0, 1, ['STALE'], [],
    'matching the last noun would read "16 school-moment cells" as 16 day cells and call a true sentence stale');

// UNBOUND is not "a noun I have no label for" — that figure is simply not read. It is a label that maps to a reading
// the tool did not answer, which is what happens when an instrument runs and prints no summary line. Judged against a
// readings file with that instrument nulled out, the same sentence must refuse instead of passing.
$outReadings = $READINGS;
$outReadings['lane43-delete-guards'] = null;
file_put_contents($root.'/'.$dir.'/readings-nulled.json', json_encode($outReadings, JSON_PRETTY_PRINT));
$nullDoc = $dir.'/unbound-null.md';
file_put_contents($root.'/'.$nullDoc,
    "Control: `php storage/app/scratch/lane46-filter-contract.php` judges 55 bindings and 0 problems.\n"
    ."The sweep judged 19 delete sites with `lane43-delete-guards.php`.\n");
$good = $run($php.' '.$checker.' --readings='.$dir.'/readings.json --doc=AGENTS.md='.$nullDoc.' --docs=AGENTS.md');
$nulled = $run($php.' '.$checker.' --readings='.$dir.'/readings-nulled.json --doc=AGENTS.md='.$nullDoc.' --docs=AGENTS.md');
$say($good['code'] === 0 && str_contains($good['out'], 'MATCHES')
    && $nulled['code'] === 1 && str_contains($nulled['out'], 'UNBOUND'),
    'unbound-reading — the same claim passes while its tool answers and refuses the moment it stops answering');

$case('history-is-not-staleness',
    "Today the tool judges 55 bindings and 0 problems, says `lane46-filter-contract.php`'s own summary.\n",
    "Today the tool judges 27 bindings and 0 problems, says `lane46-filter-contract.php`'s own summary.\n",
    0, 1, ['STALE'], [],
    'a present-tense sentence about the tool is judged; the same number inside "its first version judged 27" is a record');

// ------------------------------------------------------------------ what must stay clean

$case('ratio-without-a-tool',
    "The block reads `2/40` seats and the desk is open 24/7, per the roster.\n",
    "The block reads `9/40` seats and the desk is open 48/7, per the roster.\n",
    0, 0, ['2 figure(s) cited a named instrument'], ['STALE', 'UNBOUND'],
    'ratios are not readings: only the control sentence is bound, in both copies');

$case('dated-sentence-left-alone',
    "Measured 2026-10-11: the audit log held 298 rows and `lane37b-repo-citation-sweep.php` judged 3 references.\n",
    "Measured 2026-10-12: the audit log held 298 rows and `lane37b-repo-citation-sweep.php` judged 4 references.\n",
    0, 0, ['HISTORY'], ['STALE'],
    'a figure inside a sentence that names its own day is history whatever the tools now print');

// ------------------------------------------------------------------ the refusals

$r = $run($php.' '.$checker.' --doc=AGENTS.md='.$dir.'/drifted-citations.md');
$say($r['code'] === 2 && str_contains($r['out'], 'no readings'),
    'no-readings — the checker refuses rather than agreeing with the documents');

$r = $run($php.' '.$checker.' --readings='.$dir.'/readings.json --docs=NoSuchFile.md');
$say($r['code'] === 2 && str_contains($r['out'], 'no document'),
    'bad-doc-scope — a --docs list that selects nothing refuses instead of reporting a clean tree');

$r = $run($php.' '.$checker.' --readings='.$dir.'/readings.json --run --only=lane77-no-such-tool');
$say($r['code'] === 2 && str_contains($r['out'], 'no instrument'),
    'bad-only-name — a typo in a scope flag is refused, not read as "nothing to check"');

$r = $run($php.' '.$checker.' --readings='.$dir.'/readings.json --doc=Nonsense.md='.$dir.'/drifted-citations.md');
$say($r['code'] === 2 && str_contains($r['out'], 'does not read'),
    'unknown-doc-label — an --doc override for a document nobody reads is refused, not accepted and ignored');

file_put_contents($root.'/'.$dir.'/empty.md', "Nothing about a tool here.\n");
$r = $run($php.' '.$checker.' --readings='.$dir.'/readings.json --doc=AGENTS.md='.$dir.'/empty.md --docs=AGENTS.md');
$say($r['code'] === 2 && str_contains($r['out'], 'no instrument figure was read'),
    'no-figures — a scan that bound nothing is a broken instrument, not a pass');

// ------------------------------------------------------------------ ground truth against the real readings

echo "\n";
$live = $root.'/storage/app/scratch/lane59-readings.json';
$use = is_file($live) ? 'storage/app/scratch/lane59-readings.json' : $dir.'/readings.json';
$r = $run($php.' '.$checker.' --readings='.$use);
// The relation, not a remembered count. This leg used to assert `$ok >= 7`, and lane 114 made that fail for the
// wrong reason: the batch deliberately took a figure OUT of AGENTS.md (a leg count beside the harness that prints
// its own), so the checker now binds fewer figures and reports them all as agreeing. A floor read off one day's
// document count is a stale claim inside the instrument; what must hold is that every figure the checker judged
// agreed, that nothing was left unbound, and that it judged something at all.
preg_match('/(\d+) figure\(s\) cited a named instrument: (\d+) judged \((\d+) match, (\d+) stale, (\d+) unbound/', $r['out'], $s);
$cited = (int) ($s[1] ?? -1);
$judged = (int) ($s[2] ?? -1);
$ok = (int) ($s[3] ?? -1);
$stale = (int) ($s[4] ?? -1);
$unbound = (int) ($s[5] ?? -1);
printf("     real documents, readings from %s: %d cited, %d judged = %d match + %d stale + %d unbound, exit %d\n",
    basename($use), $cited, $judged, $ok, $stale, $unbound, $r['code']);
$say($stale === 0 && $unbound === 0 && $judged >= 1 && $ok === $judged && $judged <= $cited && $r['code'] === 0,
    'real-run — every instrument figure the checker judged equals what its tool prints ('.$judged.' of '.$cited.' cited)');

// The CI half: only the citation sweeps are measured on the runner, so a figure citing a tool CI leaves out is NAMED and
// not judged — never passed as checked. This leg judges the REAL documents, so it must read the REAL readings: with the
// fixture's 2026-10-20 numbers it reported a checker defect when the tree had simply moved (lane37b's own count went
// 271→275 when this file grew), and a harness that fails for the wrong reason trains nobody to trust its red.
$truthPath = $root.'/storage/app/scratch/lane59-readings.json';
if (! is_file($truthPath)) {
    printf("REFUSED: no %s — run lane59-collect-readings.php first.\n", 'lane59-readings.json');
    printf("       Two legs below judge the real documents, and a fixture reading cannot settle what a live tool prints.\n");
    exit(1);
}
$TRUTH = json_decode((string) file_get_contents($truthPath), true);
if (! is_array($TRUTH) || $TRUTH === []) {
    exit("REFUSED: {$truthPath} parses to nothing usable, so the two real-document legs would judge on no measurement.\n");
}

$fourOnly = [];
foreach (['sweep-docx-citations', 'lane37-section-reference-sweep',
    'lane37b-repo-citation-sweep', 'lane32-cite-meaning-sweep'] as $name) {
    if (! isset($TRUTH[$name])) {
        exit("REFUSED: the real readings carry no {$name} measurement, so CI's own scope cannot be rehearsed.\n");
    }
    $fourOnly[$name] = $TRUTH[$name];
}
file_put_contents($root.'/'.$dir.'/readings-ci.json', json_encode($fourOnly, JSON_PRETTY_PRINT));
$r = $run($php.' '.$checker.' --readings='.$dir.'/readings-ci.json --ci');
$say($r['code'] === 0 && str_contains($r['out'], 'Named, not judged, in CI mode'),
    'ci-scope — a tool the runner does not measure is named, while a tool that ran and printed nothing is refused');
if ($r['code'] !== 0) {
    foreach (preg_split('/\n/', $r['out']) ?: [] as $l) {
        if (preg_match('/STALE|UNBOUND|VERDICT|REFUSED|figure\(s\)/', $l) === 1) {
            echo '       | '.substr($l, 0, 150)."\n";
        }
    }
}

// The fixture path, kept a fixture: scoped to a synthetic document whose figures are the ones $READINGS carries above, so
// this leg proves the readings file is read at all rather than re-judging the tree.
$fixtureDoc = "Run `php storage/app/scratch/sweep-docx-citations.php` (602 citations over 141 files).\n"
    .'`lane44-unique-keys.php` compares 9 keys over 7 tables.\n'
    .'`ems:audit:record-states` runs 19 rules over the state-bearing tables.\n';
file_put_contents($root.'/'.$dir.'/allclean.md', $fixtureDoc);
$r = $run($php.' '.$checker.' --readings='.$dir.'/readings.json --doc=AGENTS.md='.$dir.'/allclean.md --docs=AGENTS.md');
$say(str_contains($r['out'], 'every present-tense instrument figure') && $r['code'] === 0,
    'fixture-readings — three figures, three tools, all bound from one readings file, and the run says so');

if ($fail > 0) {
    printf("\n%d case(s) BAD — the checker or this harness is wrong, and a clean verdict from either would be a lie.\n", $fail);
    exit(1);
}
echo "\nAll lane 59 cases behaved.\n";
exit(0);
