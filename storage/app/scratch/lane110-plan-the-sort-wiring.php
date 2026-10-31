<?php

/**
 * Lane 110: the per-screen plan for the DataTable pages that still offer no order (§28.3 G-40, task #28).
 *
 * G-40 is the register's largest open engineering row: eight of the thirty-six screens that render the shared table
 * are wired to `ListSort`, twenty-eight are not. Wiring a screen is not a mechanical act — the ruling this project
 * already holds says a header may only be painted for an order the handler obeys, that an ENUM column may carry a
 * header because the school wrote its own declaration order, and that a column reached through a relation pins the
 * select and joins under a private alias. So the plan for each screen has to say WHICH painted column is WHICH kind
 * of thing before anyone writes a `$sortable` array.
 *
 * Three facts per unwired screen, each read from a place that already decides it:
 *   1. the page and the action that renders it — parsed out of `lane90-probe-pagination.php`, because that probe
 *      owns the rows-expression-to-action join (a second definition of it is this repository's recurring defect);
 *   2. the columns the page actually PAINTS — its own `columns` array, `key:` and `header:`;
 *   3. what each of those is in the schema — read out of the migrations, never from live `ems`, so the plan holds on
 *      any clone: a real column (with its type, and its ENUM values in declaration order), a relation, or derived in
 *      the page.
 *
 * It prints a queue, not a gate: `DERIVED` and `UNKNOWN` rows are a reader's decision about what a desk may order,
 * and inventing an order for a painted-but-stored-nowhere column is the invented-vocabulary defect §28.3 G-28 names.
 *
 * Usage: php storage/app/scratch/lane110-plan-the-sort-wiring.php [--only=Page/Name]
 */
$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$only = null;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--only=')) {
        $only = substr($arg, strlen('--only='));

        continue;
    }
    fwrite(STDERR, "REFUSED: unknown argument {$arg}; this planner reads --only=.\n");

    exit(1);
}

// --- 1. the pages that already send an order ---------------------------------------------------------------
// RecursiveDirectoryIterator, not `glob('**/*.jsx')`: PHP's glob has no recursive double-star, and the pattern that
// looks like it walks the tree answers only one level deep — which would report the nested Admin screens as
// unwired forever. A silent empty population is the defect this repository meets with `refuse on an empty match`.
$wired = [];
$pageFiles = [];
$pageWalk = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/resources/js/Pages'));
foreach ($pageWalk as $f) {
    if (! $f->isFile() || ! str_ends_with($f->getFilename(), '.jsx')) {
        continue;
    }
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root.'/resources/js/Pages/'), -4));
    $pageFiles[$rel] = $f->getPathname();
    $src = (string) file_get_contents($f->getPathname());
    if (str_contains($src, 'sortParams') || str_contains($src, 'sort.available') || str_contains($src, 'ui/listSort')) {
        $wired[$rel] = true;
    }
}
if ($pageFiles === []) {
    fwrite(STDERR, "REFUSED: the Pages walk found no .jsx file, so 'wired' would be an empty set by accident.\n");

    exit(1);
}

