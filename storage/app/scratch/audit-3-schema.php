<?php

/**
 * Audit lane 3 — the schema against the code that writes it, on live `ems`.
 *
 * Three ways these two can disagree, and each one fails at a different moment:
 *
 *  A. A stored value that the PHP enum has no case for. Laravel's enum cast throws ValueError when
 *     the model is hydrated, so the desk does not see a validation error — it sees a 500 on a row
 *     that has been in the database for months. This is what a restored legacy dump does to a
 *     widened-then-narrowed column.
 *  B. A PHP case the MySQL column does not allow. The write dies in the insert, and only for the
 *     one value nobody has tried to save yet.
 *  C. A NOT NULL column with no default that no write path ever fills. Same class as the
 *     courses.requiresRetentionExam defect found on 2026-10-07: an unchecked checkbox is simply
 *     absent from the payload, so the insert fails, and the screen answers with a stack trace.
 *
 * Nothing is written. Every number here is measured against the live database.
 */

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 3);
config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'ems',
    'database.connections.mysql.username' => 'root', 'database.connections.mysql.password' => '']);
DB::purge('mysql');

// ---- the code side: model -> table -> enum-cast columns -------------------------
$castsByTable = [];
foreach (glob($root.'/app/Models/*.php') as $file) {
    $class = 'App\\Models\\'.basename($file, '.php');
    if (! class_exists($class)) {
        continue;
    }
    try {
        $model = new $class;
    } catch (Throwable) {
        continue;
    }
    if (! method_exists($model, 'getCasts') || ! method_exists($model, 'getTable')) {
        continue;
    }
    $table = $model->getTable();
    foreach ($model->getCasts() as $column => $cast) {
        if (! is_string($cast) || ! enum_exists($cast)) {
            continue;
        }
        $ref = new ReflectionEnum($cast);
        if (! $ref->isBacked()) {
            continue;
        }
        $castsByTable[$table][$column] = [
            'enum' => $cast,
            'cases' => array_map(fn ($c) => (string) $c->value, $cast::cases()),
        ];
    }
}

// ---- the database side -----------------------------------------------------------
$cols = [];
foreach (DB::select('select TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, DATA_TYPE
                     from information_schema.COLUMNS where TABLE_SCHEMA = database()') as $c) {
    $cols[$c->TABLE_NAME][$c->COLUMN_NAME] = $c;
}

$deadReads = [];
$deadWrites = [];
foreach ($castsByTable as $table => $columns) {
    foreach ($columns as $column => $info) {
        $dbCol = $cols[$table][$column] ?? null;
        if ($dbCol === null) {
            $deadReads[] = sprintf('%s.%s  cast to %s but the column is GONE from live ems', $table, $column, $info['enum']);

            continue;
        }
        $allowed = [];
        if (preg_match_all("/'([^']*)'/", $dbCol->COLUMN_TYPE, $am)) {
            $allowed = $am[1];
        }
        $missingInDb = array_diff($info['cases'], $allowed);
        $missingInPhp = array_diff($allowed, $info['cases']);
        if ($allowed !== [] && $missingInDb !== []) {
            $deadWrites[] = sprintf('%-22s %-20s %s  PHP case(s) the column cannot store: %s',
                $table, $column, $info['enum'], implode('/', $missingInDb));
        }

        $stored = DB::table($table)->select($column)->distinct()->pluck($column)->filter(fn ($v) => $v !== null)->all();
        $stray = array_diff(array_map(fn ($v) => (string) $v, $stored), $info['cases']);
        if ($stray !== []) {
            $count = DB::table($table)->whereIn($column, $stray)->count();
            $deadReads[] = sprintf('%-22s %-20s %s  %d row(s) hold value(s) the enum has no case for: %s',
                $table, $column, $info['enum'], $count, implode('/', $stray));
        }
        if ($allowed !== [] && $stored !== []) {
            $unused = array_diff($info['cases'], array_map(fn ($v) => (string) $v, $stored));
            if (count($unused) === count($info['cases'])) {
                $deadWrites[] = sprintf('%-22s %-20s %s  no row uses ANY of its %d case(s) — the cast is untested by the data',
                    $table, $column, $info['enum'], count($info['cases']));
            }
        }
    }
}

// ---- C: NOT NULL, no default, never named by a write path -------------------------
$phpFiles = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app')) as $f) {
    if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
        $phpFiles[$f->getPathname()] = file_get_contents($f->getPathname());
    }
}
// What the validators and create/fill arrays mention, per column name, across app/.
$unwritable = [];
$neverNamed = [];
// Framework-owned tables (cache, queue, sessions, migrations, Spatie's pivots) are written by
// vendor code that never appears in app/. Reporting them as "no write path" is noise, and on the
// first run it was all the section had to say.
$appTables = [];
foreach (glob($root.'/app/Models/*.php') as $file) {
    $class = 'App\\Models\\'.basename($file, '.php');
    if (class_exists($class) && method_exists($class, 'getTable')) {
        $appTables[(new $class)->getTable()] = true;
    }
}
foreach ($cols as $table => $columns) {
    if (! isset($appTables[$table])) {
        continue;
    }
    foreach ($columns as $column => $c) {
        if ($c->IS_NULLABLE !== 'NO' || $c->COLUMN_DEFAULT !== null
            || str_contains((string) $c->EXTRA, 'auto_increment')
            || in_array($column, ['created_at', 'updated_at'], true)) {
            continue;
        }
        $named = 0;
        foreach ($phpFiles as $src) {
            $named += preg_match_all('/[\'"]'.preg_quote($column, '/').'[\'"]/', $src);
        }
        if ($named === 0) {
            $neverNamed[] = sprintf('%-24s %-24s %-14s NOT NULL, no default, and the string never appears in app/', $table, $column, $c->DATA_TYPE);
        } elseif ($named < 2) {
            $unwritable[] = sprintf('%-24s %-24s %-14s named once in app/ — check that a write path sets it (%d)', $table, $column, $c->DATA_TYPE, $named);
        }
    }
}

printf("models with enum casts: %d   enum-cast columns compared: %d   live tables: %d\n",
    count($castsByTable), array_sum(array_map('count', $castsByTable)), count($cols));

echo "\n== A. STORED VALUE THE PHP ENUM CANNOT READ (a 500 on hydrate) ==\n";
echo $deadReads === [] ? "  (none)\n" : '  '.implode("\n  ", $deadReads)."\n";

echo "\n== B. PHP CASE THE MySQL COLUMN CANNOT STORE ==\n";
echo $deadWrites === [] ? "  (none)\n" : '  '.implode("\n  ", $deadWrites)."\n";

echo "\n== C. NOT NULL WITHOUT DEFAULT, NEVER NAMED IN app/ ==\n";
echo $neverNamed === [] ? "  (none)\n" : '  '.implode("\n  ", $neverNamed)."\n";

echo "\n== C2. NAMED EXACTLY ONCE (usually a rule, rarely a write) ==\n";
echo $unwritable === [] ? "  (none)\n" : '  '.implode("\n  ", array_slice($unwritable, 0, 40))."\n";
