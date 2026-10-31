<?php

/**
 * Lane 58 — every suite-size or class-size claim in the live documents, bound to a reading that answers it.
 *
 * The authority document said "The suite stands at 771 tests and 5191 assertions". Measured 2026-10-20, no run
 * answers that pair: `artisan test` whole on this box answers 780 passed / 5,220 assertions, CI's suite step answers
 * 9 skipped and 771 passed / 5,015 assertions, and CI's walkthrough step answers 8 tests / 201 assertions. The 771
 * is what made the sentence survive three passes of reading it: it *is* a number the suite step prints today, while
 * the assertions beside it came from a different surface on a different day. So the claim class is the whole problem
 * — a suite total, a step total and a single class's total are three different quantities, and a checker that
 * compares them against each other will call a true class figure stale and bless a false suite figure.
 *
 * Readings come from three instruments, none of which is a guess:
 *   `--list-tests`   discovered case count, no database, no run          (binds "discovers N cases")
 *   `--junit=`       a whole-run JUnit: per-`<testsuite>` tests/assertions (binds suite totals and class totals)
 *   `--junit-e2e=`   the walkthrough step's JUnit                          (binds step totals)
 *   `--local-suite=` a captured `artisan test` summary line                (binds this box's whole-suite run)
 * A claim whose reading the checker does not have is UNBOUND and refuses the run — a parser with a hole and a clean
 * document must not look alike (lanes 41, 43, 46, 50 each paid for that lesson).
 *
 * Documents whose *file name* carries a date are audited reports of their own day (Round-3, the September UI
 * audits); their figures are reported as dated and not judged, the way lane 42 separated history from present state.
 * Rewriting another auditor's numbers is not this checker's business, and pretending to check them would be worse.
 *
 * Usage:
 *   php storage/app/scratch/lane58-suite-claims.php [--junit=f] [--junit-e2e=f] [--local-suite=f]
 *                                                   [--discovered=n] [--ci] [--report-only]
 */

$root = dirname(__DIR__, 3);
$fails = 0;
$refusals = [];

$opt = static function (string $name) use ($argv): ?string {
    $seen = null;
    foreach ($argv as $a) {
        if (preg_match('/^--'.preg_quote(substr($name, 2), '/').'=(.*)$/', $a, $m) === 1) {
            // Last one wins, so a harness can hand a default and then override it: the mutant leg that points
            // --junit at a missing file must be able to replace the fixture rather than be ignored by it.
            $seen = $m[1];
        }
    }

    return $seen;
};
$flag = static function (string $name) use ($argv): bool {
    return in_array($name, $argv, true);
};
// A path handed to the checker may be absolute or relative to the project, because the harness that plants a mutant
// runs from wherever it was started and a flag silently pointing at nothing is how a mutant reports a pass.
$resolve = static function (?string $v) use ($root): ?string {
    if ($v === null) {
        return null;
    }

    return is_file($v) ? $v : $root.'/'.$v;
};

// ------------------------------------------------------------------ the readings

/** @var array<string,array{tests:int,assertions:?int}> class-name => its own totals */
$classReadings = [];
/** @var array<int,array{label:string,tests:int,assertions:?int}> */
$runReadings = [];

$discover = $opt('--discovered');
if ($discover === null && ! $flag('--report-only')) {
    // No `2>/dev/null`: PHP's shell here is cmd.exe, which reads that as a path to create and prints "The system
    // cannot find the path specified" instead of running phpunit. Only lines starting ` - ` are counted, so any
    // noise on stderr cannot inflate the number anyway.
    $cmd = 'cd '.escapeshellarg($root).' && '.escapeshellarg(PHP_BINARY).' vendor/bin/phpunit --list-tests';
    $out = (string) shell_exec($cmd);
    // Each line reads ` - Tests\Feature\X::test_y#3` — one line per discovered case, datasets included.
    $discover = (string) preg_match_all('/^ - /m', $out);
}
if ($discover !== null && (int) $discover > 0) {
    $runReadings[] = ['label' => 'discovered cases (--list-tests)', 'tests' => (int) $discover, 'assertions' => null];
    printf("reading  %-34s %s cases\n", 'discovered', $discover);
} else {
    // A zero here means the listing never ran (or the shell refused it), and quietly dropping the reading would let
    // every claim that binds to it fall through as UNBOUND on one driver and MATCH on another.
    $refusals[] = 'the discovered case count read '.var_export($discover, true).' — --list-tests answered nothing usable';
}

