<?php

/**
 * Read-only plan for the reference-data half of §28.3 G-40.
 *
 * Fourteen `Admin/ReferenceData/*` pages render the shared `DataTable`, and each is served by its own action in
 * `ReferenceDataController`. Before any of them is wired, the question is per column, not per screen: is the value
 * a column of the table the action queries, a hop away, or painted from something the query never holds (a joined
 * label, a count, a formatter)? A header may only be offered for the first two, and the withheld ones are the
 * finding — the same rule the desk queues already follow.
 *
 * This prints, for each page: the columns it declares, and for each column whether that NAME appears as a column in
 * ANY migration table this scan read. That is the unit, and it is deliberately narrower than a wiring plan: the
 * scan does not yet bind a name to the table the screen's own action queries, so `subjectName -> subjects` beside the
 * Courses screen says the word exists, not that `courses` holds it. Binding each column to the queried model is
 * `lane90-probe-pagination.php`'s page-to-action-to-prop method, and until a screen's rows expression is bound that
 * way no header may be offered from this output. Nothing here is judged about *order*.
 *
 * The tool refuses twice over. It refuses when the migration scan read so few tables that the map was never built,
 * and it refuses when the server holds a table the map never read — naming each one, because a column of an unmapped
 * table cannot be told from a computed label and every withholding would be a guess. When no database is reachable it
 * prints `server surface: NOT JUDGED` instead of pretending the second check ran, which is the rule every other
 * instrument here learned the hard way. The first version had only the floor, and its docblock claimed the schema
 * comparison: an accepted-and-inert promise, which reads as a guard while nothing is guarded.
 */
$root = dirname(__DIR__, 3);
$pages = glob($root.'/resources/js/Pages/Admin/ReferenceData/*.jsx');
sort($pages);

// Column names per table, read out of the migrations — from BOTH spellings. A column added later by
// `Schema::table('courses', …)` is on the table exactly as much as one declared in its create block, and a plan
// that reads only `create` will withhold a sortable column and then record the withholding as the finding.
// Measured on the first run of this file: it called `requiresCourseSpecificExam` and
// `entranceExamExemptsTransferee` "not a column anywhere this scan read" for the Courses screen, while
// `SHOW COLUMNS FROM courses` on live `ems` answers both as tinyint(1) — they arrive in the 2026-10-01 and
// 2026-10-07 add-column migrations. Each block is bounded at the NEXT `Schema::` call in the same `up()` half,
// so a file that creates one table and then alters another cannot lend the first table the second one's columns
// (the previous version took `substr($up, $start)` to the end of the half, which is the wrong-target shape).
$tableCols = [];
foreach (glob($root.'/database/migrations/*.php') as $m) {
    if (str_contains(basename($m), 'down')) {
        continue;
    }
    $src = (string) file_get_contents($m);
    $up = preg_split('/public function down\(/', $src)[0];
    if (preg_match_all('/Schema::(create|table)\(\s*[\'"]([a-zA-Z_]+)[\'"]/', $up, $sm, PREG_OFFSET_CAPTURE) === 0) {
        continue;
    }
    foreach ($sm[2] as $i => $nameMatch) {
        $tableName = $nameMatch[0];
        $start = $nameMatch[1];
        $end = $sm[0][$i + 1][1] ?? strlen($up);
        $body = substr($up, $start, $end - $start);
        if (! preg_match_all('/\$table->([a-zA-Z]+)\(\s*[\'"]([a-zA-Z_]+)[\'"]/', $body, $cn)) {
            continue;
        }
        $added = $cn[2];
        // `->dropColumn('x')` and `->dropColumn(['x','y'])` in the same block: a column this migration retires
        // was never on the table for a screen to sort by. Counting it would invent a sortable column, which is
        // the accepted-but-inert flag defect arriving in a column map.
        $dropped = [];
        if (preg_match_all('/\$table->dropColumn\(([^)]*)\)/', $body, $dm)) {
            foreach ($dm[1] as $argList) {
                if (preg_match_all('/[\'"]([a-zA-Z_]+)[\'"]/', $argList, $q)) {
                    $dropped = array_merge($dropped, $q[1]);
                }
            }
        }
        $tableCols[$tableName] = array_merge($tableCols[$tableName] ?? [], array_values(array_diff($added, $dropped)));
    }
}

