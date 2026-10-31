<?php

/**
 * Lane 116: census the shared tables that offer no order, column by column (read-only).
 *
 * §28.3 G-40's remaining half is "28 of the 36 pages that render DataTable send no sort". That figure is a page
 * count; wiring a screen needs the *column* count and, per column, whether the handler's own query can answer the
 * order — the rule the ENUM ruling of 2026-10-31 set (offer a column whose MySQL type the server sorts, withhold a
 * column painted per row or derived in the browser). This tool reads each page's column list, marks which entries
 * already carry a sortKey, and binds the page to the action that renders it, so the next batch is a queue with
 * counts rather than a guess.
 *
 * It prints every row it judges and refuses when the population is empty (an unreadable page is a REFUSED, never a
 * clean census). Nothing here decides whether a column SHOULD be sortable — that is the handler's query and the
 * migration's type, read per screen when the screen is wired.
 *
 * Usage: php storage/app/scratch/lane116-census-the-unwired-tables.php [--json=<path>] [--all]
 */
$root = dirname(__DIR__, 3);
$pages = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/resources/js/Pages', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->isFile() && str_ends_with($f->getFilename(), '.jsx')) {
        $pages[] = str_replace('\\', '/', substr($f->getPathname(), strlen($root.'/resources/js/Pages/')));
    }
}
sort($pages);

$showAll = in_array('--all', $argv, true);
$jsonOut = null;
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with($a, '--json=')) {
        $jsonOut = substr($a, 7);
    } elseif (! in_array($a, ['--all'], true)) {
        fwrite(STDERR, "REFUSED: unknown argument {$a}; this tool reads --all and --json=<path>.\n");

        exit(1);
    }
}

// Which component name does each page answer with? The Inertia::render string is the join to the controller.
$renders = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app/Http/Controllers', FilesystemIterator::SKIP_DOTS)) as $f) {
    if (! $f->isFile() || ! str_ends_with($f->getFilename(), '.php')) {
        continue;
    }
    $body = (string) file_get_contents($f->getPathname());
    $offset = 0;
    while (preg_match('/Inertia::render\(\s*[\'"]([^\'"]+)[\'"]/', $body, $m, PREG_OFFSET_CAPTURE, $offset) === 1) {
        $offset = $m[0][1] + strlen($m[0][0]);
        $before = substr($body, 0, $m[0][1]);
        preg_match_all('/\n    (?:public|private|protected) function (\w+)\s*\(/', $before, $mm);
        $action = $mm[1] ? end($mm[1]) : '?';
        // $m[1] is [string, offset] under PREG_OFFSET_CAPTURE, so the NAME is $m[1][0]. Reading $m[1][1] keyed the
        // whole map by byte offsets and every page answered "(no render found)" — a census whose join read nothing.
        $renders[$m[1][0]][] = [basename($f->getPathname()), $action];
    }
}

$rows = [];
foreach ($pages as $page) {
    $src = (string) file_get_contents($root.'/resources/js/Pages/'.$page);
    if (str_contains($src, '<DataTable') === false) {
        continue;
    }
    $wired = str_contains($src, 'onSort={') || str_contains($src, 'sortParams(');

    // The column list, however the page spells it: `const columns = [...]`, or `const columns = useMemo(() => [...], [deps])`.
    // A flat regex ending at the first `];` matches neither, and matching nothing while printing "0 columns" is the
    // defect this repository has refused in lanes 46, 50 and 90 — so the block is walked by its own brackets and a
    // page whose list cannot be read is reported UNREAD, never zero.
    [$labels, $sortKeys, $unread, $shape] = columnsOf($src);
    preg_match('/rows=\{([^}]+)\}/u', $src, $r);

    $component = str_replace('.jsx', '', $page);
    $owners = $renders[$component] ?? [];

    $rows[] = [
        'page' => $page,
        'wired' => $wired,
        'columns' => count($labels),
        'sortKeys' => $sortKeys,
        'unread' => $unread,
        'shape' => $shape,
        'rows' => trim($r[1] ?? '?'),
        'labels' => $labels,
        'actions' => array_map(fn ($o) => $o[0].'::'.$o[1].'()', $owners),
    ];
}

