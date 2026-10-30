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
    $ambiguous = preg_match('/AMBIGUOUS (\d+)/', $out, $bm) === 1 ? (int) $bm[1] : null;
    $notBuilt = preg_match('/NOT-BUILT (\d+)/', $out, $cm) === 1 ? (int) $cm[1] : null;

    return [
        'out' => $out,
        'rows' => $rows,
        'ok' => $ok,
        'noArtifact' => $noArtifact,
        'ambiguous' => $ambiguous,
        'notBuilt' => $notBuilt,
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

/**
 * Rewrite one cell of one gap row, addressed by the row's own id.
 *
 * The Status column is per-row prose that repeats across rows ("Not built" appears in several), so a leg that
 * needle-matched on the value alone would plant in the wrong row or in all of them. These two helpers locate the
 * row by its id and then require the inner needle to be unique INSIDE that row — the same "a needle needs an owner"
 * rule the citation passes follow.
 *
 * @return array{bad: bool, r: ?array, row: ?string}
 */
$plantInRow = static function (string $id, string $from, string $to, string $label, bool $isStatus = false) use ($matrix, $copy, $run): array {
    $lines = file($matrix);
    $hit = null;
    foreach ($lines as $i => $l) {
        if (preg_match('/^\|\s*'.preg_quote($id, '/').'\s*\|/', $l) === 1) {
            $hit = $i;

            break;
        }
    }
    if ($hit === null) {
        fwrite(STDERR, "BAD: {$label} found no {$id} row in the matrix — the register moved and this leg is out of date.\n");

        return ['bad' => true, 'r' => null, 'row' => null];
    }

    $row = rtrim((string) $lines[$hit], "\r\n");
    if ($isStatus) {
        $parts = preg_split('/(?<!\\\\)\|/', $row) ?: [];
        $last = count($parts) - 2;
        if ($last < 2) {
            fwrite(STDERR, "BAD: {$label}: the {$id} row has no Status cell to rewrite.\n");

            return ['bad' => true, 'r' => null, 'row' => $row];
        }
        $parts[$last] = ' '.$to.' ';
        $lines[$hit] = implode('|', $parts)."\n";
    } else {
        $n = substr_count($row, $from);
        if ($n !== 1) {
            fwrite(STDERR, "BAD: {$label}'s needle matched {$n} time(s) inside the {$id} row, want 1.\n");

            return ['bad' => true, 'r' => null, 'row' => $row];
        }
        $lines[$hit] = str_replace($from, $to, $row)."\n";
    }

    file_put_contents($copy, implode('', $lines));
    $r = $run('--matrix='.$copy);
    @unlink($copy);

    return ['bad' => false, 'r' => $r, 'row' => $row];
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
    // ↓ Three legs for the Status column the matrix gained on 2026-10-30 (task #43). Adding the column moved the
    // checker's AMBIGUOUS count from 6 to 12 BEFORE the checker was taught to read it — which is the lesson that a
    // register change and a classifier change are two changes, and that a rewritten checker whose complaint count
    // falls must be falsified, not admired. These are the falsifications.
    'status-cell-must-lead' => function () use ($control, $plantInRow) {
        $p = $plantInRow('G36', '', 'See the row above', 'status-cell-must-lead', true);
        if ($p['bad']) {
            return false;
        }
        $r = $p['r'];

        return $r['rows'] === $control['rows']
            && $r['ambiguous'] === $control['ambiguous'] + 1
            && str_contains($r['out'], 'G-36')
            && str_contains($r['out'], 'the Status cell leads with neither verdict');
    },
    'status-header-is-load-bearing' => function () use ($control, $matrix, $copy, $run) {
        // Rename the header of ONLY the table that carries G40, so the column exists but is no longer recognised.
        // G40's prose then decides its verdict positionally and says "Partly built", which no leading-verb pattern
        // matches — the row must fall to AMBIGUOUS. If the header were decoration, nothing would move.
        $lines = file($matrix);
        $rowIdx = null;
        foreach ($lines as $i => $l) {
            if (preg_match('/^\|\s*G40\s*\|/', $l) === 1) {
                $rowIdx = $i;

                break;
            }
        }
        if ($rowIdx === null) {
            fwrite(STDERR, "BAD: no G40 row in the matrix — the register moved and this leg is out of date.\n");

            return false;
        }
        $headIdx = null;
        for ($i = $rowIdx; $i >= 0; $i--) {
            if (str_contains((string) $lines[$i], '| Status |') && str_starts_with(trim((string) $lines[$i]), '| Id |')) {
                $headIdx = $i;

                break;
            }
        }
        if ($headIdx === null) {
            fwrite(STDERR, "BAD: no `| Id | … | Status |` header above the G40 row.\n");

            return false;
        }
        $before = substr_count(implode('', $lines), '| Status |');
        $lines[$headIdx] = str_replace('| Status |', '| Outcome |', (string) $lines[$headIdx]);
        if (substr_count(implode('', $lines), '| Status |') !== $before - 1) {
            fwrite(STDERR, "BAD: the header edit changed the column count by nothing.\n");

            return false;
        }
        file_put_contents($copy, implode('', $lines));
        $r = $run('--matrix='.$copy);
        @unlink($copy);

        // At least one, not exactly one: the header governs a whole table, so every row of it loses its column and
        // this leg's first draft asserted +1 and reported SURVIVED when two moved. The instrument was right and the
        // expectation was arithmetic from intent — the same failure this file records for verifier expectations.
        return $r['rows'] === $control['rows']
            && $r['ambiguous'] !== null && $control['ambiguous'] !== null
            && $r['ambiguous'] >= $control['ambiguous'] + 1
            && str_contains($r['out'], 'G-40');
    },
    'status-column-beats-row-prose' => function () use ($control, $plantInRow) {
        // Put a Built claim in the row's own prose while its Status cell still says "Not built". The verdict must not
        // move: a classifier that read the prose would either claim the artifact check or call the row ambiguous, and
        // either way the column would be decoration.
        $p = $plantInRow('G36', '| A natural key per notice', '| Built first: a natural key per notice', 'status-column-beats-row-prose');
        if ($p['bad']) {
            return false;
        }
        $r = $p['r'];

        return $r['rows'] === $control['rows']
            && $r['notBuilt'] === $control['notBuilt']
            && $r['ambiguous'] === $control['ambiguous']
            && $r['ok'] === $control['ok'];
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
