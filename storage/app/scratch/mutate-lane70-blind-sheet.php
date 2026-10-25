<?php

/**
 * Lane 70: prove the blind-spot reading sheet can refuse.
 *
 * `lane70-blind-sheet.php` exists to be trusted: it prints the sweep's own UNATTRIBUTED rows and the sentence each
 * one sits in. Every guard it carries is a refusal this repository has had to buy before — a row whose line number
 * names a different paragraph paints an empty sentence and reads like a pointer written without context (lane 51
 * moved citations onto wrong lines and nothing downstream complained); a tally whose rows could not be parsed loses
 * pointers silently (lane 43's checker that reported zero sites); a flag accepted but not read turns a planted
 * failure into a clean run (lane 41).
 *
 * None of those paths is reachable by editing the dump alone, because the sheet forwards --dump= to the sweep, so
 * both always read the same bytes — which is the point, and the reason this harness also drives --sweep= at canned
 * stub outputs. The two dump legs prove the opposite half: that a copy IS what got judged. Every leg names the
 * needle it requires and a run that reaches no verdict reports BAD rather than passing.
 *
 *   php storage/app/scratch/mutate-lane70-blind-sheet.php
 */
$root = dirname(__DIR__, 3);
$php = PHP_BINARY;
$tool = $root.'/storage/app/scratch/lane70-blind-sheet.php';
$dump = $root.'/storage/app/scratch/docx-flat.txt';
$tmp = sys_get_temp_dir().'/lane70-blind-mutate';
if (! is_dir($tmp)) {
    mkdir($tmp, 0777, true);
}

$run = static function (array $args) use ($php, $tool): array {
    $cmd = escapeshellarg($php).' '.escapeshellarg($tool);
    foreach ($args as $a) {
        $cmd .= ' '.escapeshellarg($a);
    }
    // 2>&1, never a redirect to a POSIX device: cmd.exe cannot find /dev/null and exits 1 before the tool runs,
    // which reads as a failed control (lane 69's owed-table harness met exactly that).
    $out = [];
    exec($cmd.' 2>&1', $out, $rc);

    return [$rc, implode("\n", $out)];
};

// A stub sweep is canned text, so write it with var_export rather than nested quoting: `echo 'a\nb'` would print a
// literal backslash-n, and a stub whose rows all land on one line stops proving the one-row-per-blind-spot shape.
$stub = static function (string $name, string $text, int $rc) use ($tmp): string {
    $path = $tmp.'/'.$name.'.php';
    file_put_contents($path, '<?php echo '.var_export($text, true)."; exit({$rc});\n");

    return $path;
};

$SUMMARY = '  bare continuation(s): 0 judged against the file named before them, %d blind spot(s)';
$PHRASE = 'the signature at :273';

$text = (string) file_get_contents($dump);
$lines = explode("\n", $text);
$tokenLine = 0;
foreach ($lines as $i => $raw) {
    if (substr_count($raw, $PHRASE) === 1) {
        $tokenLine = $i + 1;

        break;
    }
}
// A needle that cannot land is the lane 44 mutant that sleeps: it reports the tool's clean output as a failure to
// complain, so refuse the whole run instead of scheduling legs against nothing.
if ($tokenLine === 0 || substr_count($text, $PHRASE) !== 1) {
    printf("REFUSED: the harness reads the blind row out of the real dump, and %s occurs %d time(s) (line %d).\n",
        $PHRASE, substr_count($text, $PHRASE), $tokenLine);
    exit(1);
}

$copyPlain = $tmp.'/dump-copy.txt';
$copyNamed = $tmp.'/dump-named.txt';
$missing = $tmp.'/no-such-dump.txt';
file_put_contents($copyPlain, $text);
file_put_contents($copyNamed, str_replace($PHRASE, 'the signature at PrintService.php:273', $text));
@unlink($missing);

