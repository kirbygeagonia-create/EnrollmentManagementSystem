<?php

/**
 * Lane 70: prove the anchor checker can fail.
 *
 * `lane70-anchor-cites.php` was rewritten on 2026-10-25 to attribute through `docx-cite-attribution.php` instead
 * of inventing its own rules, and its MOVED count fell 47 → 13. A drop that big is exactly what a broken reader
 * looks like: lane 46's first filter checker "cleared the tree" because it read the wrong construct, and lane 50's
 * passed every real call because it only matched the shape it had just fixed. So the proof is not the count — it is
 * that the tool still NAMES the drift when a drift is planted, and still goes SILENT when a pointer is corrected.
 *
 * Both legs find their citation in the current document (`--show-ok` and the tool's own MOVED line) rather than
 * hardcoding a number, because a hardcoded needle decays the first time a pass re-points it — that is how lane 32's
 * parenthesis leg died, and a mutant whose needle matches nothing reports the tool's clean output as its own
 * failure.
 *
 *   php storage/app/scratch/mutate-lane70-anchor.php
 */
$root = dirname(__DIR__, 3);
$php = PHP_BINARY;
$tool = $root.'/storage/app/scratch/lane70-anchor-cites.php';
$dumpPath = $root.'/storage/app/scratch/docx-flat.txt';
$tmp = $root.'/storage/app/scratch/mut-lane70-anchor';
if (! is_dir($tmp)) {
    mkdir($tmp, 0777, true);
}

$run = static function (array $args) use ($php, $tool): array {
    $cmd = escapeshellarg($php).' '.escapeshellarg($tool);
    foreach ($args as $a) {
        $cmd .= ' '.escapeshellarg($a);
    }
    $out = [];
    exec($cmd.' 2>&1', $out, $rc);

    return [$rc, implode("\n", $out)];
};

$parse = static function (string $out): ?array {
    if (preg_match('/(\d+) bare pointer\(s\) judged: (\d+) OK, (\d+) MOVED, (\d+) NO-ANCHOR, (\d+) UNBOUND.*, (\d+) BLIND/', $out, $m) !== 1) {
        return null;
    }

    return ['read' => (int) $m[1], 'ok' => (int) $m[2], 'moved' => (int) $m[3],
        'noanchor' => (int) $m[4], 'unbound' => (int) $m[5], 'blind' => (int) $m[6]];
};

$dump = (string) file_get_contents($dumpPath);
$fails = 0;
$report = static function (string $label, bool $pass, string $note = '') use (&$fails): void {
    if ($pass) {
        printf("PASS   %-30s %s\n", $label, $note);

        return;
    }
    printf("FAIL   %-30s %s\n", $label, $note);
    $fails++;
};

// Rewrite one bare token inside the dump line that names a given file, and refuse to pretend when it cannot land.
$moveToken = static function (string $dump, string $fileBase, string $token, int $newLine): ?string {
    $new = $newLine >= 0 ? ':'.$newLine : '';
    $hits = 0;
    $lines = explode("\n", $dump);
    foreach ($lines as $i => $raw) {
        if (! str_contains($raw, $fileBase) || substr_count($raw, $token) === 0) {
            continue;
        }
        $at = strpos($raw, $token);
        $after = substr($raw, $at + strlen($token), 1);
        if ($after !== '' && ctype_digit($after)) {
            continue;   // `:9` is the head of `:97`; a token is only itself when no digit follows
        }
        $lines[$i] = substr($raw, 0, $at).$new.substr($raw, $at + strlen($token));
        $hits++;
        break;
    }

    return $hits === 1 ? implode("\n", $lines) : null;
};

[$rc, $out] = $run([]);
$base = $parse($out);
if ($base === null || str_contains($out, 'Warning:')) {
    $report('control', false, 'the control did not answer with a parseable summary (or printed a PHP warning)');
    exit(1);
}
$report('control', true, sprintf('%d read, %d OK, %d MOVED, %d NO-ANCHOR, %d UNBOUND, %d BLIND (exit %d)',
    $base['read'], $base['ok'], $base['moved'], $base['noanchor'], $base['unbound'], $base['blind'], $rc));

[$rc, $okOut] = $run(['--show-ok']);
$okRows = [];
foreach (explode("\n", $okOut) as $l) {
    if (preg_match('/^OK\s+(\S+)\s+:(\d+)(?:-(\d+))?\s+holds "([^"]+)"/', $l, $m) === 1) {
        $okRows[] = ['file' => $m[1], 'from' => (int) $m[2], 'to' => (int) ($m[3] ?? $m[2]), 'name' => $m[4]];
    }
}
$report('show-ok-is-read', $okRows !== [] && $okRows[0]['from'] > 0, count($okRows).' correct pairings the harness can mutate');

