<?php

/**
 * Proves storage/app/scratch/lane94-matrix-claims.php — the auditor that asks whether every "Built" claim in the
 * requirement matrix names something that exists.
 *
 * The matrix is the artifact that answers "is the development prompt fully implemented?", so this checker is the
 * one standing between an invented file name and a green-looking row. It has never been falsified, which is the
 * same position lane 46 was in when it cleared the whole tree after reading the wrong construct.
 *
 * Nothing here remembers a tally. The control is measured in the same run and every later leg is asserted as a
 * DELTA against it, because a hard-coded expectation is a verifier that ages into a broken tool. Likewise the
 * artifact path each leg mutates is read out of the control's OWN output, not written down here — so if the matrix
 * stops carrying that row, the leg reports BAD rather than passing on a needle that matched nothing.
 *
 * Usage: php storage/app/scratch/mutate-lane94-matrix-claims.php [--only=LEG]
 */
$root = dirname(__DIR__, 3);
chdir($root);

$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$tool = 'storage/app/scratch/lane94-matrix-claims.php';
$matrix = $root.'/Documentation/Prompt-Requirement-Matrix-2026-10-26.md';
$workdir = $root.'/storage/app/scratch';

$run = static function (string $args) use ($php, $tool): array {
    $out = (string) shell_exec($php.' '.$tool.' '.$args.' 2>&1');
    // The checker exits 1 both on a refusal and on a missing artifact, so the message — not the status — decides
    // which of the two happened, and every leg asserts on the message it expects.
    $rows = preg_match('/(\d+) gap row\(s\) read/', $out, $rm) === 1 ? (int) $rm[1] : null;
    $ok = preg_match('/OK (\d+), MISSING (\d+)/', $out, $am) === 1 ? [(int) $am[1], (int) $am[2]] : null;
    $noArtifact = preg_match('/NO-ARTIFACT (\d+)/', $out, $nm) === 1 ? (int) $nm[1] : null;

    return [
        'out' => $out,
        'rows' => $rows,
        'ok' => $ok,
        'noArtifact' => $noArtifact,
        'refused' => str_contains($out, 'REFUSED'),
    ];
};

// The control: read the real matrix once, and take both the tallies and the mutated token from its own output.
$control = $run('--all');
if ($control['rows'] === null || $control['ok'] === null) {
    fwrite(STDERR, "BAD: the control printed no row tally and no artifact buckets, so the tool is unreadable — read its output, not this harness.\n".mb_substr($control['out'], 0, 400)."\n");
    exit(1);
}
printf("control: %d gap row(s); artifact OK %d, MISSING %d; NO-ARTIFACT %s\n",
    $control['rows'], $control['ok'][0], $control['ok'][1], var_export($control['noArtifact'], true));

// Pick a token the leg can address uniquely. The first path the checker names may be a fragment repeated in three
// rows (`Registrar/Show.js` was exactly that, and a needle that matches three times is a leg that cannot say which
// row it mutated), so candidates are taken from every ARTIFACT-OK line and the first one occurring exactly once in
// the matrix wins. Nothing here is hardcoded: if the register stops carrying the row, the leg reports BAD.
$matrixSrc = (string) file_get_contents($matrix);
$candidates = [];
foreach (preg_split('/\R/', $control['out']) as $line) {
    if (str_contains($line, 'ARTIFACT-OK')
        && preg_match_all('#([A-Za-z0-9_.\-/]+\.(?:php|js|jsx|md|json|neon))#', $line, $mm) === 1) {
        $candidates[] = $mm[1][0];
    }
}

$realPath = null;
foreach (array_unique($candidates) as $c) {
    if (substr_count($matrixSrc, $c) === 1) {
        $realPath = $c;
        break;
    }
}

if ($realPath === null) {
    fwrite(STDERR, 'BAD: none of the '.count(array_unique($candidates))." artifact path(s) the control named occurs exactly once in the matrix, so the invented-file leg has nothing it can address.\n");
    exit(1);
}
echo 'control names the artifact: '.$realPath."\n";

$copy = $workdir.'/mutate-lane94-copy.md';

