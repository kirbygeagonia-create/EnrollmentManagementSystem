<?php

/**
 * Lane 60 — every ratio, census figure and per-table column count the documents state, bound to the query that answers it.
 *
 * Lane 58 bound the suite totals to instrument output; lane 59 bound the counts the tools print. This is the third family
 * and the one no checker read: figures stated in prose over the database. "Counts: 62 tables (54 EMS domain + 8 framework),
 * 418 columns, 86 foreign-key constraints, 175 indexes" was a 2026-10-04 reading restated as the present schema, while
 * §23.8's own cross-check on the same subject said 63 — the document disagreed with itself and nothing noticed, because
 * pass 28 re-derived the census and rewrote only some of the sentences holding it. Worse, one figure was not stale but
 * mis-named: 175 is what information_schema counts as table constraints, and the schema holds 129 indexes.
 *
 * What this refuses, and why each rule is shaped the way it is:
 *
 *   A claim binds only if its own sentence is findable. A sentence that no longer matches its pattern is UNBOUND and the
 *     run refuses — a reworded claim must never look like a checked one (lane 42's lesson, lane 59's again).
 *   A figure with no reading beside it is not a claim. `8/8 rendered`, `24/7`, `2/40` and a "4 of the 43" where the 43
 *     is the one measured figure all read as ratios, not readings: only the captured position addressed to a named reading
 *     is judged.
 *   A sentence that carries its own date is history. "measured: 1,459 of 3,426 audit rows" stays true whatever the table
 *     holds now; judging it is how a document gets bullied into restating a record.
 *   Data figures are judged on the surface that states them. The schema census is a property of the migrations, so CI's
 *     own seeded schema may answer it; a count of rows in `staff_roles` belongs to whichever database was read, so in
 *     `--ci` those claims are named and left unjudged rather than passed.
 *   Spellings count. "six document types" is a claim the enum answers with 6.
 *
 * usage:
 *   php storage/app/scratch/lane60-ratio-claims.php [--run] [--ci] [--trace] [--report-only]
 *   php storage/app/scratch/lane60-ratio-claims.php --readings=<file> [--flat=<file>] [--docs=docx,AGENTS.md]
 *       [--only=counts-sentence+headings]   ids joined by + or ,; `headings` selects the §24 per-table class
 */

$root = dirname(__DIR__, 3);
$php = PHP_BINARY;

$flat = $root.'/storage/app/scratch/docx-flat.txt';
$readings = $root.'/storage/app/scratch/lane60-readings.json';
$ciMode = false;
$reportOnly = false;
$trace = false;
$takeNow = false;
$only = [];
$onlyClaims = [];
$overrides = [];
$allowSchema = null;

foreach (array_slice($argv, 1) as $a) {
    if ($a === '--ci') {
        $ciMode = true;
    } elseif ($a === '--report-only') {
        $reportOnly = true;
    } elseif ($a === '--trace') {
        $trace = true;
    } elseif ($a === '--run') {
        $takeNow = true;
    } elseif (preg_match('/^--only=(.*)$/s', $a, $m) === 1) {
        // '+' as well as ',': claim ids carry spaces, and a shell argument with commas-and-spaces is a quoting hazard
        // the harness would rather not pay for.
        $onlyClaims = array_values(array_filter(array_map('trim', preg_split('/[,+]/', $m[1]) ?: [])));
    } elseif (preg_match('/^--docs=(.*)$/s', $a, $m) === 1) {
        $only = array_values(array_filter(array_map('trim', explode(',', $m[1]))));
    } elseif (preg_match('/^--doc=([^=]+)=(.*)$/s', $a, $m) === 1) {
        $overrides[trim($m[1])] = $root.'/'.ltrim($m[2], '/');
    } elseif (preg_match('/^--allow-schema=(.*)$/s', $a, $m) === 1) {
        $allowSchema = $m[1];
    } elseif (preg_match('/^--readings=(.*)$/s', $a, $m) === 1) {
        $readings = $root.'/'.$m[1];
    } elseif (preg_match('/^--flat=(.*)$/s', $a, $m) === 1) {
        $flat = $root.'/'.$m[1];
    } elseif (! str_starts_with($a, '-') && $a !== '') {
        $readings = $root.'/'.$a;
    }
}

