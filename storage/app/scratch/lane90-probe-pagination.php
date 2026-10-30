<?php

/**
 * Read-only: which desk lists actually hand their table a paginator?
 *
 * §28.3 G-40 has carried "and two carry no pagination at all" since the sweep wrote it, and this batch repeated that
 * claim into §1.1 and the matrix. Two earlier drafts of this probe printed a census that was not a measurement: the
 * first matched 0 of 36 pages because it compared backslash page paths to forward-slash render names; the second
 * reported 24 unpaginated by asking whether the *controller file* contained a paginator and whether the render's prop
 * array named the file's first paginator variable. That bound is the file, not the action — so the ID hub, the
 * student directory and the audit log were all condemned while their own actions do paginate.
 *
 * This draft joins at the only place the claim actually lives: the page's own `<DataTable rows={…}>` expression names
 * the prop, the prop is looked up in the prop array of the `Inertia::render('<component>')` that serves the page (a
 * page may be served from several actions, and each is judged separately), and the value is resolved to the line in
 * THAT action's body that assigns it. Anything the join cannot resolve is printed as UNBOUND and the run refuses to
 * state a census when UNBOUND is more than a third of the rows — a number read off an unread half is the defect the
 * register is being corrected for.
 *
 * Usage: php storage/app/scratch/lane90-probe-pagination.php [--row=<page substring>]
 */

$root = dirname(__DIR__, 3);
$args = array_slice($argv, 1);
$filter = null;
foreach ($args as $a) {
    if (preg_match('/^--row=(.+)$/', $a, $m)) {
        $filter = $m[1];
    } else {
        fwrite(STDERR, "REFUSED: unknown flag {$a} — this probe reads no other argument.\n");

        exit(1);
    }
}

/**
 * Walk forward from an opener, counting brackets, and return the span that closes it — or the statement up to the
 * first depth-zero `;`. Strings and comments are stepped over, because a `( )` in a `//` note inside a prop array is
 * what made the first scanner report an array it could not reach, and because a closure body written across lines
 * holds the `;` that a `[^;]*` match stops at — which is how a `->paginate(20)` four lines further down went unseen.
 */
$scan = function (string $s, int $i, string $stop): ?string {
    $start = $i;
    $depth = 0;
    $quote = null;
    $len = strlen($s);
    for (; $i < $len; $i++) {
        $c = $s[$i];
        $n = $i + 1 < $len ? $s[$i + 1] : '';
        if ($quote !== null) {
            if ($c === '\\') {
                $i++;
            } elseif ($c === $quote) {
                $quote = null;
            }

            continue;
        }
        if ($c === "'" || $c === '"' || $c === '`') {
            $quote = $c;
        } elseif (($c === '/' && $n === '/') || ($c === '#' && $n !== '[')) {
            $eol = strpos($s, "\n", $i);
            $i = $eol === false ? $len : $eol;
        } elseif ($c === '/' && $n === '*') {
            $e = strpos($s, '*/', $i);
            $i = $e === false ? $len : $e + 1;
        } elseif ($c === '[' || $c === '(' || $c === '{') {
            $depth++;
        } elseif ($c === ']' || $c === ')' || $c === '}') {
            $depth--;
            if ($depth < 0) {
                return null;
            }
            if ($depth === 0 && $stop === ']') {
                return substr($s, $start, $i - $start + 1);
            }
        } elseif ($c === ';' && $depth === 0 && $stop === ';') {
            return substr($s, $start, $i - $start);
        }
    }

    return $depth === 0 && $stop === ';' ? substr($s, $start, $len - $start) : null;
};

/**
 * The balanced `[...]` that starts at $from in $src.
 */
$balanced = function (string $src, int $from) use ($scan): ?string {
    return $src[$from] === '[' ? $scan($src, $from, ']') : null;
};

/**
 * Method start offsets of one controller file, longest-body-first irrelevant: the enclosing method of an offset is
 * the last start before it. Visibility keywords only, so a closure's `function` never opens a method.
 */
