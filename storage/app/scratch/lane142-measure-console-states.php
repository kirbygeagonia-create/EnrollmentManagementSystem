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
    fwrite(STDERR, "REFUSED: this tool takes --json=<path>, --pages=<dir> or --ci and received '{$a}'.\n");

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
$findings = [];
$counts = ['EMPTY' => 0, 'LOADING' => 0, 'CONSOLE' => 0, 'PARSE' => 0];
$refreshingPages = 0;
$forbidden = ['console.log', 'console.warn', 'debugger '];
$errorNotes = [];

foreach ($files as $f) {
    $src = (string) file_get_contents($f);
    // The display key is relative to whatever tree was walked, not to the project root: with --pages= the copy lives
    // outside the root, and a root-relative slice of it would print a path that names no file.
    $rel = str_replace('\\', '/', substr($f, strlen($pagesRoot) + 1));
    $absByFile[$rel] = $f;

    // Every `<DataTable …>` opening tag in the file. An attribute may itself contain `>` (a ternary inside a
    // braces expression), so the tag is taken by brace-and-angle depth from the opening, never by a regex that
    // stops at the first `>` — that is lane 47's StatCard trap met on this control.
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
            $counts['EMPTY']++;
            $findings[] = ['EMPTY', $rel, $at, 'no emptyMessage on this table site; the desk reads the shared default sentence'];
        }
    }

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
    $refreshes = preg_match('/\brouter\s*\.\s*(?:get|visit|reload)\s*\(/', $src) === 1;
    $signalsRefresh = preg_match('/\brouter\s*\.\s*on\s*\(/', $src) === 1
        || preg_match('/<DataTable\b[^>]*\b(?:busy|loading)=/s', $src) === 1;
    if ($refreshes && ! $signalsRefresh) {
        $counts['LOADING']++;
        $findings[] = ['LOADING', $rel, 0, "repaints {$nSites} shared table(s) from its own router call with no pending signal on that refresh"];
    }
    if ($refreshes) {
        $refreshingPages++;
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
printf("\nLOADING scope: of the %d page(s) that render a shared table, %d repaint the rows from a `router.get`/`visit`/"
    ."`reload` the page itself calls; the other %d are NOT this bucket's question and are not counted clean. A page is "
    ."credited only by a signal on that refresh — a `router.on(...)` subscription or a pending value passed to the "
    ."table — never by a SAVE button's `form.processing`, which is a different act.\n",
    count($sitesByFile), $refreshingPages, count($sitesByFile) - $refreshingPages);
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
    echo "--ci: every site the scanner could bound is closed on the three source-level halves; the responsive "
        ."half remains NOT JUDGED.\n";
}

exit(0);
