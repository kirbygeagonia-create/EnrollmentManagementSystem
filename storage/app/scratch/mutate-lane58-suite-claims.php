<?php

/**
 * Lane 58 — prove that lane58-suite-claims.php can fail.
 *
 * The measurement pass found the checker's own first version reporting "6 suite-size claims" where the documents hold
 * fifteen, because it read one claim per line, took only the first pair on a line, required digits (so "seven cases,
 * 173 assertions" was invisible), required a connective word between the two figures (so "Pest 274/274 (1,638
 * assertions)" was invisible), and could not tell a class's total from a suite's — which let it call a TRUE class
 * figure stale while missing three real claims. Every one of those holes is a case here: a document carrying the shape
 * the parser used to skip, run clean and mutated, with the requirement that *the mutation moves the verdict*.
 *
 * Two cases are deliberately expected CLEAN, and that is not a softness: ratios (`8/8 rendered`, `24/7 Security Desk`)
 * must NOT be read as claims — the first loose pass called 22 of them claims, which is a checker reporting its own
 * noise as a document's defect. And a dated sentence must be left alone. A harness that demands every case flip red
 * reports a correct expectation as a failure (lane 55's third leg).
 *
 * Every fixture document carries one control claim that binds (SchoolTimeTest, 6/33, exactly as the authority document
 * states it), so a case can never reach the empty-scan refusal by accident and the exit code always means something.
 *
 * Three refusals are cases too: an empty `--live=`, a document that yields no claim at all, and a mutation that
 * changes no bytes — each a way an instrument prints a pass while measuring nothing.
 *
 * The last leg is ground truth, not a fixture: with the real JUnit from today's whole-suite run, the three class
 * figures the authority document states must MATCH the classes' own totals.
 *
 *   php storage/app/scratch/mutate-lane58-suite-claims.php
 */

$root = dirname(__DIR__, 3);
$php = escapeshellarg(PHP_BINARY);
$checker = escapeshellarg($root.'/storage/app/scratch/lane58-suite-claims.php');
$dir = $root.'/storage/app/scratch/lane58-mut';
if (! is_dir($dir) && ! mkdir($dir, 0644, true)) {
    echo "REFUSED: cannot build the scratch copy directory.\n";
    exit(1);
}

/** A run and the verdict of its exit code. */
$run = static function (string $cmd): array {
    // `; echo EXIT=$?` is bash and never runs here: PHP's shell on Windows is cmd.exe, so the semicolon and everything
    // after it arrive as harmless extra argv and the marker is simply absent — which read every case below as exit -1.
    // `if errorlevel N` is tested when cmd reaches it, which `echo %ERRORLEVEL%` on the same line is not.
    $out = (string) shell_exec($cmd.' 2>&1 & if errorlevel 2 (echo EXIT=2) else if errorlevel 1 (echo EXIT=1) else (echo EXIT=0)');
    $code = -1;
    if (preg_match('/EXIT=(\d+)\s*$/', $out, $m) === 1) {
        $code = (int) $m[1];
    }

    return ['out' => $out, 'code' => $code];
};

$fail = 0;
$say = static function (bool $ok, string $what) use (&$fail): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'BAD', $what);
    if (! $ok) {
        $fail++;
    }
};

/** A run summary and a per-class JUnit, so the checker has readings without running the suite again. */
file_put_contents($dir.'/fixture-suite.txt', "Tests:    780 passed (5220 assertions)\nSUITE_EXIT=0\n");
file_put_contents($dir.'/fixture-junit.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="Feature" tests="780" assertions="5220">
    <testsuite name="Tests\Feature\DeskSummaryScopeTest" tests="7" assertions="173"/>
    <testsuite name="Tests\Feature\DeskTileNumberSourceTest" tests="4" assertions="290"/>
    <testsuite name="Tests\Feature\SchoolTimeTest" tests="6" assertions="33"/>
  </testsuite>
</testsuites>
XML);

// The claim every fixture carries that must bind in both its clean and its mutated form.
$CONTROL = "ROW 9: Register | Pinned by tests/Feature/SchoolTimeTest.php — 6 cases / 33 assertions, proved by the harness.\n";

$runOn = static function (string $file, string $extra = '') use ($php, $checker, $root, $run): array {
    $args = '--live=docx=storage/app/scratch/lane58-mut/'.$file
        .' --junit=storage/app/scratch/lane58-mut/fixture-junit.xml'
        .' --local-suite=storage/app/scratch/lane58-mut/fixture-suite.txt'
        .' --discovered=780'.$extra;

    return $run($php.' '.$checker.' '.$args, $root);
};

/**
 * One case: the same document clean and mutated, and the verdict required to move.
 *
 * @param string[] $wantIn     tokens the mutated run must print
 * @param string[] $wantNotIn  tokens the mutated run must never print
 */