$methods = function (string $src): array {
    $list = [];
    if (preg_match_all('/(?:public|protected|private)\s+(?:static\s+)?function\s+(\w+)/', $src, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        foreach ($m as $r) {
            $list[] = ['name' => $r[1][0], 'at' => $r[0][1]];
        }
    }
    usort($list, fn (array $a, array $b): int => $a['at'] <=> $b['at']);

    return $list;
};

$bodyOf = function (array $list, int $index, string $src): string {
    $start = $list[$index]['at'];
    $end = $index + 1 < count($list) ? $list[$index + 1]['at'] : strlen($src);

    return substr($src, $start, $end - $start);
};

/**
 * The value expression of one key in a prop array: from the arrow to the next comma at depth zero.
 */
$valueExpr = function (string $array, string $key): ?string {
    if (! preg_match('/[\x27"]'.preg_quote($key, '/').'[\x27"]\s*=>/', $array, $m, PREG_OFFSET_CAPTURE)) {
        return null;
    }
    $i = $m[0][1] + strlen($m[0][0]);
    $depth = 0;
    $quote = null;
    $len = strlen($array);
    for (; $i < $len; $i++) {
        $c = $array[$i];
        if ($quote !== null) {
            if ($c === '\\') {
                $i++;
            } elseif ($c === $quote) {
                $quote = null;
            }

            continue;
        }
        if ($c === "'" || $c === '"' || $c === '`') {
            $quote = $c;
        } elseif ($c === '[' || $c === '(' || $c === '{') {
            $depth++;
        } elseif ($c === ']' || $c === ')' || $c === '}') {
            if ($depth === 0) {
                break;
            }
            $depth--;
        } elseif ($c === ',' && $depth === 0) {
            break;
        }
    }

    return trim(substr($array, $m[0][1] + strlen($m[0][0]), $i - ($m[0][1] + strlen($m[0][0]))));
};

$isPaginator = fn (string $expr): bool => (bool) preg_match('/->paginate\(|::paginate\(|cursorPaginate\(|simplePaginate\(/', $expr);
$isCollection = fn (string $expr): bool => (bool) preg_match('/->get\(|::all\(|->pluck\(|->keyBy\(|\bcollect\(|new Collection/', $expr);
// through(), map() and groupBy() reshape a result without replacing it, so a prop built by one of them still
// describes whichever set its source was. The look-back is what makes those rows read instead of UNBOUND.
$isTransformer = fn (string $expr): bool => (bool) preg_match('/->(?:through|map|mapWithKeys|groupBy|values|filter|sortBy|sort|transform|setCollection|keyBy|all)\s*\(/', $expr);

/**
 * Decide one prop value: an inline expression, a variable assigned once, or a variable reshaped by a transformer
 * after its source line. Returns [verdict, detail].
 */
$resolver = function (string $expr, string $body): array {
    global $isPaginator, $isCollection, $isTransformer;    if ($isPaginator($expr)) {
        return ['PAGINATED', preg_replace('/\s+/', ' ', $expr)];
    }
    if ($isCollection($expr)) {
        return ['COLLECTION', preg_replace('/\s+/', ' ', $expr)];
    }
    if (! preg_match('/^\$([a-zA-Z_]\w*)/', $expr, $vm)) {
        return ['UNBOUND', preg_replace('/\s+/', ' ', substr($expr, 0, 80))];
    }
    $var = $vm[1];
    // `'offices' => $offices->through(fn …)` reshapes inline, so the decisive line is the assignment the chain
    // starts from — the same look-back the bare-variable branch uses, reached from the other side.
    $rhs = [];
    if (preg_match_all('/\$'.preg_quote($var, '/').'\s*=\s*/', $body, $sm, PREG_OFFSET_CAPTURE)) {
        global $scan;
        foreach ($sm[0] as $one) {
            $taken = $scan($body, $one[1] + strlen($one[0]), ';');
            if ($taken !== null) {
                $rhs[] = $taken;
            }
        }
    }
    if ($expr !== '$'.$var) {
        array_unshift($rhs, $expr);
    }
    if ($rhs === []) {
        return ['UNBOUND', 'no assignment of $'.$var.' in this action'];
    }
    if ($rhs === []) {
        return ['UNBOUND', 'no assignment of $'.$var.' in this action'];
    }
    $last = trim((string) end($rhs));
    foreach (array_reverse($rhs) as $i => $one) {
        $one = trim($one);
        if ($isPaginator($one)) {
            $steps = $i;

            return [$steps === 0 ? 'PAGINATED' : 'PAGINATED', $steps === 0
                ? '$'.$var.' = '.preg_replace('/\s+/', ' ', substr($one, 0, 70))
                : '$'.$var.' reshaped after '.preg_replace('/\s+/', ' ', substr($one, 0, 70))];
        }
        if ($isCollection($one)) {
            return ['COLLECTION', '$'.$var.' = '.preg_replace('/\s+/', ' ', substr($one, 0, 70))];
        }
        if (! $isTransformer($one)) {
            break;
        }
    }

    return ['UNBOUND', '$'.$var.' = '.preg_replace('/\s+/', ' ', substr($last, 0, 70))];
};

// ---- pages that render the shared table, and the prop their own rows expression reads -------------------------

$pages = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/resources/js/Pages', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (! $f->isFile() || ! str_ends_with($f->getFilename(), '.jsx')) {
        continue;
    }
    $src = (string) file_get_contents($f->getPathname());
    if (! str_contains($src, '<DataTable')) {
        continue;
    }
    $rel = str_replace('\\', '/', $f->getPathname());
    $rel = str_replace([str_replace('\\', '/', $root).'/resources/js/Pages/', '.jsx'], ['', ''], $rel);
    $keys = [];
    foreach (preg_split('/<DataTable\b/', $src) as $frag) {
        if (preg_match('/\brows=\{\s*([a-zA-Z_][\w.]*)/', $frag, $rm)) {
            $head = $rm[1];
            if (str_starts_with($head, 'props.')) {
                $head = substr($head, 6);
            }
            // rows={enrollments.data} and rows={data.rows} both name the server prop by its first component.
            $name = explode('.', $head)[0];
            // A name the page declares itself (`const sortedSchedules = …`) is computed in the browser over whatever
            // prop it reads, so the server cannot be asked about it directly: it is its own bucket, not an UNBOUND.
            $local = preg_match('/(?:const|let|var|function)\s+'.preg_quote($name, '/').'\b/', $src) === 1;
            $keys[$name] = $local ? 'local' : 'prop';
        }
    }
    $pages[$rel] = $keys;
    ksort($pages);
}

