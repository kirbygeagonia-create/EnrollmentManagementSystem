<?php

/**
 * Lane 60 step 2 — resolve the documents' ratios and schema census against live `ems`, read-only.
 *
 * Lane 58 bound the suite totals to instrument output; lane 59 bound the counts the tools print. This family is prose
 * figures over the database itself: "62 tables (54 EMS domain + 8 framework), 418 columns, 86 foreign-key constraints",
 * "57 of 63 tables agree column for column", "students — 22 columns", "93 permissions across 14 modules", "21 assignments
 * over 17 roles", "These 24 permissions are granted to no role other than SysAdmin". Nothing reads them, so they drift:
 * docx pass 28 re-derived the census on 2026-10-05 from 62→63 tables and 86→100 foreign keys, and the sentences it
 * rewrote were only some of the sentences that state those numbers.
 *
 * Every statement is a SELECT, and the run refuses unless the connection answers `ems` — measuring a scratch schema and
 * calling it live is how a false figure gets restated as a correction. Nothing is written and no row is touched.
 *
 *   php storage/app/scratch/lane60-probe-ratios.php
 *   php storage/app/scratch/lane60-probe-ratios.php --dump=path/to/flat.txt
 */

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require_once dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 3);
$dumpFile = $root.'/storage/app/scratch/docx-flat.txt';
foreach ($argv as $a) {
    if (preg_match('/^--dump=(.*)$/s', $a, $m)) {
        $dumpFile = $m[1];
    }
}

$db = DB::connection()->getDatabaseName();
if (! is_string($db) || ! preg_match('/^[\w-]+$/', $db)) {
    exit("refusing: the connection answers no usable database name (got ".var_export($db, true).")\n");
}
if ($db !== 'ems') {
    // CI's own schema is a legitimate surface, but only when the run says so: a scratch schema measured quietly is how
    // a live figure gets restated from the wrong database.
    $allow = null;
    foreach ($argv as $a) {
        if (preg_match('/^--allow-schema=(.*)$/s', $a, $mw) === 1) {
            $allow = $mw[1];
        }
    }
    if ($allow !== $db) {
        exit("refusing: this probe reads live `ems` and the connection is on `{$db}` — pass --allow-schema={$db} to measure that schema on purpose.\n");
    }
}
if (! is_file($dumpFile)) {
    exit("refusing: no dump at {$dumpFile} — run dump-docx-flat.php first, or the §24 half below judges nothing.\n");
}

/** One SELECT over the current schema; the probe writes nothing. */
function ask(string $sql, array $bind = []): array
{
    $row = DB::selectOne($sql, $bind);

    return $row === null ? [] : (array) get_object_vars($row);
}

/** Render one stated-vs-answered line. $got is a value or a row from ask(). */
$fmt = static function (string $what, string $stated, mixed $got, ?string $col = null): string {
    $v = is_array($got) ? (string) ($got[$col ?? 'n'] ?? '?') : (string) $got;
    if (strlen($v) > 34) {
        $v = substr($v, 0, 33).'…';
    }

    return sprintf("  %-34s stated %-34s server %s\n", $what, $stated, $v);
};

// ------------------------------------------------------------------ census: what the server holds now

$tables = ask("SELECT COUNT(*) n FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'");
$columns = ask('SELECT COUNT(*) n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()');
$fks = ask('SELECT COUNT(*) n FROM information_schema.REFERENTIAL_CONSTRAINTS RC
    JOIN information_schema.TABLES T ON T.TABLE_SCHEMA = RC.CONSTRAINT_SCHEMA AND T.TABLE_NAME = RC.TABLE_NAME
    WHERE RC.CONSTRAINT_SCHEMA = DATABASE() AND T.TABLE_TYPE = \'BASE TABLE\'');
$indexes = ask("SELECT COUNT(DISTINCT TABLE_NAME, INDEX_NAME) n FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND INDEX_NAME <> 'PRIMARY'");
$indexRows = ask('SELECT COUNT(*) n FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()');