if ($takeNow) {
    // The readings are taken by the probe, never restated here: a checker that carries its own copy of the numbers is a
    // second document to go stale. --allow-schema is forwarded, not invented: the probe refuses a schema it was not told
    // to read, so a CI run against ems_test says so on the command line.
    $cmd = escapeshellarg($php).' '.escapeshellarg($root.'/storage/app/scratch/lane60-probe-ratios.php')
        .' --write-readings='.escapeshellarg(str_replace($root.'/', '', $readings));
    if ($allowSchema !== null) {
        $cmd .= ' --allow-schema='.escapeshellarg($allowSchema);
    }
    $cmd .= ' 2>&1';
    $out = (string) shell_exec($cmd);
    if (! str_contains($out, 'readings written to')) {
        echo "REFUSED: the probe did not report writing its readings, so nothing below is measured.\n";
        echo substr($out, 0, 800)."\n";
        exit(1);
    }
}

if (! is_file($readings)) {
    printf("REFUSED: no readings at %s — run with --run, or run lane60-probe-ratios.php --write-readings=…\n", $readings);
    exit(1);
}
if (! is_file($flat)) {
    echo "REFUSED: no docx dump at {$flat} — run dump-docx-flat.php first.\n";
    exit(1);
}
$R = json_decode((string) file_get_contents($readings), true);
if (! is_array($R) || ! isset($R['tables'], $R['perTableColumns'])) {
    echo "REFUSED: the readings file does not carry the census and the per-table lists.\n";
    exit(1);
}
if (! empty($R['failedReadings'])) {
    printf("REFUSED: the probe could not take %d reading(s): %s\n", count($R['failedReadings']),
        implode(', ', $R['failedReadings']));
    exit(1);
}

$surface = ($R['database'] ?? '') === 'ems' ? 'this box (live ems)' : 'a schema named '.$R['database'];
$WORDS = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8,
    'nine' => 9, 'ten' => 10, 'eleven' => 11, 'twelve' => 12, 'thirteen' => 13, 'fourteen' => 14, 'fifteen' => 15,
    'sixteen' => 16, 'seventeen' => 17, 'eighteen' => 18, 'nineteen' => 19, 'twenty' => 20,
    'twenty-one' => 21, 'twenty-two' => 22, 'twenty-three' => 23, 'twenty-four' => 24, 'twenty-five' => 25,
    'twenty-six' => 26, 'thirty' => 30, 'forty' => 40, 'forty-five' => 45, 'fifty' => 50, 'fifty-five' => 55,
    'sixty' => 60, 'sixty-two' => 62, 'sixty-three' => 63, 'seventy' => 70, 'ninety' => 90, 'ninety-three' => 93];

/** @return array<int,array{id:string,doc:string,line:int,figure:string,claimed:int,reading:string,cls:string,expect:int}> */
function valueOf(string $t, array $words): ?int
{
    // Un-comma first, then ask if it is a number: is_numeric("1,452") is false, and lane 58 paid for that exact order —
    // a comma figure would otherwise be reported as "a form this checker cannot read" instead of judged.
    $t = strtolower(trim($t));
    $digits = str_replace(',', '', $t);
    if (is_numeric($digits)) {
        return (int) $digits;
    }

    return $words[$t] ?? null;
}

function dig(array $R, string $path): ?int
{
    $cur = $R;
    foreach (explode('.', $path) as $seg) {
        if (! is_array($cur) || ! array_key_exists($seg, $cur)) {
            return null;
        }
        $cur = $cur[$seg];
    }

    return is_numeric($cur) ? (int) $cur : null;
}

// ---------------------------------------------------------------- the sentences, each with the readings it claims

$DOCX = 'docx';
$AGENTS = 'AGENTS.md';
$BUILD = 'Build_Plan.md';