// ---- every Inertia::render in app/, resolved to its action and prop array ------------------------------------

$renders = [];
$cit = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app/Http/Controllers', FilesystemIterator::SKIP_DOTS));
foreach ($cit as $f) {
    if (! $f->isFile() || ! str_ends_with($f->getFilename(), '.php')) {
        continue;
    }
    $src = (string) file_get_contents($f->getPathname());
    $list = $methods($src);
    if (preg_match_all("/Inertia::render\(\s*'([^']+)'/", $src, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        foreach ($mm as $r) {
            $comp = $r[1][0];
            $at = $r[0][1];
            $idx = null;
            foreach ($list as $i => $meth) {
                if ($meth['at'] < $at) {
                    $idx = $i;
                }
            }
            $body = $idx === null ? '' : $bodyOf($list, $idx, $src);

            // The props argument is whatever follows the component name's comma at depth zero — a literal array, or
            // the variable the action built it in. Reading only the literal is how a whole controller's pages came
            // back "could not be balanced". The group's own offset points at the name INSIDE its quotes, so the
            // whole-match end is the only position that sits past the closing quote.
            $end = $r[0][1] + strlen($r[0][0]);
            $after = ltrim(substr($src, $end));
            $props = str_starts_with($after, ',') ? ltrim(substr($after, 1)) : $after;
            $array = null;
            if (str_starts_with($props, '[')) {
                $array = $balanced($src, (int) strpos($src, '[', $end));
            } elseif (preg_match('/^\$([a-zA-Z_]\w*)/', $props, $pm)) {
                $pname = $pm[1];
                if (preg_match('/\$'.preg_quote($pname, '/').'\s*=\s*\[/s', $body, $om, PREG_OFFSET_CAPTURE)) {
                    $array = $balanced($body, $om[0][1] + strlen($om[0][0]) - 1);
                }
            }
            $renders[$comp][] = [
                'file' => str_replace($root.'/app/Http/Controllers/', '', $f->getPathname()),
                'method' => $idx === null ? '(top level)' : $list[$idx]['name'],
                'body' => $body,
                'array' => $array,
                'propsSpelledAs' => $array === null ? preg_replace('/\s+/', ' ', substr($props, 0, 40)) : '',
            ];
        }
    }
}

$rows = [];
foreach ($pages as $page => $keys) {
    if ($filter !== null && ! str_contains($page, $filter)) {
        continue;
    }
    if ($keys === []) {
        $rows[] = [$page, '-', '-', 'NO-ROWS-PROP', 'the page renders the table without a rows={} expression this probe reads'];

        continue;
    }
    $rs = $renders[$page] ?? [];
    if ($rs === []) {
        $rows[] = [$page, '-', '-', 'NO-RENDER', 'no Inertia::render(\''.$page.'\') in app/Http/Controllers'];

        continue;
    }
    foreach ($rs as $r) {
        if ($r['array'] === null) {
            $rows[] = [$page, $r['file'], $r['method'], 'UNBOUND', 'props spelled as '.$r['propsSpelledAs'].'; the array was not reachable'];

            continue;
        }
        foreach ($keys as $key => $kind) {
            if ($kind === 'local') {
                $rows[] = [$page, $r['file'], $r['method'], 'CLIENT-DERIVED', "'{$key}' is built in the page, not handed over by ".$r['method'].'()'];

                continue;
            }
            $expr = $valueExpr($r['array'], $key);
            if ($expr === null) {
                // compact('key') is how a controller names a prop after its variable.
                if (preg_match('/compact\(([^)]*)\)/', $r['array'], $cm) && str_contains($cm[1], "'{$key}'")) {
                    $expr = '$'.$key;
                } else {
                    $rows[] = [$page, $r['file'], $r['method'], 'NO-KEY', "no prop named '{$key}' in this render"];

                    continue;
                }
            }
            [$verdict, $detail] = $resolver($expr, $r['body']);
            $rows[] = [$page, $r['file'], $r['method'], $verdict, $detail];
        }
    }
}

$counts = [];
foreach ($rows as $r) {
    $counts[$r[3]] = ($counts[$r[3]] ?? 0) + 1;
}
$total = count($rows);
if ($total === 0) {
    fwrite(STDERR, "REFUSED: nothing resolved to judge — a filter that matches no page is not a census.\n");

    exit(1);
}

printf("pages rendering the shared table: %d   rows judged (page x action x prop): %d\n\n", count($pages), $total);
foreach ($rows as $r) {
    printf("  %-13s %-34s %-22s %-16s %s\n", $r[3], $r[0], $r[1].'@'.$r[2], '', $r[4]);
}
ksort($counts);
$line = [];
foreach ($counts as $v => $n) {
    $line[] = $v.' '.$n;
}
printf("\ncensus by verdict: %s\n", implode('; ', $line));
$paginated = $counts['PAGINATED'] ?? 0;
printf("pages whose EVERY judged action hands a paginator: %d\n", count(array_filter(
    array_unique(array_column($rows, 0)),
    function (string $page) use ($rows): bool {
        $mine = array_values(array_filter($rows, fn (array $r): bool => $r[0] === $page));

        return $mine !== [] && array_reduce($mine, fn (bool $c, array $r): bool => $c && $r[3] === 'PAGINATED', true);
    }
)));
$unread = ($counts['UNBOUND'] ?? 0) + ($counts['NO-KEY'] ?? 0) + ($counts['NO-RENDER'] ?? 0) + ($counts['NO-ROWS-PROP'] ?? 0);
if ($unread > $total / 3) {
    fwrite(STDERR, "\nREFUSED: ".$unread.' of '.$total." rows were not resolved, so no pagination census may be stated from this run.\n");

    exit(1);
}
printf("verdict: %d of %d rows unread; PAGINATED %d; the rows above are printed, not counted clean.\n", $unread, $total, $paginated);
