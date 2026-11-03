<?php

/**
 * Falsifier for the two use-case figure audits (per-actor closure, complete-figure accounting).
 *
 * Both audits print a clean verdict today, which is exactly the state in which a broken audit is invisible: each leg
 * therefore plants the defect the audit is supposed to catch, in a throwaway COPY of the generator, and requires the
 * run to refuse while naming its own reason. A leg that only asserts "exit 1" would also be satisfied by a syntax
 * error, so the reason string is matched too.
 *
 * The copies are `.mjs`, and `storage/app/.gitignore` re-includes scratch by extension — a leftover mutant would
 * travel to the remote as tracked content. Every copy is deleted at the end and the deletion is proved by listing
 * the directory, not by trusting an exit code.
 *
 * Usage: php storage/app/scratch/mutate-lane208-figure-audits.php
 */
$root = dirname(__DIR__, 3);
chdir($root);
$php = PHP_BINARY;
$node = 'node';
$gen = 'storage/app/scratch/lane208-use-case-figures.mjs';
$manifest = 'Documentation/Diagrams/manifest.md';

if (! is_file($gen)) {
    fwrite(STDERR, "REFUSED: {$gen} is not in the tree.\n");
    exit(1);
}
$original = (string) file_get_contents($gen);

$bad = 0;
$legs = 0;
$copies = [];

$cleanup = static function () use (&$copies): void {
    foreach ($copies as $f) {
        if (is_file($f)) {
            unlink($f);
        }
    }
};
register_shutdown_function($cleanup);

$run = static function (string $file, string $flag) use ($root, $node): array {
    $out = [];
    $code = 0;
    exec('cd '.escapeshellarg($root).' && '.$node.' '.escapeshellarg($file).' '.$flag.' 2>&1', $out, $code);

    return [$code, preg_replace('/\e\[[0-9;]*m/', '', implode("\n", $out))];
};

$expect = static function (string $name, string $file, string $flag, int $wantCode, string $needle) use (&$bad, &$legs, $run): void {
    $legs++;
    [$code, $out] = $run($file, $flag);
    if ($code !== $wantCode) {
        echo str_pad($name, 42)."BAD   exit {$code}, wanted {$wantCode}: ".str_replace("\n", ' / ', mb_substr($out, -200))."\n";
        $bad++;

        return;
    }
    if ($needle !== '' && ! str_contains($out, $needle)) {
        echo str_pad($name, 42)."BAD   right exit, but the reason [{$needle}] is not in the output\n";
        $bad++;

        return;
    }
    echo str_pad($name, 42)."ok    exit {$code}".($needle === '' ? '' : ", reason read")."\n";
};

$plant = static function (string $name, string $from, string $to) use ($original, $gen, &$copies, &$bad, &$legs): ?string {
    $legs++;
    $hits = substr_count($original, $from);
    if ($hits !== 1) {
        echo str_pad($name, 42)."BAD   anchor occurs {$hits} time(s), wanted exactly 1 — the leg's age, not a defect\n";
        $bad++;

        return null;
    }
    $file = 'storage/app/scratch/lane208-mutant-'.count($copies).'.mjs';
    file_put_contents($file, str_replace($from, $to, $original));
    $copies[] = $file;
    if ((string) file_get_contents($file) === $original) {
        echo str_pad($name, 42)."BAD   the plant left the copy byte-identical\n";
        $bad++;
        unlink($file);

        return null;
    }

    return $file;
};

echo "=== controls (the audits as delivered) ===\n";
$manifestBefore = md5((string) file_get_contents($manifest));
$expect('control-actor-closure', $gen, '--audit-actor-edges', 0, 'AUDIT CLEAN: every dependency edge');
$expect('control-complete-figure', $gen, '--audit-complete-figure', 0, 'AUDIT CLEAN: the complete figure');

$legs++;
if (md5((string) file_get_contents($manifest)) !== $manifestBefore) {
    echo str_pad('control-no-render-side-effect', 42)."BAD   an audit run rewrote the manifest — it must stop before rendering\n";
    $bad++;
} else {
    echo str_pad('control-no-render-side-effect', 42)."ok    the audits write nothing\n";
}