$junit = $resolve($opt('--junit'));
if ($junit !== null && is_file($junit)) {
    $x = simplexml_load_file($junit);
    if ($x === false) {
        $refusals[] = "--junit={$junit} is not parseable XML";
    } else {
        // A folder-scoped junit nests config → suite → class; a whole-suite one nests config → ONE outer testsuite
        // named for the config file → Unit and Feature → classes. Reading only two levels deep found the outer total
        // and zero classes, so every class claim in the document came back UNBOUND on the one run that could answer
        // them. Walk the tree instead: a `<testsuite>` whose name is a class FQCN is a class, anything else is a run.
        $walk = static function (SimpleXMLElement $node) use (&$walk, &$classReadings, &$runReadings): void {
            foreach ($node->testsuite as $ts) {
                $name = (string) $ts['name'];
                $tests = isset($ts['tests']) ? (int) $ts['tests'] : 0;
                $assertions = isset($ts['assertions']) ? (int) $ts['assertions'] : null;
                if (str_contains($name, '\\') && str_ends_with($name, 'Test')) {
                    $classReadings[substr($name, (int) strrpos($name, '\\') + 1)] =
                        ['tests' => $tests, 'assertions' => $assertions];

                    // Do not descend: PHPUnit groups a data provider's cases into child `<testsuite>` elements named
                    // after the test, and reading those as runs handed the checker seven fake "runs" of 13–14 tests
                    // beside the three real ones.
                    continue;
                }
                $runReadings[] = ['label' => 'junit run: '.basename($name), 'tests' => $tests, 'assertions' => $assertions];
                $walk($ts);
            }
        };
        $walk($x);
        foreach ($runReadings as $r) {
            if (str_starts_with($r['label'], 'junit run')) {
                printf("reading  %-34s %s tests / %s assertions\n", $r['label'], $r['tests'], $r['assertions'] ?? 'n/a');
            }
        }
        printf("reading  %-34s %d class(es)\n", 'junit per-class', count($classReadings));
    }
} elseif ($junit !== null) {
    $refusals[] = "--junit={$junit} does not exist — a claim that needs it would be reported UNBOUND";
}

$e2e = $resolve($opt('--junit-e2e'));
if ($e2e !== null && is_file($e2e)) {
    $x = simplexml_load_file($e2e);
    if ($x !== false) {
        // Same shape as the suite's own file: the step's total is the aggregate, and its classes are the same readings.
        $collectStep = static function (SimpleXMLElement $node) use (&$collectStep, &$runReadings, &$classReadings): void {
            foreach ($node->testsuite as $ts) {
                $name = (string) $ts['name'];
                $tests = isset($ts['tests']) ? (int) $ts['tests'] : 0;
                $assertions = isset($ts['assertions']) ? (int) $ts['assertions'] : null;
                if (str_contains($name, '\\') && str_ends_with($name, 'Test')) {
                    $classReadings[substr($name, (int) strrpos($name, '\\') + 1)] =
                        ['tests' => $tests, 'assertions' => $assertions];

                    continue;
                }
                $runReadings[] = ['label' => 'e2e step: '.basename($name), 'tests' => $tests, 'assertions' => $assertions];
                $collectStep($ts);
            }
        };
        $collectStep($x);
    }
}

