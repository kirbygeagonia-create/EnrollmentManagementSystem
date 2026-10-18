<?php

/**
 * Lane 49 — a real zero must not be painted as absence.
 *
 * Lane 48 stopped the screen inventing a number the server never sent. This is the mirror: the number *is*
 * zero — an account with nothing paid, a scholarship covering 0%, a student assessed ₱0 — and the display
 * tests it with truthiness, so the desk gets the same em dash it gets when the figure is missing. "Paid —"
 * and "Paid ₱0.00" answer different questions, and only one of them is a fact about the money.
 *
 *   HIDDEN-ZERO   the tested operand is **the money or percentage the branch then prints** — `totalPaid > 0 ?
 *                 `₱${totalPaid…}` : '—'`, `coveragePercent ? `${coveragePercent}%` : '—'` — so the figure's own
 *                 0 falls to the absence branch. An account with nothing paid and a student with no fee sheet
 *                 render identically, and only one of them is a fact about the money.
 *   PRESENCE-TEST the honest shape, counted as checked: `x == null ? '—' : …`, `x != null ? …`, or a test on
 *                 the *list* the figure came from (`assessed.length > 0 ? ₱${totalAssessed} : '—'`).
 *   NOT-A-FIGURE  the else branch is `''` (a validation class, an empty subtitle), the operand is a label,
 *                 code, date or error message, or the formatted branch is a description rather than the
 *                 tested amount (the class card's `total > 0 ? "N (x lec / y lab)" : '—'`, where a subject with
 *                 no units is not a quantity the school awards). Listed, not judged.
 *   UNBOUND       a guard that ends in a dash but whose tested operand this file cannot recover — most often a
 *                 ternary written across lines. Named and counted in the summary, never silently skipped: the
 *                 first draft of this checker judged single lines only and would otherwise have looked clean
 *                 while blind to the multi-line cells that started the lane.
 *
 * What it does NOT claim. It reads one physical line, so a ternary split across lines is reported rather than
 * judged. It does not execute React, cannot see arithmetic that produces 0, and does not claim a 0 exists in
 * any column: whether the hidden zero is reachable on real data was measured by hand against live `ems`
 * (`studentassessments.remainingBalance` holds 0 today; `scholarshiptypes.coveragePercent` is NOT NULL decimal
 * and the Admin desk can create 0).
 *
 * Scope is a flag so a harness can prove the scan runs: EMS_LANE49_PAGES=Students,Assessment restricts which
 * files are read, and a scope matching nothing refuses rather than reporting clean.
 *
 * Usage: php storage/app/scratch/lane49-zero-shown-as-absence.php [--debug=Students]
 */

$root = dirname(__DIR__, 3);
$oneLine = fn (string $s): string => preg_replace('/\s+/', ' ', $s);

$scope = getenv('EMS_LANE49_PAGES');
$scope = $scope === false || trim($scope) === '' ? null : array_values(array_filter(array_map('trim', explode(',', $scope))));

$debug = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--debug=')) {
        $debug = substr($arg, strlen('--debug='));
    }
}

/** The vocabulary that marks an operand as a figure rather than a label — kept for the mutants' messages. */
$numericName = '/(amount|balance|paid|total|assessment|assessed|percent|coverage|units|fee|revenue|credit|qty|quantity|load|slots|count|days|hours|steps|score|sum|debt|owed)$/i';

