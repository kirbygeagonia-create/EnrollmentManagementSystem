<?php

/**
 * Mutation-proof for refresh-docx-pass118.php.
 *
 * Pass 118 rewrites four claims in §18.6 of the authority docx plus one register citation, through
 * docx-edit-engine.php. The engine refuses to write when a needle is missing, duplicated, or lands in the
 * wrong container — but "refuses" is only worth what a mutant can demonstrate, and this pass edits prose whose
 * surrounding runs repeat hundreds of times ("11", "cancelled", a bare "."), which is exactly the material
 * where a wrong offset does damage silently.
 *
 * Mutants run as copies placed IN THE SAME DIRECTORY as the real pass, because the pass script resolves both
 * the repository root and the engine's path from its own location — a copy one folder deeper would fail for
 * the wrong reason, which is the lesson the pass-117 harness already learned.
 *
 * Nothing here writes: --apply is never passed, and the engine's only write path is behind it.
 *
 * usage: php storage/app/scratch/mutate-docx-pass118.php
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$dir = $root.'/storage/app/scratch';
str_contains($dir, '/storage/app/scratch') && is_dir($dir) || exit("harness root resolved to {$dir}, not the scratch folder\n");
$pass = file_get_contents($dir.'/refresh-docx-pass118.php');

$mutants = [
    // a needle that does not exist in the document at all (the register-row citation is a real, unique needle
    // in the pass source — so this mutant is that string with its numbers changed, which the document has not)
    'needle-absent' => [
        ["'PrintService.php:23 and :37-38 (ChromiumLocator::locate, then setChromePath).'",
         "'PrintService.php:23 and :37-39 (ChromiumLocator::locate, then setChromePath).'"],
        'FAIL',
    ],
    // a needle that repeats: the whole point of the engine's uniqueness rule
    'needle-duplicated' => [
        ["'Nothing links '", "'The '"],
        'occurrences, expected 1',
    ],
    // a paragraph anchor that is gone: the rewrite would have nothing to land on
    'para-anchor-absent' => [
        ["'is legacy: nothing sets it, and there is no cancel action on'",
         "'is legacy: nothing sets it, and there is no cancel action on the moon'"],
        'FAIL para',
    ],
    // markup smuggled into a replacement: closing and reopening the same elements leaves every container
    // count unchanged and the XML still well-formed, so only an outright refusal is a guard
    'markup-smuggled' => [
        ["'One medical fact, one source — and it is not a clinical one: '",
         "'One medical fact, one source:</w:p></w:body><w:p>'"],
        'replacement carries markup',
    ],
    // an edit that changes nothing: the pass would report eight targets resolved and correct §18.6 while
    // leaving the false sentence exactly as it stands. The engine now refuses a replacement identical to its
    // needle. What NO guard can see is the near-miss — the false claim kept and a clause appended — because
    // the stale-needle check stands down when a replacement legitimately keeps the sentence it is editing.
    // That case belongs to the meaning sweep and to reading, which is why both were done before this pass.
    'stale-claim-kept' => [
        ["' are the names of PERMISSIONS, and the desk abilities that used to share them were renamed clinic.viewAtDesk and id.viewAtDesk on 2026-10-01, so Spatie’s Gate::before can no longer grant an ability by permission and skip the office scoping the policy writes (§22.4, X-1 closed; AuthServiceProvider.php:180 and :198, pinned by AbilityNameCollisionTest).'",
         "' are used as ability names that collide with permission names, so the office scoping in those policies can be bypassed (§22.4, X-1).'"],
        'identical to the needle',
    ],
    // a citation the document has never heard of: prose must not name a file the sweep cannot check
    'unknown-file-cited' => [
        ["AuthServiceProvider.php:180 and :198", "AuthServiceProvider.php:180 and :198, ClinicGhost.php:99"],
        'DRY RUN',
    ],
];

$fails = 0;
foreach ($mutants as $name => [$pair, $expectedHint]) {
    [$from, $to] = $pair;
    if (substr_count($pass, $from) < 1) {
        printf("BROKEN-HARNESS %-24s anchor not in the pass source: %s\n", $name, substr($from, 0, 50));
        $fails++;

        continue;
    }
    $code = str_replace($from, $to, $pass);
    $file = $dir.'/mutant-p118-'.$name.'.php';
    file_put_contents($file, $code);

    $out = [];
    $rc = 0;
    exec(escapeshellarg($php).' '.escapeshellarg($file).' 2>&1', $out, $rc);
    $joined = implode("\n", $out);

    // A mutant is caught when the run refuses: a FAIL line, a non-zero exit, or a refusal that names the
    // guard. The last mutant is the deliberate exception — it stays a valid dry run, and is checked for that.
    if ($name === 'unknown-file-cited') {
        $ok = $rc === 0 && str_contains($joined, 'DRY RUN');
        printf($ok ? "expected-pass %-23s (a bad FILE name is not this engine's business; the sweep catches it)\n"
            : "SURVIVED-WRONG %-20s the pass refused for a reason other than the one under test\n", $ok ? '' : $name);
        $ok || $fails++;
        @unlink($file);

        continue;
    }

    $caught = $rc !== 0 || stripos($joined, 'FAIL') !== false || stripos($joined, 'refus') !== false
        || stripos($joined, 'still present') !== false;
    if ($caught) {
        printf("caught    %-24s %s\n", $name, preg_replace('/\s+/', ' ', substr(strstr($joined, 'FAIL') ?: $joined, 0, 96)));
    } else {
        $fails++;
        printf("SURVIVED  %-24s exit=%d\n%s\n", $name, $rc, str_replace("\n", "\n          ", substr($joined, 0, 400)));
    }
    @unlink($file);
}

// Control: the pass as written must resolve all eight targets and stay a dry run.
$out = [];
$rc = 0;
exec(escapeshellarg($php).' '.escapeshellarg($dir.'/refresh-docx-pass118.php').' 2>&1', $out, $rc);
$joined = implode("\n", $out);
if ($rc === 0 && str_contains($joined, '8 targets resolved uniquely') && str_contains($joined, 'DRY RUN')) {
    echo "control     clean — 8 targets unique, dry run, exit 0\n";
} else {
    $fails++;
    echo "CONTROL WRONG: the untouched pass is not a clean dry run\n".str_replace("\n", "\n  ", $joined)."\n";
}

printf("\n%s\n", $fails === 0 ? 'ALL MUTANTS CAUGHT — pass 118 guards are live' : $fails.' problem(s) — do not apply pass 118');
exit($fails === 0 ? 0 : 1);
