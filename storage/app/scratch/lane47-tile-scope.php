<?php

/**
 * Lane 47 — does a summary tile describe the set its label claims?
 *
 * Every desk screen puts numbers above its list, and a reader takes those numbers for the queue. This checker
 * asks where each number actually comes from and whether the label says so. Four sets are in use in this tree
 * without any of them being named: the whole filtered set, the whole table, the current page, and a predicate
 * the list query can never satisfy.
 *
 *   SILENT-ZERO  a controller takes its "full filtered set" aggregate from `(clone $query)` AFTER calling
 *                `$query->paginate()`. Laravel's paginate() mutates the builder in place — forPage() sets
 *                limit/offset on $this (vendor/laravel/framework/src/Illuminate/Database/Query/Builder.php:3208)
 *                — so the clone carries "LIMIT 20 OFFSET 20" and answers with nothing from page 2 onward.
 *                Page 1 always looks right, which is why this survives a demo. AccountingController is the one
 *                desk that clones before paginating.
 *   PINNED-SELECT a controller takes its aggregate from `(clone $query)` AFTER `ListSort::apply()` ran on that
 *                builder, and the aggregate appends with `selectRaw()`. The order joins the table an ordered
 *                column sits on and, for a hop, pins the select to the base model's own columns; `select()`
 *                REPLACES and `selectRaw()` APPENDS, so the aggregate carries `table.*` beside its own column and
 *                MySQL 8's ONLY_FULL_GROUP_BY answers 1055 — a 500 at the desk for clicking a header — while
 *                SQLite answers the query. This is the one verdict here that the local suite cannot prove; the
 *                surface that sees it is a scratch MySQL schema, with `DB_CONNECTION` pinned as well as
 *                `E2E_DB_DATABASE` (lane 87's rule). Naming the aggregate's own columns with `select()` first is
 *                what the admission queue's status tile does now, and that shape is counted clean.
 *   PAGE-TILE    a StatCard whose value reduces over `props.data` (the page) while its label names no scope.
 *   MIXED-SET    a screen where one tile reads the paginator total and another reads only the page rows, so the
 *                tiles disagree with each other and nothing tells the desk which is which.
 *   IMPOSSIBLE   a tile counted by a predicate the handler's own query cannot return. Evaluation's "Evaluated"
 *                tile compares enrollmentStatus to 'evaluated' while EvaluationController::index restricts the
 *                list to pending + returnedToEvaluation: it reads 0 forever, on a box where 2 enrollments are
 *                evaluated.
 *   DEAD-OPTION  a filter pick-list assembled from the page's rows, so a value that lives on a later page
 *                cannot be chosen at all. AuditLogs' Entity dropdown answers with 4 tables while auditlogs
 *                holds 43 (measured on live ems).
 *
 * UNBOUND — a tile it cannot trace, or a whereIn whose enum it cannot resolve — is reported and counts against
 * the run. An unread claim must never look like a checked one.
 *
 * What this does NOT claim. It judges whether a label states the scope the code produces, not whether that
 * scope is the one the desk wants — "queue size beside page totals" can be a legitimate screen. It cannot see
 * a tile fed by a fetch() instead of a prop, and it does not judge the arithmetic of a rate (whose denominator
 * is pinned by a test, not here). A SERVER tile is only as honest as its controller, which is why the ordering
 * pass below runs over the same handlers. Record pages that list no paginator are skipped and named, because
 * the question does not arise for them.
 *
 * Scope is a flag so a harness can prove the scan is actually running: EMS_LANE47_PAGES=Pages/ID,Pages/Exam
 * restricts which page files are judged, and a scope that matches nothing refuses rather than reporting clean.
 *
 * Usage: php storage/app/scratch/lane47-tile-scope.php [--debug=Exam/Index]
 */

$root = dirname(__DIR__, 3);
$php = getenv('EMS_PHP') ?: 'C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe';

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$debug = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--debug=')) {
        $debug = substr($arg, strlen('--debug='));
    }
}

$scope = getenv('EMS_LANE47_PAGES');
$scope = $scope === false || trim($scope) === '' ? null : array_values(array_filter(array_map('trim', explode(',', $scope))));

