<?php

/**
 * Prove that `lane108c-check-the-narrowed-lists.php` can fail.
 *
 * The auditor answered `MISSING 0` over fourteen narrowed-load pages on its first correct run. A clean verdict from
 * a brand-new instrument is the shape this repository has been burned by repeatedly (lane 46 cleared the whole tree
 * because it read `route('x', {…})` instead of `router.get(…)`; lane 50 passed every real call because it matched
 * only the shape it had just learned), so each promise below is planted and required to move the verdict:
 *
 *   dropped-columns      one narrowed list shrunk to `studentId` must make the page's own name columns MISSING.
 *   suffix-read          a planted `student?.suffix` read must be MISSING — `suffix` is a NAME column and the
 *                        five-column list does not carry it, which is the mistake the census would have made on
 *                        the four detail screens that paint it.
 *   birthdate-read       a planted non-demographic read must be MISSING (the other branch).
 *   email-does-not-classify the same plant spelled `email` must NOT be MISSING: the signed-in staff member's email
 *                        sits in the layout every page imports, and classifying on it once called all 25 pages
 *                        sensitive — the opposite defect, and a leg that accepts either verdict is the leg that
 *                        cannot fail.
 *   whole-load-reachable a real `'student'` added to the same action's `with([…])` must enter
 *                        WHOLE-LOAD-IN-SAME-ACTION and name the path, proving the bucket the first cut mis-filled
 *                        is reachable by the corrected anchor (`whereHas('student', ...)` must NOT fill it — the
 *                        control's `WHOLE-LOAD-IN-SAME-ACTION 0` over a tree that holds four of those is that leg).
 *   unknown-flag         refuses by name.
 *
 * Files are restored by hash after EVERY leg, and unconditionally at shutdown: a guard that made the restore a
 * no-op after the first call left each later mutant in the tree, and the next case then measured a tree already
 * carrying the previous defect. A needle that matches zero times is BAD, never a pass.
 *
 * Usage: php storage/app/scratch/mutate-lane108c-narrowed-audit.php
 */
$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$tool = $root.'/storage/app/scratch/lane108c-check-the-narrowed-lists.php';
$ctrl = $root.'/app/Http/Controllers/Registrar/RegistrarController.php';
$page = $root.'/resources/js/Pages/Registrar/Index.jsx';

foreach ([$tool, $ctrl, $page] as $f) {
    if (! is_file($f)) {
        fwrite(STDERR, 'REFUSED: '.basename($f).' does not exist, so there is nothing to mutate.'."\n");

        exit(1);
    }
}

$snap = [];
foreach ([$ctrl, $page] as $f) {
    $snap[$f] = (string) file_get_contents($f);
}
restore_all($snap, 'start');

/** @param array<string,string> $snap */
function restore_all(array $snap, string $when): void
{
    foreach ($snap as $f => $bytes) {
        if ((string) @file_get_contents($f) !== $bytes) {
            file_put_contents($f, $bytes);
        }
    }
    echo '  ['.$when.'] tree restored by hash'.PHP_EOL;
}

register_shutdown_function(static function () use (&$snap): void {
    restore_all($snap, 'shutdown');
});

/**
 * Plant, then make a FRESH CHILD PROCESS confirm the marker before anything is measured.
 *
 * The first two runs of this harness printed `MISSING 0 → silent` for planted page reads while a separate check
 * showed the bytes on disk. The plant had stuck — the child the harness then measured with had not read it: on this
 * OneDrive-backed tree, a process that has already written and restored several times can hand its next child a
 * stale view of the file. A leg must not be allowed to report a surviving defect on bytes the tool never saw, so
 * the visibility check runs BEFORE the measurement and retries the write up to five times; if the child still
 * cannot see the marker the leg is reported as the harness's own failure.
 *
 * @return array{ok: bool, note: string}
 */
function plant_visible(string $file, string $from, string $to, string $marker): array
{
    global $php, $root;
    for ($try = 1; $try <= 5; $try++) {
        $src = (string) file_get_contents($file);
        if (! str_contains($src, $marker)) {
            if (substr_count($src, $from) !== 1) {
                return ['ok' => false, 'note' => 'needle matched '.substr_count($src, $from).' time(s), wanted 1'];
            }
            file_put_contents($file, str_replace($from, $to, $src));
        }
        $seen = [];
        chdir($root);
        exec(escapeshellarg($php).' '.escapeshellarg($root.'/storage/app/scratch/lane108c-child-sees.php')
            .' '.escapeshellarg($file).' '.escapeshellarg($marker).' 2>&1', $seen);
        $answer = trim(implode(' ', $seen));
        if (str_contains($answer, 'SEES') && ! str_contains($answer, 'NOT SEES')) {
            return ['ok' => true, 'note' => $try === 1 ? 'child saw the plant' : "child saw the plant on try {$try}"];
        }
        usleep(400000);
    }

    return ['ok' => false, 'note' => 'the child process could not be made to read the plant in 5 tries'
        .' (last answer: '.$answer.') — this leg measured nothing'];
}

