<?php

/**
 * Falsifier for `storage/app/scratch/lane217-verify-actor-figures.mjs`.
 *
 * The verifier reads three surfaces (model.json, manifest.md, the index page) and every one of them is
 * addressable, so each defect below is planted in a THROWAWAY COPY — the deliverables are never edited.
 * A leg is satisfied only when the named complaint appears AND the run exits 1; a leg whose needle matches
 * nothing reports BAD rather than passing, because an anchor that has aged wears the costume of a guard.
 */

$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$root = getcwd();
if (! is_dir($root.'/Documentation/Diagrams')) {
    fwrite(STDERR, "REFUSED: this harness must run from the repository root (no Documentation/Diagrams here)\n");
    exit(1);
}
$verifier = 'storage/app/scratch/lane217-verify-actor-figures.mjs';
if (! is_file($verifier)) {
    fwrite(STDERR, "REFUSED: the verifier [$verifier] is missing, so there is nothing to falsify\n");
    exit(1);
}

$model = file_get_contents('Documentation/Diagrams/model.json');
$manifest = file_get_contents('Documentation/Diagrams/manifest.md');
$index = file_get_contents('Documentation/Use-Case-Diagrams.md');
foreach (['model' => $model, 'manifest' => $manifest, 'index' => $index] as $label => $text) {
    if (! is_string($text) || $text === '') {
        fwrite(STDERR, "REFUSED: the $label surface read empty, so every later leg measures nothing\n");
        exit(1);
    }
}

$copies = [];
$cleanup = function () use (&$copies) {
    foreach ($copies as $p) {
        if (is_file($p)) {
            unlink($p);
        }
    }
    $copies = [];
};
register_shutdown_function($cleanup);

$write = function (string $name, string $body) use (&$copies) {
    $p = 'storage/app/scratch/lane217-mutant-'.$name;
    file_put_contents($p, $body);
    if (filesize($p) !== strlen($body)) {
        fwrite(STDERR, "REFUSED: the plant [$p] did not land byte-for-byte\n");
        exit(1);
    }
    $copies[] = $p;

    return $p;
};

$run = function (array $args) {
    $cmd = 'node '.escapeshellarg('storage/app/scratch/lane217-verify-actor-figures.mjs');
    foreach ($args as $a) {
        $cmd .= ' '.escapeshellarg($a);
    }
    $out = [];
    $status = 0;
    exec($cmd.' 2>&1', $out, $status);

    return ['text' => implode("\n", $out), 'code' => $status];
};

$bad = 0;
$legs = 0;
$expect = function (string $name, array $res, string $needle, bool $wantFail = true) use (&$bad, &$legs) {
    $legs++;
    $okCode = $wantFail ? ($res['code'] === 1) : ($res['code'] === 0);
    if (! str_contains($res['text'], $needle)) {
        echo str_pad($name, 40)."BAD   no such line: {$needle}\n";
        echo '        '.str_replace("\n", "\n        ", substr($res['text'], -400))."\n";
        $bad++;

        return;
    }
    if (! $okCode) {
        echo str_pad($name, 40)."BAD   matched the complaint but exited {$res['code']}\n";
        $bad++;

        return;
    }
    echo str_pad($name, 40)."ok    exit {$res['code']}\n";
};

// ---------------------------------------------------------------- 1. control
$control = $run([]);
$expect('control', $control, 'AGREES:', false);

// ---------------------------------------------------------------- 2. a use case left edgeless
// Delete one association whose far end has no other edge at all; the verifier must call it an island.
$mj = json_decode($model, true);
$degree = [];
foreach ($mj['edges'] as $e) {
    $degree[$e['from']] = ($degree[$e['from']] ?? 0) + 1;
    $degree[$e['to']] = ($degree[$e['to']] ?? 0) + 1;
}
$kindOf = [];
foreach ($mj['nodes'] as $n) {
    $kindOf[$n['id']] = $n['kind'];
}
$victim = null;
foreach ($mj['edges'] as $i => $e) {
    if ($e['kind'] !== 'association') {
        continue;
    }
    $case = $kindOf[$e['from']] === 'usecase' ? $e['from'] : ($kindOf[$e['to']] === 'usecase' ? $e['to'] : null);
    if ($case !== null && ($degree[$case] ?? 0) === 1) {
        $victim = ['i' => $i, 'id' => $case];
        break;
    }
}
if ($victim === null) {
    echo str_pad('island-found', 40)."BAD   no use case hangs off exactly one association — re-cut this leg\n";
    $bad++;
    $legs++;
} else {
    $cut = $mj['edges'][$victim['i']];
    unset($mj['edges'][$victim['i']]);
    $mj['edges'] = array_values($mj['edges']);
    $p = $write('model-island.json', json_encode($mj));
    $res = $run(["--model={$p}"]);
    $label = null;
    foreach ($mj['nodes'] as $n) {
        if ($n['id'] === $victim['id']) {
            $label = $n['label'];
        }
    }
    $expect('use-case-left-edgeless', $res, 'AND by no stereotype edge', true);
    if (! str_contains($res['text'], $label)) {
        echo str_pad('  names-the-victim', 40)."BAD   the refusal did not name [$label]\n";
        $bad++;
    } else {
        echo str_pad('  names-the-victim', 40)."ok    [$label] cut at edge index {$victim['i']}\n";
    }
    $cleanup();
}