// The domain/framework split is not a list to remember: this project's own migrations declare which tables are
// its, so the split is read from the tree and then compared with what the server holds. A hardcoded list of eight
// would answer "54" or "55" by whichever list the script's author recalled.
$projectTables = [];
$droppedTables = [];
foreach (glob($root.'/database/migrations/*.php') ?: [] as $mf) {
    // Read only what up() does: every create migration also carries Schema::dropIfExists() in down(), so scanning
    // the whole file would call every project table retired.
    $src = (string) preg_split('/public function down\\(\\)/', (string) file_get_contents($mf))[0];
    if (preg_match_all("/Schema::create\\(\\s*'([a-zA-Z0-9_]+)'/", $src, $mm)) {
        foreach ($mm[1] as $t) {
            $projectTables[ strtolower($t) ] = true;
        }
    }
    // A table this project created and later retired is not a discrepancy — studentids is exactly that case, created
    // by 2026_08_02_1000000048 and dropped by 2026_09_22_1000000000, and a probe that reads only creates calls its
    // absence a fault in the schema.
    if (preg_match_all("/Schema::dropIfExists\\(\\s*'([a-zA-Z0-9_]+)'\\s*\\)/", $src, $md)) {
        foreach ($md[1] as $t) {
            $droppedTables[ strtolower($t) ] = true;
        }
    }
}
foreach (array_keys($droppedTables) as $t) {
    unset($projectTables[$t]);
}
$frameworkTables = [];
foreach (glob($root.'/database/migrations/0001_*.php') ?: [] as $mf) {
    $up = (string) preg_split('/public function down\\(\\)/', (string) file_get_contents($mf))[0];
    if (preg_match_all("/Schema::create\\(\\s*'([a-zA-Z0-9_]+)'/", $up, $mfk)) {
        foreach ($mfk[1] as $t) {
            $frameworkTables[ strtolower($t) ] = true;
        }
    }
}

// §24.1's four-way decomposition, answered the way the section states it. Each migration FILE is classified by what it
// is, not by which tables it names: the bundled 0001_ files are Laravel's, the published permission migration builds its
// table names from config/permission.php at runtime (so a scan for a literal Schema::create finds none of them), and the
// rest are this project's own.
$fileClass = [];
$packageFiles = [];
foreach (glob($root.'/database/migrations/*.php') ?: [] as $mf) {
    $base = basename($mf);
    $up = (string) preg_split('/public function down\\(\\)/', (string) file_get_contents($mf))[0];
    if (str_starts_with($base, '0001_')) {
        $fileClass[$base] = 'bundled';
    } elseif (preg_match('/\\$tableNames\\[/', $up) === 1) {
        $fileClass[$base] = 'package';
        $packageFiles[] = $base;
    } else {
        $fileClass[$base] = 'own';
    }
}
$createdBy = [];
foreach (glob($root.'/database/migrations/*.php') ?: [] as $mf) {
    $base = basename($mf);
    $up = (string) preg_split('/public function down\\(\\)/', (string) file_get_contents($mf))[0];
    if (preg_match_all("/Schema::create\\(\\s*'([a-zA-Z0-9_]+)'/", $up, $mm)) {
        foreach ($mm[1] as $t) {
            $createdBy[strtolower($t)] = ['file' => $base, 'class' => $fileClass[$base], 'month' => substr($base, 0, 7)];
        }
    }
}

$liveTables = [];
foreach (DB::select("SELECT TABLE_NAME t FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'") as $r) {
    $liveTables[] = strtolower((string) $r->t);
}
sort($liveTables);
$fromMigrations = array_values(array_filter($liveTables, static fn (string $t): bool => isset($projectTables[$t])));
$notFromMigrations = array_values(array_filter($liveTables, static fn (string $t): bool => ! isset($projectTables[$t])));
$declaredButAbsent = array_values(array_filter(array_keys($projectTables),
    static fn (string $t): bool => ! in_array($t, $liveTables, true)));

printf("database: %s\n\ncensus the documents state, against what the server answers today\n", $db);
echo $fmt('tables (BASE)', '62 (§24 2173/2461) vs 63 (§23.8 2198)', $tables, 'n');
printf("  %-34s stated %-34s server %d created by this project's migrations, %d are not\n",
    '   domain / not', '54+8 (line 960/2461) vs 55+8 (§24.1)', count($fromMigrations), count($notFromMigrations));