$CLAIMS = [
    ['doc' => $DOCX, 'cls' => 'schema', 'id' => 'counts-sentence',
        're' => '/Counts: (\d[\d,]*) tables \((\d[\d,]*) EMS domain \+ (\d[\d,]*) framework\), (\d[\d,]*) columns, (\d[\d,]*) foreign-key constraints, (\d[\d,]*) indexes, and (\d[\d,]*) table constraints in all\./',
        'paths' => ['tables', 'domainTables', 'frameworkTables', 'columns', 'foreignKeys', 'indexes', 'constraints']],
    ['doc' => $DOCX, 'cls' => 'schema', 'id' => 'header-pair',
        're' => '/Domain tables in the live database \| (\d[\d,]*) \| .*?holds (\d[\d,]*) tables in all, of which (\d[\d,]*) are framework/s',
        'paths' => ['domainTables', 'tables', 'frameworkTables']],
    ['doc' => $DOCX, 'cls' => 'schema', 'id' => 'column-aggregate',
        're' => '/(\d[\d,]*) columns documented for (\d[\d,]*) domain tables/',
        'paths' => ['documentHeadings.domainStatedColumns', 'domainTables']],
    ['doc' => $DOCX, 'cls' => 'schema', 'id' => 'models-vs-tables',
        're' => '/(\d[\d,]*) Eloquent models for (\d[\d,]*) domain tables/',
        'paths' => ['eloquentModels', 'domainTables']],
    ['doc' => $DOCX, 'cls' => 'schema', 'id' => 'cross-check',
        're' => '/(\d[\d,]*) of (\d[\d,]*) tables agree column for column; (\d[\d,]*) differ/',
        'paths' => ['reproducibleFromMigrations', 'tables', 'notFromMigrations']],
    ['doc' => $DOCX, 'cls' => 'schema', 'id' => 'cross-check-second',
        're' => '/reproduce the live column set for (\d[\d,]*) of the (\d[\d,]*) tables documented here\. The (\d[\d,]*) exceptions/s',
        'paths' => ['reproducibleFromMigrations', 'tables', 'notFromMigrations']],
    ['doc' => $DOCX, 'cls' => 'schema', 'id' => 'nulling-keys',
        're' => '/These (\d[\d,]*) keys are SET NULL on delete/',
        'paths' => ['deleteRules.setNull']],
    ['doc' => $DOCX, 'cls' => 'schema', 'id' => 'fk-census',
        're' => '/(\d[\d,]*) constraints: (\d[\d,]*) refuse deletion of the parent \(NO ACTION\), (\d[\d,]*) null the child key \(SET NULL\), (\d[\d,]*) delete the child rows/s',
        'paths' => ['foreignKeys', 'deleteRules.noAction', 'deleteRules.setNull', 'deleteRules.cascade']],
    ['doc' => $DOCX, 'cls' => 'schema', 'id' => 'partition-2416',
        're' => '/(\d[\d,]*) domain \+ (\d[\d,]*) framework, and every column, key and index quoted/',
        'paths' => ['domainTables', 'frameworkTables']],
    ['doc' => $DOCX, 'cls' => 'schema', 'id' => 'show-tables',
        're' => '/(\d[\d,]*) \+ (\d[\d,]*)\. This is what SHOW TABLES returns/',
        'paths' => ['domainTables', 'frameworkTables']],
    ['doc' => $DOCX, 'cls' => 'data', 'id' => 'role-seats',
        're' => '/(\d[\d,]*) assignments over (\d[\d,]*) roles/',
        'paths' => ['staffRoleAssignments', 'roles']],
    ['doc' => $DOCX, 'cls' => 'data', 'id' => 'permission-catalogue',
        're' => '/(\d[\d,]*) permissions across (\d[\d,]*) modules, and (\d[\d,]*) Spatie roles/',
        'paths' => ['permissions', null, 'roles']],
    ['doc' => $DOCX, 'cls' => 'data', 'id' => 'sysadmin-only',
        're' => '/These (\d[\d,]*) permissions are defined in the catalogue but granted to no role other than SysAdmin/',
        'paths' => ['grantedToSysAdminOnly']],
    ['doc' => $DOCX, 'cls' => 'data', 'id' => 'grade-scale',
        're' => '/gradescale has (\d[\d,]*) rows and a 3\.00 fallback/s',
        'paths' => ['rows.gradescaleRows']],
    ['doc' => $DOCX, 'cls' => 'data', 'id' => 'terms-catalog',
        're' => '/academicterms \| Yes — CRUD \| Yes, (\d[\d,]*) terms/s',
        'paths' => ['rows.academictermsRows']],
    ['doc' => $DOCX, 'cls' => 'data', 'id' => 'settings-table',
        're' => '/holds (\d[\d,]*) rows on the live database, written by SettingsSeeder/s',
        'paths' => ['rows.settingsRows']],
    ['doc' => $DOCX, 'cls' => 'schema', 'id' => 'printable-universe',
        're' => '/The printable universe is (\w+) document types/i',
        'paths' => ['documentTypeEnumValues']],
    ['doc' => $AGENTS, 'cls' => 'schema', 'id' => 'agents-fk-rules',
        're' => '/carries (\d[\d,]*) foreign keys: only (\w+)\s+cascade.*?and (\d[\d,]*) refuse a delete outright/s',
        'paths' => ['foreignKeys', 'deleteRules.cascade', 'deleteRules.refusing']],
    ['doc' => $BUILD, 'cls' => 'schema', 'id' => 'plan-stage',
        're' => '/(\d[\d,]*) tables and (\d[\d,]*) foreign keys now, against/s',
        'paths' => ['tables', 'foreignKeys']],
    ['doc' => $BUILD, 'cls' => 'schema', 'id' => 'plan-engine',
        're' => '/MySQL 8 engine with (\d[\d,]*) interconnected tables/s',
        'paths' => ['tables']],
    ['doc' => $BUILD, 'cls' => 'data', 'id' => 'plan-permissions',
        're' => '/functional desk roles, (\d[\d,]*) granular permissions/s',
        'paths' => ['permissions']],
];