$localSuite = $opt('--local-suite');
if ($localSuite !== null) {
    $path = is_file($localSuite) ? $localSuite : $root.'/storage/app/scratch/'.$localSuite;
    // Pest colourises its own summary, so the log reads `Tests:` ESC[39m `   ` ESC[32;1m `780 passed`.
    $t = preg_replace('/\x1b\[[0-9;]*m/', '', (string) @file_get_contents($path));
    // Un-anchored and last-match-first: a GitHub log prefixes every line with a timestamp, and a run prints per-section
    // counts before its summary. Pest also puts skips on either side of the pass count.
    preg_match_all('/Tests:\s*(?:(\d+)\s+failed,\s*)?(?:(\d+)\s+skipped,\s*)?(\d+)\s+passed(?:,\s*(\d+)\s+skipped)?\s+\((\d+)\s+assertions\)/', $t, $all);
    if ($all[0] !== []) {
        $i = count($all[0]) - 1;
        $skipped = (int) ($all[2][$i] !== '' ? $all[2][$i] : $all[4][$i]);
        $runReadings[] = ['label' => 'local run: '.basename($path), 'tests' => (int) $all[3][$i], 'assertions' => (int) $all[5][$i]];
        printf("reading  %-34s %s passed / %s assertions%s\n", basename($path), $all[3][$i], $all[5][$i],
            $skipped > 0 ? ', '.$skipped.' skipped' : '');
    } elseif (preg_match('/OK \((\d+) tests?, (\d+) assertions\)/', $t, $m) === 1) {
        $runReadings[] = ['label' => 'local run: '.basename($path), 'tests' => (int) $m[1], 'assertions' => (int) $m[2]];
        printf("reading  %-34s %s tests / %s assertions\n", basename($path), $m[1], $m[2]);
    } else {
        $refusals[] = "--local-suite=".basename($path)." carries no summary line";
    }
}

// ------------------------------------------------------------------ claim extraction

$WORDS = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8,
    'nine' => 9, 'ten' => 10, 'eleven' => 11, 'twelve' => 12, 'thirteen' => 13, 'fourteen' => 14, 'fifteen' => 15,
    'sixteen' => 16, 'seventeen' => 17, 'eighteen' => 18, 'nineteen' => 19, 'twenty' => 20];

// The spellings were taken from the claims found by the measurement pass, not invented.
$toNum = static function (string $s) use ($WORDS): ?int {
    // `1,638 assertions` is how the markdown writes a four-digit count, and is_numeric() answers false for it.
    // Dropping a claim because of a thousands separator is exactly the silent hole this lane exists to catch, so the
    // comma goes first and mutate-lane58-suite-claims.php plants a comma-formatted figure to prove it stays.
    $s = str_replace(',', '', strtolower(trim($s)));
    if ($s !== '' && ctype_digit($s)) {
        return (int) $s;
    }

    return $WORDS[strtolower(trim($s))] ?? null;
};

$NUMNC = '(?:\d[\d,]*|'.implode('|', array_keys($WORDS)).')';
// Run-shaped spellings only. `8/8 rendered`, `24/7`, a `2/40` seat counter and `400/500/600` font weights are ratios
// and must never be read as a test count — the first loose pass called 22 of them claims, which is a checker that
// reports its own noise as a document's defect. A slash is a run when it says `passed`/`tests` beside it, or when an
// assertion figure and a suite label stand next to it; a bare `N tests` is a run only when the suite is named before
// it; `N passing tests` and `N tests passed` state a run in their own spelling and need no label.
$runRe = '/(?:(\d[\d,]*)\s*\/\s*('.$NUMNC.')\s*(passed|tests)?|('.$NUMNC.')\s+(passing\s+tests|tests\s+passed|tests|cases))/i';
$assertMention = '/('.$NUMNC.')\s+assertions/i';
$LABEL_RE = '/(suite|Pest\b|Test Suite Status|^[\s*>-]*Total\b)/i';
$CLASS_RE = '/\b([A-Za-z0-9_]+Test)\b/';

$HISTORY_RE = '/(passed at|could not|was measured|was re-measured|re-measured|used to state|measured on|measured as|Measured as|had run|at the time|back in|found what|which corrected|first run)/i';

$CI_RE = '/(\bCI\b|the runner|GitHub|suite step|walkthrough step|the job)/i';
$LOCAL_RE = '/(this box|this machine|this tree|artisan test|whole\b|SQLite)/i';
// A sentence that carries its own date has qualified its own figure. "Measured 2026-01-01: 999 tests" is a record of
// that day whatever verb it uses, and this is the rule that lets the authority document state a dated reading without
// the checker nagging about it — while "The suite stands at 771 tests and 5191 assertions", which carries none, is
// judged against today and refused.
$DATE_BODY = '(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},\s*\d{4}|\d{4}-\d{2}-\d{2}';
$DATED_RE = '/('.$DATE_BODY.')/';