$oneLine = fn (string $s): string => preg_replace('/\s+/', ' ', $s);

/** The body of one controller method, between its signature and the next method at the same indent. */
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
 * Read one JSX opening tag from `<Name` to the `>` that closes it, honouring quotes and brace depth.
 *
 * A regex cannot do this here: `<StatCard … iconBg={totalAvailable > 0 ? 'success' : 'danger'}>` carries a `>`
 * inside an expression, and `/<StatCard[^>]*>/` stops mid-tag — so the tile the cheap parser drops is the one
 * with the conditional tone, which is the tile that needs judging.
 */
$openingTag = function (string $src, int $start): ?string {
    $len = strlen($src);
    $depth = 0;
    $quote = null;
    for ($i = $start; $i < $len; $i++) {
        $c = $src[$i];
        if ($quote !== null) {
            if ($c === $quote && ($i === 0 || $src[$i - 1] !== '\\')) {
                $quote = null;
            }

            continue;
        }
        if ($c === '"' || $c === "'" || $c === '`') {
            $quote = $c;

            continue;
        }
        if ($c === '{') {
            $depth++;

            continue;
        }
        if ($c === '}') {
            $depth--;

            continue;
        }
        if ($c === '>' && $depth === 0) {
            return substr($src, $start, $i - $start);
        }
    }

    return null;
};

/** Every `<StatCard …>` in a page: its label text and its value expression. */
$statCards = function (string $src) use ($openingTag): array {
    $out = [];
    $at = 0;
    while (($at = strpos($src, '<StatCard', $at)) !== false) {
        $tag = $openingTag($src, $at);
        $at += 9;
        if ($tag === null) {
            continue;
        }
        $label = preg_match('/\blabel="([^"]*)"/', $tag, $l) ? $l[1] : (preg_match("/\blabel='([^']*)'/", $tag, $lc) ? $lc[1] : null);
        if ($label === null && preg_match('/\blabel=\{([^}]*)\}/', $tag, $ld)) {
            // a computed label still states a scope in its literals: `label={x ? 'Pass Rate · scored' : '…'}`
            $label = trim(preg_match("/'([^']*)'/", $ld[1], $ls) ? $ls[1] : $ld[1], " \t'\"`");
        }
        $value = preg_match('/\bvalue=\{(.*?)\}\s*(?:[a-zA-Z]+=|\/?>)/s', $tag, $v) ? trim($v[1]) : null;
        if ($value !== null) {
            $out[] = ['label' => $label, 'value' => $value];
        }
    }

    return $out;
};

/** Local consts in a page, name -> the text defining them. Both useMemo shapes appear here. */
$locals = function (string $src): array {
    $map = [];
    if (preg_match_all('/const (\w+) = useMemo\(\(\) => \{(.*?)\}\s*,\s*\[[^\]]*\]/s', $src, $o, PREG_SET_ORDER)) {
        foreach ($o as $m) {
            $map[$m[1]] = $m[2];
        }
    }
    if (preg_match_all('/const (\w+) = useMemo\(\s*\(\)\s*=>\s*(.+?)\s*,\s*\[[^\]]*\]\s*,?\s*\)/s', $src, $o, PREG_SET_ORDER)) {
        foreach ($o as $m) {
            $map[$m[1]] ??= $m[2];
        }
    }
    if (preg_match_all('/const (\w+) = ([^\n;]+);/', $src, $o, PREG_SET_ORDER)) {
        foreach ($o as $m) {
            $map[$m[1]] ??= $m[2];
        }
    }

    return $map;
};

/**
 * The props the default export destructures — those are the server's answer, not a derivation.
 *
 * `[^}]*` would end the list at the first default value (`{ summary = {}, filters = {} }`), and every prop
 * after it would then read as UNBOUND — a screen full of UNBOUND is how a real finding gets lost. The
 * parameter text is therefore scanned with brace depth and split on top-level commas.
 */