// --- 2. lane90's own verdict: page x action x rows expression ---------------------------------------------
chdir($root);
exec(escapeshellarg($php).' '.escapeshellarg($root.'/storage/app/scratch/lane90-probe-pagination.php').' 2>&1', $l90, $code);
if ($code !== 0) {
    fwrite(STDERR, "REFUSED: lane90 exited {$code}, so its census is not a measurement to build a plan on.\n"
        .implode("\n", array_slice($l90, 0, 6))."\n");

    exit(1);
}
$rows = [];
foreach ($l90 as $line) {
    if (preg_match('/^\s*(PAGINATED|COLLECTION|CLIENT-DERIVED)\s+(\S+)\s+.*?([A-Za-z0-9_\/]+\.php)@(\w+)\s{2,}(.*)$/', $line, $m) === 1) {
        $rows[] = ['kind' => $m[1], 'page' => $m[2], 'controller' => $m[3], 'action' => $m[4], 'expr' => trim($m[5])];
    }
}
if ($rows === []) {
    fwrite(STDERR, "REFUSED: lane90 printed no row this reader could parse, so the plan would be built on nothing —"
        ." its output shape moved, and a planner that reads the wrong line prints a clean plan over an unread census.\n");

    exit(1);
}
$parsedFromTally = 0;
foreach ($l90 as $line) {
    if (preg_match('/^pages rendering the shared table: (\d+)\s+rows judged \(page x action x prop\): (\d+)/', $line, $m) === 1) {
        $parsedFromTally = (int) $m[2];
    }
}
if ($parsedFromTally === 0 || $parsedFromTally !== count($rows)) {
    fwrite(STDERR, sprintf("REFUSED: lane90 tallies %d judged rows and this reader parsed %d — a row fell through"
        ." the parser.\n", $parsedFromTally, count($rows)));

    exit(1);
}

// --- 3. migration columns, per table ------------------------------------------------------------------------
/**
 * Columns of one table as the migrations declare them: name => ['type' => …, 'enum' => string[]|null].
 *
 * Block-bound like `students-column-guard.php`, because a migration file may declare several tables and a column
 * name read from the wrong block is a wrong claim about a table. `->change()` migrations and later `->after()`
 * adds are folded in by file order, and a dropped column is removed, so the answer tracks the schema a fresh
 * `migrate` would build rather than the first file that mentioned the table.
 *
 * @return array<string,array{type:string,enum:?array<int,string>}>
 */
