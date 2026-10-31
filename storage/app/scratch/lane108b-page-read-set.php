<?php

/**
 * Lane 108b: what does each candidate desk page ACTUALLY read from the student — including through the components
 * it imports?
 *
 * `lane108-census-whole-student-loads.php` decides a site is a candidate by searching the page file alone. That is
 * not enough to narrow one: a desk paints a name through a shared cell, an alias, or a layout component, and a
 * column list that omits what the component reads does not throw — it prints an em dash where a person used to see
 * a value. Lane 71's `undefined` at the registrar is the same failure from the other side, and lane 108's `id`
 * mistake is the third: a column list written from a partial reading of the surface.
 *
 * So this prints EVIDENCE, not a verdict. For each page it walks the import graph (bounded depth) and prints every
 * line that mentions a `students` column by name, with the file it came from. Several of those names belong to
 * other records too — `email`, `status`, `suffix`, `username` are columns of `staffusers` and of `payments` — so a
 * hit is a thing to read, never a reason to keep a column. The reader decides per page; the tool refuses only when
 * it resolved nothing.
 *
 * Usage: php storage/app/scratch/lane108b-page-read-set.php [--pages=A/Index,B/Index] [--depth=3]
 */
$root = dirname(__DIR__, 3);
// The graph walker and the column vocabulary come from ONE module shared with the census, so "what does this page
// read" cannot be answered two ways. This tool's own job is to print the LINES for a human, because an ambiguous
// name (`email`, `status`, `username`) belongs to a staff or payment record as easily as to a student.
require __DIR__.'/page-read-graph.php';

$COLS = array_values(array_diff(array_merge(
    student_nondemic_columns(),
    student_ambiguous_columns(),
    student_name_columns(),
    ['passwordHash']
), ['studentId'])); // the owner key is named on enrollments, clearances, assessments and requests alike: printing it
// would add 39 lines of noise and say nothing about whether a page reads the person's record.

$depth = 3;
$only = null;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--depth=')) {
        $depth = max(1, (int) substr($arg, strlen('--depth=')));

        continue;
    }
    if (str_starts_with($arg, '--pages=')) {
        $only = array_values(array_filter(array_map('trim', explode(',', substr($arg, strlen('--pages='))))));

        continue;
    }
    fwrite(STDERR, "REFUSED: unknown argument {$arg}; this probe reads --pages= and --depth=.\n");

    exit(1);
}

// The candidate pages, taken from the census's own LIST rows so this file never redefines which sites are next.
$pages = [
    'Accounting/DailyReport', 'Admin/WorkflowMonitor/Index', 'Admission/Index', 'Clearance/Index',
    'Clinic/Index', 'Dashboard', 'Evaluation/Index', 'Evaluation/ShiftRequests', 'Exam/Index', 'Exam/Results',
    'ID/Index',
];
if ($only !== null) {
    // Any page that exists may be asked of this reader, not just the queue list: the detail screens that still
    // load a whole student are the next thing to narrow, and each one paints its student through a shared
    // component. Refusing only a name with no file keeps the check meaningful — a typo must not read as "no hits".
    foreach ($only as $p) {
        if (! is_file($root.'/resources/js/Pages/'.$p.'.jsx')) {
            fwrite(STDERR, "REFUSED: --pages= names {$p}, and resources/js/Pages/{$p}.jsx does not exist."
                ." This reader will not report a clean read for a page it could not open.\n");

            exit(1);
        }
    }
    $pages = $only;
}

// The import walker is `page_reachable_files()` in `page-read-graph.php` — see that file for why the separator
// style has to be normalised before any comparison: the first cut of this reader resolved one file per page and
// printed "(1 reachable file(s))" for every one of them, a clean-looking verdict over a tree it had not read.


$totalHits = 0;
$totalFiles = 0;
$badWalks = [];
foreach ($pages as $p) {
    $file = str_replace('\\', '/', $root.'/resources/js/Pages/'.$p.'.jsx');
    if (! is_file($file)) {
        echo "MISSING  {$p} — no page at resources/js/Pages/{$p}.jsx\n";

        continue;
    }
    $walk = page_reachable_files($root, $file, $depth);
    $files = $walk['files'];
    $totalFiles += count($files);
    if (count($files) === 1) {
        $badWalks[] = $p;
    }
    $rows = [];
    foreach ($files as $path => $src) {
        foreach (explode("\n", $src) as $ln => $line) {
            if (preg_match('/^\s*(\/\/|\*)/', $line)) {
                continue; // a comment is not a read
            }
            foreach ($COLS as $c) {
                if (preg_match('/[.\?\]]'.$c.'\b/', $line) === 1) {
                    $rows[] = sprintf('    %-28s :%-4d %s', basename($path), $ln + 1, trim(substr(preg_replace('/\s+/', ' ', $line), 0, 118)));

                    break;
                }
            }
        }
    }
    $totalHits += count($rows);
    printf("%s  (%d reachable file(s)%s)%s", $p, count($files),
        $walk['unresolved'] === [] ? '' : ', '.count($walk['unresolved']).' local import(s) NOT resolved', PHP_EOL);
    foreach (array_slice(array_unique($walk['unresolved']), 0, 6) as $u) {
        echo '    UNRESOLVED specifier: '.$u."\n";
    }
    foreach (array_slice($rows, 0, 60) as $r) {
        echo $r."\n";
    }
    if (count($rows) > 60) {
        printf("    … %d more line(s) withheld — the page is not small; ask for --pages=%s alone.%s", count($rows) - 60, $p, PHP_EOL);
    }
    echo "\n";
}

if ($badWalks !== []) {
    fwrite(STDERR, "REFUSED: ".count($badWalks)." page(s) resolved no local import at all (".implode(', ', $badWalks).
        "). A page that imports nothing is possible; a resolver that reaches only the page is the broken case, and"
        ." this tool exists to read past the page.\n");

    exit(1);
}

if ($totalHits === 0) {
    fwrite(STDERR, "REFUSED: the walk found not one column-name hit across every page, which means the reader is"
        ." broken and not that no page paints a student field.\n");

    exit(1);
}
echo "lines printed: {$totalHits} over ".count($pages)." page(s) — a hit is a thing to READ, not a verdict:"
    ." email / status / suffix / username are columns of other records too.\n";
