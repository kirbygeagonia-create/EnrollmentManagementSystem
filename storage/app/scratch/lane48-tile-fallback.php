<?php

/**
 * Lane 48 — when a tile's number is not there, what does the desk see?
 *
 * Lane 47 asked which set a number describes. This asks the next question: the number is absent — the handler
 * never returned that key, the aggregate is null, or the request never answered — and the screen still has to
 * paint something. A `?? 0`, a `|| 0`, or a money wrapper that coerces paints a zero, and a zero is a fact the
 * server never told the screen. On a desk it is indistinguishable from a real zero queue: "Php 0.00 assessed"
 * and "the assessable queue could not be counted" render identically, and the second one sends someone to the
 * wrong office.
 *
 *   INVENTED-ZERO     a tile value coerces absence into a number: `stats.x ?? 0`, `a || 0`, or a local the
 *                     tile reads which is defined as `Number(summary.x || 0)`.
 *   UNSAFE-FORMATTER  the screen's own money helper reads `Number(n || 0)`, so a null amount becomes 0.00
 *                     before any call-site default is consulted. Six pages carry a private copy; the checker
 *                     reads each one, because the copies do not behave alike and one written across three lines
 *                     escapes a scanner that stops at the end of a line.
 *   FALLBACK-DERIVED  the fallback supplies a count the screen took from the rows it already holds —
 *                     `serverStats || computedStats`, `enrollments?.total ?? enrollments?.data?.length`. Worse
 *                     than a zero, because it looks like a measured number.
 *   DEAD-KEY          the tile reads a key the bound handler does not answer with — the same defect with its
 *                     cause named: the fallback is not defensive, it is the only value the tile will ever show.
 *   OBJECT-TILE       the value is a whole array, list or record rather than a number off it.
 *   BLANK             the tag carries no value at all.
 *   UNBOUND           the key set could not be resolved from the handler text. Reported and counted against
 *                     the run: an unread claim must never look like a checked one.
 *
 * A real zero stays a zero. Where a controller coalesces `sum()` over an empty set to 0 — Accounting and
 * Assessment both do, in PHP — that 0 came from the server and must render. This refuses only a zero the
 * *screen* made up, which is why the rule is "no numeric default in a value expression", not "no zeros".
 *
 * What this does NOT claim. It resolves a handler's stat keys from the text of the action that renders the
 * page: an inline array, a `selectRaw` alias set, a paginator, and a route-bound model whose keys are that
 * table's columns plus its relations and appended accessors — `student.studentclearances` is a relation, not a
 * column, and a column list alone called it a dead key on a screen that was telling the truth. A value
 * produced by a `pluck`, a helper's return or a relation is DYNAMIC and reported, never guessed. A tile that
 * reads no server number at all (text like "Office: Registrar", or a sum over one record's complete payment
 * list on a page with no pagination) is listed as not judged, because there is no invention in it; the
 * page-versus-set question is lane 47's and a tile fed by fetch() is lane 29's. It does not execute React, so
 * it checks the shared tile for an absence state and refuses the numeric defaults that prevent one.
 *
 * Scope is a flag so a harness can prove the scan is running: EMS_LANE48_PAGES=Accounting,Assessment restricts
 * which page files are judged, and a scope that matches nothing refuses rather than reporting clean.
 *
 * Usage: php storage/app/scratch/lane48-tile-fallback.php [--debug=Accounting]
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

$scope = getenv('EMS_LANE48_PAGES');
$scope = $scope === false || trim($scope) === '' ? null : array_values(array_filter(array_map('trim', explode(',', $scope))));

$oneLine = fn (string $s): string => preg_replace('/\s+/', ' ', $s);

/** Split on top-level commas: quotes and nested brackets cannot open a new piece. */
$splitTopLevel = function (string $e, string $sep = ','): array {
    $parts = [];
    $buf = '';
    $depth = 0;
    $quote = null;
    $chars = preg_split('//', $e, -1, PREG_SPLIT_NO_EMPTY);
    for ($i = 0; $i < count($chars); $i++) {
        $c = $chars[$i];
        if ($quote !== null) {
            $buf .= $c;
            if ($c === $quote && $chars[$i - 1] !== '\\') {
                $quote = null;
            }

            continue;
        }
        if ($c === '"' || $c === "'" || $c === '`') {
            $quote = $c;
            $buf .= $c;

            continue;
        }
        if ($c === '(' || $c === '[' || $c === '{') {
            $depth++;
        } elseif ($c === ')' || $c === ']' || $c === '}') {
            $depth--;
        }
        if ($c === $sep && $depth === 0) {
            $parts[] = $buf;
            $buf = '';

            continue;
        }
        $buf .= $c;
    }
    $parts[] = $buf;

    return array_map('trim', $parts);
};

/**
 * The branches of a top-level conditional, so a test is never read as a fallback.
 *
 * `stats.passRate === null || stats.passRate === undefined ? '—' : …` holds `||` inside the *condition*, and a
 * scanner that judged it would call a guard a fallback. `?` followed by `?` or `.` is optional chaining, not
 * a ternary; nested conditionals are walked with a counter so the matching `:` is the right one.
 */