/**
 * Extract one page's column list: its labels, how many entries already carry a sortKey, and whether the block could
 * be read at all.
 *
 * @return array{0: list<string>, 1: int, 2: bool}
 */
function columnsOf(string $src): array
{
    if (preg_match('/(?:const|let)\s+columns\s*=/u', $src, $m, PREG_OFFSET_CAPTURE) !== 1) {
        // Four of these pages hand the shared table an inline literal (`columns={[…]}` built inside the render,
        // usually one row per charge or schedule). That is a different shape, not a failure to read it, so it is
        // named — a census that calls an unspelled list "UNREAD" sends the next reader to the wrong file.
        if (preg_match('/columns=\{\s*\[/u', $src) === 1) {
            return [[], 0, false, 'inline'];
        }

        return [[], 0, true, 'none'];
    }
    $i = strpos($src, '[', $m[0][1] + strlen($m[0][0]));
    if ($i === false) {
        return [[], 0, true, 'unbracketed'];
    }

    $depth = 0;
    $end = null;
    $quote = null;
    $len = strlen($src);
    for ($j = $i; $j < $len; $j++) {
        $c = $src[$j];
        if ($quote !== null) {
            if ($c === '\\') {
                $j++;

                continue;
            }
            if ($c === $quote) {
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
        } elseif ($c === ']' || $c === ')' || $c === '}') {
            $depth--;
            if ($depth === 0) {
                $end = $j;

                break;
            }
        }
    }
    if ($end === null) {
        return [[], 0, true, 'unbalanced'];
    }

    $block = substr($src, $i + 1, $end - $i - 1);
    preg_match_all("/key:\s*('|\")([^'\"]+)\\1/su", $block, $k);

    return [$k[2], preg_match_all('/sortKey:/u', $block), false, 'const'];
}

if ($rows === []) {
    fwrite(STDERR, "REFUSED: no page under resources/js/Pages renders the shared table — the reader found nothing to judge.\n");

    exit(1);
}

$unwired = array_values(array_filter($rows, fn (array $r): bool => ! $r['wired']));
if ($showAll === false && $unwired === []) {
    fwrite(STDERR, "REFUSED: zero unwired pages, which has never been this repository's state — check the reader before believing it.\n");

    exit(1);
}

printf("%-46s %7s %8s  %-24s %s\n", 'PAGE', 'cols', 'sortKey', 'rows=', 'ACTION');
$unread = 0;
$unjoined = 0;
foreach ($rows as $r) {
    if (! $showAll && $r['wired']) {
        continue;
    }
    $unread += ($r['unread'] && $r['shape'] !== 'inline') ? 1 : 0;
    $unjoined += $r['actions'] === [] ? 1 : 0;
    printf(
        "%-46s %7s %8s  %-24s %s%s%s\n",
        $r['page'],
        $r['shape'] === 'inline' ? 'inline' : ($r['unread'] ? 'UNREAD' : $r['columns']),
        $r['shape'] === 'inline' || $r['unread'] ? '-' : $r['sortKeys'],
        mb_substr($r['rows'], 0, 24),
        implode(', ', $r['actions']) ?: '(no render found — read it)',
        $r['wired'] ? '   [wired]' : '',
        $r['columns'] > 0 && $r['sortKeys'] === 0 ? '   no sortKey on any column' : ''
    );
}

if ($unread > 0 || $unjoined > 0) {
    printf(
        "\nREFUSED: %d page(s) had no readable column list and %d had no action joined — the census below is read off rows it did not open.\n",
        $unread,
        $unjoined
    );

    exit(1);
}

$totalCols = 0;
foreach ($unwired as $r) {
    $totalCols += $r['columns'];
}
printf(
    "\n%d page(s) render the shared table: %d wired, %d unwired, carrying %d columns between them.\n",
    count($rows),
    count($rows) - count($unwired),
    count($unwired),
    $totalCols
);
echo "A column is offered only when the handler's own query answers the order (§28.3 G-40); this census counts,\n"
    ."it does not decide. Pages above with 0 columns were read but their list is spelled inline — open them.\n";

if ($jsonOut !== null) {
    file_put_contents($root.'/'.$jsonOut, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    printf("wrote %s (%d row(s))\n", $jsonOut, count($rows));
}