$propNames = function (string $src): array {
    if (! preg_match('/export default function \w+\(/', $src, $m, PREG_OFFSET_CAPTURE)) {
        return [];
    }
    $open = $m[0][1] + strlen($m[0][0]) - 1;
    $len = strlen($src);
    $depth = 0;
    $params = null;
    for ($i = $open; $i < $len; $i++) {
        $c = $src[$i];
        if ($c === '(' || $c === '{' || $c === '[') {
            $depth++;

            continue;
        }
        if ($c === ')' || $c === '}' || $c === ']') {
            $depth--;
            if ($depth === 0) {
                $params = substr($src, $open + 1, $i - $open - 1);

                break;
            }
        }
    }
    if ($params === null || ! preg_match('/^\s*\{(.*)\}\s*(?:=\s*\{\})?\s*$/s', $params, $b)) {
        return [];
    }

    $names = [];
    $depth = 0;
    $current = '';
    $push = function (string $piece) use (&$names): void {
        $name = trim(explode('=', trim($piece))[0]);
        // `{ stats: serverStats = null }` — the page reads the local alias, not the prop key
        if (str_contains($name, ':')) {
            $name = trim(explode(':', $name)[1]);
        }
        $name = preg_replace('/\s.*$/', '', $name);
        if ($name !== '' && ! in_array($name, $names, true)) {
            $names[] = $name;
        }
    };
    foreach (str_split($b[1]) as $c) {
        if ($c === '{' || $c === '[' || $c === '(') {
            $depth++;
        } elseif ($c === '}' || $c === ']' || $c === ')') {
            $depth--;
        }
        if ($c === ',' && $depth === 0) {
            $push($current);
            $current = '';

            continue;
        }
        $current .= $c;
    }
    $push($current);

    return $names;
};

/**
 * Where a tile's number comes from: PAGE (this page of rows), SET (the paginator's total), SERVER (a prop, so
 * the handler decides), LITERAL, or UNBOUND.
 *
 * A name is never the evidence. `totalBlocks` is judged by what it is defined from, `peso(x)` by `x`, and
 * `stats.pending` by the line the `stats` memo returns for `pending` — one memo mixes sources, since
 * Evaluation's `total` reads the paginator while its `pending` reads rows. Where a property cannot be
 * separated from a mixed body, the tile is UNBOUND and the run refuses, rather than the checker guessing.
 */