$ternaryBranches = null;
$ternaryBranches = function (string $e) use (&$ternaryBranches): array {
    $chars = preg_split('//', $e, -1, PREG_SPLIT_NO_EMPTY);
    $depth = 0;
    $quote = null;
    $qAt = null;
    $pending = 0;
    $colonAt = null;
    $skip = 0;
    for ($i = 0; $i < count($chars); $i++) {
        if ($skip > 0) {
            $skip--;

            continue;
        }
        $c = $chars[$i];
        if ($quote !== null) {
            if ($c === $quote && $chars[$i - 1] !== '\\') {
                $quote = null;
            }

            continue;
        }
        if ($c === '"' || $c === "'" || $c === '`') {
            $quote = $c;

            continue;
        }
        if ($c === '(' || $c === '[' || $c === '{') {
            $depth++;

            continue;
        }
        if ($c === ')' || $c === ']' || $c === '}') {
            $depth--;

            continue;
        }
        if ($depth !== 0) {
            continue;
        }
        if ($c === '?' && ! in_array($chars[$i + 1] ?? '', ['?', '.'], true)) {
            if ($qAt === null) {
                $qAt = $i;
            } else {
                $pending++;
            }

            continue;
        }
        if ($c === ':' && $qAt !== null) {
            if ($pending > 0) {
                $pending--;

                continue;
            }
            $colonAt = $i;

            break;
        }
    }
    if ($qAt === null || $colonAt === null) {
        return [];
    }
    $yes = trim(substr($e, $qAt + 1, $colonAt - $qAt - 1));
    $no = trim(substr($e, $colonAt + 1));
    $out = [$yes];
    $nested = $ternaryBranches($no);
    $out = array_merge($out, $nested === [] ? [$no] : $nested);

    return $out;
};

/**
 * Every top-level `??` / `||` of an expression as [left, operator, right].
 *
 * Depth must be tracked through `(`, `[`, `{` and through quotes: `(blocks?.data || []).length` is one
 * expression, and a scanner that split inside those parentheses would judge a fragment of it. A conditional is
 * decomposed first, so only the operands a desk actually sees are scanned. `left` is the whole text before the
 * operator, which is what the tile reads when the operand is present.
 */
$splitOps = function (string $e) use (&$splitOps, $ternaryBranches): array {
    $branches = $ternaryBranches($e);
    if ($branches !== []) {
        $out = [];
        foreach ($branches as $b) {
            $out = array_merge($out, $splitOps($b));
        }

        return $out;
    }

    $cuts = [];
    $depth = 0;
    $quote = null;
    $chars = preg_split('//', $e, -1, PREG_SPLIT_NO_EMPTY);
    $skip = 0;
    for ($i = 0; $i < count($chars); $i++) {
        if ($skip > 0) {
            $skip--;

            continue;
        }
        $c = $chars[$i];
        if ($quote !== null) {
            if ($c === $quote && $chars[$i - 1] !== '\\') {
                $quote = null;
            }

            continue;
        }
        if ($c === '"' || $c === "'" || $c === '`') {
            $quote = $c;

            continue;
        }
        if ($c === '(' || $c === '[' || $c === '{') {
            $depth++;

            continue;
        }
        if ($c === ')' || $c === ']' || $c === '}') {
            $depth--;

            continue;
        }
        if (($c === '|' || $c === '?') && ($chars[$i + 1] ?? '') === $c) {
            $cuts[] = [$i, $c.$c];
            $skip = 1;
        }
    }
    if ($cuts === []) {
        return [[trim($e), null, null]];
    }
    $out = [];
    $prev = 0;
    foreach ($cuts as $n => [$at, $op]) {
        $rightFrom = $at + 2;
        $rightTo = $cuts[$n + 1][0] ?? strlen($e);
        $out[] = [trim(substr($e, $prev, $at - $prev)), $op, trim(substr($e, $rightFrom, $rightTo - $rightFrom))];
        $prev = $rightTo;
    }

    return $out;
};

/** Split a sum or product into its operands, so `totalScholarship + totalWaived` reaches both names. */
$splitArithmetic = function (string $e): array {
    $parts = [];
    $buf = '';
    $depth = 0;
    $quote = null;
    $chars = preg_split('//', $e, -1, PREG_SPLIT_NO_EMPTY);
    for ($i = 0; $i < count($chars); $i++) {
        $c = $chars[$i];
        if ($quote !== null) {
            $buf .= $c;
            if ($c === $quote && $chars[$i - 1] !== '\\') {
                $quote = null;
            }

            continue;
        }
        if ($c === '"' || $c === "'" || $c === '`') {
            $quote = $c;
            $buf .= $c;

            continue;
        }
        if ($c === '(' || $c === '[' || $c === '{') {
            $depth++;
        } elseif ($c === ')' || $c === ']' || $c === '}') {
            $depth--;
        }
        if ($depth === 0 && in_array($c, ['+', '*', '/'], true)) {
            $parts[] = trim($buf);
            $buf = '';

            continue;
        }
        // a `-` is an operator only between two operands, never a sign or a `=>` arrow
        if ($depth === 0 && $c === '-' && preg_match('/[\w\)\]]$/', $buf) && preg_match('/^\s*[\w$"\x27(]/', substr($e, $i + 1))) {
            $parts[] = trim($buf);
            $buf = '';

            continue;
        }
        $buf .= $c;
    }
    $parts[] = trim($buf);
    $parts = array_values(array_filter($parts, static fn (string $p): bool => $p !== ''));

    return count($parts) > 1 ? $parts : [$e];
};

/**
 * Does this expression get its number by counting rows the screen already holds?
 *
 * A fallback to `blocks?.data?.length` or a memo over `rows` replaces an absent server count with a page
 * count, which is the same class of invention as `?? 0`. A fallback to another record field is a text choice
 * and not this lane's question, so only a page-shaped target counts.
 */