$migrationColumns = static function (string $table) use ($root): array {
    $cols = [];
    $files = glob($root.'/database/migrations/*.php') ?: [];
    sort($files);
    foreach ($files as $f) {
        $whole = (string) file_get_contents($f);
        // Read only the UP half. This is the rule `students-column-guard.php` already holds and this reader first
        // broke: every create migration carries a drop in its rollback, so a scan over the whole file sees
        // `$table->boolean('requiresCourseSpecificExam')` in up() and `$table->dropColumn(…)` in down() and reports
        // a real column as absent — which a plan then mislabels as DERIVED, the invented-column claim §28.3 G-28 is
        // the register's name for.
        $src = strstr($whole, 'public function down()', true);
        if ($src === false) {
            $src = $whole;
        }
        // Two spellings of the table argument: a quoted literal (this project's own migrations) and Spatie's
        // published `Schema::create($tableNames['roles'], …)`, where the name is a variable index. A pattern that
        // read only the literal refused the whole plan over `roles` — a true table the RBAC migration does create.
        // `use (…)` is optional: most of this project's create migrations close over nothing, and a pattern that
        // required it matched none of them.
        $quoted = '[\x27"]'.$table.'[\x27"]';
        $indexed = '\$\w+\[\s*[\x27"]'.$table.'[\x27"]\s*\]';
        if (! preg_match_all('/Schema::(create|table)\(\s*(?:'.$quoted.'|'.$indexed.'),\s*function\s*\(Blueprint\s+\$table\)(?:\s*use\s*\(([^)]*)\))?\s*\{/', $src, $sm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            continue;
        }
        foreach ($sm as $block) {
            // A create whose file also rolls it back is still one block; a `table()` edit adds or changes.
            $start = $block[0][1] + strlen($block[0][0]);
            $body = substr($src, $start);
            $depth = 1;
            $len = strlen($body);
            for ($i = 0; $i < $len; $i++) {
                if ($body[$i] === '{') {
                    $depth++;
                } elseif ($body[$i] === '}') {
                    $depth--;
                    if ($depth === 0) {
                        break;
                    }
                }
            }
            $body = substr($body, 0, $i);
            // `$table->dropColumn(['a','b'])` — the array spelling, which the general pattern below cannot see
            // because it expects a quote immediately after the parenthesis.
            if (preg_match_all("/\\\$table->(?:dropColumn|drop)\\(\\s*\\[([^\\]]*)\\]/", $body, $ad, PREG_SET_ORDER)) {
                foreach ($ad as $one) {
                    if (preg_match_all("/[\x27\"](\w+)[\x27\"]/", $one[1], $dn)) {
                        foreach ($dn[1] as $n) {
                            unset($cols[$n]);
                        }
                    }
                }
            }
            if (preg_match_all('/\$table->(\w+)\(\s*[\x27"](\w+)[\x27"](?:\s*,\s*\[([^\]]*)\])?/', $body, $cm, PREG_SET_ORDER)) {
                foreach ($cm as $c) {
                    $method = $c[1];
                    $name = $c[2];
                    if ($method === 'dropColumn' || $method === 'drop') {
                        // The match text carries exactly this one quoted name, which is all the scalar spelling
                        // holds; group 3 is the ENUM value list and must never be read as a column list.
                        unset($cols[$name]);

                        continue;
                    }
                    $enum = null;
                    if ($method === 'enum' && ($c[3] ?? '') !== '') {
                        $enum = array_values(array_filter(array_map('trim', explode(',',
                            str_replace(['\'', '"'], '', $c[3])))));
                    }
                    $cols[$name] = ['type' => $method, 'enum' => $enum];
                }
            }
        }
    }

    return $cols;
};

/** The model a rows expression starts from, when the expression names it; `$query` chains are followed back. */
$modelFromExpr = static function (string $expr, string $action, string $controllerPath): ?string {
    if (preg_match('/\b([A-Z][A-Za-z0-9_]*)::(with|query|where|whereIn|orderBy)\b/', $expr, $m) === 1) {
        return $m[1];
    }
    // `$assessments = $query->paginate(20)` — walk back to the line that assigned $query in this action.
    if (preg_match('/^\$(\w+)\s*=/', $expr, $m) === 1) {
        return null; // resolved by the caller from the action body
    }

    return null;
};

/**
 * Columns the page paints: the shared `DataTable` contract is `{ key, label, render? }` — `DataTable.jsx` reads
 * `col.label` for the heading and `col.key` for the sort button — so both field orders are matched, and a column
 * with no `key` is a `render`-only cell (no stable name to order by).
 *
 * @return array<int,array{key:string,header:string}>
 */
$pageColumns = static function (string $pagePath): array {
    $src = (string) file_get_contents($pagePath);
    $out = [];
    // `key` first (what these pages write), then `label` first (what the older ones do).
    if (preg_match_all("/key:\s*[\x27\"](\w+)[\x27\"]\s*,\s*label:\s*[\x27\"]([^'\"]+)[\x27\"]/", $src, $m, PREG_SET_ORDER)) {
        foreach ($m as $one) {
            $out[$one[1]] = $one[2];
        }
    }
    if (preg_match_all("/label:\s*[\x27\"]([^'\"]+)[\x27\"][^{}]*?key:\s*[\x27\"](\w+)[\x27\"]/", $src, $n, PREG_SET_ORDER)) {
        foreach ($n as $one) {
            $out[$one[2]] = $one[1];
        }
    }

    return $out;
};

$plan = [];
foreach ($rows as $r) {
    if ($r['kind'] === 'CLIENT-DERIVED') {
        continue; // the page maps rows in the browser; a server order cannot describe what it shows
    }
    if ($only !== null && $r['page'] !== $only) {
        continue;
    }
    if (isset($wired[$r['page']])) {
        continue;
    }
    $pagePath = $pageFiles[$r['page']] ?? null;
    if ($pagePath === null) {
        fwrite(STDERR, "REFUSED: lane90 judged {$r['page']} but the Pages walk never named that file, so the two"
            ." readers disagree about the population.\n");

        exit(1);
    }
    $ctrlName = basename($r['controller']);
    $ctrlPath = null;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app/Http/Controllers'));
    foreach ($it as $f) {
        if ($f->isFile() && $f->getFilename() === $ctrlName) {
            $ctrlPath = $f->getPathname();
        }
    }
    if ($ctrlPath === null) {
        fwrite(STDERR, "REFUSED: no controller file named {$ctrlName}.\n");

        exit(1);
    }
    $csrc = (string) file_get_contents($ctrlPath);
    // The action's own body, so a model named by another action cannot be borrowed.
    if (preg_match('/function\s+'.$r['action'].'\s*\([^)]*\)[^{]*\{/', $csrc, $hm, PREG_OFFSET_CAPTURE) !== 1) {
        fwrite(STDERR, "REFUSED: {$ctrlName}@{$r['action']}() was not found, so the plan would name a model the action"
            ." does not query.\n");

        exit(1);
    }
    $start = $hm[0][1] + strlen($hm[0][0]);
    $rest = substr($csrc, $start);
    $depth = 1;
    $len = strlen($rest);
    for ($i = 0; $i < $len; $i++) {
        if ($rest[$i] === '{') {
            $depth++;
        } elseif ($rest[$i] === '}') {
            $depth--;
            if ($depth === 0) {
                break;
            }
        }
    }
    $body = substr($rest, 0, $i);

    $model = $modelFromExpr($r['expr'], $r['action'], $ctrlPath);
    if ($model === null) {
        // Follow the chain: the rows expression names a variable assigned from a Model:: call in the same action.
        if (preg_match('/\b([A-Z][A-Za-z0-9_]*)::(with|query|where|whereIn)\b/', $body, $m2) === 1) {
            $model = $m2[1];
        }
    }
    if ($model === null) {
        $plan[] = ['page' => $r['page'], 'action' => $r['action'], 'model' => null, 'cols' => [], 'kind' => $r['kind']];

        continue;
    }
    // Model => table. Laravel's snake_case plural of the short class name is what this project's tables use, but
    // `protected $table` in the model is the authority, so read it rather than derive it.
    $modelFile = $root.'/app/Models/'.$model.'.php';
    if (! is_file($modelFile)) {
        $plan[] = ['page' => $r['page'], 'action' => $r['action'], 'model' => $model.' (NO MODEL FILE)', 'cols' => [], 'kind' => $r['kind']];

        continue;
    }
    $msrc = (string) file_get_contents($modelFile);
    $table = null;
    if (preg_match("/protected\s+\\\$table\s*=\s*[\x27\"](\w+)[\x27\"]/", $msrc, $tm) === 1) {
        $table = $tm[1];
    }
    if ($table === null) {
        fwrite(STDERR, sprintf("REFUSED: %s has no `protected \$table`, and this planner will not guess a table name"
            ." from a class name — a wrong table is a wrong plan for every column below it.\n", $model));

        exit(1);
    }
    $cols = $migrationColumns($table);
    if ($cols === []) {
        // Printed as a queue, not cleared and not fatal: a table no `Schema::create` spelling this reader knows
        // reaches is a screen whose column types are unknown, and the register may not read that as "nothing owed".
        // The run refuses only when EVERY screen is unread, which is the parser's failure rather than the schema's.
        $plan[] = ['page' => $r['page'], 'action' => $r['action'], 'model' => $model, 'table' => $table,
            'cols' => [], 'painted' => [], 'kind' => $r['kind'], 'unread' => true];

        continue;
    }
    $painted = $pageColumns($pagePath);
    $plan[] = [
            'page' => $r['page'], 'action' => $r['action'], 'model' => $model, 'table' => $table,
            'cols' => $cols, 'painted' => $painted, 'kind' => $r['kind'],
            // The model's own relation names decide whether a painted key is a HOP or a made-up column. Guessing
            // from the key's spelling (uppercase, or containing "name") mislabelled `unit` — a real belongsTo on
            // Courses — as "no such column", which is the wrong-target claim in a plan a reader is supposed to trust.
            'modelSrc' => $msrc, 'path' => str_replace($root.'/', '', $ctrlPath),
    ];
}

if ($plan === []) {
    fwrite(STDERR, ($only !== null
        ? "REFUSED: --only={$only} matched no unwired page, so the plan is empty because the filter was wrong.\n"
        : "REFUSED: no unwired page was planned, which contradicts the twenty-eight the register counts.\n"));

    exit(1);
}

$counts = ['COLUMN' => 0, 'ENUM' => 0, 'HOP' => 0, 'DERIVED' => 0, 'UNKNOWN' => 0, 'UNREAD-TABLE' => 0];
// Two units, named: a page can contribute several rows-expressions (Accounting's daily report builds two
// collections from one action), so "25" is rows and the screen count is its own number — lane 90's lesson that a
// census must state which unit it counts in.
$uniquePages = array_values(array_unique(array_column($plan, 'page')));
echo sprintf("unwired screens planned: %d screen(s), %d rows-expression(s) of the %d lane90 judged (wired today: %d)\n\n",
    count($uniquePages), count($plan), count($rows), count($wired));
foreach ($plan as $p) {
    printf("%s  [%s via %s@%s  table: %s]\n", $p['page'], $p['kind'], $p['model'] ?? 'MODEL-UNRESOLVED',
        $p['action'], $p['table'] ?? '?');
    if (! empty($p['unread'])) {
        $counts['UNREAD-TABLE']++;
        echo "    table not readable from the migrations — this screen's column types are UNKNOWN, not absent\n";

        continue;
    }
    if (! isset($p['painted'])) {
        echo "    no model resolved from the rows expression — read the action before writing a set\n";

        continue;
    }
    if ($p['painted'] === []) {
        echo "    no `key:`/`header:` columns parsed — the page builds its columns another way; read it\n";

        continue;
    }
    foreach ($p['painted'] as $key => $header) {
        if (isset($p['cols'][$key])) {
            $c = $p['cols'][$key];
            if ($c['enum'] !== null) {
                $counts['ENUM']++;
                printf("    ENUM     %-22s \"%s\"  declaration order: %s\n", $key, $header, implode(' < ', $c['enum']));
            } else {
                $counts['COLUMN']++;
                printf("    COLUMN   %-22s \"%s\"  (%s)\n", $key, $header, $c['type']);
            }

            continue;
        }
        // A painted key that is not a column of the listed table is either a RELATION the model declares (orderable
        // only through a join, which pins the select — §28.3 G-51) or DERIVED in the page (no order to offer).
        if (preg_match('/function\s+'.preg_quote($key, '/').'\s*\(\s*\)\s*:\s*\\\\?[A-Za-z]*\b(Has|BelongsTo|Morph)/', $p['modelSrc'] ?? '') === 1) {
            $counts['HOP']++;
            printf("    HOP      %-22s \"%s\"  — a relation on %s, so an order here joins and must pin the select\n", $key, $header, $p['model']);

            continue;
        }
        $counts['DERIVED']++;
        printf("    DERIVED  %-22s \"%s\"  — no column on %s and no relation by that name\n", $key, $header, $p['table']);
    }
    echo "\n";
}
printf("column verdicts: COLUMN %d, ENUM %d, HOP %d, DERIVED %d, UNREAD-TABLE %d\n",
    $counts['COLUMN'], $counts['ENUM'], $counts['HOP'], $counts['DERIVED'], $counts['UNREAD-TABLE']);
if ($counts['UNREAD-TABLE'] === count($plan)) {
    fwrite(STDERR, "\nREFUSED: every screen's table was unreadable, so the plan is a census of nothing — the reader"
        ." is broken, not the schema.\n");

    exit(1);
}
echo "\nHOP? and DERIVED are a reader's decisions, not this tool's: prompt §14 asks for an order the desk can see, and\n"
    ."§28.3 G-28 is the register's name for a column that turns out not to exist.\n";

exit(0);
