<?php

/**
 * Lane 59 — every figure the documents attribute to a tool, bound to what that tool prints today.
 *
 * Lane 58 caught one stale number in the authority document; measuring the whole family found nine more, eight of them
 * in AGENTS.md — the file whose job is to tell the next batch what each gate answers. `sweep-docx-citations.php` prints
 * "602 citation(s) checked against 141 file(s)" while AGENTS.md has said 559 over 115 since a September document pass;
 * lane37 answers 860 references and 108 finding rows where the file says 851 and 96; lane37b says 271 where it says 243;
 * lane32 answers 113 ranges where the file says 107 pairs inside 665; lane49 reports 19 unread guards and 340 non-figure
 * lines where both AGENTS.md and §29 of the docx say 18 and 355.
 *
 * Why this is not a number-and-noun problem, measured before it was designed: scanning these documents for
 * `<number> <countable noun>` returns 109 hits, and the digits 18, 19 and 96 sit in dozens of unrelated sentences —
 * "term 18", "21 DATE columns, 18 of them cast", "documentprintlog carries eight columns", "§18.3's column list". A noun
 * does not identify a reading. What does is the **instrument named in the same sentence**, so a claim is read only where
 * it cites a tool from the table below and the tool's own summary line is the reading. A labelled figure whose tool is
 * named but prints no such number is UNBOUND and refuses — otherwise a checker with a hole looks exactly like a checked
 * document, which is the failure lanes 43, 44, 46, 50 and 58 each paid for.
 *
 * Labels are matched on the PHRASE after the number, longest pattern first: `16 school-moment cells` and `26 day cells`
 * both end in "cells", and reading the last noun alone would compare 16 against 26 and report a true sentence as a
 * defect. Readings are addressed by name, not by position, for the same reason — lane 49 prints its non-figure count
 * before the unread count, and one positional regex read that tool and lane 50's as printing nothing at all.
 *
 * Past tense is history and is left alone: "its first version judged 27 bindings and cleared the tree" records the
 * defect lane 46 found, and rewriting it to today's 55 would destroy the sentence that warns the next reader.
 *
 *   php storage/app/scratch/lane59-instrument-counts.php [--run] [--readings=f] [--dump-readings] [--ci] [--only=a,b] [--trace]
 */

$root = dirname(__DIR__, 3);
$scratch = $root.'/storage/app/scratch';

$flag = static function (string $name) use ($argv): bool {
    return in_array($name, $argv, true);
};
$opt = static function (string $name) use ($argv): ?string {
    $seen = null;
    foreach ($argv as $a) {
        if (preg_match('/^'.preg_quote($name, '/').'=(.*)$/', $a, $m) === 1) {
            $seen = $m[1];
        }
    }

    return $seen;
};

$WORDS = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8,
    'nine' => 9, 'ten' => 10, 'eleven' => 11, 'twelve' => 12, 'thirteen' => 13, 'fourteen' => 14, 'fifteen' => 15,
    'sixteen' => 16, 'seventeen' => 17, 'eighteen' => 18, 'nineteen' => 19, 'twenty' => 20, 'thirty' => 30,
    'sixty' => 60];
$toNum = static function (string $s) use ($WORDS): ?int {
    // Lane 58's lesson: is_numeric() is false for "1,234", and four-digit counts are written with commas.
    $s = str_replace(',', '', strtolower(trim($s)));
    if ($s !== '' && ctype_digit($s)) {
        return (int) $s;
    }

    return $WORDS[$s] ?? null;
};

/**
 * Each instrument: the sentence that cites it, how to run it, the named numbers its summary prints, the phrases that
 * mean each number, and whether it needs a live database (so `--ci` leaves it to this box).
 */
