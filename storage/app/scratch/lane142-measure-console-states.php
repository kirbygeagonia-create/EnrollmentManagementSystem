<?php

/**
 * Lane 142 — prompt §14's console clause, weighed at the source rather than in a browser.
 *
 * §28.3 G-41 records that "console errors and empty, loading and responsive states" has no instrument, so the clause
 * is UNVERIFIED rather than passing — and the row defers the browser-job question to the owner because a CI browser
 * step costs runner time. Three of the four halves need no browser at all, and every defect they can find is a fact
 * about the source that a person reading one page could also see:
 *
 *   EMPTY      a table site that passes no `emptyMessage` shows the shared control's default sentence, which names
 *              nothing — the desk cannot tell "this queue is empty" from "this office has nothing today";
 *   LOADING    a page whose rows arrive from the server repaints only when the answer lands, so a filter, a sort or
 *              a submit with no pending affordance reads as a dead screen for the length of the query;
 *   CONSOLE    a `console.log` or a `debugger` left in a desk page is the half of §14 that is a fact, not a judgement.
 *
 * The responsive half is deliberately NOT claimed here: media queries and reflow are properties of a rendered
 * viewport, and a source scan that reports on them would be the lane 62 defect — a busy instrument printing a
 * negative it did not measure. The row stays Open with that one named as the browser question.
 *
 * Usage: php storage/app/scratch/lane142-measure-console-states.php [--json=<path>] [--ci]
 */
$root = dirname(__DIR__, 3);
chdir($root);

$ci = false;
$jsonTo = null;
$pagesOverride = null;
$controlOverride = null;
$bootOverride = null;
foreach ($argv as $i => $a) {
    if ($i === 0) {
        continue;
    }
    if (str_starts_with($a, '--json=')) {
        $jsonTo = substr($a, 7);

        continue;
    }
    // So a harness can plant a defect without editing a desk page: the population is addressable, and the tool
    // refuses a directory it cannot walk rather than reporting the same clean numbers an empty read would give.
    if (str_starts_with($a, '--pages=')) {
        $pagesOverride = substr($a, 8);

        continue;
    }
    if ($a === '--ci') {
        $ci = true;

        continue;
    }
    // The two files that establish the shared control's coverage, addressable so a harness can falsify that claim:
    // --control= at a copy whose pending read is removed must make every refreshing page chargeable again. Without
    // such a leg, "LOADING 0" is a claim about a control nobody proved.
    if (str_starts_with($a, '--control=')) {
        $controlOverride = substr($a, 10);

        continue;
    }
    if (str_starts_with($a, '--boot=')) {
        $bootOverride = substr($a, 7);

        continue;
    }
    fwrite(STDERR, 'REFUSED: this tool takes --json=<path>, --pages=<dir>, --control=<path>, --boot=<path> or --ci'
        ." and received '{$a}'.\n");

    exit(1);
}

$pagesRoot = $pagesOverride ?? $root.'/resources/js/Pages';
if (! is_dir($pagesRoot)) {
    fwrite(STDERR, "REFUSED: {$pagesRoot} is not a directory, so the population cannot be walked.\n");

    exit(1);
}

$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pagesRoot, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->isFile() && str_ends_with($f->getFilename(), '.jsx')) {
        $files[] = $f->getPathname();
    }
}
sort($files);
if ($files === []) {
    fwrite(STDERR, "REFUSED: the walk found no page file, so every bucket below would be empty by accident.\n");

    exit(1);
}

$tableSites = [];
$sitesByFile = [];
$absByFile = [];

// Does the shared control carry the refresh signal, and is it armed at boot? Read once, from the two files that
// would have to say so, so a page cannot be credited by a subscription that was never registered: DataTable.jsx
// must read the pending state AND app.jsx must call the boot-time watcher, because the store only fills when a
// listener exists.
$controlPath = $controlOverride ?? $root.'/resources/js/Components/ui/DataTable.jsx';
$bootPath = $bootOverride ?? $root.'/resources/js/app.jsx';
foreach ([$controlPath, $bootPath] as $mustRead) {
    if (! file_exists($mustRead)) {
        fwrite(STDERR, "REFUSED: {$mustRead} is not in the tree, so the control's coverage cannot be established.\n");

        exit(1);
    }
}
$controlReadsPending = str_contains((string) file_get_contents($controlPath), 'useRouterPending');
$bootArmsWatch = str_contains((string) file_get_contents($bootPath), 'watchRouterPending(');
$controlSignals = $controlReadsPending && $bootArmsWatch;

printf("shared control carries the refresh signal: %s (control reads pending: %s, boot arms the watcher: %s)\n",
    $controlSignals ? 'yes' : 'NO', $controlReadsPending ? 'yes' : 'no', $bootArmsWatch ? 'yes' : 'no');
$findings = [];
$counts = ['EMPTY' => 0, 'LOADING' => 0, 'CONSOLE' => 0, 'PARSE' => 0];
$refreshingPages = 0;
$bareSites = [];
$guardedSites = [];
$rawRefreshing = 0;
$forbidden = ['console.log', 'console.warn', 'debugger '];
$errorNotes = [];

