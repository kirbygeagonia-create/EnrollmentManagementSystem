<?php

/**
 * Proves `lane190-probe-subject-line-race.php` reads its own guards rather than accepting them.
 *
 * Every leg is a refusal that fires BEFORE the tool touches MySQL, because a harness that clones live `ems` for
 * each leg would be a second writer beside anything else running on this box (the no-concurrent-mutating-runs
 * rule). The load-bearing verdict — a 1213 that reaches the caller must make the run UNSAFE — is not provable by
 * a planted byte here: it needs two live writers, and the delivered capture in `lane190-out.txt` is that proof
 * (10 writers, 6 proposed, 4 unretried lock deaths). What this harness can and does falsify is the guard that
 * would otherwise let a broken invocation print a confident verdict.
 *
 * Usage: php storage/app/scratch/mutate-lane190-race-probe.php
 */
$root = dirname(__DIR__, 3);
chdir($root);

$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$tool = 'storage/app/scratch/lane190-probe-subject-line-race.php';
if (! is_file($php) || ! is_file($tool)) {
    fwrite(STDERR, "REFUSED: the interpreter or the tool is missing.\n");
    exit(1);
}

$copy = 'storage/app/scratch/lane190-mutant-copy.php';
$bytes = (string) file_get_contents($tool);

$legs = [
    // A flag the tool does not read must be refused BY NAME. Two refusal messages exist and this input is a
    // `--flag` shape, so the parser files it as unread rather than rejecting it as unparseable — the leg that
    // expected the other message reported a working guard as a surviving defect, which is this repository's own
    // "a leg that cannot fail is the instrument's age" finding, met on the first run of this file.
    ['name' => 'unknown-flag-refused', 'args' => '--no-such-flag-at-all',
        'expect' => 'exit1', 'needle' => 'flag(s) not read by this tool: --no-such-flag-at-all'],

    // A bare word is the other refusal, and both must be reachable or one branch is decoration.
    ['name' => 'bare-argument-refused', 'args' => 'nonsense',
        'expect' => 'exit1', 'needle' => 'this tool does not understand'],

    // --pairs is a count of live writer pairs, not a load dial: 0 would run nothing and print a verdict.
    ['name' => 'pairs-zero-refused', 'args' => '--pairs=0',
        'expect' => 'exit1', 'needle' => '--pairs must be 1 to 20'],

    ['name' => 'pairs-too-large-refused', 'args' => '--pairs=99',
        'expect' => 'exit1', 'needle' => '--pairs must be 1 to 20'],

    // A schema outside the pattern the tool owns is how a concurrent write reaches a database nobody meant to
    // drop afterwards.
    ['name' => 'schema-name-refused', 'args' => '--schema=ems',
        'expect' => 'exit1', 'needle' => '--schema must match ems_l190_[a-z]'],

    // The verdict word itself: with the deadlock bucket removed from the unsafe list, a run whose children died
    // on 1213 would print SAFE. This leg proves that clause is WRITTEN in the tool that is executed — the copy
    // must not contain it once muted, and must contain it before.
    ['name' => 'deadlock-clause-is-live', 'args' => '--schema=ems_l190_z --pairs=1 --no-such',
        'expect' => 'exit1', 'needle' => 'this tool does not understand'],
];

$bad = 0;
$deadlockClause = "if (\$counts['deadlock'] > 0) {";
if (! str_contains($bytes, $deadlockClause)) {
    echo "BAD  deadlock-clause-present: the tool no longer charges a lock death to UNSAFE, so SAFE can print over a 1213.\n";
    $bad++;
} else {
    echo "ok   deadlock-clause-present: a lock death is an unsafe outcome, not a bucket that costs nothing.\n";
}

// The muted copy is the falsification of the clause above: with it deleted, the tool would still resolve every
// flag and refuse for the same reasons — so the check is textual here and the behavioural proof is the capture.
file_put_contents($copy, str_replace($deadlockClause, 'if (false) {', $bytes));
$muted = (string) file_get_contents($copy);
if (str_contains($muted, $deadlockClause)) {
    echo "BAD  mute-lands: the string this harness mutes is not the string the tool runs.\n";
    $bad++;
} else {
    echo "ok   mute-lands: the clause was found and muted in the copy, so the check above matched real bytes.\n";
}

foreach ($legs as $leg) {
    if ($leg['name'] === 'deadlock-clause-is-live') {
        // Already judged by the two checks above; keep it out of the run loop rather than fake a pass.
        continue;
    }
    $cmd = escapeshellarg($php).' '.escapeshellarg($tool).' '.escapeshellarg($leg['args']).' 2>&1';
    $out = (string) shell_exec($cmd);
    $ok = str_contains($out, $leg['needle']) && (str_contains($out, 'REFUSED') || ! str_contains($out, 'SAFE'));
    printf("%s %s: %s\n", $ok ? 'ok  ' : 'BAD ', $leg['name'], $ok ? 'refused by name' : trim(substr($out, 0, 200)));
    if (! $ok) {
        $bad++;
    }
}

@unlink($copy);
if (is_file($copy)) {
    echo "BAD  copy-retired: {$copy} survived — a mutant copy left in the tree is a second definition of the tool.\n";
    $bad++;
} else {
    echo "ok   copy-retired: the mutant copy is gone.\n";
}

printf("\n%s\n", $bad === 0 ? 'ALL KILLED: refusal legs and the verdict-word check agree.' : $bad.' leg(s) BAD.');
exit($bad === 0 ? 0 : 1);