$INSTRUMENTS = [
    'sweep-docx-citations' => [
        'cite' => '/sweep-docx-citations|whole-document sweep|global sweep/i',
        'cmd' => 'storage/app/scratch/sweep-docx-citations.php',
        'parts' => [
            'citations' => '/([3-9]\d{2}|\d+) citation\(s\) checked against/',
            'files' => '/checked against (\d+) file\(s\)/',
            'problems' => '/; (\d+) line problem/',
        ],
        'labels' => ['citations?' => 'citations', 'files?' => 'files', 'problems?' => 'problems'],
        'db' => false,
    ],
    'lane37-section-reference-sweep' => [
        'cite' => '/lane37-section-reference-sweep|section-reference-sweep/i',
        'cmd' => 'storage/app/scratch/lane37-section-reference-sweep.php',
        'parts' => [
            'headings' => '/sections known: (\d+)/',
            'findingRows' => '/finding rows indexed: (\d+)/',
            'references' => '/references checked: (\d+)/',
        ],
        'labels' => ['headings?' => 'headings', 'sections?' => 'headings', 'finding rows' => 'findingRows',
            '§-references' => 'references', 'references?' => 'references'],
        'db' => false,
    ],
    'lane37b-repo-citation-sweep' => [
        'cite' => '/lane37b-repo-citation-sweep|lane37b/i',
        'cmd' => 'storage/app/scratch/lane37b-repo-citation-sweep.php',
        'parts' => [
            'references' => '/(\d+) reference\(s\) in/',
            'files' => '/reference\(s\) in (\d+) file\(s\)/',
            'problems' => '/file\(s\): (\d+) problem/',
        ],
        'labels' => ['references?' => 'references', 'files?' => 'files', 'problems?' => 'problems'],
        'db' => false,
    ],
    'lane32-cite-meaning-sweep' => [
        'cite' => '/lane32-cite-meaning-sweep|cite-meaning/i',
        'cmd' => 'storage/app/scratch/lane32-cite-meaning-sweep.php',
        'parts' => [
            'ranges' => '/(\d+) citation range\(s\) checked/',
            'pairs' => '/(\d+) pair\(s\) flagged/',
        ],
        'labels' => ['ranges?' => 'ranges', 'cited ranges' => 'ranges', 'pairs?' => 'pairs'],
        'db' => false,
    ],
    'lane43-delete-guards' => [
        'cite' => '/lane43-delete-guards|delete-guards/i',
        'cmd' => 'storage/app/scratch/lane43-delete-guards.php',
        'parts' => [
            'sites' => '/(\d+) site\(s\) judged/',
            'problems' => '/site\(s\) judged, (\d+) problem\(s\)/',
            'refusing' => '/(\d+) relations refuse a delete/',
            'allowing' => '/(\d+) will not/',
        ],
        'labels' => ['delete sites' => 'sites', 'sites?' => 'sites', 'problems?' => 'problems'],
        'db' => true,
    ],
    'lane44-unique-keys' => [
        'cite' => '/lane44-unique-keys/i',
        'cmd' => 'storage/app/scratch/lane44-unique-keys.php',
        'parts' => [
            'keys' => '/holds (\d+) keys across/',
            'tables' => '/keys across (\d+) tables/',
        ],
        'labels' => ['unique keys' => 'keys', 'keys' => 'keys', 'tables' => 'tables'],
        'db' => true,
    ],
    'lane46-filter-contract' => [
        'cite' => '/lane46-filter-contract/i',
        'cmd' => 'storage/app/scratch/lane46-filter-contract.php',
        'parts' => [
            'bindings' => '/(\d+) page\/route binding\(s\) judged/',
            'problems' => '/binding\(s\) judged, (\d+) problem\(s\)/',
            'unbound' => '/(\d+) unread or unbound/',
        ],
        'labels' => ['bindings?' => 'bindings', 'desk filters' => 'bindings', 'problems?' => 'problems'],
        'db' => false,
    ],
    'lane47-tile-scope' => [
        'cite' => '/lane47-tile-scope/i',
        'cmd' => 'storage/app/scratch/lane47-tile-scope.php',
        'parts' => ['screens' => '/Screens judged: (\d+)/', 'tiles' => '/Tiles traced: (\d+)/'],
        'labels' => ['screens' => 'screens', 'tiles' => 'tiles'],
        'db' => false,
    ],
    'lane48-tile-fallback' => [
        'cite' => '/lane48-tile-fallback/i',
        'cmd' => 'storage/app/scratch/lane48-tile-fallback.php',
        'parts' => ['screens' => '/Screens: (\d+)\.\s+Tiles read: \d+\./', 'tiles' => '/Screens: \d+\.\s+Tiles read: (\d+)\./'],
        'labels' => ['screens' => 'screens', 'tiles' => 'tiles'],
        'db' => false,
    ],
    'lane49-zero-shown-as-absence' => [
        'cite' => '/lane49-zero-shown-as-absence/i',
        'cmd' => 'storage/app/scratch/lane49-zero-shown-as-absence.php',
        'parts' => [
            'dashGuards' => '/Guards ending in a dash: (\d+)/',
            'unread' => '/Not read \(a dash on the line, no recoverable operand\): (\d+)/',
            'nonFigures' => '/Not a money\/percent figure the guard itself prints: (\d+)/',
            'blind' => '/tail written on its own line \((\d+)\)/',
        ],
        // AGENTS.md's phrase "guards whose dash sits on a line it cannot join" is the tool's unread count, and
        // "lines where the operand is a name…" is its non-figure count; the mapping is this checker's whole claim.
        'labels' => ['guards?' => 'unread', 'lines' => 'nonFigures', 'blind spots' => 'blind'],
        'db' => false,
    ],
    'lane50-date-day-shift' => [
        'cite' => '/lane50-date-day-shift/i',
        'cmd' => 'storage/app/scratch/lane50-date-day-shift.php',
        'parts' => [
            'files' => '/Files read: (\d+)/',
            'dayCells' => '/DAY-FORMATTER: (\d+)/',
            'momentCells' => '/SCHOOL-FORMATTER: (\d+)/',
            'blind' => '/\((\d+) total\)/',
        ],
        'labels' => ['school-moment|moments?' => 'momentCells', 'day cells?' => 'dayCells', 'cells?' => 'dayCells',
            'blind spots' => 'blind', 'files' => 'files'],
        'db' => false,
    ],
    'record-states' => [
        'cite' => '/ems:audit:record-states|record state audit|the standing audit/i',
        'cmd' => 'artisan ems:audit:record-states',
        'parts' => ['rules' => '/Rules run: (\d+)/'],
        'labels' => ['rules' => 'rules'],
        'db' => true,
    ],
    'demo-pipeline' => [
        'cite' => '/seed_full_demo_pipeline|the generator\'?s? (own )?report|desk report/i',
        'cmd' => 'log:lane58-pipeline-live.txt',
        'parts' => ['ledger' => '/(Ledger —.*)\n/s'],
        'labels' => ['Ledger lines?' => 'ledger', 'lines' => 'ledger'],
        'db' => true,
    ],
];

