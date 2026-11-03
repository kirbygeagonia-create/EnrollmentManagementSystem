<?php

/**
 * Read-only census: does any desk action that JOINS a table for its order also filter that action's rows by a bare
 * column name that BOTH sides of the join hold?
 *
 * Found by the academic term list: `academicyears` carries `startDate` and `endDate`, the School Year header joins
 * that table, and the Active/Inactive tabs asked for those columns UNqualified — so live `ems` answered
 * "Column 'startDate' in where clause is ambiguous" (a 500 at the desk) and the suite's SQLite refuses with
 * "ambiguous column name: startDate". Neither face is reachable by a case that only ever clicks a header, which is
 * why this census asks the whole tree instead of stopping at the screen that produced the finding.
 *
 * The hop's own `local` names the base table, so the question is answered from the schema rather than by reading:
 * a bare name is AMBIGUOUS exactly when the joined table and the base table both hold it. A name the joined table
 * holds alone is printed as resolving, because a search that reaches a hop by a bare column is this tree's ordinary
 * shape, not a defect.
 *
 * Usage: php storage/app/scratch/lane224-census-join-ambiguity.php [--allow-live]
 */

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$database = DB::connection()->getDatabaseName();
if ($database === 'ems' && ! in_array('--allow-live', array_slice($GLOBALS['argv'], 1), true)) {
    fwrite(STDERR, "REFUSED: this reads the schema of live `{$database}`; pass --allow-live to say so deliberately.\n");
    exit(1);
}

$columnsOf = static function (string $table) use ($database): array {
    $rows = DB::select(
        'select COLUMN_NAME from information_schema.COLUMNS where TABLE_SCHEMA = ? and TABLE_NAME = ?',
        [$database, $table]
    );

    return array_map(static fn (object $r): string => (string) $r->COLUMN_NAME, $rows);
};

$colCache = [];
$cols = static function (string $table) use (&$colCache, $columnsOf): array {
    if (! isset($colCache[$table])) {
        $colCache[$table] = $columnsOf($table);
    }

    return $colCache[$table];
};

$walk = static function (string $dir): Generator {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
            yield $f->getPathname();
        }
    }
};

$actions = 0;
$withHop = 0;
$hits = [];
$resolves = [];
$blind = [];

foreach ($walk($root.'/app/Http/Controllers') as $file) {
    $body = (string) file_get_contents($file);
    preg_match_all('/\n    (?:public|private|protected) function (\w+)\(/', $body, $sm, PREG_OFFSET_CAPTURE);
    $marks = $sm[1];
    foreach ($marks as $i => $mark) {
        $name = (string) $mark[0];
        $from = (int) $mark[1];
        $next = $i + 1 < count($marks) ? (int) $marks[$i + 1][1] : strlen($body);
        $chunk = substr($body, $from, $next - $from);
        if (! str_contains($chunk, 'ListSort::apply(')) {
            continue;
        }
        $actions++;

        // One pattern answers both spellings: a single `join`/`foreign`/`local` triple, and a `joins` list whose
        // every hop is written the same way.
        preg_match_all("/'join' => '([a-z_]+)'/i", $chunk, $jm);
        $joined = array_values(array_unique($jm[1]));
        preg_match_all("/'local' => '([a-z_]+)\.([a-zA-Z_]+)'/i", $chunk, $lm);
        $bases = array_values(array_unique($lm[1]));
        if ($joined === []) {
            continue;
        }
        $withHop++;
        if ($bases === []) {
            $blind[] = basename($file).'::'.$name.' — no `local` named a base table, so the intersection cannot be weighed';

            continue;
        }

        $baseCols = [];
        foreach ($bases as $base) {
            foreach ($cols($base) as $c) {
                $baseCols[$c] = true;
            }
        }

        // Only BARE names are collected: a qualified `table.column` cannot be ambiguous, so it is not a candidate.
        preg_match_all("/->(where|whereNull|whereNotNull|whereIn|whereDate|whereBetween|orderBy)\(\s*'([a-zA-Z_]+)'/", $chunk, $wm, PREG_SET_ORDER);
        $bare = [];
        foreach ($wm as $w) {
            $bare[$w[2]] = true;
        }

        foreach ($joined as $table) {
            $tableCols = $cols($table);
            if ($tableCols === []) {
                $blind[] = basename($file).'::'.$name.' — the schema answered no columns for `'.$table.'`';

                continue;
            }
            foreach (array_keys($bare) as $col) {
                if (! in_array($col, $tableCols, true)) {
                    continue;
                }
                if (isset($baseCols[$col])) {
                    $hits[] = [basename($file), $name, $table, $col, implode(',', $bases)];
                } else {
                    $resolves[] = [basename($file), $name, $table, $col];
                }
            }
        }
    }
}

printf("schema: %s\nactions calling ListSort::apply: %d   of which at least one joins a table: %d\n\n",
    $database, $actions, $withHop);

if ($actions === 0 || $withHop === 0) {
    fwrite(STDERR, "REFUSED: the walk read {$actions} wired action(s) and {$withHop} with a hop — the census judged nothing.\n");
    exit(1);
}

foreach ($hits as [$file, $action, $table, $col, $base]) {
    printf("  AMBIGUOUS  %-32s %-24s bare `%s` is held by the joined `%s` AND by the base (%s)\n",
        $file, $action, $col, $table, $base);
}
foreach ($resolves as [$file, $action, $table, $col]) {
    printf("  resolves   %-32s %-24s bare `%s` belongs only to the joined `%s`\n", $file, $action, $col, $table);
}
foreach ($blind as $b) {
    printf("  BLIND   %s\n", $b);
}

printf("\n%d AMBIGUOUS, %d resolving uniquely, %d blind reading(s) over %d hopped action(s).\n",
    count($hits), count($resolves), count($blind), $withHop);
echo "AMBIGUOUS means both sides of the hop hold the column the action names bare, which is a 500 on MySQL and an "
    ."ambiguous-column refusal on SQLite the moment a header (or its fallback) adds the join.\n";
exit($blind === [] && $hits === [] ? 0 : 1);