$docs = [
    $DOCX => ['file' => $flat, 'label' => 'docx'],
    $AGENTS => ['file' => $root.'/AGENTS.md', 'label' => 'AGENTS.md'],
    $BUILD => ['file' => $root.'/Documentation/Build_Plan.md', 'label' => 'Build_Plan.md'],
];
// The mutant harness substitutes copies; without these two flags a planted defect in a fixture document would be judged
// beside the real ones and every case would exit 1 for the state of the tree rather than for the mutation.
foreach ($overrides as $k => $path) {
    if (! isset($docs[$k])) {
        printf("REFUSED: --doc names %s, which this checker does not read (known: %s).\n", $k, implode(', ', array_keys($docs)));
        exit(1);
    }
    $docs[$k]['file'] = $path;
}
if ($only !== []) {
    foreach ($only as $k) {
        if (! isset($docs[$k])) {
            printf("REFUSED: --docs names %s, which is not one of %s.\n", $k, implode(', ', array_keys($docs)));
            exit(1);
        }
    }
    $docs = array_intersect_key($docs, array_flip($only));
}
$texts = [];
foreach ($docs as $key => $d) {
    if (! is_file($d['file'])) {
        printf("REFUSED: %s is not readable, so its claims cannot be judged.\n", $d['label']);
        exit(1);
    }
    $texts[$key] = (string) file_get_contents($d['file']);
}

// A fixture document carries a few sentences, not the whole census, so the run can be scoped to the claims it holds.
// Scoping is by claim id and refuses a name that matches nothing: a typo in a flag must never look like "nothing to
// check", and an out-of-scope claim is reported as such rather than judged absent.
$known = array_column($CLAIMS, 'id');
$known[] = 'headings';
$unknown = array_values(array_diff($onlyClaims, $known));
if ($unknown !== []) {
    printf("REFUSED: --only names no such claim: %s (known: %s)\n", implode(', ', $unknown), implode(', ', $known));
    exit(1);
}
$scoped = $onlyClaims !== [] ? array_flip($onlyClaims) : null;
// The per-table class is named like a claim so a fixture that carries no dictionary headings can leave it out on
// purpose; without that, a two-line fixture would refuse for a class it never claimed to judge.
$judgeHeads = $scoped === null || isset($scoped['headings']);

$DATED = '/\b(?:20\d\d-\d\d-\d\d|(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},?\s+20\d\d|measured on|measured:|at the time|back in|used to|was false|re-read on)\b/i';

$rows = [];
$unbound = 0;
$stale = 0;
$matches = 0;
$history = 0;
$skipped = 0;
$outOfScope = [];