// ------------------------------------------------------------------ the readings

$readings = [];
if (($rf = $opt('--readings')) !== null) {
    $path = is_file($rf) ? $rf : $scratch.'/'.basename($rf);
    $j = json_decode((string) @file_get_contents($path), true);
    if (! is_array($j) || $j === []) {
        echo "REFUSED: --readings=".basename($path)." is not a readings file written by this checker's --dump-readings.\n";
        exit(2);
    }
    $readings = $j;
}

if ($flag('--run')) {
    $only = $opt('--only');
    $want = $only !== null ? array_map('trim', explode(',', $only)) : array_keys($INSTRUMENTS);
    foreach ($want as $key) {
        if (! isset($INSTRUMENTS[$key])) {
            echo "REFUSED: --only names no instrument called {$key} — a typo in a scope flag reads as a clean run.\n";
            exit(2);
        }
        $spec = $INSTRUMENTS[$key];
        if ($flag('--ci') && $spec['db']) {
            printf("run      %-32s skipped in CI mode (needs a live database)\n", $key);

            continue;
        }
        if (str_starts_with($spec['cmd'], 'log:')) {
            // The demo pipeline writes live `ems`, so it is never run to make a reading: the log the last gated run left
            // behind is counted instead, and its line count is what the documents claim.
            $log = (string) @file_get_contents($scratch.'/'.substr($spec['cmd'], 4));
            $vals = [];
            foreach ($spec['parts'] as $part => $re) {
                $vals[$part] = $part === 'ledger' ? preg_match_all('/Ledger —/', $log) : null;
            }
            $readings[$key] = $vals;
            printf("run      %-32s %d (counted in %s, which this tool is not run to make)\n", $key, $vals['ledger'], substr($spec['cmd'], 4));

            continue;
        }
        $c = $spec['cmd'];
        $full = str_starts_with($c, 'artisan ')
            ? 'cd '.escapeshellarg($root).' && '.escapeshellarg(PHP_BINARY).' artisan '.escapeshellarg(substr($c, 8))
            : 'cd '.escapeshellarg($root).' && '.escapeshellarg(PHP_BINARY).' '.escapeshellarg($c);
        $out = (string) shell_exec($full.' 2>&1');
        $vals = [];
        $missing = [];
        foreach ($spec['parts'] as $part => $re) {
            if (preg_match($re, $out, $m) === 1) {
                $vals[$part] = (int) $m[1];
            } else {
                $missing[] = $part;
            }
        }
        if ($missing !== []) {
            $readings[$key] = null;
            printf("run      %-32s PRINTED NO %s — its claims cannot be judged, and are refused\n", $key, implode('/', $missing));
        } else {
            $readings[$key] = $vals;
            printf("run      %-32s %s\n", $key, implode(' / ', array_map(static fn ($k, $v): string => $k.'='.$v, array_keys($vals), $vals)));
        }
    }
}

