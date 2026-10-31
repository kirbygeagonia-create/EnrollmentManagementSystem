<?php

/**
 * Lane 108c: does every NARROWED student load still carry the columns the page under it paints?
 *
 * The lane-108 census answers the opposite question — which handlers still ship a WHOLE student — and its first cut
 * decided "this page reads nothing beyond the name" by searching the page file alone. That reading moved thirteen
 * sites to a five-column list BEFORE the graph walker existed. `Evaluation/Show` then proved a page paints through
 * shared components: `EnrollmentProfileForm` edits birthdate, citizenship, contact number, telephone, semesters
 * completed and years in institution. Narrowing from the old reading would have emptied a desk form while every
 * test stayed green, and a payload case cannot see it — those cases assert the KEYS THE SERVER SENT, not the keys
 * the screen reads.
 *
 * So this tool re-audits the narrowing it already applied. For each load literal that names columns it binds the
 * enclosing action (byte offset), takes the `Inertia::render` INSIDE that action and before the next method
 * declaration, walks the page's import graph through the shared module, and asks which `students` columns the
 * reachable code names. A column the page names that the list does not carry is MISSING — a dash on a desk, not a
 * 500.
 *
 * Two buckets stop it crying wolf, both learned the hard way here:
 *   BY-ROUTE-MODEL — the action takes a whole `Students $student`, so the payload ships the columns regardless of
 *                    what the eager-load list says (a narrowed list on a nested relation does not narrow that).
 *   WHOLE-LOAD-IN-SAME-ACTION — another load in the same action ships the student un-narrowed.
 * `email`/`username`/`status`/`remarks` are never MISSING on their own: the signed-in staff member's email sits in
 * the layout every page imports, and classifying on it called all 25 pages sensitive in one run. Those print as
 * LINES-TO-READ beside a POSSIBLE row.
 *
 * Nothing here writes a file.
 *
 * Usage: php storage/app/scratch/lane108c-check-the-narrowed-lists.php [--show-lines]
 */
$root = dirname(__DIR__, 3);
require __DIR__.'/page-read-graph.php';

$showLines = false;
$jsonTo = null;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--show-lines') {
        $showLines = true;

        continue;
    }
    if (str_starts_with($arg, '--json=')) {
        $jsonTo = substr($arg, strlen('--json='));

        continue;
    }
    fwrite(STDERR, "REFUSED: unknown argument {$arg}; this tool reads --show-lines and --json=.\n");

    exit(1);
}

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

$names = student_name_columns();

/** The last method declaration before a byte offset — a first-match lookup would name the top of the file. */
$enclosing = static function (array $decls, int $at): string {
    $action = 'TOP-LEVEL';
    foreach ($decls as $d) {
        if ($d[1] < $at) {
            $action = $d[0];
        }
    }

    return $action;
};

