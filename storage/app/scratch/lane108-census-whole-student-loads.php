<?php

/**
 * Lane 108: how many desk handlers still load a WHOLE student, and does the page under each one read it?
 *
 * G-52 was found on the three queues whose order joins a relation, and those three are narrowed. This census asks
 * the same question of every handler in `app/Http/Controllers`: an eager load written as `'student'` or
 * `'enrollment.student'` with no column list hands the browser every column `students` holds except the one
 * `Students::$hidden` withholds — so `birthdate`, `contactNumber`, `citizenship` and the rest travel to a desk that
 * paints a name. Whether that is a defect is answered per page, not per table: the intake screen and Student 360
 * legitimately read those fields, so only a page that reads NOTHING but the identity set is a candidate.
 *
 * A load is attributed to its enclosing `public function` by byte offset (a whole-file match would condemn an
 * action that merely mentions `with(` elsewhere), and the page is bound through the `Inertia::render('<Component>')`
 * inside that SAME action. Nothing here edits a file.
 *
 * Buckets are asserted to sum to the population, because a census whose rows fall through a `continue` reports a
 * clean number over records it never read. NO-COMPONENT is a real verdict, not a failure: a JSON endpoint, a
 * redirect, or a render written outside the action cannot be judged from the page and is printed for a reader.
 *
 * Usage: php storage/app/scratch/lane108-census-whole-student-loads.php [--show-identity]
 */
$root = dirname(__DIR__, 3);

$showIdentity = false;
$jsonTo = null;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--show-identity') {
        $showIdentity = true;

        continue;
    }
    if (str_starts_with($arg, '--json=')) {
        $jsonTo = substr($arg, strlen('--json='));

        continue;
    }
    fwrite(STDERR, "REFUSED: unknown argument {$arg}; this census reads --show-identity and --json=.\n");

    exit(1);
}

// Which `students` columns are a name and which are not lives in ONE module, shared with
// `lane108b-page-read-set.php`: this census once kept its own list and read only the page file, so it filed
// `Evaluation/Show` — which renders a profile form that edits birthdate, citizenship and contact number — as a
// screen reading "nothing beyond the name". Two readers answering one question differently is the defect this
// repository refuses in lanes 6, 46, 69, 70 and 90; this was the second definition, in my own instrument.
require __DIR__.'/page-read-graph.php';

$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app/Http/Controllers'));
foreach ($it as $f) {
    if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
        $files[] = $f->getPathname();
    }
}
sort($files);
if ($files === []) {
    fwrite(STDERR, "REFUSED: the walk found no controller, so it measured nothing.\n");

    exit(1);
}

// An eager-load site: `with(` or `load(` followed within 400 bytes by a quoted relation path ending in `.student`
// or standing alone as `student`, with NO `:` column list on it.
$loadPattern = '/\b(?:with|load)\(\s*(?:\[)?([^\]()]{0,400})/s';
$rows = [];
$seen = [];