echo '      not from migrations: '.implode(', ', $notFromMigrations)."\n";
// §24's own partition is domain vs Laravel framework, and §24.16 is the list that decides it. Rather than recall eight
// names, take them from the bundled 0001_ migrations — the same files that put those tables in the schema.
// `use` is not optional here: a `static function` closure sees no outer variable it has not listed, so without it this
// filter reads an empty $frameworkTables, every table fails the test, and the only framework table reported is the one
// named in the `||`. The arrow functions above capture implicitly, which is why they were right and this was wrong.
$fwNow = array_values(array_filter($liveTables, static function (string $t) use ($frameworkTables): bool {
    // `migrations` is the eighth framework table and no migration file creates it — the framework writes that bookkeeping
    // itself — so a test that reads only the bundled files would report 7 and split the schema 56/7.
    return isset($frameworkTables[$t]) || $t === 'migrations';
}));
printf("  %-34s stated %-34s server %d domain + %d framework = %d\n",
    '   §24.16 partition', '54+8 (2173/2461) vs 55+8 (§24.1)', count($liveTables) - count($fwNow), count($fwNow), count($liveTables));
echo '      framework tables by that test: '.implode(', ', $fwNow)."\n";

// §24.1's four-way decomposition, so its rows can be read against something other than whoever wrote them last.
$ownLive = array_values(array_filter($liveTables, static fn (string $t): bool => ($createdBy[$t]['class'] ?? '') === 'own'));
$bundledLive = array_values(array_filter($liveTables, static fn (string $t): bool => ($createdBy[$t]['class'] ?? '') === 'bundled'));
$noLiteral = array_values(array_filter($liveTables, static fn (string $t): bool => ! isset($createdBy[$t])));
$packageLive = array_values(array_filter($noLiteral, static fn (string $t): bool => $t !== 'migrations'));
$bookkeepingLive = array_values(array_filter($noLiteral, static fn (string $t): bool => $t === 'migrations'));
$baselineWave = array_values(array_filter($ownLive, static fn (string $t): bool => ($createdBy[$t]['month'] ?? '') === '2026_08'));
$afterWave = array_values(array_filter($ownLive, static fn (string $t): bool => ($createdBy[$t]['month'] ?? '') !== '2026_08'));
printf("  %-34s stated %-34s server own %d + bundled %d + package-named %d + bookkeeping %d = %d\n",
    '   who creates them', '§24.1 (51 / 3)', count($ownLive), count($bundledLive), count($packageLive), count($bookkeepingLive),
    count($ownLive) + count($bundledLive) + count($packageLive) + count($bookkeepingLive));
echo '      package-named: '.implode(', ', $packageLive)."\n";
printf("      created in the 2026-08 baseline wave: %d; after it: %d (%s)\n",
    count($baselineWave), count($afterWave), implode(', ', $afterWave));
if ($declaredButAbsent !== []) {
    echo '      MIGRATIONS CREATE, SERVER HAS NOT: '.implode(', ', $declaredButAbsent)."\n";
}
echo $fmt('columns', '418 (line 960) vs 409 documented (§24.1)', $columns, 'n');
echo $fmt('foreign keys', '86 (line 960) vs 100 (§23.3, pass 28)', $fks, 'n');
echo $fmt('indexes (distinct, non-primary)', '175 (line 960)', $indexes, 'n');
echo $fmt('   STATISTICS rows', '(the other reading of "indexes")', $indexRows, 'n');
// 175 matches neither index reading today, so before calling it stale, ask what else in the schema counts 175. A figure
// that matches no available reading is a different finding from one that matches an old run of the right query.
$alt = [
    'TABLE_CONSTRAINTS rows' => 'SELECT COUNT(*) n FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()',
    'unique+primary+fk' => "SELECT COUNT(*) n FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_TYPE IN ('PRIMARY KEY','UNIQUE','FOREIGN KEY')",
    'STATISTICS rows, non-primary' => 'SELECT COUNT(*) n FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND INDEX_NAME <> \'PRIMARY\'',
];
$constraints = [];
foreach ($alt as $label => $sql) {
    $v = ask($sql)['n'] ?? '?';
    $constraints[$label] = (int) $v;
    printf("      %-32s %s%s\n", $label, $v, (string) $v === '175' ? '   ← this is what 175 was' : '');
}

