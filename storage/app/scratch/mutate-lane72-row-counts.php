<?php

/**
 * Prove that lane72-row-count-claims.php can fail, and that its four "shape" buckets are load-bearing.
 *
 * The control runs on the real dump; every other leg runs on a COPY through --dump= (the tool finds the project
 * root itself, so a copy must stay inside storage/app/scratch). Each leg asserts on the printed message, because
 * an exit code cannot be read back through cmd.exe and the tool exits 1 for several different reasons anyway.
 *
 * Three of the legs require SILENCE, and they are the ones that matter: this checker's first queue was five
 * sentences, all five of them the instrument's own breadth ("one student", "two offices", "7 workflowsteps"), so a
 * leg that only plants a stale number proves the judge, not the filters. The silent legs plant a false figure in
 * each shape the tool refuses to weigh and require the clean verdict to survive.
 *
 *   php storage/app/scratch/mutate-lane72-row-counts.php
 */
$root = dirname(__DIR__, 3);
$scratch = $root.'/storage/app/scratch';
$php = 'C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe';
$tool = $scratch.'/lane72-row-count-claims.php';
$dumpSrc = $scratch.'/docx-flat.txt';
$tmp = $scratch.'/tmp-lane72';
@mkdir($tmp);
$fails = 0;

$run = static function (string $args) use ($php, $tool, $root): string {
    return (string) shell_exec('cd '.escapeshellarg($root).' && '.$php.' '.escapeshellarg($tool).' --verify '.$args.' 2>&1');
};

$check = static function (string $name, bool $ok, string $detail) use (&$fails): void {
    printf("  %s %s%s\n", $ok ? 'PASS' : 'BAD ', $name, $detail === '' ? '' : ' — '.$detail);
    if (! $ok) {
        $fails++;
    }
};

$agree = 'every present-tense row count this checker bound equals';
$staleOf = static function (string $out, string $table): int {
    // A queue row is the padded printf shape, not its leading word — the footer also names these words
    // (lane 70's lesson, paid for twice).
    preg_match_all('/^\s*STALE\s+line \d+\s+document\s+\d+,\s+`'.preg_quote($table, '/').'`/m', $out, $m);

    return count($m[0]);
};
$tally = static function (string $out, string $name): ?int {
    return preg_match('/(\d+) '.preg_quote($name, '/').',/', $out, $m) === 1 ? (int) $m[1] : null;
};

$dump = (string) file_get_contents($dumpSrc);
if (substr_count($dump, 'holds nine offices') < 1) {
    exit("REFUSED: the dump no longer carries the sentence this harness plants into, so every leg below would\n"
        ."        report the tool's own silence as a pass.\n");
}

// 1 — the control.
$out = $run('');
$controlMatch = $tally($out, 'MATCH');
$agreeLine = $out;
$check('control', str_contains($out, $agree) && $staleOf($out, 'offices') === 0 && $controlMatch !== null,
    'MATCH '.$controlMatch);

// 2 — a planted stale census figure: the true "nine offices" re-spelled as eight.
$planted = str_replace('holds nine offices.', 'holds eight offices.', $dump, $n1);
if ($n1 !== 2) {
    printf("REFUSED: the stale needle matched %d time(s), not the 2 copies this leg expects.\n", $n1);
    exit(1);
}
file_put_contents($tmp.'/stale.txt', $planted);
$out = $run('--dump=storage/app/scratch/tmp-lane72/stale.txt');
$check('planted-stale-figure raises STALE on offices', $staleOf($out, 'offices') === 2
    && str_contains($out, 'state a count the server disagrees with'), 'STALE/offices '.$staleOf($out, 'offices'));
$check('planted-stale leaves the honest claims standing', ($m = $tally($out, 'MATCH')) !== null
    && $m === $controlMatch - 2, 'MATCH '.var_export($m, true).' expected '.($controlMatch - 2));

// 3 — a false figure in a COMPARISON sentence must not be weighed: the tool does not know how many offices the
// two examination flows differ in, and neither does COUNT(*).
$planted = str_replace('two offices, two permissions', 'eight offices, eight permissions', $dump, $n2);
if ($n2 !== 1) {
    printf("REFUSED: the comparison needle matched %d time(s), not 1.\n", $n2);
    exit(1);
}
file_put_contents($tmp.'/comparison.txt', $planted);
$out = $run('--dump=storage/app/scratch/tmp-lane72/comparison.txt');
$check('comparison-shaped false figure is not judged', $staleOf($out, 'offices') === 0 && str_contains($out, $agree),
    'STALE/offices '.$staleOf($out, 'offices'));

