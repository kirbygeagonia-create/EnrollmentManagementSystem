<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

/**
 * Lane 46 — does every filter a desk sets reach the query, and does the screen say what it applied?
 *
 * Two screens were found lying. `Students/Index.jsx` sent `status` while `StudentController::index` never read
 * the key, so the directory listed every student under whatever option the desk picked and then reset the
 * control on the next render, because `filters` echoed only `search`. The same dropdown also offered `graduated`
 * and `dropped`, which `students.status` cannot hold — the column is `enum('active','inactive')`, measured on
 * live `ems` and in `2026_08_02_1000000005_create_students_table.php`. And every row of `Exam/Index.jsx` links
 * to `exam.results` with `?search=<School ID>` while that handler branched only on `result` (and on a `stage`
 * key no screen sends any more), so the click opened the whole list under a URL that promised one student.
 *
 * Three claims, each checked against the code and the database rather than remembered:
 *
 *   DEAD-SEND    a page sends a query key for a route whose handler never consults it.
 *   NO-RETURN    a handler consults a key but does not echo it in its `filters` prop, so the control resets
 *                while the list stays narrowed — the desk cannot see what it filtered by.
 *   FICTION      a dropdown value the column cannot hold. The handler's own `->where('<column>', …)` names the
 *                column the key narrows; the model the builder starts from names the table; and the enum's
 *                legal values come from information_schema on this box.
 *
 * A key the checker cannot bind — a route with no controller action, a builder whose model it cannot read, a
 * column no single table owns — is reported AMBIGUOUS or UNBOUND and counts against the run. A parser with a
 * hole and a clean tree look identical otherwise, which is the lesson every checker in this repository has
 * already paid for once.
 *
 * Two shapes the first parser never learned, both met on 2026-10-30 when the sort batch wired four more screens.
 * (1) A `filters` prop written as a multi-line array, or as `$request->only([…]) + ['sort' => …]`: the two
 * one-line patterns read null, and null means "no echo to compare", so NO-RETURN went unsatisfied on exactly the
 * newest pages — a mutant that deleted an echoed key printed 0 problems. The value is now walked by its own
 * brackets and ends at the first comma at bracket depth zero, which is what carries a merged array too.
 * (2) A page whose query is built by spreading a shared helper (`...sortParams(…)`) sends `sort` and `direction`
 * with neither name written in the object literal, so no literal-key scan enumerated them and the order contract
 * was invisible here; the helper's own `return { … }` is read for its keys, so one definition owns the vocabulary.
 * The harness legs that plant a deleted key in each shape are the proof, because a clean tree reads the same
 * either way.
 *
 * What this does NOT claim. DEAD-SEND is about a key the handler never reads. A key read into a variable that
 * is then unused — `->when($status, …)` deleted but `$status = …$request->query('status')` left behind — still
 * counts as consulted, and no rule here catches it, because enumerating "is this variable used for anything"
 * across every query shape would invent claims the parser cannot keep honest. That shape is pinned by a test
 * instead (tests/Feature/StudentDirectoryFilterTest.php), whose mutant is the deletion of the branch alone.
 * FICTION also stays quiet when a filter is computed rather than matched — Terms' status comes from dates, not
 * from an enum column — and the run says so only in --debug.
 *
 * Exit 0 only when every page, route and column under discussion was actually resolved.
 *
 * Usage: php storage/app/scratch/lane46-filter-contract.php [--debug=route.name]
 *
 * --debug prints what one binding actually parsed — the keys consulted, the column each narrows, the model the
 * builder starts from, the enum the column holds — because FICTION checks nothing at all when it cannot resolve
 * a column, and a silently skipped class is the defect this repository keeps paying for.
 */
$root = dirname(__DIR__, 3);
$php = getenv('EMS_PHP') ?: 'C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe';

// The schema questions go through the framework's own connection, the way lane 40-44 do: no cmd.exe quoting
// around information_schema, and the database named is the one .env points at.
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = DB::connection()->getDatabaseName();
$debug = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--debug=')) {
        $debug = substr($arg, strlen('--debug='));
    }
}

