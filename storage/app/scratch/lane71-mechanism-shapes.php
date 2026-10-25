<?php

/**
 * Lane 71, stage 2: keep only the mechanism sentences a probe can FALSIFY.
 *
 * The stage-1 census (lane71-mechanism-census.php) found 1,762 "candidates" in 4,488 sentences — far too wide, and it
 * caught prose about the document itself ("This document is the canonical functional and data specification…"). A rule
 * that cannot be checked is not a checker: lane 46's first filter tool cleared the tree because it read the wrong
 * construct, and a 1,762-row queue would be ignored the way the anchor checker's red is. So keep only two shapes, each
 * with a probe that can answer it from the repository:
 *
 *   VALUE  — the sentence asserts that something a file names HAS a literal: `notifiable_type = "App\Models\Students"`,
 *            `the array holds exactly seven keys`, `status is enum('active','inactive')`, `writes paid`.
 *            Probe: read the named file (or the cited span) and test whether the asserted token appears.
 *   NEG    — the sentence asserts that something does NOT exist or never happens: `no code reads X`, `nothing
 *            references X`, `no desk can produce X`, `X is never displayed`, `no migration mentions Y`.
 *            Probe: grep app/, resources/, routes/, database/, tools/, tests/ for the named identifier.
 *
 * Output is a queue with counts per bucket and the probe's answer where one was attempted; nothing exits non-zero
 * yet, because the checker's precision has not been measured (the lane 70 rule: promote only after the sample is
 * read). --limit=N skims; the run refuses if it prints fewer rows than it tallies.
 *
 * Usage: php storage/app/scratch/lane71-mechanism-shapes.php [--dump=path] [--limit=40] [--all]
 *
 * MEASURED RESULT, 2026-10-25 — read before trusting a line below. The CLASSIFIER works: 4,590 sentences yield 73
 * VALUE claims and 16 NEG claims, 406 dated/history, and 54 of the VALUE claims are one uniform §24 family that
 * `lane71-model-decls.php` now checks properly (54 OK, and `mutate-lane71-model-decls.php` proves it fails both
 * ways). The VERDICT PROBE on this file does NOT work, and four observed failures say why a corpus-wide
 * `substr_count` cannot judge a mechanism sentence at all:
 *   - `Clearanceperiods::accepting()` was reported REFUTED because the string appears 12 times, though the sentence
 *     negates the QUERY returning rows, not the method existing;
 *   - `$primaryKey = 'studentId'` was called consistent because `studentId` occurs 926 times somewhere in `app/`;
 *   - `enum(` matched 66 times and proved nothing about the enum a sentence describes;
 *   - a §1.1 history row ("It found one dead right that every role holds and no code reads") reached the NEG bucket at
 *     all, because the past-tense marker sits in another clause and `$PAST` has no `found`.
 * So treat the printed rows as a QUEUE of sentences to bind, and the verdicts as unfalsified noise: each shape needs
 * its own subject→predicate probe (column values from `information_schema`, a span's parsed keys, a method's call
 * sites), the way lanes 46, 47, 50, 60, 64 and 71-each-needed-their-own-binding. Nothing here is a gate.
 */
$root = dirname(__DIR__, 3);
$dumpPath = $root.'/storage/app/scratch/docx-flat.txt';
$limit = 40;
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with((string) $a, '--dump=')) {
        $dumpPath = substr((string) $a, 7);
    } elseif (str_starts_with((string) $a, '--limit=')) {
        $limit = (int) substr((string) $a, 8);
    } else {
        printf("REFUSED: unknown argument [%s].\n", $a);
        exit(1);
    }
}
if (! is_file($dumpPath)) {
    printf("REFUSED: no dump at %s — run dump-docx-flat.php first.\n", $dumpPath);
    exit(1);
}

// The repository the probes read. Measured once, as the sweep learned to do.
$scanRoots = ['app', 'resources/js', 'resources/views', 'routes', 'database', 'tools', 'tests'];
$haystack = '';
$files = 0;
foreach ($scanRoots as $r) {
    $dir = $root.'/'.$r;
    if (! is_dir($dir)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (! $f->isFile() || ! preg_match('/\.(php|js|jsx|blade\.php|mjs)$/i', $f->getFilename())) {
            continue;
        }
        $haystack .= (string) file_get_contents($f->getPathname());
        $files++;
    }
}
printf("probe corpus: %d files, %s bytes\n", $files, number_format(strlen($haystack)));