$sourceOf = function (string $expr, array $locals, array $props, int $depth = 0) use (&$sourceOf, $oneLine): array {
    $e = $oneLine(trim(str_replace(['${', '`'], ' ', $expr), ' {}'));
    if ($e === '') {
        return ['UNBOUND', $expr];
    }
    if ($depth >= 5) {
        return ['UNBOUND', 'definition chain deeper than 5 hops: '.$e];
    }

    // `${stats.passRate}%` — a template literal carries its number inside an interpolation; the surrounding
    // text is decoration and would defeat every anchor below.
    if (str_contains($expr, '`')) {
        if (preg_match_all('/\$\{([^}]*)\}/', $expr, $ti) && $ti[1] !== []) {
            return $sourceOf($ti[1][0], $locals, $props, $depth + 1);
        }

        return ['LITERAL', $e];
    }

    // `usePage().props.stats` — a page that reads the shared Inertia payload instead of its own props.
    if (str_starts_with($e, 'usePage(')) {
        return ['SERVER', $e];
    }

    // `peso(summary.totalAssessed ?? 0)` — a formatting wrapper is not a source. This fires for a local
    // helper too (`const peso = (n) => …`), where the definition is the arrow, not the number.
    if (preg_match('/^([A-Za-z_]\w*(?:\.\w+)*|Math|Number)\((.*)\)$/s', $e, $call)) {
        $fn = $call[1];
        $isHelper = in_array($fn, ['Math', 'Number', 'Boolean', 'String', 'isNaN'], true)
            || (isset($locals[$fn]) && str_contains($locals[$fn], '=>'));
        if ($isHelper || ! isset($locals[$fn])) {
            if ($fn === 'Math' || $fn === 'Number') {
                // classify the first argument that is a real quantity, not a constant
                foreach (preg_split('/\s*,\s*/', $call[2]) as $arg) {
                    [$k, $w] = $sourceOf($arg, $locals, $props, $depth + 1);
                    if ($k !== 'LITERAL') {
                        return [$k, $e.' — from '.$oneLine($w)];
                    }
                }
            }

            return $sourceOf($call[2], $locals, $props, $depth + 1);
        }
    }

    $head = preg_split('/[.?[\]()]/', ltrim($e, '$'))[0] ?? '';

    // `stats.pending` where stats is a memo returning an object: ask that memo what it returns for `pending`.
    if (preg_match('/^(\w+)\.(\w+)$/', $e, $pp) && isset($locals[$pp[1]]) && str_contains($locals[$pp[1]], 'return {')) {
        if ($pp[2] === 'length') {
            return $sourceOf($locals[$pp[1]], $locals, $props, $depth + 1);
        }
        if (preg_match('/(?<![.\w])'.preg_quote($pp[2], '/').'\s*:\s*([^\n,]+)/', $locals[$pp[1]], $seg)) {
            return $sourceOf($seg[1], $locals, $props, $depth + 1);
        }
        $def = $locals[$pp[1]];
        if (preg_match('/\brows\b|\?\.data\b/', $def) && ! preg_match('/\.total\b|\?\.total\b/', $def)) {
            return ['PAGE', $e.' — the whole `'.$pp[1].'` memo reduces over rows'];
        }

        return ['UNBOUND', $e.' is not named as `'.$pp[2].':` inside the `'.$pp[1].'` memo, which mixes sources'];
    }

    // `byModeEntries.length` — the length of a local list is that list's own source.
    if (preg_match('/^(\w+)\.length$/', $e, $ln) && isset($locals[$ln[1]])) {
        return $sourceOf($locals[$ln[1]], $locals, $props, $depth + 1);
    }

    if ($head !== '' && isset($locals[$head])) {
        return $sourceOf($locals[$head], $locals, $props, $depth + 1);
    }

    // `serverStats || computedStats` — the left operand is what the desk sees; a different source on the
    // right is a fallback and is named, because a page-derived fallback behind a server prop is still a lie
    // the moment the server stops sending one. Only top-level operators count: `(blocks?.data || []).reduce(…)`
    // is one expression, and splitting it would judge a fragment.
    if (preg_match('/\s(?:\|\||\?\?)\s/', $e)) {
        $alts = [];
        $depth2 = 0;
        $current = '';
        $chars = preg_split('//', $e, -1, PREG_SPLIT_NO_EMPTY);
        for ($i = 0; $i < count($chars); $i++) {
            $c = $chars[$i];
            if ($c === '(' || $c === '[' || $c === '{') {
                $depth2++;
            } elseif ($c === ')' || $c === ']' || $c === '}') {
                $depth2--;
            }
            if ($depth2 === 0 && ($c === '|' || $c === '?')
                && ($chars[$i + 1] ?? '') === $c) {
                $alts[] = $current;
                $current = '';
                $i++;

                continue;
            }
            $current .= $c;
        }
        $alts[] = $current;
        $alts = array_values(array_filter(array_map('trim', $alts), static fn (string $s): bool => $s !== ''));
        if (count($alts) > 1) {
            [$kind, $why] = $sourceOf($alts[0], $locals, $props, $depth + 1);
            foreach (array_slice($alts, 1) as $alt) {
                [$altKind] = $sourceOf($alt, $locals, $props, $depth + 1);
                if ($altKind !== $kind && $altKind !== 'LITERAL' && $altKind !== 'UNBOUND') {
                    $why .= '  (falls back to a '.$altKind.' count: '.$oneLine($alt).')';

                    break;
                }
            }

            return [$kind, $why];
        }
    }

    if (preg_match('/\.total\b|\?\.total\b/', $e)) {
        return ['SET', $e.((bool) preg_match('/\?\?\s*[\w.]*\??\.data/', $e) ? '  (falls back to the page length)' : '')];
    }
    if (preg_match('/\?\.data\b|\.data\b|\brows\b/', $e)) {
        return ['PAGE', $e];
    }
    if ($head !== '' && in_array($head, $props, true)) {
        return ['SERVER', $e];
    }
    if (preg_match('/^[0-9\x27"`]/', $e)) {
        return ['LITERAL', $e];
    }

    return ['UNBOUND', $e.($head === '' ? '' : ' — `'.$head.'` is neither a local const nor a destructured prop')];
};