$legs++;
[$code, $out] = $run($gen, '--audit-nonsense-flag');
if ($code === 0 || ! str_contains($out, 'REFUSED')) {
    echo str_pad('unknown-flag-refused', 42)."BAD   exit {$code}; an unknown flag must be refused by name\n";
    $bad++;
} else {
    echo str_pad('unknown-flag-refused', 42)."ok    exit {$code}, refused by name\n";
}

echo "\n=== planted defects ===\n";
// (1) the actor closure sweep removed entirely: owed include/extend chains stop being drawn.
if (($f = $plant('actor-closure-removed', '  for (let grew = true; grew;) {', '  for (let grew = false; grew;) {')) !== null) {
    $expect('actor-closure-removed', $f, '--audit-actor-edges', 1, 'the model owes an actor figure were not drawn');
}
// (2) one use case taken out of the complete figure's population: a distinct shape no longer drawn.
// The anchor is taken from the emitter's OWN bytes by shape, not from a literal remembered from last week: the call
// line gained its layout arguments on 2026-11-03 and the three legs below reported their own age as a defect.
$legs++;
if (! preg_match('/(const built = emitGraph\(\[\.\.\.nodes\.values\(\)\], edges, \'SYS\'[^\n]*;)/', $original, $am)) {
    echo str_pad('complete-figure-anchor', 42)."BAD   the complete figure's emitGraph call was not found by shape\n";
    $bad++;
    $nodeFrom = null;
} else {
    $nodeFrom = $am[1];
    echo str_pad('complete-figure-anchor', 42)."ok    anchored by shape: ".substr($nodeFrom, 0, 58)."…\n";
}
if ($nodeFrom !== null) {
    $nodeTo = str_replace('[...nodes.values()]',
        "[...nodes.values()].filter((n) => !(n.kind !== 'actor' && n.label === [...nodes.values()].find((x) => x.kind !== 'actor').label))",
        $nodeFrom);
    $edgeTo = str_replace(', edges,', ', edges.slice(1),', $nodeFrom);
    if (str_contains($nodeTo, '.filter(') && str_contains($edgeTo, 'edges.slice(1)')) {
        if (($f = $plant('complete-figure-loses-a-node', $nodeFrom, $nodeTo)) !== null) {
            $expect('complete-figure-loses-a-node', $f, '--audit-complete-figure', 1, 'a use case or an actor is absent');
        }
        // (3) one edge never handed to the emitter: the ledger must stop adding up.
        if (($f = $plant('complete-figure-loses-an-edge', $nodeFrom, $edgeTo)) !== null) {
            $expect('complete-figure-loses-an-edge', $f, '--audit-complete-figure', 1, 'never reached the emitter');
        }
    }
}
// (4) the same lost edge WITH the ledger check blinded: the run must go quiet, which is what proves the guard is the
// only thing that sees this defect. A leg asserting the refusal alone would pass on an audit that refuses everything.
$guardFrom = "if (completeReport.edges + completeReport.dropped.length !== edges.length) {";
$legs++;
if ($nodeFrom !== null && substr_count($original, $nodeFrom) === 1 && substr_count($original, $guardFrom) === 1) {
    $mutant = str_replace($nodeFrom, $edgeTo, $original);
    $mutant = str_replace($guardFrom, 'if (false) {', $mutant);
    $f4 = 'storage/app/scratch/lane208-mutant-blinded-ledger.mjs';
    file_put_contents($f4, $mutant);
    $copies[] = $f4;
    [$code, $out] = $run($f4, '--audit-complete-figure');
    // Which edge is first in the list decides how the numbers move, so the assertion is the relation, not a
    // remembered figure: the ledger must visibly fail to close while the run stays quiet.
    $closes = null;
    if (preg_match('/model (\d+) edges, drawn (\d+), folded as restatement (\d+) and as merged endpoints (\d+)/', $out, $mm) === 1) {
        $closes = ((int) $mm[2] + (int) $mm[3] + (int) $mm[4]) === (int) $mm[1];
    }
    if ($code !== 0 || $closes !== false) {
        echo str_pad('lost-edge-invisible-with-guard-blinded', 42).'BAD   exit '.$code.', ledger closes='.var_export($closes, true)
            ."; expected a QUIET run whose ledger does not add up\n";
        $bad++;
    } else {
        echo str_pad('lost-edge-invisible-with-guard-blinded', 42)."ok    the defect survives only because the ledger was blinded ({$mm[1]} model, {$mm[2]} drawn, {$mm[3]}+{$mm[4]} folded)\n";
    }
} else {
    echo str_pad('lost-edge-invisible-with-guard-blinded', 42)."BAD   anchors not unique — the leg's age, not a defect\n";
    $bad++;
}
// (5) byte-identical copy: a re-run of the delivered generator must change no verdict.
$legs++;
$same = 'storage/app/scratch/lane208-mutant-identical.mjs';
file_put_contents($same, $original);
$copies[] = $same;
[$code, $out] = $run($same, '--audit-complete-figure');
if ($code !== 0 || ! str_contains($out, 'AUDIT CLEAN')) {
    echo str_pad('identical-copy-agrees', 42)."BAD   exit {$code}: the delivered readings must survive a copy\n";
    $bad++;
} else {
    echo str_pad('identical-copy-agrees', 42)."ok    the same accounting on a byte-identical copy\n";
}