$cases = [
    ['control', [], 0, ['1 blind pointer(s)', 'dump line '.$tokenLine], []],
    ['identical-copy-changes-nothing', ['--dump='.$copyPlain], 0,
        ['1 blind pointer(s)', 'dump line '.$tokenLine], ['REFUSED']],
    ['dump-is-read-not-merely-accepted', ['--dump='.$copyNamed], 0,
        ['0 blind pointer(s)'], ['dump line '.$tokenLine]],
    ['row-without-a-dump-line-field',
        ['--sweep='.$stub('no-field', sprintf($SUMMARY, 1)."\n    UNATTRIBUTED :273 near \"at :273\"\n", 0)],
        1, ['could be parsed'], ['dump line']],
    ['tally-never-printed',
        ['--sweep='.$stub('no-tally', "STUB-MARKER-NO-TALLY\n    UNATTRIBUTED :273 near \"at :273\" on dump line {$tokenLine}\n", 0)],
        1, ['printed no blind-spot tally', 'STUB-MARKER-NO-TALLY'], []],
    ['sweep-exited-2',
        ['--sweep='.$stub('bad-exit', sprintf($SUMMARY, 0)."\n", 2)],
        1, ['exited 2'], []],
    ['line-number-out-of-range',
        ['--sweep='.$stub('far-line', sprintf($SUMMARY, 1)."\n    UNATTRIBUTED :273 near \"at :273\" on dump line 999999\n", 0)],
        1, ['could not be located', '999999'], ['read in document order']],
    ['token-not-on-the-named-line',
        ['--sweep='.$stub('wrong-line', sprintf($SUMMARY, 1)."\n    UNATTRIBUTED :1234567 near \"cites :1234567\" on dump line 3\n", 0)],
        1, ['could not be located', 'does not carry that token'], ['read in document order']],
    ['dump-missing', ['--dump='.$missing], 1, ['REFUSED', 'missing or too short'], []],
];

$failures = 0;

foreach ($cases as [$label, $args, $wantRc, $needles, $forbidden]) {
    [$rc, $out] = $run($args);

    if (trim($out) === '' || $rc === 255) {
        printf("BAD    %-34s reached no verdict (exit %d) — a broken instrument, not a result.\n", $label, $rc);
        $failures++;

        continue;
    }

    $absent = array_values(array_filter($needles, static fn ($n) => ! str_contains($out, $n)));
    $leaked = array_values(array_filter($forbidden, static fn ($n) => str_contains($out, $n)));
    $first = strtok($out, "\n");

    if ($rc === $wantRc && $absent === [] && $leaked === []) {
        printf("PASS   %-34s %s — %s\n", $label, $wantRc === 0 ? 'answered' : 'refused', $first);
        if ($label === 'dump-is-read-not-merely-accepted') {
            echo "       (the unmutated run above prints this row; a --dump= that was ignored would too)\n";
        }

        continue;
    }

    printf("FAIL   %-34s exit %d wanted %d%s%s\n", $label, $rc, $wantRc,
        $absent === [] ? '' : ', never printed: '.implode(' | ', $absent),
        $leaked === [] ? '' : ', printed what must be absent: '.implode(' | ', $leaked));
    foreach (array_slice(explode("\n", $out), 0, 4) as $l) {
        echo '       '.$l."\n";
    }
    $failures++;
}

[$rc, $out] = $run([]);
if ($rc !== 0 || ! str_contains($out, '1 blind pointer(s)')) {
    printf("FAIL   %-34s the tree no longer answers the control: exit %d\n", 'control-again', $rc);
    $failures++;
} else {
    printf("PASS   %-34s still green after every leg, and every file the harness made is gone\n", 'control-again');
}

array_map('unlink', glob($tmp.'/*') ?: []);
@rmdir($tmp);

printf("\n%d legs, %d failure(s).\n", count($cases) + 1, $failures);
if ($failures > 0) {
    echo "The reading sheet is not trustworthy: a leg that cannot make it refuse is a guard that does not exist.\n";
    exit(1);
}
echo "Every refusal path fired, both green legs answered, and a --dump= copy changed what was judged.\n";
exit(0);