/** Read a MySQL enum column's legal values from the schema this box actually has. */
$enumOf = function (string $table, string $column) use ($database): ?array {
    static $cache = [];
    $k = $table.'.'.$column;
    if (array_key_exists($k, $cache)) {
        return $cache[$k];
    }
    $row = DB::selectOne(
        'SELECT COLUMN_TYPE AS t FROM information_schema.columns '
        .'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
        [$database, $table, $column]
    );
    $type = trim((string) ($row->t ?? ''));
    if (! preg_match("/^enum\('(.*)'\)$/s", $type, $m)) {
        return $cache[$k] = null;
    }

    return $cache[$k] = array_map(fn (string $v) => str_replace("''", "'", $v), explode("','", $m[1]));
};

/** Every named GET route with a controller action, from the router itself. */
$routesOf = function () use ($php, $root): array {
    $raw = shell_exec('cd /d '.escapeshellarg($root).' && '.escapeshellarg($php).' artisan route:list --json 2>&1');
    $list = json_decode((string) $raw, true);
    if (! is_array($list)) {
        fwrite(STDERR, "FATAL: `artisan route:list --json` did not answer with JSON\n");
        exit(1);
    }
    $byName = [];
    $named = [];
    foreach ($list as $r) {
        if (empty($r['name']) || empty($r['action'])) {
            continue;
        }
        $named[$r['name']] = true;
        if (! preg_match('/GET|HEAD/', $r['method'] ?? '')) {
            continue; // a payload posted to a write route is not a filter, and this contract is about filters
        }
        if (! preg_match('/^(App\\\\Http\\\\Controllers\\\\[\\\\\w]+)@([\w]+)$/', $r['action'], $m)) {
            continue;
        }
        $params = [];
        if (preg_match_all('/\{(\w+)(?:\?)?\}/', $r['uri'] ?? '', $pm)) {
            foreach ($pm[1] as $p) {
                $params[$p] = true;
            }
        }
        $byName[$r['name']] = [
            'class' => $m[1],
            'method' => $m[2],
            'params' => array_keys($params),
        ];
    }

    return [$byName, $named];
};

/** The body of one controller method, taken between its signature and the next method at the same indent. */
$methodBody = function (string $file, string $method): ?string {
    static $cache = [];
    $k = $file.'#'.$method;
    if (array_key_exists($k, $cache)) {
        return $cache[$k];
    }
    $src = @file_get_contents($file);
    if ($src === false) {
        return $cache[$k] = null;
    }
    if (! preg_match('/function '.preg_quote($method, '/').'\s*\([^)]*\)[^{]*\{(.*?)\n    \}/s', $src, $m)) {
        return $cache[$k] = null;
    }

    return $cache[$k] = $m[1];
};

/**
 * Keys the handler consults on its request, and the column each one narrows.
 *
 * `consults` counts every shape this codebase uses: `$request->search`, `input/query/get('x')`, and
 * `only([...])`. `narrows` records `->where('column', …)` on the same line as the value that came off the key,
 * which is what lets FICTION resolve a column without guessing at the desk's intent.
 */
$handlerKeys = function (string $body): array {
    $consults = [];
    $narrows = [];
    if (preg_match_all('/\$request->only\(\[([^\]]*)\]/', $body, $o)) {
        foreach ($o[1] as $list) {
            foreach (explode(',', $list) as $one) {
                $one = trim($one, " \t\n'\"");
                if ($one !== '') {
                    $consults[$one] = true;
                }
            }
        }
    }
    if (preg_match_all('/\$request->(?:input|query|get|boolean|integer)\(\x27([^\x27]+)\x27/', $body, $o)) {
        foreach ($o[1] as $one) {
            $consults[$one] = true;
        }
    }
    // $request->search / ->status / ->courseId used as properties, minus the framework's own members.
    $framework = ['input', 'query', 'get', 'only', 'all', 'validate', 'file', 'boolean', 'integer', 'user',
        'session', 'expectsJson', 'ajax', 'method', 'ip', 'header', 'has', 'filled', 'merge', 'json', 'route',
        'is', 'attributes', 'request', 'offsetGet', 'string', 'float', 'date', 'collect', 'create'];
    if (preg_match_all('/\$request->([a-zA-Z][a-zA-Z0-9_]*)/', $body, $o)) {
        foreach ($o[1] as $one) {
            if (! in_array($one, $framework, true)) {
                $consults[$one] = true;
            }
        }
    }
    // Which column each key narrows — this is what lets FICTION ask the database about a real enum.
    //
    // Three shapes appear in this codebase, and a parser that reads only the first leaves the class silently
    // unsatisfied: `->when($request->search, … ->where(…))`, `->when($queue === 'no_balance', … ->where(…))`,
    // and the normalised form `… $status = in_array($request->query('status'), …) … ->when($status,
    // fn ($q) => $q->where('status', $status))`. So locals are mapped to their request key first, then every
    // `when` is paired with the first `where` inside its own statement.
    $locals = [];
    if (preg_match_all('/\$(\w+)\s*=\s*[^\n;]*\$request->(?:query|input|get|boolean)\(\x27(\w+)\x27/', $body, $o, PREG_SET_ORDER)) {
        foreach ($o as $m) {
            $locals[$m[1]] = $m[2];
        }
    }
    if (preg_match_all('/->when\(\s*\$?(request->)?(\w+)[^;]*?->where\(\x27(\w+)\x27/s', $body, $o, PREG_SET_ORDER)) {
        foreach ($o as $m) {
            $key = $m[1] === 'request->' ? $m[2] : ($locals[$m[2]] ?? null);
            if ($key !== null) {
                $narrows[$key][] = $m[3];
            }
        }
    }

    return [array_keys($consults), $narrows];
};

