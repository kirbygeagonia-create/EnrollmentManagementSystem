<?php

/**
 * Lane 58 — every place a suite size is stated, and whether it claims to be true now.
 *
 * SUPERSEDED the same day by storage/app/scratch/lane58-suite-claims.php, which is what the gates and CI run. This first
 * pass is kept because its under-report is the measurement: it printed "6 suite-size claims" where the documents hold 15,
 * because it read one claim per line, took only the first pair on a line, required digits and a connective word, dropped
 * every comma-formatted figure via `is_numeric("1,638") === false`, and had no claim class — so it called SchoolTimeTest's
 * TRUE "6 cases / 33 assertions" a stale suite total while missing three real claims. Run the mutant harness
 * (mutate-lane58-suite-claims.php) rather than this file if you want an answer.
 *
 * The repository has been here before with other numbers: §28.3's size was stated in two places and one of them
 * stopped agreeing with the table (pass 142), the audit's rule count was restated in five files by hand (lanes 56
 * and 57), and the demo report's line count turned out to be the harness's number, not the tool's. A test-suite
 * figure is the same kind of claim — it moves with every case anyone adds — and today's green run says 780 tests
 * and 5,220 assertions on this box while the document says "The suite stands at 771 tests and 5191 assertions".
 *
 * This step only measures. Each hit is reported with the sentence around it and a tense verdict: a claim carrying a
 * date or a past-tense verb is history and stays, a claim in the present is a fact about this build and has to
 * match one of the measured readings below. The two are not the same number, and a checker that conflates them will
 * either nag about history or bless a stale claim — the failure lane 42 had to design around.
 *
 *   php storage/app/scratch/lane58-probe-suite-claims.php
 */

$root = dirname(__DIR__, 3);

/** The readings that are true right now, from logs written by this batch's runs. */
$measured = [];
foreach (['lane57-suite.txt' => 'this box, `artisan test` whole', 'lane57-ci-mysql.log' => 'CI suite step', 'lane57-ci-final.json' => 'CI jobs'] as $f => $label) {
    $t = preg_replace('/\x1b\[[0-9;]*m/', '', (string) @file_get_contents($root.'/storage/app/scratch/'.$f));
    if (preg_match('/Tests:\s*(\d+) skipped, (\d+) passed \((\d+) assertions\)/', $t, $m) === 1) {
        printf("measured  %-26s %s skipped, %s passed (%s assertions)\n", $label, $m[1], $m[2], $m[3]);
        $measured[] = ['tests' => (int) $m[2], 'assertions' => (int) $m[3]];
    } elseif (preg_match('/Tests:\s*(\d+) passed \((\d+) assertions\)/', $t, $m) === 1) {
        printf("measured  %-26s %s passed (%s assertions)\n", $label, $m[1], $m[2]);
        $measured[] = ['tests' => (int) $m[1], 'assertions' => (int) $m[2]];
    }
}
if (preg_match('/OK \((\d+) tests, (\d+) assertions\)/', (string) @file_get_contents($root.'/storage/app/scratch/lane57-ci-mysql.log'), $e) === 1) {
    printf("measured  %-26s %s tests (%s assertions)\n", 'CI walkthrough step', $e[1], $e[2]);
}
echo "\n";

$history = '/(202\d-\d\d-\d\d|was measured|at that time|then|once|had been|as of|before|on 202\d|first|previously|passed at)/i';

$scan = function (string $label, string $path) use ($root, $history, $measured): int {
    $bytes = (string) @file_get_contents($root.'/'.$path);
    if ($bytes === '') {
        return 0;
    }
    $bytes = str_replace("\r\n", "\n", $bytes);
    $found = 0;

    foreach (preg_split('/\n/', $bytes) as $i => $line) {
        // A suite-size claim mentions assertions; anything else is unrelated prose about a single figure.
        if (preg_match('/\d[\d,]*\s+assertions/i', $line) !== 1) {
            continue;
        }
        preg_match_all('/(\d[\d,]*)\s+(?:tests?|cases?|passed)[^.\n]{0,60}?(\d[\d,]*)\s+assertions/i', $line, $pair, PREG_SET_ORDER);
        if ($pair === []) {
            continue;
        }
        $context = preg_replace('/\s+/', ' ', substr($line, max(0, (int) strpos($line, $pair[0][0]) - 90), 230));
        $isHistory = preg_match($history, $context) === 1;
        $tests = (int) str_replace(',', '', $pair[0][1]);
        $assertions = (int) str_replace(',', '', $pair[0][2]);
        $matches = false;
        foreach ($measured as $m) {
            if ($m['tests'] === $tests && $m['assertions'] === $assertions) {
                $matches = true;
                break;
            }
        }

        printf("  %-8s %-34s :%-5d %s  %s\n", $isHistory ? 'HISTORY' : 'PRESENT', $label, $i + 1,
            $isHistory ? ' ' : ($matches ? 'MATCHES a current reading' : 'STALE — matches no current reading'),
            $context);
        $found++;
    }

    return $found;
};

// Every markdown file in the project, not a hand-kept list: the whole point of this probe is that a claim can sit
// in a document nobody remembered to enumerate. The flat dump carries the authority docx.
$targets = [['docx (flat dump)', 'storage/app/scratch/docx-flat.txt'], ['AGENTS.md', 'AGENTS.md'], ['DEPLOYMENT.md', 'DEPLOYMENT.md'], ['README.md', 'README.md']];
$found = function (string $pattern) {
    $out = [];
    foreach (glob(dirname(__DIR__, 3).'/'.$pattern) ?: [] as $p) {
        $out[] = [basename($p), str_replace(dirname(__DIR__, 3).'/', '', $p)];
    }

    return $out;
};
foreach (array_merge($found('*.md'), $found('Documentation/*.md')) as [$label, $path]) {
    if (in_array($label, ['AGENTS.md', 'DEPLOYMENT.md', 'README.md'], true) || str_contains($label, 'Complete Documentation')) {
        continue;
    }
    $targets[] = [$label, $path];
}

$total = 0;
foreach ($targets as [$label, $path]) {
    if (! is_file($root.'/'.$path)) {
        printf("  (absent) %s\n", $label);
        continue;
    }
    $total += $scan($label, $path);
}

printf("\n%d suite-size claim(s) found across %d documents.\n", $total, count($targets));
echo "HISTORY lines are dated or past-tense and are not wrong; PRESENT lines assert this build and must match a reading.\n";
exit(0);
