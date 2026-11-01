<?php

/**
 * Lane 144 — wait for the delivered suite run to finish, then execute only the checks that READ its artefacts.
 *
 * The point of this file is that a long suite leg outlasts an agent's turns, and polling it by hand spends them:
 * each hand check is a turn for a verdict that is not ready yet. So the wait lives here, in a script written
 * completely before it is launched (a wrapper edited while an instance of it still runs is a wrapper that resumes
 * reading inside its own comment — reported in this repository's own gates), and what it runs afterwards is
 * restricted to readers. Anything that mutates the tree or the documents is left for the operator, in the
 * foreground, with the readings in hand.
 *
 * Footer contract: the run prints `ALL WAITING CHECKS DONE` on the last line. A log that ends without it is a dead
 * instrument, never a verdict.
 *
 * Usage: php storage/app/scratch/lane144-after-suite.php
 */
$root = dirname(__DIR__, 3);
chdir($root);

$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$suiteLog = 'storage/app/scratch/lane141-suite.txt';
$junit = 'storage/app/scratch/lane141-junit.xml';
$readings = 'storage/app/scratch/lane59-readings.json';

$deadline = time() + 3600;
$exitLine = null;
while (time() < $deadline) {
    if (! file_exists($suiteLog)) {
        echo 'waiting for the suite log to appear…'.chr(10);
        sleep(20);

        continue;
    }
    $text = (string) file_get_contents($suiteLog);
    if (preg_match('/^SUITE_EXIT=(\d+)$/m', $text, $m) === 1) {
        $exitLine = (int) $m[1];

        break;
    }
    $n = preg_match_all('/[✓⨯]/u', $text);
    echo 'suite still running: '.$n.' case(s) printed'.chr(10);
    sleep(20);
}

if ($exitLine === null) {
    fwrite(STDERR, "REFUSED: the suite log never reported SUITE_EXIT within the hour. This is not a verdict — read "
        ."the log and the process list before believing anything below.\n");

    exit(1);
}

printf("suite finished: SUITE_EXIT=%d%s", $exitLine, chr(10));
if (preg_match('/^(\s*Tests:.*|.*Tests:\s*\d+.*)$/mu', (string) file_get_contents($suiteLog), $t) === 1) {
    echo '  tally line: '.trim($t[1]).chr(10);
}
if ($exitLine !== 0) {
    fwrite(STDERR, "REFUSED: the suite exited {$exitLine}. Nothing below is run until that is read.\n");

    exit(1);
}

if (! file_exists($junit)) {
    fwrite(STDERR, "REFUSED: no junit at {$junit}, so the class figures and the suite claims cannot be read from "
        ."the run that produced them.\n");

    exit(1);
}

$jobs = [
    'discovery count' => $php.' artisan test --list-tests > storage/app/scratch/lane144-listtests.txt 2>&1',
    'lane58 suite claims' => $php.' storage/app/scratch/lane58-suite-claims.php --junit='.$junit
        .' --local-suite='.$suiteLog,
    'lane59 collect readings' => $php.' storage/app/scratch/lane59-collect-readings.php',
    'lane59 instrument counts' => $php.' storage/app/scratch/lane59-instrument-counts.php --readings='.$readings,
    'lane140 harness' => $php.' storage/app/scratch/mutate-lane140-wired-counts.php',
    'lane142 harness (re-run alone)' => $php.' storage/app/scratch/mutate-lane142-console-states.php',
    'docx citation sweep' => $php.' storage/app/scratch/sweep-docx-citations.php',
    'repo citations' => $php.' storage/app/scratch/repo-file-line-citations.php',
];

$bad = 0;
foreach ($jobs as $label => $cmd) {
    echo chr(10).'### '.$label.chr(10);
    $out = [];
    $code = 0;
    exec($cmd.' 2>&1', $out, $code);
    $keep = [];
    foreach ($out as $line) {
        $s = trim($line);
        if ($s === '') {
            continue;
        }
        $keep[] = $s;
    }
    $tail = array_slice($keep, -6);
    foreach ($tail as $line) {
        echo '  '.substr($line, 0, 160).chr(10);
    }
    if ($code !== 0) {
        $bad++;
        echo '  -> exit '.$code.chr(10);
    }
}

// The discovery-vs-run comparison is the check that no inserted method stole a #[Test] attribute. It is printed, not
// asserted, because the two numbers belong to different invocations and only the operator can say which run is the
// delivered one. Counting LINES was wrong and said so immediately: `artisan test --list-tests` opens with an INFO
// banner and a blank line, so a newline count reported 1,179 against a run that answered 1,176 — three phantom
// methods. A test is a line that names a test class.
if (file_exists('storage/app/scratch/lane144-listtests.txt')) {
    // No literal backslash appears in this pattern, and that is deliberate. An earlier draft asked for
    // `Tests\\Unit|Tests\\Feature`; the backslashes arrive collapsed on this box, PCRE then reads `\U` — which it
    // does not support — and the call returns false. Guarded with `@`, that false is indistinguishable from a
    // genuine zero, so the attribute-theft check would have reported "0 tests discovered" about a healthy tree. A
    // listed test line is exactly one that begins `- Tests`, and that count is cross-checked against the run's own
    // tally below rather than asserted from a namespace.
    $disc = preg_match_all('/^\s*-\s+Tests/m', (string) file_get_contents('storage/app/scratch/lane144-listtests.txt'));
    if ($disc === false) {
        fwrite(STDERR, "REFUSED: the discovery count would not compile — that is a broken reader, not a tree with "
            ."no tests.\n");

        exit(1);
    }
    echo chr(10).'discovery: '.$disc.' test(s) listed (compare with the run\'s own Tests: line)'.chr(10);
}

printf(chr(10).'%s: %d job(s), %d non-zero%s', $bad === 0 ? 'ALL WAITING CHECKS DONE' : 'CHECKS REPORTED PROBLEMS',
    count($jobs), $bad, chr(10));

exit($bad === 0 ? 0 : 1);