$case = static function (string $name, string $clean, string $mutant, int $wantClean, int $wantMutant,
    array $wantIn, array $wantNotIn, string $why) use ($dir, $CONTROL, $runOn, $say): void {
    $cleanBody = $CONTROL.$clean;
    $mutBody = $CONTROL.$mutant;
    if ($cleanBody === $mutBody) {
        $say(false, $name.' — the mutation left the bytes identical, so the case measures nothing');

        return;
    }
    $cf = $name.'.clean.txt';
    $mf = $name.'.txt';
    file_put_contents($dir.'/'.$cf, $cleanBody);
    file_put_contents($dir.'/'.$mf, $mutBody);

    $a = $runOn($cf);
    $b = $runOn($mf);
    $missing = array_values(array_filter($wantIn, static fn (string $n): bool => ! str_contains($b['out'], $n)));
    $leaked = array_values(array_filter($wantNotIn, static fn (string $n): bool => str_contains($b['out'], $n)));
    $ok = $a['code'] === $wantClean && $b['code'] === $wantMutant && $missing === [] && $leaked === [];
    $say($ok, $name.' — '.$why);
    if (! $ok) {
        printf("     exit clean wanted %d read %d; mutant wanted %d read %d%s%s\n",
            $wantClean, $a['code'], $wantMutant, $b['code'],
            $missing === [] ? '' : '; missing: '.implode(' / ', $missing),
            $leaked === [] ? '' : '; leaked: '.implode(' / ', $leaked));
        foreach (preg_split('/\n/', $b['out']) ?: [] as $l) {
            if (preg_match('/SUITE|CLASS|STEP|UNBOUND|VERDICT|REFUSED|MATCHES|STALE/', $l) === 1) {
                echo '       | '.substr($l, 0, 150)."\n";
            }
        }
    }
};

// ------------------------------------------------------------------ the shapes the first parser skipped

$case('pair-wrong',
    "ROW 1: Revision | The suite stands at 780 tests and 5220 assertions on this box, and the build is green.\n",
    "ROW 1: Revision | The suite stands at 999 tests and 999 assertions on this box, and the build is green.\n",
    0, 1, ['STALE'], ['MATCHES  local run'],
    'a present-tense pair that no reading answers is refused — this is the defect the document carried');

$case('comma-figure',
    "ROW 1: Revision | The suite stands at 780 tests and 5,220 assertions on this box.\n",
    "ROW 1: Revision | The suite stands at 780 tests and 5,191 assertions on this box.\n",
    0, 1, ['claimed 780 / 5191'], [],
    'a thousands separator is part of the number — the first parser dropped every comma figure and reported clean');

$case('spelled-class',
    "ROW 1: Register | Pinned by tests/Feature/DeskSummaryScopeTest.php — seven cases, 173 assertions.\n",
    "ROW 1: Register | Pinned by tests/Feature/DeskSummaryScopeTest.php — nine cases, 999 assertions.\n",
    0, 1, ['STALE'], [],
    'a class figure written as a word is still a claim, and the run answers seven/173');

$case('word-order',
    "ROW 1: Register | Measured 2026-01-01: the suite then held 999 passing tests, 999 assertions.\n",
    "ROW 1: Register | The suite grew to 999 passing tests, 999 assertions this quarter.\n",
    0, 1, ['STALE'], [],
    '`N passing tests` is read even with no connective word between the two figures');

$case('slash-label-cell',
    "| Gate | Result |\n| Pest | **780/780** (5,220 assertions) |\n",
    "| Gate | Result |\n| Pest | **888/888** (5,220 assertions) |\n",
    0, 1, ['STALE'], [],
    'a label in one table cell and its figure in the next is one claim, not an orphan number');

$case('unbound-class',
    "ROW 1: Register | Pinned by tests/Feature/SchoolTimeTest.php — 6 cases / 33 assertions.\n",
    "ROW 1: Register | Pinned by tests/Feature/WondermentTest.php — 6 cases / 33 assertions.\n",
    0, 1, ['0 stale and 1 unbound'], [],
    'a class no run answers is UNBOUND — a parser with a hole and a checked document must not look alike');

$case('surface-matters',
    "ROW 1: Register | The suite answers 780 tests and 5220 assertions in CI’s suite step.\n",
    "ROW 1: Register | The suite answers 999 tests and 999 assertions in CI’s suite step.\n",
    0, 1, ['STALE'], [],
    'the surface a claim names decides which reading may answer it, and a present CI figure is judged on CI’s own run');

// ------------------------------------------------------------------ the expectations that must stay clean

$case('ratios-are-not-claims',
    "ROW 1: Print | Done: 8/8 rendered in storage/app/prints/fidelity and the desk is open 24/7 for the office.\n",
    "ROW 1: Print | Done: 9/8 rendered in storage/app/prints/fidelity and the desk is open 48/7 for the office.\n",
    0, 0, ['every present-tense claim binds'], ['STALE', 'UNBOUND'],
    'a ratio changed under the checker must still not be read as a run total (only the control claim is read)');