$problems = 0;
$counts = ['HIDDEN-ZERO' => 0, 'PRESENCE-TEST' => 0, 'NOT-A-FIGURE' => 0, 'UNBOUND' => 0];
$files = 0;
$lines = 0;
$blind = [];
$skipped = [];

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/resources/js'));
foreach ($it as $f) {
    if ($f->isDir() || ! preg_match('/\.(jsx|js)$/', $f->getFilename())) {
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
    if (! str_contains($src, '—')) {
        continue;
    }
    $files++;
    foreach (preg_split('/\n/', $src) as $i => $line) {
        $raw = trim($line);
        $text = $oneLine($raw);

        // a ternary's tail written on its own line: this file reads one line at a time, so say so
        if (preg_match('/^:\s*\?\'—\'|^:\s*\'—\'/u', $raw)) {
            $blind[] = $rel.':'.($i + 1);
            $counts['UNBOUND']++;

            continue;
        }

        // the false branch must be an absence marker; `: ''` is an empty class or subtitle, not a dash
        if (! preg_match('/:((?:[^:]|\s)*)\'—\'/u', $text, $fb) && ! preg_match('/:((?:[^:]|\s)*)"—"/u', $text, $fb)) {
            if (preg_match('/\?\s*[^:]{1,120}:\s*\'\'/', $text) || preg_match("/\|\|\s*''/", $text)) {
                $counts['NOT-A-FIGURE']++;
            }

            continue;
        }
        $lines++;

        // the reversed spelling — `X == null ? '—' : <the amount>` — is the same presence test with the branches
        // swapped, and a checker that left it in the unread pile would look blind exactly where the fix landed
        if (preg_match('/(\w+(?:\??\.\w+)*)\s*[=!]==?\s*(?:null|undefined)\s*\?\s*[\'"]—[\'"]/u', $text, $gm)) {
            $counts['PRESENCE-TEST']++;
            printf("  ok           %s:%d  presence of %s decides it, dash first: %s\n", $rel, $i + 1, $gm[1], mb_substr($text, 0, 100));

            continue;
        }

        // `X ? <shows X as money or percent> : '—'` and its `X > 0 ? …` variant. The branch between `?` and the
        // colon is the one that *displays* the figure; reading the segment before the dash instead — the false
        // branch — asks a question about nothing, which is exactly how the first draft of this file let all
        // three reverted shapes through as "clean".
        $tested = null;
        $branch = null;
        $shape = null;
        $tern = '(?:[^?()]|\?\.|\([^\)]*\))*?';
        if (preg_match('/(\w+(?:\??\.\w+)*)\s*(?:[<>]=?\s*0|===?\s*0(?!\s*=))\s*\?('.$tern.')\s*:\s*[\'"]—[\'"]/u', $text, $gm)) {
            [$tested, $branch, $shape] = [$gm[1], $gm[2], 'magnitude'];
        } elseif (preg_match('/(\w+(?:\??\.\w+)*)\s*\?('.$tern.')\s*:\s*[\'"]—[\'"]/u', $text, $gm)) {
            [$tested, $branch, $shape] = [$gm[1], $gm[2], 'truthy'];
        } elseif (preg_match('/(\w+(?:\??\.\w+)*)\s*\|\|\s*[\'"]—[\'"]/u', $text, $gm)) {
            // `X || '—'` displays the operand bare, so its name is all there is to go on
            [$tested, $branch, $shape] = [$gm[1], $gm[1], 'or'];
        }

        if ($tested === null) {
            $counts['UNBOUND']++;
            printf("  UNBOUND      %s:%d  %s\n", $rel, $i + 1, mb_substr($text, 0, 120));

            continue;
        }

        // an explicit presence test, or a test on the list the figure came from, is the shape this lane wants
        if (preg_match('/[=!]==?\s*(null|undefined)/', $text) || str_ends_with($tested, '.length')
            || preg_match('/\b\w+(?:\??\.\w+)*\.length\s*(?:[<>]=?|===?\s*0)\s*\?/', $text)
            || preg_match('/\b\w+\.filter\(|\.some\(|\.every\(/', $text)) {
            $counts['PRESENCE-TEST']++;
            printf("  ok           %s:%d  presence of %s decides it, not the amount: %s\n", $rel, $i + 1, $tested, mb_substr($text, 0, 100));

            continue;
        }

        // the load-bearing test: does the branch that *shows* something render this operand as money or
        // percent? `${id}` inside a name template is text, so the sign must sit on this operand's own braces
        $stem = preg_split('/\./', $tested)[count(preg_split('/\./', $tested)) - 1];
        $rendersIt = false;
        $segs = [];
        preg_match_all('/(?<pre>..)\{(?<inner>[^{}]*)\}(?<post>.?)/u', (string) $branch, $segs, PREG_SET_ORDER);
        foreach ($segs as $s) {
            if (! preg_match('/\b'.preg_quote($stem, '/').'\b/', $s['inner'])) {
                continue;
            }
            if (mb_substr($s['pre'], -1) === '$' && mb_substr($s['pre'], 0, 1) === '₱') {
                $rendersIt = true;

                break;
            }
            if ($s['post'] === '%') {
                $rendersIt = true;

                break;
            }
        }
        if (! $rendersIt && $shape === 'or' && preg_match($numericName, $stem)) {
            // a bare `{total || '—'}` shows no sign at all; then the name is the only evidence, so it is the rule
            $rendersIt = true;
        }
        if (! $rendersIt) {
            $counts['NOT-A-FIGURE']++;
            $skipped[] = $rel.':'.($i + 1).' '.$tested;

            continue;
        }

        $counts['HIDDEN-ZERO']++;
        $problems++;
        printf("  HIDDEN-ZERO  %s:%d  `%s` is its own guard, so a real 0 of it prints as absence\n", $rel, $i + 1, $tested);
        printf("               %s\n", mb_substr($text, 0, 130));
    }
}

$blind = array_values(array_unique($blind ?? []));
$skipped = array_values(array_unique($skipped ?? []));

if ($lines === 0 || $files === 0) {
    fwrite(STDERR, 'REFUSED: no dash-returning guard was read'.($scope === null ? '' : ' under EMS_LANE49_PAGES='.implode(',', $scope))." — an empty scan is not a clean one.\n");
    exit(1);
}
if ($counts['HIDDEN-ZERO'] + $counts['PRESENCE-TEST'] === 0) {
    fwrite(STDERR, "REFUSED: nothing was judged — the scan read {$lines} guard(s) and classified every one as a string or unreadable.\n");
    exit(1);
}

printf("\nFiles read: %d.  Guards ending in a dash: %d.\n", $files, $lines);
printf("Judged: %d hidden zero(s), %d correct presence-tests.\n", $counts['HIDDEN-ZERO'], $counts['PRESENCE-TEST']);
printf("Not a money/percent figure the guard itself prints: %d\n", $counts['NOT-A-FIGURE']);
printf("Not read (a dash on the line, no recoverable operand): %d\n", $counts['UNBOUND']);
if ($blind !== []) {
    printf("\nBlind spots — a ternary's `: '—'` tail written on its own line (%d):\n  %s\n", count($blind), implode("\n  ", array_slice($blind, 0, 12)));
}
if ($skipped !== []) {
    printf("\nNot judged as figures (sample of 12 of %d):\n  %s\n", count($skipped), implode("\n  ", array_slice($skipped, 0, 12)));
}
if ($debug !== null) {
    echo "\n(debug accepted; the per-line verdicts above are the detail)\n";
}

printf("\n%d problem(s).\n", $problems);
exit($problems === 0 ? 0 : 1);