// ------------------------------------------------------------------ page inventory

$pages = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/resources/js/Pages'));
foreach ($it as $f) {
    if ($f->isDir() || ! str_ends_with($f->getFilename(), '.jsx')) {
        continue;
    }
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root.'/resources/js/')));
    if ($scope !== null) {
        $hit = false;
        foreach ($scope as $s) {
            if (str_contains($rel, $s)) {
                $hit = true;

                break;
            }
        }
        if (! $hit) {
            continue;
        }
    }
    $src = (string) file_get_contents($f->getPathname());
    if (str_contains($src, '<StatCard')) {
        $pages[$rel] = $src;
    }
}

if ($pages === []) {
    fwrite(STDERR, 'REFUSED: no page file carries a StatCard'.($scope === null ? ' anywhere in resources/js/Pages' : ' under EMS_LANE47_PAGES='.implode(',', $scope))." — an empty scope is not a clean one.\n");
    exit(1);
}

// ---------------------------------------------------------- page -> controller action

$raw = shell_exec('cd /d '.escapeshellarg($root).' && '.escapeshellarg($php).' artisan route:list --json 2>&1');
$routeList = json_decode((string) $raw, true);
if (! is_array($routeList)) {
    fwrite(STDERR, "FATAL: `artisan route:list --json` did not answer with JSON\n");
    exit(1);
}

$pageOwner = [];
foreach ($routeList as $r) {
    if (! preg_match('/^(App\\\\Http\\\\Controllers\\\\[\w\\\\]+)@(\w+)$/', (string) ($r['action'] ?? ''), $m)) {
        continue;
    }
    $file = $root.'/'.str_replace('\\', '/', $m[1]).'.php';
    $body = $methodBody($file, $m[2]);
    if ($body === null) {
        continue;
    }
    if (preg_match_all("/Inertia::render\(\s*'([^']+)'/", $body, $o)) {
        foreach ($o[1] as $rendered) {
            $pageOwner[$rendered] = ['file' => $file, 'method' => $m[2], 'body' => $body, 'route' => ltrim($r['uri'] ?? '?', '/')];
        }
    }
}

$problems = 0;
$judged = 0;
$screens = 0;
$skipped = [];