/** Split a document into claim-sized units and report each pair found in them. */
// A sentence containing the word "assertions" that the patterns reject is how a parser hides a claim; --trace prints
// every such sentence so the blind spot is readable instead of silent.
$trace = $flag('--trace');
$multiRows = [];
$claimsFrom = static function (string $label, string $path, string $kind) use ($root, $runRe, $assertMention, $toNum, $LABEL_RE, $CLASS_RE, $trace, $DATE_BODY, &$multiRows): array {
    $bytes = (string) @file_get_contents($root.'/'.$path);
    if ($bytes === '') {
        return [];
    }
    $out = [];
    $lines = preg_split('/\n/', str_replace("\r\n", "\n", $bytes)) ?: [];
    foreach ($lines as $idx => $line) {
        $cells = preg_split('/\s+\|\s+/', $line) ?: [$line];
        foreach ($cells as $cellOffset => $cell) {
            // A markdown table row keeps its label in one cell and its figure in the next: `| Pest | **274/274** … |`.
            // A cell holding no digits is that label, so it travels with the cell after it.
            $unit = $cell;
            if (preg_match('/\d/', $unit) === 0 && isset($cells[$cellOffset + 1])) {
                $unit = $cell.' '.$cells[$cellOffset + 1];
            }
            // Sentences, not lines: one flat-dump row is a single 23,000-byte line carrying a dozen claims.
            foreach (preg_split('/(?<=[.;?!])\s+/', $unit) ?: [] as $sentence) {
                if ($trace && stripos($sentence, 'assertions') !== false) {
                    fwrite(STDERR, "trace {$label} :".($idx + 1).' '.substr(trim(preg_replace('/\s+/', ' ', $sentence)), 0, 110)."\n");
                }
                $hasAssertions = preg_match($assertMention, $sentence, $am) === 1;
                $assertions = $hasAssertions ? $toNum($am[1]) : null;
                // The blind spot is printed, not counted clean: a sentence carrying two totals is judged on its FIRST
                // pair, which is what a dated narrative sentence like §1.1's row does all day.
                if (preg_match_all($assertMention, $sentence, $multi) > 1) {
                    $multiRows[] = sprintf(
                        "  %s :%d  judged on \"%s\", beside %s  |  %s\n",
                        $label,
                        $idx + 1,
                        $multi[0][0],
                        implode(', ', array_slice($multi[0], 1)),
                        substr(trim(preg_replace('/\s+/', ' ', $sentence)), 0, 96)
                    );
                }
                if ($hasAssertions && $assertions === null) {
                    continue;
                }
                if (preg_match($runRe, $sentence, $tm) !== 1) {
                    if ($trace && $hasAssertions) {
                        fwrite(STDERR, "  rejected: an assertion figure naming no run\n");
                    }
                    continue;
                }
                $isSlash = ($tm[1] ?? '') !== '' || ($tm[2] ?? '') !== '';
                $tests = $isSlash ? $toNum($tm[2] ?? '') : $toNum($tm[4] ?? '');
                $word = $isSlash ? ($tm[3] ?? '') : ($tm[5] ?? '');
                if ($tests === null) {
                    if ($trace) {
                        fwrite(STDERR, "  rejected: the run mention's number did not parse\n");
                    }
                    continue;
                }
                $at = (int) strpos($sentence, $tm[0]);
                $before = substr($sentence, 0, $at);
                $unambiguous = ! $isSlash && ($word === 'passing tests' || $word === 'tests passed');
                if ($isSlash && $word === '' && ! $hasAssertions) {
                    if ($trace) {
                        fwrite(STDERR, "  rejected: a bare ratio — \"".substr(trim($sentence), 0, 80)."\"\n");
                    }
                    continue;
                }
                if (! $hasAssertions && ! $unambiguous && preg_match($LABEL_RE, $before) !== 1) {
                    // A pair — a run count AND an assertion count in one sentence — states a suite or a class whatever
                    // words sit beside it. A lone count does not: `2/40` and `24/7` are ratios, and the first loose pass
                    // read 22 of them as claims, which is a checker reporting its own noise as a document's defect.
                    if ($trace) {
                        fwrite(STDERR, "  rejected: a lone count with no assertions and no suite label — \"".substr(trim($sentence), 0, 80)."\"\n");
                    }
                    continue;
                }
                $runDated = false;
                if ($kind === 'md') {
                    // A block that declares when it was RUN is a record of that run. "Last Verified" is not the same
                    // thing: it dates the reading of the document, not the measurement — which is why the storyboard's
                    // status line is judged while the table under its "Last Run: September 24, 2026" is not. The scan
                    // walks back to the section heading, so a run date governs the table it introduces and no further.
                    $RUN_RE = '/(Last Run|Measured|Run on|Suite run|as of|Results?)[^\n]{0,30}('.$DATE_BODY.')/i';
                    for ($b = $idx; $b >= max(0, $idx - 60); $b--) {
                        if ($b === $idx && preg_match('/('.$DATE_BODY.')/', $lines[$b]) === 1) {
                            $runDated = true;
                            break;
                        }
                        if ($b !== $idx && preg_match($RUN_RE, $lines[$b]) === 1) {
                            $runDated = true;
                            break;
                        }
                        if (preg_match('/^#{1,6}\s/', $lines[$b]) === 1) {
                            break;
                        }
                    }
                }
                $out[] = ['doc' => $label, 'path' => $path, 'kind' => $kind, 'line' => $idx + 1,
                    'sentence' => trim(preg_replace('/\s+/', ' ', $sentence)), 'tests' => $tests,
                    'assertions' => $assertions, 'runDated' => $runDated];
            }
        }
    }

    return $out;
};

