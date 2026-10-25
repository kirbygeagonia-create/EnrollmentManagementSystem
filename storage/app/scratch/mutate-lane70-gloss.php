<?php

/**
 * Proof that lane70-gloss-snippets.php can fail.
 *
 * Every case plants one defect in a COPY of the flat dump and requires the named verdict to appear; the
 * untouched control must stay clean, and a case whose needle matches nothing prints BAD rather than passing —
 * a mutant that cannot apply is a mutant that sleeps. The last two legs are the ones that have bitten this
 * repository: the `--dump=` flag must change what is scanned (a flag that is accepted but ignored makes a
 * mutant run look like a clean one), and a missing dump must refuse instead of answering with an empty table.
 *
 *   php storage/app/scratch/mutate-lane70-gloss.php
 */
$root = dirname(__DIR__, 3);
$php = PHP_BINARY;
$checker = $root.'/storage/app/scratch/lane70-gloss-snippets.php';
$realDump = $root.'/storage/app/scratch/docx-flat.txt';
$copies = [];

$fails = 0;
$report = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'BAD ', $what);
    if (! $ok) {
        $fails++;
    }
};

$exec = function (string $cmd): int {
    exec($cmd.' 2>&1', $outLines, $rc);

    return $rc;
};
$readOut = function (string $cmd): string {
    return (string) shell_exec($cmd.' 2>&1');
};

$hardCount = function (string $out): int {
    return preg_match('/NOT-IN-FILE — the prose quotes code that does not exist \((\d+)\)/', $out, $m) === 1
        ? (int) $m[1] : -1;
};

echo "=== control: the dump as it stands ===\n";
$ctl = $readOut('"'.$php.'" "'.$checker.'"');
preg_match('/glossed citation\(s\) examined: (\d+)/', $ctl, $examined);
$report(($examined[1] ?? 0) >= 5, 'the control examined '.($examined[1] ?? 0).' glossed citations (a run that binds nothing proves nothing)');
$report($hardCount($ctl) === 0, 'the control reports 0 NOT-IN-FILE row(s)');

$cases = [
    // a quoted call the cited file does not contain: the exact defect pass 196 cleared
    ['the prose quotes a call that is not in the file', ['array_filter()', 'unset($steps[2])'],
        'NOT-IN-FILE', 'unset'],
    // a quoted VARIABLE that exists nowhere: same verdict, different lookup, because a variable is used and
    // not called and a pattern that demands a bracket after the name would report a live line as silent
    ['a quoted variable that exists nowhere', ['( $isFlexibleStudent )', '( $isFlexibleStudentAlt )'],
        'NOT-IN-FILE', '$isFlexibleStudentAlt'],
    // a symbol that exists in the file, but neither inside the cited lines nor as the method owning them
    ['a real call cited at a range that excludes it',
        ['WorkflowService.php:66-71 ( array_filter() )', 'WorkflowService.php:180-190 ( stepLabels() )'],
        'OUT-OF-RANGE', 'stepLabels'],
];
foreach ($cases as $ci => [$what, $mut, $wantVerdict, $wantToken]) {
    echo "\n=== planted: {$what} ===\n";
    $text = (string) file_get_contents($realDump);
    $n = 0;
    $staged = str_replace($mut[0], $mut[1], $text, $n);
    if ($n === 0) {
        $report(false, $what.' — the needle matched nothing, so nothing was planted');

        continue;
    }
    if ($staged === $text) {
        $report(false, $what.' — the mutation left the copy byte-identical');

        continue;
    }
    $path = $root.'/storage/app/scratch/lane70-gloss-mutant-'.$ci.'.txt';
    file_put_contents($path, $staged);
    $copies[] = $path;
    $out = $readOut('"'.$php.'" "'.$checker.'" --dump="'.$path.'"');
    foreach (explode("\n", $out) as $ol) {
        if (str_contains($ol, 'NOT-IN-FILE') || str_contains($ol, 'OUT-OF-RANGE') || str_contains($ol, 'PARAPHRASE')) {
            echo '      | '.trim($ol)."\n";
        }
    }
    $report(str_contains($out, $wantVerdict), 'the run answers '.$wantVerdict.' for the planted defect');
    $report(str_contains($out, $wantToken), 'and names the token it read ['.$wantToken.']');
    if ($wantVerdict === 'NOT-IN-FILE') {
        $fakeOut = $out;
        $report($hardCount($out) >= 1, 'a fake call is refused as a finding, not silence');
    }
}

echo "\n=== the flag is read, not accepted ===\n";
$after = $readOut('"'.$php.'" "'.$checker.'"');
$report($hardCount($after) === 0 && ($fakeOut ?? '') !== '' && $hardCount($fakeOut) >= 1,
    'the real dump still answers 0 while the copy answered '.$hardCount($fakeOut ?? '').' — so --dump= selects the file');

echo "\n=== a missing dump refuses ===\n";
$nowhere = $root.'/storage/app/scratch/definitely-not-here.txt';
$missing = $readOut('"'.$php.'" "'.$checker.'" --dump="'.$nowhere.'"');
$report(str_contains($missing, 'REFUSED'), 'an unread document refuses instead of printing an empty table');
$report($exec('"'.$php.'" "'.$checker.'" --dump="'.$nowhere.'"') === 1,
    'and the refusal exits 1, not 0');

foreach ($copies as $c) {
    @unlink($c);
}
echo "\n";
if ($fails > 0) {
    printf("%d leg(s) failed — the checker is not proven.\n", $fails);
    exit(1);
}
echo "all legs pass: the gloss checker can fail, and it reads the dump it is given.\n";
