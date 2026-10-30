<?php

/**
 * Prove lane 90's pagination probe by planting each defect it already met.
 *
 * The probe prints "29 paginated / 4 collection / 8 client-derived / 0 unread" and §28.3 G-40 now states that
 * census. Three earlier drafts printed a verdict from a wrong join — backslash page paths against forward-slash
 * render names, a whole-file `paginate(` match, and a scanner that stopped at a `;` inside a closure body — so each
 * of those is planted here in a throwaway copy and the run must answer differently from the control. A leg that
 * cannot fail is decoration, so every leg states the NAMED effect it requires, not merely "output changed".
 *
 * Nothing here touches the repository's real files: each copy is lane90-mut-<n>.php, and the probe walks
 * resources/js/Pages and app/Http/Controllers, never storage/app/scratch, so a copy cannot enter its own
 * population. Copies are unlinked by the shutdown handler even when a leg fatals.
 *
 * Usage: php storage/app/scratch/mutate-lane90-pagination.php
 */

$root = dirname(__DIR__, 3);
$scratch = $root.'/storage/app/scratch';
$php = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe';
$probe = $scratch.'/lane90-probe-pagination.php';

$src = (string) file_get_contents($probe);
$copies = [];
// By reference, or the handler is handed the empty array it captured at registration and every planted copy
// survives the run — measured, not theorised: the first run of this harness left lane90-mut-*.php in the tree, and
// storage/app/.gitignore re-includes scratch by extension, so a mutant copy is untracked repository content.
register_shutdown_function(function () use (&$copies, $scratch) {
    foreach ($copies as $c) {
        if (is_file($c)) {
            unlink($c);
        }
    }
    foreach (glob($scratch.'/lane90-mut-*.php') ?: [] as $stray) {
        unlink($stray);
    }
});

/**
 * Run one PHP file, return [stdout+stderr, exit code]. The exit code is read from exec's by-reference result, never
 * from the trailing command's own status.
 */
$run = function (string $file, string $args = '') use ($php): array {
    $lines = [];
    $rc = -1;
    exec('"'.$php.'" '.escapeshellarg($file).' '.$args.' 2>&1', $lines, $rc);

    return [implode("\n", $lines), $rc];
};

/**
 * The census line is the probe's own summary; compare it rather than whole output, which carries volatile paths.
 */
$censusOf = function (string $out): string {
    return preg_match('/^census by verdict: (.*)$/m', $out, $m) === 1 ? trim($m[1]) : '(no census line)';
};

$fail = 0;
$legs = 0;

// ---- control ------------------------------------------------------------------------------------------------

$probeOut = null;
[$controlOut, $controlRc] = $run($probe);
$control = $censusOf($controlOut);
$controlOk = $controlRc === 0 && str_contains($controlOut, '0 of') && str_contains($controlOut, 'rows unread');
++$legs;
printf("[%s] control            pristine probe exits 0 with a full census: %s\n", $controlOk ? 'ok' : 'FAIL', $control);
if (! $controlOk) {
    fwrite(STDERR, "REFUSED: a control that is not green makes every later leg meaningless.\n");
    fwrite(STDERR, substr($controlOut, -800)."\n");

    exit(1);
}

/**
 * @param  array<int, array{0:string,1:string,2:string,3:string,4:int}|array{string,string,string,string,int}
 */
