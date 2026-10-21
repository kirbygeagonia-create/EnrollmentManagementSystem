<?php

/**
 * Lane 60 — prove lane60-ratio-claims.php can fail, in each way the measurement pass showed it could.
 *
 * The checker's shape came from what this lane found: a census sentence carrying four superseded readings and one number
 * that was never an index count at all; a per-table heading that could name a table the server does not have; a figure
 * written as a word ("six document types") that a digits-only parser would skip; and a claim that could survive being
 * reworded into nothing, which is how a checker with a hole and a checked document start to look alike.
 *
 * Two legs must stay CLEAN — a sentence naming its own day is history, and `8/8 rendered` is not a reading — because a
 * harness that demands every case flip red reports a correct expectation as a failure (lane 55's third leg).
 *
 * Every fixture case is scoped twice: `--docs=docx` so the markdown files are not judged against a fixture, and
 * `--only=<ids>` so the claims the fixture does not carry are out of scope rather than UNBOUND. Without the second one
 * clean and mutant both exit 1, and the harness measures nothing about the planted defect — found the hard way on the
 * first run of this file.
 *
 *   php storage/app/scratch/mutate-lane60-ratio-claims.php
 */

$root = dirname(__DIR__, 3);
$php = escapeshellarg(PHP_BINARY);
$checker = escapeshellarg($root.'/storage/app/scratch/lane60-ratio-claims.php');
$dir = 'storage/app/scratch/lane60-mut';
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

/** The readings the fixtures are judged against — the shape live `ems` answered on 2026-10-21. */
$READINGS = [
    'instrument' => 'lane60-probe-ratios.php',
    'database' => 'ems',
    'asOf' => '2026-10-21',
    'schoolDay' => '2026-10-21',
    'tables' => 63,
    'frameworkTables' => 8,
    'domainTables' => 55,
    'reproducibleFromMigrations' => 57,
    'notFromMigrations' => 6,
    'columns' => 452,
    'domainColumns' => 410,
    'foreignKeys' => 100,
    'indexes' => 129,
    'constraints' => 175,
    'eloquentModels' => 54,
    'documentTypeEnumValues' => 6,
    'deleteRules' => ['cascade' => 4, 'setNull' => 26, 'restrict' => 1, 'noAction' => 69, 'refusing' => 70, 'total' => 100],
    'permissions' => 93,
    'roles' => 17,
    'staffRoleAssignments' => 42,
    'grantedToSysAdminOnly' => 24,
    'documentHeadings' => ['read' => 63, 'agree' => 63, 'differ' => 0, 'domainStatedColumns' => 410, 'domainStatedTables' => 55],
    'perTableColumns' => ['students' => 22, 'enrollments' => 23, 'payments' => 13],
    'rows' => ['gradescaleRows' => 4, 'academictermsRows' => 9, 'settingsRows' => 3],
    'failedReadings' => [],
];
file_put_contents($root.'/'.$dir.'/readings.json', json_encode($READINGS, JSON_PRETTY_PRINT));

/**
 * One case: the same document clean and mutated, the verdict required to move.
 *
 * @param  string[]  $ids  the claims this fixture carries, so the rest are out of scope rather than UNBOUND
 */
$case = static function (string $name, string $clean, string $mutant, array $ids, int $wantClean, int $wantMutant,
    array $wantIn, array $wantNotIn, string $why) use ($root, $dir, $php, $checker, $run, $say): void {
    // Every fixture carries two headings that match, so "no figure was read" can never be mistaken for a pass.
    $head = "P 1: students — 22 columns\nP 2: enrollments — 23 columns\n";
    $cleanBody = $head.$clean;
    $mutBody = $head.$mutant;
    if ($cleanBody === $mutBody) {
        $say(false, $name.' — the mutation left the bytes identical, so the case measures nothing');

        return;
    }
    $cf = $dir.'/'.$name.'.clean.txt';
    $mf = $dir.'/'.$name.'.txt';
    file_put_contents($root.'/'.$cf, $cleanBody);
    file_put_contents($root.'/'.$mf, $mutBody);
    $args = static fn (string $f): string => '--readings='.$dir.'/readings.json --flat='.$f
        .' --docs=docx --only='.implode('+', $ids);
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
        foreach (explode("\n", $b['out']) as $l) {
            if (preg_match('/MATCHES|STALE|UNBOUND|HISTORY|NAMED|VERDICT|REFUSED|figure\(s\)/', $l) === 1) {
                echo '       | '.substr($l, 0, 150)."\n";
            }
        }
    }
};

$counts = "P 3: Counts: 63 tables (55 EMS domain + 8 framework), 452 columns, 100 foreign-key constraints, 129 indexes, and 175 table constraints in all.\n";