$live = [
    ['docx', 'storage/app/scratch/docx-flat.txt', 'docx'],
    ['AGENTS.md', 'AGENTS.md', 'md'],
    ['DEPLOYMENT.md', 'DEPLOYMENT.md', 'md'],
    ['README.md', 'README.md', 'md'],
    ['System_Storyboard_Walkthrough.md', 'Documentation/System_Storyboard_Walkthrough.md', 'md'],
    ['Per-Office-UAT-Walkthrough.md', 'Documentation/Per-Office-UAT-Walkthrough.md', 'md'],
    ['Demo-Day-Guide.md', 'Documentation/Demo-Day-Guide.md', 'md'],
    ['Offline-Presentation-Guide.md', 'Documentation/Offline-Presentation-Guide.md', 'md'],
    ['Use-Case-Diagram-Guide.md', 'Documentation/Use-Case-Diagram-Guide.md', 'md'],
    ['System-Design-Quality-Review.md', 'Documentation/System-Design-Quality-Review.md', 'md'],
    ['SEAIT_Official_Information.md', 'Documentation/SEAIT_Official_Information.md', 'md'],
];
$flat = $opt('--flat');
if ($flat !== null) {
    $live[0] = ['docx', $flat, 'docx'];
}
// `--live=docx=copy.txt,AGENTS.md=copy.md` replaces the document set. The mutant harness needs it to judge a COPY
// rather than the real tree, and an entry that parses to nothing must refuse: lane 46's checker judged 27 bindings
// and cleared the tree because it looked at the wrong construct, and a scope flag that can be empty is the same trap.
$liveOpt = $opt('--live');
if ($liveOpt !== null) {
    $live = [];
    foreach (array_filter(explode(',', $liveOpt)) as $pair) {
        if (strpos($pair, '=') === false) {
            $refusals[] = "--live entry “{$pair}” is not label=path";

            continue;
        }
        [$l, $p] = explode('=', $pair, 2);
        $live[] = [$l, $p, str_starts_with($l, 'docx') ? 'docx' : 'md'];
    }
    if ($live === []) {
        $refusals[] = '--live named no document — an empty scan is not a clean one';
    }
}