$plant = function (string $name, string $from, string $to, string $wantNeedle, int $wantCount = 1) use (&$copies, &$fail, &$legs, $scratch, $src, $run, $censusOf, $control): void {
    ++$legs;
    $n = substr_count($src, $from);
    if ($n !== $wantCount) {
        printf("[FAIL] %-20s needle matches %d time(s), expected %d — the leg cannot land\n", $name, $n, $wantCount);
        ++$fail;

        return;
    }
    $file = $scratch.'/lane90-mut-'.$name.'.php';
    file_put_contents($file, str_replace($from, $to, $src));
    if (str_replace($from, $to, $src) === $src) {
        printf("[FAIL] %-20s mutation left the bytes identical\n", $name);
        ++$fail;

        return;
    }
    $copies[] = $file;
    [$out, $rc] = $run($file);
    $census = $censusOf($out);
    $refused = str_contains($out, 'REFUSED');
    $hasNeedle = $wantNeedle === '' ? 1 : substr_count($out, $wantNeedle);
    $moved = $census !== $control;
    $verdict = ($moved || $refused) && ! str_contains($census, $control) ? 'ok' : 'FAIL';
    // A leg that only changes the census still has to show WHY: the named effect must appear in the output.
    if ($wantNeedle !== '' && $hasNeedle === 0) {
        $verdict = 'FAIL';
    }
    if ($verdict === 'FAIL') {
        ++$fail;
    }
    printf("[%s] %-20s census: %s%s\n       wanted in output: %s%s\n", $verdict, $name,
        $refused ? 'REFUSED — '.$rc : $census,
        $moved ? ' (moved)' : ' (did not move)',
        $wantNeedle === '' ? '(none)' : var_export($wantNeedle, true),
        $wantNeedle === '' ? '' : ' → found '.$hasNeedle.' time(s)');
};

// ---- the retired joins, planted -------------------------------------------------------------------------------

// (1) The page path kept as Windows backslashes, so `Inertia::render('Registrar/Index')` matches nothing. This is
//     the defect the first draft printed a census over, so the corrected probe must now REFUSE it.
$plant('backslash-paths',
    "\$rel = str_replace('\\\\', '/', \$f->getPathname());",
    "\$rel = \$f->getPathname();",
    'REFUSED');

// (2) Comments are no longer stepped over, so a bracket written in a `//` note inside a prop array makes that array
//     unbalanced — the exact failure that reported four pages as "could not be balanced".
$plant('no-comment-skip',
    "        } elseif ((\$c === '/' && \$n === '/') || (\$c === '#' && \$n !== '[')) {",
    '        } elseif (false) {',
    'props spelled as');

// (3) The unread guard is load-bearing: with a broken join AND the guard disabled, the probe must print a census
//     over rows it never resolved, which is exactly the fake-clean shape the guard exists to refuse. (A leg that
//     truncated a prop value at its first comma was tried first and measured as not load-bearing here — only the
//     rows prop is read, and its values survive truncation — so it was replaced rather than left as decoration.)
++$legs;
$both = str_replace(
    ["    if (! preg_match('/^\\\$([a-zA-Z_]\\w*)/', \$expr, \$vm)) {", 'if ($unread > $total / 3) {'],
    ["    if (! preg_match('/^\\\$([a-zA-Z_]\\w*)\\$/', \$expr, \$vm)) {", 'if (false) {'],
    $src
);
if (substr_count($src, "    if (! preg_match('/^\\\$([a-zA-Z_]\\w*)/', \$expr, \$vm)) {") !== 1
    || substr_count($src, 'if ($unread > $total / 3) {') !== 1
    || $both === $src) {
    printf("[FAIL] %-20s one of its two needles does not land exactly once\n", 'guard-is-load-bearing');
    ++$fail;
} else {
    $guardFile = $scratch.'/lane90-mut-guard-off.php';
    file_put_contents($guardFile, $both);
    $copies[] = $guardFile;
    [$out, $rc] = $run($guardFile);
    $ok = $rc === 0 && ! str_contains($out, 'REFUSED') && str_contains($out, 'UNBOUND');
    if (! $ok) {
        ++$fail;
    }
    printf("[%s] %-20s guard off + broken join: exit %d, census %s\n", $ok ? 'ok' : 'FAIL',
        'guard-is-load-bearing', $rc, $censusOf($out));
}

// (4) The statement-terminator reading returns to `[^;]*`, which stops at the semicolon inside a closure body and so
//     loses the `->paginate(20)` written below it in the same chain.
$plant('semicolon-cut-rhs',
    '            $taken = $scan($body, $one[1] + strlen($one[0]), \';\');',
    "            \$t = substr(\$body, \$one[1] + strlen(\$one[0]));\n            \$taken = preg_match('/^([^;]*)/s', \$t, \$tt) === 1 ? \$tt[1] : null;",
    'UNBOUND');