// 4 — a false figure in the walkthrough's per-record trail must not be weighed against the whole table.
$planted = str_replace('7 workflowsteps', '8 workflowsteps', $dump, $n3);
if ($n3 !== 1) {
    printf("REFUSED: the trail needle matched %d time(s), not 1.\n", $n3);
    exit(1);
}
file_put_contents($tmp.'/trail.txt', $planted);
$out = $run('--dump=storage/app/scratch/tmp-lane72/trail.txt');
$check('per-record trail figure is not judged', $staleOf($out, 'workflowsteps') === 0 && str_contains($out, $agree),
    'STALE/workflowsteps '.$staleOf($out, 'workflowsteps'));

// 5 — a false figure on a class member must not be weighed, and must not silently become a table claim either.
$planted = str_replace('the two Enrollments::create sites', 'the eight Enrollments::create sites', $dump, $n4);
if ($n4 !== 1) {
    printf("REFUSED: the class-member needle matched %d time(s), not 1.\n", $n4);
    exit(1);
}
file_put_contents($tmp.'/codeish.txt', $planted);
$out = $run('--dump=storage/app/scratch/tmp-lane72/codeish.txt');
$check('class-member count is not judged as a table', $staleOf($out, 'enrollments') === 0 && str_contains($out, $agree),
    'STALE/enrollments '.$staleOf($out, 'enrollments'));

// 6 — a dated sentence is a record: put today's figure under a day and it stops being a living claim.
$planted = str_replace('Live ems holds nine offices.', '2026-01-01 — Live ems holds nine offices.', $dump, $n5);
if ($n5 < 1) {
    printf("REFUSED: the date needle matched %d time(s).\n", $n5);
    exit(1);
}
file_put_contents($tmp.'/dated.txt', $planted);
$out = $run('--dump=storage/app/scratch/tmp-lane72/dated.txt');
$check('a dated figure becomes HISTORY, not a verdict', ($m = $tally($out, 'MATCH')) !== null
    && $m === ($controlMatch - $n5) && str_contains($out, $agree), 'MATCH '.var_export($m, true).' expected '.($controlMatch - $n5));

// 8 — a dump carrying nothing to judge must refuse, not report agreement. The fixture strikes EVERY lowercase
// plural, so no noun can resolve to a table — striking a remembered list of seven table names (the first draft)
// left `permissions` standing, and the run answered with a real STALE instead of the refusal this leg wants.
$none = (string) preg_replace('/\b[a-z]{4,}s\b/', 'zzz', $dump);
file_put_contents($tmp.'/none.txt', $none);
$out = $run('--dump=storage/app/scratch/tmp-lane72/none.txt');
$check('nothing-to-judge refuses', str_contains($out, 'REFUSED: the walk bound nothing'),
    trim(substr((string) strtok($out, "\n"), 0, 70)));

// 8 — a byte-identical copy must answer exactly like the control.
file_put_contents($tmp.'/copy.txt', $dump);
$out = $run('--dump=storage/app/scratch/tmp-lane72/copy.txt');
$check('byte-identical copy answers like the control', $out === $agreeLine, $out === $agreeLine ? '' : 'output differs');

// 9 — the queue is printed, so --limit must say so rather than look clean.
$out = $run('--limit=1');
$check('--limit reports an incomplete queue', str_contains($out, 'the queue above is INCOMPLETE')
    && (bool) preg_match('/queue above is INCOMPLETE: (\d+) row/', $out, $mm) && $mm[1] >= 1, substr((string) ($mm[1] ?? '?'), 0, 8).' withheld');

// 10 — three refusals, told apart by their own messages.
$out = $run('--dump=storage/app/scratch/tmp-lane72/nope.txt');
$check('missing dump refuses by name', str_contains($out, 'REFUSED: no dump at'), trim(substr((string) strtok($out, "\n"), 0, 60)));
file_put_contents($tmp.'/short.txt', implode('', array_slice(file($dumpSrc), 0, 40)));
$out = $run('--dump=storage/app/scratch/tmp-lane72/short.txt');
$check('truncated dump refuses by count', str_contains($out, 'REFUSED: the dump holds'), trim(substr((string) strtok($out, "\n"), 0, 60)));
$out = $run('--allow-schema=ems_nope');
$check('a schema the environment contradicts refuses', str_contains($out, 'REFUSED: asked to judge schema'), trim(substr((string) strtok($out, "\n"), 0, 60)));
$out = $run('--ci');
$check('an unknown flag refuses rather than doing nothing', str_contains($out, 'is not a flag this tool reads'),
    trim(substr((string) strtok($out, "\n"), 0, 60)));

printf("\n%s\n", $fails === 0 ? 'all legs proved the checker can fail and can stay silent' : "$fails leg(s) BAD");
foreach (glob($tmp.'/*.txt') ?: [] as $f) {
    @unlink($f);
}
@rmdir($tmp);
exit($fails === 0 ? 0 : 1);
