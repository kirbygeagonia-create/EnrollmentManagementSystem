<?php

/**
 * Mutation-proof for retire-superseded-enrollments.php.
 *
 * The script deletes nothing but flips seven statuses, and its justification is one sentence: each of these
 * enrollments is superseded by a LATER enrollment for the same student. If the guard for that sentence were
 * broken, the script would drop a student's only record and their desk queue would go quiet with no trace. So
 * each mutant below must be refused, and refused by the guard named — not by luck.
 *
 * The real dump path is passed so the dump guard is satisfied everywhere except in the one mutant that tests it.
 * --apply is never passed, so no mutant can write.
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$dir = $root.'/storage/app/scratch';
$dump = 'C:/Users/ADMIN/ems-backups/ems-pre-lane36-retire-2026-10-15.sql';
$base = file_get_contents($dir.'/retire-superseded-enrollments.php');

str_contains($base, "\$apply = in_array('--apply', \$argv, true);") || exit("REFUSE: the script no longer gates --apply\n");
is_file($dump) || exit("REFUSE: the reference dump is gone, so every mutant below would fail for the wrong reason\n");

$mutants = [
    // an enrollment with no later record for its student: DEMO-2026-001's term-11 enrollment 53 has no twin
    'twin-guard' => [
        ['$ids = [595, 597, 654, 655, 656, 657, 658];', '$ids = [53];'],
        'NO later enrollment',
    ],
    // an enrollment that is not `enrolled`: 596 is DEMO-2026-002's term-11 record and it is `pending`, so the
    // state machine has no legal transition for it. The twin guard is over-determined (a later term AND a
    // different id), which is why the first version of this mutant — relaxing one half — could not fail;
    // a guard that cannot be broken by weakening one clause is not the guard worth testing here.
    'status-guard' => [
        ['$ids = [595, 597, 654, 655, 656, 657, 658];', '$ids = [596];'],
        'not enrolled',
    ],
    // no dump at all
    'dump-missing' => [
        ["if (\$dumpArg === '' || ! is_file(\$dumpArg)", "if (true || \$dumpArg === '' || ! is_file(\$dumpArg)"],
        'no verified dump',
    ],
    // a dump that is present but does not hold the table being changed
    'dump-wrong-table' => [
        ["str_contains(file_get_contents(\$dumpArg), 'CREATE TABLE `enrollments`')",
         "str_contains(file_get_contents(\$dumpArg), 'CREATE TABLE `not_a_real_table`')"],
        'does not contain the enrollments table',
    ],
    // no actor able to perform the desk action
    'actor-missing' => [
        ["->where('officeId', OfficeId::Registrar->value)", "->where('officeId', 999)"],
        'no live account holds enrollment.drop',
    ],
    // a reason shorter than the desk's own validation floor — proves the script cannot write a drop the
    // controller would reject, since the reason is the audit trail of the act
    'reason-too-short' => [
        ["\$reason = 'Superseded demo enrollment: the same student holds a later term record, and this copy was left by '\n    .'the move of the demo dataset. Ruled on 2026-10-15 after the record-state review found the two notes were '\n    .'counting the same seven rows.';",
         "\$reason = 'short';"],
        'reason too short',
    ],
];

$fails = 0;
foreach ($mutants as $name => [$pair, $expected]) {
    [$from, $to] = $pair;
    if (substr_count($base, $from) !== 1) {
        printf("HARNESS WRONG %-24s anchor seen %d times: %s\n", $name, substr_count($base, $from), substr($from, 0, 50));
        $fails++;

        continue;
    }
    $code = str_replace($from, $to, $base);
    // same directory as the real script, so its dirname()-derived $root stays correct
    $file = $dir.'/mutant-retire-'.$name.'.php';
    file_put_contents($file, $code);
    $out = [];
    $rc = 0;
    exec(escapeshellarg($php).' '.escapeshellarg($file).' '.escapeshellarg($dump).' 2>&1', $out, $rc);
    $joined = implode("\n", $out);
    @unlink($file);

    if ($rc !== 0 && str_contains($joined, $expected)) {
        printf("caught    %-26s — %s\n", $name, $expected);
    } else {
        $fails++;
        printf("SURVIVED  %-26s exit=%d expected=%s\n%s\n", $name, $rc, $expected,
            str_replace("\n", "\n          ", substr($joined, 0, 400)));
    }
}

// control: the unmutated script must still report a clean dry run of all seven
$out = [];
$rc = 0;
exec(escapeshellarg($php).' '.escapeshellarg($dir.'/retire-superseded-enrollments.php').' '.escapeshellarg($dump).' 2>&1', $out, $rc);
$joined = implode("\n", $out);
if ($rc === 0 && str_contains($joined, '7 of 7 enrollments pass every guard') && str_contains($joined, 'DRY RUN')) {
    echo "control     clean — 7 of 7 pass, dry run, exit 0\n";
} else {
    $fails++;
    echo "CONTROL WRONG: the unmutated script is not a clean dry run\n".str_replace("\n", "\n  ", $joined)."\n";
}

printf("\n%s\n", $fails === 0 ? 'ALL MUTANTS CAUGHT — the retirement guards are live' : $fails.' problem(s) — do not apply');
exit($fails === 0 ? 0 : 1);