$dated = [];
foreach ([glob($root.'/*.md'), glob($root.'/Documentation/*.md')] as $set) {
    foreach ($set ?: [] as $p) {
        if (preg_match('/(-\d{4}-\d{2}-\d{2}\.md|SEAIT-EMS-Round3-Current-State\.md)$/', $p) === 1) {
            $dated[] = [basename($p), str_replace($root.'/', '', $p), 'md'];
        }
    }
}

$claims = [];
foreach ($live as [$label, $path, $kind]) {
    $claims = array_merge($claims, $claimsFrom($label, $path, $kind));
}
$datedClaims = [];
foreach ($dated as [$label, $path, $kind]) {
    $datedClaims = array_merge($datedClaims, $claimsFrom($label, $path, $kind));
}

// A second claim can sit in the same sentence window after the split; keep every one, drop exact repeats.
$dedupe = static function (array $list) {
    $seen = [];

    return array_values(array_filter($list, static function (array $c) use (&$seen): bool {
        $k = $c['doc'].'|'.$c['line'].'|'.$c['tests'].'|'.$c['assertions'];
        if (isset($seen[$k])) {
            return false;
        }
        $seen[$k] = true;

        return true;
    }));
};
$claims = $dedupe($claims);
// The table-cell join means a figure can be read once with its label cell and once without; the count of claims a
// reader trusts has to be the count of distinct claims, not the count of ways the parser happened to reach one.
$datedClaims = $dedupe($datedClaims);

// ------------------------------------------------------------------ classify and bind

echo "\n";
$stale = 0;
$unbound = 0;
$judged = 0;
$history = 0;
$ciSkips = 0;

foreach ($claims as $c) {
    $s = $c['sentence'];
    $class = null;
    $runBy = null;

    // Subject: which quantity does the sentence describe?
    $subject = 'SUITE';
    if (preg_match('/\b([A-Za-z0-9_]+Test)\b/', $s, $cn) === 1) {
        $subject = 'CLASS';
        $class = $cn[1];
    } elseif (preg_match('#(tests/Feature/\w+|walkthrough legs|the end-to-end folder|E2E)#', $s) === 1) {
        $subject = 'STEP';
    }
    if ($subject === 'SUITE' && preg_match('/(suite|Pest\b|Test Suite|Total|passing\s+tests|tests\s+passed|tests\s+passing)/i', $s) !== 1) {
        $subject = 'UNBOUND-SUBJECT';
    }

    // Surface: whose run is being quoted?
    $surface = 'unknown';
    if (preg_match($CI_RE, $s) === 1) {
        $surface = 'ci';
    }
    if (preg_match($LOCAL_RE, $s) === 1) {
        $surface = $surface === 'ci' ? 'both' : 'local';
    }

    // Tense: a past-tense or explicitly-measured sentence is a record of its day; anything else asserts now.
    $tense = 'PRESENT';
    if (preg_match($HISTORY_RE, $s) === 1) {
        $tense = 'HISTORY';
        $runBy = 'the sentence speaks of a run it names as past';
    } elseif (preg_match($DATED_RE, $s) === 1) {
        $tense = 'HISTORY';
        $runBy = 'the sentence carries the date its figure was read on';
    } elseif (($c['runDated'] ?? false) === true) {
        $tense = 'HISTORY';
        $runBy = 'the block above it names the day it was run';
    }

    printf("%-9s %-32s :%-5d %-24s %-7s %-7s %s\n", $subject, $c['doc'], $c['line'],
        $subject === 'CLASS' ? ($class ?? '?') : '—', $surface, $tense, substr($s, 0, 118));

    if ($tense === 'HISTORY') {
        $history++;
        echo str_repeat(' ', 62).'        → not judged: '.($runBy ?? 'the sentence speaks of a run it names as past')."\n";
        $runBy = null;

        continue;
    }

    if ($flag('--ci') && $surface === 'local' && $subject !== 'CLASS') {
        // A class's own total comes from the junit either way, so a sentence that happens to mention SQLite is not a
        // claim about the box. A suite *total* is: its assertions move with the driver and the dataset under the run.
        $ciSkips++;
        echo str_repeat(' ', 62)."        → SKIPPED in CI mode: this box's run is not measurable on the runner\n";

        continue;
    }

    $judged++;
    $candidates = [];
    if ($subject === 'CLASS') {
        $r = $classReadings[$class ?? ''] ?? null;
        if ($r !== null) {
            $candidates[] = ['label' => 'class '.$class, 'tests' => $r['tests'], 'assertions' => $r['assertions']];
        }
    } elseif ($subject === 'STEP') {
        foreach ($runReadings as $r) {
            if (str_contains($r['label'], 'e2e')) {
                $candidates[] = $r;
            }
        }
    } elseif ($subject === 'SUITE') {
        foreach ($runReadings as $r) {
            if ($surface === 'ci' && ! str_contains($r['label'], 'junit run') && ! str_contains($r['label'], 'discovered')) {
                continue;
            }
            if ($surface === 'local' && ! str_contains($r['label'], 'local run') && ! str_contains($r['label'], 'discovered')) {
                continue;
            }
            $candidates[] = $r;
        }
    }

    if ($candidates === [] || $subject === 'UNBOUND-SUBJECT') {
        $unbound++;
        echo str_repeat(' ', 62)."        → UNBOUND: no reading answers a ".$subject.' claim on surface '.$surface
            ." (hold it against the documents and it looks checked)\n";

        continue;
    }

    $hit = null;
    foreach ($candidates as $r) {
        // A count-only claim is judged on its count; it never claimed an assertion figure to disagree with.
        if ($r['tests'] === $c['tests'] && ($c['assertions'] === null || $r['assertions'] === $c['assertions'])) {
            $hit = $r;
            break;
        }
    }
    if ($hit !== null) {
        printf("  %-60s MATCHES %s (%s / %s)\n", '', $hit['label'], $hit['tests'], $hit['assertions'] ?? 'no assertions');
    } else {
        $stale++;
        $nearest = array_map(static fn (array $r): string => $r['label'].' '.$r['tests'].'/'.($r['assertions'] ?? 'n/a'), $candidates);
        printf("  %-60s STALE — claimed %s / %s; the readings answer %s\n", '', $c['tests'], $c['assertions'] ?? 'no figure', implode(', ', $nearest));
    }
}