// ------------------------------------------------------------------ a census figure that drifted

$case('census-tables', $counts, str_replace('Counts: 63 tables', 'Counts: 62 tables', $counts),
    ['counts-sentence'], 0, 1, ['STALE', 'claimed 62', 'answers 63'], [],
    'the exact defect lane 60 found: a superseded table count restated as the present schema');

$case('census-index-figure', $counts, str_replace('129 indexes', '175 indexes', $counts),
    ['counts-sentence'], 0, 1, ['STALE', 'indexes', 'claimed 175', 'answers 129'], [],
    'the figure that was never an index count: 175 is constraints, and the checker judges the noun it bound');

$case('census-comma-figure', $counts, str_replace('452 columns', '1,452 columns', $counts),
    ['counts-sentence', 'headings'], 0, 1, ['STALE', 'claimed 1452'], [],
    'a comma inside a number is parsed, not skipped — lane 58 lost every comma figure to is_numeric()');

$case('delete-rule-drift',
    "P 3: These 26 keys are SET NULL on delete: the record stays and the attribution disappears.\n",
    "P 3: These 16 keys are SET NULL on delete: the record stays and the attribution disappears.\n",
    ['nulling-keys'], 0, 1, ['STALE', 'deleteRules.setNull', 'answers 26'], [],
    'the section that contradicted its own summary line two paragraphs above');

$case('spelled-figure-binds',
    "P 3: 25.2 The printable universe is six document types\n",
    "P 3: 25.2 The printable universe is nine document types\n",
    ['printable-universe'], 0, 1, ['STALE', 'claimed 9', 'answers 6'], [],
    'a figure written as a word is a claim, and the enum answers six');

$case('cross-check-drift',
    "P 3: Live tables against the migration parse: 57 of 63 tables agree column for column; 6 differ\n",
    "P 3: Live tables against the migration parse: 58 of 63 tables agree column for column; 5 differ\n",
    ['cross-check'], 0, 1, ['STALE', 'reproducibleFromMigrations', 'answers 57'], [],
    'an x-of-y ratio binds both sides: the part and the exceptions move together or the claim is wrong');

$case('reworded-claim-refuses',
    "P 3: These 26 keys are SET NULL on delete: the record stays.\n",
    "P 3: A further set of keys nulls the child reference when the parent goes.\n",
    ['nulling-keys'], 0, 1, ['UNBOUND', 'not in the document'], ['MATCHES'],
    'a claim reworded out of existence must refuse the run — an unread claim may never look like a checked one');

// ------------------------------------------------------------------ the per-table heading class

$case('heading-drift',
    "P 9: payments — 13 columns\n", "P 9: payments — 12 columns\n",
    ['headings'], 0, 1, ['STALE', 'per-table', 'stated 12 columns, the server holds 13'], [],
    'one dictionary heading off by one, judged against the server rather than the migration text');

$case('heading-orphan',
    "P 9: payments — 13 columns\n", "P 9: wonderment — 13 columns\n",
    ['headings'], 0, 1, ['UNBOUND', 'no table by that name'], ['the server holds'],
    'a heading naming a table the readings do not carry is UNBOUND — a parser with a hole must not look like a pass');

// ------------------------------------------------------------------ the expectations that must stay CLEAN

$case('dated-sentence-left-alone', $counts,
    "P 3: Measured on 2026-10-01: Counts: 62 tables (54 EMS domain + 8 framework), 418 columns, 86 foreign-key constraints, 175 indexes, and 99 table constraints in all.\n",
    ['counts-sentence'], 0, 0, ['HISTORY'], ['STALE', 'UNBOUND'],
    'a figure inside a sentence that names its own day is history whatever the server answers now');

$case('ratios-are-not-readings', $counts,
    $counts."P 4: Done: 8/8 rendered in storage/app/prints/fidelity and the desk is open 24/7 for the office.\n",
    ['counts-sentence'], 0, 0, ['MATCHES'], ['STALE', 'UNBOUND'],
    '`8/8` and `24/7` are not readings: only a captured position addressed to a named figure is judged');

$case('scoped-out-claim-not-judged', $counts,
    $counts."P 4: gradescale has 4 rows and a 3.00 fallback\n",
    ['counts-sentence'], 0, 0, ['out of scope', 'grade-scale'], ['STALE', 'UNBOUND'],
    'a claim --only leaves out is reported as out of scope, never as a figure that was checked');

// ------------------------------------------------------------------ the refusals

$r = $run($php.' '.$checker.' --readings='.$dir.'/absent.json --flat='.$dir.'/census-tables.clean.txt --docs=docx --only=counts-sentence');
$say($r['code'] === 1 && str_contains($r['out'], 'REFUSED'),
    'no-readings — the checker refuses rather than agreeing with the documents');

