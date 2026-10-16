<?php

/**
 * Mutation-proof for lane37b-repo-citation-sweep.php.
 *
 * The sweep reads the working tree, so its mutants are planted in the tree for exactly one run and restored
 * byte-for-byte afterwards — asserted by hash, not by trust. Two classes are provoked, the control is the
 * unmutated tree, and the script restores even if the sweep dies: no tracked file is ever left holding a
 * planted defect.
 *
 *   1  WRONG-LOCATION — the citation this whole lane is about, in the reverse direction: a real id
 *      ("§25 P-14") retargeted at a chapter that does not hold it ("§28 P-14").
 *   2  UNRESOLVED — a pointer to a section that exists in no corpus ("§77.3", chapter 77 has nothing).
 *
 * usage: php storage/app/scratch/mutate-lane37b-sweep.php
 */

$root = dirname(__DIR__, 3);
$php = PHP_BINARY;
$sweep = str_replace('/', '\\', $root.'/storage/app/scratch/lane37b-repo-citation-sweep.php');
$file = $root.'/app/Console/Commands/AuditRecordStates.php';
$deploy = $root.'/DEPLOYMENT.md';

$originals = [$file => file_get_contents($file), $deploy => file_get_contents($deploy)];
$restored = false;

$restore = function () use ($originals, &$restored): void {
    foreach ($originals as $path => $bytes) {
        file_put_contents($path, $bytes);
    }
    $restored = true;
};

register_shutdown_function(function () use ($restore, &$restored): void {
    if (! $restored) {
        fwrite(STDERR, "\nshutdown: the planted mutant was still in the tree — restoring now\n");
        $restore();
    }
});

$run = function () use ($php, $sweep, $root): array {
    $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open([$php, $sweep], $desc, $pipes, $root);
    $out = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
    foreach ($pipes as $p) {
        fclose($p);
    }

    return [$out, proc_close($proc)];
};

$parse = function (string $out): array {
    preg_match('/UNRESOLVED (\d+), NO-ID (\d+), WRONG-LOCATION (\d+)/', $out, $m);

    return [(int) ($m[1] ?? -1), (int) ($m[2] ?? -1), (int) ($m[3] ?? -1)];
};

$fails = 0;
$verdict = function (string $name, bool $ok, string $detail) use (&$fails): void {
    printf("  %-9s %-18s %s\n", $ok ? 'PASS' : 'SURVIVED', $name, $detail);
    if (! $ok) {
        $fails++;
    }
};

echo "=== lane 37b sweep mutation-proof (mutants planted in the tree, restored by hash) ===\n";

[$u0, $i0, $w0] = $parse($run()[0]);
$baseline = "UNRESOLVED {$u0}, NO-ID {$i0}, WRONG-LOCATION {$w0}";
printf("  baseline: %s\n", $baseline);

// --- mutant 1: retarget a real citation at the wrong chapter
$src = $originals[$file];
$n1 = substr_count($src, '§25 P-14');
if ($n1 < 1) {
    $verdict('mutant-1-build', false, 'no "§25 P-14" citation in AuditRecordStates.php to retarget');
} else {
    file_put_contents($file, str_replace('§25 P-14', '§28 P-14', $src));
    [$u, $i, $w] = $parse($run()[0]);
    $verdict('wrong-location', $w > $w0, "{$n1} citation(s) retargeted at §28; WRONG-LOCATION {$w0} → {$w}");
    $restore();
}

// --- mutant 2: pointers that resolve in no corpus, including one that names a document explicitly
$src2 = $originals[$deploy];
$anchor = 'A report tool must not decide those over the';
$n2 = substr_count($src2, $anchor);
if ($n2 !== 1) {
    $verdict('mutant-2-build', false, "anchor occurs {$n2} time(s), expected 1");
} else {
    // §77.3 names nothing; "(audit §9.9)" names the audit document, which has no §9.9 — the second is the
    // case a "resolve at home first" rule would have hidden, because DEPLOYMENT.md has its own chapter 9.
    file_put_contents($deploy, str_replace($anchor, 'See §77.3 first, or (audit §9.9). '.$anchor, $src2));
    [$u, $i, $w] = $parse($run()[0]);
    $verdict('unresolved', $u >= $u0 + 2, 'planted §77.3 and (audit §9.9); UNRESOLVED '.$u0.' → '.$u.' (wanted ≥ '.($u0 + 2).')');
    $restore();
}

// --- the tree must be exactly as it was
foreach ($originals as $path => $bytes) {
    $same = file_get_contents($path) === $bytes;
    $verdict('restored', $same, basename($path).(strlen($bytes) ? ' ('.strlen($bytes).' bytes)' : ''));
}

printf("\n%s\n", $fails === 0
    ? 'both classes fire on a planted defect and the tree came back byte-identical'
    : "{$fails} mutant(s) SURVIVED — the repo sweep's silence is not yet evidence");
exit($fails === 0 ? 0 : 1);