// LEG: move a pointer the tool currently judges CORRECT onto a line that does not hold its identifier.
$moved = null;
if ($okRows !== []) {
    $candidate = null;
    foreach ($okRows as $o) {
        $found = [];
        foreach (['app', 'resources/js', 'routes', 'tools', 'database'] as $dir) {
            if (! is_dir($root.'/'.$dir)) {
                continue;
            }
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile() && $f->getFilename() === $o['file']) {
                    $found[] = str_replace('\\', '/', $f->getPathname());
                }
            }
        }
        if ($found === []) {
            continue;
        }
        $fl = file($found[0]);
        // A target line that exists, is not blank, and does not contain the identifier: the drift class itself.
        for ($t = count($fl) - 1; $t >= 1; $t--) {
            if (trim($fl[$t]) === '' || str_contains($fl[$t], $o['name'])) {
                continue;
            }
            if ($t + 1 === $o['from'] || $t + 1 === $o['to']) {
                continue;
            }
            $moved = ['o' => $o, 'file' => $found[0], 'to' => $t + 1];

            break;
        }
        if ($moved !== null) {
            break;
        }
    }
}
if ($moved === null) {
    $report('planted-wrong-target', false, 'no correct pairing could be relocated — the leg cannot be built');
} else {
    $token = ':'.$moved['o']['from'].($moved['o']['to'] > $moved['o']['from'] ? '-'.$moved['o']['to'] : '');
    $mutated = $moveToken($dump, $moved['o']['file'], $token, $moved['to']);
    if ($mutated === null) {
        $report('planted-wrong-target', false, "the needle {$token} did not land on exactly one line naming {$moved['o']['file']}");
    } else {
        $f = $tmp.'/wrong-target.txt';
        file_put_contents($f, $mutated);
        [$rc2, $out2] = $run(['--dump='.$f]);
        $b2 = $parse($out2);
        $namesIt = $b2 !== null && str_contains($out2, 'MOVED')
            && preg_match('/MOVED\s+\S*\s*:'.$moved['to'].'-/', $out2) === 1;
        $report('planted-wrong-target', $namesIt && $b2 !== null && $b2['moved'] === $base['moved'] + 1,
            sprintf('%s%s moved onto line %d — MOVED %d → %d', $moved['o']['file'], $token, $moved['to'],
                $base['moved'], $b2['moved'] ?? -1));
    }
}

// LEG: give a row the tool says is WRONG its own true line, and require it to stop being wrong.
$trueLine = null;
foreach (explode("\n", $out) as $l) {
    if (preg_match('/^MOVED\s+(\S+)\s+:(\d+)(?:-(\d+))?\s+but "([^"]+)" is at (\d+)/', $l, $m) === 1) {
        $trueLine = ['file' => $m[1], 'from' => (int) $m[2], 'to' => (int) ($m[3] ?? $m[2]), 'name' => $m[4], 'truth' => (int) $m[5]];

        break;
    }
}
if ($trueLine === null) {
    $report('planted-correct-goes-quiet', false, 'the tool printed no MOVED row to repair');
} else {
    $token = ':'.$trueLine['from'].($trueLine['to'] > $trueLine['from'] ? '-'.$trueLine['to'] : '');
    $mutated = $moveToken($dump, $trueLine['file'], $token, $trueLine['truth']);
    if ($mutated === null) {
        $report('planted-correct-goes-quiet', false, "the needle {$token} did not land on exactly one line naming {$trueLine['file']}");
    } elseif ($mutated === $dump) {
        $report('planted-correct-goes-quiet', false, 'the mutation left the bytes identical — nothing was planted');
    } else {
        $f = $tmp.'/repaired.txt';
        file_put_contents($f, $mutated);
        [$rc3, $out3] = $run(['--dump='.$f]);
        $b3 = $parse($out3);
        $still = $b3 !== null && preg_match('/MOVED\s+\S*\s*:'.$trueLine['truth'].'-/', $out3) === 1;
        $report('planted-correct-goes-quiet', $b3 !== null && $b3['moved'] === $base['moved'] - 1 && ! $still,
            sprintf('%s %s → :%d — MOVED %d → %d', $trueLine['file'], $token, $trueLine['truth'],
                $base['moved'], $b3['moved'] ?? -1));
    }
}

// LEG: a byte-identical copy must change no verdict (a harness that mutates by accident reports its own edit).
$f = $tmp.'/identical.txt';
file_put_contents($f, $dump);
[$rc4, $out4] = $run(['--dump='.$f]);
$b4 = $parse($out4);
$report('byte-identical-copy', $b4 === $base, $b4 === null ? 'no summary' : sprintf('MOVED %d, OK %d (control: %d, %d)',
    $b4['moved'], $b4['ok'], $base['moved'], $base['ok']));