$bad = 0;
$legs = 0;

function audit(array $argvExtra = []): array
{
    global $root, $php, $tool;
    // No probe flag rides on the command: the tool refuses any argument it does not read, by name, so an
    // invented `--quiet=` would make every leg below measure a refusal instead of a verdict.
    $cmd = escapeshellarg($php).' '.escapeshellarg($tool);
    foreach ($argvExtra as $a) {
        $cmd .= ' '.escapeshellarg($a);
    }
    chdir($root);
    $out = [];
    exec($cmd.' 2>&1', $out, $code);

    return ['out' => implode("\n", $out), 'code' => $code];
}

function tally(string $out, string $label): int
{
    if (preg_match('/^\s{2}'.preg_quote($label, '/').'\s+(\d+)$/m', $out, $m) === 1) {
        return (int) $m[1];
    }

    return -1;
}

/** Plant one exact byte-for-byte change; refuse the leg when its needle does not land. */
function plant(string $file, string $from, string $to): ?string
{
    $src = (string) file_get_contents($file);
    $n = substr_count($src, $from);
    if ($n !== 1) {
        return "needle matched {$n} time(s) in ".basename($file).', wanted exactly 1';
    }
    if (file_put_contents($file, str_replace($from, $to, $src)) === false) {
        return 'could not write '.basename($file);
    }
    // A leg that reports "the tool did not see the defect" must first know the tool could have: re-read the file and
    // require the marker to be on disk. Without this, a lost write looks exactly like a surviving defect — and this
    // harness's first run printed `MISSING 0 → silent` for two planted reads, which this line now tells apart.
    $after = (string) file_get_contents($file);
    if (! str_contains($after, $to)) {
        return 'wrote '.basename($file).' but the replacement is not in the bytes on disk afterwards'
            .' (the write did not stick — not a verdict about the tool)';
    }

    return null;
}

$control = audit();
$legs++;
$cMissing = tally($control['out'], 'MISSING');
$cOk = tally($control['out'], 'OK');
$cWhole = tally($control['out'], 'WHOLE-LOAD-IN-SAME-ACTION');
$cNoComp = tally($control['out'], 'NO-COMPONENT');
printf("control: MISSING %d, OK %d, WHOLE-LOAD %d, NO-COMPONENT %d, exit %d%s",
    $cMissing, $cOk, $cWhole, $cNoComp, $control['code'], PHP_EOL);
if ($cMissing !== 0 || $cWhole !== 0 || $cOk < 12 || $control['code'] !== 0) {
    $bad++;
    echo '  BAD — the control is not green, so no verdict below measures anything.'
        .' WHOLE-LOAD 0 is itself a claim: the tree holds four `whereHas(\'student\', …)` clauses and an'
        .' `withErrors([\'student\' => …])`, none of which loads a row.'."\n";
} else {
    echo '  ok'.PHP_EOL;
}

// --- leg 2: shrink one narrowed list to a single column -----------------------------------------------
$r = plant($ctrl, "'student:studentId,schoolIdNumber,lastName,firstName,middleName', 'course', 'major', 'term',",
    "'student:studentId', 'course', 'major', 'term',");
$legs++;
if ($r !== null) {
    $bad++;
    echo '  BAD (dropped-columns): '.$r."\n";
} else {
    $m = audit();
    $n = tally($m['out'], 'MISSING');
    $names = ['schoolIdNumber', 'lastName', 'firstName', 'middleName'];
    $carried = [];
    if (preg_match('/the list does NOT carry: (.+)$/m', $m['out'], $cm) === 1) {
        foreach ($names as $col) {
            if (str_contains($cm[1], $col)) {
                $carried[] = $col;
            }
        }
    }
    printf('  dropped-columns: MISSING %d, carries %s, exit %d%s', $n,
        $carried === [] ? '(nothing)' : implode(',', $carried), $m['code'], PHP_EOL);
    if ($n !== 1 || count($carried) !== 4 || $m['code'] !== 1) {
        $bad++;
        echo '    BAD — a list narrowed to one column must name all four other identity columns MISSING and exit 1.'."\n";
    } else {
        echo '    ok'.PHP_EOL;
    }
    restore_all($snap, 'after dropped-columns');
}