echo "\n=== the index that links the figures ===\n";
// (5) the delivered state: every PNG on disk is linked by the generated index, and every link has a file. This is
// the check the figures failed before the index existed — 43 images sat in a folder no document pointed at.
$legs++;
$diskPng = array_values(array_filter(scandir('Documentation/Diagrams'), static fn (string $f): bool => str_ends_with($f, '.png')));
$diskPng = array_values(array_filter($diskPng, static fn (string $f): bool => str_starts_with($f, 'use-case-')));
$index = (string) file_get_contents('Documentation/Use-Case-Diagrams.md');
preg_match_all('/\]\(Diagrams\/([^)]+)\.png\)/', $index, $im);
$linked = array_unique($im[1] ?? []);
$onDisk = array_map(static fn (string $f): string => (string) substr($f, 0, -4), $diskPng);
$unlinked = array_values(array_diff($onDisk, $linked));
$ghost = array_values(array_diff($linked, $onDisk));
if ($unlinked !== [] || $ghost !== [] || count($linked) === 0) {
    echo str_pad('index-links-every-figure', 42).'BAD   unlinked ['.implode(',', $unlinked).'] ghosts ['.implode(',', $ghost)."]\n";
    $bad++;
} else {
    echo str_pad('index-links-every-figure', 42).'ok    '.count($linked).' figures on disk, all linked, no dead link'."\n";
}

// (6) a bucket that matches nothing: the plan must refuse before a single figure is drawn.
$bucketFrom = "pick: (n) => n.startsWith('use-case-actor-')";
$legs++;
if (substr_count($original, $bucketFrom) === 1) {
    $f6 = 'storage/app/scratch/lane208-mutant-dead-bucket.mjs';
    file_put_contents($f6, str_replace($bucketFrom, "pick: (n) => n.startsWith('use-case-actor-ZZZ')", $original));
    $copies[] = $f6;
    [$code, $out] = $run($f6, '');
    if ($code === 0 || ! str_contains($out, 'no bucket of the index') || str_contains($out, 'wrote 43 figure(s)')) {
        echo str_pad('dead-bucket-refuses-before-render', 42)."BAD   exit {$code}: the plan must stop the run before drawing\n";
        $bad++;
    } else {
        echo str_pad('dead-bucket-refuses-before-render', 42)."ok    exit {$code}, 28 figures named, nothing rendered\n";
    }
} else {
    echo str_pad('dead-bucket-refuses-before-render', 42)."BAD   bucket anchor not unique — the leg's age, not a defect\n";
    $bad++;
}

