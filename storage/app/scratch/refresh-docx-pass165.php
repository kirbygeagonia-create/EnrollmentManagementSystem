<?php

/**
 * Pass 165 — §1.1's revision row stops stating the suite's size as a present fact.
 *
 * The row said "The suite stands at 771 tests and 5191 assertions". Re-measured on 2026-10-20 on the four surfaces
 * that report the suite, that pair matches none of them: this box's whole `artisan test` run reads 780 passed with
 * 5,220 assertions, CI's SQLite job reads 9 skipped and 771 passed with 5,013, CI's MySQL job reads the same 9 and
 * 771 with 5,015, and CI's walkthrough step — the legs the suite steps skip — reads 8 tests with 201. The 771 is why
 * the sentence survived three passes of being read: it *is* what both CI jobs print today, beside an assertion count
 * that came from one box on one earlier day (lane 56's log, `lane56-suite.txt:943`). Two CI jobs agreeing on the test
 * count and disagreeing on the assertions is the finding: an assertion total is a property of a run, not of the code.
 *
 * Every figure in the new sentence is interpolated from the logs, not typed. The pass reads them, refuses if any is
 * missing, refuses if the two CI jobs do not agree on the test count it is about to describe, and refuses unless the
 * checker names this exact sentence as STALE before the edit — a pass that rewrites a claim it cannot show was wrong
 * is decoration.
 *
 * The row's own label moved too: "2026-10-16–18 (this issue)" carried lane 55's, 56's and 57's work in after it, and
 * 56 and 57 are committed on 2026-10-20. A date range that stops short of its own content is the same defect in a
 * different column.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    if (! $ok) {
        $fails++;
    }
};

// ------------------------------------------------------------------ the four readings, parsed not remembered

$strip = static fn (string $s): string => preg_replace('/\x1b\[[0-9;]*m/', '', $s) ?? $s;
$runLine = static function (string $path, string $label) use ($strip, $guard): ?array {
    $t = $strip((string) @file_get_contents($path));
    // Pest prints skips on either side of the pass count — `9 skipped, 771 passed (5015 assertions)` on CI,
    // `780 passed (5220 assertions)` here — so both orders are read and neither is guessed. The line is not anchored,
    // because a GitHub log prefixes every line with its timestamp, and the LAST such line is the summary: a run prints
    // per-section counts before it.
    preg_match_all('/Tests:\s*(?:(\d+)\s+failed,\s*)?(?:(\d+)\s+skipped,\s*)?(\d+)\s+passed(?:,\s*(\d+)\s+skipped)?\s+\((\d+)\s+assertions\)/', $t, $all);
    if ($all[0] !== []) {
        $i = count($all[0]) - 1;

        return ['failed' => (int) $all[1][$i], 'skipped' => (int) ($all[2][$i] !== '' ? $all[2][$i] : $all[4][$i]),
            'passed' => (int) $all[3][$i], 'assertions' => (int) $all[5][$i], 'label' => $label];
    }
    $guard(false, "{$label} prints a Tests: summary line (".basename($path).' read nothing)');

    return null;
};

$local = $runLine($root.'/storage/app/scratch/lane58-suite.txt', 'this box, artisan test whole');
$ciSqlite = $runLine($root.'/storage/app/scratch/lane57-ci-sqlitejob.log', 'CI SQLite job');
$ciMysql = $runLine($root.'/storage/app/scratch/lane57-ci-mysql.log', 'CI MySQL job');

$exitOk = preg_match('/SUITE_EXIT=(\d+)/', $strip((string) @file_get_contents($root.'/storage/app/scratch/lane58-suite.txt')), $e) === 1
    && (int) $e[1] === 0;
$guard($exitOk, 'the run on this box exited 0 (read '.($e[1] ?? 'nothing').')');
$guard($local !== null && $local['failed'] === 0 && $local['skipped'] === 0,
    'and reached a green verdict with no skip: '.($local['passed'] ?? '?').' passed');

$e2e = null;
if (preg_match('/OK \((\d+) tests, (\d+) assertions\)/', $strip((string) @file_get_contents($root.'/storage/app/scratch/lane57-ci-mysql.log')), $x) === 1) {
    $e2e = ['tests' => (int) $x[1], 'assertions' => (int) $x[2]];
    printf("     walkthrough step: %s tests / %s assertions\n", $e2e['tests'], $e2e['assertions']);
}
$guard($e2e !== null, 'CI’s walkthrough step reports its own total in the same log');

$discovered = null;
foreach ($argv as $a) {
    if (preg_match('/^--discovered=(\d+)$/', $a) === 1) {
        $discovered = (int) $a;
    }
}
if ($discovered === null) {
    // `2>/dev/null` is a Unix redirect and cmd.exe reads it as a path to create, so it prints "The system cannot find
    // the path specified" instead of running the command — and the count comes back 0. Only ` - ` lines are counted.
    $out = (string) shell_exec('cd '.escapeshellarg($root).' && '.escapeshellarg(PHP_BINARY).' vendor/bin/phpunit --list-tests');
    $discovered = (int) preg_match_all('/^ - /m', $out);
}
printf("     discovered cases: %d\n", $discovered);
$guard($discovered > 0, '--list-tests answered a case count rather than nothing');

// The sentence claims the two CI jobs agree on tests and disagree on assertions; it may not be written unless they do.
if ($ciSqlite !== null && $ciMysql !== null) {
    $guard($ciSqlite['passed'] === $ciMysql['passed'] && $ciSqlite['skipped'] === $ciMysql['skipped'],
        'the two CI jobs agree on the test count ('.$ciMysql['passed'].' passed, '.$ciMysql['skipped'].' skipped)');
    $guard($ciSqlite['assertions'] !== $ciMysql['assertions'],
        'and disagree on assertions ('.$ciSqlite['assertions'].' vs '.$ciMysql['assertions'].') — which is the finding');
    $guard($ciMysql['passed'] + $ciMysql['skipped'] === $discovered,
        'CI’s passed plus skipped is the discovered case count ('.$discovered.'), so the readings describe one suite');
    $guard($local !== null && $local['passed'] === $discovered,
        'and this box, which runs the skipped legs too, passes exactly that many');
}

// ------------------------------------------------------------------ the checker must name the defect first

$pre = (string) shell_exec('cd '.escapeshellarg($root).' && '.escapeshellarg(PHP_BINARY).' storage/app/scratch/lane58-suite-claims.php'
    .' --discovered='.$discovered.' --report-only');
$guard(str_contains($pre, 'The suite stands at 771 tests and 5191 assertions'), 'the checker read the sentence it is about to replace');
$guard(preg_match('/The suite stands at 771 tests and 5191 assertions[^\n]*\n[^\n]*STALE/', $pre) === 1,
    'and reports that sentence as STALE — the pass proves the defect before it claims to fix one');

// ------------------------------------------------------------------ the document

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$partCount = $zip->numFiles;
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
$new = $xml;

$oldSentence = 'The suite stands at 771 tests and 5191 assertions, and every check the project gates a merge on is green: Pest whole, PHPStan at zero errors, Pint in --test mode, ESLint at zero warnings and the Vite build.';
$newSentence = sprintf(
    'The suite was re-measured on 2026-10-20 on the four surfaces that report it, and they do not give one number: '
    .'`php artisan test` whole on this box reads %d passed with %s assertions; CI’s SQLite job reads %d skipped and %d passed '
    .'with %s assertions; CI’s MySQL job reads the same %d skipped and %d passed with %s assertions; and CI’s walkthrough step, '
    .'which runs the folder whose legs the suite steps skip, reads %d tests with %s assertions. `phpunit --list-tests` discovers %d cases, '
    .'and %d plus %d accounts for the same %d, so these are four readings of one suite rather than four suites. '
    .'The figure this row used to state, 771 tests and 5191 assertions, matched none of them when it was re-measured — '
    .'and the 771 is why three passes of reading this row let it stand: that number is what both CI jobs print today, beside an '
    .'assertion count that belonged to one box on one earlier day. Two jobs agreeing on the test count and disagreeing on the '
    .'assertions is the point — an assertion total is a property of a run, not of the code — so a claim about the suite now names '
    .'the surfaces it was read from and the day it was read, and storage/app/scratch/lane58-suite-claims.php binds every such claim '
    .'in these documents to a reading it can name, refusing rather than passing a figure it cannot bind. '
    .'Every check the project gates a merge on is green: Pest whole, PHPStan at zero errors, Pint in --test mode, ESLint at zero warnings and the Vite build.',
    $local['passed'] ?? 0, number_format($local['assertions'] ?? 0),
    $ciSqlite['skipped'] ?? 0, $ciSqlite['passed'] ?? 0, number_format($ciSqlite['assertions'] ?? 0),
    $ciMysql['skipped'] ?? 0, $ciMysql['passed'] ?? 0, number_format($ciMysql['assertions'] ?? 0),
    $e2e['tests'] ?? 0, number_format($e2e['assertions'] ?? 0),
    $discovered, $ciMysql['passed'] ?? 0, $ciMysql['skipped'] ?? 0, $discovered
);

$oldLabel = '2026-10-16–18 (this issue)';
$newLabel = '2026-10-16–20 (this issue)';

$assertPlain = static function (string $text, string $what) use (&$fails, $guard): void {
    $bad = [];
    foreach (['&', '<', '>'] as $c) {
        if (str_contains($text, $c)) {
            $bad[] = $c;
        }
    }
    $guard($bad === [], $what.' carries no XML metacharacter'.($bad === [] ? '' : ' (found '.implode(' ', $bad).')'));
};
$assertPlain($newSentence, 'the replacement sentence');
$assertPlain($newLabel, 'the replacement row label');

$guard(substr_count($new, $oldSentence) === 1, 'the stale sentence is present exactly once before the edit');
$new = str_replace($oldSentence, $newSentence, $new, $c1);
$guard($c1 === 1, 'the stale sentence was replaced once (replaced '.$c1.')');
$guard(substr_count($new, $oldSentence) === 0, 'and no copy of it survives except the one the new sentence quotes');

$guard(substr_count($new, $oldLabel) === 1, 'the row label to move is present exactly once');
$new = str_replace($oldLabel, $newLabel, $new, $c2);
$guard($c2 === 1, 'the row label was moved to –20 once (replaced '.$c2.')');

$guard(str_contains($new, '771 tests and 5191 assertions'), 'the old figure is still quotable, inside the sentence that says it matched nothing');
$guard(strlen($new) > strlen($xml), 'the document grew by '.(strlen($new) - strlen($xml)).' bytes');

if ($fails > 0) {
    printf("\n%d guard(s) failed — NOTHING WRITTEN.\n", $fails);
    exit(1);
}
if (! $apply) {
    echo "\nThe sentence this pass would write:\n\n".$newSentence."\n\nDRY RUN. Re-run with --apply.\n";
    exit(0);
}

$backup = $root.'/storage/app/scratch/ems-docx-pre-pass165.docx';
copy($src, $backup);
$out = $root.'/Documentation/.ems-docx-pass165.tmp';
$write = new ZipArchive();
if ($write->open($out, ZipArchive::CREATE) !== true) {
    echo "REFUSED: cannot build the replacement archive.\n";
    exit(1);
}
$read = new ZipArchive();
$read->open($src);
for ($i = 0; $i < $read->numFiles; $i++) {
    $name = $read->getNameIndex($i);
    $write->addFromString($name, $name === 'word/document.xml' ? $new : $read->getFromIndex($i));
}
$write->close();
$read->close();
if (! rename($out, $src)) {
    echo "REFUSED: could not move the new archive; backup at {$backup}.\n";
    exit(1);
}
$check = new ZipArchive();
$check->open($src);
$guard($check->numFiles === $partCount, 'the archive still holds '.$partCount.' parts');
$round = (string) $check->getFromName('word/document.xml');
$check->close();
$guard(hash('sha256', $round) === hash('sha256', $new), 'word/document.xml on disk is the bytes this pass built');
if ($fails > 0) {
    printf("\n%d guard(s) failed after writing; backup at %s\n", $fails, $backup);
    exit(1);
}

printf("\nPass 165 written; backup at %s\n", $backup);
exit(0);