$sites = [];
$declsByFile = [];
foreach ($files as $abs) {
    $src = (string) file_get_contents($abs);
    $rel = str_replace($root.'/', '', $abs);
    preg_match_all('/(?:public|protected|private) function (\w+)\s*\(([^)]*)\)/s', $src, $dm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    $decls = [];
    $signatureAt = [];
    foreach ($dm as $d) {
        $decls[] = [$d[1][0], $d[1][1]];
        $signatureAt[$d[1][1]] = trim(preg_replace('/\s+/', ' ', $d[2][0]) ?? '');
    }
    $declsByFile[$rel] = ['decls' => $decls, 'signatures' => $signatureAt, 'src' => $src];

    // A relation path that names columns: `student:a,b,c` or `enrollment.student:a,b,c`.
    preg_match_all("/'([a-zA-Z_.]*[Ss]tudent):([a-zA-Z0-9_,]+)'/", $src, $lm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    foreach ($lm as $m) {
        $at = $m[0][1];
        $action = $enclosing($decls, $at);
        $signature = '';
        $declAt = null;
        foreach ($decls as $d) {
            if ($d[1] < $at) {
                $declAt = $d[1];
            }
        }
        if ($declAt !== null) {
            $signature = $signatureAt[$declAt];
        }

        // The component rendered by THIS action: cut the source at the next declaration first, so a render
        // belonging to the method below cannot be borrowed. (The census stops at a `next {` guard that this
        // corpus never contains, which is why the join is re-derived here rather than trusted from there.)
        $nextDecl = strlen($src);
        foreach ($decls as $d) {
            if ($d[1] > $at) {
                $nextDecl = min($nextDecl, $d[1]);
            }
        }
        $component = null;
        if (preg_match('/Inertia::render\(\s*[\x27"]([\w\/]+)/', substr($src, $at, $nextDecl - $at), $rm) === 1) {
            $component = $rm[1];
        }

        $sites[] = [
            'file' => $rel,
            'abs' => $abs,
            'action' => $action,
            'signature' => $signature,
            'relation' => $m[1][0],
            'columns' => array_values(array_filter(explode(',', $m[2][0]))),
            'component' => $component,
        ];
    }
}

if ($sites === []) {
    fwrite(STDERR, "REFUSED: no narrowed student load exists in app/Http/Controllers, which contradicts the"
        ." thirteen this batch wrote — re-read the pattern before believing a clean audit.\n");

    exit(1);
}

$counts = ['OK' => 0, 'MISSING' => 0, 'BY-ROUTE-MODEL' => 0, 'WHOLE-LOAD-IN-SAME-ACTION' => 0, 'NO-COMPONENT' => 0, 'SINGLE-FILE-WALK' => 0];
$rows = [];

// Group by (file, action): one action can carry several narrowed lists, and the columns a page may read come from
// their union — Accounting's queue loads `enrollment.student` narrowed and `enrollment.course` beside it.
$byAction = [];
foreach ($sites as $i => $s) {
    $byAction[$s['file'].'|'.$s['action']]['sites'][] = $i;
    $byAction[$s['file'].'|'.$s['action']]['union'] = array_merge($byAction[$s['file'].'|'.$s['action']]['union'] ?? [], $s['columns']);
}

foreach ($byAction as $key => $group) {
    $first = $sites[$group['sites'][0]];
    $avail = array_values(array_unique($group['union']));

    // Is the whole student available to the payload another way?
    if (preg_match('/\bStudents\s+\$\w+/', $first['signature']) === 1) {
        $counts['BY-ROUTE-MODEL']++;
        $rows[] = ['v' => 'BY-ROUTE-MODEL', 's' => $first, 'avail' => $avail, 'missing' => [], 'reads' => []];

        continue;
    }
    $src0 = $declsByFile[$first['file']]['src'];
    // A whole-student EAGER LOAD in the same action ships the columns regardless of the narrowed list beside it.
    // The first cut matched any quoted `student` string and swallowed twelve of fifteen rows into this bucket
    // unread: `whereHas('student', …)` is a search clause and `withErrors(['student' => …])` is a validation key,
    // and neither loads a row. So the literal must sit inside a `with(`/`load(` argument — the same anchor the
    // census uses for a whole-student load — and the deciding path is printed, so the bucket is checkable.
    $wholeHere = [];
    preg_match_all('/\b(?:with|load)\(\s*(?:\[)?([^\]()]{0,400})/s', $src0, $cm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    foreach ($cm as $c) {
        $actionOfCall = $enclosing($declsByFile[$first['file']]['decls'], $c[0][1]);
        if ($actionOfCall !== $first['action']) {
            continue;
        }
        preg_match_all("/'([a-zA-Z_.]*[Ss]tudent)'/", $c[1][0], $bm2, PREG_SET_ORDER);
        foreach ($bm2 as $b) {
            $wholeHere[] = $b[1];
        }
    }
    if ($wholeHere !== []) {
        $counts['WHOLE-LOAD-IN-SAME-ACTION']++;
        $rows[] = ['v' => 'WHOLE-LOAD-IN-SAME-ACTION', 's' => $first, 'avail' => $avail, 'missing' => [], 'reads' => [],
            'why' => 'this action also loads '.implode(', ', $wholeHere).' whole'];

        continue;
    }

    if ($first['component'] === null) {
        $counts['NO-COMPONENT']++;
        $rows[] = ['v' => 'NO-COMPONENT', 's' => $first, 'avail' => $avail, 'missing' => [], 'reads' => []];

        continue;
    }
    $page = $root.'/resources/js/Pages/'.$first['component'].'.jsx';
    if (! is_file($page)) {
        $counts['NO-COMPONENT']++;
        $rows[] = ['v' => 'NO-COMPONENT', 's' => $first, 'avail' => $avail, 'missing' => [], 'reads' => [], 'why' => 'no page at Pages/'.$first['component'].'.jsx'];

        continue;
    }
    $walk = page_reachable_files($root, $page);
    if ($walk['files'] === []) {
        $counts['NO-COMPONENT']++;
        $rows[] = ['v' => 'NO-COMPONENT', 's' => $first, 'avail' => $avail, 'missing' => [], 'reads' => [], 'why' => 'the page resolved no source at all'];

        continue;
    }
    // The scan scope travels with the verdict, because "the page reads nothing" means something different if only
    // one file was opened: the first census read the page file alone and filed `Evaluation/Show` — which paints
    // through `EnrollmentProfileForm` — as a name-only screen. A walk that reaches ONE file is that blind reading
    // arriving again, so it is its own bucket and it refuses the run.
    $scope = ['files' => count($walk['files']), 'unresolved' => $walk['unresolved']];
    if (count($walk['files']) < 2) {
        $counts['SINGLE-FILE-WALK']++;
        $rows[] = ['v' => 'SINGLE-FILE-WALK', 's' => $first, 'avail' => $avail, 'missing' => [], 'reads' => [],
            'scope' => $scope, 'why' => 'resolved no local import, so the graph reader never ran'];

        continue;
    }
    $reads = page_student_column_reads($walk['files']);
    $hard = [];
    foreach ($reads['nondemographic'] as $col) {
        if (! in_array($col, $avail, true)) {
            $hard[$col] = $col;
        }
    }
    foreach ($reads['names'] as $col) {
        if (! in_array($col, $avail, true) && in_array($col, $names, true)) {
            $hard[$col] = $col;
        }
    }
    // `suffix` is the column a five-column name list does not carry; the census already knows four detail screens
    // paint it, so a MISSING suffix here is a desk that stopped printing "Jr."
    if ($hard !== []) {
        $counts['MISSING']++;
        $rows[] = ['v' => 'MISSING', 's' => $first, 'avail' => $avail, 'scope' => $scope, 'missing' => array_values($hard),
            'reads' => $reads['nondemographic'], 'ambiguous' => $reads['ambiguous'], 'lines' => $reads['lines']];

        continue;
    }
    $counts['OK']++;
    $rows[] = ['v' => 'OK', 's' => $first, 'avail' => $avail, 'scope' => $scope, 'missing' => [], 'reads' => $reads['nondemographic'],
        'ambiguous' => $reads['ambiguous'], 'lines' => $reads['lines']];
}

if (array_sum($counts) !== count($byAction)) {
    fwrite(STDERR, sprintf("REFUSED: the audit tallies %d action(s) across buckets but collected %d — a row fell"
        ." through a branch that counted nothing.\n", array_sum($counts), count($byAction)));

    exit(1);
}

echo 'narrowed load literals: '.count($sites).' over '.count($byAction)." action(s)\n";
foreach ($rows as $r) {
    $why = isset($r['why']) ? '  ('.$r['why'].')' : '';
    // The scope travels with the verdict: an OK over 40 reachable files and an OK over 1 are not the same claim,
    // and the second one is the reading that filed a profile-form screen as name-only.
    $scope = isset($r['scope']) ? sprintf('  [walked %d file(s)%s]', $r['scope']['files'],
        $r['scope']['unresolved'] === [] ? '' : ', unresolved: '.implode(', ', array_slice($r['scope']['unresolved'], 0, 3))) : '';
    printf("%-27s %-56s %-26s loads %s%s  -> page %s%s\n", $r['v'], $r['s']['file'], $r['s']['action'].'()',
        implode(',', $r['avail']), $why, $r['s']['component'] ?? '(none)', $scope === '' ? '' : ' '.$scope);
    if ($r['v'] === 'MISSING') {
        echo '                          the list does NOT carry: '.implode(', ', $r['missing'])."\n";
        if (($r['ambiguous'] ?? []) !== []) {
            echo '                          shared-name hits to read: '.implode(', ', $r['ambiguous'])."\n";
        }
        if ($showLines) {
            foreach (array_slice($r['lines'] ?? [], 0, 40) as $l) {
                echo '                            '.$l."\n";
            }
        }
    }
}
echo "\n";
foreach ($counts as $label => $n) {
    printf("  %-27s %d\n", $label, $n);
}

// A walk that reached only the page file is the exact blindness that produced the wrong census, so it cannot be
// reported as a clean audit of that page.
if ($counts['SINGLE-FILE-WALK'] > 0) {
    fwrite(STDERR, "\nREFUSED: {$counts['SINGLE-FILE-WALK']} page(s) resolved no local import, so the graph reader"
        ." never ran on them — an OK from a one-file walk is the census's original blind reading, not a verdict.\n");

    exit(1);
}

if ($jsonTo !== null) {
    // The depth probe reads the AUDIT's binding rather than re-deriving "which page does this action render" — two
    // definitions of one join is this file's recurring defect (lanes 6, 46, 69, 90), and the probe's first cut did
    // exactly that and walked nine wrong pages while reporting a clean answer.
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'file' => $r['s']['file'],
            'action' => $r['s']['action'],
            'component' => $r['s']['component'],
            'verdict' => $r['v'],
            'carries' => $r['avail'],
            'missing' => $r['missing'],
            'walked' => $r['scope']['files'] ?? 0,
        ];
    }
    if (file_put_contents($jsonTo, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
        fwrite(STDERR, "REFUSED: could not write --json={$jsonTo}, so a downstream probe would read a stale file.\n");

        exit(1);
    }
    printf("json written: %s (%d row(s))%s", $jsonTo, count($out), PHP_EOL);
}

if ($counts['MISSING'] > 0) {
    echo "\nA MISSING row is a column the page names and the payload no longer sends: the desk paints a dash or\n"
        ."`undefined`, and no test reads a rendered string, so the suite stays green. Read the lines with\n"
        ."--show-lines before widening a list — several of these names are also columns of other records.\n";
}

exit($counts['MISSING'] > 0 ? 1 : 0);