/** The keys the `filters` prop hands back to the page. */
$echoedKeys = function (string $body): ?array {
    // Read the value by its own brackets, never by the next quote or the next `]` in the file.
    //
    // The first version matched only two shapes — one line ending at the next `'key'`, or one line ending at the
    // array's own `]` — and three of the four handlers wired to `ListSort` write a multi-line array with a comment
    // above it. Those reads answered null, and null means "no echo to compare", so NO-RETURN was silently
    // unsatisfied on exactly the newest pages: a mutant that deleted an echoed key still printed 0 problems.
    // An unread surface is the same liability as an unread tile (lane 50 read this class from the JSX side).
    if (! preg_match("/['\"]filters['\"]\s*=>\s*/", $body, $m, PREG_OFFSET_CAPTURE)) {
        return null;
    }
    $i = $m[0][1] + strlen($m[0][0]);
    $len = strlen($body);
    while ($i < $len && (preg_match('/\s/', $body[$i]))) {
        $i++;
    }
    if ($i >= $len) {
        return null;
    }
    $expr = '';
    $depth = 0;
    for (; $i < $len; $i++) {
        $c = $body[$i];
        if ($c === "'" || $c === '"') {
            $q = $c;
            $open = $i;
            $i++;
            for (; $i < $len; $i++) {
                if ($body[$i] === '\\') {
                    $i++;

                    continue;
                }
                if ($body[$i] === $q) {
                    break;
                }
            }
            // The whole literal travels, not just its opening quote: the first cut appended only `$c`, so the
            // key text was dropped and `'search' =>` never matched. That made the reader answer null on a
            // handler whose array it had just walked correctly — the unread-surface defect reproduced inside
            // its own fix, which is why the leg that plants a deleted key is the proof and not the clean run.
            $expr .= substr($body, $open, $i - $open + 1);

            continue;
        }
        if ($c === '/' && ($i + 1) < $len && $body[$i + 1] === '/') {
            $nl = strpos($body, "\n", $i);
            $i = $nl === false ? $len : $nl;

            continue;
        }
        if ($c === '[' || $c === '(' || $c === '{') {
            $depth++;
            $expr .= $c;

            continue;
        }
        if ($c === ']' || $c === ')' || $c === '}') {
            $depth--;
            $expr .= $c;

            continue;
        }
        // A comma at depth zero ends this prop. The old stop was the array's own closing bracket, which is wrong
        // for the shape `Campus Clearance` writes: `'filters' => $request->only([...]) + ['sort' => …]` — that
        // merge is part of the same echo, and cutting at the first `]` reported the three `only` keys and read
        // `sort`/`direction` as never echoed.
        if ($c === ',' && $depth === 0) {
            break;
        }
        $expr .= $c;
    }

    $keys = [];
    if (preg_match('/\$request->only\(\[([^\]]*)\]/', $expr, $o)) {
        foreach (explode(',', $o[1]) as $one) {
            $one = trim($one, " \t\n'\"");
            if ($one !== '') {
                $keys[] = $one;
            }
        }
    }
    if (preg_match_all('/[\x27"](\w+)[\x27"]\s*=>/', $expr, $o)) {
        foreach ($o[1] as $one) {
            $keys[] = $one;
        }
    }

    return $keys === [] ? null : array_values(array_unique($keys));
};