$pageDerived = function (string $e, array $loc) use ($oneLine): bool {
    $seen = [];
    $queue = [$e];
    for ($i = 0; $i < 4 && $queue !== []; $i++) {
        $link = array_shift($queue);
        if (in_array($link, $seen, true)) {
            continue;
        }
        $seen[] = $link;
        if ((bool) preg_match('/\.length\b|\?\.data\b|\.data\b|\brows\b|\.reduce\(|\.filter\(|\.map\(/', $link)) {
            return true;
        }
        foreach (preg_match_all('/\$\{([^}]*)\}/', $link, $ti) ? $ti[1] : [] as $interp) {
            $queue[] = $oneLine($interp);
        }
        $head = preg_split('/[.?[\]()]/', ltrim(trim($link), '$'))[0] ?? '';
        // a useMemo body is full of arrow callbacks, and a rule that refused any definition containing `=>`
        // could not see that Clearance's `computedStats` counts the rows on screen
        if ($head !== '' && isset($loc[$head]) && $loc[$head] !== $link) {
            $queue[] = $oneLine(mb_substr($loc[$head], 0, 400));
        }
    }

    return false;
};

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
 * Read one JSX opening tag from `$start` to the `>` that closes it, honouring quotes and brace depth.
 *
 * A regex stops at the first `>`, and a tile with a conditional tone (`iconBg={available > 0 ? … : …}`)
 * carries one inside an expression: the tile with the most interesting value is the one a cheap parser drops.
 */