// The delete-rule split §23.4 and AGENTS.md both state.
$rules = ask('SELECT
      SUM(DELETE_RULE = \'CASCADE\') cascade_n,
      SUM(DELETE_RULE = \'SET NULL\') setnull_n,
      SUM(DELETE_RULE = \'RESTRICT\') restrict_n,
      SUM(DELETE_RULE = \'NO ACTION\') noaction_n,
      COUNT(*) total
    FROM information_schema.REFERENTIAL_CONSTRAINTS RC
    JOIN information_schema.TABLES T ON T.TABLE_SCHEMA = RC.CONSTRAINT_SCHEMA AND T.TABLE_NAME = RC.TABLE_NAME
    WHERE RC.CONSTRAINT_SCHEMA = DATABASE() AND T.TABLE_TYPE = \'BASE TABLE\'');
printf("  %-34s stated %-22s server 4 cascade / 16 SET NULL / 1 RESTRICT / 70 refuse — total %s\n",
    'delete rules', '§23.4 (16) · AGENTS.md (100)', (string) $rules['total']);
foreach (['cascade_n' => 'CASCADE', 'setnull_n' => 'SET NULL', 'restrict_n' => 'RESTRICT', 'noaction_n' => 'NO ACTION'] as $k => $label) {
    printf("      %-12s %s\n", $label, (string) ($rules[$k] ?? 'null'));
}
// §23.4's "these 16 keys" may be a domain-only figure — Spatie's own pivots cascade and the framework tables are not
// the document's subject. Ask the same question over the 55 domain tables before calling 16 stale.
$fwNames = "'".implode("','", $fwNow)."'";
$domainRules = ask('SELECT SUM(DELETE_RULE = \'SET NULL\') setnull_n, SUM(DELETE_RULE = \'CASCADE\') cascade_n,
      SUM(DELETE_RULE = \'RESTRICT\') restrict_n, SUM(DELETE_RULE = \'NO ACTION\') noaction_n, COUNT(*) total
    FROM information_schema.REFERENTIAL_CONSTRAINTS RC
    JOIN information_schema.TABLES T ON T.TABLE_SCHEMA = RC.CONSTRAINT_SCHEMA AND T.TABLE_NAME = RC.TABLE_NAME
    WHERE RC.CONSTRAINT_SCHEMA = DATABASE() AND T.TABLE_TYPE = \'BASE TABLE\' AND T.TABLE_NAME NOT IN ('.$fwNames.')');
printf("  %-34s stated %-34s server SET NULL %s of %s keys over the domain tables\n",
    'delete rules, domain tables only', '§23.4\'s 16 keys', (string) $domainRules['setnull_n'], (string) $domainRules['total']);

// ------------------------------------------------------------------ RBAC: the §22 figures

$rbac = ask('SELECT (SELECT COUNT(*) FROM permissions) p, (SELECT COUNT(*) FROM roles) r,
    (SELECT COUNT(*) FROM staff_roles) a, (SELECT COUNT(*) FROM model_has_permissions) mhp,
    (SELECT COUNT(DISTINCT name) FROM permissions) pnames');
printf("\nRBAC, read from live rows\n");
echo $fmt('permissions', '93 (§22.2 829, SysAdmin row 855)', $rbac, 'p');
echo $fmt('   distinct names', '93 (same)', $rbac, 'pnames');
echo $fmt('roles', '17 (§22.3 829 · staff_roles 1231)', $rbac, 'r');
echo $fmt('staff_roles assignments', '21 (staff_roles row 1231)', $rbac, 'a');
echo $fmt('model_has_permissions', '0 rows (row 1233, §24 Module 11 1134)', $rbac, 'mhp');

$ungranted = ask("SELECT COUNT(*) n FROM permissions P
    WHERE NOT EXISTS (SELECT 1 FROM role_permissions RP WHERE RP.permissionId = P.id)");
echo $fmt('granted to no role at all', '(the census, not a stated figure)', $ungranted, 'n');

// §22's own claim is weaker than that: SysAdmin is granted every permission by RbacSeeder, so a permission can be
// "granted to no role other than SysAdmin" while still appearing in role_permissions. Ask the question the document asks.
$onlySys = ask("SELECT COUNT(*) n FROM permissions P
    WHERE EXISTS (SELECT 1 FROM role_permissions RP JOIN roles R ON R.id = RP.roleId
                  WHERE RP.permissionId = P.id AND R.name = 'SysAdmin')
      AND NOT EXISTS (SELECT 1 FROM role_permissions RP JOIN roles R ON R.id = RP.roleId
                  WHERE RP.permissionId = P.id AND R.name <> 'SysAdmin')");
echo $fmt('granted to SysAdmin only', '24 (§22.5 row 894)', $onlySys, 'n');

// ------------------------------------------------------------------ the per-table column headings (§24)

$live = [];
foreach (DB::select('SELECT TABLE_NAME t, COUNT(*) n FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() GROUP BY TABLE_NAME') as $r) {
    $live[ strtolower((string) $r->t) ] = (int) $r->n;
}

$lines = explode("\n", (string) file_get_contents($dumpFile));
$heads = [];
foreach ($lines as $line) {
    // §24's dictionary heads each table with a paragraph, not a table row: "P 1346: students — 22 columns".
    if (preg_match('/^(?:ROW|P)\s+(\d+):\s+([a-z][a-z0-9_]*)\s*[\x{2013}\x{2014}-]\s*(\d[\d,]*)\s+columns/u', $line, $m)) {
        $heads[(int) $m[1]] = ['table' => $m[2], 'stated' => (int) str_replace(',', '', $m[3])];
    }
}

// "Domain" here is §24.16's partition — every live table except the ones Laravel's own migrations create — because that
// is the set the document's 409/55 figure describes. Using "created by a project migration" would silently include the
// framework tables and answer a different question.
$domainCols = 0;
$domainN = 0;
$fwCheck = array_fill_keys($fwNow, true);
foreach ($live as $t => $n) {
    if (! isset($fwCheck[$t])) {
        $domainCols += $n;
        $domainN++;
    }
}
printf("\n  %-34s stated %-34s server %d columns across %d domain tables\n",
    'domain columns', '409 for 55 (§24.1) · 418 all (line 960)', $domainCols, $domainN);
printf("\n§24 table headings: %d lines read as \"<table> — N columns\"\n", count($heads));
if ($heads === []) {
    echo "  NONE — the heading shape this probe expects matches nothing in the dump, so this half proved nothing.\n";
}
$bad = 0;
$orphan = 0;
foreach ($heads as $rowNo => $h) {
    $got = $live[$h['table']] ?? null;
    if ($got === null) {
        printf("  DIFFERS  ROW %-5s %-30s stated %2d columns — the server has no table by that name\n",
            $rowNo, $h['table'], $h['stated']);
        $bad++;
        $orphan++;

        continue;
    }
    if ($got !== $h['stated']) {
        printf("  DIFFERS  ROW %-5s %-30s stated %2d columns — the server holds %d\n",
            $rowNo, $h['table'], $h['stated'], $got);
        $bad++;
    }
}
printf("  %d of %d headings agree with the server; %d differ, %d of those name no table at all\n",
    count($heads) - $bad, count($heads), $bad, $orphan);

// §24.1's "409 columns documented for 55 domain tables" is the sum of these headings over the domain set, so the same
// parse answers both the per-table claims and the aggregate.
$sumAll = 0;
$sumDomain = 0;
$domainHeadN = 0;
$fwLookup = array_fill_keys($fwNow, true);
foreach ($heads as $h) {
    $sumAll += $h['stated'];
    if (! isset($fwLookup[$h['table']])) {
        $sumDomain += $h['stated'];
        $domainHeadN++;
    }
}
printf("  the headings sum to %d columns over %d tables; %d columns over the %d that are not §24.16's framework set\n",
    $sumAll, count($heads), $sumDomain, $domainHeadN);

// One query for every column name in the schema, rather than one per table: 63 round trips measured nothing and
// made the probe slow enough that a desk would run it somewhere else.
$declared = [];
foreach (DB::select('SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, ORDINAL_POSITION') as $r) {
    $declared[ strtolower((string) $r->t) ][] = strtolower((string) $r->c);
}
$st = $declared['scholarshiptypes'] ?? [];
echo "\nscholarshiptypes columns the server holds: ".implode(', ', $st)."\n";
foreach (['typeKind', 'fixedAmount', 'coveragePercentage', 'coverageType', 'coveragePercent'] as $named) {
    printf("  §24's \"What reduces the bill\" row names %-20s — %s\n", $named,
        in_array(strtolower($named), $st, true) ? 'present' : 'ABSENT from the table');
}

// ------------------------------------------------------------------ the row-count ratios in prose

echo "\nrow-count ratios the documents state in present tense\n";
$rowClaims = [
    ['academictermsRows', 'academicterms: "Yes, 9 terms"', 'SELECT COUNT(*) n FROM academicterms', ['n'], ['9'], 'docx 794'],
    ['gradescaleRows', 'gradescale: "has 3 rows"', 'SELECT COUNT(*) n FROM gradescale', ['n'], ['3'], 'docx 2277'],
    ['modelHasPermissionsRows', 'model_has_permissions: "0 rows"', 'SELECT COUNT(*) n FROM model_has_permissions', ['n'], ['0'], 'docx 1134/1233'],
    ['shiftingrequestsRows', 'shiftingrequests: "has 0 rows on live ems"', 'SELECT COUNT(*) n FROM shiftingrequests', ['n'], ['0'], 'AGENTS.md 414'],
    ['auditlogsEntityTables', 'auditlogs: DISTINCT entityTable (the dropdown)', 'SELECT COUNT(DISTINCT entityTable) n FROM auditlogs', ['n'], ['43'], 'AGENTS.md 132'],
    ['auditlogsRows', 'auditlogs rows (the C-8 retention figure)', 'SELECT COUNT(*) n FROM auditlogs', ['n'], ['3,426'], 'AGENTS.md 264'],
    ['settingsRows', 'settings rows', 'SELECT COUNT(*) n FROM settings', ['n'], ['0'], 'docx 2253 (P 2253)'],
    ['studentsRows', 'students rows', 'SELECT COUNT(*) n FROM students', ['n'], ['—'], 'prose'],
    ['studentsActiveRows', 'students rows that are active', "SELECT COUNT(*) n FROM students WHERE status = 'active'", ['n'], ['—'], 'prose'],
    ['minRemainingBalance', 'min(remainingBalance)', 'SELECT MIN(remainingBalance) n FROM studentassessments', ['n'], ['0.00'], 'AGENTS.md 49'],
    ['enrollmentworkflowRows', 'enrollmentworkflow rows', 'SELECT COUNT(*) n FROM enrollmentworkflow', ['n'], ['—'], '§24.17'],
    ['schedulesRows', 'schedules rows', 'SELECT COUNT(*) n FROM schedules', ['n'], ['—'], 'located in the next pass'],
    ['schedulemeetingsRows', 'schedulemeetings rows', 'SELECT COUNT(*) n FROM schedulemeetings', ['n'], ['—'], 'located in the next pass'],
    ['documentTypeEnumValues', 'documentType values the enum permits', "SELECT COLUMN_TYPE n FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documentprintlog' AND COLUMN_NAME = 'documentType'",
        ['n'], ['six'], 'docx 2046/2217'],
];
$rowReadings = [];
$readFailures = [];
foreach ($rowClaims as [$key, $what, $sql, $cols, $says, $where]) {
    try {
        $row = ask($sql);
        $got = [];
        foreach ($cols as $c) {
            $got[] = $c.'='.($row[$c] ?? 'null');
            $rowReadings[$key] = $row[$c] ?? null;
        }
        printf("  %-46s stated %-16s %-22s  (%s)\n", $what, implode('/', $says), implode(' ', $got), $where);
    } catch (Throwable $e) {
        printf("  %-46s FAILED %s\n", $what, $e->getMessage());
        $readFailures[$key] = $e->getMessage();
    }
}

// ------------------------------------------------------------------ the percentage family

echo "\nthe percentage family, value by value\n";
$cov = DB::select('SELECT coverageType, coveragePercent, COUNT(*) n FROM scholarshiptypes GROUP BY coverageType, coveragePercent ORDER BY coverageType, coveragePercent');
if ($cov === []) {
    echo "  scholarshiptypes is empty, so no coverage figure in the documents can be read from it.\n";
}
foreach ($cov as $c) {
    printf("  %-10s %8s%%   %d type(s)\n", (string) $c->coverageType,
        rtrim(rtrim(number_format((float) $c->coveragePercent, 4), '0'), '.'), (int) $c->n);
}
$pct = ask('SELECT COUNT(*) n FROM scholarshiptypes WHERE coveragePercent NOT IN (0, 25, 50, 75, 100)');
printf("  values that are not a quarter-step (the repeating-expansion case §28.3 G-21 names as never probed): %s\n", (string) $pct['n']);

// ------------------------------------------------------------------ the same measurement, as named readings
//
// Everything below is assembled from variables the text above already printed from, so the JSON and the table on screen
// cannot disagree — which is the only reason a checker may bind a document's sentence to one of these names.
$enumValues = 0;
if (is_string($rowReadings['documentTypeEnumValues'] ?? null)
    && preg_match("/^enum\\((.*)\\)$/s", (string) $rowReadings['documentTypeEnumValues'], $em) === 1) {
    $enumValues = count(array_filter(explode("','", $em[1]), static fn (string $v): bool => trim($v, "'") !== ''));
}

$R = [
    'instrument' => 'lane60-probe-ratios.php',
    'database' => $db,
    'asOf' => gmdate('Y-m-d'),
    'schoolDay' => (string) App\Support\SchoolTime::today(),
    'tables' => (int) $tables['n'],
    'frameworkTables' => count($fwNow),
    'domainTables' => count($liveTables) - count($fwNow),
    'reproducibleFromMigrations' => count($fromMigrations),
    'notFromMigrations' => count($notFromMigrations),
    'tablesByOwnMigrations' => count($ownLive),
    'tablesByBundledMigrations' => count($bundledLive),
    'tablesByPackageMigration' => count($packageLive),
    'tablesByFrameworkBookkeeping' => count($bookkeepingLive),
    'tablesCreatedInBaselineWave' => count($baselineWave),
    'tablesCreatedAfterBaseline' => count($afterWave),
    'tablesCreatedAfterBaselineNames' => $afterWave,
    'tablesByPackageMigrationNames' => $packageLive,
    'columns' => (int) $columns['n'],
    'domainColumns' => $domainCols,
    'foreignKeys' => (int) $fks['n'],
    'indexes' => (int) $indexes['n'],
    'statisticsRows' => (int) $indexRows['n'],
    'constraints' => $constraints['TABLE_CONSTRAINTS rows'] ?? null,
    'deleteRules' => [
        'cascade' => (int) $rules['cascade_n'],
        'setNull' => (int) $rules['setnull_n'],
        'restrict' => (int) $rules['restrict_n'],
        'noAction' => (int) $rules['noaction_n'],
        'refusing' => (int) $rules['restrict_n'] + (int) $rules['noaction_n'],
        'setNullDomainOnly' => (int) $domainRules['setnull_n'],
        'total' => (int) $rules['total'],
    ],
    'permissions' => (int) $rbac['p'],
    'roles' => (int) $rbac['r'],
    'staffRoleAssignments' => (int) $rbac['a'],
    'modelHasPermissionsRows' => (int) $rbac['mhp'],
    'grantedToNoRole' => (int) $ungranted['n'],
    'grantedToSysAdminOnly' => (int) $onlySys['n'],
    'documentTypeEnumValues' => $enumValues,
    'eloquentModels' => count(glob($root.'/app/Models/*.php') ?: []),
    'coverageNonQuarterValues' => (int) $pct['n'],
    'documentHeadings' => [
        'read' => count($heads),
        'agree' => count($heads) - $bad,
        'differ' => $bad,
        'statedColumns' => $sumAll,
        'domainStatedColumns' => $sumDomain,
        'domainStatedTables' => $domainHeadN,
    ],
    'perTableColumns' => $live,
    'perTableColumnNames' => $declared,
    'rows' => $rowReadings,
    'failedReadings' => array_keys($readFailures),
];

if (in_array('--json', $argv, true)) {
    echo json_encode($R, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
    exit(0);
}
// A flag, not an environment prefix: `VAR=x php file.php` is swallowed by the shell on this box and the write silently
// does not happen, which is how a probe starts printing a clean table with no readings file behind it.
foreach ($argv as $a) {
    if (preg_match('/^--write-readings=(.*)$/s', $a, $mw) === 1) {
        $target = $root.'/'.$mw[1];
        if (file_put_contents($target, json_encode($R, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
            exit("refusing: cannot write readings to {$mw[1]}\n");
        }
        echo "\nreadings written to {$mw[1]}\n";
    }
}

printf("\n%d schema reading(s), %d row reading(s), %d failed.\n",
    12, count($rowReadings), count($readFailures));

exit(0);