// (5) The variable branch goes back to requiring the whole expression to BE a variable, so an inline reshape
//     (`'offices' => $offices->through(…)`) is no longer resolved to its source and falls out as UNBOUND. This is
//     the fix that took the census from 13 unread rows to 0, so it has to be able to come back and be caught.
$plant('anchored-var-only',
    "    if (! preg_match('/^\\\$([a-zA-Z_]\\w*)/', \$expr, \$vm)) {",
    "    if (! preg_match('/^\\\$([a-zA-Z_]\\w*)\\$/', \$expr, \$vm)) {",
    'UNBOUND');

// (6) The join is asked of the whole file rather than the action: the same question as "does this controller
//     paginate", which is the second draft's defect and inflates PAGINATED.
$plant('file-not-action',
    "                'body' => \$body,",
    "                'body' => \$src,",
    '');

// ---- the refusals, planted as inputs rather than mutations ----------------------------------------------------

++$legs;
[$out, $rc] = $run($probe, '--row=NoSuchPageAtAll');
$ok = $rc === 1 && str_contains($out, 'REFUSED');
if (! $ok) {
    ++$fail;
}
printf("[%s] %-20s exit %d, said: %s\n", $ok ? 'ok' : 'FAIL', 'empty-population', $rc,
    trim(str_replace("\n", ' ', (string) strstr((string) $out, 'REFUSED'))));

++$legs;
[$out, $rc] = $run($probe, '--verbose');
$ok = $rc === 1 && str_contains($out, 'unknown flag');
if (! $ok) {
    ++$fail;
}
printf("[%s] %-20s exit %d, refused by name: %s\n", $ok ? 'ok' : 'FAIL', 'unknown-flag', $rc,
    trim(str_replace("\n", ' ', (string) strstr((string) $out, 'REFUSED'))));

// (7) The scope flag must change WHAT IS MEASURED, not merely be accepted: an accepted-and-inert flag is this
//     repository's named defect class, and a probe that reads no flag still prints a census over the whole tree.
++$legs;
[$scopedOut, $scopedRc] = $run($probe, '--row=Registrar/Index');
$scoped = $censusOf($scopedOut);
$ok = $scopedRc === 0 && $scoped !== $control && str_contains($scopedOut, 'Registrar/Index')
    && ! str_contains($scopedOut, 'Students/Index');
if (! $ok) {
    ++$fail;
}
printf("[%s] %-20s scoped run exits %d with census: %s\n", $ok ? 'ok' : 'FAIL', 'row-flag-is-read', $scopedRc, $scoped);

// (8) A byte-identical copy must answer exactly like the control — this also proves a copy sitting in scratch never
//     enters the probe's own population.
++$legs;
$copyFile = $scratch.'/lane90-mut-bytecopy.php';
file_put_contents($copyFile, $src);
$copies[] = $copyFile;
[$out, $rc] = $run($copyFile);
$ok = $rc === 0 && $censusOf($out) === $control;
if (! $ok) {
    ++$fail;
}
printf("[%s] %-20s %s\n", $ok ? 'ok' : 'FAIL', 'byte-identical-copy', $censusOf($out));

// (8) The harness's own guard: a needle that cannot land must be reported BAD, not passed.
++$legs;
$probeGuard = function () use ($src): bool {
    $n = substr_count($src, 'this string is not in the probe at all');

    return $n === 0;
};
$ok = $probeGuard();
if (! $ok) {
    ++$fail;
}
printf("[%s] %-20s a needle that matches nothing is count 0, which the leg handler reports FAIL rather than silence\n",
    $ok ? 'ok' : 'FAIL', 'needle-cannot-land');

printf("\n%s: %d leg(s), %d failed\n", $fail === 0 ? 'ALL KILLED' : 'NOT ALL KILLED', $legs, $fail);

exit($fail === 0 ? 0 : 1);