foreach ($pages as $p) {
    $rel = str_replace($root.DIRECTORY_SEPARATOR, '', $p);
    $name = basename($p, '.jsx');
    if ($name === 'Index') {
        continue;
    }
    $lines = file($p);
    $cols = [];
    $in = false;
    foreach ($lines as $i => $line) {
        $t = trim($line);
        if (preg_match('/const columns\s*=/', $t)) {
            $in = true;
        }
        if ($in && preg_match("/^\s*\{ ?key: ?'([^']+)'/", $t, $m)) {
            $cols[] = $m[1];
        }
        if ($in && preg_match('/^\s*\],?\s*\);/', $t)) {
            $in = false;
        }
    }
    echo "=== {$rel} — ".count($cols)." columns\n";
    foreach ($cols as $c) {
        $base = explode('.', $c)[0];
        $guess = strtolower(preg_replace('/(?<!^)[A-Z]/', 's$0', $base));
        $found = 'unknown-table';
        foreach ($tableCols as $tbl => $cs) {
            if (in_array($c, $cs, true)) {
                $found = 'name is a column of '.$tbl.' (first table holding it, NOT bound to this screen)';
                break;
            }
        }
        if ($found === 'unknown-table' && str_contains($c, '.')) {
            $found = 'NESTED (hop or relation)';
        }
        if ($found === 'unknown-table') {
            $found = 'not a column anywhere this scan read (computed or joined label?)';
        }
        echo '    '.str_pad($c, 26).' -> '.$found."\n";
    }
}
echo "\ntables read from migrations: ".count($tableCols)."\n";
if (count($tableCols) < 30) {
    fwrite(STDERR, "\nREFUSED: the migration scan read ".$tableCols.' tables, which is not a schema — every '
        ."verdict above would be a guess about a map that was never built.\n");

    exit(1);
}

// The floor above catches a map that was never built. It cannot catch a map that is short by a few tables, and
// that is the failure this tool exists to avoid — so the real bound is the server the screens actually read.
// `migrations` is the one table the framework writes itself and no migration creates, so it is named and excused
// rather than silently subtracted: a partition is derived, never recalled.
$env = [];
foreach (file($root.'/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (preg_match('/^\s*([A-Z_]+)\s*=\s*(.*)$/', $line, $kv) && ! str_starts_with(trim($line), '#')) {
        $env[$kv[1]] = trim($kv[2], " \t\"");
    }
}
$schema = $env['DB_DATABASE'] ?? '';
try {
    $pdo = new PDO('mysql:host='.($env['DB_HOST'] ?? '127.0.0.1').';dbname='.$schema, $env['DB_USERNAME'] ?? 'root', $env['DB_PASSWORD'] ?? '');
    $server = [];
    foreach ($pdo->query('select TABLE_NAME from information_schema.TABLES where TABLE_SCHEMA = '.$pdo->quote($schema)) as $r) {
        $server[] = $r[0];
    }
} catch (Throwable $e) {
    $server = null;
}

if ($server === null) {
    echo "server surface: NOT JUDGED (no database reachable as '".($schema ?: '???')."') — the census above is read off migrations alone\n";
} else {
    $missing = array_values(array_diff($server, array_keys($tableCols)));
    // a retired table left behind by a drop migration is not a hole in the map
    $dropped = [];
    foreach (glob($root.'/database/migrations/*.php') as $m) {
        if (preg_match_all('/Schema::drop\w*\(\s*[\'"]([a-zA-Z_]+)[\'"]/', (string) file_get_contents($m), $dm)) {
            $dropped = array_merge($dropped, $dm[1]);
        }
    }
    // The RBAC pivots and any other vendor-owned table are created by a package's migration, not one in this
    // repository, so they are excused by READING vendor rather than by a remembered list — `staff_roles`,
    // `role_permissions` and `model_has_permissions` are Spatie's, and `migrations` the framework writes itself.
    $vendor = [];
    foreach (glob($root.'/vendor/*/*/database/migrations/*.php') as $m) {
        if (preg_match_all('/Schema::(create|table)\(\s*[\'"]([a-zA-Z_]+)[\'"]/', (string) file_get_contents($m), $vm)) {
            $vendor = array_merge($vendor, $vm[2]);
        }
    }
    // Spatie creates its pivots under names read from config, so `Schema::create('staff_roles'` appears nowhere in
    // a vendor file — the literal-name scan above is blind to them by construction. They are excused by reading the
    // config that names them, not by remembering the list: `model_has_roles` is `staff_roles` and
    // `role_has_permissions` is `role_permissions` in this installation.
    $configTables = [];
    $perm = $root.'/config/permission.php';
    if (is_file($perm) && preg_match_all("/'(?:roles|permissions|model_has_permissions|model_has_roles|role_has_permissions)'\s*=>\s*'([a-zA-Z_]+)'/", (string) file_get_contents($perm), $pm)) {
        $configTables = $pm[1];
    }
    $excused = array_values(array_unique(array_merge($vendor, $configTables, ['migrations'])));
    $missing = array_values(array_diff($missing, $dropped, $excused));
    printf("server tables: %d, mapped by this repository: %d, excused as vendor/framework-owned: %d, unmapped: %d\n",
        count($server), count($tableCols), count($excused), count($missing));
    if ($missing !== []) {
        fwrite(STDERR, 'REFUSED: the server holds tables this map never read — '.implode(', ', $missing)
            .". A column of an unmapped table cannot be told from a computed label, so the withholdings above are unproven.\n");

        exit(1);
    }
}