// LEG: a dump that is long enough to be read but holds nothing attributable must REFUSE with the judges-nothing
// guard, not the size floor. Both exit 1, so the leg is split by message — a refusal proved by its status alone is
// a leg that passes on a broken instrument (lane 43, lane 69's owed table, and this harness's own first run).
$tiny = "P 1: this page cites no line of code at all.\n"
    .'ROWS: `PrintService.php` and `AccountingController.php` are named, and no continuation follows either. '
    .str_repeat('Nothing here can be judged, and a checker that judges nothing must say so. ', 20)."\n";
$f = $tmp.'/no-pointers.txt';
file_put_contents($f, $tiny);
[$rc5, $out5] = $run(['--dump='.$f]);
$report('judges-nothing-refuses',
    $rc5 === 1 && str_contains($out5, 'REFUSED') && str_contains($out5, 'judged') && ! str_contains($out5, 'dump-docx-flat'),
    strtok($out5, "\n") ?: 'exit '.$rc5);

// LEG: no dump at all — the other refusal, proved by its own message.
[$rc6, $out6] = $run(['--dump='.$tmp.'/does-not-exist.txt']);
$report('missing-dump-refuses', $rc6 === 1 && str_contains($out6, 'REFUSED') && str_contains($out6, 'dump-docx-flat'),
    strtok($out6, "\n") ?: 'exit '.$rc6);

// LEGS on the QUEUE printing. The footer used to claim UNBOUND and BLIND were printed while `$limit = 0` made every
// print condition false — so a 110-row human queue existed only as a number. These three legs are what makes that
// claim true: the rows must arrive, a cap must SAY it capped, and a branch that tallies without writing must stop
// the run.
$b7 = $parse($out);
$unboundLines = preg_match_all('/^UNBOUND    \S/m', $out);
$blindLines = preg_match_all('/^BLIND      /m', $out);
$report('queue-rows-arrive', $b7 !== null && $unboundLines === $b7['unbound'] && $blindLines === $b7['blind'],
    $b7 === null ? 'no summary' : sprintf('%d UNBOUND and %d BLIND line(s) written against a tally of %d + %d',
        $unboundLines, $blindLines, $b7['unbound'], $b7['blind']));

[$rc8, $out8] = $run(['--limit=5']);
// `^UNBOUND ` also matches the footer's own sentence, so a row is recognised by the run of spaces the printf pads.
$capLines = preg_match_all('/^(?:UNBOUND {4}|BLIND {6})\S/m', $out8);
$report('cap-says-it-capped', $rc8 === 1 && $capLines === 5 && str_contains($out8, 'INCOMPLETE')
    && str_contains($out8, 'withheld'),
    sprintf('%d row(s) under --limit=5; footer says %s', $capLines,
        str_contains($out8, 'INCOMPLETE') ? 'INCOMPLETE' : 'nothing about a cap'));

// The invariant is proved against a COPY of the tool with one printer switched off, because the defect it guards
// against is a branch of the tool itself. The copy sits beside the real file: `dirname(__DIR__, 3)` from a
// sub-directory resolves outside the repository and the require of the shared module fails there.
$copy = $root.'/storage/app/scratch/mut-lane70-anchor-toolcopy.php';
$toolLines = (array) file($tool);
$guardAt = null;
foreach ($toolLines as $i => $row) {
    if (str_contains((string) $row, '$mayPrint()') && str_contains((string) ($toolLines[$i + 1] ?? ''), 'the sentence names no construct at all')) {
        $guardAt = $i;

        break;
    }
}
if ($guardAt === null) {
    $report('silent-bucket-refuses', false, 'REFUSED TO RUN: the queue printer could not be found in the tool, so the invariant is unproved');
} else {
    $toolLines[$guardAt] = str_replace('$mayPrint()', 'false', (string) $toolLines[$guardAt]);
    file_put_contents($copy, implode('', $toolLines));
    $cmd = escapeshellarg($php).' '.escapeshellarg($copy).' 2>&1';
    $out9 = [];
    exec($cmd, $out9, $rc9);
    $out9 = implode("\n", $out9);
    $report('silent-bucket-refuses', $rc9 === 1 && str_contains($out9, 'REFUSED')
        && str_contains($out9, 'queue row(s)'), strtok($out9, "\n") ?: 'exit '.$rc9);
    unlink($copy);
}

array_map('unlink', glob($tmp.'/*') ?: []);
@rmdir($tmp);

printf("\n%s\n", $fails === 0
    ? 'The anchor checker moves when a pointer is planted wrong, goes quiet when one is repaired, and refuses when it reads nothing.'
    : "{$fails} leg(s) did not behave — do not trust this checker's silence.");
exit($fails === 0 ? 0 : 1);