foreach ($files as $f) {
    $src = (string) file_get_contents($f);
    // The display key is relative to whatever tree was walked, not to the project root: with --pages= the copy lives
    // outside the root, and a root-relative slice of it would print a path that names no file.
    $rel = str_replace('\\', '/', substr($f, strlen($pagesRoot) + 1));
    $absByFile[$rel] = $f;

    // Every page, table or not. The first draft ran this scan inside the `has a shared table` branch, and the harness
    // caught it: a `console.log` or a `debugger` left on a form page was invisible, which is the over-narrow scope
    // this file has been caught by from the other side. The prompt's bullet is about workflows, not about tables.
    foreach (['console.log', 'console.warn', 'debugger'] as $bad) {
        $n = substr_count($src, $bad);
        if ($n > 0) {
            $counts['CONSOLE'] += $n;
            $findings[] = ['CONSOLE', $rel, 0, "{$n} occurrence(s) of '{$bad}'"];
        }
    }
    // console.error is the deliberate half and is printed, not counted: the fetch guard that reports a refusal
    // writes it on purpose (lane 29's rule that a screen may not paint a plausible success).
    $errCount = substr_count($src, 'console.error');
    if ($errCount > 0) {
        $errorNotes[$rel] = $errCount;
    }

    if (! preg_match_all('/<DataTable\b/', $src, $tm, PREG_OFFSET_CAPTURE)) {
        continue;
    }
    foreach ($tm[0] as $hit) {
        $at = $hit[1];
        $depth = 0;
        $end = null;
        $len = strlen($src);
        for ($p = $at; $p < $len; $p++) {
            $c = $src[$p];
            if ($c === '{') {
                $depth++;
            } elseif ($c === '}') {
                $depth--;
            } elseif ($c === '>' && $depth === 0) {
                $end = $p;

                break;
            }
        }
        if ($end === null) {
            // A site the scanner cannot bound is reported by name: a silent skip here would print the same clean
            // bucket totals as a page whose empty state really is set.
            $counts['PARSE']++;
            $findings[] = ['PARSE', $rel, $at, 'a <DataTable tag whose attributes never close — this site was not judged'];

            continue;
        }
        $tag = substr($src, $at, $end - $at + 1);
        $tableSites[] = [$rel, $at];
        $sitesByFile[$rel] = ($sitesByFile[$rel] ?? 0) + 1;
        if (! str_contains($tag, 'emptyMessage')) {
            // Not yet a finding: the shared control's default sentence only reaches the desk when the page lets the
            // table paint at all. `Admin/WorkflowMonitor/Index.jsx` was reported by the first draft and is wrong —
            // it renders `EmptyState title="No records match these filters"` in the false arm of a
            // `rows.length === 0` test, so the generic string is unreachable and the emptiness IS named. Asking the
            // paint rather than the prop is lane 46's "a checker pointed at the wrong construct prints a pass",
            // met from the over-reporting side.
            $bareSites[$rel] = ($bareSites[$rel] ?? 0) + 1;
        }
    }
}

foreach ($sitesByFile as $rel => $nSites) {
    // Read the file the walk actually opened, not a path rebuilt from the project root: with --pages= the copy tree
    // sits outside the root, and a rebuilt path would silently read nothing and report the page as having no
    // pending state.
    $src = (string) file_get_contents($absByFile[$rel] ?? $root.'/'.$rel);

    // LOADING is judged on the REFRESH, not on the word appearing anywhere in the file. The first draft credited a
    // page for containing `processing`, and measuring that rule showed what it was hiding: of the 28 pages that
    // repaint a table from their own router call, 24 were credited solely by a SAVE button's `form.processing` — a
    // different act on a different control — and 0 by any signal attached to the refresh. So the affordance must sit
    // where the answer is awaited: a `router.on(...)` subscription, or a pending value handed to the table.
    //
    // A page that renders the shared control is credited by the CONTROL's own subscription, because that is where the
    // fix lives (one definition, inherited by every desk table — lane 69's rule). What stays chargeable is a page
    // that refreshes rows the control cannot see: a hand-built table with no signal of its own.
    $refreshes = (preg_match('/\brouter\s*\.\s*(?:get|visit)\s*\(\s*route\([^)]*\)\s*,/', $src) === 1
            || str_contains($src, 'preserveState'))
        && preg_match('/\brouter\s*\.\s*(?:get|visit|reload)\s*\(/', $src) === 1;
    $ownSignal = preg_match('/\brouter\s*\.\s*on\s*\(/', $src) === 1
        || preg_match('/<DataTable\b[^>]*\b(?:busy|loading)=/s', $src) === 1
        || str_contains($src, 'useRouterPending');
    $usesSharedControl = preg_match('/<DataTable\b/', $src) === 1;
    if ($refreshes && ! $ownSignal && ! ($usesSharedControl && $controlSignals)) {
        $counts['LOADING']++;
        $findings[] = ['LOADING', $rel, 0, "repaints {$nSites} shared table(s) from its own router call with no pending signal on that refresh"];
    }
    if ($refreshes) {
        $refreshingPages++;
    }
}