foreach ($files as $abs) {
    $src = (string) file_get_contents($abs);
    $rel = str_replace($root.'/', '', $abs);

    // Action boundaries by byte offset, so a load is charged to the method that runs it.
    preg_match_all('/(?:public|protected|private) function (\w+)\s*\(/', $src, $dm, PREG_OFFSET_CAPTURE);
    $decls = $dm[1];

    preg_match_all($loadPattern, $src, $lm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    foreach ($lm as $m) {
        $at = $m[0][1];
        $action = 'TOP-LEVEL';
        foreach ($decls as $d) {
            if ($d[1] < $at) {
                $action = $d[0];
            }
        }

        // Relation paths inside this with()/load() call only, each with ITS OWN byte offset: a later rewriter must
        // change the literal where it sits, because a short needle like `'student'` occurs all over the tree —
        // lane 69's rule that a needle list corrupts, applied to this class.
        $paths = [];
        preg_match_all("/'([a-zA-Z0-9_.]*)'/", $m[1][0], $pm, PREG_OFFSET_CAPTURE);
        foreach ($pm[1] as $p) {
            if (in_array('student', explode('.', $p[0]), true) && ! str_contains($p[0], ':')) {
                // The capture group begins AFTER the opening quote, so minus one is the quote itself. The first cut
                // recorded the group offset while its comment claimed it had added one; the rewriter then read
                // `enrollment.student',` where it expected `'enrollment.student'` and refused every row it was
                // handed — the refusal worked, the census had not.
                $paths[] = ['path' => $p[0], 'at' => $m[1][1] + $p[1] - 1];
            }
        }
        $whole = array_values(array_unique(array_column($paths, 'path')));
        if ($whole === []) {
            continue; // every load here already names its columns, or loads no student
        }

        // The component rendered by this SAME action.
        $component = null;
        $slice = substr($src, $at);
        // Stop at the next method declaration so a render below this action cannot be borrowed.
        if (preg_match('/\bnext\s*\{/', $slice) === 0 && preg_match('/Inertia::render\(\s*[\x27"]([\w\/]+)/', $slice, $rm)) {
            $component = $rm[1];
        }

        // One row per CALL SITE, not per (file, action): an action may hold two whole-student loads (the daily
        // report does), and merging them would hide the second one's offsets from the rewriter that reads this JSON.
        $rows[] = ['file' => $rel, 'action' => $action, 'paths' => $whole, 'literals' => $paths, 'component' => $component];
    }
}

if ($rows === []) {
    fwrite(STDERR, "REFUSED: no handler in app/Http/Controllers carries a whole-student load, which contradicts"
        ." the three this batch narrowed — re-read the pattern before believing a clean census.\n");

    exit(1);
}

$counts = ['IDENTITY-ONLY' => 0, 'READS-SENSITIVE' => 0, 'NO-COMPONENT' => 0];
// The identity-only rows split by what the site IS, because the remedy differs: a desk list page can be narrowed
// from source and checked in the browser, a detail screen may paint a field through a shared component, and a print
// path is judged on paper (lane 62) rather than by reading the JSX. These three names are the register row's own
// units, so they are printed by this tool rather than counted by whoever reads its output.
$splits = ['LIST' => 0, 'DETAIL' => 0, 'PRINT' => 0];
$identityOnly = [];
$prints = [];

foreach ($rows as $ri => $r) {
    $rows[$ri]['kind'] = '';
    if ($r['component'] === null) {
        $counts['NO-COMPONENT']++;

        $prints[] = sprintf("NO-COMPONENT   %-58s %-30s %s", $r['file'], $r['action'].'()', implode(', ', $r['paths']));

        continue;
    }
    $page = $root.'/resources/js/Pages/'.$r['component'].'.jsx';
    if (! is_file($page)) {
        $counts['NO-COMPONENT']++;

        $prints[] = sprintf("NO-COMPONENT   %-58s %-30s %s  (no page at Pages/%s.jsx)", $r['file'], $r['action'].'()', implode(', ', $r['paths']), $r['component']);

        continue;
    }
    $js = null; // read through the shared graph walker below, never from the page file alone
    $walk = page_reachable_files($root, $page);
    $reachable = $walk['files'];
    if ($reachable === []) {
        $counts['NO-COMPONENT']++;

        $prints[] = sprintf("NO-COMPONENT   %-58s %-30s %s  (the page resolved no source at all)", $r['file'], $r['action'].'()', implode(', ', $r['paths']));

        continue;
    }
    $reads = page_student_column_reads($reachable);
    $unresolvedNotes = [];
    if ($reads['ambiguous'] !== []) {
        // A shared name (`email`, `status`, `username`, `remarks`) is printed, never classified: the signed-in
        // staff member's email sits in the layout every page imports, and classifying on it would call every
        // screen in the application a student read.
        $unresolvedNotes[] = 'shared-name hits to read: '.implode(', ', $reads['ambiguous'])
            .' across '.count($reads['lines']).' line(s) in '.count($reachable).' reachable file(s)';
    }
    $r['unresolvedNotes'] = $unresolvedNotes;
    $read = $reads['nondemographic'];
    $nameExtras = array_values(array_intersect($reads['names'], ['suffix']));
    if ($read !== []) {
        $counts['READS-SENSITIVE']++;

        $prints[] = sprintf("READS-SENSITIVE %-57s %-30s %s -> %s reads %s", $r['file'], $r['action'].'()', implode(', ', $r['paths']), $r['component'], implode(', ', $read));

        continue;
    }
    $counts['IDENTITY-ONLY']++;
    $kind = 'LIST';
    if (str_starts_with($r['action'], 'print') || str_contains($r['component'], 'Print')) {
        $kind = 'PRINT';
    } elseif ($r['action'] === 'show' || str_ends_with($r['component'], '/Show')) {
        $kind = 'DETAIL';
    }
    $splits[$kind]++;
    $rows[$ri]['kind'] = $kind;
    $rows[$ri]['readsSuffix'] = $nameExtras !== [];
    $tag = $nameExtras === [] ? '' : '  (+'.implode(',', $nameExtras).' — a narrowed list there must carry it)';
    $identityOnly[] = sprintf('%s  %s %s() [%s] -> %s%s', $kind, $r['file'], $r['action'], implode(', ', $r['paths']), $r['component'], $tag);

    $prints[] = sprintf('%-7s IDENTITY-ONLY %-52s %-30s %s -> %s reads none of them%s', '['.$kind.']', $r['file'], $r['action'].'()', implode(', ', $r['paths']), $r['component'], $tag);
}

if (array_sum($counts) !== count($rows)) {
    fwrite(STDERR, sprintf("REFUSED: the census tallies %d rows across buckets but collected %d — a row fell"
        ." through a branch that counted nothing.\n", array_sum($counts), count($rows)));

    exit(1);
}

// The sum invariant above CANNOT see this failure: on the first run every one of 27 rows landed in NO-COMPONENT
// because the render pattern had an invalid escape, and the buckets added up perfectly. A census that resolved no
// page states nothing about any payload, so the join itself is guarded.
if ($counts['IDENTITY-ONLY'] + $counts['READS-SENSITIVE'] === 0) {
    fwrite(STDERR, "REFUSED: no row resolved a page, so the handler-to-component join is broken and the verdict"
        ." below would be a census of nothing.\n");

    exit(1);
}

echo "load sites examined: ".count($rows)." over ".count($files)." controllers\n";
foreach ($prints as $p) {
    echo $p."\n";
}
echo "\n";
foreach ($counts as $label => $n) {
    printf("  %-16s %d\n", $label, $n);
}
// The split is a second claim about the same rows, so it is guarded by the same invariant — a sub-count that does
// not add up is the lane 71 defect (a summary that tallies what a `continue` never examined) arriving one level down.
if (array_sum($splits) !== $counts['IDENTITY-ONLY']) {
    fwrite(STDERR, sprintf("REFUSED: the LIST/DETAIL/PRINT split adds to %d but IDENTITY-ONLY tallies %d.\n",
        array_sum($splits), $counts['IDENTITY-ONLY']));

    exit(1);
}
echo "\nthe identity-only rows, by what the site is:\n";
foreach ($splits as $kind => $n) {
    printf("  %-7s %d\n", $kind, $n);
}
// The suffix tally is printed as its own line because the register states it: a figure a document attributes to a
// tool has to be answerable by a line that tool writes (lane 59), and per-row `+suffix` tags are not a total.
$suffixByKind = ['LIST' => 0, 'DETAIL' => 0, 'PRINT' => 0];
foreach ($rows as $r) {
    if (($r['kind'] ?? '') !== '' && ! empty($r['readsSuffix'])) {
        $suffixByKind[$r['kind']]++;
    }
}
echo "\nrows whose page also paints the name SUFFIX (a five-column name list would drop it):\n";
foreach ($suffixByKind as $kind => $n) {
    printf("  %-7s %d of %d\n", $kind, $n, $splits[$kind]);
}
if (array_sum($suffixByKind) > $counts['IDENTITY-ONLY']) {
    fwrite(STDERR, sprintf("REFUSED: %d suffix-painting rows counted over %d identity-only rows.\n",
        array_sum($suffixByKind), $counts['IDENTITY-ONLY']));

    exit(1);
}
if ($showIdentity) {
    echo "\nIDENTITY-ONLY sites, spelled for a patcher:\n";
    foreach ($identityOnly as $i) {
        echo '  '.$i."\n";
    }
}
if ($jsonTo !== null) {
    // The rewriter reads this instead of re-deriving "which site is a LIST queue" — two definitions of one
    // vocabulary is this file's recurring defect (lanes 6, 46, 69, 90).
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'file' => $r['file'],
            'action' => $r['action'],
            'component' => $r['component'],
            'kind' => $r['kind'],
            'readsSuffix' => $r['readsSuffix'] ?? false,
            'literals' => $r['literals'],
        ];
    }
    if (file_put_contents($jsonTo, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
        fwrite(STDERR, "REFUSED: could not write --json={$jsonTo}, so a downstream rewriter would read a stale file.\n");

        exit(1);
    }
    printf("json written: %s (%d row(s))%s", $jsonTo, count($out), PHP_EOL);
}

echo "\nNote: `READS-SENSITIVE` is judged on the PAGE, and a page that reads a column through a local alias or a\n"
    ."shared component can still be missed — the bucket is a floor, not a ceiling. `Students::\$hidden` withholds\n"
    ."passwordHash on the nested model, so no row here claims that field reaches a desk.\n";