foreach ($CLAIMS as $c) {
    if ($scoped !== null && ! isset($scoped[$c['id']])) {
        $outOfScope[] = $c['id'];

        continue;
    }
    if (! isset($texts[$c['doc']])) {
        if ($scoped !== null && isset($scoped[$c['id']])) {
            printf("REFUSED: --only names %s, but its document is out of this run's --docs scope.\n", $c['id']);
            exit(1);
        }

        continue;
    }
    $label = $docs[$c['doc']]['label'];
    if (! preg_match($c['re'], $texts[$c['doc']], $mm, PREG_OFFSET_CAPTURE)) {
        $unbound++;
        printf("UNBOUND  %-14s %-34s the sentence this claim was read from is not in the document\n", $label, $c['id']);

        continue;
    }
    $lineNo = 1 + substr_count(substr($texts[$c['doc']], 0, (int) $mm[0][1]), "\n");
    // Judge the sentence, not the paragraph: one dated clause beside an undated one does not make the figure history.
    $sentStart = (int) strrpos(substr($texts[$c['doc']], 0, (int) $mm[0][1]), '. ');
    $sentence = substr($texts[$c['doc']], max(0, $sentStart), 300);
    $isHistory = preg_match($DATED, $sentence) === 1;

    $figs = array_slice($mm, 1);
    foreach ($c['paths'] as $i => $path) {
        if ($path === null || ! isset($figs[$i])) {
            continue;
        }
        $claimed = valueOf((string) $figs[$i][0], $WORDS);
        $expect = dig($R, $path);
        if ($claimed === null) {
            $unbound++;
            printf("UNBOUND  %-14s %-34s %s — the figure is written in a form this checker cannot read\n", $label, $c['id'], $path);

            continue;
        }
        if ($expect === null) {
            $unbound++;
            printf("UNBOUND  %-14s %-34s %s — the probe prints no such reading\n", $label, $c['id'], $path);

            continue;
        }
        if ($isHistory) {
            $history++;
            printf("HISTORY  %-14s %-34s %s=%d beside a date, left as recorded\n", $label, $c['id'], $path, $claimed);

            continue;
        }
        if ($ciMode && $c['cls'] === 'data') {
            $skipped++;
            printf("NAMED    %-14s %-34s %s=%d — a count of rows belongs to the database that stated it, not to CI\n",
                $label, $c['id'], $path, $claimed);

            continue;
        }
        if ($claimed === $expect) {
            $matches++;
            printf("%-8s %-14s %-34s line %-6d %s=%d\n", 'MATCHES', $label, $c['id'], $lineNo, $path, $expect);
        } else {
            $stale++;
            printf("STALE    %-14s %-34s line %-6d %s claimed %d, the server answers %d\n",
                $label, $c['id'], $lineNo, $path, $claimed, $expect);
        }
        if ($trace) {
            echo "         sentence: ".trim(preg_replace('/\s+/', ' ', mb_substr($sentence, 0, 170)))."\n";
        }
    }
}

// ---------------------------------------------------------------- the per-table column counts (§24's own dictionary)

$heads = [];
$headLines = $judgeHeads && isset($texts[$DOCX]) ? explode("\n", $texts[$DOCX]) : [];
foreach ($headLines as $line) {
    if (preg_match('/^(?:ROW|P)\s+(\d+):\s+([a-z][a-z0-9_]*)\s*[\x{2013}\x{2014}-]\s*(\d[\d,]*)\s+columns/u', $line, $m) === 1) {
        $heads[(int) $m[1]] = ['table' => $m[2], 'stated' => (int) str_replace(',', '', $m[3])];
    }
}
$headBad = 0;
$headMissing = 0;
foreach ($heads as $rowNo => $h) {
    $expect = $R['perTableColumns'][$h['table']] ?? null;
    if ($expect === null) {
        $headMissing++;
        printf("UNBOUND  docx          per-table %-24s line %-6d no table by that name in the readings\n", $h['table'], $rowNo);

        continue;
    }
    if ($expect !== $h['stated']) {
        $headBad++;
        printf("STALE    docx          per-table %-24s line %-6d stated %d columns, the server holds %d\n",
            $h['table'], $rowNo, $h['stated'], $expect);
    }
}
$unbound += $headMissing;
$stale += $headBad;
printf("\n§24 per-table headings: %d read, %d agree with the server, %d differ, %d name no table.\n",
    count($heads), count($heads) - $headBad - $headMissing, $headBad, $headMissing);
if ($heads === [] && $judgeHeads && isset($texts[$DOCX])) {
    echo "REFUSED: no §24 heading was parsed, so the largest class in this family was judged by nothing.\n";
    exit(1);
}
if ($heads === [] && ! $judgeHeads) {
    echo "  (the per-table heading class is out of this run's --only scope, so it was not judged at all)\n";
}
if ($heads === []) {
    echo "  (the docx is out of this run's scope, so the per-table class was not judged at all)\n";
}
if ($outOfScope !== []) {
    printf("  out of scope for this run: %s\n", implode(', ', $outOfScope));
}

printf("\nreadings taken on %s (%s), school day %s.\n", $surface, $R['asOf'], $R['schoolDay'] ?? '?');
printf("%d sentence figure(s) + %d heading figure(s): %d match, %d stale, %d unbound, %d history, %d named-not-judged.\n",
    $matches + $stale + $history + $skipped, count($heads), $matches, $stale, $unbound, $history, $skipped);

if ($reportOnly) {
    exit(0);
}
if ($stale > 0 || $unbound > 0) {
    printf("\nVERDICT: refuse — %d stale and %d unbound figure(s). A document may state a number, but only beside a reading that answers it.\n",
        $stale, $unbound);
    exit(1);
}
echo "\nVERDICT: every bound ratio, census figure and column count equals what the server answers.\n";
exit(0);