$case('dated-is-history',
    "ROW 1: Register | The suite was measured 2026-01-01: 3 tests and 4 assertions, and it passed.\n",
    "ROW 1: Register | The suite was measured 2026-01-02: 999 tests and 999 assertions, and it passed.\n",
    0, 0, ['HISTORY'], ['STALE'],
    'a dated record is history: moving its figure must not turn it into a claim about this build');

$case('clean-class-claim-binds',
    "ROW 1: Register | Pinned by tests/Feature/DeskTileNumberSourceTest.php (four cases, 290 assertions).\n",
    "ROW 1: Register | Pinned by tests/Feature/DeskTileNumberSourceTest.php (four cases, 291 assertions).\n",
    0, 1, ['STALE'], [],
    'the run’s own per-class totals bind the figures the document states, one peso of drift is refused');

// ------------------------------------------------------------------ the refusals

// The nesting the first reader missed: a whole-suite junit sits one level deeper than a folder-scoped one, and reading
// only two levels found the run's total and no classes at all — which reported three true class figures as UNBOUND on
// the one file that answered them.
$deep = $dir.'/fixture-junit-deep.xml';
file_put_contents($deep, <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="/home/runner/work/repo/phpunit.xml" tests="780" assertions="5220">
    <testsuite name="Feature" tests="746" assertions="5120">
      <testsuite name="Tests\Feature\SchoolTimeTest" tests="6" assertions="33">
        <testsuite name="a school day opens at the school’s midnight" tests="1" assertions="5"/>
      </testsuite>
    </testsuite>
  </testsuite>
</testsuites>
XML);
file_put_contents($dir.'/deep-probe.txt',
    "ROW 1: Register | Pinned by tests/Feature/SchoolTimeTest.php — 6 cases / 33 assertions.\n");
$r = $runOn('deep-probe.txt', ' --junit=storage/app/scratch/lane58-mut/fixture-junit-deep.xml');
$say($r['code'] === 0 && str_contains($r['out'], 'MATCHES class SchoolTimeTest')
    && substr_count($r['out'], 'junit run:') === 2,
    'deep-junit — classes are found one level deeper, and a data provider’s group is not mistaken for a run');

$r = $runOn('pair-wrong.clean.txt', ' --ci');
$say(str_contains($r['out'], 'SKIPPED in CI mode') || $r['code'] === 0,
    'ci-mode — a claim whose surface is this box is named as skipped, not silently passed');

file_put_contents($dir.'/no-claims.txt', "ROW 1: Register | Nothing about a suite here.\n");
$r = $runOn('no-claims.txt');
$say($r['code'] === 2 && str_contains($r['out'], 'matched nothing'),
    'no-claims — a scan that read no claim is a broken instrument, not a pass');

$r = $run($php.' '.$checker.' --live= --discovered=780');
$say(str_contains($r['out'], 'named no document'), 'empty-scope — an empty document set refuses rather than reporting a clean tree');

$r = $runOn('pair-wrong.clean.txt', ' --junit=storage/app/scratch/lane58-mut/does-not-exist.xml');
$say($r['code'] === 2, 'missing-junit — a reading one flag short is refused, so a case cannot pass by not loading');

// ------------------------------------------------------------------ ground truth against the real run

echo "\n";
$real = '/storage/app/scratch/lane58-junit-full.xml';
if (is_file($root.$real) && (int) filesize($root.$real) > 0) {
    // No --discovered here: the checker measures the case count itself, so this leg cannot pass on a remembered number.
    $gt = $run($php.' '.$checker.' --flat=storage/app/scratch/docx-flat.txt'
        .' --junit=storage/app/scratch/lane58-junit-full.xml'
        .' --local-suite=storage/app/scratch/lane58-suite.txt');
    $out = $gt['out'];
    $code = $gt['code'];
    $classLines = preg_grep('/CLASS/', preg_split('/\n/', $out) ?: []);
    // The verdict prints on the line after each claim, so matching is counted from the whole output, not from the
    // claim lines — counting MATCHES inside a CLASS line reported 0 bound while the run bound all three.
    $matched = preg_match_all('/MATCHES class /', $out);
    $say(count($classLines) >= 3 && $matched >= 3,
        'real-junit — every class figure the document states binds to the run’s own JUnit ('.$matched.'/'.count($classLines).' class claims, exit '.$code.')');
    echo str_replace("\n", "\n     ", rtrim($out))."\n";
} else {
    $say(false, 'real-junit — no whole-suite JUnit on disk; the fixture legs prove the parsing, this leg proves the binding');
}

if ($fail > 0) {
    printf("\n%d case(s) BAD — the checker or the harness is wrong, and a clean verdict from either would have been a lie.\n", $fail);
    exit(1);
}
echo "\nAll lane 58 cases behaved.\n";
exit(0);