/** The model a list builder starts from, which is what fixes the table for a column. */
$modelOf = function (string $body): ?string {
    if (preg_match('/(?:CollegeScope::of\w+\(\s*)?\b([A-Z][A-Za-z]+)::(?:query|with)\(/', $body, $m)) {
        return $m[1];
    }

    return null;
};

$tableOf = function (string $model) use ($root): string {
    $file = $root.'/app/Models/'.$model.'.php';
    $src = @file_get_contents($file);
    if ($src !== false && preg_match('/protected \$table\s*=\s*\x27([^\x27]+)\x27/', $src, $m)) {
        return $m[1];
    }

    return strtolower($model);
};

/**
 * What a page offers for a filter key, and what it sends.
 *
 * `sends` are the object keys inside `route('name', { … })` and `router.get(route('name'), { … })`.
 * `offers` are the `{ value: 'x' }` literals of every const array in the file, which is how a dropdown's
 * vocabulary is read without knowing which dropdown feeds which key — so a value is checked against every
 * column the handler narrows for that route, and only reported when it fits none of them.
 */
$pageKeys = function (string $jsx) use ($root): array {
    $src = @file_get_contents($jsx);
    if ($src === false) {
        return [[], [], false];
    }

    /** The top-level keys and the whole text of the object literal that starts at $start, braces counted. */
    $objectKeys = function (int $start) use ($src): array {
        $depth = 0;
        $end = $start;
        for ($p = $start, $len = strlen($src); $p < $len; $p++) {
            if ($src[$p] === '{') {
                $depth++;
            } elseif ($src[$p] === '}') {
                $depth--;
                if ($depth === 0) {
                    $end = $p;

                    break;
                }
            }
        }
        $obj = substr($src, $start, $end - $start + 1);
        $keys = [];
        if (preg_match_all('/(?:^|[{,])\s*([a-zA-Z_]\w*)\s*:/', $obj, $km)) {
            foreach ($km[1] as $key) {
                $keys[$key] = true;
            }
        }

        return [array_keys($keys), $obj];
    };

    // The keys a spread contributes, read from the helper that returns them. A page that builds its query with
    // `...sortParams(…)` sends `sort` and `direction` without either name appearing in the object literal, and
    // the first version of this reader enumerated only literal keys — so the order contract the four wired
    // screens just acquired was invisible to the instrument that owns "a key sent must come back". Hardcoding
    // the two names here would be a second definition of the helper's vocabulary, so they are taken from its
    // own `return { … }` in resources/js/Components/ui/listSort.js; an unread helper means no spread keys, and
    // the run's `unread` tally would say so rather than clear the class silently.
    $spreadKeys = [];
    $helperSrc = @file_get_contents($root.'/resources/js/Components/ui/listSort.js');
    if ($helperSrc !== false
        && preg_match('/export function sortParams\([^)]*\)\s*\{.*?return\s*\{(.*?)\};/s', $helperSrc, $hm)
        && preg_match_all('/(?:^|[{,])\s*([a-zA-Z_]\w*)\s*:/', $hm[1], $km)) {
        foreach ($km[1] as $key) {
            $spreadKeys[] = $key;
        }
    }
    $spreadKeys = array_values(array_unique($spreadKeys));

    /** Which helper names this object spreads into itself. */
    $spreadInto = function (string $obj) use ($spreadKeys): array {
        if ($spreadKeys === [] || ! str_contains($obj, '...sortParams(')) {
            return [];
        }

        return $spreadKeys;
    };

    $sends = [];
    // (A) a link or a query built inline on the route call: route('x.y', { … })
    if (preg_match_all('/route\(\x27([a-zA-Z0-9_.\-]+)\x27\s*,\s*(\{)/', $src, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[1] as $i => $nameHit) {
            [$keys, $obj] = $objectKeys($m[2][$i][1]);
            foreach ([...$keys, ...$spreadInto($obj)] as $key) {
                $sends[$nameHit[0]][$key] = true;
            }
        }
    }
    // (B) the filter submit, where the object is router.get's SECOND argument and the route call has none:
    //     router.get(route('x.y'), { … }, { preserveState: true })  — this is where a desk's own filters live,
    //     and a checker that reads only shape (A) clears a tree while judging the wrong sites.
    if (preg_match_all('/router\.get\(\s*route\(\x27([a-zA-Z0-9_.\-]+)\x27\)\s*,\s*(\{)/', $src, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[1] as $i => $nameHit) {
            [$keys, $obj] = $objectKeys($m[2][$i][1]);
            foreach ([...$keys, ...$spreadInto($obj)] as $key) {
                $sends[$nameHit[0]][$key] = true;
            }
        }
    }
    $sends = array_map('array_keys', $sends);

    // Per-key dropdown vocabularies: `const statusOptions = [ … ]` belongs to the filter the page reads back
    // as `filters.status`. A page pools several dropdowns, so comparing every value against every column the
    // handler narrows produces false alarms — a Terms screen whose `status` control is computed from dates,
    // not from an enum column, was reported for offering active/inactive. Binding by key is what makes the
    // claim the one the desk actually sees.
    $offers = [];
    if (preg_match_all('/const (\w+)Options\s*=\s*\[(.*?)\];/s', $src, $om, PREG_SET_ORDER)) {
        foreach ($om as $m) {
            $key = lcfirst($m[1]);
            if (! preg_match('/useState\(filters\.'.preg_quote($key, '/').'\b/', $src)) {
                continue; // not a filter control the page repopulates from the server
            }
            if (preg_match_all('/value:\s*\x27([a-zA-Z0-9_]+)\x27/', $m[2], $vm)) {
                $vals = array_values(array_unique(array_filter($vm[1], fn ($v) => $v !== '')));
                if ($vals !== []) {
                    $offers[$key] = $vals;
                }
            }
        }
    }

    return [$sends, $offers, true];
};