// --- legs 3-5: plant a read the narrowed list does not carry -------------------------------------------
foreach ([
    ['suffix-read', 'suffix', true],
    ['birthdate-read', 'birthdate', true],
    ['email-does-not-classify', 'email', false],
] as [$label, $col, $mustFire]) {
    $legs++;
    $marker = 'lane108cProbe';
    // `?.{$col}`, not `?->{$col}`: PHP's double-quoted interpolation ate the brace and wrote a PROPERTY ARROW into
    // the JavaScript, so the planted read was `student?->suffix` — bytes the classifier is right to ignore, because
    // no JS page reaches a field that way. Three runs of "the tool cannot see my plant" were the mutant's spelling,
    // not the rule's hole; the verifier below now checks the exact dotted text the leg claims to have planted.
    $line = "const {$marker} = (row) => row?.student?.{$col} ?? '';\n";
    $pv = plant_visible($page, 'export default function', $line.'export default function', 'student?.'.$col);
    if (! $pv['ok']) {
        $bad++;
        echo "  BAD ({$label}): ".$pv['note']."\n";

        continue;
    }
    $m = audit();
    $n = tally($m['out'], 'MISSING');
    $carries = [];
    preg_match_all('/the list does NOT carry: (.+)$/m', $m['out'], $cm);
    foreach ($cm[1] as $carryLine) {
        if (str_contains($carryLine, $col)) {
            $carries[] = $carryLine;
        }
    }
    preg_match_all('/the list does NOT carry: (.+)$/m', $m['out'], $cm);
    foreach ($cm[1] as $carryLine) {
        if (str_contains($carryLine, $col)) {
            $carries[] = $carryLine;
        }
    }
    $fired = $carries !== [];
    printf('  %s: MISSING %d, %s %s → %s (%s)%s', $label, $n, $col,
        $mustFire ? 'must classify' : 'must NOT classify', $fired ? 'FIRED' : 'silent', $pv['note'], PHP_EOL);
    if ($mustFire !== $fired) {
        // A leg that disagrees is kept with the child's whole answer, because "the tool says 0" has meant three
        // different things here: the classifier missed the shape, the row fell into another bucket, or the walk
        // degraded. Only the printed rows tell which.
        $dump = $root.'/storage/app/scratch/mutate-lane108c-failing-'.$label.'.txt';
        file_put_contents($dump, $m['out']);
        $bucket = [];
        foreach (explode("\n", $m['out']) as $l) {
            if (str_contains($l, 'RegistrarController')) {
                $bucket[] = trim(preg_replace('/\s+/', ' ', $l) ?? '');
            }
        }
        echo '    Registrar row(s) as the child printed them: '.implode(' || ', $bucket)."\n"
            .'    full output kept at '.basename($dump)."\n";
        $bad++;
        echo '    BAD — the verdict disagrees with the rule the leg exists to prove.'."\n";
    } elseif ($mustFire && $n !== 1) {
        $bad++;
        echo '    BAD — one planted read must produce exactly one MISSING row, got '.$n.'.'."\n";
    } else {
        if ($mustFire && str_contains($m['out'], 'shared-name hits to read')) {
            echo '    (ambiguous names are printed beside a real MISSING, never judged alone — ok)'.PHP_EOL;
        }
        echo '    ok'.PHP_EOL;
    }
    restore_all($snap, "after {$label}");
}

// --- leg 6: a real whole load in the same action --------------------------------------------------------
$r = plant($ctrl, "'student:studentId,schoolIdNumber,lastName,firstName,middleName', 'course', 'major', 'term',",
    "'student:studentId,schoolIdNumber,lastName,firstName,middleName', 'student', 'course', 'major', 'term',");
$legs++;
if ($r !== null) {
    $bad++;
    echo '  BAD (whole-load-reachable): '.$r."\n";
} else {
    $m = audit();
    $n = tally($m['out'], 'WHOLE-LOAD-IN-SAME-ACTION');
    printf('  whole-load-reachable: WHOLE-LOAD %d, MISSING %d%s', $n, tally($m['out'], 'MISSING'), PHP_EOL);
    if ($n !== 1 || ! str_contains($m['out'], 'this action also loads student whole')) {
        $bad++;
        echo '    BAD — the bucket must be reachable by the load-call anchor and must name the path it decided on.'."\n";
    } else {
        echo '    ok'.PHP_EOL;
    }
    restore_all($snap, 'after whole-load-reachable');
}

// --- leg 7: an unknown flag is refused by name -----------------------------------------------------------
$legs++;
$u = audit(['--no-such-flag-at-all']);
if ($u['code'] === 0 || ! str_contains($u['out'], '--no-such-flag-at-all')) {
    $bad++;
    echo '  BAD (unknown-flag): refused by name and exit 1 required; got exit '.$u['code'].'.'."\n";
} else {
    echo '  unknown-flag: refused by name'.PHP_EOL;
}

// --- final control ---------------------------------------------------------------------------------------
restore_all($snap, 'final');
$f = audit();
$legs++;
if ($f['code'] !== 0 || tally($f['out'], 'MISSING') !== $cMissing || tally($f['out'], 'OK') !== $cOk) {
    $bad++;
    echo '  BAD — the trailing control does not read like the leading one, so a restore failed.'."\n";
} else {
    echo '  control-again: MISSING '.tally($f['out'], 'MISSING').', OK '.tally($f['out'], 'OK').' — ok'.PHP_EOL;
}

printf("\n%s: %d leg(s), %d bad\n", $bad === 0 ? 'ALL PROVED' : 'FAILED', $legs, $bad);

exit($bad === 0 ? 0 : 1);