foreach ($pages as $rel => $src) {
    $pageName = preg_replace('#^Pages/|\.jsx$#', '', $rel);

    // Only a screen that lists a paginated set can answer "which set does this number describe". A record page
    // (Assessment/Show) totals one account and a dashboard filters nothing, so the question does not arise.
    // Skipped screens are named at the end, and a run that judges nothing refuses.
    if (! preg_match('/<Pagination\b|\?\.data\b/', $src)) {
        $skipped[] = $rel;

        continue;
    }

    $cards = $statCards($src);
    if ($cards === []) {
        continue;
    }
    $screens++;
    $loc = $locals($src);
    $props = $propNames($src);
    $owner = $pageOwner[$pageName] ?? null;

    printf("\n%s\n    %s\n", $rel, $owner === null
        ? 'no bound controller action — props come from a route closure or shared Inertia data, so the\n'
            .'    server-side questions (SILENT-ZERO, IMPOSSIBLE) are not asked for this screen; the tile-side\n'
            .'    claims below still are'
        : $oneLine(basename($owner['file']).'::'.$owner['method'].' on /'.$owner['route']));

    $sources = [];
    foreach ($cards as $c) {
        [$kind, $why] = $sourceOf($c['value'], $loc, $props);
        $label = (string) ($c['label'] ?? '(no label)');
        $claimsPage = (bool) preg_match('/page|this screen/i', $label);
        $sources[] = $kind;
        $judged++;

        $note = 'ok';
        if ($kind === 'PAGE' && ! $claimsPage) {
            $note = 'PAGE-TILE — counts the current page; the label names no page';
            $problems++;
        } elseif ($kind === 'UNBOUND') {
            $note = 'UNBOUND — '.$why;
            $problems++;
        }

        printf("  %-7s %-30s %-26s %s\n", $kind, $oneLine(mb_substr($label, 0, 30)), $oneLine(mb_substr($c['value'], 0, 26)), $note);
        if ($debug !== null && str_contains($pageName, $debug)) {
            printf("          traced: %s\n", $oneLine($why));
        }
    }

    $counts = array_count_values($sources);
    // A screen may legitimately show the queue size beside the page's own totals — but only if each number
    // says which it is. The class is about the disagreement the desk cannot see.
    $unlabeledPage = 0;
    foreach ($cards as $i => $c) {
        if ($sources[$i] === 'PAGE' && ! (bool) preg_match('/page|this screen/i', (string) ($c['label'] ?? ''))) {
            $unlabeledPage++;
        }
    }
    if ($unlabeledPage > 0 && (($counts['SET'] ?? 0) > 0 || ($counts['SERVER'] ?? 0) > 0)) {
        printf("  MIXED-SET — %d tile(s) read the paginator total or a server prop while %d read only this page,\n"
            ."                and nothing on the screen tells the desk which is which.\n",
            ($counts['SET'] ?? 0) + ($counts['SERVER'] ?? 0), $unlabeledPage);
        $problems++;
    }

    foreach ($loc as $name => $def) {
        if (! str_ends_with($name, 'Options')) {
            continue;
        }
        if (preg_match('/\?\.data\??\.map|\.data\.map|\.data\?\.flatMap/', $def)) {
            printf("  DEAD-OPTION — the `%s` pick-list is assembled from the rows on this page, so a value that\n"
                ."                only appears on a later page cannot be chosen at all.\n", $name);
            $problems++;
        }
    }

    if ($owner === null) {
        continue;
    }
    if (preg_match_all("/whereIn\(\s*'(\w+)'[^,]*,\s*\[([^\]]*)\]/", $owner['body'], $w, PREG_SET_ORDER)) {
        foreach ($w as $one) {
            [$field, $list] = [$one[1], $one[2]];
            $allowed = [];
            $unbound = false;
            foreach (explode(',', $list) as $case) {
                $case = trim($case);
                // both shapes appear: `EnrollmentStatus::Pending` (the enum case) and
                // `EnrollmentStatus::Pending->value` (its backing string)
                $case = preg_replace('/->value$/', '', $case);
                if ($case === '') {
                    continue;
                }
                if (preg_match('/^(\w+)::(\w+)$/', $case, $cm)
                    && class_exists($fq = 'App\\Enums\\'.$cm[1])
                    && defined($fq.'::'.$cm[2])) {
                    $v = constant($fq.'::'.$cm[2]);
                    $allowed[] = $v instanceof \BackedEnum ? $v->value : (string) $v;

                    continue;
                }
                $unbound = true;
            }
            if ($unbound || $allowed === []) {
                printf("  UNBOUND — `%s` is restricted to [%s] but those enum values could not be resolved, so the\n"
                    ."                impossible-tile question was not asked for this field.\n", $field, $oneLine($list));
                $problems++;

                continue;
            }
            $compared = [];
            if (preg_match_all("/\b".preg_quote($field, '/')."\s*===?\s*'([^']+)'/", $src, $cmp)) {
                $compared = $cmp[1];
            }
            foreach (array_unique($compared) as $v) {
                if (! in_array($v, $allowed, true)) {
                    printf("  IMPOSSIBLE — the page counts `%s === '%s'` while the handler restricts that column\n"
                        ."                 to (%s), so the tile can never leave 0.\n", $field, $v, implode(', ', $allowed));
                    $problems++;
                }
            }
        }
    }
}

// ------------------------------------------------------- server-side aggregate order

printf("\nController aggregate order — is the summary taken before or after the page LIMIT?\n");