if ($readings === []) {
    echo "REFUSED: no readings. Use --run (this box's tools), --readings=<file>, or both.\n";
    echo "         A checker with no measurement is a document agreeing with itself.\n";
    exit(2);
}
if ($flag('--dump-readings')) {
    // The collector's contract: this file owns the summary-line regexes, and hands back what it measured.
    echo "\nREADINGS_JSON:".json_encode($readings)."\n";
    exit(0);
}
echo "\n";

// ------------------------------------------------------------------ the claims

$docs = [
    ['AGENTS.md', 'AGENTS.md'],
    ['DEPLOYMENT.md', 'DEPLOYMENT.md'],
    ['README.md', 'README.md'],
    ['docx', 'storage/app/scratch/docx-flat.txt'],
    ['Per-Office-UAT-Walkthrough.md', 'Documentation/Per-Office-UAT-Walkthrough.md'],
    ['Demo-Day-Guide.md', 'Documentation/Demo-Day-Guide.md'],
    ['Offline-Presentation-Guide.md', 'Documentation/Offline-Presentation-Guide.md'],
    ['System_Storyboard_Walkthrough.md', 'Documentation/System_Storyboard_Walkthrough.md'],
    ['SEAIT-EMS-Round3-Current-State.md', 'SEAIT-EMS-Round3-Current-State.md'],
];
if (($flat = $opt('--flat')) !== null) {
    $docs[3] = ['docx', $flat];
}
// `--doc=AGENTS.md=storage/app/scratch/lane59-mut/copy.md` points one document at a copy. The mutant harness needs it,
// and an unknown label is refused rather than ignored: lane 41's harness passed --dir= that its code never read, and an
// ignored flag makes a mutant run look exactly like a clean one.
if (($docOpt = $opt('--doc')) !== null) {
    foreach (array_filter(explode(',', $docOpt)) as $pair) {
        if (! str_contains($pair, '=')) {
            echo "REFUSED: --doc “{$pair}” is not label=path.\n";
            exit(2);
        }
        [$l, $p] = explode('=', $pair, 2);
        $hit = false;
        foreach ($docs as $i => $d) {
            if ($d[0] === $l) {
                $docs[$i] = [$l, $p];
                $hit = true;
            }
        }
        if (! $hit) {
            printf("REFUSED: --doc names “%s”, which this checker does not read — the mutation would have gone unseen.\n", $l);
            exit(2);
        }
    }
}
if (($onlyDocs = $opt('--docs')) !== null) {
    $docs = array_values(array_filter($docs, static fn (array $d): bool => in_array($d[0], explode(',', $onlyDocs), true)));
    if ($docs === []) {
        echo "REFUSED: --docs named no document this checker knows.\n";
        exit(2);
    }
}

$NUMNC = '(?:\d[\d,]*|'.implode('|', array_keys($WORDS)).')';
$HISTORY_RE = '/(first version|its first|previously|had been|was |were |found|corrected|caught|reported|printed|at the time|back in|used to|it once|which had|slept because|stopped applying|\bthen\b|on that date|on that day)/i';
$DATE_RE = '/(\d{4}-\d{2}-\d{2}|(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},\s*\d{4})/';
// A §1.1 revision row whose first cell is a bare date or date range IS the dated record — "2026-10-11–15 |" describes
// what the build did then, so "ran fourteen rules" inside it is history. The cell that reads "2026-10-16–20 (this
// issue)" is not bare: three later lanes wrote into it, and a figure in a living row is a claim about now. That
// distinction is the whole of lane 58's finding, applied to this class.
// The en dash in "2026-10-11–15" is three bytes, so the class needs /u: without it the dash is matched byte-by-byte,
// the row reads as undated, and a historic figure in §1.1 is refused as a stale claim about today.
$DATED_ROW_RE = '/^ROW \d+: \d{4}-\d{2}-\d{2}(?:[\x{2013}\x{2014}-]\d{1,2})?\s*\|/u';
// The figure plus up to three following words: the phrase, not the last noun, decides which reading answers it.
$claimRe = '/('.$NUMNC.')((?:\s+[A-Za-z§\/&-]+){1,3})/';

$trace = $flag('--trace');
$stale = 0;
$unbound = 0;
$history = 0;
$judged = 0;
$bound = 0;
$matched = 0;
$ciUnmeasured = [];