/** Plant one text change into a copy of the matrix and answer the checker's verdict over it. */
$plant = static function (string $from, string $to, string $label) use ($matrix, $copy, $run): array {
    $src = (string) file_get_contents($matrix);
    $hits = substr_count($src, $from);
    if ($hits !== 1) {
        fwrite(STDERR, "BAD: {$label}'s needle matched {$hits} time(s), expected exactly 1.\n");

        return ['bad' => true, 'r' => null];
    }
    file_put_contents($copy, str_replace($from, $to, $src));
    $r = $run('--matrix='.$copy);
    @unlink($copy);

    return ['bad' => false, 'r' => $r];
};

$legs = [
    'invented-artifact' => function () use ($control, $realPath, $plant) {
        $p = $plant($realPath, 'tests/Feature/NeverWritten/NeverWrittenTest.php', 'invented-artifact');
        if ($p['bad']) {
            return false;
        }
        $r = $p['r'];
        $moved = $r['ok'] !== null && $r['ok'][1] === $control['ok'][1] + 1 && $r['ok'][0] === $control['ok'][0] - 1;

        return $moved && $r['refused'] && str_contains($r['out'], 'names a file the repository does not hold');
    },
    'artifact-unnamed' => function () use ($control, $realPath, $plant) {
        // Drop the path from a Built row by replacing it with prose: the row must move out of ARTIFACT-OK rather
        // than vanish, because a checker that stops counting a row is a checker reporting a clean register.
        $p = $plant($realPath, 'the file named in the row above', 'artifact-unnamed');
        if ($p['bad']) {
            return false;
        }
        $r = $p['r'];

        return $r['ok'] !== null && $r['ok'][0] === $control['ok'][0] - 1 && $r['rows'] === $control['rows'];
    },
    'no-rows-refuses' => function () use ($matrix, $copy, $run) {
        // This leg deliberately mutates EVERY occurrence: the register is read through its `Id` headers, so
        // renaming all of them must make the tool refuse rather than print a census over nothing it read.
        $src = (string) file_get_contents($matrix);
        $hits = substr_count($src, '| Id |');
        if ($hits < 2) {
            fwrite(STDERR, "BAD: only {$hits} `| Id |` header(s) exist, so the leg cannot strip the population.\n");

            return false;
        }
        file_put_contents($copy, str_replace('| Id |', '| Number |', $src));
        $r = $run('--matrix='.$copy);
        @unlink($copy);

        return $r['refused'] && str_contains($r['out'], 'not one gap row was read');
    },
    'unknown-flag' => function () use ($run) {
        $r = $run('--verbo=1');

        return $r['refused'] && str_contains($r['out'], '--verbo');
    },
    'missing-matrix' => function () use ($run) {
        $r = $run('--matrix=Documentation/No-Such-Matrix.md');

        return $r['refused'] && str_contains($r['out'], 'no matrix at');
    },
    'control-copy' => function () use ($control, $matrix, $copy, $run) {
        copy($matrix, $copy);
        $r = $run('--matrix='.$copy);
        @unlink($copy);

        return $r['rows'] === $control['rows'] && $r['ok'] === $control['ok'] && ! $r['refused'];
    },
];

register_shutdown_function(static function () use ($copy): void {
    if (is_file($copy)) {
        @unlink($copy);
    }
});

$only = null;
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--only=(.*)$/', $a, $m) === 1) {
        $only = $m[1];
    } else {
        fwrite(STDERR, "REFUSED: unknown argument {$a}; this harness reads only --only=.\n");
        exit(1);
    }
}

$bad = 0;
$ran = 0;
foreach ($legs as $name => $leg) {
    if ($only !== null && $only !== $name) {
        continue;
    }
    $ran++;
    $killed = false;
    try {
        $killed = (bool) $leg();
    } catch (Throwable $e) {
        fwrite(STDERR, "BAD: {$name} threw ".$e->getMessage()."\n");
    }
    echo ($killed ? '  killed ' : '  SURVIVED ').$name."\n";
    if (! $killed) {
        $bad++;
    }
}

printf("\n%s: %d leg(s) run, %d bad (control: %d rows, %d artifacts OK)\n",
    $bad === 0 && $ran > 0 ? 'ALL KILLED' : 'FAILURES', $ran, $bad, $control['rows'], $control['ok'][0]);

exit($bad === 0 && $ran > 0 ? 0 : 1);