$openingTag = function (string $src, int $start): ?string {
    $len = strlen($src);
    $depth = 0;
    $quote = null;
    for ($i = $start; $i < $len; $i++) {
        $c = $src[$i];
        if ($quote !== null) {
            if ($c === $quote && $src[$i - 1] !== '\\') {
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

/** The `{…}` group starting at `$from`, both braces included, or null if unbalanced. */
$braceGroup = function (string $s, int $from): ?string {
    $len = strlen($s);
    $depth = 0;
    $quote = null;
    for ($i = $from; $i < $len; $i++) {
        $c = $s[$i];
        if ($quote !== null) {
            if ($c === $quote && $s[$i - 1] !== '\\') {
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
            if ($depth === 0) {
                return substr($s, $from, $i - $from + 1);
            }
        }
    }

    return null;
};

/**
 * The props of one JSX tag as `name => [value text, delimiter]`.
 *
 * `value="…"` and `value={…}` both occur, and the difference is a verdict, not a detail: `value="Registrar"`
 * is a text tile that cannot invent a number, while `value={Registrar}` would be an undefined identifier. A
 * value expression also carries nested braces and quotes (`label={\`${stats.scored} scored\`}`), so each
 * value is consumed by its own delimiter rather than by a regex that stops at the first `}` it likes.
 */
$tagProps = function (string $tag) use ($braceGroup): array {
    $out = [];
    $len = strlen($tag);
    $i = 0;
    while ($i < $len) {
        if (! preg_match('/\G([a-zA-Z_][\w-]*)\s*=\s*/', $tag, $m, PREG_OFFSET_CAPTURE, $i)) {
            $i++;

            continue;
        }
        $name = $m[1][0];
        $at = $m[0][1] + strlen($m[0][0]);
        if ($at >= $len) {
            break;
        }
        $ch = $tag[$at];
        if ($ch === '{') {
            $group = $braceGroup($tag, $at);
            if ($group === null) {
                break;
            }
            $out[$name] = [mb_substr($group, 1, -1), 'brace'];
            $i = $at + strlen($group);

            continue;
        }
        if ($ch === '"' || $ch === "'") {
            $close = strpos($tag, $ch, $at + 1);
            if ($close === false) {
                break;
            }
            $out[$name] = [substr($tag, $at + 1, $close - $at - 1), 'quote'];
            $i = $close + 1;

            continue;
        }
        preg_match('/\G[^\s\/]+/', $tag, $bare, 0, $at);
        $out[$name] = [$bare[0] ?? '', 'bare'];
        $i = $at + strlen($out[$name][0]);
    }

    return $out;
};

/** Every `<StatCard …>` in a page: line, label, value expression and its delimiter. */
$statCards = function (string $src) use ($openingTag, $tagProps): array {
    $out = [];
    $at = 0;
    while (($at = strpos($src, '<StatCard', $at)) !== false) {
        $line = substr_count(substr($src, 0, $at), "\n") + 1;
        $tag = $openingTag($src, $at);
        if ($tag === null) {
            break;
        }
        $p = $tagProps($tag);
        $out[] = [
            'line' => $line,
            'label' => isset($p['label']) && trim($p['label'][0]) !== '' ? $p['label'][0] : '(no label)',
            'value' => isset($p['value']) ? trim($p['value'][0]) : null,
            'kind' => $p['value'][1] ?? null,
        ];
        $at += strlen($tag) + 1;
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
    // a formatter written across lines is still one definition, and a scanner that reads only to the end of
    // the line loses the very body whose coercion this lane refuses — the null-safe `peso` is three lines
    if (preg_match_all('/const (\w+) = (.*?);/s', $src, $o, PREG_SET_ORDER)) {
        foreach ($o as $m) {
            $map[$m[1]] ??= $m[2];
        }
    }

    return $map;
};

/**
 * `local name => server prop key` for the props the default export destructures.
 *
 * `{ stats: serverStats = null }` reads the alias, so a local name is not the server's key. The list is
 * scanned with brace depth and split on top-level commas: `[^}]*` would end it at the first default value and
 * every prop after that would read as UNBOUND — a screen full of UNBOUND is how a real finding gets lost.
 */
$propMap = function (string $src): array {
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
    if ($params === null || ! preg_match('/^\s*\{(.*)\}\s*$/s', $params, $b)) {
        return [];
    }

    $pieces = [];
    $depth = 0;
    $current = '';
    $quote = null;
    $text = $b[1];
    for ($i = 0; $i < strlen($text); $i++) {
        $c = $text[$i];
        if ($quote !== null) {
            if ($c === $quote) {
                $quote = null;
            }

            continue;
        }
        if ($c === '"' || $c === "'") {
            $quote = $c;

            continue;
        }
        if ($c === '{' || $c === '[' || $c === '(') {
            $depth++;
        } elseif ($c === '}' || $c === ']' || $c === ')') {
            $depth--;
        }
        if ($c === ',' && $depth === 0) {
            $pieces[] = $current;
            $current = '';

            continue;
        }
        $current .= $c;
    }
    $pieces[] = $current;

    $map = [];
    foreach ($pieces as $piece) {
        $decl = trim(preg_replace('/=.*$/', '', trim($piece), 1));
        if ($decl === '') {
            continue;
        }
        if (str_contains($decl, ':')) {
            [$server, $local] = array_map('trim', explode(':', $decl, 2));
            if ($local !== '') {
                $map[$local] = $server;
            }

            continue;
        }
        if (preg_match('/^\w+$/', $decl)) {
            $map[$decl] = $decl;
        }
    }

    return $map;
};

/**
 * Top-level `'key' => value` pairs of a PHP array literal, quote- and bracket-aware.
 *
 * Nested commas must not split a pair: `'filters' => $request->only(['search', 'queue'])` holds two commas
 * inside one value, and a naive split turns `queue'])` into a key.
 */
$arrayPairs = function (string $lit): array {
    $pairs = [];
    $flush = function (string $t) use (&$pairs): void {
        // the literal's own opening `[` is glued onto the first pair's text, and a scanner that requires the
        // token to start with `'key' =>` silently loses that pair — which reads as a DEAD-KEY for the first
        // stat the handler sends
        $t = preg_replace('/^\s*\[/', '', trim($t), 1);
        if (preg_match("/^'(\w+)'\s*=>\s*(.*)$/s", trim($t), $m)) {
            $pairs[$m[1]] = trim($m[2]);
        }
    };
    $len = strlen($lit);
    $depth = 0;
    $quote = null;
    $buf = '';
    for ($i = 0; $i < $len; $i++) {
        $c = $lit[$i];
        if ($quote !== null) {
            $buf .= $c;
            if ($c === $quote && $lit[$i - 1] !== '\\') {
                $quote = null;
            }

            continue;
        }
        if ($c === "'" || $c === '"') {
            $quote = $c;
            $buf .= $c;

            continue;
        }
        if ($c === '[' || $c === '(' || $c === '{') {
            $depth++;
        } elseif ($c === ']' || $c === ')' || $c === '}') {
            $depth--;
        }
        if ($c === ',' && $depth === 1) {
            $flush($buf);
            $buf = '';

            continue;
        }
        $buf .= $c;
    }
    $flush($buf);

    return $pairs;
};

/** The bracketed group starting at `$from` (brackets included), or null if unbalanced. */
$bracketGroup = function (string $s, int $from): ?string {
    $len = strlen($s);
    $depth = 0;
    $quote = null;
    for ($i = $from; $i < $len; $i++) {
        $c = $s[$i];
        if ($quote !== null) {
            if ($c === $quote && $s[$i - 1] !== '\\') {
                $quote = null;
            }

            continue;
        }
        if ($c === "'" || $c === '"') {
            $quote = $c;

            continue;
        }
        if ($c === '[' || $c === '(' || $c === '{') {
            $depth++;

            continue;
        }
        if ($c === ']' || $c === ')' || $c === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($s, $from, $i - $from + 1);
            }
        }
    }

    return null;
};

/** The right-hand side assigned to one variable in a method body, up to the statement's `;`. */
$stmtRhs = function (string $body, string $var): ?string {
    if (! preg_match('/\$'.preg_quote($var, '/').'\s*=\s*/', $body, $m, PREG_OFFSET_CAPTURE)) {
        return null;
    }
    $at = $m[0][1] + strlen($m[0][0]);
    $len = strlen($body);
    $depth = 0;
    $quote = null;
    for ($i = $at; $i < $len; $i++) {
        $c = $body[$i];
        if ($quote !== null) {
            if ($c === $quote && $body[$i - 1] !== '\\') {
                $quote = null;
            }

            continue;
        }
        if ($c === "'" || $c === '"' || $c === '`') {
            $quote = $c;

            continue;
        }
        if ($c === '[' || $c === '(' || $c === '{') {
            $depth++;

            continue;
        }
        if ($c === ']' || $c === ')' || $c === '}') {
            $depth--;

            continue;
        }
        if ($c === ';' && $depth === 0) {
            return substr($body, $at, $i - $at);
        }
    }

    return null;
};

/**
 * The typed parameters of a controller action as `$name => FQCN`.
 *
 * A record page's props are the route-bound model itself (`show(Enrollments $enrollment)`), and nothing in the
 * body assigns it, so without the signature its tiles read as UNBOUND forever. With it, `enrollment.anyField`
 * can be bound to the table's real columns.
 */
$actionParams = function (string $file, string $method): array {
    static $cache = [];
    $k = $file.'#'.$method;
    if (array_key_exists($k, $cache)) {
        return $cache[$k];
    }
    $src = @file_get_contents($file);
    if ($src === false || ! preg_match('/function '.preg_quote($method, '/').'\s*\(([^)]*)\)/', $src, $m)) {
        return $cache[$k] = [];
    }
    $uses = [];
    if (preg_match_all('/^use (App\\\\[\w\\\\]+);/m', $src, $u)) {
        foreach ($u[1] as $fq) {
            $uses[basename(str_replace('\\', '/', $fq))] = str_replace('/', '\\', $fq);
        }
    }
    $out = [];
    foreach (explode(',', $m[1]) as $param) {
        // `(?:\\)?` is an optional leading backslash — a single-quoted `'\\?'` would be a literal `?` to PCRE,
        // and that version of this pattern matched no typed parameter at all, leaving every record page UNBOUND
        if (preg_match('~^\s*(?:\\\\)?([A-Z]\w*)\s+\$(\w+)~', $param, $p)) {
            $fq = $uses[$p[1]] ?? ('App\\Models\\'.$p[1]);
            if (class_exists($fq)) {
                $out[$p[2]] = $fq;
            }
        }
    }

    return $cache[$k] = $out;
};

/**
 * A model's table columns, read from the live schema; empty means unreadable, never "no columns".
 */
$columnsOf = function (string $classOrTable): array {
    $table = $classOrTable;
    if (class_exists($classOrTable)) {
        try {
            $table = (new $classOrTable)->getTable();
        } catch (\Throwable) {
            return [];
        }
    }
    try {
        if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
            return [];
        }

        return \Illuminate\Support\Facades\Schema::getColumnListing($table);
    } catch (\Throwable) {
        return [];
    }
};

/**
 * What a record prop answers with: its table's columns, plus the relation and accessor names hung off it.
 *
 * `enrollment.enrolledSubjects` and `student.studentclearances` are not columns, and a column list alone calls
 * them DEAD-KEY — a false finding on a real screen. `studentclearances()` delegates to `clearances()`, so a
 * body-pattern match misses it and the declared return type is what finds it. A tile may read any of the three
 * kinds, so all three are the key set.
 */
$modelKeys = function (string $class) use ($columnsOf): array {
    $cols = $columnsOf($class);
    $rel = [];
    $src = '';
    try {
        $reflection = new ReflectionClass($class);
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
            $rt = $m->getReturnType();
            if (! $rt instanceof ReflectionNamedType) {
                continue;
            }
            $type = $rt->getName();
            if (is_subclass_of($type, \Illuminate\Database\Eloquent\Relations\Relation::class)
                || is_subclass_of($type, \Illuminate\Database\Eloquent\Casts\Attribute::class)) {
                $rel[] = $m->getName();
            }
        }
        $src = (string) @file_get_contents((string) $reflection->getFileName());
    } catch (\Throwable) {
        $rel = [];
    }
    // a relation declared without a native return type still returns `$this->hasMany(…)`
    if ($src !== '' && preg_match_all('/function\s+(\w+)\s*\([^)]*\)[^{]*\{\s*return\s+\$this->(hasMany|belongsTo|hasOne|belongsToMany|morphMany|morphOne|hasManyThrough|hasOneThrough|morphToMany)\b/s', $src, $rm)) {
        $rel = array_merge($rel, $rm[1]);
    }
    // an appended accessor arrives in the JSON but is not a column
    if ($src !== '' && preg_match('/\$appends\s*=\s*\[(.*?)\];/s', $src, $ap)) {
        preg_match_all("/['\"](\w+)['\"]/", $ap[1], $am);
        $rel = array_merge($rel, $am[1]);
    }

    return array_values(array_unique(array_merge($cols, $rel)));
};

/**
 * What one render-array value answers with: a class and its keys, or the text that could not be read.
 *
 * STATIC is an inline array, ALIAS a `selectRaw(...)` row whose keys are its SQL aliases, PAGINATOR Laravel's
 * fixed `data`/`total` shape, MODEL a route-bound record whose keys are its table columns, LIST an Eloquent
 * collection (so `x.length` is a list, not a key set), and DYNAMIC/UNBOUND anything whose keys are not in the
 * text — reported, never guessed, because a checker that invents a key set will happily clear a dead key.
 */
$classify = null;
$classify = function (string $valueText, string $body, array $modelVars = []) use (&$classify, $arrayPairs, $bracketGroup, $stmtRhs, $oneLine, $columnsOf, $modelKeys): array {
    $valueText = trim($valueText);
    if ($valueText === '') {
        return ['UNBOUND', '(empty)'];
    }

    if ($valueText[0] === '[') {
        $group = $bracketGroup($valueText, 0);

        return $group === null ? ['UNBOUND', $oneLine($valueText)] : ['STATIC', array_keys($arrayPairs($group))];
    }

    if (preg_match('/^\$(\w+)$/', $valueText, $vm)) {
        $var = $vm[1];
        // a route-bound record: its keys are the table's columns plus its relation names
        if (isset($modelVars[$var])) {
            $cols = $modelKeys($modelVars[$var]);

            return $cols === [] ? ['UNBOUND', '$'.$var.' is a '.basename(str_replace('\\', '/', $modelVars[$var])).', whose columns this box cannot read'] : ['MODEL', $cols];
        }
        $rhs = $stmtRhs($body, $var);
        if ($rhs === null) {
            return ['DYNAMIC', '$'.$var.' is not assigned inside this action'];
        }
        $rhsOne = $oneLine($rhs);
        $trimmed = ltrim($rhs);
        if ($trimmed !== '' && $trimmed[0] === '[') {
            $group = $bracketGroup($trimmed, 0);
            if ($group !== null) {
                return ['STATIC', array_keys($arrayPairs($group))];
            }
        }
        // `$enrollment->idrequests->first()` — a related record whose keys are that table's columns. The
        // relation names in this repository are the plural table names, and hasTable() decides, so an
        // unrecognised one stays UNBOUND instead of guessing a schema.
        if (preg_match('/^\$(\w+)->(\w+)\?->first\(\)$|^\$(\w+)->(\w+)->first\(\)$/', $rhsOne, $rr)) {
            $base = ($rr[1] ?? '') !== '' ? $rr[1] : ($rr[3] ?? '');
            $rel = ($rr[2] ?? '') !== '' ? $rr[2] : ($rr[4] ?? '');
            if (isset($modelVars[$base])) {
                // ask the relation itself which model it points at; the table name is the fallback guess
                $related = null;
                try {
                    $related = (new $modelVars[$base])->{$rel}()->getRelated();
                } catch (\Throwable) {
                    $related = null;
                }
                $cols = $related !== null ? $modelKeys(get_class($related)) : $columnsOf($rel);
                if ($cols !== []) {
                    return ['MODEL', $cols];
                }
            }

            return ['UNBOUND', $rhsOne];
        }
        if (preg_match('/selectRaw/i', $rhsOne) && preg_match_all('/\bas\s+`?(\w+)/i', $rhsOne, $al) && $al[1] !== []) {
            return ['ALIAS', $al[1]];
        }
        if (preg_match('/->paginate\(/', $rhsOne)) {
            return ['PAGINATOR', ['data', 'total', 'per_page', 'current_page', 'last_page', 'links']];
        }
        // a pipeline that ends in a number is a number, whatever it plucked on the way
        if ((bool) preg_match('/->(count|sum|avg|max|min)\(\)\s*;?\s*$/', $rhsOne) || (bool) preg_match('/^\(int\)|^\(float\)|->count\(\)$/', $rhsOne)) {
            return ['SCALAR', $rhsOne];
        }
        if (preg_match('/->get\(\)|::all\(|->pluck\(|ResourceCollection/', $rhsOne)) {
            return ['LIST', $rhsOne];
        }
        if (preg_match('/^this->\w+\(/', $rhsOne) || preg_match('/::\w+\(/', $rhsOne)) {
            return ['DYNAMIC', '$'.$var.' = '.$rhsOne];
        }
        // a scalar or a ternary: no key set, so nothing to bind a `prop.key` tile to
        return ['SCALAR', $rhsOne];
    }

    return ['UNBOUND', $oneLine(mb_substr($valueText, 0, 70))];
};

/** The keys this page's handler answers with, per top-level prop. */
$serverMap = function (string $body, array $modelVars = []) use ($classify, $arrayPairs, $bracketGroup): array {
    if (! preg_match("/Inertia::render\(\s*'[^']+'\s*,\s*\[/", $body, $m, PREG_OFFSET_CAPTURE)) {
        return [];
    }
    $literal = $bracketGroup($body, $m[0][1] + strlen($m[0][0]) - 1);
    if ($literal === null) {
        return [];
    }

    $map = [];
    foreach ($arrayPairs($literal) as $key => $valueText) {
        $map[$key] = $classify($valueText, $body, $modelVars);
    }

    return $map;
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
    fwrite(STDERR, 'REFUSED: no page file carries a StatCard'.($scope === null ? ' anywhere in resources/js/Pages' : ' under EMS_LANE48_PAGES='.implode(',', $scope))." — an empty scope is not a clean one.\n");
    exit(1);
}

// ------------------------------------------------- the shared tile: does absence have a state?

$shared = (string) file_get_contents($root.'/resources/js/Components/ui/StatCard.jsx');
$sharedDashes = str_contains($shared, "'—'");

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
$invented = 0;
$derivedTiles = 0;
$textTiles = 0;
$formatterScreens = 0;
$deadKeys = [];
$unread = [];
$unnumbered = [];

foreach ($pages as $rel => $src) {
    $pageName = preg_replace('#^Pages/|\.jsx$#', '', $rel);
    $cards = $statCards($src);
    if ($cards === []) {
        continue;
    }
    $owner = $pageOwner[$pageName] ?? null;
    $loc = $locals($src);
    $props = $propMap($src);
    $serverKeys = $owner === null ? [] : $serverMap($owner['body'], $actionParams($owner['file'], $owner['method']));

    $formatter = null;
    foreach ($loc as $n => $def) {
        if (str_contains($def, '=>') && preg_match('/peso|money|amount/i', $n)) {
            $formatter = [$n, $def];

            break;
        }
    }
    $formatterUnsafe = $formatter !== null && (bool) preg_match('/\|\|\s*0|\?\?\s*0|\|\|\s*\'\'/', $formatter[1]);

    $screens++;
    printf("\n%s\n    %s%s\n", $rel,
        $owner === null ? 'no bound controller action — these tiles cannot be bound to a handler'
            : $oneLine(basename($owner['file']).'::'.$owner['method'].' on /'.$owner['route']),
        $formatterUnsafe ? "\n    UNSAFE-FORMATTER  ".$formatter[0].'() = '.$oneLine(mb_substr($formatter[1], 0, 96)) : '');
    if ($debug !== null && str_contains($pageName, $debug)) {
        printf("        props: %s\n", implode(', ', array_keys($props)));
        $mp = [];
        foreach ($owner === null ? [] : $actionParams($owner['file'], $owner['method']) as $v => $c) {
            $mp[] = basename(str_replace('\\', '/', $c)).' $'.$v;
        }
        printf("        model params: %s\n", $mp === [] ? '(none resolved)' : implode(', ', $mp));
        foreach ($serverKeys as $k => [$cls, $keys]) {
            printf("        server %-16s %-9s %s\n", $k, $cls, $oneLine(mb_substr(is_array($keys) ? implode(', ', $keys) : (string) $keys, 0, 70)));
        }
    }
    if ($formatterUnsafe) {
        $formatterScreens++;
        $problems++;
    }

    foreach ($cards as $c) {
        $judged++;
        if ($c['value'] === null || trim($c['value']) === '') {
            printf("  %-6s %-26s %-24s BLANK — the tag carries no value at all\n", 'L'.$c['line'],
                $oneLine(mb_substr($c['label'], 0, 26)), '(none)');
            $problems++;

            continue;
        }
        $flat = $oneLine(trim($c['value']));

        if ($c['kind'] === 'quote') {
            printf("  %-6s %-26s %-24s TEXT — a quoted string, so this tile invents no number\n", 'L'.$c['line'],
                $oneLine(mb_substr($c['label'], 0, 26)), $oneLine(mb_substr($flat, 0, 24)));
            $textTiles++;

            continue;
        }

        // Resolve the expression the way a reader would: jump a call wrapper to its argument, a template
        // literal into its interpolations, an arithmetic sum into its operands, and a local name into its
        // definition — breadth-first, because `peso(totalScholarship + totalWaived)` has to reach both.
        $chain = [];
        $queue = [$flat];
        for ($hop = 0; $hop < 9 && $queue !== []; $hop++) {
            $link = array_shift($queue);
            if (in_array($link, $chain, true)) {
                continue;
            }
            $chain[] = $link;

            // `${a} Subjects (${b} Units)` — the text is decoration, the numbers are inside
            if (preg_match_all('/\$\{([^}]*)\}/', $link, $ti) && $ti[1] !== []) {
                foreach ($ti[1] as $interp) {
                    $queue[] = $oneLine($interp);
                }
                continue;
            }

            // a call wrapper is not a source: `peso(x)`, `Number(y || 0)`, `Math.max(0, stats.available)`
            if (preg_match('/^([\w.]+)\s*\((.*)\)$/s', $link, $cw) && ! (isset($loc[$cw[1]]) && ! str_contains($loc[$cw[1]], '=>'))) {
                foreach ($splitTopLevel($cw[2]) as $arg) {
                    if ($arg === '' || preg_match('/^[0-9\x27"`[]/', $arg) || in_array($arg, ['null', 'undefined', 'true', 'false'], true)) {
                        continue;
                    }
                    $queue[] = $arg;

                    continue 2;
                }
            }

            // an arithmetic composition: every operand is a candidate source
            $operands = $splitArithmetic($link);
            if (count($operands) > 1) {
                foreach ($operands as $o) {
                    $queue[] = $o;
                }
                continue;
            }

            $head = preg_split('/[.?[\]()]/', ltrim(trim($link), '$'))[0] ?? '';
            if ($head === '' || ! isset($loc[$head]) || $loc[$head] === $link) {
                continue;
            }
            $def = $loc[$head];
            if (str_contains($def, '=>')) {
                continue;
            }

            // `X.Y` where X is a memo returning an object: ask that memo what it answers for Y
            if (preg_match('/^(\w+)\??\.(\w+)$/', $link, $mm) && str_contains($def, 'return')) {
                if (preg_match('/return\s*(\{.*\})\s*[;)]?\s*$/s', $def, $rr)
                    && preg_match('/(?<![.\w])'.preg_quote($mm[2], '/')."\s*:\s*([^\n,]+)/", $rr[1], $seg)) {
                    $queue[] = $oneLine($seg[1]);

                    continue;
                }
                $queue[] = $oneLine($def);

                continue;
            }

            $queue[] = $oneLine(trim($def, ' ,{}'));
        }

        // Q1 — does the expression hand the desk a number the server never sent?
        $coerceAt = null;
        $derived = null;
        foreach ($chain as $link) {
            foreach ($splitOps($link) as $pair) {
                [$left, $op, $right] = $pair;
                if ($right === null) {
                    continue;
                }
                if ((bool) preg_match('/^0(\.0+)?$/', $right)) {
                    $coerceAt = $left.' '.$op.' 0';

                    break 2;
                }
                if ($right === '' || preg_match("/^['\"`].*['\"`]$/s", $right)
                    || in_array($right, ['null', 'undefined', 'true', 'false'], true)) {
                    continue;
                }
                // only a fallback that replaces the missing number with one the screen counted is this lane's
                // class; `status || otherStatus` is a text choice and belongs to a different question
                if ($pageDerived($right, $loc)) {
                    $derived = $left.' '.$op.' '.mb_substr($right, 0, 34);

                    break 2;
                }
            }
        }
        $usesFormatter = $formatter !== null && (bool) preg_match('/\b'.preg_quote((string) $formatter[0], '/').'\s*\(/', $flat);
        if ($usesFormatter && $formatterUnsafe && $coerceAt === null && $derived === null) {
            $coerceAt = $formatter[0].'() turns a null amount into 0';
        }

        [$prop, $key] = [null, null];
        $paths = [];
        foreach ($chain as $link) {
            $pp = [];
            preg_match_all('/\b(\w+)\s*\??\.\s*(\w+)/', $link, $pp, PREG_SET_ORDER);
            foreach ($pp as $one) {
                $paths[] = [$one[1], $one[2]];
            }
            $bb = [];
            preg_match_all('/\b(\w+)\s*\??\[\s*([^\]]+?)\s*\]/', $link, $bb, PREG_SET_ORDER);
            foreach ($bb as $one) {
                $paths[] = [$one[1], '['.trim($one[2], "'\" ").']'];
            }
        }
        // a JS array/string method is never a server key: `summary.length`, `periods.filter`, `list.map`
        $accessors = ['length', 'size', 'map', 'filter', 'slice', 'some', 'every', 'reduce', 'join', 'split',
            'trim', 'toUpperCase', 'toLowerCase', 'push', 'find', 'findIndex', 'includes', 'flat', 'flatMap', 'keys', 'values', 'entries'];
        foreach ($paths as [$name, $sub]) {
            if (in_array($sub, $accessors, true)) {
                continue;
            }
            if (array_key_exists($name, $props)) {
                [$prop, $key] = [$props[$name], $sub];

                break;
            }
            // a local that IS a prop, with or without a fallback — `const stats = serverStats || computedStats`
            // — carries that prop's keys, so `stats.pending` is a claim about the handler's `stats`. A local
            // assembled from several sources is not, and pairing it produced `summary.length`-shaped noise.
            if (isset($loc[$name])) {
                $ops = $splitOps((string) $loc[$name]);
                $headOp = trim(preg_replace('/\?\.\w+/', '', $ops[0][0]));
                if (array_key_exists($headOp, $props)) {
                    [$prop, $key] = [$props[$headOp], $sub];

                    break;
                }
            }
        }
        if ($prop === null) {
            // a tile that reads a whole prop as its value: `value={capacity}`
            foreach ($chain as $link) {
                $one = trim($link);
                if (preg_match('/^(\w+)$/', $one) && array_key_exists($one, $props)) {
                    [$prop, $key] = [$props[$one], null];

                    break;
                }
            }
        }

        $verdict = 'ok';
        if ($coerceAt !== null) {
            $verdict = 'INVENTED-ZERO — supplies `0` for an absent number: '.$oneLine(mb_substr($coerceAt, 0, 54));
            $invented++;
            $problems++;
        } elseif ($derived !== null) {
            $verdict = 'FALLBACK-DERIVED — falls back to a value the screen computed: '.$oneLine(mb_substr($derived, 0, 54));
            $derivedTiles++;
            $problems++;
        }

        if ($prop !== null && isset($serverKeys[$prop])) {
            [$cls, $keys] = $serverKeys[$prop];
            $readable = ['STATIC', 'ALIAS', 'PAGINATOR', 'MODEL', 'SCALAR'];
            if ($key === null) {
                if ($cls === 'SCALAR') {
                    $verdict = $verdict === 'ok' ? 'ok — the whole prop is the number the server sent' : $verdict;
                } elseif (in_array($cls, ['STATIC', 'LIST', 'MODEL'], true)) {
                    $verdict = 'OBJECT-TILE — the value is the whole `'.$prop.'` '.$cls.', which is not a number: '
                        .(is_array($keys) ? implode(', ', $keys) : (string) $keys);
                    $problems++;
                } else {
                    $unread[] = $rel.': reads the whole `'.$prop.'` prop, answered as '.$cls;
                    $verdict = $verdict === 'ok' ? 'UNBOUND — reads the whole `'.$prop.'` prop ('.$cls.')' : $verdict;
                    $problems++;
                }
            } elseif (! str_starts_with($key, '[')) {
                if (! in_array($cls, $readable, true)) {
                    $note = $prop.'.'.$key.' <- '.$cls.': '.(is_array($keys) ? implode(', ', $keys) : (string) $keys);
                    $unread[] = $rel.': '.$note;
                    $verdict = $verdict === 'ok' ? 'UNBOUND — '.$note : $verdict.'  (also '.$note.')';
                    $problems++;
                } elseif (! in_array($key, (array) $keys, true)) {
                    $note = 'DEAD-KEY — '.$prop.'.'.$key.' is not in the '.$cls.' keys this handler answers with ('
                        .implode(', ', array_slice((array) $keys, 0, 14)).')';
                    $verdict = $verdict === 'ok' ? $note : $verdict.'; and '.$note;
                    $deadKeys[] = $rel.' '.$prop.'.'.$key;
                    $problems++;
                }
            } else {
                // `queueCounts[q.key]` — the key is chosen at runtime by the page's own tab list, which is a
                // `key:` literal set that also matches DataTable column definitions. Judging it would compare
                // two different lists, so the keys the handler answers with are printed for a reader and the
                // tile is judged only by the fallback rule above.
                if (! in_array($cls, ['STATIC', 'ALIAS', 'MODEL', 'PAGINATOR'], true)) {
                    $unread[] = $rel.': '.$prop.'['.$key.'] keyed lookup, handler answers as '.$cls;
                    if ($verdict === 'ok') {
                        $verdict = 'UNBOUND — a keyed lookup into a '.$cls.' prop cannot be bound';
                    }
                    $problems++;
                } elseif ($verdict === 'ok') {
                    $verdict = 'ok — keyed lookup, the handler answers with: '.implode(', ', array_slice((array) $keys, 0, 8));
                }
            }
        } elseif ($prop === null && $owner !== null) {
            if ($coerceAt === null && $derived === null) {
                // Q2 has nothing to ask: no destructured prop is in the chain, so this tile carries no server
                // number at all — it is text, or a count of this record's own rows. Listed, not judged.
                $unnumbered[] = $rel.':L'.$c['line'].' '.mb_substr($flat, 0, 48);
                $verdict = 'ok — no server number in this tile (text or composed on the screen)';
            } else {
                $verdict .= '  (no prop path either)';
            }
        }

        printf("  %-6s %-26s %-24s %s\n", 'L'.$c['line'], $oneLine(mb_substr($c['label'], 0, 26)),
            $oneLine(mb_substr($flat, 0, 24)), $verdict);
        if ($debug !== null && str_contains($pageName, $debug)) {
            printf("        chain: %s\n", implode('  ->  ', $chain));
        }
    }
}

printf("\nShared tile (Components/ui/StatCard.jsx): %s\n", $sharedDashes
    ? 'renders a dash for an absent number'
    : 'NO absence state — an absent value reaches the desk as a blank, or as whatever the caller defaulted to');
if (! $sharedDashes) {
    $problems++;
}

if ($judged === 0) {
    echo "\nREFUSED — no tile was read, so nothing was checked.\n";
    exit(1);
}

if ($unread !== []) {
    echo "\nUNBOUND, each counted against the run:\n";
    foreach (array_unique($unread) as $u) {
        echo '  '.$u."\n";
    }
}

printf("\nScreens: %d.  Tiles read: %d.  Text tiles (no number to invent): %d.  Tiles with no server number at all: %d.\n"
    ."Invented a number: %d.  Fell back to a screen-computed value: %d.  Unsafe money formatters: %d.  Dead keys: %d.\n  %d problem(s).\n",
    $screens, $judged, $textTiles, count($unnumbered), $invented, $derivedTiles, $formatterScreens, count($deadKeys), $problems);

if ($unnumbered !== []) {
    echo "\nNot judged (a tile that reads no server number cannot invent one):\n  ".implode("\n  ", $unnumbered)."\n";
}

exit($problems === 0 ? 0 : 1);