foreach ($docs as [$label, $path]) {
    $bytes = (string) @file_get_contents($root.'/'.$path);
    if ($bytes === '') {
        printf("  (absent) %s\n", $label);

        continue;
    }
    foreach (preg_split('/\n/', str_replace("\r\n", "\n", $bytes)) ?: [] as $idx => $line) {
        $rowDated = $label === 'docx' && preg_match($DATED_ROW_RE, $line) === 1;
        // preg_replace returns an empty string for a whitespace-only line, and `?: []` would then hand preg_split an
        // array; preg_match_all returns an int, and `?: []` would hand the foreach below that int. Both guards are
        // cast instead, because the second one made every claim loop a warning that a mutant read as a pass.
        $flat = (string) preg_replace('/\s+/', ' ', $line);
        foreach ((array) (preg_split('/(?<=[.;?!])\s+/', $flat) ?: []) as $sentence) {
            foreach ($INSTRUMENTS as $key => $spec) {
                if (preg_match($spec['cite'], $sentence) !== 1 || ($flag('--ci') && $spec['db'])) {
                    continue;
                }
                // "Not measured on this runner" and "measured, and printed nothing" are different verdicts: the first is
                // a scope limit the report names, the second is a hole — and a checker that treats them alike lets a tool
                // that broke look like a tool that was never in scope. --only narrows which keys exist at all.
                if ($flag('--ci') && ! array_key_exists($key, $readings)) {
                    $ciUnmeasured[$key] = ($ciUnmeasured[$key] ?? 0) + 1;

                    continue;
                }
                $reading = $readings[$key] ?? null;
                $labels = $spec['labels'];
                uksort($labels, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
                $saw = false;
                $all = [];
                preg_match_all($claimRe, $sentence, $all, PREG_SET_ORDER);
                foreach ($all as $cm) {
                    $claimed = $toNum($cm[1]);
                    $phrase = strtolower(trim($cm[2]));
                    if ($claimed === null || $phrase === '') {
                        continue;
                    }
                    $part = null;
                    foreach ($labels as $pattern => $p) {
                        if (preg_match('/\b(?:'.$pattern.')\b/i', $phrase) === 1) {
                            $part = $p;
                            break;
                        }
                    }
                    if ($part === null) {
                        continue;
                    }
                    $value = is_array($reading) ? ($reading[$part] ?? null) : null;
                    $isHistory = preg_match($HISTORY_RE, $sentence) === 1
                        || preg_match($DATE_RE, $sentence) === 1
                        || $rowDated;
                    $saw = true;
                    $bound++;
                    printf("%-8s %-40s :%-5d %-22s claimed %5d %-12s %s\n", $isHistory ? 'HISTORY' : 'PRESENT',
                        $label.' › '.$key, $idx + 1, trim($cm[0]), $claimed,
                        $isHistory ? '(not judged)' : ($value === null ? 'UNBOUND' : ($claimed === $value ? 'MATCHES' : 'STALE')),
                        substr(trim($sentence), 0, 80));
                    if ($isHistory) {
                        $history++;

                        continue;
                    }
                    $judged++;
                    if ($value === null) {
                        $unbound++;
                        printf("           %s→ %s answers no '%s' figure — a hole must not look checked\n", str_repeat(' ', 12), $key, $part);

                        continue;
                    }
                    if ($claimed === $value) {
                        $matched++;

                        continue;
                    }
                    $stale++;
                    printf("           %s→ %s prints %d today\n", str_repeat(' ', 12), $key, $value);
                }
                if ($trace && ! $saw) {
                    fwrite(STDERR, "  cited-no-figure  {$key} :: ".substr(trim($sentence), 0, 96)."\n");
                }
            }
        }
    }
}

printf("\n%d figure(s) cited a named instrument: %d judged (%d match, %d stale, %d unbound), %d history.\n",
    $bound, $judged, $matched, $stale, $unbound, $history);
if ($ciUnmeasured !== []) {
    $named = [];
    foreach ($ciUnmeasured as $k => $n) {
        $named[] = $k.' ×'.$n;
    }
    printf("Named, not judged, in CI mode (%s): %s\n", implode(', ', $named),
        'these tools need a live database or were left out by --only; a reading that does not exist here is not a pass');
}
if ($bound === 0) {
    echo "REFUSED: no instrument figure was read — a checker that matched nothing is not a clean document.\n";
    exit(2);
}
if ($stale > 0 || $unbound > 0) {
    echo "VERDICT: refuse — {$stale} stale and {$unbound} unbound instrument figure(s).\n";
    exit(1);
}
echo "VERDICT: every present-tense instrument figure equals what its tool prints today.\n";
exit(0);