$ordered = 0;
// Counted separately from the total so a harness can require THIS verdict to move. A leg that asserts only
// "%d problem(s)" cannot tell a new rule that fires from an old rule that fired twice.
$pinnedSelect = 0;
$pinnedGuarded = 0;
$seen = [];
foreach ($pageOwner as $pageName => $o) {
    $body = $o['body'];
    if (! preg_match_all('/\$?(\w+)->paginate\(/', $body, $pg)) {
        continue;
    }
    foreach (array_unique($pg[1]) as $var) {
        $paginateAt = strpos($body, '$'.$var.'->paginate(');
        if ($paginateAt === false) {
            continue;
        }
        if (! preg_match_all('/\(clone \$'.preg_quote($var, '/').'\)(.{0,400})/s', $body, $cl, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            continue;
        }
        foreach ($cl as $c) {
            if (! preg_match('/selectRaw|->count\(\)|->sum\(|->pluck\(|->first\(\)|groupBy/', $c[1][0])) {
                continue;
            }
            $cloneAt = $c[0][1];
            $key = basename($o['file']).'::'.$o['method'].'#'.$cloneAt;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $ordered++;
            $after = $cloneAt > $paginateAt;
            printf("  %-30s $%-9s cloned %-6s — %s\n", basename($o['file']).'::'.$o['method'], $var,
                $after ? 'AFTER' : 'before', $oneLine(mb_substr($c[1][0], 0, 54)));
            if ($after) {
                printf("    SILENT-ZERO — that clone carries the page's LIMIT/OFFSET, so the aggregate answers\n"
                    ."                 with nothing from page 2 onward. Page 1 cannot show this.\n");
                $problems++;
            }

            // The second half of the same ordering question, and the one that reached a desk as a 500 rather than
            // as a wrong number. `ListSort` joins the table an ordered column sits on and, for a hop, pins the
            // builder's select to the base model's own columns; `selectRaw()` APPENDS. So an aggregate cloned
            // after that call carries `table.*` beside its own column and `ONLY_FULL_GROUP_BY` refuses it on the
            // school's MySQL, while SQLite answers it — which is why this verdict cannot be proved by the suite
            // alone and `mutate-lane47-checker.php` is run against a scratch MySQL schema as well.
            $chain = $c[1][0];
            $orderAt = strpos($body, 'ListSort::apply(');
            $inheritsOrder = $orderAt !== false && $orderAt < $cloneAt;
            $appends = preg_match('/selectRaw\(/', $chain) === 1;
            // `select()` REPLACES the column list, so a chain that names its own columns before appending is safe
            // even when the order ran first — that is the shape the admission queue's status tile now uses.
            $replacesFirst = preg_match('/->select\([^)]*\)\s*(->selectRaw|\->groupBy|\->pluck|\->first|\->count|\->sum)/', $chain) === 1;
            if ($inheritsOrder && $appends && ! $replacesFirst) {
                printf("    PINNED-SELECT — the order already ran on this builder, so its pinned `table.*` rides\n"
                    ."                 into the aggregate that `selectRaw()` appends to. MySQL 8 answers 1055;\n"
                    ."                 SQLite answers the query. Name the columns with `select()` first.\n");
                $pinnedSelect++;
                $problems++;
            } elseif ($inheritsOrder && $appends && $replacesFirst) {
                $pinnedGuarded++;
            }
        }
    }
}
if ($ordered === 0) {
    echo "  UNBOUND — no paginated builder with a cloned aggregate was found, so the ordering question was\n"
        ."          never asked. A checker that reads nothing prints a pass; refuse instead.\n";
    $problems++;
}

if ($skipped !== []) {
    printf("\nSkipped, no paginated list on the screen: %s\n", implode(', ', $skipped));
}

if ($judged === 0) {
    echo "\nREFUSED — no tile was traced, so nothing was checked.\n";
    exit(1);
}

printf("\nScreens judged: %d.  Tiles traced: %d.  Aggregates cloned off a paginated builder: %d"
    ." (PINNED-SELECT %d, plus %d that name their own columns first and are clean).\n  %d problem(s).\n",
    $screens, $judged, $ordered, $pinnedSelect, $pinnedGuarded, $problems);

exit($problems === 0 ? 0 : 1);