$routes = [];
$named = [];
[$routes, $named] = $routesOf();
$pages = [];
$pagesDir = getenv('EMS_LANE46_PAGES') ?: $root.'/resources/js/Pages';
if (! is_dir($pagesDir)) {
    fwrite(STDERR, "FATAL: no page directory at {$pagesDir} — the scope named cannot be read, so nothing is checked\n");
    exit(1);
}
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pagesDir));
foreach ($it as $fi) {
    if ($fi->isFile() && $fi->getExtension() === 'jsx') {
        $rel = str_replace('\\', '/', substr($fi->getPathname(), strlen($pagesDir.'/')));
        $pages[preg_replace('/\.jsx$/', '', $rel)] = $fi->getPathname();
    }
}

$problems = 0;
$unread = 0;
$checked = 0;
$judged = [];
$rows = [];

foreach ($pages as $page => $path) {
    [$sends, $offers, $read] = $pageKeys($path);
    if (! $read) {
        $rows[] = ['UNREAD', $page, '', 'the page could not be read'];
        $unread++;

        continue;
    }
    foreach ($sends as $routeName => $keys) {
        if (! isset($routes[$routeName])) {
            if (isset($named[$routeName])) {
                continue; // a write route: its payload is a form post, not a filter
            }
            $rows[] = ['NO-ROUTE', $page, $routeName, 'the page sends a query for a route the router does not name'];
            $unread++;

            continue;
        }
        $file = $root.'/app/'.str_replace('\\', '/', substr($routes[$routeName]['class'], strlen('App\\'))).'.php';
        $body = $methodBody($file, $routes[$routeName]['method']);
        if ($body === null) {
            $rows[] = ['UNBOUND', $page, $routeName, $routes[$routeName]['class'].'@'.$routes[$routeName]['method'].' could not be parsed'];
            $unread++;

            continue;
        }
        $checked++;
        $judged[] = $page.' -> '.$routeName;
        [$consults, $narrows] = $handlerKeys($body);
        $echoed = $echoedKeys($body);

        if ($debug === $routeName) {
            $model = $modelOf($body);
            $table = $model === null ? null : $tableOf($model);
            printf("=== %s bound to %s@%s\n", $routeName, $routes[$routeName]['class'], $routes[$routeName]['method']);
            printf("    file            %s\n", str_replace($root.'/', '', $file));
            printf("    params          %s\n", implode(', ', $routes[$routeName]['params']) ?: '(none)');
            printf("    keys sent       %s\n", implode(', ', $keys) ?: '(none)');
            printf("    consults        %s\n", implode(', ', $consults) ?: '(NONE — the parser read nothing)');
            foreach ($narrows as $key => $columns) {
                printf("    narrows         %s -> %s\n", $key, implode(', ', array_unique($columns)));
            }
            printf("    filters echoes  %s\n", $echoed === null ? '(could not read the filters prop)' : implode(', ', $echoed));
            printf("    model / table   %s / %s\n", $model ?? '(unread)', $table ?? '(unread)');
            $seen = [];
            foreach ($narrows as $columns) {
                foreach (array_unique($columns) as $column) {
                    if ($table === null) {
                        continue;
                    }
                    $seen[$table.'.'.$column] = $enumOf($table, $column);
                }
            }
            foreach ($seen as $tc => $vals) {
                printf("    column enum     %s = %s\n", $tc, $vals === null ? '(not an enum on this box)' : implode('|', $vals));
            }
            // `$offers` is keyed by filter name and each value is the list of literals, so a bare implode walks
            // straight into "Array to string conversion" — the debug branch crashed here the first time the sort
            // batch sent anyone to read a binding, which is how a flag that prints nothing was found unread.
            printf("    offers          %s\n", $offers === []
                ? '(no value: literals in this page)'
                : implode(', ', array_map(
                    fn (array $v, string $k): string => $k.'='.implode('|', $v),
                    $offers,
                    array_keys($offers)
                )));
        }

        foreach ($keys as $key) {
            if ($key === 'page' || in_array($key, $routes[$routeName]['params'], true)) {
                continue; // the paginator's own link, and a `{param}` the router binds rather than a filter
            }
            if (! in_array($key, $consults, true)) {
                $rows[] = ['DEAD-SEND', $page, $routeName,
                    "?{$key} is sent but ".basename($file).'@'.$routes[$routeName]['method'].' never consults it'];
                $problems++;

                continue;
            }
            if ($echoed !== null && ! in_array($key, $echoed, true)) {
                $rows[] = ['NO-RETURN', $page, $routeName,
                    "the handler consults {$key} but its filters prop does not echo it, so the control resets"];
                $problems++;
            }
        }

        $model = $modelOf($body);
        $table = $model === null ? null : $tableOf($model);

        foreach ($offers as $key => $values) {
            if (! isset($narrows[$key])) {
                continue; // the handler does not narrow a column for this key
            }
            $enums = [];
            foreach (array_unique($narrows[$key]) as $column) {
                if ($table === null) {
                    continue;
                }
                $vals = $enumOf($table, $column);
                if ($vals !== null) {
                    $enums[$column] = $vals;
                }
            }
            if ($enums === []) {
                continue; // the key is computed against a column that holds no vocabulary of its own
            }
            if ($debug === $routeName) {
                foreach ($enums as $column => $vals) {
                    printf("    bound %s.%s = %s\n", $table, $column, implode('|', $vals));
                }
                printf("    options for %s: %s\n", $key, implode(', ', $values));
            }
            $allowed = array_values(array_unique(array_merge(...array_values($enums))));
            $targets = array_map(fn (string $column) => $table.'.'.$column, array_keys($enums));
            foreach ($values as $value) {
                if (! in_array($value, $allowed, true)) {
                    $rows[] = ['FICTION', $page, $routeName,
                        "the {$key} dropdown offers '{$value}' — ".implode(' / ', $targets)
                        .' holds only '.implode('|', $allowed)];
                    $problems++;
                }
            }
        }
    }
}

foreach ($rows as [$kind, $page, $route, $why]) {
    printf("  %-10s %-34s %-26s %s\n", $kind, $page, $route, $why);
}

if ($debug !== null) {
    foreach ($judged as $j) {
        echo "  judged: {$j}\n";
    }
}

printf("\n%d page/route binding(s) judged, %d problem(s), %d unread or unbound.\n", $checked, $problems, $unread);
printf("  scanned %s\n", $pagesDir);
echo "  A desk may only be shown a list that matches the controls it just set: a key sent and never consulted\n"
    ."  is a filter that does nothing, and a key consulted and never echoed is a control that resets on the\n"
    ."  next render while the list stays narrowed. An unbound claim is counted against this run.\n";

if ($checked === 0) {
    fwrite(STDERR, "FATAL: nothing was judged — a parser that binds no site cannot clear a tree\n");
    exit(1);
}

exit(($problems === 0 && $unread === 0) ? 0 : 1);