// ---------------------------------------------------------------- 3. an actor label with no figure
$actorRow = null;
if (preg_match('/^\| `use-case-actor-[a-z0-9-]+\.png` \| Actor: [^|]+\|[^\n]*\n/m', $manifest, $m) === 1) {
    $actorRow = $m[0];
}
if ($actorRow === null) {
    echo str_pad('figure-row-removed', 40)."BAD   the manifest's actor rows are not in the shape this leg plants\n";
    $bad++;
    $legs++;
} else {
    $p = $write('manifest-norow.md', str_replace($actorRow, '', $manifest));
    $res = $run(["--manifest={$p}"]);
    $expect('figure-row-removed', $res, 'names no figure in the manifest', true);
    $cleanup();
}

// ---------------------------------------------------------------- 4. a byte column that no longer matches
if (preg_match('/^\| `(use-case-[a-z0-9-]+\.png)` \| ([^|]+)\| (\d+)×(\d+) \| (\d+) \|$/m', $manifest, $m) === 1) {
    $row = $m[0];
    $bytes = $m[5];
    $drifted = substr($row, 0, -strlen($bytes.' |')).((int) $bytes + 1).' |';
    if ($drifted === $row) {
        echo str_pad('figure-bytes-drifted', 40)."BAD   the plant left the row byte-identical\n";
        $bad++;
        $legs++;
    } else {
        $p = $write('manifest-bytes.md', str_replace($row, $drifted, $manifest));
        $res = $run(["--manifest={$p}", '--figures=Documentation/Diagrams']);
        $expect('figure-bytes-drifted', $res, 'bytes where the manifest states', true);
        $cleanup();
    }
} else {
    echo str_pad('figure-bytes-drifted', 40)."BAD   no manifest row in the planted shape — re-cut this leg\n";
    $bad++;
    $legs++;
}

// ---------------------------------------------------------------- 5. the index stops linking a figure
if (preg_match('/\[[^\]]*\]\(([^)]*use-case-actor-[a-z0-9-]+\.png)\)/', $index, $m) === 1) {
    $p = $write('index-unlinked.md', str_replace($m[1], str_replace('use-case-actor', 'use-case-actorX', $m[1]), $index));
    $res = $run(["--index={$p}"]);
    $expect('index-loses-a-link', $res, 'is not linked from', true);
    $cleanup();
} else {
    echo str_pad('index-loses-a-link', 40)."BAD   the index links no actor figure in the planted shape\n";
    $bad++;
    $legs++;
}

// ---------------------------------------------------------------- 6. a stereotype edge loses its word
$mj = json_decode($model, true);
$stereo = null;
foreach ($mj['edges'] as $i => $e) {
    if ($e['kind'] === 'include' && str_contains((string) ($e['label'] ?? ''), '«')) {
        $stereo = $i;
        break;
    }
}
if ($stereo === null) {
    echo str_pad('stereotype-word-stripped', 40)."BAD   no «include» label found to strip\n";
    $bad++;
    $legs++;
} else {
    $mj['edges'][$stereo]['label'] = '';
    $p = $write('model-nostereo.json', json_encode($mj));
    $res = $run(["--model={$p}"]);
    $expect('stereotype-word-stripped', $res, 'carry no «include»/«extend» word', true);
    $cleanup();
}

// ---------------------------------------------------------------- 7. the ledger stops closing
if (preg_match('/model 129 edges, drawn 122, folded as restatement 7/', $manifest) === 1) {
    $p = $write('manifest-ledger.md', str_replace('model 129 edges, drawn 122,', 'model 129 edges, drawn 121,', $manifest));
    $res = $run(["--manifest={$p}"]);
    $expect('edge-ledger-unclosed', $res, 'the edge ledger does not close', true);
    $cleanup();
} else {
    echo str_pad('edge-ledger-unclosed', 40)."BAD   the delivered ledger line has moved; re-cut this leg\n";
    $bad++;
    $legs++;
}

// ---------------------------------------------------------------- 8. the figures surface is read
$tmp = 'storage/app/scratch/lane217-empty-dir';
if (! is_dir($tmp)) {
    mkdir($tmp, 0777, true);
}
$res = $run(["--figures={$tmp}"]);
$expect('figures-dir-refuses', $res, 'listed in the manifest but not on disk', true);
@rmdir($tmp);

// ---------------------------------------------------------------- 9. a byte-identical copy agrees
$p = $write('model-copy.json', $model);
$q = $write('manifest-copy.md', $manifest);
$r = $write('index-copy.md', $index);
$res = $run(["--model={$p}", "--manifest={$q}", "--index={$r}", '--figures=Documentation/Diagrams']);
$expect('identical-copies-agree', $res, 'AGREES:', false);
$cleanup();

// ---------------------------------------------------------------- 10. refusals told apart by message
$res = $run(['--no-such-flag-at-all']);
$expect('unknown-flag-refused', $res, 'flag(s) not read by this tool: --no-such-flag-at-all', true);
$res = $run(['--model=Documentation/Diagrams/no-such-file.json']);
$expect('missing-surface-refused', $res, 'REFUSED: the model surface', true);

// ---------------------------------------------------------------- 11. nothing left behind
$left = glob('storage/app/scratch/lane217-mutant-*') ?: [];
$left[] = is_dir($tmp) ? $tmp : null;
$left = array_values(array_filter($left));
if ($left !== []) {
    echo str_pad('copies-swept', 40).'BAD   still on disk: '.implode(', ', $left)."\n";
    $bad++;
} else {
    echo str_pad('copies-swept', 40)."ok    no mutant surface, no mutant output, no stray directory\n";
}
$legs++;

echo "\n=== verdict ===\n";
echo $bad === 0
    ? "ALL PROVABLE: {$legs} leg(s), 0 bad\n"
    : "BAD: {$bad} of {$legs} leg(s)\n";
exit($bad === 0 ? 0 : 1);