// ------------------------------------------------------------------ dated documents, reported not judged

if ($datedClaims !== []) {
    echo "\ndated reports (figures are records of their own day, deliberately not judged): ";
    $byDoc = [];
    foreach ($datedClaims as $c) {
        $byDoc[$c['doc']][] = $c['tests'].'/'.($c['assertions'] ?? '—').' @:'.$c['line'];
    }
    foreach ($byDoc as $doc => $list) {
        printf("\n  %-46s %s", $doc, implode(', ', $list));
    }
    echo "\n";
}

// ------------------------------------------------------------------ verdict

printf(
    "\n%d claim(s) read from %d live document(s): %d judged, %d history, %d dated-report figures not judged.\n",
    count($claims) + count($datedClaims), count($live), $judged, $history, count($datedClaims)
);
// The tally is read off the rows themselves: a document that both walks visit (the matrix is live AND matches the
// dated glob) would otherwise be counted twice, which is the two-definitions-of-one-word defect in my own footer.
$multiRows = array_values(array_unique($multiRows));
if ($multiRows !== []) {
    printf("Blind spot, printed rather than passed: %d sentence(s) state more than one total and were judged on their first figure.\n", count($multiRows));
    foreach ($multiRows as $row) {
        fwrite(STDOUT, $row);
    }
}

if ($claims === []) {
    $refusals[] = 'no claim was read from any live document — a checker that matched nothing is not a clean document';
}
foreach ($refusals as $r) {
    echo 'REFUSED: '.$r."\n";
}
if ($unbound > 0) {
    echo $unbound." claim(s) could not be bound.\n";
}

if ($refusals !== []) {
    exit(2);
}
if ($stale > 0 || $unbound > 0) {
    echo "VERDICT: refuse — ".$stale.' stale and '.$unbound.' unbound present-tense claim(s).'
        ."  (history: ".$history.', skipped on CI by surface: '.$ciSkips.")\n";
    exit(1);
}
echo "VERDICT: every present-tense claim binds to a reading it names.\n";
exit(0);