// EMPTY, judged on whether the emptiness can reach the desk at all.
foreach ($bareSites as $rel => $n) {
    $src = (string) file_get_contents($absByFile[$rel] ?? $root.'/'.$rel);
    if (preg_match('/<EmptyState\b/', $src) === 1) {
        $guardedSites[$rel] = $n;

        continue;
    }
    $counts['EMPTY'] += $n;
    $findings[] = ['EMPTY', $rel, 0, "{$n} bare table site(s): no emptyMessage and no EmptyState anywhere in the page"];
}

// The population the shared control CANNOT reach: a page that builds its own <table> and refreshes it from a router
// call. Without this loop the answer is a comfortable 0 that omits exactly the surfaces the fix does not cover —
// lane 145's census named one such page (Registrar/Show.jsx, whose year and semester pickers refetch the record while
// its tables are hand-built), and a reader of "LOADING 0" would never be sent to it.
foreach ($files as $f) {
    $src = (string) file_get_contents($f);
    if (preg_match('/<DataTable\b/', $src) === 1) {
        continue;
    }
    // The same discriminator `DeskRefreshPendingStateTest` uses, so the two cannot answer differently: a visit that
    // passes a query object or asks for preserveState repaints THIS screen's rows, while `router.get(route('x.index'))`
    // behind a Cancel button leaves the page and has no stale list to announce.
    $inPlace = preg_match('/\brouter\s*\.\s*(?:get|visit)\s*\(\s*route\([^)]*\)\s*,/', $src) === 1
        || str_contains($src, 'preserveState');
    if (! $inPlace || preg_match('/\brouter\s*\.\s*(?:get|visit|reload)\s*\(/', $src) !== 1) {
        continue;
    }
    $rawRefreshing++;
    $rel = str_replace('\\', '/', substr($f, strlen($pagesRoot) + 1));
    if (preg_match('/\brouter\s*\.\s*on\s*\(/', $src) !== 1 && ! str_contains($src, 'useRouterPending')) {
        $counts['LOADING']++;
        $findings[] = ['LOADING', $rel, 0, 'repaints its own rows from a router call, outside the shared control, with no pending signal'];
    }
}

if ($tableSites === []) {
    fwrite(STDERR, "REFUSED: no page renders <DataTable>, which cannot be true of this tree — the reader is blind.\n");

    exit(1);
}

printf("pages walked: %d\n", count($files));
printf("table sites rendered: %d\n", count($tableSites));
foreach ($counts as $label => $n) {
    printf("%-10s %d\n", $label, $n);
}
printf("\nLOADING scope: of the %d page(s) that render a shared table, %d repaint the rows from a `router.get`/`visit`/"."`reload` the page itself calls; the other %d are NOT this bucket's question and are not counted clean. A page is "
    .'credited only by a signal on that refresh — a `router.on(...)` subscription or a pending value passed to the '
    ."table — never by a SAVE button's `form.processing`, which is a different act.\n",
    count($sitesByFile), $refreshingPages, count($sitesByFile) - $refreshingPages);
printf("EMPTY scope: %d page(s) leave the shared control's default sentence reachable; %d render a bare <DataTable> "
    ."but name the emptiness with their own EmptyState, and are not counted as gaps.\n",
    array_sum($bareSites) - array_sum($guardedSites), count($guardedSites));
if ($findings !== []) {
    echo "\nqueue:\n";
    foreach ($findings as [$kind, $file, $at, $why]) {
        printf("  %-8s %-58s @%-7d %s\n", $kind, $file, $at, $why);
    }
}
if ($errorNotes !== []) {
    echo "\nconsole.error occurrences, printed not counted (the refusal guards write them on purpose):\n";
    foreach ($errorNotes as $rel => $n) {
        printf("  %-58s %d\n", $rel, $n);
    }
}
echo "\nresponsive half: NOT JUDGED — media queries and reflow are viewport properties, and that is the browser "
    ."question §28.3 G-41 defers.\n";

if ($jsonTo !== null) {
    $payload = json_encode([
        'pages' => count($files),
        'tableSites' => count($tableSites),
        'counts' => $counts,
        'findings' => array_map(static fn (array $f): array => ['kind' => $f[0], 'file' => $f[1], 'byte' => $f[2], 'why' => $f[3]], $findings),
    ], JSON_PRETTY_PRINT);
    if (@file_put_contents($jsonTo, $payload) === false) {
        fwrite(STDERR, "REFUSED: could not write '{$jsonTo}'.\n");

        exit(1);
    }
    printf("wrote %s\n", $jsonTo);
}

// `--ci` is the claim that the source-level halves are settled: it refuses while any of them is open, so the flag
// cannot silently certify a queue it printed.
if ($ci) {
    $open = $counts['EMPTY'] + $counts['LOADING'] + $counts['CONSOLE'] + $counts['PARSE'];
    if ($open > 0) {
        fwrite(STDERR, "REFUSED (--ci): {$open} source-level §14 state finding(s) are open — this is a queue, not a pass.\n");

        exit(1);
    }
    echo '--ci: every site the scanner could bound is closed on the three source-level halves; the responsive '
        ."half remains NOT JUDGED.\n";
}

exit(0);