echo "\n=== the label reaches the page ===\n";
// WITHDRAWN first cut, recorded rather than hidden: this leg re-spelled the delivered |"«include»"| back into the
// historical |"<<include>>"| — the spelling that produced an EMPTY BOX when the lane started — and expected the
// artefact check to refuse. The run drew all 43 figures and passed, because on this mermaid that label now renders.
// A leg that cannot fail is decoration, so it is stated here and replaced by a leg that can: the comparison loop is
// handed a label requirement the drawing cannot possibly satisfy, and must refuse naming it. That proves the loop
// runs and its refusal path is reachable, which is all an instrument leg can honestly claim.
$legs++;
$unsat = '  for (const [token, count] of wanted) {';
if (substr_count($original, $unsat) === 1) {
    $mg = 'storage/app/scratch/lane208-mg-unsatisfiable.mjs';
    $plant = "wanted.set('zz-unreachable-token', 1);"."\n".'  for (const [token, count] of wanted) {';
    file_put_contents($mg, str_replace($unsat, $plant, $original));
    $ocopy = 'storage/app/scratch/lane208-mg-out';
    @mkdir($ocopy, 0777, true);
    $mgout = [];
    $mgcode = 0;
    exec('cd '.escapeshellarg($root).' && node '.escapeshellarg($mg).' --out='.escapeshellarg($ocopy).' 2>&1', $mgout, $mgcode);
    $mgtext = preg_replace('/\e\[[0-9;]*m/', '', implode("\n", $mgout));
    if ($mgcode === 0 || ! str_contains($mgtext, 'did not reach the page') || ! str_contains($mgtext, 'zz-unreachable-token')) {
        echo str_pad('label-loop-refuses-on-an-unmet-token', 42)."BAD   exit {$mgcode}: the artefact comparison never refused, or refused without naming the token\n";
        echo '        '.str_replace("\n", ' / ', mb_substr($mgtext, -240))."\n";
        $bad++;
    } else {
        echo str_pad('label-loop-refuses-on-an-unmet-token', 42)."ok    exit {$mgcode}, the drawn SVG is weighed against every asserted label\n";
    }
    foreach (glob($ocopy.'/*') ?: [] as $x) {
        unlink($x);
    }
    @rmdir($ocopy);
    if (is_file($mg)) {
        unlink($mg);
    }
    // The index is written beside the output directory, so a throwaway out dir leaves a throwaway page behind.
    if (is_file('storage/app/scratch/Use-Case-Diagrams.md')) {
        unlink('storage/app/scratch/Use-Case-Diagrams.md');
    }
    $mleft = array_values(array_filter(glob('storage/app/scratch/lane208-mg-*') ?: []));
    $legs++;
    if ($mleft !== [] || is_file('storage/app/scratch/Use-Case-Diagrams.md')) {
        echo str_pad('mutant-copies-swept', 42).'BAD   still present: '.implode(', ', array_map('basename', $mleft))."\n";
        $bad++;
    } else {
        echo str_pad('mutant-copies-swept', 42)."ok    no mutant generator, no mutant output, no stray index\n";
    }
} else {
    echo str_pad('label-loop-refuses-on-an-unmet-token', 42)."BAD   anchor not unique — the leg's age, not a defect\n";
    $bad++;
}
echo "\n=== copies left behind ===\n";
$legs++;
$left = glob('storage/app/scratch/lane208-mutant-*.mjs') ?: [];
$cleanup();
$copies = [];
$after = glob('storage/app/scratch/lane208-mutant-*.mjs') ?: [];
if ($after !== []) {
    echo str_pad('copies-deleted', 42).'BAD   still on disk: '.implode(', ', array_map('basename', $after))."\n";
    $bad++;
} else {
    echo str_pad('copies-deleted', 42)."ok    planted ".count($left)." copy(ies); none survives the run\n";
}

printf("\n%s: %d leg(s), %d bad\n", $bad === 0 ? 'ALL PROVABLE' : 'NOT ALL PROVABLE', $legs, $bad);
exit($bad === 0 ? 0 : 1);