$lines = explode("\n", (string) file_get_contents($dumpPath));
$DATED = '/(?:as of \d{4}|\bon \d{4}-\d{2}-\d{2}|\b\d{4}-\d{2}-\d{2}\b|\(this issue\)|it used to|before ruling|no longer|was corrected|Corrected \d{4}|since \d{4}-\d{2}|when it was written)/u';
$VALUE = '/(?:\b(?:is|are|was|were|holds?|stores?|writes?|carries?|equals?|reads?|gives?|answers?|returns?)\s+(?:exactly\s+)?[\'"`]([A-Za-z0-9_\\\\\/.,: ()|\x{2018}\x{2019}\x{201C}\x{201D}-]{2,40})[\'"`])|(?:=\s*[\'"`]([A-Za-z0-9_\\\\\/.,: ()|\x{2018}\x{2019}\x{201C}\x{201D}-]{2,40})[\'"`])/u';
$NEG = '/\b(?:no|nothing|nowhere|never|not|without)\b[^.]{0,80}?\b(code|file|reader|writer|desk|surface|migration|controller|test|row|reference|call site|caller|query|screen)\b\s+(?:that |which )?(?:reads?|writes?|references?|mentions?|calls?|uses?|shows?|displays?|queries?|consults?|produces?|hits?|touches?|names?|exercises?|gates?|checks?)\b/u';
$IDENT = '/(?:`([a-z][A-Za-z]{3,29})`|([A-Z][a-z]{3,29}(?:Policy|Controller|Service|Seeder|Service::[a-z]\w+|::[a-z]\w+)))/';

$counts = ['VALUE' => 0, 'NEG' => 0, 'history' => 0];
$printed = ['VALUE' => 0, 'NEG' => 0];
$agree = ['VALUE' => 0, 'NEG' => 0];
$refute = ['VALUE' => 0, 'NEG' => 0];
$unread = ['VALUE' => 0, 'NEG' => 0];
$sentTotal = 0;
$rows = [];
foreach ($lines as $i => $line) {
    $t = trim((string) $line);
    if ($t === '' || preg_match('/^(ROW|P) \d+: /', $t) !== 1) {
        continue;
    }
    $t = (string) preg_replace('/^(ROW|P) \d+: /', '', $t);
    foreach (preg_split('/(?<=[.!?]) (?=[A-Z\x{201C}`])/u', $t) as $sent) {
        $sent = trim((string) $sent);
        if (mb_strlen($sent) < 20) {
            continue;
        }
        $sentTotal++;
        if (preg_match($DATED, $sent) === 1) {
            $counts['history']++;

            continue;
        }
        $kind = null;
        if (preg_match_all($NEG, $sent, $mm) >= 1) {
            $kind = 'NEG';
        } elseif (preg_match_all($VALUE, $sent, $mv) >= 1) {
            $kind = 'VALUE';
        }
        if ($kind === null) {
            continue;
        }
        $counts[$kind]++;
        $rows[] = [$kind, $i + 1, $sent];
    }
}

printf("\n%d sentence(s) read: %d VALUE claims, %d NEG claims, %d dated/history, %d not a probeable shape.\n",
    $sentTotal, $counts['VALUE'], $counts['NEG'], $counts['history'],
    $sentTotal - $counts['VALUE'] - $counts['NEG'] - $counts['history']);

foreach ($rows as [$kind, $ln, $sent]) {
    $tok = null;
    if ($kind === 'VALUE') {
        if (preg_match($VALUE, $sent, $v) === 1) {
            $tok = trim((string) ($v[1] !== '' ? $v[1] : ($v[2] ?? '')));
        }
    } else {
        if (preg_match($IDENT, $sent, $x) === 1) {
            $tok = trim((string) ($x[1] !== '' ? $x[1] : ($x[2] ?? '')));
        }
    }
    $verdict = 'no token to probe';
    if ($tok !== null && $tok !== '' && strlen($tok) >= 4) {
        $hits = substr_count($haystack, $tok);
        if ($kind === 'VALUE') {
            $verdict = $hits > 0 ? sprintf('token [%s] appears %d time(s) in the corpus — reads consistent', $tok, $hits)
                : sprintf('REFUTED: token [%s] appears NOWHERE in %d files', $tok, $files);
            $hits > 0 ? $agree[$kind]++ : $refute[$kind]++;
        } else {
            $verdict = $hits > 0
                ? sprintf('REFUTED: the sentence says none exists, but [%s] appears %d time(s)', $tok, $hits)
                : sprintf('consistent: [%s] appears nowhere in %d files', $tok, $files);
            $hits > 0 ? $refute[$kind]++ : $agree[$kind]++;
        }
    } else {
        $unread[$kind]++;
    }
    if ($printed[$kind] < $limit) {
        $printed[$kind]++;
        printf("\n%s line %d: %s\n    %s\n", $kind, $ln, str_replace("\n", ' ', mb_substr($sent, 0, 200)), $verdict);
    }
}
printf("\nVALUE: %d agree, %d refuted, %d unprobeable.  NEG: %d agree, %d refuted, %d unprobeable.\n",
    $agree['VALUE'], $refute['VALUE'], $unread['VALUE'], $agree['NEG'], $refute['NEG'], $unread['NEG']);
$withheld = 0;
foreach (['VALUE', 'NEG'] as $k) {
    $withheld += max(0, $counts[$k] - $printed[$k]);
}
if ($withheld > 0) {
    printf("the queue above is INCOMPLETE: %d row(s) withheld (raise --limit or read the whole queue).\n", $withheld);
}
if ($counts['VALUE'] + $counts['NEG'] === 0) {
    echo "\nREFUSED: 0 probeable claims read — a broken read, not a clean document.\n";
    exit(1);
}
echo "\n(stage 2 reports; it is NOT a gate. Precision is measured by reading these rows before any exit code is wired.)\n";