$r = $run($php.' '.$checker.' --readings='.$dir.'/readings.json --flat='.$dir.'/census-tables.clean.txt --docs=nosuchdoc');
$say($r['code'] === 1 && str_contains($r['out'], 'REFUSED'),
    'bad-doc-scope — a --docs list that selects nothing refuses instead of reporting a clean tree');

$r = $run($php.' '.$checker.' --readings='.$dir.'/readings.json --flat='.$dir.'/census-tables.clean.txt --doc=nosuch=x.txt');
$say($r['code'] === 1 && str_contains($r['out'], 'REFUSED'),
    'unknown-doc-label — an --doc override for a document nobody reads is refused, not accepted and ignored');

$r = $run($php.' '.$checker.' --readings='.$dir.'/readings.json --flat='.$dir.'/census-tables.clean.txt --docs=docx --only=counts-sentes');
$say($r['code'] === 1 && str_contains($r['out'], 'REFUSED'),
    'bad-claim-name — a typo in --only is refused, not read as "nothing to check"');

$r = $run($php.' '.$checker.' --readings='.$dir.'/readings.json --flat='.$dir.'/census-tables.clean.txt --docs=docx --only=agents-fk-rules');
$say($r['code'] === 1 && str_contains($r['out'], "out of this run's --docs scope"),
    'claim-document-mismatch — scoping a markdown claim to a docx fixture refuses instead of judging nothing');

file_put_contents($root.'/'.$dir.'/noheadings.txt', $counts);
$r = $run($php.' '.$checker.' --readings='.$dir.'/readings.json --flat='.$dir.'/noheadings.txt --docs=docx --only=counts-sentence+headings');
$say($r['code'] === 1 && str_contains($r['out'], 'REFUSED'),
    'no-headings — a scan that bound the largest class by nothing is a broken instrument, not a pass');

$broken = $READINGS;
$broken['failedReadings'] = ['rows.gradescaleRows'];
file_put_contents($root.'/'.$dir.'/readings-broken.json', json_encode($broken));
$r = $run($php.' '.$checker.' --readings='.$dir.'/readings-broken.json --flat='.$dir.'/census-tables.clean.txt --docs=docx --only=counts-sentence');
$say($r['code'] === 1 && str_contains($r['out'], 'REFUSED'),
    'failed-reading — a reading the probe could not take refuses the run instead of passing the claim beside it');

$wrongSchema = $READINGS;
$wrongSchema['database'] = 'ems_l54_a';
file_put_contents($root.'/'.$dir.'/readings-scratch.json', json_encode($wrongSchema));
$r = $run($php.' '.$checker.' --readings='.$dir.'/readings-scratch.json --flat='.$dir.'/census-tables.clean.txt --docs=docx --only=counts-sentence');
$say(str_contains($r['out'], 'a schema named ems_l54_a') || str_contains($r['out'], 'REFUSED'),
    'surface-named — readings from a scratch schema are reported as that surface, never silently called the live one');

// ------------------------------------------------------------------ CI mode and ground truth

file_put_contents($root.'/'.$dir.'/ci-scope.txt', $counts
    ."P 4: staff_roles is unique on the pair: 42 assignments over 17 roles\n"
    ."P 5: no grade-bearing table is printed; gradescale has 4 rows and a 3.00 fallback\n");
$r = $run($php.' '.$checker.' --readings='.$dir.'/readings.json --flat='.$dir.'/ci-scope.txt --docs=docx --only=counts-sentence+role-seats+grade-scale --ci');
$say($r['code'] === 0 && str_contains($r['out'], 'NAMED') && str_contains($r['out'], 'belongs to the database that stated it'),
    'ci-scope — a count of rows is named and not judged on a runner whose data is its own');
if (! str_contains($r['out'], 'NAMED')) {
    echo '       | '.substr(str_replace("\n", "\n       | ", trim($r['out'])), 0, 500)."\n";
}

$r = $run($php.' '.$checker.'');
$say($r['code'] === 0 && str_contains($r['out'], 'VERDICT: every bound ratio'),
    'real-run — every ratio, census figure and §24 column count the documents state equals what the server answers');
if ($r['code'] !== 0) {
    foreach (explode("\n", $r['out']) as $l) {
        if (preg_match('/STALE|UNBOUND|REFUSED|figure\(s\)/', $l) === 1) {
            echo '       | '.substr($l, 0, 155)."\n";
        }
    }
}

if ($fail > 0) {
    printf("\n%d case(s) BAD — the checker or this harness is wrong, and a clean verdict from either would be a lie.\n", $fail);
    exit(1);
}
echo "\nAll lane 60 cases behaved.\n";
exit(0);
